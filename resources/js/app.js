import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const elements = document.querySelectorAll('[data-reveal]');

    if (!elements.length) {
        return;
    }

    if (
        window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches
    ) {
        elements.forEach((element) => {
            element.classList.add('is-visible');
        });

        return;
    }

    if (!('IntersectionObserver' in window)) {
        elements.forEach((element) => {
            element.classList.add('is-visible');
        });

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -50px 0px',
        },
    );

    elements.forEach((element, index) => {
        const delay =
            element.dataset.delay
            ?? Math.min(index * 70, 420);

        element.style.setProperty(
            '--reveal-delay',
            `${delay}ms`,
        );

        observer.observe(element);
    });
});
