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
    function closeFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.style.display = 'none';
        }
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

    var bookingModal = document.getElementById('booking-modal');
    var cancelModal = document.getElementById('cancel-modal');
    if (!bookingModal || !cancelModal) {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeFeedbackModal();
            }
        });
        return;
    }

    var quantitySelect = document.getElementById('modal-quantity');
    var cancelEditLink = document.getElementById('cancel-modal-edit');
    var createEventLink = document.getElementById('modal-create-event');
    var playerFields = [2, 3, 4].map(function (index) {
        return {
            index: index,
            field: document.getElementById('modal-player' + index + '-field'),
            input: document.getElementById('modal-player' + index),
        };
    });

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

    function closeAllModals() {
        bookingModal.style.display = 'none';
        cancelModal.style.display = 'none';
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

    function openBookingModal(trigger) {
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

        if (createEventLink) {
            var eventUrl = new URL(createEventLink.getAttribute('data-event-create-base'), window.location.origin);
            eventUrl.searchParams.set('sid', trigger.getAttribute('data-sid'));
            eventUrl.searchParams.set('datetime_start', slotDate + ' ' + secondsToTime(timeStart));
            eventUrl.searchParams.set('datetime_end', slotDate + ' ' + secondsToTime(timeEnd));
            createEventLink.href = eventUrl.toString();
        }

        resetBookingFields();
        bookingModal.style.display = 'block';
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

        cancelModal.style.display = 'block';
    }

    if (quantitySelect) {
        quantitySelect.addEventListener('change', syncBookingFields);
        syncBookingFields();
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.booking-trigger');
        if (trigger) {
            event.preventDefault();
            if (trigger.getAttribute('data-action') === 'cancel') {
                openCancelModal(trigger);
            } else {
                openBookingModal(trigger);
            }
            return;
        }

        if (event.target === bookingModal || event.target === cancelModal) {
            closeAllModals();
        }
    });

    var modalClose = document.getElementById('modal-close');
    var modalCancel = document.getElementById('modal-cancel');
    var cancelClose = document.getElementById('cancel-modal-close');
    var cancelAbort = document.getElementById('cancel-modal-abort');

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

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
});

// Player-name autocomplete: fetches matching member aliases from the AJAX
// endpoint (min 2 chars, debounced) and fills the #player-suggestions datalist.
// Self-contained — does not depend on the modal logic above.
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
