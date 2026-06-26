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
})();
