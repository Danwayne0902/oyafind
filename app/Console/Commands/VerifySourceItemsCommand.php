<?php

namespace App\Console\Commands;

use App\Models\SourceItem;
use App\Models\User;
use App\Services\OpportunityPublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class VerifySourceItemsCommand extends Command
{
    protected $signature = 'opportunities:verify
                            {--verifier= : Verifier id or email}
                            {--source= : Verify only this source slug}
                            {--limit=25 : Maximum items to verify in this session}';

    protected $description = 'Run the required source checks and publish staff-approved listings';

    public function handle(OpportunityPublicationService $publication): int
    {
        $verifier = $this->resolveUser($this->option('verifier'));

        if ($verifier === null) {
            $this->error('Pass --verifier=ID (an allowlisted verifier account, different from the reviewer).');
            $this->line('Eligible ids are set by OYAFIND_PUBLIC_VERIFIER_IDS.');

            return self::FAILURE;
        }

        try {
            $policy = $publication->verifierPolicyFor($verifier);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('This command is interactive. Run it in a terminal, or use opportunities:pending --status=reviewed.');

            return self::FAILURE;
        }

        $items = SourceItem::query()
            ->where('status', SourceItem::STATUS_REVIEWED)
            ->with(['source:id,name,slug', 'location:id,name', 'reviewer:id,name'])
            ->when($this->option('source'), fn ($query, $slug) => $query->whereHas(
                'source',
                fn ($source) => $source->where('slug', $slug),
            ))
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($items->isEmpty()) {
            $this->info('No staff-approved items are waiting for verification.');

            return self::SUCCESS;
        }

        $this->line("Verifier: {$verifier->name} (#{$verifier->id})");
        $this->line('Required checks: '.implode(', ', $policy['required_types']));
        $this->newLine();

        $published = 0;
        $rejected = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $reviewer = $item->reviewer;

            $this->line('<fg=cyan>#'.$item->id.'</> <options=bold>'.Str::limit($item->title, 100).'</>');
            $this->line(sprintf(
                '   %s · %s · closes %s · via %s · approved by %s',
                $item->category_slug ?? 'no category',
                $item->location?->name ?? 'all areas',
                $item->deadline_at?->format('Y-m-d') ?? 'not stated',
                $item->source?->name ?? 'unknown source',
                $reviewer?->name ?? 'unknown reviewer',
            ));
            $this->line('   '.$item->canonical_url);
            $this->line('   '.Str::limit((string) ($item->summary ?: $item->excerpt), 180));
            $this->line('   Open the source page and confirm the title, organisation, area and deadline.');

            $action = $this->choice('Decision', ['publish', 'reject', 'skip', 'quit'], 'skip');

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

                try {
                    $publication->reject($item, $verifier, $reason);
                    $rejected++;
                    $this->line('<fg=yellow>Rejected.</>');
                } catch (Throwable $exception) {
                    $this->error('Not rejected: '.$exception->getMessage());
                }

                $this->newLine();

                continue;
            }

            if ($reviewer === null) {
                $this->error('The recorded reviewer account no longer exists, so this item cannot be published.');
                $this->newLine();

                continue;
            }

            try {
                $opportunity = $publication->publish($item, $reviewer, $verifier);
                $published++;
                $this->info("Published: /opportunities/{$opportunity->slug}");
            } catch (Throwable $exception) {
                $this->error('Not published: '.$exception->getMessage());
            }

            $this->newLine();
        }

        $this->table(
            ['Published', 'Rejected', 'Skipped'],
            [[$published, $rejected, $skipped]],
        );

        return self::SUCCESS;
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
