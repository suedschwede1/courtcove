{{-- Account card "WhatsApp-Benachrichtigungen". $whatsapp comes from App\Services\Notifications\AccountCard. --}}
@php
    $privacyUrl = trim((string) \App\Models\Option::getValue('client.website.privacy', ''));
    $card = $whatsapp;
    $reload = old('_whatsapp_form') === '1';
    $weekdaysOn = array_map('intval', (array) ($reload ? old('weekdays', []) : $card['filters']['weekdays']));
    $quantitiesOn = array_map('intval', (array) ($reload ? old('quantities', []) : $card['filters']['quantities']));
    $timeFrom = $reload ? (string) old('time_from', '') : $card['filters']['time_from'];
    $timeTo = $reload ? (string) old('time_to', '') : $card['filters']['time_to'];
    $searchOn = $reload ? old('search') === '1' : $card['searchOn'];
    $filtersUsed = $weekdaysOn !== [] || $quantitiesOn !== [] || $timeFrom !== '' || $timeTo !== '';
    $filterErrors = $errors->hasAny(['weekdays', 'time_from', 'time_to', 'quantities']) || $errors->has('weekdays.0') || $errors->has('quantities.0');
@endphp
@if($card['state'] !== 'hidden')
<x-ui.card :title="__('booking.notifications.account_card.title')" id="whatsapp" data-whatsapp-card="{{ $card['state'] }}">
    <div class="flex flex-col gap-4">
        <p class="ds-field__help">{{ __('booking.notifications.account_card.intro') }}</p>

        @if($card['state'] === 'active')
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge tone="good">{{ __('booking.notifications.account_card.badge_active') }}</x-ui.badge>
                <span class="ds-field__help">{{ __('booking.notifications.account_card.consented_at', ['date' => $card['consentedAt']->isoFormat('L')]) }}</span>
            </div>
            @if($card['pausedReason'] !== null)
                <x-ui.notice tone="attention" data-whatsapp-paused="{{ $card['pausedReason'] }}">{{ __('booking.notifications.account_card.paused', ['reason' => __('booking.notifications.refused.'.$card['pausedReason'])]) }}</x-ui.notice>
            @endif
            @if($card['maskedPhone'] !== null)
                <p class="ds-field__help">{{ __('booking.notifications.account_card.number_label', ['number' => $card['maskedPhone']]) }}</p>
            @endif
            <p class="ds-field__help">{{ __('booking.notifications.account_card.own_number_hint') }}</p>

            <form method="POST" action="{{ route('account.whatsapp.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="_whatsapp_form" value="1">

                <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" aria-describedby="whatsapp-search-help">
                    <x-ui.option-tile type="checkbox" name="search" value="1" :label="__('booking.notifications.account_card.search_switch')" :checked="$searchOn" />
                </x-ui.option-group>
                <p class="ds-field__help" id="whatsapp-search-help">{{ __('booking.notifications.account_card.search_help') }}</p>

                <x-ui.disclosure :summary="__('booking.notifications.account_card.filters')" :open="$filtersUsed || $filterErrors">
                    <div class="flex flex-col gap-4">
                        <p class="ds-field__help">{{ __('booking.notifications.account_card.filters_help') }}</p>

                        <x-ui.option-group :legend="__('booking.notifications.account_card.weekdays_label')" :columns="3">
                            @foreach(__('booking.notifications.account_card.weekdays') as $day => $dayLabel)
                                <x-ui.option-tile type="checkbox" name="weekdays[]" :value="$day" :label="$dayLabel" :checked="in_array($day, $weekdaysOn, true)" />
                            @endforeach
                        </x-ui.option-group>
                        @if($errors->has('weekdays.0') || $errors->has('weekdays'))
                            <p class="ds-field__error">{{ $errors->first('weekdays.0') ?: $errors->first('weekdays') }}</p>
                        @endif

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field :label="__('booking.notifications.account_card.time_from')" for="wa-time-from">
                                <x-ui.input type="time" name="time_from" id="wa-time-from" :value="$timeFrom" />
                            </x-ui.field>
                            <x-ui.field :label="__('booking.notifications.account_card.time_to')" for="wa-time-to">
                                <x-ui.input type="time" name="time_to" id="wa-time-to" :value="$timeTo" />
                            </x-ui.field>
                        </div>
                        <p class="ds-field__help">{{ __('booking.notifications.account_card.time_help') }}</p>

                        <x-ui.option-group :legend="__('booking.notifications.account_card.quantities_label')" :columns="2">
                            @foreach(__('booking.notifications.account_card.quantities') as $quantity => $quantityLabel)
                                <x-ui.option-tile type="checkbox" name="quantities[]" :value="$quantity" :label="$quantityLabel" :checked="in_array($quantity, $quantitiesOn, true)" />
                            @endforeach
                        </x-ui.option-group>
                        @if($errors->has('quantities.0') || $errors->has('quantities'))
                            <p class="ds-field__error">{{ $errors->first('quantities.0') ?: $errors->first('quantities') }}</p>
                        @endif
                    </div>
                </x-ui.disclosure>

                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.notifications.account_card.save') }}</x-ui.button>
                </div>
            </form>

            <form method="POST" action="{{ route('account.whatsapp.revoke') }}" class="flex justify-end">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.notifications.account_card.revoke') }}</x-ui.button>
            </form>
        @else
            @if($card['notice'] !== null)
                <x-ui.notice tone="neutral" data-whatsapp-notice="{{ $card['notice']['kind'] }}">
                    @if($card['notice']['kind'] === 'stop')
                        {{ __('booking.notifications.account_card.notice_stop', ['date' => $card['notice']['date']->isoFormat('L')]) }}
                    @else
                        {{ __('booking.notifications.account_card.notice_phone_changed') }}
                    @endif
                </x-ui.notice>
            @endif

            @if($card['state'] === 'locked')
                <x-ui.notice tone="neutral" data-whatsapp-reason="{{ $card['reason'] }}">
                    {{ __('booking.notifications.refused.'.$card['reason']) }}
                    @if($card['phoneReason'])
                        <a href="{{ route('account.edit') }}#phone" class="ds-admin-link">{{ __('booking.notifications.account_card.profile_link') }}</a>
                    @endif
                </x-ui.notice>
                <div class="flex justify-end">
                    <x-ui.button type="button" variant="dark" :block="false" :disabled="true">{{ __('booking.notifications.account_card.locked_button') }}</x-ui.button>
                </div>
            @else
                <form method="POST" action="{{ route('account.whatsapp.enable') }}" class="flex flex-col gap-4">
                    @csrf
                    @if($card['maskedPhone'] !== null)
                        <p class="ds-field__help">{{ __('booking.notifications.account_card.number_label', ['number' => $card['maskedPhone']]) }}</p>
                    @endif
                    <p class="ds-field__help">{{ __('booking.notifications.account_card.own_number_hint') }}</p>

                    <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" aria-describedby="whatsapp-privacy-help">
                        <x-ui.option-tile type="checkbox" name="consent" value="1" :label="__('booking.notifications.consent_text_v1')" :checked="false" />
                    </x-ui.option-group>
                    @error('consent')
                        <p class="ds-field__error">{{ $message }}</p>
                    @enderror
                    <p class="ds-field__help" id="whatsapp-privacy-help">
                        {{ __('booking.notifications.privacy_notice') }}
                        @if($privacyUrl !== '')
                            <a href="{{ $privacyUrl }}" class="ds-admin-link" target="_blank" rel="noopener noreferrer">{{ __('booking.notifications.account_card.privacy_link') }}</a>
                        @endif
                    </p>

                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.notifications.account_card.enable') }}</x-ui.button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</x-ui.card>
@endif
