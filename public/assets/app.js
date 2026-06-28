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

    // Operator branding preview (P3-07). The saved /brand.css remains the source
    // of truth; this only previews unsaved form values inside the admin card.
    var brandForm = document.querySelector('[data-brand-form]');
    var brandPreview = document.querySelector('[data-brand-preview]');
    if (brandForm && brandPreview) {
        var brandName = brandForm.querySelector('[data-brand-name]');
        var brandPrimary = brandForm.querySelector('[data-brand-primary]');
        var brandAccent = brandForm.querySelector('[data-brand-accent]');
        var brandTheme = brandForm.querySelector('[data-brand-theme]');
        var previewName = brandPreview.querySelector('[data-brand-preview-name]');
        var previewTheme = brandPreview.querySelector('[data-brand-preview-theme]');
        var hex = function (v) { return /^#[0-9a-fA-F]{6}$/.test((v || '').trim()); };
        var rgb = function (v) {
            v = v.replace('#', '');
            return [parseInt(v.slice(0, 2), 16), parseInt(v.slice(2, 4), 16), parseInt(v.slice(4, 6), 16)];
        };
        var lum = function (v) {
            return rgb(v).map(function (n) {
                n = n / 255;
                return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4);
            }).reduce(function (sum, n, i) {
                return sum + n * [0.2126, 0.7152, 0.0722][i];
            }, 0);
        };
        var contrast = function (a, b) {
            var l1 = lum(a), l2 = lum(b), hi = Math.max(l1, l2), lo = Math.min(l1, l2);
            return (hi + 0.05) / (lo + 0.05);
        };
        var contrastToken = function (v) {
            return contrast(v, '#ffffff') >= contrast(v, '#0f1218') ? '#ffffff' : '#0f1218';
        };
        var updateBrandPreview = function () {
            var primary = brandPrimary && hex(brandPrimary.value) ? brandPrimary.value : '#2f6feb';
            var accent = brandAccent && hex(brandAccent.value) ? brandAccent.value : primary;
            brandPreview.style.setProperty('--preview-accent', primary);
            brandPreview.style.setProperty('--preview-accent-contrast', contrastToken(primary));
            brandPreview.style.setProperty('--preview-accent-2', accent);
            if (previewName && brandName) { previewName.textContent = brandName.value || 'Community'; }
            if (previewTheme && brandTheme) { previewTheme.textContent = brandTheme.value.charAt(0).toUpperCase() + brandTheme.value.slice(1); }
        };
        brandForm.addEventListener('input', updateBrandPreview);
        brandForm.addEventListener('change', updateBrandPreview);
        updateBrandPreview();
    }
})();
