<?php

declare(strict_types=1);

namespace Stillwired\Integration;

/**
 * Evidence classification for GitHub's provider-side webhook delivery log.
 * It never promotes a lack of observations to a VERIFIED conclusion.
 */
final class GitHubWebhookEvidence
{
    /** @param array<string, mixed> $hook @param list<array<string, mixed>> $deliveries
     *  @return array<string, mixed>
     */
    public static function classify(array $hook, array $deliveries, \DateTimeImmutable $since): array
    {
        if (!array_key_exists('active', $hook) || !is_bool($hook['active'])) {
            throw new \InvalidArgumentException('The webhook active flag must be a boolean.');
        }
        $attempts = [];
        $accepted = [];
        $uncertainRows = 0;
        foreach ($deliveries as $d) {
            $raw = $d['delivered_at'] ?? null;
            if (!is_string($raw) || trim($raw) === '') {
                ++$uncertainRows;
                continue;
            }
            try {
                $date = new \DateTimeImmutable($raw);
            } catch (\Throwable) {
                ++$uncertainRows;
                continue;
            }
            if ($date < $since) {
                continue;
            }
            $code = $d['status_code'] ?? null;
            $good = is_int($code) && $code >= 200 && $code < 400;
            $item = [
                'delivery_id' => (string) ($d['id'] ?? 'unknown'),
                'guid' => is_string($d['guid'] ?? null) ? $d['guid'] : null,
                'delivered_at' => $date->format(DATE_ATOM),
                'http_status_code' => is_int($code) ? $code : null,
                'accepted_by_destination_http' => $good,
                'is_redelivery' => ($d['redelivery'] ?? false) === true,
            ];
            $attempts[] = $item;
            if ($good) {
                $accepted[] = $item;
            }
        }
        // These are provider-side records, not independently signed sink receipts.
        $finding = count($accepted) > 0 ? 'POST_CUTOFF_HTTP_ACCEPTED'
            : (count($attempts) > 0 ? 'POST_CUTOFF_ATTEMPT_OBSERVED'
            : (($hook['active'] === true) ? 'ACTIVE_CONFIGURATION_NO_POST_CUTOFF_RECORD' : 'NO_POST_CUTOFF_RECORD'));

        return [
            'integration' => 'github_repository_webhook',
            'source_type' => 'REAL_PROVIDER_LOG_NOT_INDEPENDENT_SINK',
            'scope' => ['hook_id' => (int) ($hook['id'] ?? 0)],
            'cutoff_utc' => $since->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'cutoff_origin' => 'supplied_by_operator_not_independently_verified',
            'provider_configured_active' => $hook['active'],
            'observation' => $finding,
            'accepted_count_after_cutoff' => count($accepted),
            'attempt_count_after_cutoff' => count($attempts),
            'delivery_records_examined' => count($deliveries),
            'unparseable_records' => $uncertainRows,
            'pagination_exhaustive' => false,
            'verdict' => $accepted !== [] ? 'EXPOSED'
                : (($attempts !== [] || $hook['active']) ? 'AT_RISK' : 'INCONCLUSIVE'),
            'provider_receipts_after_cutoff' => $attempts,
            'limitations' => [
                'GitHub-side HTTP result is not an independently verified receiver receipt',
                'No webhook payloads, tokens or destination URL retained',
                'Latest page only; lack of recent deliveries never proves closure',
                'No automatic offboarding-time or identity-revocation verification',
                'Read-only audit: no ping, redelivery, disable or deletion performed',
            ],
        ];
    }
}
