<?php

declare(strict_types=1);
namespace Stillwired\Command;

use Stillwired\Lab\StateStore;
use Stillwired\Lab\ApprovalStore;
use Stillwired\Lab\McpVerifier;
use Stillwired\Domain\Evidence;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[AsCommand(name: 'sw:lab', description: 'Synthetic MCP lab: reset, revoke, verify, approve, stop, fault, reactivate')]
final class LabCommand extends Command
{
    public function __construct(
        private StateStore $state, private ApprovalStore $approvals,
        private McpVerifier $verifier,
        #[Target('stillwired')] private McpClientInterface $client,
    ) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'reset|revoke|verify|approve|stop|fault|reactivate')
             ->addArgument('flow', InputArgument::OPTIONAL, 'webhook|scheduled_export')
             ->addArgument('value', InputArgument::OPTIONAL, 'token or up/down');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string) $input->getArgument('action');
        $flow = (string) ($input->getArgument('flow') ?? '');
        $value = (string) ($input->getArgument('value') ?? '');
        try {
            switch ($action) {
                case 'reset': $this->state->reset(); $output->writeln('LAB_RESET'); break;
                case 'revoke': $this->state->revokeHuman(); $output->writeln('HUMAN_REVOKED'); break;
                case 'approve': $output->writeln('APPROVAL_TOKEN (one-use, 5 min) for '.$flow.': '.$this->approvals->issue($flow)); break;
                case 'stop':
                    if ($value === '') throw new \InvalidArgumentException('Approval token required');
                    [$server,$tool] = match ($flow) { 'webhook' => ['webhook','stop_webhook'], 'scheduled_export' => ['scheduler','stop_export'], default => throw new \InvalidArgumentException('Unknown flow')};
                    $result = $this->client->get($server)->callTool($tool, ['approvalToken' => $value]);
                    $output->writeln($result->content[0]->text ?? 'NO_RESULT');
                    break;
                case 'fault':
                    if (!in_array($value, ['up','down','stale','honest'], true)) throw new \InvalidArgumentException('Use up, down, stale, or honest');
                    if (in_array($value, ['stale','honest'], true)) {
                        $this->state->setStaleReport($flow, $value === 'stale');
                    } else {
                        $this->state->setOffline($flow, $value === 'down');
                    }
                    $output->writeln('FAULT_INJECTED: '.$flow.' '.$value);
                    break;
                case 'reactivate': $output->writeln($this->state->reactivate($flow) ? 'REACTIVATED' : 'ALREADY_ACTIVE'); break;
                case 'verify':
                    $report = $this->verifier->verify();
                    $path = dirname(__DIR__, 2).'/var/evidence/gate1-'.strtolower($report['decision']).'.json';
                    Evidence::write($path, $report);
                    $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                    $output->writeln('EVIDENCE: '.$path);
                    if ($report['decision'] === 'INCONCLUSIVE') return Command::FAILURE;
                    break;
                default: throw new \InvalidArgumentException('Unknown action');
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>'.get_debug_type($e).': '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
