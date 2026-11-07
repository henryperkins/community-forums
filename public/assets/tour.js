// RetroBoards onboarding tour — progressive enhancement only (P3-11).
// The forum is fully usable without this script; if a target is missing the
// step is skipped, and completion is recorded server-side so it persists across
// devices. No inline styles: the popover rests at the bottom by CSS class, and
// when it can stand beside its target the script sets only left/top through
// the CSSOM, as the app's menus do, which the strict style-src permits.
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

    // Steps target final Gate-A DOM nodes; any missing target is skipped. A
    // comma-separated target is an ordered preference, not a selector list:
    // the first one shown wins (see resolveTarget).
    var STEPS = [
        // Device-neutral and true at every width: on a phone the lockup may be the
        // mark alone, and from 1080px down search is a glyph rather than a box.
        // The lockup links to the board index, so the step says so rather than
        // "come back here", which was untrue wherever the tour began elsewhere.
        { sel: '.forum-bar-brand', title: 'Welcome', text: 'Select the community’s name or mark, at the top of every page, to see all of its boards.' },
        { sel: '.forum-bar-search', title: 'Search', text: 'Find topics and people from search, at the top of every page.' },
        { sel: '[data-bell]', title: 'Notifications', text: 'Replies, mentions, and reactions show up under the bell.' },
        { sel: '.board-rail, .app-shell', title: 'Boards', text: 'Browse boards in the sidebar and jump into any conversation.' },
        { sel: 'form.composer, .composer-details', title: 'Compose', text: 'Write in Markdown — bold, lists, code, spoilers, and image uploads all work in the same editor.' },
        { sel: '.forum-bar-user', title: 'Your account', text: 'Tune appearance, reading, and composing under Settings whenever you like.' }
    ];

    // A target is shown when it has a box that is not parked beside the
    // viewport: the phone's board drawer exists while it is closed, off-canvas.
    function shown(el) {
        if (!el.getClientRects().length) { return false; }
        var rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < document.documentElement.clientWidth;
    }

    // querySelector('.board-rail, .app-shell') answers in document order, so it
    // always found the shell (the rail's ancestor) and the Boards step lit the
    // whole page. Each alternative is tried in the order it is written.
    function resolveTarget(sel) {
        var choices = sel.split(',');
        for (var k = 0; k < choices.length; k++) {
            var el = document.querySelector(choices[k].trim());
            if (el && shown(el)) { return el; }
        }
        return null;
    }

    function run() {
        // Re-entry guard: a tour is already on screen (e.g. the auto-start tour
        // for a first-run user) and the Replay button was clicked. Each run()
        // appends its own popover and binds a private document keydown listener
        // that only its own finish() can remove, so a second concurrent run()
        // would stack a duplicate dialog and leak a listener. One tour at a time.
        if (document.querySelector('.tour-popover')) { return; }
        var steps = STEPS.filter(function (s) { return resolveTarget(s.sel); });
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

        // Stand the card beside what it describes. A control (the lockup, the
        // search, the bell, the seat) gets the card below it, or above it when
        // there is no room below; a column (the board rail) gets it alongside,
        // level with the middle of the view. Anything larger, such as the whole
        // shell standing in for a closed phone drawer, has no beside: the card
        // keeps its bottom-centre rest, as it does wherever it would not fit.
        var anchorFrame = 0;
        function anchor() {
            anchorFrame = 0;
            pop.classList.remove('is-anchored');
            pop.style.removeProperty('left');
            pop.style.removeProperty('top');
            if (!highlighted || !shown(highlighted)) { return; }
            var vw = document.documentElement.clientWidth;
            var vh = window.innerHeight;
            var gap = 12;
            // The resting card's own gutter (width: calc(100% - 32px)).
            var margin = 16;
            var rect = highlighted.getBoundingClientRect();
            pop.classList.add('is-anchored');
            var box = pop.getBoundingClientRect();
            var left;
            var top;
            if (rect.height <= vh / 2) {
                top = rect.bottom + gap + box.height <= vh - margin ? rect.bottom + gap : rect.top - gap - box.height;
                left = Math.max(margin, Math.min(rect.left, vw - box.width - margin));
                if (top < margin) { pop.classList.remove('is-anchored'); return; }
            } else if (rect.width <= vw * 0.4) {
                left = rect.right + gap + box.width <= vw - margin ? rect.right + gap : rect.left - gap - box.width;
                top = Math.max(margin, (vh - box.height) / 2);
                if (left < margin) { pop.classList.remove('is-anchored'); return; }
            } else {
                pop.classList.remove('is-anchored');
                return;
            }
            pop.style.left = Math.round(left) + 'px';
            pop.style.top = Math.round(top) + 'px';
        }
        function scheduleAnchor() {
            if (!anchorFrame) { anchorFrame = window.requestAnimationFrame(anchor); }
        }

        function finish(replay) {
            clearHighlight();
            document.removeEventListener('keydown', onKeydown);
            window.removeEventListener('resize', scheduleAnchor);
            window.removeEventListener('scroll', scheduleAnchor, true);
            if (anchorFrame) { window.cancelAnimationFrame(anchorFrame); anchorFrame = 0; }
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
            var target = resolveTarget(step.sel);
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
            anchor();
            next.focus();
        }
        document.addEventListener('keydown', onKeydown);
        // A smooth scrollIntoView, a rotated phone or a scrolled pane moves the
        // target after placement; follow it, once per frame.
        window.addEventListener('resize', scheduleAnchor);
        window.addEventListener('scroll', scheduleAnchor, true);
        show();
    }

    // Replay controls reset + restart the tour after enhancement is ready.
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-tour-replay]') : null;
        if (!t) { return; }
        e.preventDefault();
        record('/onboarding/replay').then(function () { document.body.setAttribute('data-tour', '1'); run(); });
    });

    document.addEventListener('DOMContentLoaded', function () {
        // Both the desktop rail and native mobile chooser render this optional
        // control hidden. Reveal it only after its click handler is installed.
        document.querySelectorAll('[data-tour-replay]').forEach(function (button) {
            button.hidden = false;
        });

        // Operator surfaces are not part of the member onboarding journey: the
        // auto-start tour must never float over the admin console or the
        // moderation queues. Manual replay (data-tour-replay) still works
        // everywhere.
        var path = window.location.pathname;
        if (path.indexOf('/admin') === 0 || path.indexOf('/mod') === 0) { return; }
        if (document.body.getAttribute('data-tour') === '1') { run(); }
    });
})();
