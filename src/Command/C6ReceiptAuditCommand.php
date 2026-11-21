<?php

declare(strict_types=1);

namespace Stillwired\Command;

use Stillwired\NativeWebhook\GitHubReceiptStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sw:c6:receipts', description: 'Audit signed GitHub webhook receipts persisted by the native Symfony consumer.')]
final class C6ReceiptAuditCommand extends Command
{
    public function __construct(private readonly GitHubReceiptStore $store)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('guid', null, InputOption::VALUE_REQUIRED, 'Optional GitHub delivery GUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $audit = $this->store->audit();
        $guid = $input->getOption('guid');
        $rows = $audit['receipts'];
        $matches = \is_string($guid) && $guid !== ''
            ? array_values(array_filter($rows, static fn (array $r): bool => strcasecmp($r['guid'], $guid) === 0))
            : $rows;
        $summary = [
            'integration' => 'symfony_webhook_receiver',
            'source' => 'LOCAL_SIGNED_RECEIVER_LOG_NOT_EXTERNAL_ATTESTATION',
            'ledger_integrity' => $audit['valid'] ? 'HASH_CHAIN_OK' : 'HASH_CHAIN_FAIL',
            'receipt_count' => $audit['count'],
            'matched_receipts' => $matches,
            'observation' => !$audit['valid'] ? 'INCONCLUSIVE' : ($matches === [] ? 'NO_RECEIPT_OBSERVED' : 'RECEIPT_OBSERVED'),
            'limitations' => [
                'No receipt is not proof that all flows stopped',
                'Local hash chain is not a signed independent timestamp',
                'GitHub signature validates body only; delivery GUID is an HTTP header',
                'A real GitHub probe requires an HTTPS endpoint and a new signed event',
            ],
        ];
        if (!$audit['valid']) {
            $summary['error'] = $audit['reason'];
        }
        $output->writeln(json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $audit['valid'] ? Command::SUCCESS : Command::FAILURE;
    }
}
