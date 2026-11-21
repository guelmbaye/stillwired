<?php

declare(strict_types=1);

require_once __DIR__.'/../src/Integration/GitHubWebhookEvidence.php';

use Stillwired\Integration\GitHubWebhookEvidence;

$cutoff = new DateTimeImmutable('2026-11-21T09:00:00+00:00');
$hookActive = ['id'=>12, 'active'=>true, 'config'=>['url'=>'https://secret.invalid/abc', 'secret'=>'TOP_SECRET']];
$hookStopped = ['id'=>12, 'active'=>false, 'config'=>['url'=>'https://secret.invalid/abc', 'secret'=>'TOP_SECRET']];
$delivery = ['id'=>101, 'guid'=>'guid1','delivered_at'=>'2026-11-21T09:10:00Z','status_code'=>200,'redelivery'=>false];
$failure = ['id'=>102, 'guid'=>'guid2','delivered_at'=>'2026-11-21T09:12:00Z','status_code'=>503];
$old = ['id'=>103, 'guid'=>'guid3','delivered_at'=>'2026-11-21T08:59:59Z','status_code'=>200];

$checks = [
    'successful post-cutoff delivery means EXPOSED' => fn() => GitHubWebhookEvidence::classify($hookStopped, [$delivery], $cutoff)['verdict'] === 'EXPOSED',
    'provider-disabled cannot hide HTTP delivery' => fn() => GitHubWebhookEvidence::classify($hookStopped, [$delivery], $cutoff)['observation'] === 'POST_CUTOFF_HTTP_ACCEPTED',
    'unsuccessful attempt does not imply delivery succeeded' => fn() => GitHubWebhookEvidence::classify($hookStopped, [$failure], $cutoff)['verdict'] === 'AT_RISK',
    'active config with no receipt is AT_RISK not VERIFIED' => fn() => GitHubWebhookEvidence::classify($hookActive, [], $cutoff)['verdict'] === 'AT_RISK',
    'inactive and empty history never means VERIFIED' => fn() => GitHubWebhookEvidence::classify($hookStopped, [], $cutoff)['verdict'] === 'INCONCLUSIVE',
    'older records never count as post-cutoff' => fn() => GitHubWebhookEvidence::classify($hookStopped, [$old], $cutoff)['accepted_count_after_cutoff'] === 0,
    'source secrets and endpoint URLs never appear in evidence' => fn() => !str_contains(json_encode(GitHubWebhookEvidence::classify($hookActive, [$delivery], $cutoff)), 'TOP_SECRET') && !str_contains(json_encode(GitHubWebhookEvidence::classify($hookActive, [$delivery], $cutoff)), 'secret.invalid'),
    'bad or missing date cannot provide false closure' => fn() => GitHubWebhookEvidence::classify($hookStopped, [['id'=>42,'status_code'=>200]], $cutoff)['verdict'] === 'INCONCLUSIVE',
    'post-cutoff 302 records count as GitHub-reported HTTP acceptance' => fn() => GitHubWebhookEvidence::classify($hookStopped, [array_replace($delivery,['status_code'=>302])], $cutoff)['accepted_count_after_cutoff'] === 1,
];

$pass=0;
foreach ($checks as $name=>$check) {
    if (!$check()) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    ++$pass;
    echo "PASS: $name\n";
}
echo "C4_GITHUB_CONTRACT_PASS $pass/".count($checks)."\n";
