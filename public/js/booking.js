(function () {
    'use strict';

    // ─── Panel toggle (Infos / Hints) ───────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-panel-toggle]');
        if (!btn) { return; }
        var panelId = btn.getAttribute('data-panel-toggle');
        var panel = document.getElementById(panelId);
        if (!panel) { return; }
        panel.hidden = !panel.hidden;
    });

    // ─── Admin Iframe Modal ─────────────────────────────────────────────────────
    // The modal itself is an Alpine component (components/calendar/modals.blade.php);
    // this script only tells it which URL to show.

    function openAdminFrame(url) {
        if (!url) { return; }
        url = url + (url.includes('?') ? '&' : '?') + 'popup=1';
        window.dispatchEvent(new CustomEvent('open-admin-frame', { detail: { url: url } }));
    }

    // Iframe navigation monitor: reload the page once the form submits successfully
    var abmIframe = document.getElementById('abm-iframe');
    if (abmIframe) {
        abmIframe.addEventListener('load', function () {
            try {
                var loc = abmIframe.contentWindow.location.href;
                if (loc && loc !== 'about:blank' && !loc.includes('popup=1')) {
                    window.location.reload();
                }
            } catch (_) {
                // Cross-origin: ignore
            }
        });
    }

    // ─── Event delegation for admin triggers (.booking-trigger) ─────────────────

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.booking-trigger');
        if (!trigger) { return; }

        e.preventDefault();

        var action    = trigger.dataset.action;
        var deleteUrl = trigger.dataset.deleteUrl;
        var editUrl   = trigger.dataset.editUrl;

        if (action === 'admin-book') {
            openAdminFrame(trigger.dataset.createUrl);
        } else if (action === 'cancel' && deleteUrl) {
            // Admin cancel: edit form inside the iframe
            if (editUrl) {
                openAdminFrame(editUrl);
            }
        } else if (action === 'event-edit' && editUrl) {
            openAdminFrame(editUrl);
        }
    });

})();
