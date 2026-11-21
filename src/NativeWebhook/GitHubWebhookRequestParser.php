<?php

declare(strict_types=1);

namespace Stillwired\NativeWebhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/** Native Symfony Webhook custom parser; rejects bad HMAC before any receipt is emitted. */
final class GitHubWebhookRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {
        $raw = $request->getContent();
        if (strlen($raw) > 1_048_576) {
            throw new RejectWebhookException(413, 'Request too large');
        }

        if (!GitHubSignature::isValid($raw, $request->headers->get('X-Hub-Signature-256', ''), $secret)) {
            throw new RejectWebhookException(401, 'Invalid GitHub webhook signature');
        }

        $type = $request->headers->get('X-GitHub-Event', '');
        $guid = $request->headers->get('X-GitHub-Delivery', '');
        if (!\in_array($type, ['push', 'ping'], true) || !\is_string($guid)
            || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $guid)) {
            throw new RejectWebhookException(400, 'Unsupported event or invalid delivery GUID');
        }

        try {
            $decoded = json_decode($raw, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RejectWebhookException(400, 'Malformed JSON');
        }
        if (!\is_array($decoded)) {
            throw new RejectWebhookException(400, 'Expected JSON object');
        }

        $repository = $decoded['repository']['full_name'] ?? null;
        if (!\is_string($repository) || !preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository)) {
            throw new RejectWebhookException(400, 'Missing repository identity');
        }

        // Only a safe summary is carried into RemoteEvent; no GitHub payload, tokens,
        // commits, destinations, or credentials are persisted.
        return new RemoteEvent($type, strtolower($guid), [
            'repository' => $repository,
            'raw_body_sha256' => hash('sha256', $raw),
            'github_hook_id' => ctype_digit((string) $request->headers->get('X-GitHub-Hook-ID', ''))
                ? (string) $request->headers->get('X-GitHub-Hook-ID') : null,
        ]);
    }
}
