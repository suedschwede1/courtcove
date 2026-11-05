@extends('members::layout')
@section('members-title', __('members::admin.functions.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.functions.title')">
    <x-slot:actions>
        <x-ui.button :block="false" icon="plus" :href="route('members.functions.create')">{{ __('members::admin.functions.new') }}</x-ui.button>
    </x-slot:actions>
</x-members::page-header>

<x-ui.card class="mbr-table-card" padding="none" as="div">
    <x-ui.table :caption="__('members::admin.functions.title')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('members::admin.functions.name') }}</th>
                <th scope="col">{{ __('members::admin.functions.is_board') }}</th>
                <th scope="col">{{ __('members::admin.functions.mail_category') }}</th>
                <th scope="col">{{ __('members::admin.functions.tasks') }}</th>
                <th scope="col" class="mbr-cell-actions">{{ __('members::admin.common.actions') }}</th>
            </tr>
        </x-slot:head>
        @foreach($clubRoles as $clubRole)
            <tr>
                <td class="ds-cell--title" data-label="{{ __('members::admin.functions.name') }}">{{ $clubRole->name }}</td>
                <td data-label="{{ __('members::admin.functions.is_board') }}">
                    @if($clubRole->is_board)
                        <x-ui.badge tone="neutral">{{ __('members::admin.members.club_roles_board') }}</x-ui.badge>
                    @else
                        <span class="text-ink-2">–</span>
                    @endif
                </td>
                <td data-label="{{ __('members::admin.functions.mail_category') }}">
                    @forelse($clubRole->mailCategories as $mailCategory)
                        <x-ui.badge tone="neutral">{{ $mailCategory->name }}</x-ui.badge>
                    @empty
                        <span class="text-ink-2">–</span>
                    @endforelse
                </td>
                <td class="text-ink-2" data-label="{{ __('members::admin.functions.tasks') }}">{{ $clubRole->tasks ?? '–' }}</td>
                <td class="mbr-cell-actions" data-label="{{ __('members::admin.common.actions') }}">
                    <div class="mbr-row-actions">
                        <x-ui.button variant="secondary" :block="false" :href="route('members.functions.edit', $clubRole)">{{ __('members::admin.common.edit') }}</x-ui.button>
                        <form method="POST" action="{{ route('members.functions.destroy', $clubRole) }}" onsubmit="return confirm({{ Js::from(__('members::admin.functions.confirm_delete')) }})">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" :block="false">{{ __('members::admin.common.delete') }}</x-ui.button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-ui.card>
@endsection
