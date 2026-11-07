<script>
    (() => {
        const saveFailed = @js(__('members::admin.common.save_failed'));
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const patch = (url, payload) => fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        });
        const saveField = async (url, field, value) => {
            try {
                const response = await patch(url, { field, value });
                const data = await response.json();

                if (!response.ok) {
                    return { ok: false, message: data.message || saveFailed };
                }

                return { ok: true, value: data.value, data };
            } catch (e) {
                return { ok: false, message: saveFailed };
            }
        };

        window.saveMemberField = async (memberId, field, value) =>
            saveField(`{{ url('/members') }}/${memberId}/field`, field, value);

        window.saveDueField = async (dueId, field, value) =>
            saveField(`{{ url('/members') }}/dues/${dueId}/field`, field, value);

        // Household dues (family fee) use the same contract on their own endpoints.
        window.saveHouseholdDueField = async (dueId, field, value) =>
            saveField(`{{ url('/members') }}/household-dues/${dueId}/field`, field, value);

        // A status change sets or clears "Bezahlt am" on the server; tell the row's date cell.
        // kind tells member and household dues apart (their ids are separate number ranges).
        window.announceDuePaidOn = (dueId, data, kind = 'member') => {
            if (!data || !('bezahlt_am' in data)) return;
            window.dispatchEvent(new CustomEvent('due-paid-on', {
                detail: { id: dueId, kind, value: data.bezahlt_am ?? null, display: data.bezahlt_am_display ?? '–' },
            }));
        };

        const paidThrough = async (url, fiscalYearId) => {
            if (!fiscalYearId) return;

            try {
                const response = await patch(url, { fiscal_year_id: fiscalYearId });

                if (response.ok) {
                    window.location.reload();
                    return;
                }

                const data = await response.json();
                alert(data.message || saveFailed);
                window.location.reload();
            } catch (e) {
                alert(saveFailed);
                window.location.reload();
            }
        };

        window.saveDuePaidThrough = async (dueId, fiscalYearId) =>
            paidThrough(`{{ url('/members') }}/dues/${dueId}/paid-through`, fiscalYearId);

        window.saveHouseholdDuePaidThrough = async (dueId, fiscalYearId) =>
            paidThrough(`{{ url('/members') }}/household-dues/${dueId}/paid-through`, fiscalYearId);
    })();
</script>
