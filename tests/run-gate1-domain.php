<?php

declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Stillwired\Lab\StateStore;
use Stillwired\Lab\ApprovalStore;

$project = sys_get_temp_dir().'/sw-g1-'.bin2hex(random_bytes(5));
$store = new StateStore($project);
$check = static function (bool $ok, string $name): void { if (!$ok) throw new RuntimeException('FAIL: '.$name); echo "PASS: $name\n"; };
try {
    $store->reset();
    $check($store->humanActive(), 'reset creates active human');
    $store->revokeHuman();
    $check(!$store->humanActive() && $store->observe('webhook')['state'] === 'active', 'human revocation does not stop webhook');
    $a = $store->emit('webhook','SW-FIRST-001');
    $check($a['delivered'] && count($store->delivered('SW-FIRST-001')) === 1, 'source emits persistent canary delivery');
    $store2 = new StateStore($project);
    $check(count($store2->delivered('SW-FIRST-001')) === 1, 'new process-style instance sees persisted sink');
    $approval = new ApprovalStore($store);
    $token = $approval->issue('webhook');
    $check(!$approval->consume('scheduled_export', $token), 'approval cannot be used on another flow');
    $check($approval->consume('webhook', $token) && !$approval->consume('webhook', $token), 'approval token is consumed exactly once');
    $check($store->stop('webhook') && !$store->stop('webhook'), 'stop idempotence with persistent state');
    $check(!$store->emit('webhook','SW-SECOND-002')['delivered'], 'disabled source emits no canary');
    $store->setOffline('scheduled_export', true);
    $unavailable = false;
    try { $store->observe('scheduled_export'); } catch (RuntimeException) { $unavailable = true; }
    $check($unavailable, 'injected source unavailability fails closed');
    $store->reactivate('webhook');
    $check($store->observe('webhook')['state'] === 'active', 'reactivation is detectable');
    echo "RESULT 10/10 PASSED\n";
} finally {
    $files = glob($project.'/var/lab/*') ?: [];
    foreach ($files as $file) @unlink($file);
    @rmdir($project.'/var/lab'); @rmdir($project.'/var'); @rmdir($project);
}
