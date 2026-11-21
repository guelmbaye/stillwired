<?php

declare(strict_types=1);

namespace Stillwired\NativeWebhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Append-only laboratory receipt ledger with file locking and a local hash chain.
 *  This is tamper-EVIDENT for accidental edits, not a signed independent attestation.
 */
final class GitHubReceiptStore
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    private function ledgerPath(): string
    {
        return $this->projectDir.'/var/evidence/c6-github-receiver.jsonl';
    }

    /** @return 'new'|'duplicate' */
    public function record(string $guid, string $eventType, array $payload): string
    {
        if (!preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D', $guid)) {
            throw new \InvalidArgumentException('Invalid GUID');
        }
        if (!\in_array($eventType, ['push', 'ping'], true)) {
            throw new \InvalidArgumentException('Unsupported event');
        }
        $repo = $payload['repository'] ?? null;
        $bodySha = $payload['raw_body_sha256'] ?? null;
        if (!\is_string($repo) || !preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repo)
            || !\is_string($bodySha) || !preg_match('/^[0-9a-f]{64}$/D', $bodySha)) {
            throw new \InvalidArgumentException('Malformed safe receipt');
        }

        $dir = \dirname($this->ledgerPath());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create evidence directory');
        }
        $fp = fopen($this->ledgerPath(), 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open receipt ledger');
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock receipt ledger');
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            if ($raw === false) {
                throw new \RuntimeException('Cannot read ledger');
            }
            $entries = self::parseAndCheck($raw);
            foreach ($entries as $entry) {
                if ($entry['guid'] === $guid) {
                    if ($entry['raw_body_sha256'] !== $bodySha || $entry['event_type'] !== $eventType) {
                        throw new \RuntimeException('GUID collision or altered redelivery');
                    }
                    return 'duplicate';
                }
            }
            $priorHash = $entries === [] ? str_repeat('0', 64) : $entries[array_key_last($entries)]['entry_sha256'];
            $record = [
                'guid' => $guid,
                'event_type' => $eventType,
                'repository' => $repo,
                'hook_id' => isset($payload['github_hook_id']) && \is_string($payload['github_hook_id'])
                    ? $payload['github_hook_id'] : null,
                'raw_body_sha256' => $bodySha,
                'received_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'previous_sha256' => $priorHash,
            ];
            $record['entry_sha256'] = self::hashRecord($record);
            $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
            fseek($fp, 0, SEEK_END);
            if (fwrite($fp, $line) !== strlen($line) || !fflush($fp)) {
                throw new \RuntimeException('Could not persist receipt');
            }
            return 'new';
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** @return array{valid: bool, count: int, receipts: array<int, array<string,mixed>>, reason: string|null} */
    public function audit(): array
    {
        $path = $this->ledgerPath();
        if (!is_file($path)) {
            return ['valid' => true, 'count' => 0, 'receipts' => [], 'reason' => null];
        }
        try {
            $entries = self::parseAndCheck((string) file_get_contents($path));
            return ['valid' => true, 'count' => count($entries), 'receipts' => $entries, 'reason' => null];
        } catch (\Throwable $e) {
            return ['valid' => false, 'count' => 0, 'receipts' => [], 'reason' => $e->getMessage()];
        }
    }

    private static function hashRecord(array $record): string
    {
        unset($record['entry_sha256']);
        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<int,array<string,mixed>> */
    private static function parseAndCheck(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $lines = explode("\n", trim($raw, "\n"));
        $entries = [];
        $prior = str_repeat('0', 64);
        $guids = [];
        foreach ($lines as $line) {
            try {
                $entry = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException('Unreadable receipt ledger', 0, $e);
            }
            if (!\is_array($entry) || !isset($entry['entry_sha256'], $entry['previous_sha256'], $entry['guid'])
                || $entry['previous_sha256'] !== $prior || !hash_equals($entry['entry_sha256'], self::hashRecord($entry))) {
                throw new \RuntimeException('Receipt hash chain mismatch');
            }
            if (isset($guids[$entry['guid']])) {
                throw new \RuntimeException('Duplicate GUID in receipt ledger');
            }
            $guids[$entry['guid']] = true;
            $prior = $entry['entry_sha256'];
            $entries[] = $entry;
        }
        return $entries;
    }
}
