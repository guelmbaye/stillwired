<?php

declare(strict_types=1);

$autoload = __DIR__.'/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "SKIP: Composer vendor/autoload.php not found; copy this kit to the existing project and run there.\n");
    exit(2);
}
require $autoload;

use Stillwired\Integration\GitHubWebhookReader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$calls = [];
$token = 'never-print-this-token';
$hook = ['id'=>441,'active'=>false,'config'=>['secret'=>'never-store-this-secret','url'=>'https://sink.example/secret-endpoint']];
$deliveries = [
  ['id'=>8991,'guid'=>'test-guid','delivered_at'=>'2026-11-21T09:02:00Z','status_code'=>200,'status'=>'OK'],
];
$client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls, $hook, $deliveries) {
    $calls[] = ['method'=>$method,'url'=>$url];
    if ($method !== 'GET') { throw new RuntimeException('Non-read-only method observed!'); }
    $body = str_contains($url, '/deliveries?') ? $deliveries : $hook;
    return new MockResponse(json_encode($body, JSON_THROW_ON_ERROR), ['http_code'=>200]);
});
$reader = new GitHubWebhookReader($client);
$evidence = $reader->audit('stillwired-fixture', 'audit-test', 441, $token, new DateTimeImmutable('2026-11-21T09:00:00Z'));
if (count($calls) !== 2 || $evidence['verdict'] !== 'EXPOSED' || $evidence['accepted_count_after_cutoff'] !== 1 ||
    str_contains(json_encode($evidence, JSON_THROW_ON_ERROR), 'never-store') ||
    str_contains(json_encode($evidence, JSON_THROW_ON_ERROR), 'never-print')) {
    fwrite(STDERR, "C4_GITHUB_HTTP_MOCK_FAIL\n");
    exit(1);
}

echo "PASS: exactly two GET requests through Symfony HttpClient\n";
echo "PASS: delivery evidence becomes EXPOSED\n";
echo "PASS: token and webhook destination not exported\n";
echo "C4_GITHUB_HTTP_MOCK_PASS 3/3\n";
