document.addEventListener('DOMContentLoaded', function () {
    var dateInput = document.getElementById('c-date');
    if (dateInput) {
        var openPicker = function () {
            if (typeof dateInput.showPicker === 'function') {
                dateInput.showPicker();
            }
        };

        dateInput.addEventListener('change', function () {
            if (!dateInput.value) {
                return;
            }

            window.location.href = dateInput.closest('form').action + '?date=' + dateInput.value;
        });

        dateInput.addEventListener('pointerdown', function () {
            openPicker();
        });

        dateInput.addEventListener('focus', function () {
            openPicker();
        });
    }

    document.querySelectorAll('[data-panel-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var panelId = toggle.getAttribute('data-panel-toggle');
            var panel = document.getElementById(panelId);
            if (!panel) {
                return;
            }

            if (panel.hasAttribute('hidden')) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    });

    var feedbackModal = document.getElementById('feedback-modal');
    var bookingModal = document.getElementById('booking-modal');
    var cancelModal = document.getElementById('cancel-modal');
    var eventModal = document.getElementById('event-modal');
    var adminBookingModal = document.getElementById('admin-booking-modal');
    var quantitySelect = document.getElementById('modal-quantity');
    var cancelEditLink = document.getElementById('cancel-modal-edit');
    var createEventButton = document.getElementById('modal-create-event');
    var eventForm = document.getElementById('event-form');
    var eventDateStart = document.getElementById('event-date-start');
    var eventTimeStart = document.getElementById('event-time-start');
    var eventDateEnd = document.getElementById('event-date-end');
    var eventTimeEnd = document.getElementById('event-time-end');
    var eventDatetimeStart = document.getElementById('event-datetime-start');
    var eventDatetimeEnd = document.getElementById('event-datetime-end');
    var eventSid = document.getElementById('event-sid');
    var eventName = document.getElementById('event-name');
    var activeSlot = null;
    var playerFields = [2, 3, 4].map(function (index) {
        return {
            index: index,
            field: document.getElementById('modal-player' + index + '-field'),
            input: document.getElementById('modal-player' + index),
        };
    });

    function closeFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.style.display = 'none';
        }
    }

    function hideModal(modal) {
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function showModal(modal) {
        if (modal) {
            modal.style.display = 'block';
        }
    }

    function closeAllModals() {
        hideModal(bookingModal);
        hideModal(cancelModal);
        hideModal(eventModal);
        hideModal(adminBookingModal);
        closeFeedbackModal();
    }

    function padNumber(value) {
        return String(value).padStart(2, '0');
    }

    function secondsToTime(seconds) {
        var totalMinutes = Math.floor(Number(seconds || 0) / 60);
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;

        return padNumber(hours) + ':' + padNumber(minutes);
    }

    function syncBookingFields() {
        if (!quantitySelect) {
            return;
        }

        var quantity = quantitySelect.value;
        playerFields.forEach(function (player) {
            if (!player.field || !player.input) {
                return;
            }

            var shouldShow = quantity === '4' ? true : (quantity === '2' && player.index === 2);
            if (shouldShow) {
                player.field.removeAttribute('hidden');
                player.input.required = true;
                if (!player.input.placeholder) {
                    player.input.placeholder = player.index === 2 ? 'Mitglied auswählen oder frei eingeben' : 'Mitglied auswählen oder frei eingeben';
                }
            } else {
                player.field.setAttribute('hidden', '');
                player.input.required = false;
                player.input.value = '';
            }
        });
    }

    function resetBookingFields() {
        if (quantitySelect) {
            quantitySelect.value = '2';
        }

        playerFields.forEach(function (player) {
            if (player.input) {
                player.input.value = '';
            }
        });

        syncBookingFields();
    }

    function fillEventFieldsFromSlot(slot) {
        if (!slot) {
            return;
        }

        var slotDate = slot.getAttribute('data-date');
        var timeStart = secondsToTime(slot.getAttribute('data-time-start'));
        var timeEnd = secondsToTime(slot.getAttribute('data-time-end'));

        if (eventDateStart) {
            eventDateStart.value = slotDate;
        }

        if (eventTimeStart) {
            eventTimeStart.value = timeStart;
        }

        if (eventDateEnd) {
            eventDateEnd.value = slotDate;
        }

        if (eventTimeEnd) {
            eventTimeEnd.value = timeEnd;
        }

        if (eventSid) {
            eventSid.value = slot.getAttribute('data-sid');
        }

        if (eventName && !eventName.value.trim()) {
            eventName.value = slot.getAttribute('data-square-name');
        }
    }

    function syncEventDateTime() {
        if (!eventDatetimeStart || !eventDatetimeEnd || !eventDateStart || !eventTimeStart || !eventDateEnd || !eventTimeEnd) {
            return;
        }

        eventDatetimeStart.value = eventDateStart.value && eventTimeStart.value ? eventDateStart.value + ' ' + eventTimeStart.value : '';
        eventDatetimeEnd.value = eventDateEnd.value && eventTimeEnd.value ? eventDateEnd.value + ' ' + eventTimeEnd.value : '';
    }

    function openBookingModal(trigger) {
        activeSlot = trigger;
        var slotDate = trigger.getAttribute('data-date');
        var timeStart = trigger.getAttribute('data-time-start');
        var timeEnd = trigger.getAttribute('data-time-end');

        document.getElementById('modal-title').textContent = trigger.getAttribute('data-square-name') + ' buchen';
        document.getElementById('modal-date').textContent = trigger.getAttribute('data-date-label');
        document.getElementById('modal-time').textContent = trigger.getAttribute('data-time-label');
        document.getElementById('modal-sid').value = trigger.getAttribute('data-sid');
        document.getElementById('modal-date-input').value = slotDate;
        document.getElementById('modal-ts').value = timeStart;
        document.getElementById('modal-te').value = timeEnd;

        if (createEventButton) {
            createEventButton.hidden = false;
        }

        var adminLink = document.getElementById('modal-admin-link');
        if (adminLink) {
            var createUrl = trigger.getAttribute('data-create-url');
            if (createUrl) {
                adminLink.href = createUrl;
                adminLink.hidden = false;
            } else {
                adminLink.hidden = true;
            }
        }

        resetBookingFields();
        showModal(bookingModal);
    }

    function openEventModal() {
        fillEventFieldsFromSlot(activeSlot);
        syncEventDateTime();
        hideModal(bookingModal);
        showModal(eventModal);
    }

    function openCancelModal(trigger) {
        var bid = trigger.getAttribute('data-bid');
        var editUrl = trigger.getAttribute('data-edit-url');
        document.getElementById('cancel-modal-title').textContent = trigger.getAttribute('data-square-name');
        document.getElementById('cancel-modal-date').textContent = trigger.getAttribute('data-date-label');
        document.getElementById('cancel-modal-time').textContent = trigger.getAttribute('data-time-label');
        document.getElementById('cancel-form').action = '/bookings/' + bid;

        if (cancelEditLink) {
            if (editUrl) {
                cancelEditLink.href = editUrl;
                cancelEditLink.hidden = false;
            } else {
                cancelEditLink.hidden = true;
                cancelEditLink.href = '#';
            }
        }

        showModal(cancelModal);
    }

    function secondsToHHMM(seconds) {
        var s = parseInt(seconds, 10) || 0;
        return padNumber(Math.floor(s / 3600)) + ':' + padNumber(Math.floor((s % 3600) / 60));
    }

    function syncAbmQuantity() {
        var qty = document.getElementById('abm-quantity');
        var isDouble = qty && qty.value === '4';
        ['abm-p3-field', 'abm-p4-field'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.hidden = !isDouble; }
        });
        ['abm-p3', 'abm-p4'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.required = isDouble; if (!isDouble) { el.value = ''; } }
        });
    }

    function syncAbmRepeat() {
        var repeat = document.getElementById('abm-repeat');
        var dateEndField = document.getElementById('abm-date-end-field');
        var dateEndInput = document.getElementById('abm-date-end');
        if (!repeat || !dateEndField) { return; }
        var isOnce = repeat.value === 'once';
        dateEndField.hidden = isOnce;
        if (dateEndInput) { dateEndInput.required = !isOnce; }
    }

    function openAdminBookingModal(trigger) {
        var timeStart = secondsToHHMM(trigger.getAttribute('data-time-start'));
        var timeEnd   = secondsToHHMM(trigger.getAttribute('data-time-end'));

        var title = document.getElementById('abm-title');
        var meta  = document.getElementById('abm-meta');
        if (title) { title.textContent = trigger.getAttribute('data-square-name'); }
        if (meta)  { meta.textContent  = trigger.getAttribute('data-date-label') + ', ' + trigger.getAttribute('data-time-label'); }

        var set = function (id, val) { var el = document.getElementById(id); if (el) { el.value = val; } };
        set('abm-sid',        trigger.getAttribute('data-sid'));
        set('abm-date',       trigger.getAttribute('data-date'));
        set('abm-time-start', timeStart);
        set('abm-time-end',   timeEnd);
        set('abm-date-end',   trigger.getAttribute('data-date'));
        set('abm-booked-for', '');
        set('abm-p2', '');
        set('abm-p3', '');
        set('abm-p4', '');

        var qty = document.getElementById('abm-quantity');
        if (qty) { qty.value = '2'; }
        var repeat = document.getElementById('abm-repeat');
        if (repeat) { repeat.value = 'once'; }

        syncAbmQuantity();
        syncAbmRepeat();
        showModal(adminBookingModal);
        setTimeout(function () {
            var bf = document.getElementById('abm-booked-for');
            if (bf) { bf.focus(); }
        }, 50);
    }

    if (adminBookingModal) {
        var abmClose     = document.getElementById('abm-close');
        var abmCancelBtn = document.getElementById('abm-cancel-btn');
        var abmQty       = document.getElementById('abm-quantity');
        var abmRepeat    = document.getElementById('abm-repeat');

        if (abmClose)     { abmClose.addEventListener('click', function (e) { e.preventDefault(); closeAllModals(); }); }
        if (abmCancelBtn) { abmCancelBtn.addEventListener('click', closeAllModals); }
        if (abmQty)       { abmQty.addEventListener('change', syncAbmQuantity); }
        if (abmRepeat)    { abmRepeat.addEventListener('change', syncAbmRepeat); }
        adminBookingModal.addEventListener('click', function (e) {
            if (e.target === adminBookingModal) { closeAllModals(); }
        });
    }

    if (feedbackModal) {
        var feedbackClose = document.getElementById('feedback-modal-close');
        var feedbackOk = document.getElementById('feedback-modal-ok');

        if (feedbackClose) {
            feedbackClose.addEventListener('click', function (event) {
                event.preventDefault();
                closeFeedbackModal();
            });
        }

        if (feedbackOk) {
            feedbackOk.addEventListener('click', closeFeedbackModal);
        }

        feedbackModal.addEventListener('click', function (event) {
            if (event.target === feedbackModal) {
                closeFeedbackModal();
            }
        });
    }

    if (quantitySelect) {
        quantitySelect.addEventListener('change', syncBookingFields);
        syncBookingFields();
    }

    [eventDateStart, eventTimeStart, eventDateEnd, eventTimeEnd].forEach(function (input) {
        if (input) {
            input.addEventListener('input', syncEventDateTime);
            input.addEventListener('change', syncEventDateTime);
        }
    });

    if (eventForm) {
        eventForm.addEventListener('submit', function () {
            syncEventDateTime();
        });
    }

    if (createEventButton) {
        createEventButton.addEventListener('click', function () {
            openEventModal();
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.booking-trigger');
        if (trigger) {
            event.preventDefault();
            var action = trigger.getAttribute('data-action');

            if (action === 'cancel') {
                openCancelModal(trigger);
            } else if (action === 'admin-book' && adminBookingModal) {
                openAdminBookingModal(trigger);
            } else {
                openBookingModal(trigger);
            }
            return;
        }

        if (event.target === bookingModal || event.target === cancelModal || event.target === eventModal) {
            closeAllModals();
        }
    });

    var modalClose = document.getElementById('modal-close');
    var modalCancel = document.getElementById('modal-cancel');
    var cancelClose = document.getElementById('cancel-modal-close');
    var cancelAbort = document.getElementById('cancel-modal-abort');
    var eventClose = document.getElementById('event-modal-close');
    var eventCancel = document.getElementById('event-modal-cancel');

    if (modalClose) {
        modalClose.addEventListener('click', function (event) {
            event.preventDefault();
            closeAllModals();
        });
    }

    if (modalCancel) {
        modalCancel.addEventListener('click', closeAllModals);
    }

    if (cancelClose) {
        cancelClose.addEventListener('click', function (event) {
            event.preventDefault();
            closeAllModals();
        });
    }

    if (cancelAbort) {
        cancelAbort.addEventListener('click', closeAllModals);
    }

    if (eventClose) {
        eventClose.addEventListener('click', function (event) {
            event.preventDefault();
            closeAllModals();
        });
    }

    if (eventCancel) {
        eventCancel.addEventListener('click', closeAllModals);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var datalist = document.getElementById('player-suggestions');
    if (!datalist) {
        return;
    }

    var timer = null;

    function refresh(value) {
        var q = value.trim();
        if (q.length < 2) {
            datalist.innerHTML = '';
            return;
        }

        fetch('/bookings/players?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (list) {
                datalist.innerHTML = '';
                (list || []).forEach(function (alias) {
                    var opt = document.createElement('option');
                    opt.value = alias;
                    datalist.appendChild(opt);
                });
            })
            .catch(function () {});
    }

    document.querySelectorAll('input[list="player-suggestions"]').forEach(function (input) {
        input.addEventListener('input', function (event) {
            clearTimeout(timer);
            var value = event.target.value;
            timer = setTimeout(function () { refresh(value); }, 200);
        });
    });
});

