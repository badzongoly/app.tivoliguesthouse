(() => {
    'use strict';
    const media = matchMedia('(max-width: 680px)');
    let openStrip = null;

    for (const [index, nav] of [...document.querySelectorAll('nav.workspace-tab-strip')].entries()) {
        const destinations = [...nav.children].filter(child => child.matches('a[href]'));
        if (destinations.length < 2) continue;

        const links = document.createElement('div');
        links.className = 'workspace-tab-links';
        links.id = 'workspace-tab-links-' + index;
        const controls = document.createElement('div');
        controls.className = 'workspace-tab-controls';
        controls.hidden = true;
        const current = document.createElement('span');
        current.className = 'workspace-current-tab';
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'secondary workspace-tab-more';
        more.textContent = 'More';
        more.setAttribute('aria-label', 'More ' + (nav.getAttribute('aria-label') || 'sections'));
        more.setAttribute('aria-controls', links.id);
        controls.append(current, more);
        nav.prepend(controls, links);
        links.append(...destinations);
        nav.setAttribute('data-tabs-ready', '');

        const selected = () => links.querySelector('a[aria-current="page"]');
        const copyCurrent = () => {
            current.textContent = selected()?.textContent.trim() || nav.getAttribute('aria-label') || 'Sections';
        };
        const state = {nav, close};
        function close(restoreFocus = false) {
            more.setAttribute('aria-expanded', 'false');
            links.hidden = media.matches;
            if (openStrip === state) openStrip = null;
            if (restoreFocus) more.focus();
        }
        function syncLayout() {
            const focused = document.activeElement;
            const compact = media.matches;
            controls.hidden = !compact;
            nav.toggleAttribute('data-tabs-compact', compact);
            copyCurrent();
            close(compact && links.contains(focused));
            if (!compact && controls.contains(focused)) (selected() || destinations[0]).focus();
        }

        more.addEventListener('click', () => {
            if (openStrip === state) {
                close();
                return;
            }
            openStrip?.close();
            links.hidden = false;
            more.setAttribute('aria-expanded', 'true');
            openStrip = state;
        });
        nav.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || openStrip !== state) return;
            event.preventDefault();
            close(true);
        });
        new MutationObserver(() => {
            copyCurrent();
            if (openStrip === state) close(links.contains(document.activeElement));
        }).observe(links, {subtree: true, attributes: true, attributeFilter: ['aria-current']});
        media.addEventListener('change', syncLayout);
        window.addEventListener('pageshow', event => { if (event.persisted) syncLayout(); });
        syncLayout();
    }

    document.addEventListener('click', event => {
        if (openStrip && !openStrip.nav.contains(event.target)) openStrip.close();
    });
})();
