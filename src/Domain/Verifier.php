<?php

declare(strict_types=1);

namespace Stillwired\Domain;

final class Verifier
{
    /**
     * CLOSED means no still-active flow in the enumerated sources and no probe
     * delivery in this synchronous, synthetic lab. Never claims global absence.
     *
     * @return array{decision:string,account_active:bool,observed_states:array<string,string>,canary_id:string,deliveries:list<array<string,mixed>>,scope:list<string>,limitations:list<string>}
     */
    public function verify(FlowLab $lab, string $canaryId): array
    {
        $states = $lab->observeConfig();
        $deliveries = $lab->emitAndCollectCanary($canaryId);

        if (in_array('active', $states, true) || count($deliveries) > 0) {
            $decision = 'EXPOSED';
        } elseif (in_array('unknown', $states, true)) {
            $decision = 'INCONCLUSIVE';
        } else {
            $decision = 'VERIFIED';
        }

        return [
            'decision' => $decision,
            'account_active' => $lab->isHumanAccountActive(),
            'observed_states' => $states,
            'canary_id' => $canaryId,
            'deliveries' => $deliveries,
            'scope' => ['webhook', 'scheduled_export'],
            'limitations' => [
                'Synthetic lab only; no live provider integration',
                'Only two enumerated flows are checked',
                'Synchronous probe; delayed queues and unknown access paths are out of scope',
                'SHA-256 checksum detects accidental changes, not adversarial re-signing',
            ],
        ];
    }
}
