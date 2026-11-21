<?php

declare(strict_types=1);
namespace Stillwired\Lab;

/** File-backed, synthetic source state; independent files per integration. */
final class StateStore
{
    public function __construct(private string $projectDir) {}
    public const FLOWS = ['webhook', 'scheduled_export'];

    public function reset(): void
    {
        foreach (self::FLOWS as $flow) {
            $this->writeState($flow, ['enabled' => true, 'owner' => $flow === 'webhook' ? 'source-application' : 'scheduler-service-account', 'revision' => 0]);
            @unlink($this->dir().'/'.$flow.'.offline');
            @unlink($this->dir().'/'.$flow.'.stale-report');
            @unlink($this->dir().'/'.$flow.'.approval');
        }
        file_put_contents($this->dir().'/human.json', "{\"active\":true}\n", LOCK_EX);
        file_put_contents($this->dir().'/sink.jsonl', '', LOCK_EX);
    }

    public function revokeHuman(): void
    {
        $this->ensure();
        file_put_contents($this->dir().'/human.json', "{\"active\":false}\n", LOCK_EX);
    }

    public function humanActive(): bool
    {
        $this->ensure();
        $data = json_decode((string) file_get_contents($this->dir().'/human.json'), true, 512, JSON_THROW_ON_ERROR);
        return (bool) $data['active'];
    }

    public function observe(string $flow): array
    {
        $this->assertFlow($flow);
        $this->ensure();
        if (is_file($this->dir().'/'.$flow.'.offline')) {
            throw new \RuntimeException('Provider unavailable (injected fault): '.$flow);
        }
        $state = $this->readState($flow);
        // Fault injection: metadata claims a flow is disabled while delivery remains enabled.
        // Do not reveal the injected fault in the MCP observation: the checker must detect it by probing.
        $reportedEnabled = $state['enabled'] && !is_file($this->dir().'/'.$flow.'.stale-report');
        return ['flow' => $flow, 'state' => $reportedEnabled ? 'active' : 'disabled', 'owner' => $state['owner'], 'revision' => $state['revision'], 'account_active' => $this->humanActive()];
    }

    public function emit(string $flow, string $canaryId): array
    {
        $this->assertFlow($flow);
        if (!preg_match('/^[A-Za-z0-9_-]{5,80}$/D', $canaryId)) {
            throw new \InvalidArgumentException('Invalid canary ID');
        }
        // Check provider availability, but do not use its possibly stale status as delivery truth.
        $this->observe($flow);
        $actual = $this->readState($flow);
        $delivery = $actual['enabled'] ? ['canary_id' => $canaryId, 'flow' => $flow, 'event_id' => bin2hex(random_bytes(8)), 'timestamp_utc' => gmdate('c')] : null;
        if ($delivery !== null) {
            if (file_put_contents($this->dir().'/sink.jsonl', json_encode($delivery, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Sink write failed');
            }
        }
        return ['flow' => $flow, 'canary_id' => $canaryId, 'delivered' => $delivery !== null, 'event_id' => $delivery['event_id'] ?? null];
    }

    public function delivered(string $canaryId): array
    {
        $this->ensure();
        $rows = file($this->dir().'/sink.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $found = [];
        foreach ($rows as $row) {
            $delivery = json_decode($row, true, 512, JSON_THROW_ON_ERROR);
            if ($delivery['canary_id'] === $canaryId) $found[] = $delivery;
        }
        return $found;
    }

    public function stop(string $flow): bool
    {
        return $this->mutate($flow, false);
    }

    public function reactivate(string $flow): bool
    {
        return $this->mutate($flow, true);
    }

    public function setOffline(string $flow, bool $offline): void
    {
        $this->assertFlow($flow);
        $this->ensure();
        $path = $this->dir().'/'.$flow.'.offline';
        if ($offline) file_put_contents($path, 'fault-injection'); else @unlink($path);
    }

    /** Inject or clear a deliberately stale status response, without changing actual delivery state. */
    public function setStaleReport(string $flow, bool $stale): void
    {
        $this->assertFlow($flow);
        $this->ensure();
        $path = $this->dir().'/'.$flow.'.stale-report';
        if ($stale) {
            if (file_put_contents($path, 'disabled', LOCK_EX) === false) {
                throw new \RuntimeException('Cannot enable stale report');
            }
        } else {
            @unlink($path);
        }
    }

    public function dir(): string
    {
        $path = $this->projectDir.'/var/lab';
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) throw new \RuntimeException('Unable to create lab dir');
        return $path;
    }

    private function ensure(): void
    {
        if (!is_file($this->dir().'/human.json') || !is_file($this->dir().'/webhook.json') || !is_file($this->dir().'/scheduled_export.json') || !is_file($this->dir().'/sink.jsonl')) {
            throw new \RuntimeException('Lab not initialised. Run: php bin/console sw:reset');
        }
    }

    private function readState(string $flow): array
    {
        return json_decode((string) file_get_contents($this->dir().'/'.$flow.'.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function writeState(string $flow, array $state): void
    {
        $this->assertFlow($flow);
        file_put_contents($this->dir().'/'.$flow.'.json', json_encode($state, JSON_THROW_ON_ERROR)."\n", LOCK_EX);
    }

    private function mutate(string $flow, bool $enabled): bool
    {
        $this->assertFlow($flow);
        $this->ensure();
        $path = $this->dir().'/'.$flow.'.json';
        $fh = fopen($path, 'c+');
        if (!$fh) throw new \RuntimeException('Cannot open '.$path);
        try {
            if (!flock($fh, LOCK_EX)) throw new \RuntimeException('Cannot lock '.$path);
            $state = json_decode(stream_get_contents($fh) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $changed = $state['enabled'] !== $enabled;
            if ($changed) {
                $state['enabled'] = $enabled;
                $state['revision']++;
                rewind($fh); ftruncate($fh, 0);
                fwrite($fh, json_encode($state, JSON_THROW_ON_ERROR)."\n"); fflush($fh);
            }
            return $changed;
        } finally { flock($fh, LOCK_UN); fclose($fh); }
    }

    private function assertFlow(string $flow): void
    {
        if (!in_array($flow, self::FLOWS, true)) throw new \InvalidArgumentException('Unknown flow: '.$flow);
    }
}
