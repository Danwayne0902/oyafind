<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\SourceItem;
use App\Models\User;
use App\Services\OpportunityPublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class ReviewSourceItemsCommand extends Command
{
    protected $signature = 'opportunities:review
                            {--reviewer= : Staff reviewer id or email}
                            {--source= : Review only this source slug}
                            {--limit=25 : Maximum items to review in this session}';

    protected $description = 'Approve or reject staged listings; approval queues them for a separate verifier';

    public function handle(OpportunityPublicationService $publication): int
    {
        $reviewer = $this->resolveUser($this->option('reviewer'));

        if ($reviewer === null) {
            $this->error('Pass --reviewer=ID (an allowlisted staff account).');
            $this->line('Eligible ids are set by OYAFIND_PUBLIC_STAFF_REVIEWER_IDS.');

            return self::FAILURE;
        }

        try {
            $policy = $publication->reviewerPolicyFor($reviewer);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('This command is interactive. Run it in a terminal, or use opportunities:pending to list candidates.');

            return self::FAILURE;
        }

        $items = SourceItem::query()
            ->pending()
            ->with(['source:id,name,slug', 'location:id,name'])
            ->when($this->option('source'), fn ($query, $slug) => $query->whereHas(
                'source',
                fn ($source) => $source->where('slug', $slug),
            ))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($items->isEmpty()) {
            $this->info('No pending source items. Run opportunities:ingest first.');

            return self::SUCCESS;
        }

        $this->line("Reviewer: {$reviewer->name} (#{$reviewer->id})");
        $this->line('Approval queues each listing for a separate verifier; nothing is public until then.');
        $this->line('Verifier check types: '.implode(', ', $policy['required_types']));
        $this->newLine();

        $approved = 0;
        $rejected = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $this->line('<fg=cyan>#'.$item->id.'</> <options=bold>'.Str::limit($item->title, 100).'</>');
            $this->line(sprintf(
                '   %s · %s · closes %s · via %s',
                $item->category_slug ?? 'no category',
                $item->location?->name ?? 'all areas',
                $item->deadline_at?->format('Y-m-d') ?? 'not stated',
                $item->source?->name ?? 'unknown source',
            ));
            $this->line('   '.$item->canonical_url);
            $this->line('   '.Str::limit((string) ($item->summary ?: $item->excerpt), 180));

            while (true) {
                $action = $this->choice(
                    'Decision',
                    ['approve', 'edit', 'reject', 'skip', 'open', 'quit'],
                    'skip',
                );

                if ($action === 'open') {
                    $this->line('   Open the source page, check the title, organisation, area and deadline, then decide.');

                    continue;
                }

                if ($action === 'edit') {
                    $this->editCategory($item);

                    continue;
                }

                break;
            }

            if ($action === 'quit') {
                break;
            }

            if ($action === 'skip') {
                $skipped++;
                $this->newLine();

                continue;
            }

            if ($action === 'reject') {
                $reason = (string) $this->ask('Reason (optional)');
                $publication->reject($item, $reviewer, $reason);
                $rejected++;
                $this->line('<fg=yellow>Rejected.</>');
                $this->newLine();

                continue;
            }

            try {
                $publication->approve($item, $reviewer);
                $approved++;
                $this->info('Queued for verification.');
                $this->line('   Verify with: php artisan opportunities:verify');
            } catch (Throwable $exception) {
                $this->error('Not approved: '.$exception->getMessage());
            }

            $this->newLine();
        }

        $this->table(
            ['Queued for verification', 'Rejected', 'Skipped'],
            [[$approved, $rejected, $skipped]],
        );

        return self::SUCCESS;
    }

    private function editCategory(SourceItem $item): void
    {
        $slugs = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();

        if ($slugs === []) {
            $this->error('No active categories exist.');

            return;
        }

        $current = in_array($item->category_slug, $slugs, true) ? $item->category_slug : $slugs[0];
        $chosen = (string) $this->choice('Category', $slugs, $current);

        $item->forceFill(['category_slug' => $chosen])->save();
        $this->line("   Category set to {$chosen}.");
    }

    private function resolveUser(mixed $value): ?User
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return ctype_digit($value)
            ? User::query()->whereKey((int) $value)->first()
            : User::query()->where('email', $value)->first();
    }
}
