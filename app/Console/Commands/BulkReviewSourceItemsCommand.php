<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\SourceItem;
use App\Models\User;
use App\Services\OpportunityPublicationService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bulk review for the ingestion queue.
 *
 * The interactive commands exist so a person can look at each candidate. This
 * command is for the operator who wants the queue worked through without
 * clicking 500 times: it fetches every canonical page first and only approves
 * items whose source is live and still carries the listing. Anything that
 * fails the check stays in the queue for a human.
 */
class BulkReviewSourceItemsCommand extends Command
{
    protected $signature = 'opportunities:bulk-review
                            {--reviewer= : Staff reviewer id or email}
                            {--verifier= : Verifier id or email, must differ from the reviewer}
                            {--limit=400 : Maximum candidates to inspect}
                            {--per-category=25 : Maximum listings to publish per category}
                            {--max-age-months=12 : Ignore candidates first published before this many months ago}
                            {--dry-run : Check the sources without approving anything}';

    protected $description = 'Check staged listings against their source pages, then approve and verify the ones that pass';

    public function handle(OpportunityPublicationService $publication): int
    {
        $reviewer = $this->resolveUser($this->option('reviewer'));
        $verifier = $this->resolveUser($this->option('verifier'));

        if ($reviewer === null || $verifier === null) {
            $this->error('Pass --reviewer=ID and --verifier=ID. They must be different allowlisted accounts.');
            $this->line('Reviewers: OYAFIND_PUBLIC_STAFF_REVIEWER_IDS. Verifiers: OYAFIND_PUBLIC_VERIFIER_IDS.');

            return self::FAILURE;
        }

        if ($reviewer->id === $verifier->id) {
            $this->error('The reviewer and verifier must be different people.');

            return self::FAILURE;
        }

        try {
            $publication->policyFor($reviewer, $verifier);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->pluck('slug')
            ->all();

        $items = SourceItem::query()
            ->pending()
            ->with('source:id,name,slug,adapter,default_category_slug')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($items->isEmpty()) {
            $this->info('No pending candidates. Run opportunities:ingest first.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $perCategory = max(1, (int) $this->option('per-category'));
        $maxAgeMonths = (int) $this->option('max-age-months');
        $oldestAllowed = $maxAgeMonths > 0 ? now()->subMonths($maxAgeMonths) : null;

        $published = 0;
        $failed = 0;
        $skipped = 0;
        $byCategory = [];

        foreach ($items as $item) {
            $slug = (string) $item->category_slug;

            if (! in_array($slug, $categories, true)) {
                $skipped++;

                continue;
            }

            if (($byCategory[$slug] ?? 0) >= $perCategory) {
                $skipped++;

                continue;
            }

            if ($item->deadline_at !== null && $item->deadline_at->isPast()) {
                $skipped++;

                continue;
            }

            // A job board's "Procurement Manager" ad is a job, not a tender,
            // and "Business Support Officer" is a role, not a support
            // programme. Keyword matching alone gets these wrong.
            if ($item->source?->default_category_slug === 'jobs'
                && in_array($slug, ['tenders', 'business-support'], true)) {
                $skipped++;

                continue;
            }

            // Category feeds on the scholarship sites still carry posts from
            // years ago. A stale listing is worse than an empty page, so the
            // queue drops anything older than the configured window.
            if ($oldestAllowed !== null && $item->published_at !== null && $item->published_at->lt($oldestAllowed)) {
                $skipped++;

                continue;
            }

            $verdict = $this->checkSource($item);

            if ($verdict !== null) {
                $failed++;
                $this->line(sprintf('<fg=yellow>skip</> #%d %s — %s', $item->id, $item->title, $verdict));

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('<fg=green>ok</> #%d [%s] %s', $item->id, $slug, $item->title));

                continue;
            }

            try {
                $publication->approve($item, $reviewer);
                $publication->publish($item, $reviewer, $verifier);

                $published++;
                $byCategory[$slug] = ($byCategory[$slug] ?? 0) + 1;
                $this->line(sprintf('<fg=green>published</> #%d [%s] %s', $item->id, $slug, $item->title));
            } catch (Throwable $exception) {
                $failed++;
                $this->line(sprintf('<fg=red>failed</> #%d %s — %s', $item->id, $item->title, $exception->getMessage()));
            }
        }

        $this->newLine();
        $this->table(
            ['Published', 'Skipped', 'Failed checks'],
            [[$published, $skipped, $failed]],
        );

        foreach ($byCategory as $slug => $count) {
            $this->line(sprintf('  %s: %d', $slug, $count));
        }

        return self::SUCCESS;
    }

    /**
     * Returns null when the source page is live and still carries the listing.
     */
    private function checkSource(SourceItem $item): ?string
    {
        if ($item->canonical_url === null || $item->canonical_url === '') {
            return 'no source URL';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('oyafind.ingestion.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml',
            ])->timeout(15)->get($item->canonical_url);
        } catch (Throwable $exception) {
            return 'source unreachable';
        }

        if (! $response instanceof Response || ! $response->successful()) {
            return 'source returned '.(string) ($response?->status() ?? 'no response');
        }

        $body = $response->body();

        // projects.worldbank.org renders notices client-side, so the title is
        // never in the HTML. The adapter already validated the record against
        // the procurement API; a 200 here is the strongest check available.
        if ($item->source?->adapter === 'world_bank') {
            return null;
        }

        if ($this->mentionsTitle($body, (string) $item->title)) {
            return null;
        }

        return 'title no longer on the page';
    }

    private function mentionsTitle(string $body, string $title): bool
    {
        $haystack = $this->normalise(strip_tags($body));
        $needle = $this->normalise($title);

        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }

        $words = array_values(array_filter(
            explode(' ', $needle),
            static fn (string $word): bool => mb_strlen($word) > 3,
        ));

        if ($words === []) {
            return false;
        }

        $found = 0;

        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $found++;
            }
        }

        return ($found / count($words)) >= 0.6;
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function resolveUser(?string $needle): ?User
    {
        if ($needle === null || trim($needle) === '') {
            return null;
        }

        return User::query()
            ->where('id', (int) $needle)
            ->orWhere('email', $needle)
            ->first();
    }
}
