<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use App\Support\PolicyConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LocationSeeder extends Seeder
{
    /**
     * Seed only explicitly configured, selectable records with review details.
     *
     * The seeder prefers CSV files under database/seeders/data/nigeria/
     * (states.csv, lgas.csv) when present, otherwise falls back to
     * config('oyafind.location_catalogue_seed_records').
     */
    public function run(PolicyConfiguration $policies): void
    {
        if (! $policies->isEnabled('location_catalogue')) {
            return;
        }

        $records = $this->loadRecords();
        if (! is_array($records) || ! array_is_list($records)) {
            throw new InvalidArgumentException('Location seed records must be a list.');
        }

        $records = array_map(function (array $record): array {
            if (! array_key_exists('is_active', $record)) {
                $record['is_active'] = true;
            }
            if (! array_key_exists('catalogue_origin', $record)) {
                $record['catalogue_origin'] = 'oyafind_reviewed';
            }

            return $record;
        }, $records);

        // Resolve the system reviewer once for records that omit reviewed_by.
        $systemReviewerId = $this->resolveSystemReviewerId($records);

        $records = array_map(
            fn (array $record) => array_key_exists('reviewed_by', $record)
                ? $record
                : array_merge($record, ['reviewed_by' => $systemReviewerId]),
            $records,
        );

        foreach ($records as $record) {
            $this->assertReviewedRecord($record);
        }

        DB::transaction(function () use ($records): void {
            // First pass: upsert records without a parent so parent IDs resolve.
            $roots = array_filter($records, fn ($r) => ! isset($r['parent_slug']));
            $children = array_filter($records, fn ($r) => isset($r['parent_slug']));

            foreach ($roots as $record) {
                $this->upsertRecord($record);
            }

            // Second pass: resolve parent slugs to IDs and upsert children.
            foreach ($children as $record) {
                $parentSlug = $record['parent_slug'];
                $parent = Location::query()->where('slug', $parentSlug)->first();

                if (! $parent) {
                    throw new InvalidArgumentException(
                        "Parent location with slug [{$parentSlug}] not found. Ensure parent records are seeded first."
                    );
                }

                $record['parent_id'] = $parent->id;
                unset($record['parent_slug']);
                $this->upsertRecord($record);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRecords(): array
    {
        $configRecords = config('oyafind.location_catalogue_seed_records');
        if (is_array($configRecords) && ! empty($configRecords)) {
            return $configRecords;
        }

        $dataDir = database_path('seeders/data/nigeria');
        $statesPath = $dataDir.'/states.csv';
        $lgasPath = $dataDir.'/lgas.csv';

        if (is_file($statesPath) && is_file($lgasPath)) {
            $records = [];
            $records = array_merge($records, $this->readCsv($statesPath));
            $records = array_merge($records, $this->readCsv($lgasPath));
            return $records;
        }

        if (is_array($configRecords)) {
            return $configRecords;
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        $records = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $records;
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return $records;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 0) {
                continue;
            }
            $record = [];
            foreach ($headers as $index => $header) {
                $key = trim($header);
                if ($key === '') {
                    continue;
                }
                $value = isset($row[$index]) ? trim($row[$index]) : null;
                if ($value === '') {
                    continue;
                }
                if ($key === 'parent_id') {
                    $record[$key] = (int) $value;
                    continue;
                }
                if ($key === 'is_active') {
                    $record[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }
                $record[$key] = $value;
            }
            if (! empty($record)) {
                $records[] = $record;
            }
        }

        fclose($handle);
        return $records;
    }

    private function upsertRecord(array $record): void
    {
        Location::query()->updateOrCreate(
            ['slug' => $record['slug']],
            $record,
        );
    }

    /**
     * Find the first administrator user to attribute location review records.
     */
    private function resolveSystemReviewerId(array $records): ?int
    {
        $anyMissingReviewer = collect($records)->contains(
            fn ($r) => ! array_key_exists('reviewed_by', $r),
        );

        if (! $anyMissingReviewer) {
            return null;
        }

        $admin = User::query()->where('role', 'admin')->orderBy('id')->first();

        if (! $admin) {
            throw new InvalidArgumentException(
                'Location seeder requires at least one administrator account when reviewed_by is omitted from seed records. '
                . 'Seed an admin user before running the location seeder.'
            );
        }

        return $admin->id;
    }

    private function assertReviewedRecord(mixed $record): void
    {
        $allowedFields = [
            'name',
            'slug',
            'parent_id',
            'parent_slug',
            'location_type',
            'is_active',
            'reviewed_at',
            'reviewed_by',
            'catalogue_origin',
            'source_name',
            'source_reference',
            'source_version',
            'source_retrieved_at',
        ];

        if (! is_array($record) || array_diff(array_keys($record), $allowedFields) !== []) {
            throw new InvalidArgumentException('Location seed records contain unsupported fields.');
        }

        foreach (['name', 'slug', 'reviewed_at', 'reviewed_by', 'catalogue_origin'] as $requiredField) {
            if (! array_key_exists($requiredField, $record) || blank($record[$requiredField])) {
                throw new InvalidArgumentException(
                    'Location seed records require an approved label, origin, reviewer, and review time.'
                );
            }
        }

        if (($record['is_active'] ?? false) !== true) {
            throw new InvalidArgumentException('Only reviewed, selectable location records may be seeded.');
        }
    }
}
