// RetroBoards onboarding tour — progressive enhancement only (P3-11).
// The forum is fully usable without this script; if a target is missing the
// step is skipped, and completion is recorded server-side so it persists across
// devices. No inline styles (the popover is positioned by CSS class).
(function () {
    'use strict';
    if (!window.fetch) { return; }

    function token() {
        var el = document.querySelector('input[name="_token"]');
        return el ? el.value : '';
    }

    function record(path) {
        var data = new FormData();
        data.append('_token', token());
        return fetch(path, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
    }

    // Steps target final Gate-A DOM nodes; any missing target is skipped.
    var STEPS = [
        { sel: '.brand', title: 'Welcome', text: 'This is your community home. Click the name any time to come back here.' },
        { sel: '.topbar-search', title: 'Search', text: 'Find topics and people from the search box up top.' },
        { sel: '[data-bell]', title: 'Notifications', text: 'Replies, mentions, and reactions show up under the bell.' },
        { sel: '.sidebar, .app-shell', title: 'Boards', text: 'Browse boards in the sidebar and jump into any conversation.' },
        { sel: 'form.composer, .composer-details', title: 'Compose', text: 'Write in Markdown — bold, lists, code, spoilers, and image uploads all work in the same editor.' },
        { sel: '.topbar-user', title: 'Your account', text: 'Tune appearance, reading, and composing under Settings whenever you like.' }
    ];

    function run() {
        // Re-entry guard: a tour is already on screen (e.g. the auto-start tour
        // for a first-run user) and the Replay button was clicked. Each run()
        // appends its own popover and binds a private document keydown listener
        // that only its own finish() can remove, so a second concurrent run()
        // would stack a duplicate dialog and leak a listener. One tour at a time.
        if (document.querySelector('.tour-popover')) { return; }
        var steps = STEPS.filter(function (s) { return document.querySelector(s.sel); });
        if (!steps.length) { return; }

        var i = 0;
        var previousFocus = document.activeElement;
        var pop = document.createElement('div');
        pop.className = 'tour-popover';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-modal', 'true');
        pop.setAttribute('aria-live', 'polite');
        pop.setAttribute('tabindex', '-1');
        document.body.appendChild(pop);
        var highlighted = null;

        function clearHighlight() {
            if (highlighted) { highlighted.classList.remove('tour-highlight'); highlighted = null; }
        }
        function focusables() {
            return Array.prototype.slice.call(pop.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
                .filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        }

        function finish(replay) {
            clearHighlight();
            document.removeEventListener('keydown', onKeydown);
            if (pop.parentNode) { pop.parentNode.removeChild(pop); }
            document.body.removeAttribute('data-tour');
            if (previousFocus && previousFocus.focus) { previousFocus.focus(); }
            if (!replay) { record('/onboarding/complete'); }
        }

        function onKeydown(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                finish(false);
                return;
            }
            if (e.key !== 'Tab') { return; }
            var nodes = focusables();
            if (!nodes.length) { return; }
            var first = nodes[0];
            var last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        function show() {
            clearHighlight();
            var step = steps[i];
            var target = document.querySelector(step.sel);
            if (target) {
                target.classList.add('tour-highlight');
                highlighted = target;
                if (target.scrollIntoView) { target.scrollIntoView({ block: 'center' }); }
            }
            var last = i === steps.length - 1;
            pop.innerHTML = '';
            var h = document.createElement('h3'); h.id = 'tour-title'; h.textContent = step.title; pop.appendChild(h);
            var p = document.createElement('p'); p.id = 'tour-desc'; p.textContent = step.text; pop.appendChild(p);
            pop.setAttribute('aria-labelledby', h.id);
            pop.setAttribute('aria-describedby', p.id);
            var actions = document.createElement('div'); actions.className = 'tour-actions';
            var prog = document.createElement('span'); prog.className = 'tour-progress';
            prog.textContent = (i + 1) + ' of ' + steps.length; actions.appendChild(prog);
            var skip = document.createElement('button'); skip.type = 'button'; skip.className = 'btn btn-secondary';
            skip.textContent = 'Skip'; skip.addEventListener('click', function () { finish(false); }); actions.appendChild(skip);
            var next = document.createElement('button'); next.type = 'button'; next.className = 'btn';
            next.textContent = last ? 'Done' : 'Next';
            next.addEventListener('click', function () { if (last) { finish(false); } else { i++; show(); } });
            actions.appendChild(next);
            pop.appendChild(actions);
            next.focus();
        }
        document.addEventListener('keydown', onKeydown);
        show();
    }

    // Replay links anywhere: <a data-tour-replay> resets + restarts.
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-tour-replay]') : null;
        if (!t) { return; }
        e.preventDefault();
        record('/onboarding/replay').then(function () { document.body.setAttribute('data-tour', '1'); run(); });
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (document.body.getAttribute('data-tour') === '1') { run(); }
    });
})();
