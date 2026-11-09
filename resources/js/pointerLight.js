/**
 * A pointer-following light for the masthead.
 *
 * The handler never reads layout on move: the bounding box is measured once
 * when the pointer enters and again on resize, then a single animation frame
 * writes two custom properties. The light itself is a transform on a fixed
 * layer, so the browser composites it and the gradient never repaints.
 *
 * The light lives behind the copy: it brightens the paper, never the letters,
 * which is what keeps the headline crisp. It is skipped entirely for
 * touch-only devices and reduced motion.
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
