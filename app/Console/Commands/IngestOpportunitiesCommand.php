<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Ingestion\IngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class IngestOpportunitiesCommand extends Command
{
    protected $signature = 'opportunities:ingest
                            {--source= : Only ingest this source slug}
                            {--limit=200 : Maximum items fetched per source}
                            {--dry-run : Fetch and normalise without saving}';

    protected $description = 'Fetch candidate listings from enabled sources into the review queue';

    public function handle(IngestionService $ingestion): int
    {
        $sources = Source::query()
            ->enabled()
            ->when($this->option('source'), fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No enabled sources matched. Seed them with: php artisan db:seed --class=SourceSeeder');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: nothing will be saved.');
        }

        $rows = [];

        foreach ($sources as $source) {
            try {
                $result = $ingestion->ingest($source, $limit, $dryRun);

                $rows[] = [
                    $source->name,
                    $source->adapter,
                    $result['fetched'],
                    $result['new'],
                    $result['duplicate'],
                    $result['filtered'],
                    'ok',
                ];

                foreach ($result['samples'] as $sample) {
                    $this->line('  · '.Str::limit($sample, 90));
                }
            } catch (Throwable $exception) {
                $rows[] = [$source->name, $source->adapter, 0, 0, 0, 0, 'error'];

                if (! $dryRun) {
                    $source->forceFill([
                        'last_fetched_at' => now('UTC'),
                        'last_status' => 'error',
                        'last_error' => Str::limit($exception->getMessage(), 1000, ''),
                    ])->save();
                }

                $this->error("  {$source->name}: ".Str::limit($exception->getMessage(), 160));
            }
        }

        $this->newLine();
        $this->table(
            ['Source', 'Adapter', 'Fetched', 'New', 'Duplicate', 'Filtered', 'Status'],
            $rows,
        );

        if (! $dryRun) {
            $this->line('Review candidates with: php artisan opportunities:pending');
        }

        return self::SUCCESS;
    }
}
