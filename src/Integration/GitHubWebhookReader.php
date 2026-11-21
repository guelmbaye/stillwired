<?php

declare(strict_types=1);

namespace Stillwired\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Read-only connector to the official GitHub REST API (only GET). */
final class GitHubWebhookReader
{
    public function __construct(private HttpClientInterface $httpClient) {}

    /** @return array<string, mixed> */
    public function audit(string $owner, string $repo, int $hookId, string $token, \DateTimeImmutable $since): array
    {
        if (!preg_match('/^[a-zA-Z0-9-]{1,39}$/D', $owner) ||
            !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D', $repo) || $hookId <= 0) {
            throw new \InvalidArgumentException('Invalid GitHub owner, repository or webhook ID.');
        }
        if (trim($token) === '') {
            throw new \InvalidArgumentException('SW_GITHUB_TOKEN must be supplied via an environment variable.');
        }
        $base = sprintf('https://api.github.com/repos/%s/%s/hooks/%d', rawurlencode($owner), rawurlencode($repo), $hookId);
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer '.$token,
            'X-GitHub-Api-Version' => '2026-03-10',
            'User-Agent' => 'Stillwired-optional-readonly-webhook-audit',
        ];
        $hook = $this->fetch($base, $headers);
        $list = $this->fetch($base.'/deliveries?per_page=100', $headers);
        if (!array_is_list($list)) {
            throw new \RuntimeException('GitHub deliveries response is not a list.');
        }
        $evidence = GitHubWebhookEvidence::classify($hook, $list, $since);
        $evidence['repository'] = $owner.'/'.$repo;
        return $evidence;
    }

    /** @param array<string, string> $headers @return array<mixed> */
    private function fetch(string $url, array $headers): array
    {
        // Never follow untrusted redirects and never expose authorization headers in output.
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'max_redirects' => 0,
            'timeout' => 8,
            'max_duration' => 10,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('GitHub API read failed with HTTP '.$response->getStatusCode());
        }
        $data = $response->toArray(false);
        if (!is_array($data)) {
            throw new \RuntimeException('GitHub API did not return a JSON object/array.');
        }
        return $data;
    }
}
