/**
 * Page motion for the printed-edition layout.
 *
 * Everything here is transform/opacity only, driven by IntersectionObserver and
 * one passive scroll listener. There is no animation library and no layout
 * thrash: the scroll handler writes two custom properties and one class.
 *
 * Motion is decoration; the page is complete without it, and
 * prefers-reduced-motion collapses every effect to an instant state.
 */

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let observer = null;
let mutationObserver = null;
let listening = false;
let frame = 0;

function revealObserver() {
    if (observer) {
        return observer;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            }
        },
        { rootMargin: '0px 0px -6% 0px', threshold: 0.05 },
    );

    return observer;
}

/**
 * Mark every not-yet-revealed element in `scope` and watch it. The stagger is
 * capped so a long list never queues a five-second cascade.
 */
export function observeReveals(scope = document) {
    const elements = scope.querySelectorAll('[data-reveal]:not(.is-revealed)');

    if (prefersReducedMotion()) {
        for (const element of elements) {
            element.classList.add('is-revealed');
        }

        return;
    }

    const watcher = revealObserver();
    let index = 0;

    for (const element of elements) {
        // Elements already above the fold should not wait for a scroll.
        const rect = element.getBoundingClientRect();
        const inView = rect.top < window.innerHeight * 0.95;

        element.style.setProperty('--reveal-i', String(inView ? Math.min(index, 7) : 0));
        index += 1;

        watcher.observe(element);
    }
}

/**
 * Count a figure up from zero once, with an ease-out curve, keeping tabular
 * figures so the width never jumps.
 */
export function countFigures(scope = document) {
    for (const element of scope.querySelectorAll('[data-count-to]:not(.is-counted)')) {
        const target = Number(element.dataset.countTo);
        element.classList.add('is-counted');

        if (!Number.isFinite(target) || prefersReducedMotion()) {
            element.textContent = String(Number.isFinite(target) ? target : 0);
            continue;
        }

        const duration = 900;
        const start = performance.now();

        const step = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);

            element.textContent = String(Math.round(target * eased));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        element.textContent = '0';
        requestAnimationFrame(step);
    }
}

function onScroll() {
    if (frame) {
        return;
    }

    frame = requestAnimationFrame(() => {
        frame = 0;

        const y = window.scrollY;
        const header = document.querySelector('.site-header');

        if (header) {
            header.classList.toggle('is-scrolled', y > 16);
        }

        // The dateline drifts a fraction of the scroll distance, so the sheet
        // feels layered without the text moving far enough to distract.
        document.documentElement.style.setProperty('--sheet-shift', String(Math.min(y, 640)));

        // A single lime hairline under the header shows reading progress.
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollable > 0 ? Math.min(1, y / scrollable) : 0;
        document.documentElement.style.setProperty('--scroll-progress', progress.toFixed(4));
    });
}

/**
 * One-time setup. Safe to call twice: listeners and observers are shared.
 */
export function initMotion() {
    if (!listening) {
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        listening = true;
    }

    refreshMotion();

    if (!mutationObserver) {
        let pending = 0;
        mutationObserver = new MutationObserver(() => {
            window.clearTimeout(pending);
            pending = window.setTimeout(() => refreshMotion(), 80);
        });

        const target = document.querySelector('#main-content');
        if (target) {
            mutationObserver.observe(target, { childList: true, subtree: true });
        }
    }
}

/**
 * Re-scan after Vue swaps a page or filters the feed.
 */
export function refreshMotion(scope = document) {
    observeReveals(scope);
    countFigures(scope);
}

/**
 * Lift the opening curtain. Runs once per browser session so a reload does not
 * replay the intro on someone checking a deadline four times.
 */
export function liftCurtain() {
    const curtain = document.querySelector('.edition-curtain');

    if (!curtain) {
        return;
    }

    const seen = (() => {
        try {
            return window.sessionStorage.getItem('oyafind-edition-seen') === '1';
        } catch {
            return false;
        }
    })();

    if (prefersReducedMotion() || seen) {
        curtain.remove();
        return;
    }

    try {
        window.sessionStorage.setItem('oyafind-edition-seen', '1');
    } catch {
        // Private modes can refuse storage; the curtain still lifts.
    }

    // The hero sequence is timed from this class so the headline rises as the
    // curtain clears, instead of finishing behind it.
    document.documentElement.classList.add('is-intro');
    window.setTimeout(() => document.documentElement.classList.remove('is-intro'), 2600);

    window.setTimeout(() => {
        curtain.classList.add('is-lifting');
        window.setTimeout(() => curtain.remove(), 950);
    }, 130);
}
