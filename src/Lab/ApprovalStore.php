<?php

declare(strict_types=1);
namespace Stillwired\Lab;

/** Local human-issued, short-lived, one-use authorisation; not production IAM. */
final class ApprovalStore
{
    public function __construct(private StateStore $state) {}
    public function issue(string $flow, int $ttl = 300): string
    {
        if (!in_array($flow, StateStore::FLOWS, true)) throw new \InvalidArgumentException('Unknown flow');
        $token = bin2hex(random_bytes(24));
        $record = ['hash' => hash('sha256', $token), 'expires_at' => time()+$ttl];
        file_put_contents($this->state->dir().'/'.$flow.'.approval', json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX);
        return $token;
    }
    public function consume(string $flow, string $token): bool
    {
        if (!in_array($flow, StateStore::FLOWS, true)) return false;
        $path = $this->state->dir().'/'.$flow.'.approval';
        $fh = @fopen($path, 'r+');
        if (!$fh) return false;
        try {
            if (!flock($fh, LOCK_EX)) return false;
            $record = json_decode(stream_get_contents($fh) ?: '{}', true);
            $valid = is_array($record) && isset($record['expires_at'], $record['hash'])
                && $record['expires_at'] >= time()
                && hash_equals($record['hash'], hash('sha256', $token));
            if ($valid) { rewind($fh); ftruncate($fh, 0); fflush($fh); }
            return $valid;
        } finally { flock($fh, LOCK_UN); fclose($fh); }
    }
}
