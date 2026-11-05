<script>
document.addEventListener('DOMContentLoaded', function () {
    var quantityRadios = document.querySelectorAll('input[name="quantity"]');
    var repeat = document.getElementById('admin-booking-repeat');
    var dateEnd = document.getElementById('admin-booking-date-end');
    var dateStart = document.querySelector('input[name="date"]');
    var player3Field = document.getElementById('admin-player3-field');
    var player4Field = document.getElementById('admin-player4-field');
    var player3Input = document.getElementById('admin-player3');
    var player4Input = document.getElementById('admin-player4');

    function syncAdminBookingFields() {
        var quantity = document.querySelector('input[name="quantity"]:checked');
        if (quantity) {
            var isDouble = quantity.value === '4';
            if (player3Field) { player3Field.style.display = isDouble ? '' : 'none'; }
            if (player4Field) { player4Field.style.display = isDouble ? '' : 'none'; }
            if (player3Input) { player3Input.required = isDouble; if (!isDouble) { player3Input.value = ''; } }
            if (player4Input) { player4Input.required = isDouble; if (!isDouble) { player4Input.value = ''; } }
        }

        var timeStart = document.getElementById('time_start');
        var timeEnd = document.getElementById('time_end');

        if (repeat && dateEnd) {
            var isOnce = repeat.value === 'once';
            var dateFields = [dateStart, timeStart, timeEnd];
            dateFields.forEach(function (f) {
                if (!f) { return; }
                f.readOnly = isOnce;
                f.classList.toggle('is-disabled', isOnce);
            });
            dateEnd.disabled = isOnce;
            dateEnd.classList.toggle('is-disabled', isOnce);
            if (isOnce && dateStart) { dateEnd.value = dateStart.value; }
        }
    }

    quantityRadios.forEach(function (radio) { radio.addEventListener('change', syncAdminBookingFields); });
    if (repeat) { repeat.addEventListener('change', syncAdminBookingFields); }
    if (dateStart) { dateStart.addEventListener('change', syncAdminBookingFields); }
    syncAdminBookingFields();

    var scopeRadioName = 'edit_scope';
    var scopeRadios = document.querySelectorAll('input[name="' + scopeRadioName + '"]');
    if (scopeRadios.length) {
        var cancelScopeField = document.getElementById('admin-booking-cancel-scope');
        var deleteScopeField = document.getElementById('admin-booking-delete-scope');
        var followingRepeatType = @json($followingRepeatType ?? 'once');
        var followingRepeatEndDate = @json($followingRepeatEndDate ?? '');
        var singleRepeatEndDate = @json($occurrenceDate ?? '');

        function syncScopeToActionForms() {
            var checked = document.querySelector('input[name="' + scopeRadioName + '"]:checked');
            var value = checked ? checked.value : 'single_occurrence';
            if (cancelScopeField) { cancelScopeField.value = value; }
            if (deleteScopeField) { deleteScopeField.value = value; }
        }

        scopeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncScopeToActionForms();
                if (!repeat || !dateEnd) { return; }
                if (this.value === 'this_and_following' && this.checked) {
                    repeat.value = followingRepeatType;
                    dateEnd.value = followingRepeatEndDate;
                } else if (this.value === 'single_occurrence' && this.checked) {
                    repeat.value = 'once';
                    dateEnd.value = singleRepeatEndDate;
                }
                syncAdminBookingFields();
            });
        });

        syncScopeToActionForms();
    }
});
</script>
