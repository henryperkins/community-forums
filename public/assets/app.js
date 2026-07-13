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

    // The Study thread view keeps every control usable as server-rendered HTML,
    // then promotes its quiet drawer, modal, and post toolbar once JavaScript is
    // active. Initialization only reveals hooks; all behavior is delegated so
    // topics fetched into the Community Inbox do not accumulate listeners.
    function enhanceThreadViews(scope) {
        if (!scope) { return; }
        var roots = [];
        if (scope.matches && scope.matches('[data-thread-study]')) { roots.push(scope); }
        if (scope.querySelectorAll) {
            var descendants = scope.querySelectorAll('[data-thread-study]');
            for (var d = 0; d < descendants.length; d++) { roots.push(descendants[d]); }
        }
        for (var i = 0; i < roots.length; i++) {
            var root = roots[i];
            if (root.getAttribute('data-thread-enhanced') === '1') { continue; }
            root.setAttribute('data-thread-enhanced', '1');
            var tools = root.querySelector('[data-topic-tools]');
            var openers = root.querySelectorAll('[data-topic-tools-open]');
            if (tools && openers.length) {
                tools.hidden = true;
                for (var k = 0; k < openers.length; k++) { openers[k].hidden = false; }
                var close = tools.querySelector('[data-topic-tools-close]');
                if (close) { close.hidden = false; }
            }
            var enhancedOnly = root.querySelectorAll('[data-post-disclosure-open], [data-post-disclosure-close], [data-thread-restructure-open], [data-thread-restructure-close]');
            for (var j = 0; j < enhancedOnly.length; j++) { enhancedOnly[j].hidden = false; }
            if (root.querySelector('#reply textarea[name="body"]')) {
                var quoteButtons = root.querySelectorAll('[data-quote-post]');
                for (var q = 0; q < quoteButtons.length; q++) { quoteButtons[q].hidden = false; }
            }
        }
    }

    var topicToolsFocus = new WeakMap();
    var restructureFocus = new WeakMap();
    var disclosureFocus = new WeakMap();
    var disclosureOpeners = new WeakMap();

    function visible(element) {
        return !!element && element.getClientRects().length > 0;
    }

    function accordTopicTools(tools, section) {
        if (!section) { return; }
        var sections = tools.querySelectorAll('[data-topic-tools-section]');
        for (var i = 0; i < sections.length; i++) {
            sections[i].open = sections[i].getAttribute('data-topic-tools-section') === section;
        }
    }

    function setTopicTools(root, open, section, invoker) {
        if (!root) { return; }
        var tools = root.querySelector('[data-topic-tools]');
        var openers = root.querySelectorAll('[data-topic-tools-open]');
        if (!tools || !openers.length) { return; }
        if (open) {
            var alreadyOpen = document.querySelectorAll('[data-topic-tools]:not([hidden])');
            for (var i = 0; i < alreadyOpen.length; i++) {
                var otherRoot = alreadyOpen[i].closest('[data-thread-study]');
                if (otherRoot && otherRoot !== root) { setTopicTools(otherRoot, false); }
            }
            topicToolsFocus.set(root, invoker || document.activeElement);
            accordTopicTools(tools, section);
            tools.hidden = false;
            tools.setAttribute('role', 'dialog');
            tools.setAttribute('aria-modal', 'true');
            for (var oi = 0; oi < openers.length; oi++) { openers[oi].setAttribute('aria-expanded', 'true'); }
            var scrim = root.querySelector('[data-topic-tools-scrim]');
            if (scrim) { scrim.hidden = false; }
            document.body.classList.add('topic-tools-open');
            var first = tools.querySelector('[data-topic-tools-close], summary, button, input, select, textarea, a[href]');
            if (first) { first.focus(); }
        } else {
            tools.hidden = true;
            tools.removeAttribute('role');
            tools.removeAttribute('aria-modal');
            for (var ci = 0; ci < openers.length; ci++) { openers[ci].setAttribute('aria-expanded', 'false'); }
            var closeScrim = root.querySelector('[data-topic-tools-scrim]');
            if (closeScrim) { closeScrim.hidden = true; }
            if (!document.querySelector('[data-topic-tools]:not([hidden])')) { document.body.classList.remove('topic-tools-open'); }
            var restore = topicToolsFocus.get(root);
            if (restore && document.documentElement.contains(restore)) { restore.focus(); }
            topicToolsFocus.delete(root);
        }
    }

    function setThreadRestructure(root, open) {
        if (!root) { return; }
        var details = root.querySelector('[data-thread-restructure]');
        var dialog = details ? details.querySelector('.thread-restructure-dialog') : null;
        var scrim = root.querySelector('[data-thread-restructure-scrim]');
        if (!details || !dialog) { return; }
        if (open) {
            setTopicTools(root, false);
            restructureFocus.set(root, document.activeElement);
            details.open = true;
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            if (scrim) { scrim.hidden = false; }
            document.body.classList.add('thread-restructure-open');
            var first = dialog.querySelector('[data-thread-restructure-close], input, button, select, textarea');
            if (first) { first.focus(); }
        } else {
            details.open = false;
            dialog.removeAttribute('role');
            dialog.removeAttribute('aria-modal');
            if (scrim) { scrim.hidden = true; }
            if (!document.querySelector('[data-thread-restructure][open]')) { document.body.classList.remove('thread-restructure-open'); }
            var restore = restructureFocus.get(root);
            if (restore && document.documentElement.contains(restore)) { restore.focus(); }
            restructureFocus.delete(root);
        }
    }

    function closePostMenus(except) {
        var menus = document.querySelectorAll('[data-post-menu][open]');
        for (var i = 0; i < menus.length; i++) {
            if (menus[i] !== except) { menus[i].open = false; }
        }
    }

    function focusPostDisclosure(disclosure, state) {
        if (!disclosure.open) { return; }
        if (state.form && state.form._rbComposerAdapter && typeof state.form._rbComposerAdapter.focus === 'function') {
            state.form._rbComposerAdapter.focus();
        } else if (state.target && document.documentElement.contains(state.target)) {
            state.target.focus();
        }
    }

    function closePostDisclosure(disclosure) {
        if (!disclosure) { return; }
        disclosure.open = false;
        disclosureFocus.delete(disclosure);
        var restore = disclosureOpeners.get(disclosure);
        if (restore && document.documentElement.contains(restore)) { restore.focus(); }
        disclosureOpeners.delete(disclosure);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) { return; }
        var clickedMenu = target.closest('[data-post-menu]');
        closePostMenus(clickedMenu);

        var opener = target.closest('[data-topic-tools-open]');
        if (opener) {
            var openRoot = opener.closest('[data-thread-study]');
            if (openRoot) { setTopicTools(openRoot, true, opener.getAttribute('data-topic-tools-open') || '', opener); }
            return;
        }
        var closer = target.closest('[data-topic-tools-close], [data-topic-tools-scrim]');
        if (closer) {
            var closeRoot = closer.closest('[data-thread-study]');
            if (closeRoot) { setTopicTools(closeRoot, false); }
            return;
        }

        var root = target.closest('[data-thread-study]');
        if (!root) { return; }
        if (target.closest('[data-thread-restructure-open]')) {
            setThreadRestructure(root, true);
            return;
        }
        if (target.closest('[data-thread-restructure-close], [data-thread-restructure-scrim]')) {
            setThreadRestructure(root, false);
            return;
        }
        var disclosureClose = target.closest('[data-post-disclosure-close]');
        if (disclosureClose) {
            closePostDisclosure(disclosureClose.closest('.post-native-disclosure'));
            return;
        }
        var disclosureOpen = target.closest('[data-post-disclosure-open]');
        if (disclosureOpen) {
            var disclosure = document.getElementById(disclosureOpen.getAttribute('data-post-disclosure-open'));
            if (disclosure && root.contains(disclosure)) {
                var disclosureForm = disclosure.querySelector('form.composer');
                var disclosureTarget = disclosure.querySelector('textarea, input, select') || disclosure.querySelector('button');
                var disclosureState = { form: disclosureForm, target: disclosureTarget };
                var disclosureRestore = clickedMenu ? clickedMenu.querySelector(':scope > summary') : disclosureOpen;
                disclosureOpeners.set(disclosure, disclosureRestore);
                if (clickedMenu) { clickedMenu.open = false; }
                if (disclosure.open) {
                    focusPostDisclosure(disclosure, disclosureState);
                } else {
                    disclosureFocus.set(disclosure, disclosureState);
                    disclosure.open = true;
                }
            }
            return;
        }
        var quote = target.closest('[data-quote-post]');
        if (quote) {
            var post = quote.closest('[data-post]');
            var textarea = root.querySelector('#reply textarea[name="body"]');
            if (post && textarea) {
                var body = post.querySelector('.post-body');
                var source = body ? body.textContent : '';
                var line = source.trim().replace(/\s+/g, ' ').slice(0, 120);
                var replyForm = textarea.closest('form.composer');
                var adapter = replyForm && replyForm._rbComposerAdapter;
                var existing = adapter && typeof adapter.getMarkdown === 'function' ? adapter.getMarkdown() : textarea.value;
                var markdown = (existing ? '\n\n' : '') + '> ' + line + (source.trim().length > 120 ? '…' : '') + '\n\n';
                if (adapter && typeof adapter.insertMarkdown === 'function') {
                    adapter.insertMarkdown(markdown);
                    if (typeof adapter.focus === 'function') { adapter.focus(); }
                } else {
                    textarea.value += markdown;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.focus();
                }
            }
            return;
        }
        var copy = target.closest('[data-copy-post]');
        if (copy && navigator.clipboard && navigator.clipboard.writeText) {
            event.preventDefault();
            var fallback = function () { window.location.href = copy.href; };
            try {
                navigator.clipboard.writeText(copy.href).then(function () {
                    if (clickedMenu) { clickedMenu.open = false; }
                }).catch(fallback);
            } catch (error) {
                fallback();
            }
        }
    });

    document.addEventListener('toggle', function (event) {
        var opened = event.target;
        var pendingDisclosure = disclosureFocus.get(opened);
        if (pendingDisclosure) {
            disclosureFocus.delete(opened);
            focusPostDisclosure(opened, pendingDisclosure);
        }
        if (!opened.matches || !opened.matches('[data-topic-tools-section][open]')) { return; }
        var siblings = opened.parentElement.querySelectorAll('[data-topic-tools-section][open]');
        for (var i = 0; i < siblings.length; i++) {
            if (siblings[i] !== opened) { siblings[i].open = false; }
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var menus = document.querySelectorAll('[data-post-menu][open]');
            for (var mi = menus.length - 1; mi >= 0; mi--) {
                if (!visible(menus[mi])) { continue; }
                var menuTrigger = menus[mi].querySelector('summary');
                menus[mi].open = false;
                if (menuTrigger) { menuTrigger.focus(); }
                event.preventDefault();
                return;
            }
            var disclosures = document.querySelectorAll('[data-thread-study] .post-native-disclosure[open]');
            for (var di = disclosures.length - 1; di >= 0; di--) {
                if (!visible(disclosures[di])) { continue; }
                closePostDisclosure(disclosures[di]);
                event.preventDefault();
                return;
            }
            var restructures = document.querySelectorAll('[data-thread-restructure][open]');
            for (var ri = restructures.length - 1; ri >= 0; ri--) {
                var restructureDialog = restructures[ri].querySelector('.thread-restructure-dialog');
                if (!visible(restructureDialog)) { continue; }
                setThreadRestructure(restructures[ri].closest('[data-thread-study]'), false);
                event.preventDefault();
                return;
            }
            var openTools = document.querySelectorAll('[data-topic-tools]:not([hidden])');
            for (var ti = openTools.length - 1; ti >= 0; ti--) {
                if (!visible(openTools[ti])) { continue; }
                setTopicTools(openTools[ti].closest('[data-thread-study]'), false);
                event.preventDefault();
                return;
            }
            return;
        }
        if (event.key !== 'Tab') { return; }
        var dialog = null;
        var openRestructures = document.querySelectorAll('[data-thread-restructure][open] .thread-restructure-dialog');
        for (var rdi = openRestructures.length - 1; rdi >= 0; rdi--) {
            if (visible(openRestructures[rdi])) { dialog = openRestructures[rdi]; break; }
        }
        if (!dialog) {
            var toolDialogs = document.querySelectorAll('[data-topic-tools]:not([hidden])');
            for (var tdi = toolDialogs.length - 1; tdi >= 0; tdi--) {
                if (visible(toolDialogs[tdi])) { dialog = toolDialogs[tdi]; break; }
            }
        }
        if (!dialog) { return; }
        var candidates = dialog.querySelectorAll('a[href], button:not([disabled]), summary, input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
        var focusable = Array.prototype.filter.call(candidates, function (item) {
            var closedDetails = item.closest('details:not([open])');
            var closedSummary = closedDetails ? closedDetails.querySelector(':scope > summary') : null;
            return visible(item) && !item.closest('[hidden]')
                && (!closedDetails || item === closedSummary)
                && item.getAttribute('tabindex') !== '-1'
                && !item.matches(':disabled') && item.getAttribute('aria-disabled') !== 'true';
        });
        if (!focusable.length) { event.preventDefault(); return; }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (!dialog.contains(document.activeElement)) {
            (event.shiftKey ? last : first).focus();
            event.preventDefault();
        } else if (event.shiftKey && document.activeElement === first) {
            last.focus();
            event.preventDefault();
        } else if (!event.shiftKey && document.activeElement === last) {
            first.focus();
            event.preventDefault();
        }
    });

    function syncKeyboardInset() {
        var viewport = window.visualViewport;
        var inset = viewport ? Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop) : 0;
        document.documentElement.style.setProperty('--keyboard-inset', inset + 'px');
    }
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncKeyboardInset);
        window.visualViewport.addEventListener('scroll', syncKeyboardInset);
        syncKeyboardInset();
    }

    enhanceThreadViews(document);

    // Community Inbox — load a topic into the reading pane (enhancement only; with
    // JS off, the thread-title links open each topic as its own page). Short-fetch
    // the thread HTML, lift its #main content into the reading pane, and keep the
    // URL shareable via ?t=<id> + history. Reactions/edit forms inside keep working
    // because their handlers are delegated on document.
    var inbox = document.querySelector('[data-inbox]');
    if (inbox && window.fetch && window.history && window.DOMParser) {
        var reading = inbox.querySelector('[data-inbox-reading]');
        var readingContent = inbox.querySelector('[data-inbox-reading-content]');
        var inboxList = inbox.querySelector('[data-inbox-list]');
        var inboxBack = inbox.querySelector('[data-inbox-back]');
        var emptyHtml = readingContent ? readingContent.innerHTML : '';
        var selectedLink = null;
        var idOf = function (href) { var m = href && href.match(/\/t\/(\d+)/); return m ? m[1] : null; };
        var markActive = function (href) {
            var rows = inboxList.querySelectorAll('.thread-row');
            for (var i = 0; i < rows.length; i++) {
                var a = rows[i].querySelector('a.thread-title');
                rows[i].classList.toggle('is-active', !!a && a.getAttribute('href') === href);
            }
        };
        var setReadingOpen = function (open) {
            inboxList.classList.toggle('is-hidden', open);
            reading.classList.toggle('is-open', open);
        };
        var showEmpty = function (restoreFocus) {
            readingContent.innerHTML = emptyHtml;
            reading.removeAttribute('aria-busy');
            reading.scrollTop = 0;
            markActive('');
            setReadingOpen(false);
            if (restoreFocus && selectedLink && document.documentElement.contains(selectedLink)) {
                selectedLink.focus();
            }
        };
        var canonicalFallback = function (href) { window.location.href = href; };
        var loadThread = function (href, push, focus, sourceLink) {
            reading.setAttribute('aria-busy', 'true');
            fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) {
                    if (r.redirected) { canonicalFallback(r.url); return null; }
                    if (!r.ok) { canonicalFallback(href); return null; }
                    return r.text();
                })
                .then(function (html) {
                    if (html === null) { return; }
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var main = doc.querySelector('#main');
                    if (!main || !main.querySelector('.thread-view, .post-stream, .thread-head')) {
                        canonicalFallback(href); return;
                    }
                    readingContent.innerHTML = main.innerHTML;
                    enhanceThreadViews(readingContent);
                    reading.removeAttribute('aria-busy');
                    reading.scrollTop = 0;
                    selectedLink = sourceLink || inboxList.querySelector(rowSelector(idOf(href)));
                    markActive(href);
                    setReadingOpen(true);
                    if (push) {
                        var id = idOf(href);
                        var url = new URL(window.location.href);
                        if (id) { url.searchParams.set('t', id); }
                        history.pushState({ rbInboxTopic: true, href: href }, '', url.toString());
                    }
                    if (focus) {                                  // move focus, don't announce the whole thread
                        var h = readingContent.querySelector('h1, h2');
                        if (!h) { h = readingContent.querySelector('.thread-head'); }
                        if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
                        else { reading.focus(); }
                    }
                }).catch(function () { canonicalFallback(href); });
        };
        var rowSelector = function (id) {
            return 'a.thread-title[href^="/t/' + id + '-"], a.thread-title[href="/t/' + id + '"]';
        };
        if (reading && readingContent && inboxList) {
            var initialUrl = new URL(window.location.href);
            try {
                history.replaceState(initialUrl.searchParams.has('t') ? { rbInboxDirect: true } : { rbInboxList: true }, '', initialUrl.toString());
            } catch (e) { /* ignore */ }
            inboxList.addEventListener('click', function (e) {
                var a = e.target.closest ? e.target.closest('a.thread-title') : null;
                if (!a || !inboxList.contains(a)) { return; }
                if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
                e.preventDefault();
                selectedLink = a;
                loadThread(a.getAttribute('href'), true, true, a);
            });
            if (inboxBack) {
                inboxBack.addEventListener('click', function () {
                    if (history.state && history.state.rbInboxTopic) {
                        history.back();
                        return;
                    }
                    var url = new URL(window.location.href);
                    url.searchParams.delete('t');
                    try { history.replaceState({ rbInboxList: true }, '', url.toString()); } catch (e) { /* ignore */ }
                    showEmpty(true);
                });
            }
            window.addEventListener('popstate', function () {
                var id = new URL(window.location.href).searchParams.get('t');
                if (!id) { showEmpty(true); return; }
                var a = inboxList.querySelector(rowSelector(id));
                if (a) {
                    selectedLink = a;
                    loadThread(a.getAttribute('href'), false, false, a);
                } else {
                    canonicalFallback('/t/' + encodeURIComponent(id));
                }
            });
            var initId = initialUrl.searchParams.get('t');
            if (initId) {
                var initA = inboxList.querySelector(rowSelector(initId));
                if (initA) {
                    selectedLink = initA;
                    loadThread(initA.getAttribute('href'), false, false, initA);
                } else {
                    canonicalFallback('/t/' + encodeURIComponent(initId));
                }
            }
        }
    }

    // The reply dock rests compactly on small screens, then stays expanded once
    // a member starts composing. Delegation also covers topics fetched into the
    // Community Inbox after this script has loaded.
    document.addEventListener('focusin', function (e) {
        var form = e.target.closest ? e.target.closest('.reply-composer') : null;
        if (form) { form.classList.add('is-expanded'); }
    });
    document.addEventListener('input', function (e) {
        var form = e.target.closest ? e.target.closest('.reply-composer') : null;
        if (form) { form.classList.add('is-expanded'); }
    });

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
        var trigger = newTopic.querySelector('summary');
        var closeTopic = function () {
            if (!newTopic.open) { return; }
            newTopic.open = false;
            if (trigger) { trigger.focus(); }   // restore focus to the trigger, not hidden content
        };
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

    // Messages details rail (Phase 2 reimagine): a real column at wide widths, a
    // right-edge drawer below ~1400px. Server-rendered as always-visible (wide)
    // or reachable via the "Members & details" #dm-rail anchor + a CSS :target
    // rule (narrow) — both work with no JS.
    //
    // At narrow widths, :target (i.e. window.location.hash) stays the ONE source
    // of truth: JS drives it with same-document location.replace instead of layering a
    // second, independent class — two mechanisms tracking the same "is the
    // drawer open" fact can only drift (e.g. middle-clicking the "Members &
    // details" link opens a new tab whose hash the click handler never saw, and
    // a class-based toggle could then never clear a :target that's still set).
    // location.replace updates CSS :target without adding a history entry.
    //
    // At wide widths there's no anchor/:target involved at all — a plain
    // .rail-hidden class (persisted in localStorage) is the only mechanism.
    var railToggle = document.querySelector('[data-rail-toggle]');
    var dmShell = document.querySelector('.dm-shell');
    if (railToggle && dmShell) {
        var RAIL_KEY = 'rb-dm-rail-collapsed';
        var railNarrow = function () {
            return window.matchMedia && window.matchMedia('(max-width: 1399px)').matches;
        };
        var setRailButton = function (expanded) {
            railToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            railToggle.classList.toggle('is-active', expanded);
        };
        var railIsOpen = function () {
            return railNarrow() ? window.location.hash === '#dm-rail' : !dmShell.classList.contains('rail-hidden');
        };
        var openRail = function () {
            if (railNarrow()) {
                if (window.location.hash !== '#dm-rail') { window.location.replace('#dm-rail'); }
            } else {
                dmShell.classList.remove('rail-hidden');
                try { window.localStorage.removeItem(RAIL_KEY); } catch (e) { /* ignore */ }
            }
            setRailButton(true);
        };
        var closeRail = function () {
            if (railNarrow()) {
                if (window.location.hash === '#dm-rail') {
                    window.location.replace(window.location.pathname + window.location.search);
                }
            } else {
                dmShell.classList.add('rail-hidden');
                try { window.localStorage.setItem(RAIL_KEY, '1'); } catch (e) { /* ignore */ }
            }
            setRailButton(false);
        };

        var storedRailCollapsed = null;
        try { storedRailCollapsed = window.localStorage.getItem(RAIL_KEY); } catch (e) { storedRailCollapsed = null; }
        if (!railNarrow() && storedRailCollapsed === '1') { dmShell.classList.add('rail-hidden'); }
        setRailButton(railIsOpen());   // sync aria-expanded with the actual computed state on load

        railToggle.addEventListener('click', function () {
            if (railIsOpen()) { closeRail(); } else { openRail(); }
        });
        var railScrim = document.querySelector('[data-rail-scrim]');
        if (railScrim) {
            railScrim.addEventListener('click', function (e) { e.preventDefault(); closeRail(); });
        }
        var railClose = document.querySelector('[data-rail-close]');
        if (railClose) {
            railClose.addEventListener('click', closeRail);
        }
        document.addEventListener('keydown', function (e) {
            // Escape peels overlays outermost-first: an open compose dialog or
            // ··· menu takes the keypress; the rail only closes when it is the
            // topmost thing open.
            if (document.querySelector('details.dm-compose-details[open], details.dm-menu[open], details.dm-report[open]')) { return; }
            if (e.key === 'Escape' && railNarrow() && railIsOpen()) { closeRail(); }
        });
        // The header menu's "Members & details" item shares the #dm-rail anchor
        // with the no-JS fallback; with JS, open the rail directly and close the
        // menu instead of navigating.
        var railOpeners = document.querySelectorAll('[data-rail-open]');
        for (var ri = 0; ri < railOpeners.length; ri++) {
            (function (opener) {
                opener.addEventListener('click', function (e) {
                    e.preventDefault();
                    openRail();
                    var openMenu = opener.closest('details.dm-menu');
                    if (openMenu) { openMenu.open = false; }
                });
            })(railOpeners[ri]);
        }
        window.addEventListener('resize', function () { setRailButton(railIsOpen()); });
        // A back/forward navigation (or another tab's replaceState) can change
        // the hash without any of the click handlers above running.
        window.addEventListener('hashchange', function () { setRailButton(railIsOpen()); });
    }

    // ··· menus (the header overflow + each message's hover-revealed report
    // control) are native <details> so they work with no JS; this only adds
    // outside-click and Escape dismissal, matching the composer-details modal.
    var dmMenus = document.querySelectorAll('details.dm-menu, details.dm-report');
    if (dmMenus.length) {
        document.addEventListener('click', function (e) {
            for (var mi = 0; mi < dmMenus.length; mi++) {
                if (dmMenus[mi].open && !dmMenus[mi].contains(e.target)) { dmMenus[mi].open = false; }
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            // The compose dialog sits above the menus — let its handler take this one.
            if (document.querySelector('details.dm-compose-details[open]')) { return; }
            for (var ei = 0; ei < dmMenus.length; ei++) {
                if (dmMenus[ei].open) {
                    var menuTrigger = dmMenus[ei].querySelector('summary');
                    dmMenus[ei].open = false;
                    if (menuTrigger) { menuTrigger.focus(); }
                }
            }
        });
    }

    // Messages compose dialog (Phase 3): the list pane's round "+" is a native
    // <details>; CSS under .has-js lifts the open dialog into a centred modal.
    // Mirrors the new-topic composer-details enhancement: Esc, backdrop click
    // (the open details' ::before hit-tests to the details itself), the Close/
    // Cancel buttons, and focusing the To field on open. Without JS the same
    // markup opens as a panel under the list header and the form posts normally.
    var dmCompose = document.querySelector('details.dm-compose-details');
    if (dmCompose) {
        // Only the enhanced presentation is a dialog; the no-JS disclosure
        // panel keeps plain details semantics, so the role is stamped here.
        var dmDialogEl = dmCompose.querySelector('.dm-dialog');
        if (dmDialogEl) { dmDialogEl.setAttribute('role', 'dialog'); }
        var dmComposeSummary = dmCompose.querySelector('summary');
        var closeCompose = function () {
            if (!dmCompose.open) { return; }
            dmCompose.open = false;
            if (dmComposeSummary) { dmComposeSummary.focus(); }
        };
        dmCompose.addEventListener('toggle', function () {
            if (dmCompose.open) {
                var toField = dmCompose.querySelector('input[name="to"]');
                if (toField) { toField.focus(); }
            }
        });
        dmCompose.addEventListener('click', function (e) {
            if (e.target === dmCompose) { closeCompose(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && dmCompose.open) { closeCompose(); }
        });
        var dmComposeClosers = dmCompose.querySelectorAll('[data-close-compose]');
        for (var cci = 0; cci < dmComposeClosers.length; cci++) {
            dmComposeClosers[cci].addEventListener('click', closeCompose);
        }
    }

    // Messages list instant filter (Phase 3): narrows the already-rendered rows
    // as you type. The input stays a real GET form field (name="q") — Enter or
    // no-JS submits to the server, which applies the same filter authoritatively.
    // When the server already applied a ?q= filter the rendered rows are a
    // subset, so narrowing them further client-side would fake empty results —
    // in that state Enter/submit stays the one (authoritative) path.
    var dmSearchInput = document.querySelector('.dm-search input[name="q"]');
    var dmListEl = document.querySelector('.dm-list');
    if (dmSearchInput && dmListEl && dmSearchInput.value.trim() === '') {
        var dmSearchEmpty = document.querySelector('[data-search-empty]');
        var dmListRows = dmListEl.querySelectorAll('li');
        // Match what the server's LIKE matches — the name and the preview —
        // not incidental row text like timestamps or the unread label.
        var dmRowText = function (li) {
            var name = li.querySelector('.dm-other');
            var preview = li.querySelector('.dm-preview');
            return ((name ? name.textContent : '') + ' ' + (preview ? preview.textContent : '')).toLowerCase();
        };
        var dmRowTexts = [];
        for (var rt = 0; rt < dmListRows.length; rt++) { dmRowTexts.push(dmRowText(dmListRows[rt])); }
        dmSearchInput.addEventListener('input', function () {
            var needle = dmSearchInput.value.trim().toLowerCase();
            var visible = 0;
            for (var ri2 = 0; ri2 < dmListRows.length; ri2++) {
                var hit = needle === '' || dmRowTexts[ri2].indexOf(needle) !== -1;
                dmListRows[ri2].classList.toggle('is-filtered', !hit);
                if (hit) { visible++; }
            }
            if (dmSearchEmpty) { dmSearchEmpty.hidden = visible !== 0; }
        });
    }

    // Enter-to-send is deliberately NOT wired here: composer.js already owns it
    // as the user's enter_to_send preference, ordered after its suggestion-menu
    // handlers so picking an @mention with Enter never fires a submit. A second
    // handler in this file would register earlier and defeat both.

    // A flash on the reading room renders as a floating toast (CSS :has) — let
    // it take its bow after a moment instead of lingering over the composer.
    // Only when it really floats (:has support) and never for an error plate,
    // so an in-flow flash in an older browser is never yanked mid-read.
    var dmFlash = document.querySelector('.main > .flash');
    if (dmFlash && dmFlash.nextElementSibling && dmFlash.nextElementSibling.classList.contains('dm-shell')
        && !dmFlash.classList.contains('flash-error')
        && window.CSS && CSS.supports && CSS.supports('selector(:has(*))')) {
        window.setTimeout(function () { dmFlash.hidden = true; }, 4000);
    }

    // Copy a letter's text from its ··· menu. The clipboard only exists with
    // JS, so the control ships hidden and is revealed here — and only when the
    // API is actually available.
    var dmCopyButtons = document.querySelectorAll('[data-copy-message]');
    if (dmCopyButtons.length && navigator.clipboard && navigator.clipboard.writeText) {
        for (var cpi = 0; cpi < dmCopyButtons.length; cpi++) {
            (function (copyBtn) {
                copyBtn.hidden = false;
                copyBtn.addEventListener('click', function () {
                    var line = copyBtn.closest('.dm-line');
                    var bodyEl = line ? line.querySelector('.dm-body') : null;
                    var text = bodyEl ? bodyEl.textContent.trim() : '';
                    navigator.clipboard.writeText(text).then(function () {
                        var pop = copyBtn.closest('details');
                        if (pop) { pop.open = false; }
                    }).catch(function () { /* menu stays open; nothing to undo */ });
                });
            })(dmCopyButtons[cpi]);
        }
    }
})();
