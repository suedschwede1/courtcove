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
                        @if(($editing ?? null) === $member->id)
                            <tr>
                                <td colspan="7">
                                    <form method="POST" action="{{ route('members.update', $member) }}" class="ui-stack">
                                        @method('PUT')
                                        @foreach($filters as $key => $value)
                                            <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                                        @endforeach
                                        @include('members::members._form')
                                        <div class="ui-form-actions">
                                            <a href="{{ route('members.index', $filters) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
                                            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td class="text-[#6a6e73]">{{ $member->member_number }}</td>
                                <td class="font-medium">{{ $member->fullName() }}</td>
                                <td class="text-[#6a6e73]">{{ $member->age() ?? '—' }}</td>
                                <td class="text-[#6a6e73]">{{ $member->feeCategory->name ?? '—' }}</td>
                                <td class="text-[#6a6e73]">{{ $member->email ?: '—' }}</td>
                                <td class="text-[#6a6e73]">{{ $member->joined_at->format('d.m.Y') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <a href="{{ route('members.index', array_merge($filters, ['edit' => $member->id])) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.edit') }}</a>
                                        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm({{ Js::from(__('members::admin.members.confirm_leave')) }})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn-ghost">{{ __('members::admin.members.mark_departed') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
