{{-- Account card "WhatsApp-Benachrichtigungen". $whatsapp comes from App\Services\Notifications\AccountCard: one number section,
     one list row per consent purpose of the notification registry (texts, types and filters all come from there), one common
     privacy note. The row switch IS the consent: a POST form with the switch as its submit button (on: consent with the
     purpose's sentence and version; off: withdrawal at once). Works the same with and without JavaScript. --}}
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
<x-ui.card :title="__('booking.notifications.account_card.title')" id="whatsapp" data-whatsapp-card="shown">
    <div class="flex flex-col gap-5">
        <p class="ds-field__help">{{ __('booking.notifications.account_card.intro') }}</p>

        <div class="flex flex-col gap-2" data-whatsapp-number>
            <h3 class="ds-card__eyebrow">{{ __('booking.notifications.account_card.number_heading') }}</h3>
            <p class="ds-field__help">
                @if($whatsapp['maskedPhone'] !== null)
                    {{ __('booking.notifications.account_card.number_label', ['number' => $whatsapp['maskedPhone']]) }}
                @endif
                {{ __('booking.notifications.account_card.own_number_hint') }}
                <a href="{{ route('account.edit') }}#phone" class="ds-admin-link">{{ __('booking.notifications.account_card.profile_link') }}</a>
            </p>
            @if($whatsapp['reason'] !== null)
                <x-ui.notice tone="neutral" id="whatsapp-reason" data-whatsapp-reason="{{ $whatsapp['reason'] }}">
                    {{ $refused($whatsapp['reason']) }}
                    @if($whatsapp['paused'])
                        {{ __('booking.notifications.account_card.paused_all') }}
                    @endif
                </x-ui.notice>
            @endif
            @if($whatsapp['notice'] !== null)
                <x-ui.notice tone="neutral" data-whatsapp-notice="{{ $whatsapp['notice']['kind'] }}">
                    @if($whatsapp['notice']['kind'] === 'stop')
                        {{ __('booking.notifications.account_card.notice_stop', ['date' => $whatsapp['notice']['date']->isoFormat('L')]) }}
                        @if($whatsapp['reason'] === null)
                            {{ __('booking.notifications.account_card.notice_stop_again') }}
                        @endif
                    @else
                        {{ __('booking.notifications.account_card.notice_phone_changed') }}
                        @if($whatsapp['reason'] === null)
                            {{ __('booking.notifications.account_card.notice_phone_changed_again') }}
                        @endif
                    @endif
                </x-ui.notice>
            @endif
        </div>

        <div class="flex flex-col gap-2" data-whatsapp-list>
            <h3 class="ds-card__eyebrow">{{ __('booking.notifications.account_card.list_heading') }}</h3>
            <ul class="flex flex-col divide-y divide-line" role="list">
                @foreach($whatsapp['purposes'] as $row)
                    @php
                        $key = $row['key'];
                        $routeArgs = ['purpose' => $key];
                        $reload = old('_whatsapp_form') === $key;
                        $on = $row['state'] === 'active';
                        $locked = $row['state'] === 'locked';
                        // A request without the switch's consent value comes back with the error at this row.
                        $consentError = $row['state'] === 'ready' && ($reload || $single) && $errors->has('consent');
                        $describedBy = 'whatsapp-desc-'.$key.' whatsapp-consent-'.$key
                            .($row['reason'] !== null ? ' whatsapp-reason-'.$key : '')
                            .($locked && $whatsapp['reason'] !== null ? ' whatsapp-reason' : '')
                            .($consentError ? ' whatsapp-consent-error-'.$key : '');
                    @endphp
                    <li id="whatsapp-{{ $key }}" tabindex="-1" class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0" data-whatsapp-purpose="{{ $key }}" data-whatsapp-row="{{ $row['state'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-col gap-1">
                                <h4 class="ds-field__label" id="whatsapp-title-{{ $key }}">{{ $row['label'] }}</h4>
                                <p class="ds-field__help" id="whatsapp-desc-{{ $key }}">{{ $row['description'] }}</p>
                            </div>
                            @if($locked)
                                <button type="button" class="ds-switch ds-switch--card" role="switch" aria-checked="false" aria-labelledby="whatsapp-title-{{ $key }}" aria-describedby="{{ $describedBy }}" disabled>
                                    <span class="ds-switch__label" aria-hidden="true">{{ __('booking.notifications.account_card.switch_off') }}</span>
                                    <span class="ds-switch__track" aria-hidden="true"><span class="ds-switch__knob"></span></span>
                                </button>
                            @else
                                {{-- The switch is the submit button: on = consent (POST), off = withdrawal (DELETE). --}}
                                <form method="POST" action="{{ $on ? route('account.whatsapp.revoke', $routeArgs) : route('account.whatsapp.enable', $routeArgs) }}" class="shrink-0" data-whatsapp-consent-version="{{ $row['consentVersion'] }}">
                                    @csrf
                                    @if($on)
                                        @method('DELETE')
                                        <button type="submit" class="ds-switch ds-switch--card" role="switch" aria-checked="true" aria-labelledby="whatsapp-title-{{ $key }}" aria-describedby="{{ $describedBy }}">
                                    @else
                                        <input type="hidden" name="_whatsapp_form" value="{{ $key }}">
                                        {{-- The version of the sentence shown; an outdated one is refused (text changed meanwhile). --}}
                                        <input type="hidden" name="consent_version" value="{{ $row['consentVersion'] }}">
                                        <button type="submit" name="consent" value="1" class="ds-switch ds-switch--card" role="switch" aria-checked="false" aria-labelledby="whatsapp-title-{{ $key }}" aria-describedby="{{ $describedBy }}">
                                    @endif
                                        <span class="ds-switch__label" aria-hidden="true">{{ $on ? __('booking.notifications.account_card.switch_on') : __('booking.notifications.account_card.switch_off') }}</span>
                                        <span class="ds-switch__track" aria-hidden="true"><span class="ds-switch__knob"></span></span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- The consent sentence belongs to the switch (aria-describedby); the full text is one click away. --}}
                        <div class="flex flex-col gap-1">
                            <p class="ds-field__help" id="whatsapp-consent-{{ $key }}">{{ $row['consentSentence'] }}</p>
                            @if($on && $row['consentedAt'] !== null)
                                <p class="ds-field__help">{{ __('booking.notifications.account_card.consented_at', ['date' => $row['consentedAt']->isoFormat('L')]) }}</p>
                            @endif
                            @if($row['consentText'] !== null)
                                <details>
                                    <summary class="ds-admin-link cursor-pointer">{{ __('booking.notifications.account_card.consent_more') }}</summary>
                                    <p class="ds-field__help mt-1">{{ $row['consentText'] }}</p>
                                </details>
                            @endif
                        </div>
                        @if($consentError)
                            <p class="ds-field__error" id="whatsapp-consent-error-{{ $key }}">{{ $errors->first('consent') }}</p>
                        @endif

                        @if($row['reason'] !== null)
                            <x-ui.notice tone="neutral" id="whatsapp-reason-{{ $key }}" data-whatsapp-reason="{{ $row['reason'] }}">{{ $refused($row['reason']) }}</x-ui.notice>
                        @endif
                        @if($row['pausedReason'] !== null)
                            <x-ui.notice tone="attention" data-whatsapp-paused="{{ $row['pausedReason'] }}">{{ __('booking.notifications.account_card.paused', ['reason' => $refused($row['pausedReason'])]) }}</x-ui.notice>
                        @endif
                        @if($row['notice'] !== null)
                            <x-ui.notice tone="neutral" data-whatsapp-notice="{{ $row['notice']['kind'] }}">
                                @if($row['notice']['kind'] === 'stop')
                                    {{ __('booking.notifications.account_card.notice_stop', ['date' => $row['notice']['date']->isoFormat('L')]) }}
                                    @if($whatsapp['reason'] === null && $row['reason'] === null)
                                        {{ __('booking.notifications.account_card.notice_stop_again') }}
                                    @endif
                                @else
                                    {{ __('booking.notifications.account_card.notice_phone_changed') }}
                                    @if($whatsapp['reason'] === null && $row['reason'] === null)
                                        {{ __('booking.notifications.account_card.notice_phone_changed_again') }}
                                    @endif
                                @endif
                            </x-ui.notice>
                        @endif

                        @php
                            $hasChoice = collect($row['types'])->contains(fn (array $type): bool => $type['choice']);
                            $hasSettings = $on && collect($row['types'])->contains(fn (array $type): bool => $type['choice'] || $type['filters'] !== []);
                            // On, but no type subscribed (no opt-in type, or all switched off before): ask for a choice.
                            $needsChoice = $hasSettings && ! collect($row['types'])->contains(fn (array $type): bool => $type['on']);
                        @endphp
                        @if($hasSettings)
                            @php
                                $settingsOpen = $needsChoice;
                                foreach ($row['types'] as $type) {
                                    $prefix = 'filters.'.$type['slug'];
                                    foreach ($type['values'] as $field => $stored) {
                                        $current = $reload ? old($prefix.'.'.$field) : $stored;
                                        $settingsOpen = $settingsOpen || ($current !== [] && $current !== '' && $current !== null) || $listErrors($prefix.'.'.$field) !== [];
                                    }
                                }
                            @endphp
                            <x-ui.disclosure :summary="$hasChoice ? __('booking.notifications.account_card.settings') : __('booking.notifications.account_card.filters')" :open="$settingsOpen" data-whatsapp-settings="{{ $key }}">
                                <form method="POST" action="{{ route('account.whatsapp.update', $routeArgs) }}" class="flex flex-col gap-4">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_whatsapp_form" value="{{ $key }}">
                                    @if($needsChoice)
                                        <x-ui.notice tone="attention" data-whatsapp-choose>{{ __('booking.notifications.account_card.choose_types') }}</x-ui.notice>
                                    @endif

                                    @foreach($row['types'] as $type)
                                        @php
                                            $slug = $type['slug'];
                                            $prefix = 'filters.'.$slug;
                                            $typeOn = $reload ? old('on.'.$slug) === '1' : $type['on'];
                                            $value = fn (string $field) => $reload ? old($prefix.'.'.$field, is_array($type['values'][$field]) ? [] : '') : $type['values'][$field];
                                        @endphp
                                        @if($type['choice'])
                                            <x-ui.option-group :columns="2" class="ds-admin-flags ds-admin-flags--single" :aria-describedby="'whatsapp-help-'.$slug">
                                                <x-ui.option-tile type="checkbox" :name="'on['.$slug.']'" value="1" :label="$type['label']" :checked="$typeOn" />
                                            </x-ui.option-group>
                                            <p class="ds-field__help" id="whatsapp-help-{{ $slug }}">{{ $type['description'] }}</p>
                                        @else
                                            <input type="hidden" name="on[{{ $slug }}]" value="1">
                                        @endif

                                        @if($type['filters'] !== [])
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
                                        @endif
                                    @endforeach

                                    <div class="flex justify-end">
                                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('booking.notifications.account_card.save') }}</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.disclosure>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="ds-field__help" id="whatsapp-privacy">
            {{ __('booking.notifications.privacy_notice') }}
            @if($privacyUrl !== '')
                <a href="{{ $privacyUrl }}" class="ds-admin-link" target="_blank" rel="noopener noreferrer">{{ __('booking.notifications.account_card.privacy_link') }}</a>
            @endif
        </p>
    </div>
</x-ui.card>
@endif
