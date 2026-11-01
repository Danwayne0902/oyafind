<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Opportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply an owner-reviewed location mapping to existing listings.
 *
 * Each row names the listing by its stable public slug, the reviewed
 * catalogue location, and the evidence used to choose it. Rows are validated
 * before anything is written so a half-applied mapping cannot publish a
 * listing under the wrong area.
 */
class BackfillOpportunityLocationsCommand extends Command
{
    protected $signature = 'locations:backfill
        {file : CSV with slug,location_slug,evidence columns}
        {--overwrite : Replace a location that is already set}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Assign reviewed locations to existing listings from an evidence CSV';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("Mapping file not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readMapping($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        $locationSlugs = array_values(array_unique(array_column($rows, 'location_slug')));
        $locations = Location::query()
            ->whereIn('slug', $locationSlugs)
            ->get()
            ->keyBy('slug');

        $invalidLocations = array_values(array_filter(
            $locationSlugs,
            function (string $slug) use ($locations): bool {
                $location = $locations->get($slug);

                return $location === null
                    || ! $location->is_active
                    || $location->reviewed_at === null
                    || $location->reviewed_by === null
                    || blank($location->catalogue_origin);
            },
        ));

        if ($invalidLocations !== []) {
            $this->error('These are not selectable reviewed locations: '.implode(', ', $invalidLocations));

            return self::FAILURE;
        }

        $opportunities = Opportunity::query()
            ->whereIn('slug', array_column($rows, 'slug'))
            ->get(['id', 'slug', 'location_id'])
            ->keyBy('slug');

        $missing = array_values(array_diff(array_column($rows, 'slug'), $opportunities->keys()->all()));
        if ($missing !== []) {
            $this->error('These listing slugs do not exist: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');
        $changed = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $locations, $opportunities, $overwrite, $dryRun, &$changed, &$skipped): void {
            foreach ($rows as $row) {
                $opportunity = $opportunities->get($row['slug']);
                $location = $locations->get($row['location_slug']);

                if ($opportunity->location_id !== null
                    && (int) $opportunity->location_id !== (int) $location->id
                    && ! $overwrite) {
                    $skipped++;

                    continue;
                }

                if ((int) $opportunity->location_id === (int) $location->id) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $opportunity->forceFill(['location_id' => $location->id])->save();

                    DB::table('source_items')
                        ->where('opportunity_id', $opportunity->id)
                        ->update([
                            'location_id' => $location->id,
                            'updated_at' => now(),
                        ]);
                }

                $changed++;
            }
        });

        $this->info(sprintf(
            '%s %d listing(s); %d unchanged or protected.',
            $dryRun ? 'Would update' : 'Updated',
            $changed,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<array{slug: string, location_slug: string, evidence: string}>|null
     */
    private function readMapping(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('The mapping file could not be opened.');

            return null;
        }

        $headers = fgetcsv($handle);
        $headers = is_array($headers) ? array_map('trim', $headers) : [];
        $required = ['slug', 'location_slug', 'evidence'];

        if (array_diff($required, $headers) !== []) {
            fclose($handle);
            $this->error('The mapping CSV needs slug,location_slug,evidence columns.');

            return null;
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $record = [];
            foreach ($headers as $index => $header) {
                $record[$header] = trim((string) ($values[$index] ?? ''));
            }

            if ($record['slug'] === '' && $record['location_slug'] === '') {
                continue;
            }

            foreach ($required as $field) {
                if ($record[$field] === '') {
                    fclose($handle);
                    $this->error("Line {$line} is missing {$field}.");

                    return null;
                }
            }

            $rows[] = [
                'slug' => $record['slug'],
                'location_slug' => $record['location_slug'],
                'evidence' => $record['evidence'],
            ];
        }

        fclose($handle);

        if ($rows === []) {
            $this->error('The mapping file has no rows.');

            return null;
        }

        return $rows;
    }
}
