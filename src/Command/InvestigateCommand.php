<?php

declare(strict_types=1);

namespace Stillwired\Command;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Active probing is restricted to synthetic canaries. The agent can never
 * invoke the stop tools; the PHP verifier owns the authoritative verdict.
 */
#[AsCommand(name: 'sw:investigate', description: 'Investigate MCP source status; optionally test it against controlled synthetic deliveries')]
final class InvestigateCommand extends Command
{
    private const OBSERVE_TOOLS = [
        'webhook_observe_webhook',
        'scheduler_observe_export',
    ];

    private const CANARY_TOOLS = [
        'webhook_emit_webhook_canary',
        'scheduler_emit_export_canary',
    ];

    public function __construct(private AgentInterface $agent)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('incident', InputArgument::OPTIONAL, 'Natural-language incident', 'The contractor user was revoked. Can we safely close the incident? Investigate the webhook and scheduled export.')
            ->addOption('active-probe', null, InputOption::VALUE_NONE, 'Allow synthetic canary probes (never revocations) to challenge source claims.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activeProbe = (bool) $input->getOption('active-probe');
        $canaryId = $activeProbe ? 'SW-AI-'.strtoupper(bin2hex(random_bytes(8))) : null;

        $system = <<<'PROMPT'
You are STILLWIRED, an incident investigator operating only on an explicitly synthetic lab.
Use both independent MCP source tools, cite concrete facts from their actual responses, and distinguish the revoked human account from source-owned integration identities. You are NOT authorized to stop, revoke, reactivate, modify configurations, or approve anything. The authoritative incident verdict belongs to a separate deterministic PHP verifier. Never claim to have found unknown integrations or to guarantee the absence of delayed deliveries.
PROMPT;
        $tools = self::OBSERVE_TOOLS;

        if ($activeProbe) {
            $tools = array_merge($tools, self::CANARY_TOOLS);
            $system .= "\n\nACTIVE SYNTHETIC PROBE MODE (no real customer systems):\n"
                ."1. Call webhook_observe_webhook and scheduler_observe_export.\n"
                ."2. Call webhook_emit_webhook_canary and scheduler_emit_export_canary. For BOTH use the exact canaryId parameter: {$canaryId}. Do not invent or change it.\n"
                ."3. Correlate each reported source state with the returned delivered boolean and event_id.\n"
                ."4. When a source says disabled but delivers a canary, explicitly state FALSE GREEN / EXPOSED and cite its exact event_id.\n"
                ."5. If a tool failed or evidence is missing, state INCONCLUSIVE for that source; do not declare closure.\n"
                ."6. State that these are synthetic delivery events, not proof about any real-world provider.\n"
                ."7. Finish with one line formatted 'RECOMMENDATION: BLOCK_CLOSURE' if any observed delivery survived despite disabled status, otherwise 'RECOMMENDATION: REQUIRE_PHP_VERIFIER'.\n";
            $output->writeln('INVESTIGATION_MODE=ACTIVE_SYNTHETIC_PROBE');
            $output->writeln('INVESTIGATION_CANARY_ID='.$canaryId);
        } else {
            $system .= "\n\nOBSERVATION MODE: Use only the two observation tools. Do not perform canary delivery or other writes.";
            $output->writeln('INVESTIGATION_MODE=OBSERVE_ONLY');
        }

        try {
            $messages = new MessageBag(
                Message::forSystem($system),
                Message::ofUser((string) $input->getArgument('incident')),
            );
            // Hide dangerous tools *before* inference, in addition to the fail-closed
            // ToolCallRequested server-side audit subscriber.
            $response = $this->agent->call($messages, ['tools' => $tools]);
            $output->writeln((string) $response->getContent());
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>AGENT_ERROR: '.get_debug_type($e).': '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
