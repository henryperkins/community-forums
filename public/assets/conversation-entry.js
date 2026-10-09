/* Consume a Messages row's one-shot signal before the reading pane can paint. */
(function () {
    'use strict';
    var key = 'rb:dm-switch';
    try {
        var raw = sessionStorage.getItem(key);
        sessionStorage.removeItem(key);
        if (!raw) { return; }
        var signal = JSON.parse(raw);
        var navigation = performance.getEntriesByType('navigation')[0];
        var age = Date.now() - signal.at;
        if (!navigation || navigation.type !== 'navigate'
            || !signal || typeof signal.path !== 'string' || typeof signal.at !== 'number'
            || !/^\/messages\/[0-9]+$/.test(signal.path) || signal.path !== location.pathname
            || !Number.isFinite(age) || age < 0 || age > 10000
            || document.documentElement.hasAttribute('data-reduced-motion')
            || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
        document.documentElement.setAttribute('data-dm-switch', '1');
    } catch (error) {
        // Storage and invalid signals never interfere with ordinary navigation.
    }
})();
