<?php

declare(strict_types=1);

use Stillwired\Domain\Evidence;
use Stillwired\Domain\FlowLab;
use Stillwired\Domain\Verifier;

if (!class_exists(FlowLab::class)) {
    require dirname(__DIR__) . '/bootstrap.php';
}

$tests = [];
$tests['human revocation leaves independent webhook and export running'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount();
    $r = (new Verifier())->verify($l, 'P1');
    assertSame('EXPOSED', $r['decision']);
    assertSame(2, count($r['deliveries']));
};
$tests['stopping both routes prevents new deliveries'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount();
    $l->stopFlow('webhook'); $l->stopFlow('scheduled_export');
    $r = (new Verifier())->verify($l, 'P2');
    assertSame('VERIFIED', $r['decision']);
    assertSame(0, count($r['deliveries']));
};
$tests['unavailable source must not yield verified'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount();
    $l->stopFlow('webhook'); $l->stopFlow('scheduled_export');
    $l->setSourceReachable('webhook', false);
    assertSame('INCONCLUSIVE', (new Verifier())->verify($l, 'P3')['decision']);
};
$tests['known active flow means exposed even when another is stopped'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount(); $l->stopFlow('webhook');
    $r = (new Verifier())->verify($l, 'P4');
    assertSame('EXPOSED', $r['decision']);
    assertSame('scheduled_export', $r['deliveries'][0]['flow']);
};
$tests['stopping a route is idempotent'] = static function (): void {
    $l = new FlowLab();
    assertSame(true, $l->stopFlow('webhook'));
    assertSame(false, $l->stopFlow('webhook'));
};
$tests['reactivated route fails next verification'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount();
    $l->stopFlow('webhook'); $l->stopFlow('scheduled_export');
    $v = new Verifier();
    assertSame('VERIFIED', $v->verify($l, 'P6A')['decision']);
    $l->startFlow('webhook');
    assertSame('EXPOSED', $v->verify($l, 'P6B')['decision']);
};
$tests['a missing source is inconclusive even with zero deliveries'] = static function (): void {
    $l = new FlowLab(); $l->revokeHumanAccount();
    $l->stopFlow('webhook'); $l->stopFlow('scheduled_export');
    $l->setSourceReachable('scheduled_export', false);
    $r = (new Verifier())->verify($l, 'P7');
    assertSame(0, count($r['deliveries']));
    assertSame('INCONCLUSIVE', $r['decision']);
};
$tests['tampered evidence fails checksum'] = static function (): void {
    $path = tempnam(sys_get_temp_dir(), 'sw-proof-');
    if ($path === false) throw new RuntimeException('tempnam failed');
    Evidence::write($path, ['decision' => 'EXPOSED']);
    assertSame(true, Evidence::checksumValid($path));
    file_put_contents($path, '{"decision":"VERIFIED"}');
    assertSame(false, Evidence::checksumValid($path));
    unlink($path); unlink($path . '.sha256');
};

function assertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$passed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        ++$passed;
        echo "PASS: $name\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL: $name — {$e->getMessage()}\n");
        exit(1);
    }
}
echo "RESULT $passed/" . count($tests) . " PASSED\n";
