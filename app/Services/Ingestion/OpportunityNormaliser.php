<?php

namespace App\Services\Ingestion;

use App\Models\Category;
use App\Models\Location;
use App\Models\Source;
use App\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Turns a raw feed record into a staging row.
 *
 * The normaliser is deliberately conservative: it removes contact details,
 * rejects roundup posts and off-domain links, and never guesses an amount,
 * eligibility rule or deadline that the source did not state.
 */
class OpportunityNormaliser
{
    /** @var list<array{id: int, name: string, slug: string}>|null */
    private ?array $states = null;

    /** @var list<string>|null */
    private ?array $categorySlugs = null;

    /**
     * Cities and common aliases mapped to the reviewed state slug.
     *
     * @var array<string, list<string>>
     */
    private const CITY_ALIASES = [
        'fct-abuja' => ['abuja', 'gwarinpa', 'wuse', 'maitama', 'gwagwalada', 'bwari', 'kuje'],
        'lagos' => ['ikeja', 'lekki', 'victoria island', 'yaba', 'surulere', 'ikoyi', 'ajah', 'apapa', 'maryland'],
        'oyo' => ['ibadan', 'ogbomoso'],
        'rivers' => ['port harcourt'],
        'edo' => ['benin city'],
        'delta' => ['warri', 'asaba', 'effurun', 'sapele'],
        'anambra' => ['onitsha', 'awka', 'nnewi'],
        'abia' => ['aba', 'umuahia'],
        'imo' => ['owerri'],
        'akwa-ibom' => ['uyo'],
        'cross-river' => ['calabar'],
        'benue' => ['makurdi'],
        'borno' => ['maiduguri'],
        'kwara' => ['ilorin'],
        'ogun' => ['abeokuta', 'sagamu'],
        'ondo' => ['akure'],
        'osun' => ['osogbo', 'ilesa'],
        'ekiti' => ['ado-ekiti', 'ado ekiti'],
        'kogi' => ['lokoja'],
        'niger' => ['minna', 'suleja'],
        'bayelsa' => ['yenagoa'],
        'jigawa' => ['dutse'],
        'yobe' => ['damaturu'],
        'nasarawa' => ['lafia', 'keffi'],
        'taraba' => ['jalingo'],
        'adamawa' => ['yola'],
        'kebbi' => ['birnin kebbi'],
        'zamfara' => ['gusau'],
        'kaduna' => ['zaria'],
        'enugu' => ['nsukka'],
        'plateau' => ['jos'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_RULES = [
        'internships' => ['internship', 'intern programme', 'graduate trainee', 'trainee programme', 'industrial training'],
        'scholarships' => ['scholarship', 'tuition', 'study abroad', 'fully funded'],
        'tenders' => ['tender', 'procurement', 'invitation for bid', 'request for expression of interest', 'expression of interest'],
        'grants' => ['grant', 'seed funding', 'funding opportunity', 'award', 'prize'],
        'training' => ['training', 'bootcamp', 'boot camp', 'course', 'workshop', 'fellowship', 'masterclass', 'certification'],
        'business-support' => ['accelerator', 'incubator', 'business support', 'sme support', 'mentorship programme', 'entrepreneur'],
        'gigs' => ['gig', 'freelance', 'part-time', 'part time', 'contractor'],
        'jobs' => ['job', 'vacancy', 'recruitment', 'hiring', 'career', 'full-time', 'full time'],
    ];

    /**
     * @var array<string, string>
     */
    private const CATEGORY_ALIASES = [
        'internship' => 'internships',
        'internships' => 'internships',
        'scholarship' => 'scholarships',
        'scholarships' => 'scholarships',
        'grant' => 'grants',
        'grants' => 'grants',
        'funding' => 'grants',
        'awards' => 'grants',
        'training' => 'training',
        'courses' => 'training',
        'fellowships' => 'training',
        'fellowship' => 'training',
        'tenders' => 'tenders',
        'procurement' => 'tenders',
        'bids' => 'tenders',
        'business' => 'business-support',
        'sme' => 'business-support',
        'gigs' => 'gigs',
        'freelance' => 'gigs',
        'jobs' => 'jobs',
        'job' => 'jobs',
        'vacancies' => 'jobs',
        'careers' => 'jobs',
    ];

    /**
     * @param  array<string, mixed>  $raw
     * @return array{status: string, reason: string|null, attributes: array<string, mixed>}
     */
    public function normalise(Source $source, array $raw): array
    {
        $title = Str::limit($this->clean((string) ($raw['title'] ?? '')), 300, '');
        $canonicalUrl = $this->canonicalUrl((string) ($raw['url'] ?? ''));

        if (mb_strlen($title) < 10) {
            return $this->filtered($raw, $title, $canonicalUrl, 'low_quality');
        }

        if ($canonicalUrl === null) {
            return $this->filtered($raw, $title, null, 'invalid_url');
        }

        if (! $this->hostAllowed($canonicalUrl, $source)) {
            return $this->filtered($raw, $title, $canonicalUrl, 'offsite_link');
        }

        if ($this->isRoundup($title)) {
            return $this->filtered($raw, $title, $canonicalUrl, 'roundup');
        }

        if ($this->isNotAListing($title)) {
            return $this->filtered($raw, $title, $canonicalUrl, 'not_a_listing');
        }

        $content = $this->clean((string) ($raw['content_source'] ?? ''));
        $summarySource = $this->clean((string) ($raw['summary_source'] ?? ''));
        $summaryText = $summarySource !== '' ? $summarySource : $content;

        if ($summaryText === '' && $content === '') {
            return $this->filtered($raw, $title, $canonicalUrl, 'empty_content');
        }

        $excerpt = Str::limit($content !== '' ? $content : $summaryText, (int) config('oyafind.ingestion.excerpt_length', 1500), '…');
        $summary = Str::limit($summaryText !== '' ? $summaryText : $excerpt, 280, '…');

        $deadline = $this->parseDate($raw['deadline_at'] ?? null)
            ?? $this->deadlineFromText($summaryText.' '.$excerpt);

        if ($deadline !== null && $deadline->isPast()) {
            return $this->filtered($raw, $title, $canonicalUrl, 'expired', $deadline);
        }

        $locationText = $this->clean((string) ($raw['location_text'] ?? ''));
        $location = $this->locationFor(trim($locationText.' '.$title.' '.Str::limit($excerpt, 600)));
        $organisation = $this->organisationFor($source, $raw, $title, $location['matched']);
        $category = $this->categoryFor($source, $raw['categories'] ?? [], $title);
        $publishedAt = $this->parseDate($raw['published_at'] ?? null);
        $dedupeKey = hash('sha256', mb_strtolower($title.'|'.$organisation));

        return [
            'status' => SourceItem::STATUS_PENDING,
            'reason' => null,
            'attributes' => [
                'source_id' => $source->id,
                'external_id' => Str::limit((string) ($raw['external_id'] ?? ''), 200, '') ?: null,
                'url_hash' => hash('sha256', $canonicalUrl),
                'canonical_url' => $canonicalUrl,
                'title' => $title,
                'organisation_name' => $organisation,
                'summary' => $summary,
                'excerpt' => $excerpt,
                'published_at' => $publishedAt?->format('Y-m-d H:i:s'),
                'deadline_at' => $deadline?->format('Y-m-d H:i:s'),
                'location_text' => $locationText !== '' ? Str::limit($locationText, 200, '') : null,
                'location_id' => $location['id'],
                'category_slug' => $category,
                'status' => SourceItem::STATUS_PENDING,
                'status_reason' => null,
                'dedupe_key' => $dedupeKey,
                'fetched_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{status: string, reason: string|null, attributes: array<string, mixed>}
     */
    private function filtered(
        array $raw,
        string $title,
        ?string $canonicalUrl,
        string $reason,
        ?CarbonImmutable $deadline = null,
    ): array {
        $url = $canonicalUrl ?? trim((string) ($raw['url'] ?? ''));

        return [
            'status' => SourceItem::STATUS_FILTERED,
            'reason' => $reason,
            'attributes' => [
                'source_id' => null,
                'external_id' => Str::limit((string) ($raw['external_id'] ?? ''), 200, '') ?: null,
                'url_hash' => hash('sha256', $url !== '' ? $url : $title.microtime()),
                'canonical_url' => Str::limit($url !== '' ? $url : 'about:invalid', 2048, ''),
                'title' => $title !== '' ? $title : 'Untitled source item',
                'organisation_name' => null,
                'summary' => null,
                'excerpt' => null,
                'published_at' => null,
                'deadline_at' => $deadline?->format('Y-m-d H:i:s'),
                'location_text' => null,
                'location_id' => null,
                'category_slug' => null,
                'status' => SourceItem::STATUS_FILTERED,
                'status_reason' => $reason,
                'dedupe_key' => null,
                'fetched_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function canonicalUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);

            foreach (array_keys($query) as $key) {
                $normalised = strtolower((string) $key);
                if (str_starts_with($normalised, 'utm_') || in_array($normalised, ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true)) {
                    unset($query[$key]);
                }
            }
        }

        $queryString = http_build_query($query);

        return $scheme.'://'.$host.$path.($queryString !== '' ? '?'.$queryString : '');
    }

    private function hostAllowed(string $url, Source $source): bool
    {
        $base = $source->baseHost();

        if ($base === null) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = (string) preg_replace('/^www\./', '', $host);

        return $host === $base || str_ends_with($host, '.'.$base);
    }

    private function isRoundup(string $title): bool
    {
        $lower = mb_strtolower($title);

        $hasOpportunityWord = preg_match(
            '/\b(opportunit|scholarship|grant|training|fellowship|job|award|conference|internship)/u',
            $lower,
        ) === 1;

        if (! $hasOpportunityWord) {
            return false;
        }

        if (preg_match('/^\s*\d{1,3}\s+/u', $lower) === 1) {
            return true;
        }

        if (str_starts_with($lower, 'list of ')) {
            return true;
        }

        return preg_match('/\b(roundup|round-up|weekly|this week|currently open)\b/u', $lower) === 1;
    }

    /**
     * Advice posts and downloadable templates are not opportunities. They are
     * common in grant and scholarship feeds and would otherwise reach review.
     */
    private function isNotAListing(string $title): bool
    {
        $lower = mb_strtolower($title);

        return preg_match(
            '/\b(sample (grant )?proposal|proposal (template|sample)|template|cover letter|cv writing|job interview tips|how to (write|apply|leverage|prepare)|tips (for|on)|guide to)\b/u',
            $lower,
        ) === 1;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{00A0}", "\u{200B}"], ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        // Never stage contact details from a third-party page.
        $value = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/u', ' ', $value);
        $value = (string) preg_replace('/(?:\+?234|0)[\s.-]?[789]\d{2}[\s.-]?\d{3}[\s.-]?\d{4}/u', ' ', $value);
        $value = (string) preg_replace('/\+\d{1,3}[\d\s().-]{7,}\d/u', ' ', $value);
        $value = (string) preg_replace('~https?://\S+~iu', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * @param  list<string>  $categories
     */
    private function categoryFor(Source $source, array $categories, string $title): ?string
    {
        $available = $this->categorySlugs();

        foreach ($categories as $category) {
            $slug = Str::slug($category);

            if (in_array($slug, $available, true)) {
                return $slug;
            }

            $alias = self::CATEGORY_ALIASES[$slug] ?? null;
            if ($alias !== null && in_array($alias, $available, true)) {
                return $alias;
            }
        }

        // The title is the strongest signal: a job advert that mentions
        // "training" in its body is still a job. Body text is not used to guess
        // a category because it is too noisy to review honestly.
        $slug = $this->categoryFromText($title, $available);

        if ($slug !== null) {
            return $slug;
        }

        return in_array($source->default_category_slug, $available, true)
            ? $source->default_category_slug
            : null;
    }

    /**
     * @param  list<string>  $available
     */
    private function categoryFromText(string $text, array $available): ?string
    {
        $lower = mb_strtolower($text);

        foreach (self::CATEGORY_RULES as $slug => $keywords) {
            if (! in_array($slug, $available, true)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (preg_match('/\b'.preg_quote($keyword, '/').'/u', $lower) === 1) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * @return array{id: int|null, matched: string|null}
     */
    private function locationFor(string $text): array
    {
        $lower = ' '.mb_strtolower($text).' ';
        $states = $this->states();
        $bySlug = [];
        foreach ($states as $state) {
            $bySlug[$state['slug']] = $state['id'];
        }

        // "Niger Delta" names a region, not Niger State.
        if (str_contains($lower, 'niger delta') && isset($bySlug['delta'])) {
            return ['id' => $bySlug['delta'], 'matched' => 'Niger Delta'];
        }

        foreach (self::CITY_ALIASES as $slug => $aliases) {
            if (! isset($bySlug[$slug])) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $lower) === 1) {
                    return ['id' => $bySlug[$slug], 'matched' => $alias];
                }
            }
        }

        usort($states, fn (array $a, array $b): int => mb_strlen($b['name']) <=> mb_strlen($a['name']));

        foreach ($states as $state) {
            if (preg_match('/\b'.preg_quote($state['name'], '/').'\b/u', $lower) === 1) {
                return ['id' => $state['id'], 'matched' => $state['name']];
            }
        }

        return ['id' => null, 'matched' => null];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function organisationFor(Source $source, array $raw, string $title, ?string $matchedLocation): string
    {
        $organisation = $this->clean((string) ($raw['organisation'] ?? ''));

        if ($organisation === '' && preg_match('/\s+at\s+(.+)$/i', $title, $matches) === 1) {
            $organisation = trim($matches[1]);
        }

        if ($organisation === '') {
            return $source->name;
        }

        if ($matchedLocation !== null) {
            $organisation = (string) preg_replace(
                '/[,;]?\s*'.preg_quote($matchedLocation, '/').'\s*$/iu',
                '',
                $organisation,
            );
        }

        $organisation = (string) preg_replace('/[,;]?\s*Nigeria\s*$/iu', '', $organisation);
        $organisation = trim($organisation, " \t\n\r\0\x0B,;-");

        return $organisation !== '' ? Str::limit($organisation, 200, '') : $source->name;
    }

    /**
     * Pull an explicit deadline from feed prose. Only phrases that clearly
     * describe a closing date are used; date-only values close at the end of
     * the stated day in UTC.
     */
    private function deadlineFromText(string $text): ?CarbonImmutable
    {
        $text = Str::limit($text, 2000, '');
        $date = '(?:\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{4}-\d{2}-\d{2})';

        $patterns = [
            '/\b(?:application|submission|closing)\s+deadline\b\s*(?:is|:|-|–)?\s*('.$date.')/iu',
            '/\bdeadline\b\s*(?:is|:|-|–)?\s*('.$date.')/iu',
            '/\bapplications?\s+(?:close|closes|are open until|are due|due)\b\s*(?:on|by|:)?\s*('.$date.')/iu',
            '/\bclosing date\b\s*(?:is|:)?\s*('.$date.')/iu',
            '/\b(?:apply|submit|register)\s+(?:before|by)\b\s*('.$date.')/iu',
            '/\bentries\s+close\b\s*(?:on|by|:)?\s*('.$date.')/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            try {
                $deadline = CarbonImmutable::parse($matches[1], 'UTC');
            } catch (\Throwable) {
                continue;
            }

            $now = CarbonImmutable::now('UTC');

            if ($deadline->year < $now->year - 1 || $deadline->year > $now->year + 6) {
                continue;
            }

            return $deadline->endOfDay()->utc();
        }

        return null;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    private function states(): array
    {
        if ($this->states === null) {
            $this->states = Location::query()
                ->where('location_type', 'state')
                ->where('is_active', true)
                ->get(['id', 'name', 'slug'])
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => mb_strtolower($location->name),
                    'slug' => $location->slug,
                ])
                ->all();
        }

        return $this->states;
    }

    /**
     * @return list<string>
     */
    private function categorySlugs(): array
    {
        return $this->categorySlugs ??= Category::query()
            ->where('is_active', true)
            ->pluck('slug')
            ->all();
    }
}
