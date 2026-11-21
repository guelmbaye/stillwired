<?php

declare(strict_types=1);

namespace Stillwired\NativeWebhook;

/** Verifies GitHub's HMAC-SHA256 over the exact raw request body. */
final class GitHubSignature
{
    public static function isValid(string $rawBody, string $header, #[\SensitiveParameter] string $secret): bool
    {
        if (trim($secret) === '' || !preg_match('/^sha256=[0-9a-f]{64}$/iD', $header)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, strtolower($header));
    }
}
