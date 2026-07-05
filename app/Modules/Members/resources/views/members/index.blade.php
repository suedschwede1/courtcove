@extends('members::layout')
@section('admin-title', __('members::admin.members.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1>{{ __('members::admin.members.title') }}</h1>
        </div>
        <a href="{{ route('members.create') }}" class="ui-btn ui-btn-primary">{{ __('members::admin.members.new_member') }}</a>
    </div>

    <div class="ui-card">
        <div class="ui-card-body ui-stack">
            <form method="GET" action="{{ route('members.index') }}" class="ui-row">
                <div class="ui-field min-w-[14rem]">
                    <label class="ui-label" for="mi-search">{{ __('members::admin.members.search') }}</label>
                    <input id="mi-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label" for="mi-fee-category">{{ __('members::admin.members.fee_category') }}</label>
                    <select id="mi-fee-category" name="fee_category" class="ui-select">
                        <option value="">{{ __('members::admin.common.all') }}</option>
                        @foreach($feeCategories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['fee_category'] ?? 0) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label" for="mi-status">{{ __('members::admin.members.status') }}</label>
                    <select id="mi-status" name="status" class="ui-select">
                        <option value="aktiv" @selected(($filters['status'] ?? 'aktiv') === 'aktiv')>{{ __('members::admin.members.status_active') }}</option>
                        <option value="ruhend" @selected(($filters['status'] ?? '') === 'ruhend')>{{ __('members::admin.members.status_ruhend') }}</option>
                        <option value="ausgetreten" @selected(($filters['status'] ?? '') === 'ausgetreten')>{{ __('members::admin.members.status_departed') }}</option>
                        <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('members::admin.common.all') }}</option>
                    </select>
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label" for="mi-zahlung">{{ __('members::admin.dues.title') }}</label>
                    <select id="mi-zahlung" name="zahlung" class="ui-select">
                        <option value="">{{ __('members::admin.common.all') }}</option>
                        <option value="bezahlt" @selected(($filters['zahlung'] ?? '') === 'bezahlt')>{{ __('members::admin.dues.status_bezahlt') }}</option>
                        <option value="ausstehend" @selected(($filters['zahlung'] ?? '') === 'ausstehend')>{{ __('members::admin.dues.status_ausstehend') }}</option>
                        <option value="ueberfaellig" @selected(($filters['zahlung'] ?? '') === 'ueberfaellig')>{{ __('members::admin.dues.status_ueberfaellig') }}</option>
                    </select>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.filter') }}</button>
            </form>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.members.member_number') }}</th>
                        <th>{{ __('members::admin.members.name') }}</th>
                        <th>{{ __('members::admin.members.age') }}</th>
                        <th>{{ __('members::admin.members.status') }}</th>
                        <th>{{ __('members::admin.dues.title') }}</th>
                        <th>{{ __('members::admin.members.fee_category') }}</th>
                        <th>{{ __('members::admin.members.email') }}</th>
                        <th>{{ __('members::admin.members.phone') }}</th>
                        <th>{{ __('members::admin.members.booking_user_short') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        <tr>
                            <td class="cursor-pointer" onclick="window.location.href='{{ route('members.edit', $member) }}'">
                                <a href="{{ route('members.edit', $member) }}" class="text-[#6a6e73] hover:underline">{{ $member->member_number }}</a>
                            </td>
                            <td class="font-medium cursor-pointer"
                                data-field="firstname"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    firstname: @js($member->firstname),
                                    lastname: @js($member->lastname),
                                    async save(field) {
                                        this.error = null;
                                        const value = field === 'firstname' ? this.firstname : this.lastname;
                                        const result = await window.saveMemberField({{ $member->id }}, field, value);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" x-text="firstname + ' ' + lastname" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->fullName() }}</span>
                                <div x-show="editing" @click.stop class="flex flex-col gap-1">
                                    <div class="flex gap-1">
                                        <input type="text" x-model="firstname" @blur="save('firstname')" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                        <input type="text" x-model="lastname" @blur="save('lastname')" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    </div>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73]">{{ $member->age() ?? '—' }}</td>
                            <td data-field="status"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($member->status === 'ausgetreten' ? 'aktiv' : $member->status),
                                    displayValue: @js($member->status),
                                    labels: { aktiv: @js(__('members::admin.members.status_active')), ruhend: @js(__('members::admin.members.status_ruhend')), ausgetreten: @js(__('members::admin.members.status_departed')) },
                                    classes: { aktiv: 'ui-badge-success', ruhend: 'ui-badge-warning', ausgetreten: 'ui-badge-neutral' },
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveMemberField({{ $member->id }}, 'status', this.value);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.value = result.value;
                                            this.displayValue = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false"
                                class="cursor-pointer">
                                <span x-show="!editing" x-text="labels[displayValue]" :class="'ui-badge ' + classes[displayValue]" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing" @click.stop>
                                    <select x-model="value" @change="save()" class="ui-select">
                                        <option value="aktiv" x-text="labels.aktiv"></option>
                                        <option value="ruhend" x-text="labels.ruhend"></option>
                                    </select>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td data-field="due-status"
                                @if($latestDue = $member->dues->sortByDesc(fn ($due) => $due->fiscalYear->starts_on)->first())
                                    x-data="{
                                        editing: false, error: null, justSaved: false,
                                        value: @js($latestDue->status),
                                        displayValue: @js($latestDue->displayStatus()),
                                        bezahltBis: @js($latestDue->bezahlt_bis_fiscal_year_id ?? ''),
                                        kommentar: @js($latestDue->kommentar ?? ''),
                                        labels: { ausstehend: @js(__('members::admin.dues.status_ausstehend')), bezahlt: @js(__('members::admin.dues.status_bezahlt')), ueberfaellig: @js(__('members::admin.dues.status_ueberfaellig')) },
                                        classes: { ausstehend: 'ui-badge-warning', bezahlt: 'ui-badge-success', ueberfaellig: 'ui-badge-danger' },
                                        async save() {
                                            this.error = null;
                                            const result = await window.saveDueField({{ $latestDue->id }}, 'status', this.value);
                                            if (result.ok) {
                                                this.value = result.value;
                                                this.displayValue = result.value;
                                                this.justSaved = true;
                                                setTimeout(() => this.justSaved = false, 1500);
                                            } else {
                                                this.error = result.message;
                                            }
                                        },
                                        async saveKommentar() {
                                            this.error = null;
                                            const result = await window.saveDueField({{ $latestDue->id }}, 'kommentar', this.kommentar || null);
                                            if (!result.ok) { this.error = result.message; }
                                        },
                                        async savePaidThrough() {
                                            if (!this.bezahltBis) return;
                                            await window.saveDuePaidThrough({{ $latestDue->id }}, this.bezahltBis);
                                        }
                                    }"
                                    @click="if (!editing) editing = true"
                                    @click.outside="if (!error) editing = false"
                                    class="cursor-pointer">
                                    <span x-show="!editing" x-text="labels[displayValue]" :class="'ui-badge ' + classes[displayValue]" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                    <div x-show="editing" @click.stop class="flex flex-col gap-1" style="min-width:10rem">
                                        <select x-model="value" @change="save()" class="ui-select">
                                            <option value="ausstehend" x-text="labels.ausstehend"></option>
                                            <option value="bezahlt" x-text="labels.bezahlt"></option>
                                            <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                                        </select>
                                        <select x-model="bezahltBis" @change="savePaidThrough()" class="ui-select">
                                            <option value="">{{ __('members::admin.dues.paid_through') }}</option>
                                            @foreach($fiscalYears as $fiscalYear)
                                                <option value="{{ $fiscalYear->id }}">{{ $fiscalYear->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" x-model="kommentar" @blur="saveKommentar()" @keydown.enter.prevent="$event.target.blur()" placeholder="{{ __('members::admin.dues.kommentar') }}" class="ui-input">
                                        <p x-show="error" x-text="error" class="ui-error"></p>
                                    </div>
                                @else
                                    <span class="text-[#6a6e73]">–</span>
                                @endif
                            </td>
                            <td class="text-[#6a6e73] cursor-pointer"
                                data-field="fee_category_id"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js((string) ($member->fee_category_id ?? '')),
                                    label: @js($member->feeCategory->name ?? '—'),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveMemberField({{ $member->id }}, 'fee_category_id', this.value || null);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.label = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" x-text="label" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->feeCategory->name ?? '—' }}</span>
                                <div x-show="editing" @click.stop>
                                    <select x-model="value" @change="save()" class="ui-select">
                                        <option value=""></option>
                                        @foreach($feeCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73] cursor-pointer"
                                data-field="email"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($member->email ?? ''),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveMemberField({{ $member->id }}, 'email', this.value || null);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" x-text="value || '—'" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->email ?: '—' }}</span>
                                <div x-show="editing" @click.stop>
                                    <input type="email" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73] cursor-pointer"
                                data-field="phone"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($member->phone ?? ''),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveMemberField({{ $member->id }}, 'phone', this.value || null);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" x-text="value || '—'" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->phone ?: '—' }}</span>
                                <div x-show="editing" @click.stop>
                                    <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td>
                                @if($member->user_id !== null)
                                    <span class="ui-badge ui-badge-success">{{ __('members::admin.common.yes') }}</span>
                                @else
                                    <span class="ui-badge ui-badge-neutral">{{ __('members::admin.common.no') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('members.edit', $member) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.edit') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($members->hasPages())
            <div class="ui-card-body">
                {{ $members->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    window.saveMemberField = async (memberId, field, value) => {
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`/members/${memberId}/field`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ field, value }),
            });
            const data = await response.json();

            if (!response.ok) {
                return { ok: false, message: data.message || 'Fehler beim Speichern.' };
            }

            return { ok: true, value: data.value };
        } catch (e) {
            return { ok: false, message: 'Fehler beim Speichern.' };
        }
    };

    window.saveDueField = async (dueId, field, value) => {
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`/members/dues/${dueId}/field`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ field, value }),
            });
            const data = await response.json();

            if (!response.ok) {
                return { ok: false, message: data.message || 'Fehler beim Speichern.' };
            }

            return { ok: true, value: data.value };
        } catch (e) {
            return { ok: false, message: 'Fehler beim Speichern.' };
        }
    };

    window.saveDuePaidThrough = async (dueId, fiscalYearId) => {
        if (!fiscalYearId) return;
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`/members/dues/${dueId}/paid-through`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ fiscal_year_id: fiscalYearId }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }

            const data = await response.json();
            alert(data.message || 'Fehler beim Speichern.');
            window.location.reload();
        } catch (e) {
            alert('Fehler beim Speichern.');
            window.location.reload();
        }
    };
</script>
@endsection
