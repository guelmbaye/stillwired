<?php

declare(strict_types=1);
namespace Stillwired\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Stillwired\Lab\StateStore;
use Stillwired\Lab\ApprovalStore;

final class SchedulerTools
{
    public function __construct(private StateStore $state, private ApprovalStore $approval) {}

    #[McpTool(name: 'observe_export', description: 'Inspect scheduler-owned export state; independent of revoked human account.')]
    public function observeExport(): string
    {
        return json_encode($this->state->observe('scheduled_export'), JSON_THROW_ON_ERROR);
    }

    #[McpTool(name: 'emit_export_canary', description: 'Emit a synthetic canary via scheduled export; returns whether a delivery occurred.')]
    public function emitExportCanary(string $canaryId): string
    {
        return json_encode($this->state->emit('scheduled_export', $canaryId), JSON_THROW_ON_ERROR);
    }

    #[McpTool(name: 'stop_export', description: 'Stop export only with an explicit one-time human approval token for this exact flow.')]
    public function stopExport(string $approvalToken): string
    {
        if (!$this->approval->consume('scheduled_export', $approvalToken)) throw new \RuntimeException('Approval missing, expired or already used');
        return json_encode(['flow' => 'scheduled_export', 'changed' => $this->state->stop('scheduled_export')], JSON_THROW_ON_ERROR);
    }
}
