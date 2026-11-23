(() => {
    const anchor = location.hash.slice(1);
    if (anchor !== 'exception-action' && !/^exception-[a-f0-9]{16}$/.test(anchor)) return;
    addEventListener('pageshow', () => {
        const point = document.getElementById(anchor) || document.getElementById('exception-action');
        if (!point) return;
        point.focus({preventScroll: true});
        point.scrollIntoView({block: 'start'});
    }, {once: true});
})();
