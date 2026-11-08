{{-- Budget threshold and pause notices of the WhatsApp channel; $notices from WhatsAppAdminStatus::notices(), $resume = show the resume button. --}}
@if($notices['state'] === 'stopped')
    <x-ui.notice tone="problem" data-whatsapp-budget-notice="stopped">{{ __('booking.admin.whatsapp.budget_stopped', ['date' => $notices['resumeDate']]) }}</x-ui.notice>
@elseif($notices['state'] === 'warn')
    <x-ui.notice tone="attention" data-whatsapp-budget-notice="warn">{{ __('booking.admin.whatsapp.budget_warn', ['percent' => $notices['percent']]) }}</x-ui.notice>
@endif
@if($notices['paused'])
    <x-ui.notice tone="problem" data-whatsapp-paused>
        <p>{{ __('booking.admin.whatsapp.paused') }}</p>
        @if($resume ?? false)
            <form method="POST" action="{{ route('admin.whatsapp.resume') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.whatsapp.resume') }}</x-ui.button>
            </form>
        @else
            @can('admin.config')
                <p><a href="{{ route('admin.whatsapp.edit') }}">{{ __('booking.admin.whatsapp.open_page') }}</a></p>
            @endcan
        @endif
    </x-ui.notice>
@endif
