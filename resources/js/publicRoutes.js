export function parsePublicRoute(location) {
    const pathname = location.pathname.replace(/\/+$/, '') || '/';
    const query = Object.fromEntries(new URLSearchParams(location.search));
    const opportunity = pathname.match(/^\/opportunities\/([A-Za-z0-9-]+)$/);
    if (opportunity) return { name: 'opportunity', slug: decodeURIComponent(opportunity[1]), query };

    const category = pathname.match(/^\/categories\/([A-Za-z0-9-]+)$/);
    if (category) return { name: 'category', slug: decodeURIComponent(category[1]), query };

    const verification = pathname.match(/^\/verify-email\/(\d+)\/([a-f0-9]{40})$/i);
    if (verification) return {
        name: 'verify-email',
        userId: verification[1],
        hash: verification[2],
        query,
    };

    if (pathname === '/register') return { name: 'register', query };
    if (pathname === '/login') return { name: 'login', query };
    if (pathname === '/profile') return { name: 'profile', query };
    if (pathname === '/saved') return { name: 'saved', query };
    if (pathname === '/tracker' || pathname === '/my-opportunities') return { name: 'tracker', query };
    if (pathname === '/categories') return { name: 'categories', query };
    if (pathname === '/how-it-works') return { name: 'how-it-works', query };
    if (pathname === '/') return { name: 'home', query };
    if (pathname === '/search') return { name: 'search', query };
    return { name: 'not-found', query };
}

export function withLocationFilter(path, locationSlug) {
    const url = new URL(path, 'https://oyafind.invalid');
    const slug = typeof locationSlug === 'string' ? locationSlug.trim() : '';
    if (slug) url.searchParams.set('location', slug);
    else url.searchParams.delete('location');
    url.searchParams.delete('page');
    return `${url.pathname}${url.search}${url.hash}`;
}

export function canonicalPageUrl(location) {
    const origin = location.origin || `${location.protocol}//${location.host}`;
    return `${origin}${location.pathname}`;
}

export function safeExternalUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') return null;
    try {
        const url = new URL(value);
        if ((url.protocol !== 'https:' && url.protocol !== 'http:') || url.username || url.password) return null;
        return url.href;
    } catch {
        return null;
    }
}

export async function sharePageUrl(shareApi, url, title) {
    if (typeof shareApi?.share !== 'function') throw new Error('Sharing is not supported.');
    return shareApi.share({ title, url });
}
