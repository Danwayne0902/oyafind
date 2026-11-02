<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>OyaFind — latest opportunities</title>
        <link>{{ $siteUrl }}/</link>
        <description>Jobs, internships, scholarships, grants, training, gigs and tenders for people in Nigeria and beyond. Every listing records when its source was checked.</description>
        <language>en-ng</language>
        @if ($updatedAt !== null)
        <lastBuildDate>{{ $updatedAt->toRfc2822String() }}</lastBuildDate>
        @endif
        <atom:link href="{{ $siteUrl }}/feed.xml" rel="self" type="application/rss+xml" />
        @foreach ($listings as $listing)
        <item>
            <title>{{ $listing->title }}</title>
            <link>{{ $siteUrl }}/opportunities/{{ $listing->slug }}</link>
            <guid isPermaLink="true">{{ $siteUrl }}/opportunities/{{ $listing->slug }}</guid>
            @if ($listing->published_at !== null)
            <pubDate>{{ $listing->published_at->toRfc2822String() }}</pubDate>
            @endif
            @if ($listing->category !== null)
            <category>{{ $listing->category->name }}</category>
            @endif
            <description>{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing->summary), 300) }}</description>
        </item>
        @endforeach
    </channel>
</rss>
