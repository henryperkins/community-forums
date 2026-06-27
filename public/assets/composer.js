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

    var ACTIONS = {
        bold: ['**', '**'], italic: ['*', '*'], strike: ['~~', '~~'],
        code: ['`', '`'], spoiler: ['||', '||'], quote: ['\n> ', ''],
        link: ['[', '](https://)'], h2: ['\n## ', ''], list: ['\n- ', '']
    };

    function buildToolbar(ta) {
        var bar = document.createElement('div');
        bar.className = 'composer-toolbar';
        Object.keys(ACTIONS).forEach(function (key) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = key;
            b.setAttribute('aria-label', 'Insert ' + key);
            b.addEventListener('click', function () {
                wrapSelection(ta, ACTIONS[key][0], ACTIONS[key][1]);
            });
            bar.appendChild(b);
        });
        ta.parentNode.insertBefore(bar, ta);
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
    function draftKey(form) {
        var who = document.body.getAttribute('data-user') || 'anon';
        return 'rb-draft:' + who + ':' + (form.getAttribute('action') || location.pathname);
    }
    function wireDrafts(form, ta) {
        var key = draftKey(form);
        try {
            var saved = localStorage.getItem(key);
            if (saved && !ta.value) { ta.value = saved; ta.dispatchEvent(new Event('input', { bubbles: true })); }
        } catch (e) {}
        ta.addEventListener('input', function () {
            try { ta.value ? localStorage.setItem(key, ta.value) : localStorage.removeItem(key); } catch (e) {}
        });
        form.addEventListener('submit', function () {
            try { localStorage.removeItem(key); } catch (e) {}
        });
    }

    // ---- Image paste / drag-drop upload (P3-04) ---------------------------
    function insertAtCursor(ta, text) {
        var s = ta.selectionStart;
        ta.value = ta.value.slice(0, s) + text + ta.value.slice(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + text.length;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function uploadImage(form, ta, file) {
        var data = new FormData();
        data.append('_token', tokenField(form));
        data.append('image', file);
        // Unique per upload so several images pasted/dropped at once each resolve
        // into their OWN marker — String.replace(str) only swaps the first match.
        var token = 'rbup-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
        var placeholder = '![uploading…](' + token + ')';
        insertAtCursor(ta, placeholder);
        fetch('/upload', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                ta.value = ta.value.replace(placeholder, (j && j.ok) ? j.markdown : '');
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            })
            .catch(function () { ta.value = ta.value.replace(placeholder, ''); });
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
        if (!prefs.enterToSend && !prefs.smartLists) { return; }
        ta.addEventListener('keydown', function (e) {
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
        wireDrafts(form, ta);
        wireUploads(form, ta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var prefs = composingPrefs();
        var forms = document.querySelectorAll('form.composer');
        for (var i = 0; i < forms.length; i++) { enhance(forms[i], prefs); }
    });
})();
