<?php

namespace App\Services\Ingestion;

use App\Models\Source;
use App\Models\SourceItem;
use Illuminate\Support\Facades\DB;

/**
 * Fetches every enabled source and stages new candidates.
 *
 * Failures are isolated per source: one unreachable feed never stops the
 * others, and a source's error is recorded for the operator.
 */
class IngestionService
{
    public function __construct(
        private readonly RssAdapter $rss,
        private readonly WorldBankAdapter $worldBank,
        private readonly OpportunityNormaliser $normaliser,
    ) {}

    /**
     * @return array{fetched: int, new: int, duplicate: int, filtered: int, samples: list<string>}
     */
    public function ingest(Source $source, int $limit = 200, bool $dryRun = false): array
    {
        $adapter = match ($source->adapter) {
            'rss' => $this->rss,
            'world_bank' => $this->worldBank,
            default => throw new \RuntimeException("Unknown adapter [{$source->adapter}] for [{$source->slug}]."),
        };

        $limit = max(1, $limit);
        $rawItems = $adapter->fetch($source, $limit);

        $normalised = [];
        foreach ($rawItems as $raw) {
            $result = $this->normaliser->normalise($source, $raw);
            $result['attributes']['source_id'] = $source->id;
            $normalised[] = $result;
        }

        $pending = array_values(array_filter(
            $normalised,
            fn (array $result): bool => $result['status'] === SourceItem::STATUS_PENDING,
        ));

        $existingHashes = SourceItem::query()
            ->whereIn('url_hash', array_column(array_column($pending, 'attributes'), 'url_hash'))
            ->pluck('url_hash')
            ->flip();

        $existingDedupeKeys = SourceItem::query()
            ->whereIn('dedupe_key', array_column(array_column($pending, 'attributes'), 'dedupe_key'))
            ->whereIn('status', [SourceItem::STATUS_PENDING, SourceItem::STATUS_REVIEWED, SourceItem::STATUS_APPROVED])
            ->pluck('dedupe_key')
            ->flip();

        $created = 0;
        $duplicate = 0;
        $filtered = 0;
        $samples = [];

        foreach ($normalised as $result) {
            $attributes = $result['attributes'];

            if ($result['status'] === SourceItem::STATUS_FILTERED) {
                $filtered++;

                if (! $dryRun) {
                    SourceItem::query()->firstOrCreate(
                        ['url_hash' => $attributes['url_hash']],
                        $attributes,
                    );
                }

                continue;
            }

            if ($existingHashes->has($attributes['url_hash'])) {
                $duplicate++;

                continue;
            }

            if ($attributes['dedupe_key'] !== null && $existingDedupeKeys->has($attributes['dedupe_key'])) {
                $duplicate++;

                continue;
            }

            if ($dryRun) {
                $created++;
                if (count($samples) < 5) {
                    $samples[] = $attributes['title'];
                }

                continue;
            }

            SourceItem::query()->firstOrCreate(
                ['url_hash' => $attributes['url_hash']],
                $attributes,
            );

            $existingHashes[$attributes['url_hash']] = true;
            if ($attributes['dedupe_key'] !== null) {
                $existingDedupeKeys[$attributes['dedupe_key']] = true;
            }

            $created++;
            if (count($samples) < 5) {
                $samples[] = $attributes['title'];
            }
        }

        if (! $dryRun) {
            DB::transaction(function () use ($source, $rawItems): void {
                $source->forceFill([
                    'last_fetched_at' => now('UTC'),
                    'last_status' => 'ok',
                    'last_error' => null,
                    'last_item_count' => count($rawItems),
                ])->save();
            });
        }

        return [
            'fetched' => count($rawItems),
            'new' => $created,
            'duplicate' => $duplicate,
            'filtered' => $filtered,
            'samples' => $samples,
        ];
    }
}
