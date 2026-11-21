<?php

declare(strict_types=1);

namespace Stillwired\Domain;

/**
 * Deterministic, synthetic lab: three distinct authorization domains.
 * It does NOT interact with real accounts, credentials, or destinations.
 */
final class FlowLab
{
    private bool $humanAccountActive = true;
    /** @var array<string,bool> */
    private array $flowActive = ['webhook' => true, 'scheduled_export' => true];
    /** @var array<string,bool> */
    private array $sourceReachable = ['webhook' => true, 'scheduled_export' => true];
    /** @var list<array{canary_id:string,flow:string,seq:int,kind:string}> */
    private array $sink = [];
    private int $sequence = 0;

    public function revokeHumanAccount(): void
    {
        $this->humanAccountActive = false;
    }

    public function isHumanAccountActive(): bool
    {
        return $this->humanAccountActive;
    }

    /** Independent integration controls; not coupled to the human account. */
    public function stopFlow(string $flow): bool
    {
        $this->assertFlow($flow);
        if (!$this->flowActive[$flow]) {
            return false; // idempotent repeat
        }
        $this->flowActive[$flow] = false;
        return true;
    }

    public function startFlow(string $flow): void
    {
        $this->assertFlow($flow);
        $this->flowActive[$flow] = true;
    }

    public function setSourceReachable(string $flow, bool $reachable): void
    {
        $this->assertFlow($flow);
        $this->sourceReachable[$flow] = $reachable;
    }

    /** @return array<string, 'active'|'disabled'|'unknown'> */
    public function observeConfig(): array
    {
        $results = [];
        foreach ($this->flowActive as $flow => $active) {
            $results[$flow] = !$this->sourceReachable[$flow]
                ? 'unknown'
                : ($active ? 'active' : 'disabled');
        }
        return $results;
    }

    /**
     * Synthetic canary in each independent source, then let enabled routes deliver.
     * No absence-of-message inference: config checks are required too.
     * @return list<array{canary_id:string,flow:string,seq:int,kind:string}>
     */
    public function emitAndCollectCanary(string $canaryId): array
    {
        $new = [];
        foreach ($this->flowActive as $flow => $active) {
            if (!$this->sourceReachable[$flow]) {
                continue; // no probe response; source state is unknown
            }
            if ($active) {
                $new[] = [
                    'canary_id' => $canaryId,
                    'flow' => $flow,
                    'seq' => ++$this->sequence,
                    'kind' => 'synthetic-delivery',
                ];
            }
        }
        array_push($this->sink, ...$new);
        return $new;
    }

    /** @return list<array{canary_id:string,flow:string,seq:int,kind:string}> */
    public function sink(): array
    {
        return $this->sink;
    }

    private function assertFlow(string $flow): void
    {
        if (!array_key_exists($flow, $this->flowActive)) {
            throw new \InvalidArgumentException(sprintf('Unknown flow: %s', $flow));
        }
    }
}
