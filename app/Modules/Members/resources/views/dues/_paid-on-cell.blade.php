{{--
    "Bezahlt am" cell of a due row (dues list and member dues card). Editable inline only while the
    due is paid; the status cell of the same row announces changes via the 'due-paid-on' event.
--}}
<td class="tabular-nums"
    data-field="bezahlt_am"
    data-label="{{ __('members::admin.dues.bezahlt_am') }}"
    x-data="{
        editing: false, error: null, justSaved: false,
        paid: @js($due->status === 'bezahlt'),
        value: @js($due->bezahlt_am?->toDateString() ?? ''),
        display: @js($due->paidOnDisplay()),
        async save() {
            this.error = null;
            const result = await window.saveDueField({{ $due->id }}, 'bezahlt_am', this.value || null);
            if (result.ok) { this.editing = false; this.display = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
            else { this.error = result.message; }
        }
    }"
    x-bind:class="{ 'cursor-pointer': paid }"
    x-on:due-paid-on.window="if ($event.detail.id === {{ $due->id }}) { paid = $event.detail.value !== null; value = $event.detail.value ?? ''; display = $event.detail.display; editing = false; error = null; }"
    @click="if (paid && !editing) editing = true"
    @click.outside="if (!error) editing = false">
    <span x-show="!editing" x-text="display" :class="{ 'mbr-saved': justSaved }">{{ $due->paidOnDisplay() }}</span>
    <div x-show="editing" @click.stop>
        <input type="date" min="{{ \App\Modules\Members\Models\MemberDue::PAID_ON_MIN }}" max="{{ now()->toDateString() }}" x-model="value" @change="save()" @keydown.enter.prevent="save()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.bezahlt_am') }}">
        <p class="ds-field__error" x-show="error" x-text="error"></p>
    </div>
</td>
