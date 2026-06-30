// RetroBoards — progressive enhancement only. Every flow works without this
// file; it just adds small conveniences on top of the server-rendered HTML.
(function () {
    'use strict';

    // Signal that JS is active so CSS can enable JS-only affordances (e.g. the
    // off-canvas nav drawer) without ever trapping no-JS users behind them.
    document.documentElement.classList.add('has-js');

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

    // Site announcement banner (ADMIN §7.4): a dismissible operator notice. With
    // JS off the server-rendered banner simply stays visible; this only remembers
    // a per-version dismissal in localStorage and hides the bar on later loads.
    var announcement = document.querySelector('[data-announcement]');
    if (announcement && announcement.getAttribute('data-dismissible') === '1') {
        var annVersion = announcement.getAttribute('data-announcement-version') || '0';
        var annKey = 'rb-announcement-dismissed';
        var annDismissed = null;
        try { annDismissed = window.localStorage.getItem(annKey); } catch (e) { annDismissed = null; }
        if (annDismissed === annVersion) {
            announcement.hidden = true;
        } else {
            var annBtn = announcement.querySelector('[data-announcement-dismiss]');
            if (annBtn) {
                annBtn.addEventListener('click', function () {
                    announcement.hidden = true;
                    try { window.localStorage.setItem(annKey, annVersion); } catch (e) { /* ignore */ }
                });
            }
        }
    }

    // Community Inbox — load a topic into the reading pane (enhancement only; with
    // JS off, the thread-title links open each topic as its own page). Short-fetch
    // the thread HTML, lift its #main content into the reading pane, and keep the
    // URL shareable via ?t=<id> + history. Reactions/edit forms inside keep working
    // because their handlers are delegated on document.
    var inbox = document.querySelector('[data-inbox]');
    if (inbox && window.fetch && window.history && window.DOMParser) {
        var reading = inbox.querySelector('[data-inbox-reading]');
        var inboxList = inbox.querySelector('[data-inbox-list]');
        var emptyHtml = reading.innerHTML;                 // the server-rendered placeholder
        try { history.replaceState({}, '', window.location.href); } catch (e) { /* ignore */ }
        var idOf = function (href) { var m = href && href.match(/\/t\/(\d+)/); return m ? m[1] : null; };
        var markActive = function (href) {
            var rows = inboxList.querySelectorAll('.thread-row');
            for (var i = 0; i < rows.length; i++) {
                var a = rows[i].querySelector('a.thread-title');
                rows[i].classList.toggle('is-active', !!a && a.getAttribute('href') === href);
            }
        };
        var showEmpty = function () { reading.innerHTML = emptyHtml; reading.scrollTop = 0; markActive(''); };
        var loadThread = function (href, push, focus) {
            reading.setAttribute('aria-busy', 'true');
            fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) {
                    if (r.redirected) { window.location.href = r.url; return null; }  // e.g. session expired → /login
                    return r.ok ? r.text() : null;
                })
                .then(function (html) {
                    if (html === null) { return; }
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var main = doc.querySelector('#main');
                    if (!main || !main.querySelector('.thread-view, .post-stream, .thread-head')) {
                        window.location.href = href; return;     // not a topic page → real navigation
                    }
                    reading.innerHTML = main.innerHTML;
                    reading.removeAttribute('aria-busy');
                    reading.scrollTop = 0;
                    markActive(href);
                    if (push) {
                        var id = idOf(href);
                        var url = new URL(window.location.href);
                        if (id) { url.searchParams.set('t', id); }
                        history.pushState({ href: href }, '', url.toString());
                    }
                    if (focus) {                                  // move focus, don't announce the whole thread
                        var h = reading.querySelector('h1, h2, .thread-head');
                        if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
                        else { reading.focus(); }
                    }
                }).catch(function () { window.location.href = href; });
        };
        var rowSelector = function (id) {
            return 'a.thread-title[href^="/t/' + id + '-"], a.thread-title[href="/t/' + id + '"]';
        };
        inboxList.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a.thread-title') : null;
            if (!a || !inboxList.contains(a)) { return; }
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
            e.preventDefault();
            loadThread(a.getAttribute('href'), true, true);
        });
        window.addEventListener('popstate', function () {
            var id = new URL(window.location.href).searchParams.get('t');
            if (!id) { showEmpty(); return; }              // Back to the bare /inbox restores the placeholder
            var a = inboxList.querySelector(rowSelector(id));
            if (a) { loadThread(a.getAttribute('href'), false, false); } else { showEmpty(); }
        });
        var initId = new URL(window.location.href).searchParams.get('t');
        if (initId) {
            var initA = inboxList.querySelector(rowSelector(initId));
            if (initA) { loadThread(initA.getAttribute('href'), false, false); }
        }
    }

    // Mobile navigation drawer (Phase 4): the sidebar rail slides in over a scrim
    // on small screens. Without JS the rail simply stacks above the content (the
    // server-rendered nav stays reachable); this only adds the off-canvas toggle.
    var navToggle = document.querySelector('[data-nav-toggle]');
    var navScrim = document.querySelector('[data-nav-scrim]');
    if (navToggle) {
        var setNav = function (open) {
            document.body.classList.toggle('nav-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            if (navScrim) { navScrim.hidden = !open; }
        };
        navToggle.addEventListener('click', function () {
            setNav(!document.body.classList.contains('nav-open'));
        });
        if (navScrim) { navScrim.addEventListener('click', function () { setNav(false); }); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setNav(false); }
        });
        // Closing the drawer after following a rail link keeps the next page clean.
        var sidebar = document.querySelector('[data-sidebar]');
        if (sidebar) {
            sidebar.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest('a')) { setNav(false); }
            });
        }
    }

    // New-Topic composer becomes a centred modal once JS is present (handoff §5.2).
    // The overlay itself is CSS, gated on .has-js; here we add Esc, scrim-click, and
    // a Cancel button to dismiss it, and focus the title on open. Without JS the
    // native <details> stays an inline expand, so creating a topic never needs script.
    var newTopic = document.querySelector('details.composer-details');
    if (newTopic) {
        var closeTopic = function () { if (newTopic.open) { newTopic.open = false; } };
        newTopic.addEventListener('toggle', function () {
            if (newTopic.open) {
                var title = newTopic.querySelector('input[name="title"]');
                if (title) { title.focus(); }
            }
        });
        // A click on the backdrop (the open details' ::before fills the viewport and
        // hit-tests to the details element itself) dismisses the modal.
        newTopic.addEventListener('click', function (e) {
            if (e.target === newTopic) { closeTopic(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && newTopic.open) { closeTopic(); }
        });
        var cancel = newTopic.querySelector('[data-close-composer]');
        if (cancel) { cancel.addEventListener('click', closeTopic); }
    }
})();
