<?php

declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use Stillwired\Lab\StateStore;

$project = sys_get_temp_dir().'/sw-competitive-'.bin2hex(random_bytes(5));
$store = new StateStore($project);
$pass = 0;
$check = static function (bool $ok, string $label) use (&$pass): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $pass++;
    echo 'PASS: '.$label."\n";
};
try {
    $store->reset();
    $store->revokeHuman();
    $check(!$store->humanActive(), 'human principal revoked');
    foreach (StateStore::FLOWS as $flow) $store->setStaleReport($flow, true);
    $check($store->observe('webhook')['state'] === 'disabled' && $store->observe('scheduled_export')['state'] === 'disabled', 'both metadata endpoints report disabled');
    $w = $store->emit('webhook', 'SW-FALSEGREEN-001');
    $e = $store->emit('scheduled_export', 'SW-FALSEGREEN-001');
    $check($w['delivered'] === true && $e['delivered'] === true, 'real synthetic deliveries contradict metadata');
    $check(count($store->delivered('SW-FALSEGREEN-001')) === 2, 'two distinct deliveries persisted in sink');
    $store->stop('webhook');
    $store->stop('scheduled_export');
    $check(!$store->emit('webhook', 'SW-AFTERSTOP-002')['delivered'] && !$store->emit('scheduled_export', 'SW-AFTERSTOP-002')['delivered'], 'stopped flows cannot deliver new canaries');
    $check(count($store->delivered('SW-AFTERSTOP-002')) === 0, 'zero post-stop deliveries persisted');
    $store->setStaleReport('webhook', false);
    $store->reactivate('webhook');
    $check($store->observe('webhook')['state'] === 'active', 'clearing fault exposes reactivated flow');
    $store->reset();
    $check($store->observe('webhook')['state'] === 'active', 'reset clears adversarial injection');
    echo "COMPETITIVE_DOMAIN_PASS {$pass}/{$pass}\n";
} finally {
    foreach (glob($project.'/var/lab/*') ?: [] as $file) @unlink($file);
    @rmdir($project.'/var/lab');
    @rmdir($project.'/var');
    @rmdir($project);
}
