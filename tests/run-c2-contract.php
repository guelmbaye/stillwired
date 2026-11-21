<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$command = (string) file_get_contents($root.'/src/Command/InvestigateCommand.php');
$audit = (string) file_get_contents($root.'/src/EventListener/InvestigationToolAuditSubscriber.php');
$pass = 0;
$check = static function (bool $ok, string $what) use (&$pass): void {
    if (!$ok) throw new RuntimeException('C2 FAIL: '.$what);
    ++$pass;
    echo 'PASS: '.$what."\n";
};
$check(str_contains($command, "->addOption('active-probe'"), 'active probing is opt-in');
$check(str_contains($command, "'webhook_observe_webhook'") && str_contains($command, "'scheduler_observe_export'"), 'both status observations available');
$check(str_contains($command, "'webhook_emit_webhook_canary'") && str_contains($command, "'scheduler_emit_export_canary'"), 'both synthetic canary probes available');
$check(str_contains($command, "['tools' => \$tools]"), 'tool set filtered before model invocation');
$check(str_contains($command, 'BLOCK_CLOSURE') && str_contains($command, 'event_id'), 'agent prompted to cite delivery receipts and decision');
$start = strpos($audit, 'private const ALLOWED');
$end = $start === false ? false : strpos($audit, '];', $start);
$allowlist = ($start === false || $end === false) ? '' : substr($audit, $start, $end-$start);
$check(str_contains($allowlist, 'emit_webhook_canary') && !str_contains($allowlist, 'stop_webhook') && !str_contains($allowlist, 'stop_export'), 'audit allowlist excludes remediation tools');
$check(str_contains($audit, "->deny('STILLWIRED AI cannot invoke that tool"), 'server-side deny mechanism present');
echo "C2_CONTRACT_PASS {$pass}/{$pass}\n";
