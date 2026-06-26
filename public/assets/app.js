// RetroBoards — progressive enhancement only. Every flow works without this
// file; it just adds small conveniences on top of the server-rendered HTML.
(function () {
    'use strict';

    // Auto-grow composer textareas as you type.
    function autosize(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t && t.classList && t.classList.contains('composer-input')) {
            autosize(t);
        }
    });

    // Reactions: toggle an EXISTING reaction chip over fetch and update it in
    // place. The "add a reaction" menu uses a normal POST (full reload) so a
    // brand-new chip is server-rendered with a valid CSRF token. Either way the
    // no-JavaScript path is unchanged.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList || !form.classList.contains('reaction-form')) { return; }
        if (form.closest('.reaction-add')) { return; }          // adding a new emoji → normal submit
        if (!window.fetch || !window.FormData) { return; }

        var btn = form.querySelector('button');
        e.preventDefault();
        var body = new FormData(form);
        body.append('format', 'json');
        fetch(form.action, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.ok) { form.submit(); return; }
            var emoji = (form.querySelector('input[name=emoji]') || {}).value;
            var n = (data.counts && data.counts[emoji]) || 0;
            if (n === 0) { form.remove(); return; }
            var on = data.state === 'added';
            btn.classList.toggle('reaction-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            var ncell = btn.querySelector('.reaction-n');
            if (ncell) { ncell.textContent = n; }
        }).catch(function () { form.submit(); });
    });

    // Notification bell: short-poll the unread count (DECISIONS §2: short-polling,
    // no WebSockets). The bell is a plain link without JS, so this only decorates.
    var bell = document.querySelector('[data-bell]');
    if (bell && window.fetch) {
        var countEl = bell.querySelector('[data-bell-count]');
        var poll = function () {
            fetch('/notifications/bell?format=json', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                if (!data || !countEl) { return; }
                if (data.unread > 0) {
                    countEl.textContent = data.unread > 99 ? '99+' : data.unread;
                    countEl.hidden = false;
                } else {
                    countEl.hidden = true;
                }
            }).catch(function () {});
        };
        poll();
        setInterval(poll, 60000); // once a minute is plenty for a forum bell
    }

    // Presence roster: short-poll who's online (P2-11). The server already
    // excludes hidden users, the viewer, and blocked members — the client just
    // renders. The widget stays hidden (no-JS) until there's someone to show.
    var presence = document.querySelector('[data-presence]');
    if (presence && window.fetch) {
        var pList = presence.querySelector('[data-presence-list]');
        var pCount = presence.querySelector('[data-presence-count]');
        var pollPresence = function () {
            fetch('/presence?format=json', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                if (!data || !pList) { return; }
                if (pCount) { pCount.textContent = data.count; }
                pList.innerHTML = '';
                (data.online || []).slice(0, 20).forEach(function (u) {
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.href = '/u/' + encodeURIComponent(u.username);
                    var dot = document.createElement('span');
                    dot.className = 'dot';
                    a.appendChild(dot);
                    a.appendChild(document.createTextNode(u.display_name || u.username));
                    li.appendChild(a);
                    pList.appendChild(li);
                });
                presence.hidden = (data.count || 0) === 0;
            }).catch(function () {});
        };
        pollPresence();
        setInterval(pollPresence, 45000);
    }
})();
