<?php

namespace App\Console\Commands;

use App\Models\SourceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PendingSourceItemsCommand extends Command
{
    protected $signature = 'opportunities:pending
                            {--source= : Only show items from this source slug}
                            {--status=pending : pending, reviewed, filtered, approved, rejected or expired}
                            {--limit=50 : Maximum rows to show}';

    protected $description = 'List staged listings waiting for staff review';

    public function handle(): int
    {
        $status = (string) $this->option('status');
        $limit = max(1, (int) $this->option('limit'));

        $counts = SourceItem::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if ($counts->isNotEmpty()) {
            $this->line($counts->map(fn ($total, $name): string => "{$name}: {$total}")->implode('  ·  '));
            $this->newLine();
        }

        $items = SourceItem::query()
            ->with(['source:id,name,slug', 'location:id,name'])
            ->where('status', $status)
            ->when($this->option('source'), fn ($query, $slug) => $query->whereHas(
                'source',
                fn ($source) => $source->where('slug', $slug),
            ))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->info("No {$status} source items.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Source', 'Category', 'Area', 'Closes', 'Title'],
            $items->map(fn (SourceItem $item): array => [
                $item->id,
                $item->source?->name,
                $item->category_slug ?? '—',
                $item->location?->name ?? '—',
                $item->deadline_at?->format('Y-m-d') ?? '—',
                Str::limit($item->title, 70),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
