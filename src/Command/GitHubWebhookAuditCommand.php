<?php

declare(strict_types=1);

namespace Stillwired\Command;

use Stillwired\Integration\GitHubWebhookReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sw:github:webhook-audit', description: 'Read-only external GitHub webhook metadata and delivery evidence.')]
final class GitHubWebhookAuditCommand extends Command
{
    public function __construct(private GitHubWebhookReader $reader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('owner', null, InputOption::VALUE_REQUIRED, 'GitHub organization or account');
        $this->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository name');
        $this->addOption('hook-id', null, InputOption::VALUE_REQUIRED, 'Repository webhook ID');
        $this->addOption('since', null, InputOption::VALUE_REQUIRED, 'Evidence cutoff with offset, e.g. 2026-11-21T09:00:00Z');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sinceValue = (string) ($input->getOption('since') ?? '');
        if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D', $sinceValue)) {
            $output->writeln('<error>Use an ISO-8601 timestamp with timezone in --since.</error>');
            return Command::INVALID;
        }
        try {
            $since = new \DateTimeImmutable($sinceValue);
            $token = getenv('SW_GITHUB_TOKEN') ?: '';
            $data = $this->reader->audit(
                (string) $input->getOption('owner'),
                (string) $input->getOption('repo'),
                (int) $input->getOption('hook-id'),
                $token,
                $since,
            );
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            // Do not print exception details: third-party transports may embed sensitive URLs or headers.
            $output->writeln('<error>GITHUB_AUDIT_INCONCLUSIVE: '.get_debug_type($error).'</error>');
            return Command::FAILURE;
        }
    }
}
