/**
 * A pointer-following light for the masthead headline.
 *
 * The handler never reads layout on move: the bounding box is measured once
 * when the pointer enters and again on resize, then a single animation frame
 * writes two custom properties. The CSS does the rest with a masked gradient,
 * so the effect is compositor-friendly and the page is complete without it.
 *
 * It is skipped entirely for touch-only devices and reduced motion, where a
 * light that follows nothing would just be a static smudge.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const touchOnly = () => window.matchMedia('(hover: none)').matches;

export function pointerLight(element) {
    if (!element || reduceMotion() || touchOnly()) {
        return () => {};
    }

    let box = null;
    let frame = 0;
    let x = 0;
    let y = 0;

    const measure = () => {
        box = element.getBoundingClientRect();
    };

    const paint = () => {
        frame = 0;
        element.style.setProperty('--spot-x', `${x.toFixed(1)}px`);
        element.style.setProperty('--spot-y', `${y.toFixed(1)}px`);
    };

    const onEnter = () => {
        measure();
        element.classList.add('is-lit');
    };

    const onMove = (event) => {
        if (!box) {
            measure();
        }

        x = event.clientX - box.left;
        y = event.clientY - box.top;

        if (!frame) {
            frame = requestAnimationFrame(paint);
        }
    };

    const onLeave = () => {
        element.classList.remove('is-lit');
    };

    const onResize = () => {
        box = null;
    };

    element.addEventListener('pointerenter', onEnter);
    element.addEventListener('pointermove', onMove);
    element.addEventListener('pointerleave', onLeave);
    window.addEventListener('resize', onResize, { passive: true });

    return () => {
        if (frame) {
            cancelAnimationFrame(frame);
        }

        element.removeEventListener('pointerenter', onEnter);
        element.removeEventListener('pointermove', onMove);
        element.removeEventListener('pointerleave', onLeave);
        window.removeEventListener('resize', onResize);
    };
}
