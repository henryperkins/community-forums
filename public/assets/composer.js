// RetroBoards shared composer — progressive enhancement (P3-02/P3-03/P3-04).
// Everything here is optional: the server-rendered <textarea> posts fine without
// it. When present it adds a Markdown toolbar, a live server-rendered preview,
// a character counter, image paste/drag-drop upload, and local draft autosave.
(function () {
    'use strict';
    if (!window.fetch) { return; }

    // Composing preferences (P3-01) are stamped on <body> by the layout for
    // signed-in users. Defaults match the schema: enter-to-send, preview, and
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

    function shellPart(form, selector) {
        return form.querySelector(selector);
    }

    function storageRead(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function storageWrite(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) {}
    }

    function TextareaComposerAdapter(form, ta) {
        this.form = form;
        this.ta = ta;
        this.changeHandlers = [];
        var self = this;
        ta.addEventListener('input', function () {
            self.changeHandlers.forEach(function (cb) { cb(self.getMarkdown()); });
        });
    }
    TextareaComposerAdapter.prototype.getMarkdown = function () { return this.ta.value; };
    TextareaComposerAdapter.prototype.setMarkdown = function (markdown) {
        this.ta.value = markdown || '';
        this.ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    TextareaComposerAdapter.prototype.insertMarkdown = function (markdown) {
        this.replaceSelection(markdown);
    };
    TextareaComposerAdapter.prototype.replaceSelection = function (markdown) {
        var ta = this.ta;
        var s = ta.selectionStart || 0;
        var e = ta.selectionEnd || s;
        ta.value = ta.value.slice(0, s) + markdown + ta.value.slice(e);
        ta.selectionStart = ta.selectionEnd = s + markdown.length;
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    TextareaComposerAdapter.prototype.rememberSelection = function () {
        return { start: this.ta.selectionStart || 0, end: this.ta.selectionEnd || 0 };
    };
    TextareaComposerAdapter.prototype.replaceRememberedSelection = function (mark, markdown) {
        this.ta.selectionStart = mark.start;
        this.ta.selectionEnd = mark.end;
        this.replaceSelection(markdown);
    };
    TextareaComposerAdapter.prototype.replacePendingUpload = function (token, markdown) {
        return replaceOnce(this.ta, token, markdown);
    };
    TextareaComposerAdapter.prototype.focus = function () { this.ta.focus(); };
    TextareaComposerAdapter.prototype.onChange = function (callback) { this.changeHandlers.push(callback); };
    TextareaComposerAdapter.prototype.setDisabled = function (disabled) { this.ta.disabled = !!disabled; };
    TextareaComposerAdapter.prototype.enterShouldSubmit = function () { return textareaEnterShouldSubmit(this.ta); };
    TextareaComposerAdapter.prototype.isSourceMode = function () { return true; };
    TextareaComposerAdapter.prototype.destroy = function () {};

    var wysiwygFactory = null;

    function registerWysiwygAdapter(factory) {
        wysiwygFactory = factory;
        document.querySelectorAll('form.composer').forEach(function (form) {
            if (form._rbComposerEnhance) { form._rbComposerEnhance(); }
        });
    }

    function enhanceWithin(root) {
        var forms = [];
        if (root && root.matches && root.matches('form.composer')) { forms.push(root); }
        if (root && root.querySelectorAll) {
            root.querySelectorAll('form.composer').forEach(function (form) { forms.push(form); });
        }
        var prefs = composingPrefs();
        forms.forEach(function (form) { enhance(form, prefs); });
    }

    window.RetroBoardsComposer = {
        registerWysiwygAdapter: registerWysiwygAdapter,
        enhanceWithin: enhanceWithin
    };

    function shouldUseWysiwyg(form) {
        return document.body.getAttribute('data-wysiwyg-composer') === '1'
            && wysiwygFactory
            && !form.hasAttribute('data-no-wysiwyg');
    }

    function maybeUpgradeWysiwyg(form, ta, fallback) {
        if (!shouldUseWysiwyg(form) || form._rbWysiwygAdapter || form._rbWysiwygAttempted) {
            return form._rbComposerAdapter || fallback;
        }
        form._rbWysiwygAttempted = true;
        try {
            var rich = wysiwygFactory(form, ta, fallback);
            if (rich) {
                form._rbWysiwygAdapter = rich;
                form._rbComposerAdapter = rich;
                if (form._rbPreviewController) { form._rbPreviewController.reconcile(rich); }
                if (form._rbSubmitController) { form._rbSubmitController.attach(rich); }
                return rich;
            }
        } catch (e) {}
        return form._rbComposerAdapter || fallback;
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

    var ACTION_ORDER = [
        'bold', 'italic', 'strike', 'code', 'quote', 'h2',
        'list', 'orderedList', 'codeblock', 'spoiler', 'link'
    ];
    var ACTIONS = {
        bold: { label: 'Bold', before: '**', after: '**', shortcut: 'b', active: 'inline' },
        italic: { label: 'Italic', before: '*', after: '*', shortcut: 'i', active: 'inline' },
        strike: { label: 'Strike', before: '~~', after: '~~', active: 'inline' },
        code: { label: 'Inline code', before: '`', after: '`', shortcut: 'e', active: 'inline' },
        quote: { label: 'Quote', before: '\n> ', after: '', prefix: '> ', active: 'line' },
        h2: { label: 'Heading', before: '\n## ', after: '', prefix: '## ', active: 'line' },
        list: { label: 'Bullet list', before: '\n- ', after: '', prefix: '- ', active: 'line' },
        orderedList: { label: 'Numbered list', before: '\n1. ', after: '', prefix: '1. ', active: 'ordered-line' },
        codeblock: { label: 'Code block', before: '\n```\n', after: '\n```\n', active: 'fence' },
        spoiler: { label: 'Spoiler', before: '||', after: '||', active: 'inline' },
        link: { label: 'Link', before: '[', after: '](https://)', shortcut: 'k', active: 'inline' }
    };
    var GROUP_BREAKS = { code: true, spoiler: true };
    var ESSENTIAL_ACTIONS = { bold: true, italic: true, list: true, link: true };
    var ICON_PATHS = {
        bold: ['M8 5h5a3 3 0 0 1 0 6H8z', 'M8 11h6a4 4 0 0 1 0 8H8z'],
        italic: ['M10 5h7', 'M7 19h7', 'M14 5 10 19'],
        strike: ['M6 7h10', 'M5 12h14', 'M8 17h8'],
        code: ['m9 8-4 4 4 4', 'm15 8 4 4-4 4'],
        quote: ['M6 7h5v5H7v5', 'M14 7h5v5h-4v5'],
        h2: ['M5 6v12', 'M13 6v12', 'M5 12h8', 'M16 10c0-2 4-2 4 0 0 2-4 3-4 6h5'],
        list: ['M9 7h10', 'M9 12h10', 'M9 17h10', 'M5 7h.01', 'M5 12h.01', 'M5 17h.01'],
        orderedList: ['M5 6h1v3', 'M5 13c2-1 2 2 0 3h2', 'M10 7h9', 'M10 12h9', 'M10 17h9'],
        codeblock: ['M5 6h14v12H5z', 'm9 10-2 2 2 2', 'm6-4 2 2-2 2'],
        spoiler: ['M3 12s3-5 9-5 9 5 9 5-3 5-9 5-9-5-9-5', 'M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4'],
        link: ['M10 14 8.5 15.5a3 3 0 0 1-4-4L7 9a3 3 0 0 1 4 0', 'm14 10 1.5-1.5a3 3 0 0 1 4 4L17 15a3 3 0 0 1-4 0', 'm9 15 6-6']
    };

    function applyAction(ta, key) {
        var action = ACTIONS[key];
        if (!action) { return false; }
        wrapSelection(ta, action.before, action.after);
        return true;
    }

    function adapterEventTargets(adapter, ta, method) {
        if (adapter && typeof adapter[method] === 'function') {
            try {
                var targets = adapter[method]();
                if (targets && typeof targets.length === 'number') {
                    var out = [];
                    for (var i = 0; i < targets.length; i++) {
                        if (targets[i] && typeof targets[i].addEventListener === 'function') {
                            out.push(targets[i]);
                        }
                    }
                    if (out.length) { return out; }
                }
            } catch (e) {}
        }
        return [ta];
    }

    function applyActionForAdapter(adapter, ta, key) {
        var action = ACTIONS[key];
        if (!action) { return false; }
        if (adapter && typeof adapter.applyAction === 'function') {
            try {
                if (adapter.applyAction(key, action)) { return true; }
            } catch (e) {}
        }
        return applyAction(ta, key);
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
        if (action.active === 'ordered-line') {
            return /^\s*\d+[.)]\s/.test(currentLine(ta));
        }
        if (action.active === 'fence') {
            return inFence(ta);
        }
        return false;
    }

    function actionIcon(key) {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        (ICON_PATHS[key] || []).forEach(function (pathData) {
            var path = document.createElementNS(ns, 'path');
            path.setAttribute('d', pathData);
            svg.appendChild(path);
        });
        return svg;
    }

    var toolbarSeq = 0;
    function buildToolbar(form, ta) {
        var slot = shellPart(form, '[data-composer-format-slot]');
        if (!slot) { return; }
        var bar = document.createElement('div');
        bar.className = 'composer-toolbar';
        bar.id = 'composer-toolbar-' + (++toolbarSeq);
        bar.setAttribute('aria-label', 'Formatting');
        var buttons = [];
        function updateState() {
            buttons.forEach(function (item) {
                item.button.setAttribute('aria-pressed', actionActive(ta, item.action) ? 'true' : 'false');
            });
        }

        function makeActionButton(key, overflow) {
            var action = ACTIONS[key];
            var b = document.createElement('button');
            b.type = 'button';
            b.className = overflow ? 'composer-overflow-action' : 'composer-toolbar-action';
            if (!overflow && ESSENTIAL_ACTIONS[key]) { b.classList.add('is-essential'); }
            var shortcut = action.shortcut ? 'Ctrl+' + action.shortcut.toUpperCase() : '';
            b.setAttribute('aria-label', action.label + (shortcut ? ' (' + shortcut + ')' : ''));
            if (overflow) {
                b.textContent = action.label;
                b.setAttribute('data-composer-overflow-action', key);
            } else {
                b.appendChild(actionIcon(key));
                b.setAttribute('data-composer-action', key);
                b.setAttribute('data-tip', action.label + (shortcut ? ' · ' + shortcut : ''));
            }
            if (action.shortcut) {
                b.setAttribute('aria-keyshortcuts', 'Control+' + action.shortcut.toUpperCase() + ' Meta+' + action.shortcut.toUpperCase());
            }
            b.setAttribute('aria-pressed', 'false');
            b.addEventListener('click', function () {
                applyActionForAdapter(form._rbComposerAdapter || form._rbComposerFallbackAdapter, ta, key);
                updateState();
            });
            buttons.push({ button: b, action: action });
            return b;
        }

        ACTION_ORDER.forEach(function (key) {
            var b = makeActionButton(key, false);
            bar.appendChild(b);
            if (GROUP_BREAKS[key]) {
                var sep = document.createElement('span');
                sep.className = 'composer-toolbar-sep';
                sep.setAttribute('aria-hidden', 'true');
                bar.appendChild(sep);
            }
        });

        var moreWrap = document.createElement('span');
        moreWrap.className = 'composer-more-wrap';
        var more = document.createElement('button');
        more.type = 'button';
        more.className = 'composer-more-toggle';
        more.textContent = '＋';
        more.setAttribute('aria-label', 'More formatting');
        more.setAttribute('aria-expanded', 'false');
        var overflow = document.createElement('div');
        overflow.className = 'composer-format-overflow';
        overflow.id = bar.id + '-overflow';
        overflow.hidden = true;
        overflow.setAttribute('role', 'group');
        overflow.setAttribute('aria-label', 'More formatting');
        more.setAttribute('aria-controls', overflow.id);
        ['strike', 'code', 'quote', 'h2', 'orderedList', 'codeblock', 'spoiler'].forEach(function (key) {
            var b = makeActionButton(key, true);
            b.addEventListener('click', function () {
                overflow.hidden = true;
                more.setAttribute('aria-expanded', 'false');
            });
            overflow.appendChild(b);
        });
        more.addEventListener('click', function () {
            var open = overflow.hidden;
            overflow.hidden = !open;
            more.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        moreWrap.appendChild(more);
        bar.appendChild(moreWrap);

        ['input', 'keyup', 'mouseup', 'select'].forEach(function (evt) {
            ta.addEventListener(evt, updateState);
        });
        slot.appendChild(bar);
        slot.appendChild(overflow);

        var actionSlot = shellPart(form, '[data-composer-actions-start-slot]');
        if (actionSlot) {
            var formatToggle = document.createElement('button');
            formatToggle.type = 'button';
            formatToggle.className = 'composer-format-toggle';
            formatToggle.textContent = 'Aa';
            formatToggle.setAttribute('aria-label', 'Formatting');
            formatToggle.setAttribute('aria-controls', bar.id);
            var stored = storageRead('rb-composer:format-row');
            var open = stored !== 'closed';
            bar.hidden = !open;
            formatToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            formatToggle.addEventListener('click', function () {
                var nextOpen = bar.hidden;
                bar.hidden = !nextOpen;
                formatToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                storageWrite('rb-composer:format-row', nextOpen ? 'open' : 'closed');
            });
            actionSlot.appendChild(formatToggle);
        }

        document.addEventListener('click', function (event) {
            if (moreWrap.contains(event.target) || overflow.contains(event.target)) { return; }
            overflow.hidden = true;
            more.setAttribute('aria-expanded', 'false');
        });
        overflow.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }
            overflow.hidden = true;
            more.setAttribute('aria-expanded', 'false');
            more.focus();
        });
        updateState();
    }

    // ---- Character counter ------------------------------------------------
    function buildCounter(form, adapter) {
        var ta = adapter.ta;
        var slot = shellPart(form, '[data-composer-counter-slot]');
        if (!slot) { return; }
        var bodyMax = ta.getAttribute('data-body-max') || form.getAttribute('data-body-max') || '';
        var limit = ta.maxLength > 0 ? ta.maxLength : parseInt(bodyMax, 10);
        if (!(limit > 0)) { return; }
        var c = document.createElement('div');
        c.className = 'composer-count';
        c.setAttribute('aria-live', 'polite');
        function update() {
            var active = form._rbComposerAdapter || adapter;
            var markdown = active && typeof active.getMarkdown === 'function' ? active.getMarkdown() : ta.value;
            var n = markdown.length;
            c.textContent = n + ' / ' + limit;
            c.hidden = n < Math.ceil(limit * 0.9);
            c.classList.toggle('over', n > limit);
        }
        ta.addEventListener('input', update);
        update();
        slot.appendChild(c);
    }

    // ---- Live preview (same server pipeline) ------------------------------
    var previewSeq = 0;
    function adapterIsSourceMode(adapter, fallback) {
        if (adapter && typeof adapter.isSourceMode === 'function') {
            try { return !!adapter.isSourceMode(); } catch (e) {}
        }
        return adapter === fallback;
    }
    function buildPreview(form, prefs, adapter, fallback) {
        var actionSlot = shellPart(form, '[data-composer-actions-end-slot]');
        var afterBox = shellPart(form, '[data-composer-after-box]');
        if (!actionSlot || !afterBox) { return; }

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'composer-preview-toggle';
        toggle.textContent = 'Preview';
        toggle.setAttribute('aria-label', 'Preview');
        var pane = document.createElement('div');
        pane.className = 'composer-preview';
        pane.id = 'composer-preview-' + (++previewSeq);
        pane.setAttribute('aria-live', 'polite');
        toggle.setAttribute('aria-controls', pane.id);
        actionSlot.appendChild(toggle);
        afterBox.appendChild(pane);

        var timer = null;
        var requestSeq = 0;
        var touched = false;
        var attached = [];
        var stored = storageRead('rb-composer:preview');
        if (stored !== 'open' && stored !== 'closed') { stored = null; }
        var open = stored === 'open' || (stored === null && prefs.showPreview && adapterIsSourceMode(adapter, fallback));

        function activeAdapter() { return form._rbComposerAdapter || adapter; }
        function renderNow() {
            if (!open) { return; }
            var active = activeAdapter();
            if (!active || typeof active.getMarkdown !== 'function') { return; }
            var seq = ++requestSeq;
            var data = new FormData();
            data.append('_token', tokenField(form));
            data.append('body', active.getMarkdown());
            fetch('/composer/preview', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (seq === requestSeq && open && j && j.ok) { pane.innerHTML = j.html; }
                })
                .catch(function () {});
        }
        function scheduleRender() {
            if (!open) { return; }
            if (timer) { clearTimeout(timer); }
            timer = setTimeout(renderNow, 350);
        }
        function setOpen(nextOpen, persist, fetchOnOpen) {
            open = !!nextOpen;
            pane.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) {
                requestSeq++;
                if (timer) { clearTimeout(timer); timer = null; }
            } else if (fetchOnOpen) {
                renderNow();
            }
            if (persist) { storageWrite('rb-composer:preview', open ? 'open' : 'closed'); }
        }
        function attach(nextAdapter) {
            if (!nextAdapter || typeof nextAdapter.onChange !== 'function' || attached.indexOf(nextAdapter) !== -1) { return; }
            attached.push(nextAdapter);
            nextAdapter.onChange(scheduleRender);
        }
        function reconcile(nextAdapter) {
            attach(nextAdapter);
            if (stored !== null || touched) { return; }
            setOpen(prefs.showPreview && adapterIsSourceMode(nextAdapter, fallback), false, false);
        }

        toggle.addEventListener('click', function () {
            touched = true;
            setOpen(!open, true, !open);
        });
        attach(adapter);
        setOpen(open, false, false);
        form._rbPreviewController = { reconcile: reconcile };
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
    function buildDraftSyncPanel(form) {
        var afterBox = shellPart(form, '[data-composer-after-box]');
        if (!afterBox) { return null; }
        var panel = document.createElement('div');
        panel.className = 'composer-draft-sync';
        panel.hidden = true;
        panel.setAttribute('role', 'status');
        panel.setAttribute('aria-live', 'polite');
        afterBox.appendChild(panel);
        return panel;
    }
    function wireServerDrafts(form, adapter, localKey, context, updateDiscard) {
        var ta = adapter.ta;
        var apiKey = serverDraftKey(context);
        var panel = buildDraftSyncPanel(form);
        var timer = null;
        var revision = 0;
        var paused = false;
        var applying = false;

        function showStatus(text) {
            if (!panel) { return; }
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
                    if (res.status === 422) {
                        var messages = res.json && res.json.messages ? res.json.messages : {};
                        var first = '';
                        for (var key in messages) {
                            if (Object.prototype.hasOwnProperty.call(messages, key) && messages[key]) {
                                first = messages[key];
                                break;
                            }
                        }
                        showStatus(first || 'Saved locally; server draft sync rejected this change.');
                        return;
                    }
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
            adapter.setMarkdown(server.body || '');
            setDraftTitle(form, server.title || '');
            try {
                adapter.getMarkdown() ? localStorage.setItem(localKey, adapter.getMarkdown()) : localStorage.removeItem(localKey);
            } catch (e) {}
            applying = false;
            updateDiscard();
        }
        function renderConflict(server, localBody, localTitle) {
            if (!server) {
                revision = 0;
                return;
            }
            revision = parseInt(server.revision, 10) || revision;
            if (!panel) {
                paused = true;
                return;
            }
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
            var body = adapter.getMarkdown();
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
                if (adapter.getMarkdown() && adapter.getMarkdown() !== (server.body || '')) {
                    renderConflict(server, adapter.getMarkdown(), draftTitle(form, draftLabel(context)));
                    return;
                }
                if (!adapter.getMarkdown() && server.body) {
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
        items.push({ key: key, context: context, user: draftUser(), at: Date.now() });
        writePendingDraftSubmits(items.slice(-20));
    }
    function clearCompletedDraftSubmits() {
        var pending = pendingDraftSubmits();
        if (!pending.length) { return; }
        var currentUser = draftUser();
        var tokenInput = document.querySelector('input[name="_token"]');
        var csrfToken = tokenInput ? tokenInput.value : '';
        var keep = [];

        function discardServerDraftForContext(context) {
            if (!serverDraftsEnabled() || !csrfToken) { return; }
            var data = new FormData();
            data.append('_token', csrfToken);
            fetch('/api/drafts/' + serverDraftKey(context) + '/discard', {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).catch(function () {});
        }

        var forms = document.querySelectorAll('form.composer');
        pending.forEach(function (item) {
            if (!item || !item.key || !item.context) { return; }
            if (item.user && item.user !== currentUser) {
                try { localStorage.removeItem(item.key); } catch (e) {}
                return;
            }
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
            discardServerDraftForContext(item.context);
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
    function buildDiscard(form, adapter, key, discardRemote) {
        var slot = shellPart(form, '[data-composer-draft-slot]');
        if (!slot) { return function () {}; }
        slot.appendChild(document.createTextNode('Draft saved · '));
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'linkbtn composer-discard';
        b.textContent = 'Discard';
        b.setAttribute('aria-label', 'Discard draft');
        slot.appendChild(b);
        function update() {
            var hasSaved = false;
            try { hasSaved = !!localStorage.getItem(key); } catch (e) {}
            slot.hidden = !hasSaved && !adapter.getMarkdown();
        }
        b.addEventListener('click', function () {
            try { localStorage.removeItem(key); } catch (e) {}
            adapter.setMarkdown('');
            if (typeof discardRemote === 'function') { discardRemote(); }
            update();
        });
        update();
        return update;
    }
    function wireDrafts(form, adapter) {
        var ta = adapter.ta;
        var who = draftUser();
        var context = draftContext(form);
        migrateAnonDrafts(who);
        var key = draftKeyFor(who, context);
        try {
            var saved = localStorage.getItem(key);
            if (saved && !adapter.getMarkdown()) { adapter.setMarkdown(saved); }
        } catch (e) {}
        var remoteDiscard = null;
        var updateDiscard = buildDiscard(form, adapter, key, function () {
            if (remoteDiscard) { remoteDiscard(); }
        });
        var serverDrafts = serverDraftsEnabled() ? wireServerDrafts(form, adapter, key, context, updateDiscard) : null;
        if (serverDrafts) { remoteDiscard = serverDrafts.discard; }
        adapter.onChange(function (markdown) {
            try { markdown ? localStorage.setItem(key, markdown) : localStorage.removeItem(key); } catch (e) {}
            if (serverDrafts) { serverDrafts.input(); }
            updateDiscard();
        });
        form.addEventListener('submit', function () {
            // Do not clear immediately: a dropped connection can leave the user on
            // the same page. The next successfully loaded page clears this context
            // before an empty composer can repopulate from localStorage.
            rememberPendingDraftSubmit(key, context);
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
        var host = document.querySelector('[data-local-drafts-list]');
        var embedded = !!host;
        if (!host) {
            host = document.querySelector('[data-drafts-list]');
            if (host && host.hasAttribute('data-server-drafts')) { return; }
        }
        if (!host || !draftsEnabled()) { return; }
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
            empty.textContent = embedded ? 'No browser-local drafts in this browser.' : 'No saved drafts in this browser.';
            host.appendChild(empty);
            return;
        }
        var list = host;
        if (embedded) {
            list = document.createElement('ul');
            list.className = 'report-list';
            host.appendChild(list);
        }
        drafts.forEach(function (d) {
            var card = document.createElement(embedded ? 'li' : 'article');
            card.className = embedded ? 'report-row' : 'card';
            card.setAttribute('data-local-draft-row', '1');
            if (embedded) {
                var head = document.createElement('div');
                head.className = 'report-head';
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.textContent = 'Local';
                var context = document.createElement('span');
                context.className = 'muted';
                context.textContent = d.context;
                head.appendChild(badge);
                head.appendChild(context);
                card.appendChild(head);
            }
            var h = document.createElement('h2');
            h.textContent = draftLabel(d.context);
            var p = null;
            if (!embedded) {
                p = document.createElement('p');
                p.className = 'muted';
                p.textContent = d.context;
            }
            var pre = document.createElement(embedded ? 'blockquote' : 'pre');
            pre.className = embedded ? 'report-excerpt' : 'draft-preview';
            pre.textContent = d.body.length > (embedded ? 240 : 500)
                ? d.body.slice(0, embedded ? 240 : 500) + '...'
                : d.body;
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
            discard.textContent = embedded ? 'Remove local copy' : 'Discard';
            discard.addEventListener('click', function () {
                try { localStorage.removeItem(d.key); } catch (e) {}
                card.remove();
                if (!host.querySelector('[data-local-draft-row]')) { renderDraftsPage(); }
            });
            actions.appendChild(discard);
            card.appendChild(h);
            if (p) { card.appendChild(p); }
            card.appendChild(pre);
            card.appendChild(actions);
            list.appendChild(card);
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
    function uploadTray(form) {
        return shellPart(form, '[data-composer-upload-tray]');
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
    function uploadCard(form, adapter, file, placeholder) {
        var ta = adapter.ta;
        var tray = uploadTray(form);
        var card = document.createElement('div');
        card.className = 'composer-upload-chip composer-upload-card is-uploading';
        var preview = document.createElement('img');
        preview.className = 'composer-upload-thumb';
        preview.alt = '';
        preview.hidden = true;
        var meta = document.createElement('div');
        meta.className = 'composer-upload-meta';
        var name = document.createElement('div');
        name.className = 'composer-upload-name';
        name.textContent = file && file.name ? file.name : 'image';
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
        meta.appendChild(name);
        meta.appendChild(status);
        meta.appendChild(progress);
        meta.appendChild(alt);
        meta.appendChild(actions);
        card.appendChild(preview);
        card.appendChild(meta);
        tray.appendChild(card);
        card._rbMarkdown = '';
        remove.addEventListener('click', function () {
            adapter.replacePendingUpload(card._rbMarkdown || placeholder, '');
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
            if (adapter.replacePendingUpload(card._rbMarkdown, next)) {
                card._rbMarkdown = next;
                preview.alt = alt.value;
            }
        });
        return {
            progress: function (pct) { progress.value = Math.max(0, Math.min(100, pct)); },
            complete: function (json) {
                var markdown = imageMarkdown(json.url, '');
                adapter.replacePendingUpload(placeholder, markdown);
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
                adapter.replacePendingUpload(placeholder, '');
                progress.remove();
                alt.disabled = true;
                status.textContent = message || 'Upload failed.';
                card.classList.remove('is-uploading');
                card.classList.add('is-failed');
            }
        };
    }
    function uploadImage(form, adapter, file) {
        var data = new FormData();
        data.append('_token', tokenField(form));
        data.append('image', file);
        data.append('purpose', uploadPurpose(form));
        // Unique per upload so several images pasted/dropped at once each resolve
        // into their OWN marker — String.replace(str) only swaps the first match.
        var token = 'rbup-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
        var placeholder = '![uploading…](' + token + ')';
        adapter.insertMarkdown(placeholder);
        var card = uploadCard(form, adapter, file, placeholder);
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
    function wireUploads(form, adapter) {
        if (!uploadTray(form)) { return; }
        var ta = adapter.ta;
        var targets = adapterEventTargets(adapter, ta, 'uploadTargets');
        function queueImageFiles(files) {
            for (var i = 0; i < files.length; i++) {
                if (files[i] && files[i].type && files[i].type.indexOf('image/') === 0) {
                    uploadImage(form, adapter, files[i]);
                }
            }
        }
        function onPaste(e) {
            var items = (e.clipboardData || {}).items || [];
            var files = [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image/') === 0) {
                    var file = items[i].getAsFile();
                    if (file) { files.push(file); }
                }
            }
            if (files.length) {
                e.preventDefault();
                queueImageFiles(files);
            }
        }
        function onDrop(e) {
            var files = (e.dataTransfer || {}).files || [];
            if (!files.length) { return; }
            e.preventDefault();
            queueImageFiles(files);
        }
        targets.forEach(function (target) {
            target.addEventListener('paste', onPaste, target !== ta);
            target.addEventListener('dragover', function (e) { e.preventDefault(); }, target !== ta);
            target.addEventListener('drop', onDrop, target !== ta);
        });

        var actionSlot = shellPart(form, '[data-composer-actions-start-slot]');
        if (!actionSlot) { return; }
        var input = document.createElement('input');
        input.type = 'file';
        input.hidden = true;
        input.multiple = true;
        input.accept = '.png,.jpg,.jpeg,.webp,.gif';
        input.setAttribute('data-composer-upload-input', '');
        var attach = document.createElement('button');
        attach.type = 'button';
        attach.className = 'composer-attach-toggle';
        attach.textContent = '＋';
        attach.setAttribute('aria-label', 'Attach images');
        attach.setAttribute('title', 'Attach images');
        attach.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            queueImageFiles(input.files || []);
            input.value = '';
        });
        actionSlot.insertBefore(input, actionSlot.firstChild);
        actionSlot.insertBefore(attach, actionSlot.firstChild);
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
    function currentSlashState(adapter, ta) {
        if (adapter && typeof adapter.slashState === 'function') {
            try {
                return adapter.slashState();
            } catch (e) {
                return null;
            }
        }
        return slashState(ta);
    }
    function replaceSlashSelection(adapter, ta, state, markdown) {
        if (adapter && typeof adapter.replaceSlashSelection === 'function') {
            try {
                adapter.replaceSlashSelection(state, markdown);
                return;
            } catch (e) {}
        }
        replaceRange(ta, state.start, state.end, markdown);
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
    // A combobox needs an accessible name. Most composer textareas rely on a
    // placeholder (which is not a name), so give them a label before we upgrade
    // them — only when one is missing, never overriding an explicit label.
    function ensureAccessibleName(el) {
        var label = el.getAttribute('aria-label');
        if (label && label.trim() !== '') { return; }
        if (el.getAttribute('aria-labelledby')) { return; }
        var id = el.getAttribute('id');
        if (id) {
            var selector = window.CSS && CSS.escape ? CSS.escape(id) : id;
            try { if (document.querySelector('label[for="' + selector + '"]')) { return; } } catch (e) {}
        }
        if (el.closest && el.closest('label')) { return; }
        var placeholder = (el.getAttribute('placeholder') || '').trim();
        el.setAttribute('aria-label', placeholder !== '' ? placeholder : 'Message composer');
    }
    var slashMenuSeq = 0;
    // Slash inserts + GIPHY are surfaced as an APG combobox: the textarea is the
    // combobox, the popup is a listbox of role=option items, selection is tracked
    // with aria-activedescendant (focus stays in the textarea so typing keeps
    // filtering), and Arrow/Home/End/Enter/Escape drive it from the keyboard.
    function wireSlashMenu(form, adapter) {
        var ta = adapter.ta;
        var box = shellPart(form, '.composer-box');
        if (!box) { return; }
        var targets = adapterEventTargets(adapter, ta, 'slashTargets');
        var menu = document.createElement('div');
        var menuId = 'composer-slash-menu-' + (++slashMenuSeq);
        var optionSeq = 0;
        menu.id = menuId;
        menu.className = 'composer-slash-menu composer-suggestion-popover';
        menu.hidden = true;
        box.appendChild(menu);

        var config = null;
        var ready = false;
        var activeState = null;
        var options = [];
        var activeIndex = -1;
        var lastRenderKey = null;

        function comboboxReady() { return ta.getAttribute('role') === 'combobox'; }
        function setExpanded(open) {
            if (!comboboxReady()) { return; }
            if (open) { ta.setAttribute('aria-controls', menuId); }
            ta.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) { ta.removeAttribute('aria-activedescendant'); }
        }
        function openMenu() { menu.hidden = false; setExpanded(true); }
        function hide() {
            activeState = null;
            options = [];
            activeIndex = -1;
            lastRenderKey = null;
            menu.hidden = true;
            menu.innerHTML = '';
            setExpanded(false);
        }
        function highlight(index) {
            if (!options.length) {
                activeIndex = -1;
                ta.removeAttribute('aria-activedescendant');
                return;
            }
            if (index < 0) { index = options.length - 1; }
            if (index >= options.length) { index = 0; }
            activeIndex = index;
            for (var i = 0; i < options.length; i++) {
                options[i].setAttribute('aria-selected', i === index ? 'true' : 'false');
            }
            var active = options[activeIndex];
            if (comboboxReady()) { ta.setAttribute('aria-activedescendant', active.id); }
            active.scrollIntoView({ block: 'nearest' });
        }
        function collectOptions() {
            options = [];
            var nodes = menu.querySelectorAll('.composer-slash-command, .composer-slash-gif');
            for (var i = 0; i < nodes.length; i++) {
                var node = nodes[i];
                if (node.getAttribute('aria-disabled') === 'true') { continue; }
                if (!node.id) { node.id = menuId + '-opt-' + (++optionSeq); }
                node.setAttribute('role', 'option');
                node.setAttribute('tabindex', '-1');
                node.setAttribute('aria-selected', 'false');
                options.push(node);
            }
            highlight(options.length ? 0 : -1);
        }
        function showStatus(text) {
            // A visible listbox needs at least one option child (aria-required-
            // children); render transient GIPHY status as a single disabled,
            // non-selectable option so the combobox stays valid.
            menu.innerHTML = '';
            var status = document.createElement('div');
            status.className = 'composer-slash-status';
            status.setAttribute('role', 'option');
            status.setAttribute('aria-disabled', 'true');
            status.setAttribute('aria-selected', 'false');
            status.textContent = text;
            menu.appendChild(status);
            options = [];
            activeIndex = -1;
            ta.removeAttribute('aria-activedescendant');
            openMenu();
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
                    var state = activeState || currentSlashState(adapter, ta);
                    if (!state) { hide(); return; }
                    if (command.type === 'giphy') {
                        searchGiphy(state, giphySearchTerm(state.query));
                        return;
                    }
                    replaceSlashSelection(adapter, ta, state, command.body);
                    hide();
                });
                menu.appendChild(b);
            });
            openMenu();
            collectOptions();
        }
        function render() {
            if (!ready || config === null) { return; }
            var state = currentSlashState(adapter, ta);
            if (!state) { hide(); return; }
            activeState = state;
            var commands = slashCommands(config, state.query);
            if (commands.length === 0) { hide(); return; }
            var key = state.query + '|' + commands.map(function (c) { return c.key; }).join(',');
            // Preserve the active option while only the caret moves (Arrow keys
            // also fire keyup); rebuild only when the slash query itself changes.
            if (key === lastRenderKey && !menu.hidden) { return; }
            lastRenderKey = key;
            renderButtons(commands);
        }
        function searchGiphy(state, term) {
            lastRenderKey = state.query + '|giphy';
            if (!term) { showStatus('Type a search after /gif.'); return; }
            showStatus('Searching GIPHY...');
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
                if (items.length === 0) { showStatus('No GIFs found.'); return; }
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
                        replaceSlashSelection(adapter, ta, state, imageMarkdown(mediaUrl, title));
                        hide();
                    });
                    menu.appendChild(b);
                });
                if (!menu.childNodes.length) { showStatus('No GIFs found.'); return; }
                lastRenderKey = state.query + '|giphy';
                openMenu();
                collectOptions();
            }).catch(function () {
                showStatus('GIPHY search is unavailable.');
            });
        }

        loadSlashConfig().then(function (j) {
            config = j;
            ready = true;
            if (config === null) { return; }
            // Upgrade to a combobox only when the picker can actually open (flag on
            // + provider key configured), so composers elsewhere are unchanged.
            ensureAccessibleName(ta);
            menu.setAttribute('role', 'listbox');
            menu.setAttribute('aria-label', 'Composer insert commands');
            ta.setAttribute('role', 'combobox');
            ta.setAttribute('aria-expanded', 'false');
            ta.setAttribute('aria-controls', menuId);
            ta.setAttribute('aria-haspopup', 'listbox');
            ta.setAttribute('aria-autocomplete', 'list');
            render();
        });
        targets.forEach(function (target) {
            target.addEventListener('input', render);
            target.addEventListener('keyup', function (e) {
                // Navigation/activation keys are handled on keydown; their keyup must
                // not rebuild the menu and reset the active option.
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter'
                    || e.key === 'Home' || e.key === 'End' || e.key === 'Escape') { return; }
                render();
            });
            target.addEventListener('click', render);
            target.addEventListener('keydown', function (e) {
                if (menu.hidden) {
                    if (e.key === 'ArrowDown' && !e.altKey && !e.ctrlKey && !e.metaKey && ready && config !== null) {
                        if (currentSlashState(adapter, ta)) {
                            lastRenderKey = null;
                            render();
                            if (!menu.hidden) { e.preventDefault(); }
                        }
                    }
                    return;
                }
                switch (e.key) {
                    case 'ArrowDown': e.preventDefault(); highlight(activeIndex + 1); break;
                    case 'ArrowUp': e.preventDefault(); highlight(activeIndex - 1); break;
                    case 'Home': if (options.length) { e.preventDefault(); highlight(0); } break;
                    case 'End': if (options.length) { e.preventDefault(); highlight(options.length - 1); } break;
                    case 'Enter':
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (activeIndex >= 0 && options.length) {
                            options[activeIndex].click();
                        }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        hide();
                        break;
                    case 'Tab': hide(); break;
                    default: break;
                }
            }, target !== ta);
        });
        document.addEventListener('click', function (e) {
            if (menu.contains(e.target)) { return; }
            for (var i = 0; i < targets.length; i++) {
                if (e.target === targets[i] || (targets[i].contains && targets[i].contains(e.target))) { return; }
            }
            hide();
        });
    }

    var referenceMenuSeq = 0;
    function referenceState(ta) {
        if (ta.selectionStart !== ta.selectionEnd) { return null; }
        var pos = ta.selectionStart || 0;
        var before = ta.value.slice(0, pos);
        var m = before.match(/(^|[\s(])([@#:])([A-Za-z0-9_+\-]{1,80})$/);
        if (!m) { return null; }
        var trigger = m[2];
        var query = m[3];
        var line = before.slice(before.lastIndexOf('\n') + 1);
        if (trigger === '#' && /^\s*#{1,3}\s?$/.test(line)) { return null; }
        if (inFence(ta)) { return null; }
        if (textareaInlineCodeOpen(ta)) { return null; }
        return {
            trigger: trigger,
            query: query,
            start: pos - trigger.length - query.length,
            end: pos
        };
    }

    function suggestionUrl(form, state) {
        return '/composer/suggest'
            + '?trigger=' + encodeURIComponent(state.trigger)
            + '&q=' + encodeURIComponent(state.query)
            + '&context=' + encodeURIComponent(form.getAttribute('data-composer-context') || '')
            + '&target_id=' + encodeURIComponent(form.getAttribute('data-composer-target-id') || '0');
    }

    function referenceTargets(adapter, ta) {
        if (typeof adapter.referenceTargets === 'function') {
            try {
                var targets = adapter.referenceTargets();
                if (targets && typeof targets.length === 'number') {
                    var out = [];
                    for (var i = 0; i < targets.length; i++) {
                        if (targets[i] && typeof targets[i].addEventListener === 'function') {
                            out.push(targets[i]);
                        }
                    }
                    if (out.length) { return out; }
                }
            } catch (e) {}
        }
        return [ta];
    }

    function currentReferenceState(adapter, ta) {
        if (typeof adapter.referenceState === 'function') {
            try {
                return adapter.referenceState();
            } catch (e) {
                return null;
            }
        }
        return referenceState(ta);
    }

    function replaceReferenceSelection(adapter, ta, state, item, markdown) {
        if (typeof adapter.replaceReferenceSelection === 'function') {
            try {
                adapter.replaceReferenceSelection(state, item);
                return;
            } catch (e) {}
        }
        replaceRange(ta, state.start, state.end, markdown);
    }

    function wireReferencePickers(form, adapter) {
        var ta = adapter.ta;
        var box = shellPart(form, '.composer-box');
        if (!box) { return; }
        var targets = referenceTargets(adapter, ta);
        var menu = document.createElement('div');
        var menuId = 'composer-reference-menu-' + (++referenceMenuSeq);
        var optionSeq = 0;
        var options = [];
        var activeIndex = -1;
        var activeState = null;
        var lastRenderKey = null;
        var requestSeq = 0;

        menu.id = menuId;
        menu.className = 'composer-reference-menu composer-suggestion-popover';
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', 'Composer references');
        box.appendChild(menu);

        ensureAccessibleName(ta);
        ta.setAttribute('role', 'combobox');
        if (!ta.hasAttribute('aria-expanded')) { ta.setAttribute('aria-expanded', 'false'); }
        if (!ta.hasAttribute('aria-controls')) { ta.setAttribute('aria-controls', menuId); }
        ta.setAttribute('aria-haspopup', 'listbox');
        ta.setAttribute('aria-autocomplete', 'list');

        function ownsCombobox() { return ta.getAttribute('aria-controls') === menuId; }
        function setExpanded(open) {
            ta.setAttribute('role', 'combobox');
            if (open) { ta.setAttribute('aria-controls', menuId); }
            ta.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open && ownsCombobox()) { ta.removeAttribute('aria-activedescendant'); }
        }
        function openMenu() {
            menu.hidden = false;
            setExpanded(true);
        }
        function hide() {
            var wasOpen = !menu.hidden || (ownsCombobox() && ta.getAttribute('aria-expanded') === 'true');
            requestSeq++;
            activeState = null;
            options = [];
            activeIndex = -1;
            lastRenderKey = null;
            menu.hidden = true;
            menu.innerHTML = '';
            if (wasOpen) { setExpanded(false); }
        }
        function highlight(index) {
            if (!options.length) {
                activeIndex = -1;
                if (ownsCombobox()) { ta.removeAttribute('aria-activedescendant'); }
                return;
            }
            if (index < 0) { index = options.length - 1; }
            if (index >= options.length) { index = 0; }
            activeIndex = index;
            for (var i = 0; i < options.length; i++) {
                options[i].setAttribute('aria-selected', i === index ? 'true' : 'false');
            }
            var active = options[activeIndex];
            ta.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({ block: 'nearest' });
        }
        function selectItem(item) {
            var state = activeState || currentReferenceState(adapter, ta);
            if (!state) { hide(); return; }
            var markdown = item.markdown || item.token || item.label || '';
            if (markdown === '') { hide(); return; }
            replaceReferenceSelection(adapter, ta, state, item, markdown);
            hide();
        }
        function renderItems(items, state, key) {
            menu.innerHTML = '';
            options = [];
            if (!items || !items.length) { hide(); return; }
            activeState = state;
            lastRenderKey = key;
            items.forEach(function (item) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'composer-reference-option';
                if (item.type === 'emoji' || item.type === 'custom_emoji') {
                    b.classList.add('is-emoji');
                    b.setAttribute('aria-label', item.label || item.token || 'Emoji');
                }
                b.setAttribute('role', 'option');
                b.setAttribute('tabindex', '-1');
                b.setAttribute('aria-selected', 'false');
                b.id = menuId + '-opt-' + (++optionSeq);

                var badge = document.createElement('span');
                badge.className = 'badge';
                if (item.type === 'emoji') {
                    badge.textContent = item.markdown || item.token || '😊';
                } else if (item.type === 'custom_emoji' && emojiImagePath(item.url)) {
                    var emojiImage = document.createElement('img');
                    emojiImage.src = emojiImagePath(item.url);
                    emojiImage.alt = '';
                    emojiImage.setAttribute('aria-hidden', 'true');
                    badge.appendChild(emojiImage);
                } else {
                    badge.textContent = item.type || state.trigger;
                }
                var label = document.createElement('span');
                label.textContent = item.label || item.token || item.url || 'Suggestion';
                var meta = document.createElement('span');
                meta.className = 'composer-reference-meta';
                meta.textContent = item.meta || item.group || item.url || '';

                b.appendChild(badge);
                b.appendChild(label);
                b.appendChild(meta);
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function (e) {
                    e.stopPropagation();
                    selectItem(item);
                });
                menu.appendChild(b);
                options.push(b);
            });
            openMenu();
            highlight(0);
        }
        function render() {
            var state = currentReferenceState(adapter, ta);
            if (!state) { hide(); return; }
            if (state.trigger === ':' && state.query.length < 2) { hide(); return; }
            var key = state.trigger + '|' + state.query + '|'
                + (form.getAttribute('data-composer-context') || '') + '|'
                + (form.getAttribute('data-composer-target-id') || '0');
            activeState = state;
            if (key === lastRenderKey && !menu.hidden) { return; }
            var seq = ++requestSeq;
            fetch(suggestionUrl(form, state), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.ok ? r.json() : null;
            }).then(function (j) {
                if (seq !== requestSeq) { return; }
                if (!j || !j.ok || !Array.isArray(j.items)) { hide(); return; }
                renderItems(j.items, state, key);
            }).catch(function () {
                if (seq === requestSeq) { hide(); }
            });
        }

        targets.forEach(function (target) {
            target.addEventListener('input', render);
            target.addEventListener('keyup', function (e) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter'
                    || e.key === 'Tab' || e.key === 'Escape') { return; }
                render();
            });
            target.addEventListener('click', render);
            target.addEventListener('keydown', function (e) {
                if (menu.hidden) {
                    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && currentReferenceState(adapter, ta)) {
                        lastRenderKey = null;
                        render();
                        if (!menu.hidden) { e.preventDefault(); }
                    }
                    return;
                }
                switch (e.key) {
                    case 'ArrowDown': e.preventDefault(); highlight(activeIndex + 1); break;
                    case 'ArrowUp': e.preventDefault(); highlight(activeIndex - 1); break;
                    case 'Enter':
                    case 'Tab':
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (activeIndex >= 0 && options.length) { options[activeIndex].click(); }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        hide();
                        break;
                    default: break;
                }
            }, target !== ta);
        });
        document.addEventListener('click', function (e) {
            if (menu.contains(e.target)) { return; }
            for (var i = 0; i < targets.length; i++) {
                if (e.target === targets[i] || (targets[i].contains && targets[i].contains(e.target))) { return; }
            }
            hide();
        });
    }

    // ---- Server-backed emoji picker --------------------------------------
    var emojiPickerSeq = 0;
    var EMOJI_RECENTS_KEY = 'rb-composer:emoji-recents';

    function emojiImagePath(value) {
        try {
            var url = new URL(String(value || ''), window.location.origin);
            if (url.origin !== window.location.origin) { return ''; }
            var path = url.pathname;
            return /^\/emoji\/[A-Za-z0-9_.-]+\.(?:png|webp)$/.test(path) || /^\/media\/\d+$/.test(path)
                ? path : '';
        } catch (e) {
            return '';
        }
    }

    function normalizeEmojiItem(item) {
        if (!item || (item.type !== 'emoji' && item.type !== 'custom_emoji')) { return null; }
        var token = String(item.token || '');
        var label = String(item.label || '').trim();
        var markdown = String(item.markdown || '');
        if (!/^:[a-z0-9_+\-]{2,40}:$/.test(token) || label === '' || markdown === '') { return null; }
        var url = item.type === 'custom_emoji' ? emojiImagePath(item.url) : '';
        if (item.type === 'custom_emoji' && url === '') { return null; }
        return {
            type: item.type,
            label: label,
            token: token,
            markdown: markdown,
            url: url,
            group: String(item.group || (item.type === 'custom_emoji' ? 'Custom' : 'Emoji'))
        };
    }

    function readEmojiRecents() {
        var raw = storageRead(EMOJI_RECENTS_KEY);
        if (!raw) { return []; }
        try {
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) { return []; }
            var out = [];
            var seen = {};
            parsed.forEach(function (item) {
                var normalized = normalizeEmojiItem(item);
                if (!normalized || out.length >= 24) { return; }
                var key = normalized.type + ':' + normalized.token;
                if (seen[key]) { return; }
                seen[key] = true;
                out.push(normalized);
            });
            return out;
        } catch (e) {
            return [];
        }
    }

    function rememberEmoji(item) {
        var normalized = normalizeEmojiItem(item);
        if (!normalized) { return; }
        var key = normalized.type + ':' + normalized.token;
        var recents = readEmojiRecents().filter(function (recent) {
            return recent.type + ':' + recent.token !== key;
        });
        recents.unshift(normalized);
        storageWrite(EMOJI_RECENTS_KEY, JSON.stringify(recents.slice(0, 24)));
    }

    function buildEmojiPicker(form, adapter) {
        var actionSlot = shellPart(form, '[data-composer-actions-start-slot]');
        var box = shellPart(form, '.composer-box');
        if (!actionSlot || !box) { return; }

        var id = ++emojiPickerSeq;
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'composer-emoji-toggle';
        trigger.textContent = '😊';
        trigger.setAttribute('aria-label', 'Emoji');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');

        var dialog = document.createElement('div');
        dialog.className = 'composer-emoji-dialog';
        dialog.id = 'composer-emoji-dialog-' + id;
        dialog.hidden = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', dialog.id + '-title');
        trigger.setAttribute('aria-controls', dialog.id);

        var header = document.createElement('div');
        header.className = 'composer-emoji-header';
        var title = document.createElement('h2');
        title.className = 'composer-emoji-title';
        title.id = dialog.id + '-title';
        title.textContent = 'Emoji';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'composer-emoji-close';
        close.setAttribute('aria-label', 'Close emoji');
        close.textContent = '×';
        header.appendChild(title);
        header.appendChild(close);

        var searchLabel = document.createElement('label');
        searchLabel.className = 'sr-only';
        searchLabel.setAttribute('for', dialog.id + '-search');
        searchLabel.textContent = 'Search emoji';
        var search = document.createElement('input');
        search.type = 'search';
        search.id = dialog.id + '-search';
        search.className = 'composer-emoji-search';
        search.placeholder = 'Search emoji';

        var status = document.createElement('p');
        status.className = 'composer-emoji-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        var groups = document.createElement('div');
        groups.className = 'composer-emoji-groups';

        dialog.appendChild(header);
        dialog.appendChild(searchLabel);
        dialog.appendChild(search);
        dialog.appendChild(status);
        dialog.appendChild(groups);
        box.appendChild(dialog);
        actionSlot.appendChild(trigger);

        var rememberedSelection = null;
        var fullCatalog = null;
        var requestSeq = 0;
        var searchTimer = null;

        function activeAdapter() { return form._rbComposerAdapter || adapter; }
        function clearGroups() {
            while (groups.firstChild) { groups.removeChild(groups.firstChild); }
        }
        function closeDialog(destination) {
            if (searchTimer !== null) {
                window.clearTimeout(searchTimer);
                searchTimer = null;
            }
            requestSeq++;
            dialog.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (destination === 'trigger') {
                trigger.focus();
            } else if (destination === 'editor') {
                var active = activeAdapter();
                if (active && typeof active.focus === 'function') { active.focus(); }
            }
        }
        function focusableDialogItems() {
            return Array.prototype.slice.call(dialog.querySelectorAll(
                'button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([tabindex="-1"])'
            )).filter(function (element) { return element.offsetParent !== null; });
        }
        function activate(item) {
            var normalized = normalizeEmojiItem(item);
            if (!normalized || !rememberedSelection) { closeDialog('editor'); return; }
            var active = activeAdapter();
            if (active && typeof active.replaceRememberedSelection === 'function') {
                active.replaceRememberedSelection(rememberedSelection, normalized.markdown);
            } else if (active && typeof active.insertMarkdown === 'function') {
                active.insertMarkdown(normalized.markdown);
            }
            rememberEmoji(normalized);
            closeDialog('editor');
        }
        function moveCell(cell, key) {
            var grid = cell.closest('[role="grid"]');
            if (!grid) { return; }
            var cells = Array.prototype.slice.call(grid.querySelectorAll('[role="gridcell"]'));
            var index = cells.indexOf(cell);
            if (index < 0) { return; }
            var row = cell.parentElement;
            var columns = 1;
            if (row) {
                var template = window.getComputedStyle(row).gridTemplateColumns.trim();
                if (template) { columns = Math.max(1, template.split(/\s+/).length); }
            }
            var next = index;
            if (key === 'ArrowLeft') { next = Math.max(0, index - 1); }
            if (key === 'ArrowRight') { next = Math.min(cells.length - 1, index + 1); }
            if (key === 'ArrowUp') { next = Math.max(0, index - columns); }
            if (key === 'ArrowDown') { next = Math.min(cells.length - 1, index + columns); }
            if (next === index) { return; }
            cells.forEach(function (candidate) { candidate.setAttribute('tabindex', '-1'); });
            cells[next].setAttribute('tabindex', '0');
            cells[next].focus();
        }
        function appendGroup(name, items) {
            if (!items.length) { return; }
            var section = document.createElement('section');
            section.className = 'composer-emoji-group';
            var heading = document.createElement('h3');
            heading.className = 'composer-emoji-group-title';
            heading.id = dialog.id + '-group-' + groups.children.length;
            heading.textContent = name;
            var grid = document.createElement('div');
            grid.className = 'composer-emoji-grid';
            grid.setAttribute('role', 'grid');
            grid.setAttribute('aria-labelledby', heading.id);
            var row = document.createElement('div');
            row.className = 'composer-emoji-grid-cells';
            row.setAttribute('role', 'row');
            items.forEach(function (item, index) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'composer-emoji-cell';
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute('aria-label', item.label);
                cell.setAttribute('title', item.label + ' ' + item.token);
                cell.setAttribute('tabindex', index === 0 ? '0' : '-1');
                if (item.type === 'custom_emoji') {
                    var image = document.createElement('img');
                    image.src = item.url;
                    image.alt = '';
                    image.setAttribute('aria-hidden', 'true');
                    cell.appendChild(image);
                } else {
                    cell.textContent = item.markdown;
                }
                cell.addEventListener('click', function () { activate(item); });
                cell.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                        event.preventDefault();
                        event.stopPropagation();
                        activate(item);
                        return;
                    }
                    if (/^Arrow(?:Left|Right|Up|Down)$/.test(event.key)) {
                        event.preventDefault();
                        moveCell(cell, event.key);
                    }
                });
                row.appendChild(cell);
            });
            grid.appendChild(row);
            section.appendChild(heading);
            section.appendChild(grid);
            groups.appendChild(section);
        }
        function renderItems(rawItems, includeRecents) {
            clearGroups();
            var items = [];
            (rawItems || []).forEach(function (item) {
                var normalized = normalizeEmojiItem(item);
                if (normalized) { items.push(normalized); }
            });
            if (includeRecents) { appendGroup('Recent', readEmojiRecents()); }
            var names = [];
            var grouped = {};
            items.forEach(function (item) {
                var name = item.group || 'Emoji';
                if (!Object.prototype.hasOwnProperty.call(grouped, name)) {
                    grouped[name] = [];
                    names.push(name);
                }
                grouped[name].push(item);
            });
            names.forEach(function (name) { appendGroup(name, grouped[name]); });
            status.textContent = groups.children.length ? '' : 'No emoji found.';
        }
        function requestItems(query) {
            if (query === '' && fullCatalog !== null) {
                renderItems(fullCatalog, true);
                return;
            }
            var seq = ++requestSeq;
            status.textContent = 'Loading emoji…';
            clearGroups();
            fetch(suggestionUrl(form, { trigger: ':', query: query }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).then(function (json) {
                if (seq !== requestSeq || dialog.hidden) { return; }
                if (!json || !json.ok || !Array.isArray(json.items)) {
                    closeDialog('trigger');
                    return;
                }
                if (query === '') { fullCatalog = json.items; }
                renderItems(json.items, query === '');
            }).catch(function () {
                if (seq === requestSeq) { closeDialog('trigger'); }
            });
        }
        function openDialog() {
            var active = activeAdapter();
            rememberedSelection = active && typeof active.rememberSelection === 'function'
                ? active.rememberSelection() : { start: adapter.ta.selectionStart || 0, end: adapter.ta.selectionEnd || 0 };
            var boxRect = box.getBoundingClientRect();
            dialog.classList.toggle('is-below', window.innerWidth > 640
                && (window.innerHeight - boxRect.bottom) > boxRect.top);
            dialog.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            requestItems('');
            search.focus();
        }

        trigger.addEventListener('click', function () {
            if (dialog.hidden) { openDialog(); }
            else { closeDialog('trigger'); }
        });
        close.addEventListener('click', function () { closeDialog('trigger'); });
        search.addEventListener('input', function () {
            if (searchTimer !== null) { window.clearTimeout(searchTimer); }
            searchTimer = window.setTimeout(function () {
                searchTimer = null;
                requestItems(search.value.trim());
            }, 200);
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeDialog('trigger');
                return;
            }
            if (event.key !== 'Tab') { return; }
            var focusable = focusableDialogItems();
            if (!focusable.length) { event.preventDefault(); return; }
            var index = focusable.indexOf(document.activeElement);
            var next = event.shiftKey ? index - 1 : index + 1;
            if (index < 0) { next = event.shiftKey ? focusable.length - 1 : 0; }
            if (next < 0) { next = focusable.length - 1; }
            if (next >= focusable.length) { next = 0; }
            event.preventDefault();
            focusable[next].focus();
        });
        document.addEventListener('mousedown', function (event) {
            if (dialog.hidden || dialog.contains(event.target) || trigger.contains(event.target)) { return; }
            closeDialog('trigger');
        });
    }

    // ---- Context-aware Enter-to-send + block continuation (P3-01) ---------
    function textareaInlineCodeOpen(ta) {
        var pos = ta.selectionStart || 0;
        var lineStart = ta.value.lastIndexOf('\n', pos - 1) + 1;
        var before = ta.value.slice(lineStart, pos);
        if (/^\s*```/.test(before)) { return false; }
        var unescaped = before.replace(/\\./g, '');
        return ((unescaped.match(/`/g) || []).length % 2) === 1;
    }
    function textareaEnterShouldSubmit(ta) {
        var line = currentLine(ta);
        if (/^\s*(?:[-*+]|\d+[.)])\s/.test(line)) { return false; }
        if (/^\s*>\s?/.test(line)) { return false; }
        if (inFence(ta)) { return false; }
        if (textareaInlineCodeOpen(ta)) { return false; }
        return true;
    }

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
    function continueQuote(ta) {
        if (ta.selectionStart !== ta.selectionEnd) { return false; }
        var v = ta.value, pos = ta.selectionStart;
        var lineStart = v.lastIndexOf('\n', pos - 1) + 1;
        var line = v.slice(lineStart, pos);
        var match = line.match(/^(\s*)>\s?(.*)$/);
        if (!match) { return false; }
        if (!match[2] || !match[2].trim()) {
            ta.value = v.slice(0, lineStart) + v.slice(pos);
            ta.selectionStart = ta.selectionEnd = lineStart;
        } else {
            var next = '\n' + match[1] + '> ';
            ta.value = v.slice(0, pos) + next + v.slice(pos);
            ta.selectionStart = ta.selectionEnd = pos + next.length;
        }
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function continueEditorialBlock(ta) {
        return continueList(ta) || continueQuote(ta);
    }
    function coarsePointer() {
        try { return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches); } catch (e) { return false; }
    }
    function requestComposerSubmit(form) {
        if (form._rbSubmitting) { return false; }
        var send = shellPart(form, '.composer-send');
        if (send && send.disabled) { return false; }
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
        else if (send) { send.click(); }
        else { form.submit(); }
        return true;
    }
    function wireKeys(form, adapter, prefs) {
        var ta = adapter.ta;
        var targets = adapterEventTargets(adapter, ta, 'keyTargets');
        function onKeyDown(e, target) {
            if (form._rbSubmitting) { return; }
            if ((e.ctrlKey || e.metaKey) && !e.altKey) {
                var key = e.key.toLowerCase();
                var action = key === 'b' ? 'bold'
                    : key === 'i' ? 'italic'
                    : key === 'k' ? 'link'
                    : key === 'e' ? 'code'
                    : null;
                if (action !== null) {
                    e.preventDefault();
                    applyActionForAdapter(adapter, ta, action);
                    return;
                }
            }
            if (e.key === 'Escape') {
                if (form.closest('.post-native-disclosure[open]')) { return; }
                e.preventDefault();
                e.stopPropagation();
                var focused = document.activeElement;
                if (focused && typeof focused.blur === 'function') { focused.blur(); }
                else if (target && typeof target.blur === 'function') { target.blur(); }
                return;
            }
            if (e.isComposing || e.key !== 'Enter') { return; }
            if (e.shiftKey || e.altKey) { return; }
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                requestComposerSubmit(form);
                return;
            }
            if (coarsePointer() || !prefs.enterToSend) { return; }

            var active = form._rbComposerAdapter || adapter;
            var shouldSubmit = true;
            if (active && typeof active.enterShouldSubmit === 'function') {
                try { shouldSubmit = !!active.enterShouldSubmit(); } catch (error) { shouldSubmit = true; }
            }
            if (!shouldSubmit) {
                if (target === ta && prefs.smartLists && continueEditorialBlock(ta)) { e.preventDefault(); }
                return;
            }
            e.preventDefault();
            requestComposerSubmit(form);
        }
        targets.forEach(function (target) {
            target.addEventListener('keydown', function (e) { onKeyDown(e, target); }, target !== ta);
        });
    }

    function wireSubmitState(form, ta, adapter) {
        var send = shellPart(form, '.composer-send');
        if (!send) { return; }
        var status = shellPart(form, '[data-composer-submit-status]');
        var attached = [];

        function activeAdapter() { return form._rbComposerAdapter || adapter; }
        function markdown() {
            var active = activeAdapter();
            return active && typeof active.getMarkdown === 'function' ? active.getMarkdown() : ta.value;
        }
        function update() {
            send.disabled = !!form._rbSubmitting || markdown().trim() === '';
        }
        function attach(nextAdapter) {
            if (!nextAdapter || typeof nextAdapter.onChange !== 'function' || attached.indexOf(nextAdapter) !== -1) {
                update();
                return;
            }
            attached.push(nextAdapter);
            nextAdapter.onChange(update);
            update();
        }

        form.addEventListener('submit', function (event) {
            if (form._rbSubmitting) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            var active = activeAdapter();
            if (active && typeof active.getMarkdown === 'function') { ta.value = active.getMarkdown(); }
            if (ta.value.trim() === '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                update();
                if (active && typeof active.focus === 'function') { active.focus(); }
                return;
            }
            form._rbSubmitting = true;
            send.disabled = true;
            form.setAttribute('aria-busy', 'true');
            form.classList.add('is-submitting');
            if (status) { status.textContent = 'Sending…'; }
        });

        attach(adapter);
        form._rbSubmitController = { attach: attach };
    }

    function enhance(form, prefs) {
        var ta = form.querySelector('.composer-input');
        if (!ta) { return; }
        form._rbComposerEnhance = function () {
            maybeUpgradeWysiwyg(form, ta, form._rbComposerFallbackAdapter || form._rbComposerAdapter);
        };
        if (ta.getAttribute('data-rb-enhanced')) {
            form._rbComposerEnhance();
            return;
        }
        ta.setAttribute('data-rb-enhanced', '1');
        var adapter = new TextareaComposerAdapter(form, ta);
        form._rbComposerFallbackAdapter = adapter;
        form._rbComposerAdapter = adapter;
        buildToolbar(form, ta);
        buildCounter(form, adapter);
        adapter = maybeUpgradeWysiwyg(form, ta, adapter);
        buildPreview(form, prefs, adapter, form._rbComposerFallbackAdapter);
        // Wire the slash combobox before wireKeys so its keydown listener runs
        // first: when the menu is open it consumes Enter/Escape (via
        // stopImmediatePropagation) before enter-to-send / list-continuation see it.
        wireSlashMenu(form, adapter);
        wireReferencePickers(form, adapter);
        buildEmojiPicker(form, adapter);
        wireKeys(form, adapter, prefs);
        wireSubmitState(form, ta, adapter);
        stampIdempotency(form);
        // A form may opt out of local draft autosave (data-no-draft). The inline
        // post-edit form does: its textarea is server-pre-filled with the current
        // body, so a saved draft is never restored into it (the !ta.value guard
        // fails) and there is no Drafts-page resume target — autosaving would only
        // leave a misleading, unrecoverable draft that the next load discards.
        if (draftsEnabled() && !form.hasAttribute('data-no-draft')) { wireDrafts(form, adapter); }
        wireUploads(form, adapter);
    }

    document.addEventListener('DOMContentLoaded', function () {
        clearCompletedDraftSubmits();
        enhanceWithin(document);
        renderDraftsPage();
    });
})();
