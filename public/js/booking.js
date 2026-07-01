(function () {
    'use strict';

    // ─── Feedback modal (success / error after redirect) ────────────────────────
    var feedbackModal = document.getElementById('feedback-modal');
    if (feedbackModal) {
        function closeFeedbackModal() { feedbackModal.style.display = 'none'; }
        var fbClose = document.getElementById('feedback-modal-close');
        var fbOk    = document.getElementById('feedback-modal-ok');
        if (fbClose) { fbClose.addEventListener('click', closeFeedbackModal); }
        if (fbOk)    { fbOk.addEventListener('click', closeFeedbackModal); }
        feedbackModal.addEventListener('click', function (e) {
            if (e.target === feedbackModal || e.target.classList.contains('booking-modal__viewport')) {
                closeFeedbackModal();
            }
        });
    }

    // ─── Panel toggle (Infos / Hints) ───────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-panel-toggle]');
        if (!btn) { return; }
        var panelId = btn.getAttribute('data-panel-toggle');
        var panel = document.getElementById(panelId);
        if (!panel) { return; }
        panel.hidden = !panel.hidden;
    });

    // ─── Helpers ───────────────────────────────────────────────────────────────

    function showModal(modal) {
        if (modal) { modal.style.display = 'block'; }
    }

    function hideModal(modal) {
        if (modal) { modal.style.display = 'none'; }
    }

    function closeIframeModal() {
        var modal  = document.getElementById('admin-booking-modal');
        var iframe = document.getElementById('abm-iframe');
        if (iframe) { iframe.src = ''; }
        hideModal(modal);
    }

    // ─── Admin Iframe Modal ─────────────────────────────────────────────────────

    function openAdminBookingModal(element) {
        var modal  = document.getElementById('admin-booking-modal');
        var iframe = document.getElementById('abm-iframe');
        if (!modal || !iframe) { return; }

        var createUrl = element.dataset.createUrl;
        if (!createUrl) { return; }

        iframe.src = createUrl + (createUrl.includes('?') ? '&' : '?') + 'popup=1';
        showModal(modal);
    }

    function openAdminUrlInModal(url) {
        var modal  = document.getElementById('admin-booking-modal');
        var iframe = document.getElementById('abm-iframe');
        if (!modal || !iframe) { return; }

        iframe.src = url + (url.includes('?') ? '&' : '?') + 'popup=1';
        showModal(modal);
    }

    // Close via close button
    var abmClose = document.getElementById('abm-close');
    if (abmClose) {
        abmClose.addEventListener('click', closeIframeModal);
    }

    // Close via backdrop click
    var abmModal = document.getElementById('admin-booking-modal');
    if (abmModal) {
        abmModal.addEventListener('click', function (e) {
            if (e.target === abmModal ||
                e.target.classList.contains('booking-modal__viewport')) {
                closeIframeModal();
            }
        });
    }

    // Escape key (iframe modal only — Alpine handles the others)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeIframeModal(); }
    });

    // Iframe navigation monitor: reload the page once the form submits successfully
    var abmIframe = document.getElementById('abm-iframe');
    if (abmIframe) {
        abmIframe.addEventListener('load', function () {
            try {
                var loc = abmIframe.contentWindow.location.href;
                if (loc && loc !== 'about:blank' && !loc.includes('popup=1')) {
                    closeIframeModal();
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
            openAdminBookingModal(trigger);
        } else if (action === 'cancel' && deleteUrl) {
            // Admin cancel: edit form inside the iframe
            if (editUrl) {
                openAdminUrlInModal(editUrl);
            }
        } else if (action === 'event-edit' && editUrl) {
            openAdminUrlInModal(editUrl);
        }
    });

})();
