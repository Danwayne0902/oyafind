<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportStreetsCommand extends Command
{
    protected $signature = 'locations:import-streets
                            {file : Path to CSV or JSON file}
                            {--reviewer= : User ID of the reviewer}
                            {--chunk=500 : Number of rows per transaction}';

    protected $description = 'Import streets/wards with provenance for the Nigeria location catalogue';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $reviewerId = $this->option('reviewer');
        if (! $reviewerId) {
            $admin = User::query()->where('role', 'admin')->orderBy('id')->first();
            if (! $admin) {
                $this->error('No admin user found. Provide --reviewer=ID');
                return self::FAILURE;
            }
            $reviewerId = $admin->id;
        }

        $reviewer = User::find($reviewerId);
        if (! $reviewer) {
            $this->error("Reviewer not found: {$reviewerId}");
            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $records = $this->readFile($file);
        if (empty($records)) {
            $this->warn('No records to import');
            return self::SUCCESS;
        }

        $this->info('Importing '.count($records).' records...');
        $bar = $this->output->createProgressBar(count($records));
        $bar->start();

        $chunks = array_chunk($records, $chunkSize);
        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $reviewer): void {
                foreach ($chunk as $record) {
                    $this->upsertRecord($record, $reviewer->id);
                }
            });
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import complete.');
        return self::SUCCESS;
    }

    private function readFile(string $file): array
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $content = file_get_contents($file);
            if ($content === false) {
                return [];
            }
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }

        $records = [];
        $handle = fopen($file, 'r');
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
                $record[$key] = $value;
            }
            if (! empty($record)) {
                $records[] = $record;
            }
        }

        fclose($handle);
        return $records;
    }

    private function upsertRecord(array $record, int $reviewerId): void
    {
        $name = $record['name'] ?? null;
        $slug = $record['slug'] ?? Str::slug($name ?? '');
        $parentSlug = $record['parent_slug'] ?? null;
        $locationType = $record['location_type'] ?? 'street';

        if (! $name || ! $slug) {
            return;
        }

        $parentId = null;
        if ($parentSlug) {
            $parent = Location::query()->where('slug', $parentSlug)->first();
            if (! $parent) {
                return;
            }
            $parentId = $parent->id;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'location_type' => $locationType,
            'is_active' => isset($record['is_active']) ? filter_var($record['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
            'reviewed_at' => $record['review_at'] ?? $record['reviewed_at'] ?? now(),
            'reviewed_by' => $reviewerId,
            'catalogue_origin' => $record['catalogue_origin'] ?? 'osm',
            'source_name' => $record['source_name'] ?? 'OpenStreetMap',
            'source_reference' => $record['source_reference'] ?? ($record['osm_id'] ?? null),
            'source_version' => $record['source_version'] ?? 'extract',
            'source_retrieved_at' => $record['source_retrieved_at'] ?? now()->toDateString(),
        ];

        Location::query()->updateOrCreate(['slug' => $slug], $data);
    }
}
