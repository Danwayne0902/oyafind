<?php

namespace App\Services\Ingestion;

use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Reads RSS 2.0 and WordPress feeds. Feeds are syndication surfaces, so we
 * keep only a short excerpt and link back to the canonical page instead of
 * copying the full article.
 */
class RssAdapter implements SourceAdapter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(Source $source, int $limit): array
    {
        $response = Http::withHeaders([
            'User-Agent' => (string) config('oyafind.ingestion.user_agent'),
            'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
        ])
            ->timeout((int) config('oyafind.ingestion.timeout', 15))
            ->retry(2, 300)
            ->get($source->feed_url);

        $response->throw();

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException("The feed for [{$source->slug}] is not valid XML.");
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            if (count($items) >= $limit) {
                break;
            }

            $content = $item->children('http://purl.org/rss/1.0/modules/content/');
            $description = (string) $item->description;
            $encoded = (string) $content->encoded;

            $categories = [];
            foreach ($item->category as $category) {
                $value = trim((string) $category);
                if ($value !== '') {
                    $categories[] = $value;
                }
            }

            $items[] = [
                'external_id' => trim((string) ($item->guid ?: $item->link)) ?: null,
                'title' => trim((string) $item->title),
                'url' => trim((string) $item->link) ?: trim((string) $item->guid),
                'published_at' => $this->parseDate((string) $item->pubDate, $source->timezone),
                'summary_source' => $description,
                'content_source' => $encoded !== '' ? $encoded : $description,
                'categories' => $categories,
                'organisation' => null,
                'deadline_at' => null,
                'location_text' => null,
            ];
        }

        return $items;
    }

    private function parseDate(string $value, string $timezone): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
