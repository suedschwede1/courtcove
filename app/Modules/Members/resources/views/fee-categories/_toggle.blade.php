{{-- "Deaktivieren" / "Reaktivieren" for one fee category (plain POST, no JavaScript needed). --}}
@if($category->isDeactivated())
    <form method="POST" action="{{ route('members.fee-categories.reactivate', $category->id) }}">
        @csrf
        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.fee_categories.reactivate') }}</x-ui.button>
    </form>
@else
    <form method="POST" action="{{ route('members.fee-categories.deactivate', $category->id) }}">
        @csrf
        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.fee_categories.deactivate') }}</x-ui.button>
    </form>
@endif
