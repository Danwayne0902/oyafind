<?php

namespace App\Services\Ingestion;

use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the World Bank procurement notice API.
 *
 * Only open notices are kept; contract awards are history, not opportunities.
 * Contact names, emails and phone numbers in the API are deliberately dropped
 * and never reach the staging table.
 */
class WorldBankAdapter implements SourceAdapter
{
    private const OPEN_NOTICE_TYPES = [
        'Invitation for Bids',
        'Invitation for Prequalification',
        'Request for Expression of Interest',
        'Request for Expressions of Interest',
        'Request for Quotations',
        'General Procurement Notice',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(Source $source, int $limit): array
    {
        $separator = str_contains($source->feed_url, '?') ? '&' : '?';

        $response = Http::withHeaders([
            'User-Agent' => (string) config('oyafind.ingestion.user_agent'),
            'Accept' => 'application/json',
        ])
            ->timeout((int) config('oyafind.ingestion.timeout', 15))
            ->retry(2, 300)
            ->get($source->feed_url.$separator.'rows='.max(1, $limit).'&os=0');

        $response->throw();

        $payload = $response->json();
        $notices = $payload['procnotices'] ?? null;

        if (! is_array($notices)) {
            throw new RuntimeException("The World Bank response for [{$source->slug}] has no notices.");
        }

        $items = [];

        foreach ($notices as $notice) {
            if (! is_array($notice)) {
                continue;
            }

            if (($notice['project_ctry_name'] ?? $notice['contact_ctry_name'] ?? null) !== 'Nigeria') {
                continue;
            }

            $noticeType = (string) ($notice['notice_type'] ?? '');
            if (! in_array($noticeType, self::OPEN_NOTICE_TYPES, true)) {
                continue;
            }

            $reference = (string) ($notice['id'] ?? '');
            if ($reference === '') {
                continue;
            }

            $description = trim((string) ($notice['bid_description'] ?? ''));
            $project = trim((string) ($notice['project_name'] ?? ''));
            $title = $description !== ''
                ? $description
                : ($project !== '' ? "{$noticeType}: {$project}" : $noticeType);

            $organisation = trim((string) ($notice['contact_organization'] ?? '')) ?: $project;
            $address = trim(implode(' ', array_filter([
                (string) ($notice['contact_address'] ?? ''),
                (string) ($notice['contact_ctry_name'] ?? ''),
            ])));

            $items[] = [
                'external_id' => $reference,
                'title' => $title,
                'url' => "https://projects.worldbank.org/en/projects-operations/procurement-detail/{$reference}",
                'published_at' => $this->parseNoticeDate((string) ($notice['noticedate'] ?? '')),
                'summary_source' => trim("{$noticeType} · {$project}", ' ·'),
                'content_source' => (string) ($notice['notice_text'] ?? ''),
                'categories' => ['Tenders'],
                'organisation' => $organisation !== '' ? $organisation : null,
                'deadline_at' => $this->parseDeadline(
                    (string) ($notice['submission_deadline_date'] ?? ''),
                    (string) ($notice['submission_deadline_time'] ?? ''),
                ),
                'location_text' => $address !== '' ? $address : null,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function parseNoticeDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!d-M-Y', $value, 'UTC') ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDeadline(string $date, string $time): ?CarbonImmutable
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        try {
            $deadline = CarbonImmutable::parse($date, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $matches) === 1) {
            $deadline = $deadline->setTime((int) $matches[1], (int) $matches[2]);
        }

        return $deadline;
    }
}
