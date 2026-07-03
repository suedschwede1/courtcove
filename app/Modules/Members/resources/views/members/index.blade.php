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
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label">{{ __('members::admin.members.status') }}</label>
                    <select name="status" class="ui-select">
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
                        <th>{{ __('members::admin.members.email') }}</th>
                        <th>{{ __('members::admin.members.joined_at') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        <tr>
                            <td class="text-[#6a6e73]">{{ $member->member_number }}</td>
                            <td class="font-medium">{{ $member->fullName() }}</td>
                            <td class="text-[#6a6e73]">{{ $member->email ?: '—' }}</td>
                            <td class="text-[#6a6e73]">{{ $member->joined_at->format('d.m.Y') }}</td>
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
@endsection
