// Mock-only enhancement: register from the query string (or the viewer's
// preference), the rail toggle, the compose dialog's close controls, and the
// new-messages pill. Nothing here is production code; the real page keeps app.js.
(function () {
    var q = new URLSearchParams(location.search);
    var wanted = q.get('theme');
    if (wanted !== 'dark' && wanted !== 'light') {
        wanted = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', wanted);

    document.addEventListener('DOMContentLoaded', function () {
        var shell = document.querySelector('.dm-shell');
        var toggle = document.querySelector('[data-rail-toggle]');
        function setRail(open) {
            if (!shell) { return; }
            shell.classList.toggle('rail-open', open);
            if (toggle) {
                toggle.classList.toggle('is-active', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }
        // Single pane: the rail is a drawer on demand, never open on arrival.
        if (shell && window.innerWidth <= 900) { setRail(false); }
        if (toggle) { toggle.addEventListener('click', function () { setRail(!shell.classList.contains('rail-open')); }); }
        var close = document.querySelector('[data-rail-close]');
        if (close) { close.addEventListener('click', function () { setRail(false); }); }
        var scrim = document.querySelector('[data-rail-scrim]');
        if (scrim) { scrim.addEventListener('click', function (e) { e.preventDefault(); setRail(false); }); }
        var opener = document.querySelector('[data-dm-rail-open]');
        if (opener) {
            opener.addEventListener('click', function (e) {
                e.preventDefault();
                setRail(true);
                var d = opener.closest('details');
                if (d) { d.open = false; }
            });
        }
        document.querySelectorAll('[data-close-compose]').forEach(function (b) {
            b.addEventListener('click', function () { var d = b.closest('details'); if (d) { d.open = false; } });
        });
        var scroller = document.querySelector('[data-dm-scroll]');
        var pill = document.querySelector('[data-dm-newpill]');
        if (scroller && pill) {
            scroller.scrollTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight - 320);
            pill.addEventListener('click', function () {
                scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' });
                pill.hidden = true;
            });
        } else if (scroller) {
            scroller.scrollTop = scroller.scrollHeight;
        }
        window.addEventListener('message', function (ev) {
            if (ev.data && ev.data.theme) { document.documentElement.setAttribute('data-theme', ev.data.theme); }
        });
    });
})();
