// RetroBoards shared composer — progressive enhancement (P3-02/P3-03/P3-04).
// Everything here is optional: the server-rendered <textarea> posts fine without
// it. When present it adds a Markdown toolbar, a live server-rendered preview,
// a character counter, image paste/drag-drop upload, and local draft autosave.
(function () {
    'use strict';
    if (!window.fetch) { return; }

    var BODY_MAX = 20000;

    // Composing preferences (P3-01) are stamped on <body> by the layout for
    // signed-in users. Defaults match the schema: enter-to-send off, preview on,
    // smart lists on.
    function composingPrefs() {
        var b = document.body;
        return {
            enterToSend: b.getAttribute('data-enter-to-send') === '1',
            showPreview: b.getAttribute('data-show-preview') !== '0',
            smartLists: b.getAttribute('data-smart-lists') !== '0'
        };
    }
    function draftsEnabled() {
        return document.body.getAttribute('data-drafts') !== '0';
    }
    function serverDraftsEnabled() {
        return draftsEnabled()
            && document.body.getAttribute('data-server-drafts') === '1'
            && document.body.getAttribute('data-user');
    }

    function tokenField(form) {
        var t = form.querySelector('input[name="_token"]');
        return t ? t.value : '';
    }

    // ---- Markdown toolbar -------------------------------------------------
    function wrapSelection(ta, before, after) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || '';
        ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
        ta.focus();
        ta.selectionStart = s + before.length;
        ta.selectionEnd = s + before.length + sel.length;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Sentence-case labels render in the Marcellus toolbar (handoff §5.2). The
    // accessible label stays "Insert {label}" so existing role queries still match.
    var ACTIONS = {
        bold: { label: 'Bold', before: '**', after: '**', shortcut: 'b', active: 'inline' },
        italic: { label: 'Italic', before: '*', after: '*', shortcut: 'i', active: 'inline' },
        strike: { label: 'Strike', before: '~~', after: '~~', active: 'inline' },
        code: { label: 'Code', before: '`', after: '`', shortcut: 'e', active: 'inline' },
        spoiler: { label: 'Spoiler', before: '||', after: '||', active: 'inline' },
        quote: { label: 'Quote', before: '\n> ', after: '', prefix: '> ', active: 'line' },
        h2: { label: 'Heading', before: '\n## ', after: '', prefix: '## ', active: 'line' },
        list: { label: 'List', before: '\n- ', after: '', prefix: '- ', active: 'line' },
        codeblock: { label: 'Code block', before: '\n```\n', after: '\n```\n', active: 'fence' },
        link: { label: 'Link', before: '[', after: '](https://)', shortcut: 'k', active: 'inline' },
        emoji: { label: 'Emoji', before: ':smile:', after: '' }
    };
    // A hairline separator follows these keys, grouping the bar as
    // emphasis | block | insert (handoff §5.2).
    var GROUP_BREAKS = { spoiler: true, codeblock: true };

    function applyAction(ta, key) {
        var action = ACTIONS[key];
        if (!action) { return false; }
        wrapSelection(ta, action.before, action.after);
        return true;
    }

    function currentLine(ta) {
        var v = ta.value, pos = ta.selectionStart;
        var lineStart = v.lastIndexOf('\n', pos - 1) + 1;
        var lineEnd = v.indexOf('\n', pos);
        if (lineEnd < 0) { lineEnd = v.length; }
        return v.slice(lineStart, lineEnd);
    }

    function inFence(ta) {
        var before = ta.value.slice(0, ta.selectionStart);
        return ((before.match(/^```/gm) || []).length % 2) === 1;
    }

    function actionActive(ta, action) {
        if (!action.active) { return false; }
        var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
        if (action.active === 'inline') {
            return s >= action.before.length
                && v.slice(s - action.before.length, s) === action.before
                && v.slice(e, e + action.after.length) === action.after;
        }
        if (action.active === 'line') {
            return currentLine(ta).indexOf(action.prefix || '') === 0;
        }
        if (action.active === 'fence') {
            return inFence(ta);
        }
        return false;
    }

    function buildToolbar(ta) {
        var bar = document.createElement('div');
        bar.className = 'composer-toolbar';
        var buttons = [];
        function updateState() {
            buttons.forEach(function (item) {
                item.button.setAttribute('aria-pressed', actionActive(ta, item.action) ? 'true' : 'false');
            });
        }
        Object.keys(ACTIONS).forEach(function (key) {
            var action = ACTIONS[key];
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = action.label;
            b.setAttribute('aria-label', 'Insert ' + action.label);
            if (action.shortcut) {
                b.setAttribute('aria-keyshortcuts', 'Control+' + action.shortcut.toUpperCase() + ' Meta+' + action.shortcut.toUpperCase());
            }
            b.setAttribute('aria-pressed', 'false');
            b.addEventListener('click', function () {
                applyAction(ta, key);
                updateState();
            });
            buttons.push({ button: b, action: action });
            bar.appendChild(b);
            if (GROUP_BREAKS[key]) {
                var sep = document.createElement('span');
                sep.className = 'composer-toolbar-sep';
                sep.setAttribute('aria-hidden', 'true');
                bar.appendChild(sep);
            }
        });
        ['input', 'keyup', 'mouseup', 'select'].forEach(function (evt) {
            ta.addEventListener(evt, updateState);
        });
        ta.parentNode.insertBefore(bar, ta);
        updateState();
    }

    // ---- Character counter ------------------------------------------------
    function buildCounter(ta) {
        var c = document.createElement('div');
        c.className = 'composer-count';
        function update() {
            var n = ta.value.length;
            c.textContent = n + ' / ' + BODY_MAX;
            c.classList.toggle('over', n > BODY_MAX);
        }
        ta.addEventListener('input', update);
        update();
        ta.parentNode.appendChild(c);
    }

    // ---- Live preview (same server pipeline) ------------------------------
    function buildPreview(form, ta) {
        var pane = document.createElement('div');
        pane.className = 'composer-preview';
        pane.setAttribute('aria-live', 'polite');
        ta.parentNode.appendChild(pane);

        var timer = null;
        ta.addEventListener('input', function () {
            if (timer) { clearTimeout(timer); }
            timer = setTimeout(function () {
                var data = new FormData();
                data.append('_token', tokenField(form));
                data.append('body', ta.value);
                fetch('/composer/preview', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) { if (j && j.ok) { pane.innerHTML = j.html; } })
                    .catch(function () {});
            }, 350);
        });
    }

    // ---- Idempotency: stamp a fresh token per composer instance -----------
    function stampIdempotency(form) {
        if (form.querySelector('input[name="idempotency_key"]')) { return; }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'idempotency_key';
        input.value = 'c-' + Date.now() + '-' + Math.random().toString(36).slice(2);
        form.appendChild(input);
    }

    // ---- Local draft autosave (per user+context, P3-03) -------------------
    function draftUser() {
        return document.body.getAttribute('data-user') || 'anon';
    }
    function draftContext(form) {
        return form.getAttribute('action') || location.pathname;
    }
    function draftKeyFor(who, context) {
        return 'rb-draft:' + who + ':' + context;
    }
    function serverDraftKey(context) {
        var key = encodeURIComponent(context).replace(/%/g, '~');
        if (key.length <= 191) { return key; }
        var hash = 5381;
        for (var i = 0; i < context.length; i++) {
            hash = ((hash << 5) + hash + context.charCodeAt(i)) >>> 0;
        }
        return 'ctx-' + hash.toString(16);
    }
    function draftTitle(form, fallback) {
        var title = form.querySelector('input[name="title"]');
        if (title && title.value.trim()) { return title.value.trim(); }
        return fallback || '';
    }
    function setDraftTitle(form, title) {
        var input = form.querySelector('input[name="title"]');
        if (input && title) {
            input.value = title;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    function buildDraftSyncPanel(ta) {
        var panel = document.createElement('div');
        panel.className = 'composer-draft-sync';
        panel.hidden = true;
        panel.setAttribute('role', 'status');
        panel.setAttribute('aria-live', 'polite');
        ta.parentNode.appendChild(panel);
        return panel;
    }
    function wireServerDrafts(form, ta, localKey, context, updateDiscard) {
        var apiKey = serverDraftKey(context);
        var panel = buildDraftSyncPanel(ta);
        var timer = null;
        var revision = 0;
        var paused = false;
        var applying = false;

        function showStatus(text) {
            panel.classList.remove('is-conflict');
            panel.setAttribute('role', 'status');
            panel.textContent = text;
            panel.hidden = text === '';
        }
        function request(method, suffix, data) {
            var options = {
                method: method,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            };
            if (data) {
                data.append('_token', tokenField(form));
                options.body = data;
            }
            return fetch('/api/drafts/' + apiKey + (suffix || ''), options);
        }
        function saveWithRevision(expectedRevision, body, title) {
            var data = new FormData();
            data.append('revision', String(expectedRevision));
            data.append('title', title || '');
            data.append('body', body);
            data.append('metadata', JSON.stringify({ context: context, path: location.pathname }));
            return request('POST', '', data)
                .then(function (r) {
                    return r.json().then(function (j) { return { status: r.status, json: j }; });
                })
                .then(function (res) {
                    if (res.status === 409) {
                        renderConflict(res.json.server || null, body, title);
                        return;
                    }
                    if (res.status >= 200 && res.status < 300 && res.json && res.json.draft) {
                        revision = parseInt(res.json.draft.revision, 10) || revision;
                        paused = false;
                        showStatus('Saved to server drafts.');
                    }
                })
                .catch(function () {
                    showStatus('Saved locally; server draft sync is unavailable.');
                });
        }
        function applyServerDraft(server) {
            if (!server) { return; }
            applying = true;
            revision = parseInt(server.revision, 10) || 0;
            ta.value = server.body || '';
            setDraftTitle(form, server.title || '');
            try {
                ta.value ? localStorage.setItem(localKey, ta.value) : localStorage.removeItem(localKey);
            } catch (e) {}
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            applying = false;
            updateDiscard();
        }
        function renderConflict(server, localBody, localTitle) {
            if (!server) {
                revision = 0;
                return;
            }
            revision = parseInt(server.revision, 10) || revision;
            panel.hidden = false;
            panel.classList.add('is-conflict');
            panel.setAttribute('role', 'alert');
            panel.innerHTML = '';

            var message = document.createElement('p');
            message.textContent = 'Draft conflict detected.';
            var detail = document.createElement('p');
            detail.className = 'muted';
            detail.textContent = 'A newer server draft exists for this composer.';
            var actions = document.createElement('div');
            actions.className = 'composer-draft-sync-actions';

            var keepLocal = document.createElement('button');
            keepLocal.type = 'button';
            keepLocal.className = 'btn btn-secondary btn-small';
            keepLocal.textContent = 'Keep local';
            keepLocal.addEventListener('click', function () {
                paused = true;
                showStatus('Kept local draft in this browser; server draft unchanged.');
            });

            var keepServer = document.createElement('button');
            keepServer.type = 'button';
            keepServer.className = 'btn btn-secondary btn-small';
            keepServer.textContent = 'Keep server';
            keepServer.addEventListener('click', function () {
                paused = false;
                applyServerDraft(server);
                showStatus('Loaded server draft.');
            });

            var saveLocal = document.createElement('button');
            saveLocal.type = 'button';
            saveLocal.className = 'btn btn-small';
            saveLocal.textContent = 'Save local as next revision';
            saveLocal.addEventListener('click', function () {
                paused = false;
                saveWithRevision(parseInt(server.revision, 10) || revision, localBody, localTitle);
            });

            actions.appendChild(keepLocal);
            actions.appendChild(keepServer);
            actions.appendChild(saveLocal);
            panel.appendChild(message);
            panel.appendChild(detail);
            panel.appendChild(actions);
        }
        function saveCurrent() {
            if (paused || applying) { return; }
            var body = ta.value;
            if (!body) {
                request('POST', '/discard', new FormData()).catch(function () {});
                revision = 0;
                showStatus('');
                return;
            }
            saveWithRevision(revision, body, draftTitle(form, draftLabel(context)));
        }
        function scheduleSave() {
            if (paused || applying) { return; }
            if (timer) { clearTimeout(timer); }
            timer = setTimeout(saveCurrent, 800);
        }

        request('GET', '')
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                var server = j && j.draft ? j.draft : null;
                if (!server) { return; }
                revision = parseInt(server.revision, 10) || 0;
                if (ta.value && ta.value !== (server.body || '')) {
                    renderConflict(server, ta.value, draftTitle(form, draftLabel(context)));
                    return;
                }
                if (!ta.value && server.body) {
                    applyServerDraft(server);
                    showStatus('Loaded server draft.');
                }
            })
            .catch(function () {});

        return {
            input: scheduleSave,
            discard: function () { request('POST', '/discard', new FormData()).catch(function () {}); }
        };
    }
    var pendingDraftSubmitKey = 'rb-draft:pending-submits';
    function pendingDraftSubmits() {
        try {
            var raw = sessionStorage.getItem(pendingDraftSubmitKey);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }
    function writePendingDraftSubmits(items) {
        try {
            if (items.length) { sessionStorage.setItem(pendingDraftSubmitKey, JSON.stringify(items)); }
            else { sessionStorage.removeItem(pendingDraftSubmitKey); }
        } catch (e) {}
    }
    function rememberPendingDraftSubmit(key, context) {
        var items = pendingDraftSubmits().filter(function (item) { return item && item.key !== key; });
        items.push({ key: key, context: context, at: Date.now() });
        writePendingDraftSubmits(items.slice(-20));
    }
    function clearCompletedDraftSubmits() {
        var pending = pendingDraftSubmits();
        if (!pending.length) { return; }
        var keep = [];
        var forms = document.querySelectorAll('form.composer');
        pending.forEach(function (item) {
            if (!item || !item.key || !item.context) { return; }
            var samePostUrlWithBody = false;
            for (var i = 0; i < forms.length; i++) {
                var form = forms[i];
                if (form.getAttribute('action') !== item.context) { continue; }
                var ta = form.querySelector('.composer-input');
                if (location.pathname === item.context && ta && ta.value) {
                    samePostUrlWithBody = true;
                    break;
                }
            }
            if (samePostUrlWithBody) {
                keep.push(item);
                return;
            }
            try { localStorage.removeItem(item.key); } catch (e) {}
        });
        writePendingDraftSubmits(keep);
    }
    function migrateAnonDrafts(who) {
        if (who === 'anon') { return; }
        try {
            var anonPrefix = 'rb-draft:anon:';
            for (var i = localStorage.length - 1; i >= 0; i--) {
                var key = localStorage.key(i);
                if (!key || key.indexOf(anonPrefix) !== 0) { continue; }
                var context = key.slice(anonPrefix.length);
                var target = draftKeyFor(who, context);
                if (!localStorage.getItem(target)) {
                    localStorage.setItem(target, localStorage.getItem(key) || '');
                }
                localStorage.removeItem(key);
            }
        } catch (e) {}
    }
    function buildDiscard(form, ta, key) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-secondary btn-small composer-discard';
        b.textContent = 'Discard draft';
        function update() {
            var hasSaved = false;
            try { hasSaved = !!localStorage.getItem(key); } catch (e) {}
            b.hidden = !hasSaved && !ta.value;
        }
        b.addEventListener('click', function () {
            try { localStorage.removeItem(key); } catch (e) {}
            ta.value = '';
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            update();
        });
        ta.parentNode.appendChild(b);
        update();
        return update;
    }
    function wireDrafts(form, ta) {
        var who = draftUser();
        var context = draftContext(form);
        migrateAnonDrafts(who);
        var key = draftKeyFor(who, context);
        try {
            var saved = localStorage.getItem(key);
            if (saved && !ta.value) { ta.value = saved; ta.dispatchEvent(new Event('input', { bubbles: true })); }
        } catch (e) {}
        var updateDiscard = buildDiscard(form, ta, key);
        var serverDrafts = serverDraftsEnabled() ? wireServerDrafts(form, ta, key, context, updateDiscard) : null;
        ta.addEventListener('input', function () {
            try { ta.value ? localStorage.setItem(key, ta.value) : localStorage.removeItem(key); } catch (e) {}
            if (serverDrafts) { serverDrafts.input(); }
            updateDiscard();
        });
        form.addEventListener('submit', function () {
            // Do not clear immediately: a dropped connection can leave the user on
            // the same page. The next successfully loaded page clears this context
            // before an empty composer can repopulate from localStorage.
            rememberPendingDraftSubmit(key, context);
            if (serverDrafts) { serverDrafts.discard(); }
            updateDiscard();
        });
    }

    function draftResumeHref(context) {
        var m = context.match(/^\/t\/(\d+)\/reply$/);
        if (m) { return '/t/' + m[1]; }
        m = context.match(/^\/messages\/(\d+)$/);
        if (m) { return context; }
        if (context === '/messages') { return '/messages/new'; }
        if (context === '/threads') { return '/'; }
        return '';
    }
    function draftLabel(context) {
        if (context === '/threads') { return 'New topic'; }
        if (context === '/messages') { return 'New direct message'; }
        if (/^\/messages\/\d+$/.test(context)) { return 'Direct-message reply'; }
        if (/^\/t\/\d+\/reply$/.test(context)) { return 'Thread reply'; }
        if (/^\/posts\/\d+\/edit$/.test(context)) { return 'Post edit'; }
        return context;
    }
    function renderDraftsPage() {
        var host = document.querySelector('[data-drafts-list]');
        if (!host || !draftsEnabled()) { return; }
        if (host.hasAttribute('data-server-drafts')) { return; }
        var who = draftUser();
        migrateAnonDrafts(who);
        var prefix = 'rb-draft:' + who + ':';
        var drafts = [];
        try {
            for (var i = 0; i < localStorage.length; i++) {
                var key = localStorage.key(i);
                if (!key || key.indexOf(prefix) !== 0) { continue; }
                var body = localStorage.getItem(key) || '';
                if (!body) { continue; }
                drafts.push({ key: key, context: key.slice(prefix.length), body: body });
            }
        } catch (e) {}
        drafts.sort(function (a, b) { return a.context.localeCompare(b.context); });
        host.innerHTML = '';
        if (!drafts.length) {
            var empty = document.createElement('p');
            empty.className = 'muted empty';
            empty.textContent = 'No saved drafts in this browser.';
            host.appendChild(empty);
            return;
        }
        drafts.forEach(function (d) {
            var card = document.createElement('article');
            card.className = 'card';
            var h = document.createElement('h2');
            h.textContent = draftLabel(d.context);
            var p = document.createElement('p');
            p.className = 'muted';
            p.textContent = d.context;
            var pre = document.createElement('pre');
            pre.className = 'draft-preview';
            pre.textContent = d.body.length > 500 ? d.body.slice(0, 500) + '...' : d.body;
            var actions = document.createElement('p');
            actions.className = 'form-actions';
            var href = draftResumeHref(d.context);
            if (href) {
                var resume = document.createElement('a');
                resume.className = 'btn btn-small';
                resume.href = href;
                resume.textContent = 'Resume';
                actions.appendChild(resume);
            }
            var discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'btn btn-secondary btn-small';
            discard.textContent = 'Discard';
            discard.addEventListener('click', function () {
                try { localStorage.removeItem(d.key); } catch (e) {}
                card.remove();
                if (!host.querySelector('article')) { renderDraftsPage(); }
            });
            actions.appendChild(discard);
            card.appendChild(h);
            card.appendChild(p);
            card.appendChild(pre);
            card.appendChild(actions);
            host.appendChild(card);
        });
    }

    // ---- Image paste / drag-drop upload (P3-04) ---------------------------
    function insertAtCursor(ta, text) {
        var s = ta.selectionStart;
        ta.value = ta.value.slice(0, s) + text + ta.value.slice(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + text.length;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function replaceRange(ta, start, end, text) {
        ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
        ta.selectionStart = ta.selectionEnd = start + text.length;
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function replaceOnce(ta, from, to) {
        var idx = ta.value.indexOf(from);
        if (idx < 0) { return false; }
        ta.value = ta.value.slice(0, idx) + to + ta.value.slice(idx + from.length);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function markdownAlt(alt) {
        // Escape backslash and both brackets in one pass (so inserted escapes
        // are not re-processed) to keep the ![alt](url) image syntax intact.
        return (alt || '').replace(/[\r\n]+/g, ' ').replace(/[\\\[\]]/g, '\\$&');
    }
    function imageMarkdown(url, alt) {
        return '![' + markdownAlt(alt) + '](' + url + ')';
    }
    function uploadPurpose(form) {
        var action = form.getAttribute('action') || '';
        return action.indexOf('/messages') === 0 ? 'dm' : 'post';
    }
    function uploadTray(form, ta) {
        var tray = form.querySelector('.composer-upload-tray');
        if (tray) { return tray; }
        tray = document.createElement('div');
        tray.className = 'composer-upload-tray';
        tray.setAttribute('aria-live', 'polite');
        ta.parentNode.appendChild(tray);
        return tray;
    }
    function moveSnippetBefore(ta, moving, anchor) {
        if (!moving || !anchor || moving === anchor) { return false; }
        var v = ta.value;
        var mi = v.indexOf(moving);
        if (mi < 0 || v.indexOf(anchor) < 0) { return false; }
        v = v.slice(0, mi) + v.slice(mi + moving.length);
        var ai = v.indexOf(anchor);
        if (ai < 0) { return false; }
        ta.value = v.slice(0, ai) + moving + v.slice(ai);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function moveSnippetAfter(ta, moving, anchor) {
        if (!moving || !anchor || moving === anchor) { return false; }
        var v = ta.value;
        var mi = v.indexOf(moving);
        if (mi < 0 || v.indexOf(anchor) < 0) { return false; }
        v = v.slice(0, mi) + v.slice(mi + moving.length);
        var ai = v.indexOf(anchor);
        if (ai < 0) { return false; }
        ta.value = v.slice(0, ai + anchor.length) + moving + v.slice(ai + anchor.length);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function uploadCard(form, ta, file, placeholder) {
        var tray = uploadTray(form, ta);
        var card = document.createElement('div');
        card.className = 'composer-upload-card is-uploading';
        var preview = document.createElement('img');
        preview.className = 'composer-upload-thumb';
        preview.alt = '';
        preview.hidden = true;
        var meta = document.createElement('div');
        meta.className = 'composer-upload-meta';
        var status = document.createElement('div');
        status.className = 'composer-upload-status';
        status.textContent = 'Uploading ' + (file && file.name ? file.name : 'image') + '...';
        var progress = document.createElement('progress');
        progress.max = 100;
        progress.value = 0;
        var alt = document.createElement('input');
        alt.type = 'text';
        alt.className = 'input input-small';
        alt.placeholder = 'Alt text';
        alt.setAttribute('aria-label', 'Image alt text');
        alt.disabled = true;
        var actions = document.createElement('div');
        actions.className = 'composer-upload-actions';
        var up = document.createElement('button');
        up.type = 'button';
        up.className = 'btn btn-secondary btn-small';
        up.textContent = 'Up';
        var down = document.createElement('button');
        down.type = 'button';
        down.className = 'btn btn-secondary btn-small';
        down.textContent = 'Down';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-secondary btn-small';
        remove.textContent = 'Remove';
        actions.appendChild(up);
        actions.appendChild(down);
        actions.appendChild(remove);
        meta.appendChild(status);
        meta.appendChild(progress);
        meta.appendChild(alt);
        meta.appendChild(actions);
        card.appendChild(preview);
        card.appendChild(meta);
        tray.appendChild(card);
        card._rbMarkdown = '';
        remove.addEventListener('click', function () {
            replaceOnce(ta, card._rbMarkdown || placeholder, '');
            card.remove();
        });
        up.addEventListener('click', function () {
            var prev = card.previousElementSibling;
            if (prev && moveSnippetBefore(ta, card._rbMarkdown, prev._rbMarkdown || '')) {
                card.parentNode.insertBefore(card, prev);
            }
        });
        down.addEventListener('click', function () {
            var next = card.nextElementSibling;
            if (next && moveSnippetAfter(ta, card._rbMarkdown, next._rbMarkdown || '')) {
                card.parentNode.insertBefore(next, card);
            }
        });
        alt.addEventListener('input', function () {
            if (!card._rbUrl || !card._rbMarkdown) { return; }
            var next = imageMarkdown(card._rbUrl, alt.value);
            if (replaceOnce(ta, card._rbMarkdown, next)) {
                card._rbMarkdown = next;
                preview.alt = alt.value;
            }
        });
        return {
            progress: function (pct) { progress.value = Math.max(0, Math.min(100, pct)); },
            complete: function (json) {
                var markdown = imageMarkdown(json.url, '');
                replaceOnce(ta, placeholder, markdown);
                card._rbUrl = json.url;
                card._rbMarkdown = markdown;
                preview.src = json.url;
                preview.alt = '';
                preview.hidden = false;
                alt.disabled = false;
                progress.value = 100;
                status.textContent = 'Uploaded image ' + json.width + 'x' + json.height + '.';
                card.classList.remove('is-uploading');
                card.classList.add('is-complete');
            },
            fail: function (message) {
                replaceOnce(ta, placeholder, '');
                progress.remove();
                alt.disabled = true;
                status.textContent = message || 'Upload failed.';
                card.classList.remove('is-uploading');
                card.classList.add('is-failed');
            }
        };
    }
    function uploadImage(form, ta, file) {
        var data = new FormData();
        data.append('_token', tokenField(form));
        data.append('image', file);
        data.append('purpose', uploadPurpose(form));
        // Unique per upload so several images pasted/dropped at once each resolve
        // into their OWN marker — String.replace(str) only swaps the first match.
        var token = 'rbup-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
        var placeholder = '![uploading…](' + token + ')';
        insertAtCursor(ta, placeholder);
        var card = uploadCard(form, ta, file, placeholder);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/upload');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) { card.progress((e.loaded / e.total) * 95); }
        };
        xhr.onload = function () {
            var j = null;
            try { j = JSON.parse(xhr.responseText || '{}'); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300 && j && j.ok) {
                card.complete(j);
            } else {
                card.fail((j && j.error) || 'Upload failed.');
            }
        };
        xhr.onerror = function () { card.fail('Upload failed. Check your connection and try again.'); };
        xhr.send(data);
    }
    function wireUploads(form, ta) {
        ta.addEventListener('paste', function (e) {
            var items = (e.clipboardData || {}).items || [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image/') === 0) {
                    uploadImage(form, ta, items[i].getAsFile());
                }
            }
        });
        ta.addEventListener('dragover', function (e) { e.preventDefault(); });
        ta.addEventListener('drop', function (e) {
            var files = (e.dataTransfer || {}).files || [];
            if (!files.length) { return; }
            e.preventDefault();
            for (var i = 0; i < files.length; i++) {
                if (files[i].type && files[i].type.indexOf('image/') === 0) { uploadImage(form, ta, files[i]); }
            }
        });
    }

    // ---- Slash inserts + GIPHY picker (Phase 4 carryover) ----------------
    var slashConfigPromise = null;
    function loadSlashConfig() {
        if (slashConfigPromise !== null) { return slashConfigPromise; }
        slashConfigPromise = fetch('/composer/giphy-config', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.ok ? r.json() : null;
        }).then(function (j) {
            return j && j.ok && j.enabled ? j : null;
        }).catch(function () {
            return null;
        });
        return slashConfigPromise;
    }
    function allowedInsert(config, key) {
        return config && Array.isArray(config.allowed_inserts) && config.allowed_inserts.indexOf(key) !== -1;
    }
    var SLASH_SNIPPETS = {
        table: {
            label: 'table',
            terms: ['table'],
            body: '| Heading | Heading |\n|---|---|\n| Cell | Cell |'
        },
        task_list: {
            label: 'task list',
            terms: ['task', 'tasks', 'todo'],
            body: '- [ ] Task'
        },
        poll: {
            label: 'poll outline',
            terms: ['poll', 'vote'],
            body: 'Poll: Question?\n- Option A\n- Option B'
        },
        custom_emoji: {
            label: 'custom emoji shortcode',
            terms: ['emoji', 'custom emoji'],
            body: ':shortcode:'
        }
    };
    function slashState(ta) {
        if (ta.selectionStart !== ta.selectionEnd) { return null; }
        var pos = ta.selectionStart;
        var lineStart = ta.value.lastIndexOf('\n', pos - 1) + 1;
        var prefix = ta.value.slice(lineStart, pos);
        var match = prefix.match(/(^|\s)\/([A-Za-z0-9_ -]*)$/);
        if (!match) { return null; }
        var query = (match[2] || '').toLowerCase().trim();
        return {
            start: pos - (match[2] || '').length - 1,
            end: pos,
            query: query
        };
    }
    function slashQueryMatches(query, command) {
        if (query === '') { return true; }
        for (var i = 0; i < command.terms.length; i++) {
            if (command.terms[i].indexOf(query) === 0 || query.indexOf(command.terms[i] + ' ') === 0) {
                return true;
            }
        }
        return command.label.indexOf(query) !== -1;
    }
    function slashCommands(config, query) {
        var commands = [];
        Object.keys(SLASH_SNIPPETS).forEach(function (key) {
            if (!allowedInsert(config, key)) { return; }
            var snippet = SLASH_SNIPPETS[key];
            var command = {
                key: key,
                type: 'snippet',
                label: snippet.label,
                terms: snippet.terms,
                body: snippet.body
            };
            if (slashQueryMatches(query, command)) { commands.push(command); }
        });
        if (allowedInsert(config, 'giphy') && config.public_key) {
            var giphy = { key: 'giphy', type: 'giphy', label: 'GIPHY', terms: ['gif', 'giphy'] };
            if (slashQueryMatches(query, giphy)) { commands.push(giphy); }
        }
        return commands;
    }
    function giphySearchTerm(query) {
        return query.replace(/^(gif|giphy)\s*/i, '').trim();
    }
    function giphyResultUrl(item) {
        return item && item.images && item.images.original && item.images.original.url
            ? item.images.original.url
            : '';
    }
    function wireSlashMenu(form, ta) {
        var menu = document.createElement('div');
        menu.className = 'composer-slash-menu';
        menu.setAttribute('aria-label', 'Composer insert commands');
        menu.hidden = true;
        ta.parentNode.insertBefore(menu, ta.nextSibling);

        var config = null;
        var ready = false;
        var activeState = null;

        function hide() {
            activeState = null;
            menu.hidden = true;
            menu.innerHTML = '';
        }
        function renderButtons(commands) {
            menu.innerHTML = '';
            commands.forEach(function (command) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'composer-slash-command';
                b.textContent = command.type === 'giphy' ? 'Search GIPHY' : 'Insert ' + command.label;
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var state = activeState || slashState(ta);
                    if (!state) { hide(); return; }
                    if (command.type === 'giphy') {
                        searchGiphy(state, giphySearchTerm(state.query));
                        return;
                    }
                    replaceRange(ta, state.start, state.end, command.body);
                    hide();
                });
                menu.appendChild(b);
            });
            menu.hidden = false;
        }
        function render() {
            if (!ready || config === null) { return; }
            var state = slashState(ta);
            if (!state) { hide(); return; }
            activeState = state;
            var commands = slashCommands(config, state.query);
            if (commands.length === 0) { hide(); return; }
            renderButtons(commands);
        }
        function searchGiphy(state, term) {
            if (!term) {
                menu.innerHTML = '<p class="muted">Type a search after /gif.</p>';
                menu.hidden = false;
                return;
            }
            menu.innerHTML = '<p class="muted">Searching GIPHY...</p>';
            menu.hidden = false;
            var url = 'https://api.giphy.com/v1/gifs/search'
                + '?api_key=' + encodeURIComponent(config.public_key)
                + '&q=' + encodeURIComponent(term)
                + '&rating=' + encodeURIComponent(config.rating || 'pg')
                + '&limit=6';
            fetch(url).then(function (r) {
                return r.ok ? r.json() : null;
            }).then(function (j) {
                var items = j && Array.isArray(j.data) ? j.data : [];
                menu.innerHTML = '';
                if (items.length === 0) {
                    menu.innerHTML = '<p class="muted">No GIFs found.</p>';
                    return;
                }
                items.forEach(function (item) {
                    var mediaUrl = giphyResultUrl(item);
                    if (!mediaUrl) { return; }
                    var title = (item.title || 'GIF').trim() || 'GIF';
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'composer-slash-gif';
                    b.setAttribute('aria-label', 'Insert GIF ' + title);
                    var imgUrl = item.images && item.images.fixed_height_small && item.images.fixed_height_small.url
                        ? item.images.fixed_height_small.url
                        : mediaUrl;
                    var img = document.createElement('img');
                    img.src = imgUrl;
                    img.alt = '';
                    var span = document.createElement('span');
                    span.textContent = title;
                    b.appendChild(img);
                    b.appendChild(span);
                    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                    b.addEventListener('click', function (e) {
                        e.stopPropagation();
                        replaceRange(ta, state.start, state.end, imageMarkdown(mediaUrl, title));
                        hide();
                    });
                    menu.appendChild(b);
                });
                if (!menu.childNodes.length) {
                    menu.innerHTML = '<p class="muted">No GIFs found.</p>';
                }
            }).catch(function () {
                menu.innerHTML = '<p class="muted">GIPHY search is unavailable.</p>';
            });
        }

        loadSlashConfig().then(function (j) {
            config = j;
            ready = true;
            render();
        });
        ta.addEventListener('input', render);
        ta.addEventListener('keyup', render);
        ta.addEventListener('click', render);
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !menu.hidden) {
                e.preventDefault();
                hide();
            }
        });
        document.addEventListener('click', function (e) {
            if (e.target === ta || menu.contains(e.target)) { return; }
            hide();
        });
    }

    // ---- Enter-to-send + smart list continuation (P3-01) ------------------
    // Continue or end a Markdown list when Enter is pressed inside one. Returns
    // true when it handled the key (so the caller suppresses the default newline).
    function continueList(ta) {
        if (ta.selectionStart !== ta.selectionEnd) { return false; }
        var v = ta.value, pos = ta.selectionStart;
        var lineStart = v.lastIndexOf('\n', pos - 1) + 1;
        var line = v.slice(lineStart, pos);
        var um = line.match(/^(\s*)([-*+])\s+(\S.*)?$/);          // - item / * item
        var om = line.match(/^(\s*)(\d+)([.)])\s+(\S.*)?$/);      // 1. item / 1) item
        if (!um && !om) { return false; }
        var empty = um ? !um[3] : !om[4];
        if (empty) {
            // Enter on a bare marker ends the list: drop the marker, blank line.
            ta.value = v.slice(0, lineStart) + v.slice(pos);
            ta.selectionStart = ta.selectionEnd = lineStart;
        } else {
            var next = um ? (um[1] + um[2] + ' ')
                          : (om[1] + (parseInt(om[2], 10) + 1) + om[3] + ' ');
            ta.value = v.slice(0, pos) + '\n' + next + v.slice(pos);
            ta.selectionStart = ta.selectionEnd = pos + 1 + next.length;
        }
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function wireKeys(form, ta, prefs) {
        ta.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey) {
                var key = e.key.toLowerCase();
                var action = key === 'b' ? 'bold'
                    : key === 'i' ? 'italic'
                    : key === 'k' ? 'link'
                    : key === 'e' ? 'code'
                    : null;
                if (action !== null) {
                    e.preventDefault();
                    applyAction(ta, action);
                    return;
                }
            }
            if (e.key !== 'Enter' || e.isComposing) { return; }
            // Shift/modifier+Enter always inserts a newline (default behaviour).
            if (e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) { return; }
            if (prefs.enterToSend) {
                e.preventDefault();
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
                else { form.submit(); }
                return;
            }
            if (prefs.smartLists && continueList(ta)) { e.preventDefault(); }
        });
    }

    function enhance(form, prefs) {
        var ta = form.querySelector('.composer-input');
        if (!ta || ta.getAttribute('data-rb-enhanced')) { return; }
        ta.setAttribute('data-rb-enhanced', '1');
        buildToolbar(ta);
        buildCounter(ta);
        if (prefs.showPreview) { buildPreview(form, ta); }
        wireKeys(form, ta, prefs);
        stampIdempotency(form);
        // A form may opt out of local draft autosave (data-no-draft). The inline
        // post-edit form does: its textarea is server-pre-filled with the current
        // body, so a saved draft is never restored into it (the !ta.value guard
        // fails) and there is no Drafts-page resume target — autosaving would only
        // leave a misleading, unrecoverable draft that the next load discards.
        if (draftsEnabled() && !form.hasAttribute('data-no-draft')) { wireDrafts(form, ta); }
        wireUploads(form, ta);
        wireSlashMenu(form, ta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var prefs = composingPrefs();
        clearCompletedDraftSubmits();
        var forms = document.querySelectorAll('form.composer');
        for (var i = 0; i < forms.length; i++) { enhance(forms[i], prefs); }
        renderDraftsPage();
    });
})();
