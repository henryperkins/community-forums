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
            var primary = brandPrimary && hex(brandPrimary.value) ? brandPrimary.value : '';
            var accent = brandAccent && hex(brandAccent.value) ? brandAccent.value : '';
            if (primary !== '') {
                brandPreview.style.setProperty('--preview-accent', primary);
                brandPreview.style.setProperty('--preview-accent-contrast', contrastToken(primary));
            } else {
                brandPreview.style.removeProperty('--preview-accent');
                brandPreview.style.removeProperty('--preview-accent-contrast');
            }
            if (accent !== '') {
                brandPreview.style.setProperty('--preview-accent-2', accent);
                brandPreview.style.setProperty('--preview-accent-2-contrast', contrastToken(accent));
            } else {
                brandPreview.style.removeProperty('--preview-accent-2');
                brandPreview.style.removeProperty('--preview-accent-2-contrast');
            }
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

    // Announcement publishing remains a normal server-rendered POST. This small
    // decoration mirrors the design's live counter; the server renders its
    // initial value, so the count does not disappear when JavaScript is off.
    var announcementForm = document.querySelector('[data-announcement-form]');
    if (announcementForm) {
        var announcementMessage = announcementForm.querySelector('[data-announcement-message]');
        var announcementCount = announcementForm.querySelector('[data-announcement-count]');
        var updateAnnouncementForm = function () {
            if (announcementMessage && announcementCount) {
                // Array.from counts Unicode code points, matching PHP mb_strlen()
                // rather than JavaScript's UTF-16 code-unit String.length.
                announcementCount.textContent = Array.from(announcementMessage.value).length + ' / 500';
            }
        };
        if (announcementMessage) { announcementMessage.addEventListener('input', updateAnnouncementForm); }
        updateAnnouncementForm();
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

        var copy = target.closest('[data-copy-post], [data-copy-link]');
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
            return;
        }

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

    // FT-01. The thread's inner scroll port now only exists once the line above has
    // run, and this script is deferred. The browser resolved any #p{id} fragment
    // against the document scroller before that, so the post it scrolled to is no
    // longer in view once .thread-scroll becomes the scroller at scrollTop 0.
    // Re-resolve the fragment inside the new scroll port, once, on load only —
    // Controller::threadRedirect() sends reply, edit, accepted-answer, moderation,
    // memory and notification click-through to /t/{id}-{slug}#p{postId}.
    (function () {
        var hash = window.location.hash;
        if (!/^#p\d+$/.test(hash)) { return; }
        var target = document.getElementById(hash.slice(1));
        if (!target) { return; }
        var port = target.closest('[data-thread-enhanced="1"] > .thread-scroll');
        if (!port) { return; }
        // scrollIntoView would also scroll the window; set the port directly.
        port.scrollTop = target.offsetTop - port.offsetTop;
    })();

    // Member-surface panels. The POST forms remain canonical; JavaScript applies
    // the state immediately and saves the same fields in the background. The
    // keyboard contract is suppressed in editors so writing never toggles chrome.
    var panelForms = {};
    var editableTarget = function (target) {
        return !!(target && target.closest && target.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""]'));
    };
    var panelField = function (kind) { return kind === 'reading' ? 'inbox_reading_open' : 'rail_open'; };
    var panelIsOpen = function (kind) { return document.body.classList.contains(kind === 'reading' ? 'is-reading-open' : 'is-rail-open'); };
    var renderPanelState = function (kind, open) {
        var openClass = kind === 'reading' ? 'is-reading-open' : 'is-rail-open';
        var closedClass = kind === 'reading' ? 'is-reading-closed' : 'is-rail-closed';
        var attribute = kind === 'reading' ? 'data-inbox-reading-open' : 'data-rail-open';
        document.body.classList.toggle(openClass, open);
        document.body.classList.toggle(closedClass, !open);
        document.body.setAttribute(attribute, open ? '1' : '0');
        var form = panelForms[kind];
        if (!form) { return; }
        var field = form.querySelector('input[name="' + panelField(kind) + '"]');
        var button = form.querySelector('button[type="submit"]');
        if (field) { field.value = open ? '0' : '1'; }
        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-pressed', open ? 'true' : 'false');
            button.setAttribute('aria-label', (open ? 'Hide ' : 'Show ') + (kind === 'reading' ? 'reading pane' : 'board rail'));
        }
    };
    var persistPanelState = function (kind, open) {
        var form = panelForms[kind];
        if (!form || !window.fetch || !window.FormData) { return; }
        var fieldName = panelField(kind);
        var data = new FormData(form);
        data.set(fieldName, open ? '1' : '0');
        fetch(form.action, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (response.ok) { return; }
            var field = form.querySelector('input[name="' + fieldName + '"]');
            if (field) { field.value = open ? '1' : '0'; }
            form.submit();
        }).catch(function () {
            var field = form.querySelector('input[name="' + fieldName + '"]');
            if (field) { field.value = open ? '1' : '0'; }
            form.submit();
        });
    };
    Array.prototype.forEach.call(document.querySelectorAll('[data-panel-form]'), function (form) {
        var kind = form.getAttribute('data-panel-form');
        if (kind !== 'rail' && kind !== 'reading') { return; }
        panelForms[kind] = form;
        form.addEventListener('submit', function (event) {
            if (!window.fetch || !window.FormData) { return; }
            event.preventDefault();
            var desired = !panelIsOpen(kind);
            renderPanelState(kind, desired);
            persistPanelState(kind, desired);
        });
    });
    document.addEventListener('keydown', function (event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || editableTarget(event.target)) { return; }
        var key = String(event.key || '').toLowerCase();
        if (key === 'b' && panelForms.rail) {
            event.preventDefault();
            var railOpen = !panelIsOpen('rail');
            renderPanelState('rail', railOpen);
            persistPanelState('rail', railOpen);
        } else if (key === 'j' && panelForms.reading) {
            event.preventDefault();
            var readingOpen = !panelIsOpen('reading');
            renderPanelState('reading', readingOpen);
            persistPanelState('reading', readingOpen);
        } else if (key === 'k') {
            event.preventDefault();
            var search = document.querySelector('.search-query-well');
            if (search) { search.focus(); search.select(); }
            else { window.location.href = '/search'; }
        }
    });

    // The directory controls remain native POST forms. With JavaScript they save
    // through the same endpoint, follow its canonical redirect, then replace only
    // the board pane so changing the view does not add a document navigation to
    // the member's history.
    var directoryPane = document.querySelector('[data-directory-pane]');
    if (directoryPane && window.fetch && window.FormData && window.history) {
        var directoryRequest = null;
        document.addEventListener('submit', function (event) {
            var form = event.target && event.target.closest
                ? event.target.closest('[data-directory-pane] form[action="/settings/member-surfaces"]')
                : null;
            if (!form || (!form.querySelector('input[name="directory_sort"]') && !form.querySelector('input[name="directory_peek"]'))) { return; }
            event.preventDefault();
            if (directoryRequest && typeof directoryRequest.abort === 'function') { directoryRequest.abort(); }
            var request = window.AbortController ? new window.AbortController() : null;
            directoryRequest = request;
            directoryPane.setAttribute('aria-busy', 'true');
            var options = {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            };
            if (request) { options.signal = request.signal; }
            fetch(form.action, options).then(function (response) {
                if (!response.ok) { throw new Error('Directory preference save failed.'); }
                return response.text().then(function (html) { return { html: html, url: response.url }; });
            }).then(function (result) {
                if (directoryRequest !== request) { return; }
                var probe = document.createElement('template');
                probe.innerHTML = result.html;
                var nextPane = probe.content.querySelector('[data-directory-pane]');
                if (!nextPane) { throw new Error('Directory response did not contain the board pane.'); }
                directoryPane.replaceWith(nextPane);
                directoryPane = nextPane;
                var nextUrl = new URL(result.url, window.location.href);
                history.replaceState(history.state, '', nextUrl.pathname + nextUrl.search + nextUrl.hash);
                directoryRequest = null;
            }).catch(function (error) {
                if (error && error.name === 'AbortError') { return; }
                directoryRequest = null;
                form.submit();
            });
        });
    }

    // Community Inbox — the bounded preview endpoint decorates canonical topic
    // links. Selection, cursor movement and actions all resolve back to native
    // forms/links, so the queue remains fully usable without this block.
    var inbox = document.querySelector('[data-inbox]');
    if (inbox && window.fetch && window.history) {
        var reading = inbox.querySelector('[data-inbox-reading]');
        var readingContent = inbox.querySelector('[data-inbox-reading-content]');
        var inboxList = inbox.querySelector('[data-inbox-list]');
        var threadList = inbox.querySelector('[data-inbox-thread-list]');
        var inboxBack = inbox.querySelector('[data-inbox-back]');
        var emptyHtml = readingContent ? readingContent.innerHTML : '';
        var selectedLink = null;
        var cursorRow = null;
        var inboxRequest = null;
        var inboxRequestGeneration = 0;
        var idOf = function (href) { var match = href && href.match(/\/t\/(\d+)/); return match ? match[1] : null; };
        var cancelInboxRequest = function () {
            inboxRequestGeneration++;
            if (inboxRequest && typeof inboxRequest.abort === 'function') { inboxRequest.abort(); }
            inboxRequest = null;
            return inboxRequestGeneration;
        };
        var clearInboxRequest = function (generation, request) {
            if (generation === inboxRequestGeneration && inboxRequest === request) { inboxRequest = null; }
        };
        var allRows = function () { return inboxList.querySelectorAll('[data-inbox-row]'); };
        var linkIn = function (row) { return row ? row.querySelector('.inbox-row-title') : null; };
        var rowForId = function (id) { return /^\d+$/.test(String(id || '')) ? inboxList.querySelector('[data-thread-id="' + id + '"]') : null; };
        var clearInboxMenuPosition = function (menu) {
            var panel = menu.querySelector('.inbox-scope-menu-panel, .inbox-row-menu-panel');
            menu.removeAttribute('data-inbox-menu-positioned');
            if (!panel) { return; }
            panel.style.removeProperty('left');
            panel.style.removeProperty('top');
        };
        var positionInboxMenu = function (menu) {
            var trigger = menu.querySelector(':scope > summary');
            var panel = menu.querySelector('.inbox-scope-menu-panel, .inbox-row-menu-panel');
            if (!menu.open || !trigger || !panel) { clearInboxMenuPosition(menu); return; }
            var margin = 8;
            var gap = menu.matches('[data-inbox-scope-menu]') ? 7 : 4;
            var triggerRect = trigger.getBoundingClientRect();
            var panelRect = panel.getBoundingClientRect();
            var left = menu.matches('[data-inbox-row-menu]') ? triggerRect.right - panelRect.width : triggerRect.left;
            var top = triggerRect.bottom + gap;
            left = Math.max(margin, Math.min(left, window.innerWidth - panelRect.width - margin));
            if (top + panelRect.height > window.innerHeight - margin) {
                top = triggerRect.top - panelRect.height - gap;
            }
            top = Math.max(margin, Math.min(top, window.innerHeight - panelRect.height - margin));
            panel.style.left = Math.round(left) + 'px';
            panel.style.top = Math.round(top) + 'px';
            menu.setAttribute('data-inbox-menu-positioned', '1');
        };
        var closeInboxMenus = function (restoreFocus) {
            Array.prototype.forEach.call(inbox.querySelectorAll('[data-inbox-scope-menu][open], [data-inbox-row-menu][open]'), function (menu) {
                menu.removeAttribute('open');
                clearInboxMenuPosition(menu);
                if (restoreFocus) {
                    var summary = menu.querySelector(':scope > summary');
                    if (summary) { summary.focus(); }
                }
            });
        };

        inbox.addEventListener('toggle', function (event) {
            var menu = event.target;
            if (!menu.matches || !menu.matches('[data-inbox-scope-menu], [data-inbox-row-menu]')) { return; }
            if (!menu.open) { clearInboxMenuPosition(menu); return; }
            Array.prototype.forEach.call(inbox.querySelectorAll('[data-inbox-scope-menu][open], [data-inbox-row-menu][open]'), function (other) {
                if (other !== menu) {
                    other.removeAttribute('open');
                    clearInboxMenuPosition(other);
                }
            });
            positionInboxMenu(menu);
        }, true);
        window.addEventListener('resize', function () {
            var openMenu = inbox.querySelector('[data-inbox-scope-menu][open], [data-inbox-row-menu][open]');
            if (openMenu) { positionInboxMenu(openMenu); }
        });
        var markActive = function (link) {
            Array.prototype.forEach.call(allRows(), function (row) {
                row.classList.toggle('is-active', !!link && linkIn(row) === link);
            });
        };
        var setCursor = function (row, follow) {
            Array.prototype.forEach.call(allRows(), function (candidate) {
                candidate.classList.toggle('is-cursor', candidate === row);
            });
            cursorRow = row;
            if (!row) { return; }
            var link = linkIn(row);
            if (link) { selectedLink = link; }
            row.scrollIntoView({ block: 'nearest' });
            if (follow && link) { loadThread(link, false, false); }
        };
        var setMobileReading = function (open) {
            inboxList.classList.toggle('is-hidden', open);
            reading.classList.toggle('is-open', open);
            if (open && !panelIsOpen('reading')) { renderPanelState('reading', true); }
        };
        var showEmpty = function (restoreFocus) {
            cancelInboxRequest();
            if (window.RetroBoardsComposer && typeof window.RetroBoardsComposer.destroyWithin === 'function') {
                window.RetroBoardsComposer.destroyWithin(readingContent);
            }
            readingContent.innerHTML = emptyHtml;
            reading.removeAttribute('aria-busy');
            reading.scrollTop = 0;
            markActive(null);
            setMobileReading(false);
            if (restoreFocus && selectedLink && document.documentElement.contains(selectedLink)) { selectedLink.focus(); }
            else if (restoreFocus) { inboxList.focus(); }
        };
        var canonicalFallback = function (href) { window.location.href = href; };
        var reconcileReadRow = function (sourceLink) {
            var row = sourceLink && sourceLink.closest ? sourceLink.closest('[data-inbox-row]') : null;
            if (!row || row.getAttribute('data-inbox-unread') !== '1') { return; }
            row.setAttribute('data-inbox-unread', '0');
            row.classList.remove('is-unread');
            var dot = row.querySelector('.unread-dot');
            if (dot) { dot.remove(); }
            Array.prototype.forEach.call(document.querySelectorAll('[data-inbox-unread-count]'), function (badge) {
                var count = parseInt(badge.getAttribute('data-inbox-unread-count') || '', 10);
                if (isNaN(count) || count < 1) { return; }
                count--;
                if (count === 0) { badge.remove(); return; }
                badge.setAttribute('data-inbox-unread-count', String(count));
                badge.textContent = badge.classList.contains('topbar-count') ? String(count) : count + ' unread';
            });
            if (inbox.getAttribute('data-inbox-scope') === 'unread') {
                row.remove();
                if (cursorRow === row) { cursorRow = null; }
            }
        };
        var loadThread = function (link, push, focus) {
            if (!link) { return; }
            var canonical = link.getAttribute('href');
            var endpoint = link.getAttribute('data-inbox-preview-url');
            if (!canonical || !endpoint) { canonicalFallback(canonical || '/inbox'); return; }
            var generation = cancelInboxRequest();
            var request = window.AbortController ? new window.AbortController() : null;
            inboxRequest = request;
            reading.setAttribute('aria-busy', 'true');
            var options = { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
            if (request) { options.signal = request.signal; }
            fetch(endpoint, options).then(function (response) {
                if (generation !== inboxRequestGeneration) { return null; }
                if (response.redirected || !response.ok) {
                    clearInboxRequest(generation, request);
                    canonicalFallback(canonical);
                    return null;
                }
                return response.text();
            }).then(function (html) {
                if (generation !== inboxRequestGeneration || html === null) { return; }
                var probe = document.createElement('template');
                probe.innerHTML = html;
                if (!probe.content.querySelector('[data-inbox-preview]')) {
                    clearInboxRequest(generation, request);
                    canonicalFallback(canonical);
                    return;
                }
                if (window.RetroBoardsComposer && typeof window.RetroBoardsComposer.destroyWithin === 'function') {
                    window.RetroBoardsComposer.destroyWithin(readingContent);
                }
                readingContent.innerHTML = html;
                if (window.RetroBoardsComposer && typeof window.RetroBoardsComposer.enhanceWithin === 'function') {
                    window.RetroBoardsComposer.enhanceWithin(readingContent);
                }
                selectedLink = link;
                reconcileReadRow(link);
                markActive(link);
                setMobileReading(true);
                reading.removeAttribute('aria-busy');
                reading.scrollTop = 0;
                if (push) {
                    var id = idOf(canonical);
                    var url = new URL(window.location.href);
                    if (id) { url.searchParams.set('t', id); }
                    history.pushState({ rbInboxTopic: true, href: canonical }, '', url.toString());
                }
                if (focus) {
                    var heading = readingContent.querySelector('h2, h1');
                    if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
                    else { reading.focus(); }
                }
                clearInboxRequest(generation, request);
            }).catch(function (error) {
                if (generation !== inboxRequestGeneration || (error && error.name === 'AbortError')) { return; }
                clearInboxRequest(generation, request);
                canonicalFallback(canonical);
            });
        };

        if (reading && readingContent && inboxList) {
            var initialUrl = new URL(window.location.href);
            try { history.replaceState(initialUrl.searchParams.has('t') ? { rbInboxDirect: true } : { rbInboxList: true }, '', initialUrl.toString()); }
            catch (error) { /* History can be unavailable in privacy modes. */ }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && inbox.querySelector('[data-inbox-scope-menu][open], [data-inbox-row-menu][open]')) {
                    event.preventDefault();
                    closeInboxMenus(true);
                    return;
                }
                if (event.ctrlKey || event.metaKey || event.altKey || editableTarget(event.target)) { return; }
                var rows = Array.prototype.slice.call(allRows());
                if (!rows.length) { return; }
                var index = cursorRow ? rows.indexOf(cursorRow) : -1;
                if (event.key === 'j' || event.key === 'k') {
                    event.preventDefault();
                    index = event.key === 'j' ? Math.min(rows.length - 1, index + 1) : Math.max(0, index < 0 ? 0 : index - 1);
                    setCursor(rows[index], true);
                    return;
                }
                var activeRow = cursorRow || rows[0];
                var activeLink = linkIn(activeRow);
                if (event.key === 'Enter' || event.key.toLowerCase() === 'o') {
                    event.preventDefault();
                    setCursor(activeRow, false);
                    loadThread(activeLink, true, true);
                } else if (event.key.toLowerCase() === 'e') {
                    var readForm = activeRow.querySelector('[data-inbox-action="read"]');
                    if (readForm) { event.preventDefault(); readForm.requestSubmit(); }
                } else if (event.key.toLowerCase() === 's') {
                    var starForm = activeRow.querySelector('[data-inbox-action="star"]');
                    if (starForm) { event.preventDefault(); starForm.requestSubmit(); }
                } else if (event.key === '#') {
                    var snoozeForm = activeRow.querySelector('[data-inbox-action="snooze"][data-inbox-snooze="monday"]');
                    if (snoozeForm) { event.preventDefault(); snoozeForm.requestSubmit(); }
                }
            });

            document.addEventListener('scroll', function () { closeInboxMenus(false); }, { capture: true, passive: true });
            inboxList.addEventListener('click', function (event) {
                var link = event.target.closest ? event.target.closest('.inbox-row-title') : null;
                if (!link || !inboxList.contains(link)) { return; }
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
                event.preventDefault();
                setCursor(link.closest('[data-inbox-row]'), false);
                loadThread(link, true, true);
            });

            var selections = Array.prototype.slice.call(inbox.querySelectorAll('[data-inbox-select]'));
            var selectAll = inbox.querySelector('[data-inbox-select-all]');
            var sweep = inbox.querySelector('[data-inbox-sweep]');
            var selectionLabel = inbox.querySelector('[data-inbox-selection-label]');
            var selectionAnchor = -1;
            var syncSelection = function () {
                var count = selections.filter(function (box) { return box.checked; }).length;
                if (sweep) { sweep.classList.toggle('is-active', count > 0); }
                if (selectionLabel) { selectionLabel.textContent = count ? count + ' selected' : 'Selected topics'; }
                if (selectAll) {
                    selectAll.checked = selections.length > 0 && count === selections.length;
                    selectAll.indeterminate = count > 0 && count < selections.length;
                }
            };
            selections.forEach(function (box, index) {
                box.addEventListener('click', function (event) {
                    if (event.shiftKey && selectionAnchor >= 0) {
                        var start = Math.min(index, selectionAnchor);
                        var end = Math.max(index, selectionAnchor);
                        for (var offset = start; offset <= end; offset++) { selections[offset].checked = box.checked; }
                    }
                    selectionAnchor = index;
                    syncSelection();
                });
                box.addEventListener('change', syncSelection);
            });
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    selections.forEach(function (box) { box.checked = selectAll.checked; });
                    syncSelection();
                });
            }
            syncSelection();

            if (inboxBack) {
                inboxBack.addEventListener('click', function () {
                    if (history.state && history.state.rbInboxTopic) { cancelInboxRequest(); history.back(); return; }
                    var url = new URL(window.location.href);
                    url.searchParams.delete('t');
                    try { history.replaceState({ rbInboxList: true }, '', url.toString()); } catch (error) { /* ignore */ }
                    showEmpty(true);
                });
            }
            window.addEventListener('popstate', function () {
                var id = new URL(window.location.href).searchParams.get('t');
                if (!id) { showEmpty(true); return; }
                var row = rowForId(id);
                var link = linkIn(row);
                if (link) { setCursor(row, false); loadThread(link, false, true); }
                else { canonicalFallback('/t/' + encodeURIComponent(id)); }
            });
            var initialId = initialUrl.searchParams.get('t');
            if (initialId) {
                var initialRow = rowForId(initialId);
                var initialLink = linkIn(initialRow);
                if (initialLink) { setCursor(initialRow, false); loadThread(initialLink, false, true); }
                else { canonicalFallback('/t/' + encodeURIComponent(initialId)); }
            }
        }
    }

    // Compose destination rail/select parity. Both controls remain real GET/select
    // affordances; this keeps the visible destination, URL and form target aligned.
    var composeSurface = document.querySelector('[data-compose]');
    if (composeSurface && window.history) {
        var composeSelect = composeSurface.querySelector('[data-compose-board-select]');
        var composeForm = composeSurface.querySelector('[data-composer-context="new_thread"]');
        var composeName = composeSurface.querySelector('[data-compose-board-name]');
        var composeTitle = composeSurface.querySelector('[data-compose-title]');
        var composeBody = composeSurface.querySelector('.composer-input');
        var composeDraft = composeSurface.querySelector('[data-compose-draft-copy]');
        var composePickers = document.querySelectorAll('[data-compose-board-picker]');
        var optionBySlug = function (slug) {
            if (!composeSelect) { return null; }
            for (var i = 0; i < composeSelect.options.length; i++) {
                if (composeSelect.options[i].getAttribute('data-board-slug') === slug) { return composeSelect.options[i]; }
            }
            return null;
        };
        var syncCompose = function (option, replaceUrl) {
            if (!option || option.disabled) { return; }
            var slug = option.getAttribute('data-board-slug') || '';
            var name = option.getAttribute('data-board-name') || option.textContent || '';
            composeSelect.value = option.value;
            composeSurface.setAttribute('data-compose-selected-board', slug);
            if (composeForm) { composeForm.setAttribute('data-composer-target-id', option.value); }
            if (composeName) { composeName.textContent = 'Posting to ' + name; }
            Array.prototype.forEach.call(composePickers, function (picker) {
                var active = picker.getAttribute('data-compose-board-picker') === slug;
                picker.classList.toggle('is-active', active);
                picker.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            var anonymousAllowed = option.getAttribute('data-board-anonymous') === '1';
            Array.prototype.forEach.call(composeSurface.querySelectorAll('[data-compose-anonymous]'), function (control) {
                control.hidden = !anonymousAllowed;
                var checkbox = control.querySelector('input[type="checkbox"]');
                if (checkbox) { checkbox.disabled = !anonymousAllowed; if (!anonymousAllowed) { checkbox.checked = false; } }
            });
            if (replaceUrl && slug) { history.replaceState(history.state, '', '/compose?board=' + encodeURIComponent(slug)); }
        };
        if (composeSelect) {
            composeSelect.addEventListener('change', function () { syncCompose(composeSelect.options[composeSelect.selectedIndex], true); });
            syncCompose(composeSelect.options[composeSelect.selectedIndex], false);
        }
        Array.prototype.forEach.call(composePickers, function (picker) {
            picker.addEventListener('click', function (event) {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
                var option = optionBySlug(picker.getAttribute('data-compose-board-picker'));
                if (!option || option.disabled) { return; }
                event.preventDefault();
                syncCompose(option, true);
            });
        });
        var syncComposeDraft = function () {
            if (!composeDraft) { return; }
            composeDraft.hidden = !((composeTitle && composeTitle.value.trim()) || (composeBody && composeBody.value.trim()));
        };
        composeSurface.addEventListener('input', syncComposeDraft);
        composeSurface.addEventListener('change', syncComposeDraft);
        syncComposeDraft();
    }

    // The reply dock's expand/minimize state lives in composer.js: deciding
    // whether an expanded dock may fold back up needs the active adapter's
    // canonical Markdown (the hidden textarea lags behind the Milkdown document),
    // which only the composer enhancement owns. The Community Inbox already
    // re-enhances injected topics through RetroBoardsComposer.enhanceWithin.

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

    // The admin console has no navigation JavaScript. Its area tier scrolls
    // horizontally and its section tabs wrap, so the console nav behaves
    // identically with scripting disabled (ADMIN.md §9.4, amended by ADR 0024).
    // The former grouped-rail drawer, its scrim and its focus trap were removed
    // with the rail itself rather than left as dead chrome.

    // The recent-activity table keeps its semantic scroll region. On narrow
    // screens this removes the directional cue and edge fade at the true end.
    Array.prototype.forEach.call(document.querySelectorAll('[data-overflow-cue]'), function (shell) {
        var region = shell.querySelector('[data-overflow-region]');
        if (!region) { return; }
        var updateOverflowCue = function () {
            var max = Math.max(0, region.scrollWidth - region.clientWidth);
            shell.classList.toggle('is-unneeded', max <= 1);
            shell.classList.toggle('is-at-end', max > 1 && region.scrollLeft >= max - 2);
        };
        region.addEventListener('scroll', updateOverflowCue, { passive: true });
        window.addEventListener('resize', updateOverflowCue);
        window.requestAnimationFrame(updateOverflowCue);
    });

    // New-Topic composer becomes a centred modal once JS is present (handoff §5.2).
    // The overlay itself is CSS, gated on .has-js; here we add Esc, scrim-click, and
    // a Cancel button to dismiss it, and focus the title on open. Without JS the
    // native <details> stays an inline expand, so creating a topic never needs script.
    var newTopic = document.querySelector('details.composer-details');
    if (newTopic) {
        var trigger = newTopic.querySelector('summary');
        // A board page carries two promoted triggers now — the slab's and the
        // condensed bar's duplicate — so this is a list. The slab's is the one
        // that owns aria-expanded and the focus-restore fallback.
        var promotedTriggers = document.querySelectorAll('[data-open-topic-composer]');
        var promotedTrigger = document.querySelector('.board-identity-actions [data-open-topic-composer]')
            || promotedTriggers[0]
            || null;
        var fabTrigger = document.querySelector('a.fab[href="#new-topic"]');
        var topicReturnFocus = trigger;
        var openTopic = function (opener) {
            // The condensed bar is aria-hidden, so its button is not a legal
            // place to send focus back to — fall back to the control it echoes.
            topicReturnFocus = (opener && !opener.closest('[aria-hidden="true"]'))
                ? opener
                : (promotedTrigger || trigger);
            newTopic.open = true;
        };
        if (promotedTriggers.length && trigger) {
            trigger.classList.add('js-native-topic-trigger');
            for (var promotedIndex = 0; promotedIndex < promotedTriggers.length; promotedIndex++) {
                (function (promoted) {
                    // The condensed duplicate is revealed by the scroll observer
                    // below instead, so it stays hidden until the slab leaves.
                    if (!promoted.closest('.board-identity-condensed')) { promoted.hidden = false; }
                    promoted.addEventListener('click', function () { openTopic(promoted); });
                }(promotedTriggers[promotedIndex]));
            }
        }
        if (fabTrigger) {
            fabTrigger.addEventListener('click', function (event) {
                event.preventDefault();
                openTopic(fabTrigger);
            });
        }
        // focus() on a hidden element is a silent no-op that drops focus to the
        // body, so the restore target is chosen from what is actually on screen —
        // the slab button is display:none below 680px, where the FAB is the opener.
        var canTakeFocus = function (el) {
            return !!el && el.isConnected && !el.hidden
                && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        };
        var closeTopic = function () {
            if (!newTopic.open) { return; }
            newTopic.open = false;
            var candidates = [topicReturnFocus, promotedTrigger, fabTrigger, trigger];
            for (var restoreIndex = 0; restoreIndex < candidates.length; restoreIndex++) {
                if (canTakeFocus(candidates[restoreIndex])) {
                    candidates[restoreIndex].focus();
                    return;
                }
            }
        };
        newTopic.addEventListener('toggle', function () {
            for (var expandedIndex = 0; expandedIndex < promotedTriggers.length; expandedIndex++) {
                promotedTriggers[expandedIndex].setAttribute('aria-expanded', newTopic.open ? 'true' : 'false');
            }
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
            if (e.key !== 'Escape' || !newTopic.open) { return; }
            // Escape peels overlays outermost-first (same contract as the DM
            // rail): an open composer popover — slash/reference menu, emoji or
            // GIF dialog — owns this keypress, and the compose modal closes
            // only when it is the topmost thing open. (With focus inside the
            // composer the popover handler also stops propagation; this guard
            // covers keypresses that reach the document directly.)
            if (newTopic.querySelector('.composer-slash-menu:not([hidden]), .composer-reference-menu:not([hidden]), [role="dialog"]:not([hidden])')) { return; }
            closeTopic();
        });
        var cancel = newTopic.querySelector('[data-close-composer]');
        if (cancel) { cancel.addEventListener('click', closeTopic); }
    }

    // Board identity condenses into a sticky bar once the slab scrolls under the
    // topbar. One observer on the slab toggles one class; the bar is pure CSS and
    // its wrapper is zero-height, so nothing here needs to know a bar height.
    var boardView = document.querySelector('.board-view');
    var boardSlab = document.querySelector('[data-board-identity]');
    if (boardView && boardSlab && typeof window.IntersectionObserver === 'function') {
        var condensedTrigger = boardView.querySelector('.board-identity-condensed [data-open-topic-composer]');
        var topbarHeight = (window.getComputedStyle(document.documentElement)
            .getPropertyValue('--topbar-h') || '').trim() || '62px';
        new window.IntersectionObserver(function (entries) {
            var condensed = !entries[entries.length - 1].isIntersecting;
            boardView.classList.toggle('is-condensed', condensed);
            // Keyboard order stays the slab's; the duplicate is pointer-only, and
            // staying [hidden] while the slab is on screen keeps it out of the
            // accessibility tree entirely.
            if (condensedTrigger) { condensedTrigger.hidden = !condensed; }
        }, { rootMargin: '-' + topbarHeight + ' 0px 0px 0px' }).observe(boardSlab);
    }

    // Following a board is a full round-trip; acknowledge the click while it runs.
    var followBoard = document.querySelector('[data-follow-board]');
    if (followBoard && followBoard.form) {
        followBoard.form.addEventListener('submit', function () {
            followBoard.setAttribute('aria-busy', 'true');
        });
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

// --- Admin member directory: bulk selection enhancement ---------------------
// The form and its checked-count fallback are server-rendered. JavaScript only
// mirrors the design's live count and makes the page-level checkbox convenient.
(function () {
    'use strict';
    Array.prototype.forEach.call(document.querySelectorAll('[data-member-bulk-form]'), function (form) {
        var toggle = form.querySelector('[data-bulk-toggle]');
        var counter = form.querySelector('[data-bulk-selected-count]');
        var boxes = form.querySelectorAll('input[name="selected[]"]');

        var update = function () {
            var selected = form.querySelectorAll('input[name="selected[]"]:checked').length;
            if (counter) {
                counter.textContent = selected === 0
                    ? 'None selected'
                    : selected + ' ' + (selected === 1 ? 'member' : 'members') + ' selected';
            }
            if (toggle) {
                toggle.checked = boxes.length > 0 && selected === boxes.length;
                toggle.indeterminate = selected > 0 && selected < boxes.length;
            }
        };

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.matches) { return; }
            if (target.matches('[data-bulk-toggle]')) {
                Array.prototype.forEach.call(boxes, function (box) {
                    box.checked = target.checked;
                });
            }
            if (target.matches('[data-bulk-toggle], input[name="selected[]"]')) {
                update();
            }
        });

        update();
    });
})();

// Admin roles: mirror the design's selected-capability count as progressive
// enhancement on the create form only. JavaScript creates the counter, so it
// is absent (not an empty styled placeholder) from the no-JS document;
// capability submission remains a normal POST.
(function () {
    'use strict';
    Array.prototype.forEach.call(document.querySelectorAll('[data-role-capability-form]'), function (form) {
        var footer = form.querySelector('.role-create-footer');
        if (!footer) { return; }
        var counter = document.createElement('span');
        counter.className = 'role-capability-count';
        counter.setAttribute('data-role-capability-count', '');
        counter.setAttribute('aria-live', 'polite');
        footer.appendChild(counter);
        var update = function () {
            var count = form.querySelectorAll('input[name="capabilities[]"]:checked:not(:disabled)').length;
            counter.textContent = count + ' ' + (count === 1 ? 'capability' : 'capabilities') + ' selected';
        };
        form.addEventListener('change', function (e) {
            if (e.target && e.target.matches && e.target.matches('input[name="capabilities[]"]')) { update(); }
        });
        update();
    });
})();
