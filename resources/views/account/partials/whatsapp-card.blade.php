{{-- Account cards "WhatsApp-Benachrichtigungen", one per consent purpose. $whatsapp comes from App\Services\Notifications\AccountCard;
     everything purpose or type specific (texts, switches, filters) comes from the notification registry. --}}
@php
    $privacyUrl = trim((string) \App\Models\Option::getValue('client.website.privacy', ''));
    // Only a web address becomes a link (no javascript: or other schemes from the option).
    $privacyUrl = preg_match('#^https?://#i', $privacyUrl) === 1 ? $privacyUrl : '';
    // Every message of a list and of each of its elements (weekdays.0, weekdays.1, ...), each text once.
    $listErrors = fn (string $key): array => array_values(array_unique(\Illuminate\Support\Arr::flatten([$errors->get($key), $errors->get($key.'.*')])));
    $refused = fn (?string $reason): string => __(\App\Http\Controllers\NotificationSettingsController::refusedKey($reason));
    $single = count($whatsapp['purposes']) === 1;
@endphp
@if($whatsapp['state'] !== 'hidden')
@foreach($whatsapp['purposes'] as $card)
@php
    $routeArgs = ['purpose' => $card['key']];
    $reload = old('_whatsapp_form') === $card['key'];
    $title = $single ? __('booking.notifications.account_card.title') : __('booking.notifications.account_card.title_purpose', ['purpose' => $card['label']]);
@endphp
<x-ui.card :title="$title" :id="$loop->first ? 'whatsapp' : 'whatsapp-'.$card['key']" data-whatsapp-card="{{ $card['state'] }}" data-whatsapp-purpose="{{ $card['key'] }}">
    <div class="flex flex-col gap-4">
        <p class="ds-field__help">{{ $card['description'] }}</p>

        @if($card['state'] === 'active')
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge tone="good">{{ __('booking.notifications.account_card.badge_active') }}</x-ui.badge>
                <span class="ds-field__help">{{ __('booking.notifications.account_card.consented_at', ['date' => $card['consentedAt']->isoFormat('L')]) }}</span>
            </div>
            @if($card['pausedReason'] !== null)
                <x-ui.notice tone="attention" data-whatsapp-paused="{{ $card['pausedReason'] }}">{{ __('booking.notifications.account_card.paused', ['reason' => $refused($card['pausedReason'])]) }}</x-ui.notice>
            @endif
            @if($card['maskedPhone'] !== null)
                <p class="ds-field__help">{{ __('booking.notifications.account_card.number_label', ['number' => $card['maskedPhone']]) }}</p>
            @endif
            <p class="ds-field__help">{{ __('booking.notifications.account_card.own_number_hint') }}</p>

            @if($card['types'] !== [])
                <form method="POST" action="{{ route('account.whatsapp.update', $routeArgs) }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_whatsapp_form" value="{{ $card['key'] }}">

                    @foreach($card['types'] as $type)
                        @php
                            $slug = $type['slug'];
                            $prefix = 'filters.'.$slug;
                            $on = $reload ? old('on.'.$slug) === '1' : $type['on'];
                            $value = fn (string $field) => $reload ? old($prefix.'.'.$field, is_array($type['values'][$field]) ? [] : '') : $type['values'][$field];
                            $filtersUsed = false;
                            $filterErrors = false;
                            foreach ($type['values'] as $field => $stored) {
                                $current = $value($field);
                                $filtersUsed = $filtersUsed || ($current !== [] && $current !== '' && $current !== null);
                                $filterErrors = $filterErrors || $listErrors($prefix.'.'.$field) !== [];
                            }
                        @endphp
                        <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" :aria-describedby="'whatsapp-help-'.$slug">
                            <x-ui.option-tile type="checkbox" :name="'on['.$slug.']'" value="1" :label="$type['label']" :checked="$on" />
                        </x-ui.option-group>
                        <p class="ds-field__help" id="whatsapp-help-{{ $slug }}">{{ $type['description'] }} {{ __('booking.notifications.account_card.type_help') }}</p>

                        @if($type['filters'] !== [])
                            <x-ui.disclosure :summary="__('booking.notifications.account_card.filters')" :open="$filtersUsed || $filterErrors">
                                <div class="flex flex-col gap-4">
                                    <p class="ds-field__help">{{ __('booking.notifications.account_card.filters_help') }}</p>

                                    @foreach($type['filters'] as $name => $filter)
                                        @if($filter['kind'] === 'weekdays')
                                            @php $daysOn = array_map('intval', (array) $value($name)); @endphp
                                            <x-ui.option-group :legend="__($filter['labelKey'] ?? 'booking.notifications.account_card.weekdays_label')" :columns="3">
                                                @foreach(__('booking.notifications.account_card.weekdays') as $day => $dayLabel)
                                                    <x-ui.option-tile type="checkbox" :name="'filters['.$slug.']['.$name.'][]'" :value="$day" :label="$dayLabel" :checked="in_array($day, $daysOn, true)" :aria-label="__('booking.notifications.account_card.weekdays_full.'.$day)" />
                                                @endforeach
                                            </x-ui.option-group>
                                            @foreach($listErrors($prefix.'.'.$name) as $message)
                                                <p class="ds-field__error">{{ $message }}</p>
                                            @endforeach
                                        @elseif($filter['kind'] === 'time_range')
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <x-ui.field :label="__('booking.notifications.account_card.time_from')" :for="'wa-'.$slug.'-'.$filter['from']" :error="$errors->first($prefix.'.'.$filter['from'])">
                                                    <x-ui.input type="time" :name="'filters['.$slug.']['.$filter['from'].']'" :id="'wa-'.$slug.'-'.$filter['from']" :value="$value($filter['from'])" />
                                                </x-ui.field>
                                                <x-ui.field :label="__('booking.notifications.account_card.time_to')" :for="'wa-'.$slug.'-'.$filter['to']" :error="$errors->first($prefix.'.'.$filter['to'])">
                                                    <x-ui.input type="time" :name="'filters['.$slug.']['.$filter['to'].']'" :id="'wa-'.$slug.'-'.$filter['to']" :value="$value($filter['to'])" />
                                                </x-ui.field>
                                            </div>
                                            <p class="ds-field__help">{{ __('booking.notifications.account_card.time_help') }}</p>
                                        @elseif($filter['kind'] === 'choice')
                                            @php
                                                $chosen = array_map('strval', (array) $value($name));
                                                $optionLabels = isset($filter['optionsKey']) ? (array) __($filter['optionsKey']) : [];
                                            @endphp
                                            <x-ui.option-group :legend="__($filter['labelKey'] ?? $type['label'])" :columns="2">
                                                @foreach($filter['options'] as $option)
                                                    <x-ui.option-tile type="checkbox" :name="'filters['.$slug.']['.$name.'][]'" :value="$option" :label="$optionLabels[$option] ?? (string) $option" :checked="in_array((string) $option, $chosen, true)" />
                                                @endforeach
                                            </x-ui.option-group>
                                            @foreach($listErrors($prefix.'.'.$name) as $message)
                                                <p class="ds-field__error">{{ $message }}</p>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </div>
                            </x-ui.disclosure>
                        @endif
                    @endforeach

                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.notifications.account_card.save') }}</x-ui.button>
                    </div>
                </form>
            @endif

            <form method="POST" action="{{ route('account.whatsapp.revoke', $routeArgs) }}" class="flex justify-end">
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
                    {{ $refused($card['reason']) }}
                    @if($card['phoneReason'])
                        <a href="{{ route('account.edit') }}#phone" class="ds-admin-link">{{ __('booking.notifications.account_card.profile_link') }}</a>
                    @endif
                </x-ui.notice>
                <div class="flex justify-end">
                    <x-ui.button type="button" variant="dark" :block="false" :disabled="true">{{ __('booking.notifications.account_card.locked_button') }}</x-ui.button>
                </div>
            @else
                <form method="POST" action="{{ route('account.whatsapp.enable', $routeArgs) }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_whatsapp_form" value="{{ $card['key'] }}">
                    @if($card['maskedPhone'] !== null)
                        <p class="ds-field__help">{{ __('booking.notifications.account_card.number_label', ['number' => $card['maskedPhone']]) }}</p>
                    @endif
                    <p class="ds-field__help">{{ __('booking.notifications.account_card.own_number_hint') }}</p>

                    <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" :aria-describedby="'whatsapp-privacy-'.$card['key']">
                        @php
                            // The first sentence is the core statement (bold), the rest explains it.
                            $consentParts = preg_split('/(?<=[.!?])\s+/u', $card['consentText'], 2) ?: [$card['consentText']];
                        @endphp
                        <x-ui.option-tile type="checkbox" name="consent" value="1" :label="$consentParts[0]" :description="$consentParts[1] ?? null" :checked="false" />
                    </x-ui.option-group>
                    @if($reload || $single)
                        @error('consent')
                            <p class="ds-field__error">{{ $message }}</p>
                        @enderror
                    @endif
                    <p class="ds-field__help" id="whatsapp-privacy-{{ $card['key'] }}">
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
@endforeach
@endif
