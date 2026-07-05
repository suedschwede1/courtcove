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
                        <option value="">{{ __('members::admin.common.all') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('members::admin.members.status_active') }}</option>
                        <option value="departed" @selected(($filters['status'] ?? '') === 'departed')>{{ __('members::admin.members.status_departed') }}</option>
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
                        <th>{{ __('members::admin.members.fee_category') }}</th>
                        <th>{{ __('members::admin.members.email') }}</th>
                        <th>{{ __('members::admin.members.joined_at') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        <tr>
                            <td class="text-[#6a6e73]">{{ $member->member_number }}</td>
                            <td class="font-medium"
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
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="firstname + ' ' + lastname" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->fullName() }}</span>
                                <div x-show="editing" class="flex flex-col gap-1">
                                    <div class="flex gap-1">
                                        <input type="text" x-model="firstname" @blur="save('firstname')" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                        <input type="text" x-model="lastname" @blur="save('lastname')" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    </div>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73]">{{ $member->age() ?? '—' }}</td>
                            <td class="text-[#6a6e73]"
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
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="label" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->feeCategory->name ?? '—' }}</span>
                                <div x-show="editing">
                                    <select x-model="value" @change="save()" class="ui-select">
                                        <option value=""></option>
                                        @foreach($feeCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73]"
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
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="value || '—'" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->email ?: '—' }}</span>
                                <div x-show="editing">
                                    <input type="email" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td class="text-[#6a6e73]"
                                data-field="joined_at"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($member->joined_at->format('Y-m-d')),
                                    display: @js($member->joined_at->format('d.m.Y')),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveMemberField({{ $member->id }}, 'joined_at', this.value);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.display = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="display" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'">{{ $member->joined_at->format('d.m.Y') }}</span>
                                <div x-show="editing">
                                    <input type="date" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('members.edit', $member) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.edit') }}</a>
                                    <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm({{ Js::from(__('members::admin.members.confirm_leave')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-ghost">{{ __('members::admin.members.mark_departed') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
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
</script>
@endsection
