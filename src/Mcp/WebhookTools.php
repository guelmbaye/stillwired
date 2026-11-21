<?php

declare(strict_types=1);
namespace Stillwired\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Stillwired\Lab\StateStore;
use Stillwired\Lab\ApprovalStore;

final class WebhookTools
{
    public function __construct(private StateStore $state, private ApprovalStore $approval) {}

    #[McpTool(name: 'observe_webhook', description: 'Inspect source-owned webhook state; independent of revoked human account.')]
    public function observeWebhook(): string
    {
        return json_encode($this->state->observe('webhook'), JSON_THROW_ON_ERROR);
    }

    #[McpTool(name: 'emit_webhook_canary', description: 'Emit a synthetic canary via the webhook; returns whether a delivery occurred.')]
    public function emitWebhookCanary(string $canaryId): string
    {
        return json_encode($this->state->emit('webhook', $canaryId), JSON_THROW_ON_ERROR);
    }

    #[McpTool(name: 'stop_webhook', description: 'Stop the webhook only with an explicit one-time human approval token for this exact flow.')]
    public function stopWebhook(string $approvalToken): string
    {
        if (!$this->approval->consume('webhook', $approvalToken)) throw new \RuntimeException('Approval missing, expired or already used');
        return json_encode(['flow' => 'webhook', 'changed' => $this->state->stop('webhook')], JSON_THROW_ON_ERROR);
    }
}
