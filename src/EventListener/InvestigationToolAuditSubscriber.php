<?php

declare(strict_types=1);

namespace Stillwired\EventListener;

use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fail-closed authorization and event audit for STILLWIRED AI investigations.
 * Canary tools are limited to the synthetic lab; write/stop tools remain denied.
 * Tool arguments (including approval tokens) are deliberately never logged.
 */
final class InvestigationToolAuditSubscriber implements EventSubscriberInterface
{
    private const ALLOWED = [
        'webhook_observe_webhook',
        'scheduler_observe_export',
        'webhook_emit_webhook_canary',
        'scheduler_emit_export_canary',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            ToolCallRequested::class => 'onRequested',
            ToolCallSucceeded::class => 'onSucceeded',
            ToolCallFailed::class => 'onFailed',
        ];
    }

    public function onRequested(ToolCallRequested $event): void
    {
        $tool = $event->getToolCall()->getName();
        if (!in_array($tool, self::ALLOWED, true)) {
            $this->append('denied', $tool);
            $event->deny('STILLWIRED AI cannot invoke that tool. Only observations and synthetic canaries are allowed.');
            return;
        }
        $this->append('requested', $tool);
    }

    public function onSucceeded(ToolCallSucceeded $event): void
    {
        $this->append('succeeded', $event->getDefinition()->getName());
    }

    public function onFailed(ToolCallFailed $event): void
    {
        $this->append('failed', $event->getDefinition()->getName());
    }

    private function append(string $status, string $tool): void
    {
        $path = dirname(__DIR__, 2).'/var/evidence/agent-tool-events.jsonl';
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Unable to create the audit directory');
        }
        $entry = [
            'timestamp_utc' => gmdate('c'),
            'status' => $status,
            'tool' => $tool,
        ];
        $line = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to persist the tool audit entry');
        }
    }
}
