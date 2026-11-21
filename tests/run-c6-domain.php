<?php

declare(strict_types=1);

require __DIR__.'/../src/NativeWebhook/GitHubSignature.php';
require __DIR__.'/../src/NativeWebhook/GitHubReceiptStore.php';

use Stillwired\NativeWebhook\GitHubSignature;
use Stillwired\NativeWebhook\GitHubReceiptStore;

$pass = 0;
function check(bool $condition, string $name): void
{
    global $pass;
    if (!$condition) { throw new RuntimeException('FAIL: '.$name); }
    ++$pass;
    echo "PASS: $name\n";
}
function fails(callable $cb): bool
{
    try { $cb(); return false; } catch (Throwable $e) { return true; }
}

$secret = 'test-local-only-secret-with-32-characters';
$body = '{"repository":{"full_name":"guelmbaye/stillwired-webhook-lab"},"zen":"test"}';
$signature = 'sha256='.hash_hmac('sha256', $body, $secret);
check(GitHubSignature::isValid($body, $signature, $secret), 'correct signature accepts exact body');
check(!GitHubSignature::isValid($body.' ', $signature, $secret), 'tampered body is rejected');
check(!GitHubSignature::isValid($body, $signature, 'wrong-secret'), 'wrong secret is rejected');
check(!GitHubSignature::isValid($body, '', $secret), 'absent signature is rejected');
check(!GitHubSignature::isValid($body, $signature, ''), 'empty secret never bypasses HMAC');
check(!GitHubSignature::isValid($body, 'sha256=bad', $secret), 'malformed signature is rejected');

$dir = sys_get_temp_dir().'/sw_c6_'.bin2hex(random_bytes(5));
mkdir($dir, 0700, true);
$store = new GitHubReceiptStore($dir);
$guid = 'a1f31870-cded-484f-8c20-f20539c21110';
$payload = ['repository' => 'guelmbaye/stillwired-webhook-lab', 'raw_body_sha256' => hash('sha256', $body), 'github_hook_id' => '714914745'];
check($store->record($guid, 'push', $payload) === 'new', 'first signed receipt stored');
check($store->record($guid, 'push', $payload) === 'duplicate', 'redelivery deduplicated');
check($store->audit()['valid'] && $store->audit()['count'] === 1, 'one receipt remains after duplicate');
check(fails(fn () => $store->record($guid, 'push', [...$payload, 'raw_body_sha256' => str_repeat('a', 64)])), 'GUID collision with another payload rejected');
check(fails(fn () => $store->record($guid, 'push', [...$payload, 'raw_body_sha256' => 'oops'])), 'malformed sha rejected');
$guid2 = 'b1f31870-cded-484f-8c20-f20539c21110';
check($store->record($guid2, 'ping', $payload) === 'new' && $store->audit()['count'] === 2, 'second receipt extends hash chain');
$ledger = $dir.'/var/evidence/c6-github-receiver.jsonl';
check(!str_contains(file_get_contents($ledger), 'zen') && !str_contains(file_get_contents($ledger), 'test-local-only-secret'), 'no raw body or signature secret persisted');
$old = file_get_contents($ledger);
file_put_contents($ledger, str_replace('guelmbaye/stillwired-webhook-lab', 'attacker/replaced', $old));
check(!$store->audit()['valid'], 'local hash-chain corruption detected');
check(fails(fn () => $store->record('c1f31870-cded-484f-8c20-f20539c21110', 'push', $payload)), 'tampered ledger refuses additional receipts');
file_put_contents($ledger, $old);
check($store->audit()['valid'] && $store->audit()['count'] === 2, 'valid ledger restored');

@unlink($ledger);
@rmdir($dir.'/var/evidence');
@rmdir($dir.'/var');
@rmdir($dir);
echo "C6_NATIVE_DOMAIN_PASS {$pass}/{$pass}\n";
