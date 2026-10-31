@php
    $pageMeta = array_merge([
        'title' => 'OyaFind — Real opportunities. Closer to you.',
        'description' => 'Explore public job, learning and business opportunities on OyaFind. Browse without an account.',
        'canonical' => url('/'),
        'robots' => 'index,follow',
        'type' => 'website',
        'image' => asset('icons/oyafind-light-512.png'),
        'jsonLd' => [],
        'fallback' => [
            'h1' => 'OyaFind',
            'intro' => 'Real opportunities. Closer to you.',
            'links' => [],
            'items' => [],
        ],
    ], $meta ?? []);
    $fallback = $pageMeta['fallback'];
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp
<!doctype html>
<html lang="en-NG">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F7F9FC" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#101C3D" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('favicon-dark.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ asset('icons/favicon-light.png') }}" type="image/png" sizes="32x32" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('icons/favicon-dark.png') }}" type="image/png" sizes="32x32" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="{{ asset('icons/oyafind-apple-touch.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ $pageMeta['title'] }}</title>
    <meta name="description" content="{{ $pageMeta['description'] }}">
    <link rel="canonical" href="{{ $pageMeta['canonical'] }}">
    <meta name="robots" content="{{ $pageMeta['robots'] }}">
    <meta property="og:site_name" content="OyaFind">
    <meta property="og:type" content="{{ $pageMeta['type'] }}">
    <meta property="og:title" content="{{ $pageMeta['title'] }}">
    <meta property="og:description" content="{{ $pageMeta['description'] }}">
    <meta property="og:url" content="{{ $pageMeta['canonical'] }}">
    <meta property="og:image" content="{{ $pageMeta['image'] }}">
    <meta property="og:locale" content="en_NG">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $pageMeta['title'] }}">
    <meta name="twitter:description" content="{{ $pageMeta['description'] }}">
    <meta name="twitter:image" content="{{ $pageMeta['image'] }}">
    @foreach ($pageMeta['jsonLd'] as $schema)
        <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($schema, $jsonFlags) !!}</script>
    @endforeach
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app">
        {{-- Rendered before the Vue bundle starts and replaced by it on mount.
             Search engines, social previews and no-JavaScript visitors get the
             real page title and listings instead of an empty shell. --}}
        <div class="seo-fallback">
            <header class="seo-fallback-head">
                <a class="seo-fallback-brand" href="{{ url('/') }}">
                    <img src="{{ asset('brand/oyafind-mark.svg') }}" alt="" width="28" height="28">
                    <span>OyaFind</span>
                </a>
                <nav aria-label="OyaFind sections">
                    <a href="{{ url('/categories') }}">Categories</a>
                    <a href="{{ url('/how-it-works') }}">How it works</a>
                    <a href="{{ url('/search') }}">Search</a>
                </nav>
            </header>
            <main class="seo-fallback-main">
                <h1>{{ $fallback['h1'] }}</h1>
                @if (! empty($fallback['intro']))
                    <p>{{ $fallback['intro'] }}</p>
                @endif
                @if (! empty($fallback['meta']))
                    <p class="seo-fallback-meta">{{ implode(' · ', $fallback['meta']) }}</p>
                @endif
                @if (! empty($fallback['links']))
                    <nav class="seo-fallback-links" aria-label="Related pages">
                        @foreach ($fallback['links'] as $link)
                            <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
                @if (! empty($fallback['items']))
                    <ul class="seo-fallback-list">
                        @foreach ($fallback['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                @if (! empty($item['meta']))
                                    <span>{{ $item['meta'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($fallback['sourceUrl']))
                    <p class="seo-fallback-meta">
                        <a href="{{ $fallback['sourceUrl'] }}" rel="nofollow noopener" target="_blank">Open the original source</a>
                    </p>
                @endif
            </main>
        </div>
    </div>
</body>
</html>
