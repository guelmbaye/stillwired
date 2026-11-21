<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Stillwired\Domain\Evidence;
use Stillwired\Domain\FlowLab;
use Stillwired\Domain\Verifier;

$command = $argv[1] ?? 'help';
if ($command === 'test') {
    require dirname(__DIR__) . '/tests/run.php';
    exit(0);
}
if ($command === 'verify-evidence') {
    $path = $argv[2] ?? dirname(__DIR__) . '/var/evidence/after.json';
    $valid = Evidence::checksumValid($path);
    echo $valid ? "CHECKSUM_OK\n" : "CHECKSUM_FAIL\n";
    exit($valid ? 0 : 1);
}
if ($command !== 'demo') {
    echo "Usage: php bin/stillwired.php demo|test|verify-evidence [path]\n";
    exit($command === 'help' ? 0 : 2);
}
$lab = new FlowLab();
$verifier = new Verifier();
$lab->revokeHumanAccount();
$before = $verifier->verify($lab, 'SW-CANARY-BEFORE-001');
$before['phase'] = 'account-revoked-but-integrations-running';
$lab->stopFlow('webhook');
$lab->stopFlow('scheduled_export');
$after = $verifier->verify($lab, 'SW-CANARY-AFTER-002');
$after['phase'] = 'after-independent-integration-shutdown';
$base = dirname(__DIR__) . '/var/evidence/';
Evidence::write($base . 'before.json', $before);
Evidence::write($base . 'after.json', $after);
echo 'Human account: REVOKED' . PHP_EOL;
echo 'BEFORE: ' . $before['decision'] . '; new deliveries=' . count($before['deliveries']) . PHP_EOL;
echo 'AFTER: ' . $after['decision'] . '; new deliveries=' . count($after['deliveries']) . PHP_EOL;
echo "Evidence: var/evidence/{before,after}.json (+ separate SHA-256)\n";
exit($before['decision'] === 'EXPOSED' && count($before['deliveries']) === 2 && $after['decision'] === 'VERIFIED' && count($after['deliveries']) === 0 ? 0 : 1);
