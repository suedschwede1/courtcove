@extends('members::layout')
@section('admin-title', __('members::admin.functions.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <h1>{{ __('members::admin.functions.title') }}</h1>
        <a href="{{ route('members.functions.create') }}" class="ui-btn ui-btn-primary">{{ __('members::admin.functions.new') }}</a>
    </div>
    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.functions.name') }}</th>
                        <th>{{ __('members::admin.functions.is_board') }}</th>
                        <th>{{ __('members::admin.functions.mail_category') }}</th>
                        <th>{{ __('members::admin.functions.tasks') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clubRoles as $clubRole)
                        <tr>
                            <td class="font-medium">{{ $clubRole->name }}</td>
                            <td>
                                @if($clubRole->is_board)
                                    <span class="ui-badge ui-badge-info">{{ __('members::admin.members.club_roles_board') }}</span>
                                @else
                                    <span class="text-[#6a6e73]">–</span>
                                @endif
                            </td>
                            <td>
                                @if($clubRole->mailCategory)
                                    <span class="ui-badge ui-badge-info">{{ $clubRole->mailCategory->name }}</span>
                                @else
                                    <span class="text-[#6a6e73]">–</span>
                                @endif
                            </td>
                            <td class="text-[#6a6e73]">{{ $clubRole->tasks ?? '–' }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('members.functions.edit', $clubRole) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.edit') }}</a>
                                    <form method="POST" action="{{ route('members.functions.destroy', $clubRole) }}" onsubmit="return confirm({{ Js::from(__('members::admin.functions.confirm_delete')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.delete') }}</button>
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
