{{--
    Configuration tab of the event administration (old admin/config.phtml), right column of
    Admin-Mixed-Event: Konfiguration (courts, blank scorecards), Berechtigungen (global admins only),
    Gegner-Sperren, Testmodus (switch for global admins only; event admins see the state).
--}}
@extends('mixed::admin.layout')

@section('title', $event->title)

@section('mixed-admin-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $eventId = (int) $event->id;
    $labels = $participants->mapWithKeys(fn ($p) => [(int) $p->id => (string) $p->label])->all();
    $participantOptions = $participants->mapWithKeys(fn ($p) => [(int) $p->id => __('mixed::admin.config.avoid_option', [
        'label' => $p->label,
        'gender' => $p->gender === 'f' || $p->gender === 'm' ? MixedFormat::gender($p->gender) : __('mixed::common.unknown_player'),
    ])])->all();
    $userOptions = $users->mapWithKeys(fn ($u) => [(int) $u->uid => (string) $u->alias])->all();
    $roleOptions = __('mixed::admin.config.roles');
@endphp
@include('mixed::admin.partials.event-head')

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'konfiguration', 'adminView' => true, 'canAdmin' => true])

<div class="grid items-start gap-5 lg:grid-cols-2">
    <div class="flex flex-col gap-5">
        <x-ui.card :title="__('mixed::admin.config.title')">
            <div class="flex flex-col gap-5">
                <form method="POST" action="{{ route('mixed.admin.set-court-count', ['event' => $eventId]) }}" class="flex flex-col gap-3">
                    @csrf
                    <x-ui.field for="mixed-court-count" :label="__('mixed::admin.config.court_count')" :help="__('mixed::admin.config.court_count_help')">
                        <x-ui.input type="number" name="court_count" id="mixed-court-count" :value="(string) $event->court_count" min="1" max="99" inputmode="numeric" required />
                    </x-ui.field>
                    <div>
                        <x-ui.button type="submit" :block="false">{{ __('mixed::admin.config.save') }}</x-ui.button>
                    </div>
                </form>

                <form method="GET" action="{{ route('mixed.scorecard-blank', ['event' => $eventId]) }}" target="_blank" class="flex flex-col gap-3 border-t border-line-soft pt-5">
                    <h3 class="font-display text-display-s text-ink">{{ __('mixed::admin.config.blank_title') }}</h3>
                    <p class="text-body-s text-ink-2">{{ __('mixed::admin.config.blank_help') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @unless ($event->isMexicano())
                            <x-ui.field for="mixed-blank-size" :label="__('mixed::admin.config.blank_size')">
                                <x-ui.select name="size" id="mixed-blank-size" :options="['4' => __('mixed::admin.config.blank_size_4'), '5' => __('mixed::admin.config.blank_size_5')]" selected="4" />
                            </x-ui.field>
                        @endunless
                        <x-ui.field for="mixed-blank-count" :label="__('mixed::admin.config.blank_count')">
                            <x-ui.input type="number" name="count" id="mixed-blank-count" value="8" min="2" max="40" inputmode="numeric" />
                        </x-ui.field>
                    </div>
                    <div>
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('mixed::admin.config.blank_print') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.card>

        @if ($isGlobalAdmin)
            <x-ui.card :title="__('mixed::admin.config.grants_title')">
                <div class="flex flex-col gap-4">
                    <p class="text-body-s text-ink-2">{{ __('mixed::admin.config.grants_help') }}</p>
                    @if ($grants->isEmpty())
                        <p class="text-body-s text-ink-3">{{ __('mixed::admin.config.grants_none') }}</p>
                    @else
                        <ul class="flex flex-col">
                            @foreach ($grants as $grant)
                                @php
                                    $uid = (int) $grant->uid;
                                    $userLabel = $userLabels[$uid] ?? __('mixed::admin.config.grant_unknown_user', ['uid' => $uid]);
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 border-t border-line-soft py-2 first:border-t-0">
                                    <span class="text-body text-ink">{{ __('mixed::admin.config.grant_line', ['user' => $userLabel, 'role' => $roleOptions[$grant->role] ?? $grant->role]) }}</span>
                                    <form method="POST" action="{{ route('mixed.admin.revoke', ['event' => $eventId]) }}">
                                        @csrf
                                        <input type="hidden" name="uid" value="{{ $uid }}">
                                        <x-ui.button type="submit" variant="secondary" :block="false" aria-label="{{ __('mixed::admin.config.grant_remove_label', ['user' => $userLabel]) }}">{{ __('mixed::admin.config.grant_remove') }}</x-ui.button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="POST" action="{{ route('mixed.admin.grant', ['event' => $eventId]) }}" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        @csrf
                        <x-ui.field for="mixed-grant-uid" :label="__('mixed::admin.config.grant_user')">
                            <x-ui.select name="uid" id="mixed-grant-uid" :options="$userOptions" :placeholder="__('mixed::admin.config.grant_user_placeholder')" />
                        </x-ui.field>
                        <x-ui.field for="mixed-grant-role" :label="__('mixed::admin.config.grant_role')">
                            <x-ui.select name="role" id="mixed-grant-role" :options="$roleOptions" />
                        </x-ui.field>
                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('mixed::admin.config.grant_add') }}</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @endif
    </div>

    <div class="flex flex-col gap-5">
        <x-ui.card :title="__('mixed::admin.config.avoid_title')">
            <div class="flex flex-col gap-4">
                <p class="text-body-s text-ink-2">{{ __('mixed::admin.config.avoid_help') }}</p>
                @if ($avoidPairs->isEmpty())
                    <p class="text-body-s text-ink-3">{{ __('mixed::admin.config.avoid_none') }}</p>
                @else
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($avoidPairs as $pair)
                            @php
                                $a = $labels[(int) $pair->participant_a] ?? __('mixed::common.unknown_player');
                                $b = $labels[(int) $pair->participant_b] ?? __('mixed::common.unknown_player');
                            @endphp
                            <li class="inline-flex items-center gap-1 rounded-pill bg-sand py-1 pl-3 pr-1 text-body-s font-semibold text-ink">
                                <span class="sr-only">{{ __('mixed::admin.config.avoid_pair', ['a' => $a, 'b' => $b]) }}</span>
                                <span aria-hidden="true" class="inline-flex items-center gap-1.5">{{ $a }}<x-ui.icon name="arrows-left-right" :size="16" class="text-ink-3" />{{ $b }}</span>
                                <form method="POST" action="{{ route('mixed.admin.remove-avoid-pair', ['event' => $eventId]) }}">
                                    @csrf
                                    <input type="hidden" name="pair_id" value="{{ (int) $pair->id }}">
                                    <x-ui.icon-button type="submit" icon="close" variant="text" :label="__('mixed::admin.config.avoid_remove_label', ['a' => $a, 'b' => $b])" />
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('mixed.admin.add-avoid-pair', ['event' => $eventId]) }}" class="flex flex-col gap-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field for="mixed-avoid-a" :label="__('mixed::admin.config.avoid_a')">
                            <x-ui.select name="participant_a" id="mixed-avoid-a" :options="$participantOptions" :placeholder="__('mixed::admin.config.avoid_a')" />
                        </x-ui.field>
                        <x-ui.field for="mixed-avoid-b" :label="__('mixed::admin.config.avoid_b')">
                            <x-ui.select name="participant_b" id="mixed-avoid-b" :options="$participantOptions" :placeholder="__('mixed::admin.config.avoid_b')" />
                        </x-ui.field>
                    </div>
                    <x-ui.button type="submit" variant="secondary">{{ __('mixed::admin.config.avoid_add') }}</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('mixed::admin.config.test_title')">
            <div class="flex flex-col gap-3">
                <p class="text-body-s text-ink-2">{{ __('mixed::admin.config.test_help') }}</p>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-ink">{{ $event->is_test ? __('mixed::admin.config.test_on') : __('mixed::admin.config.test_off') }}</p>
                    @if ($isGlobalAdmin)
                        <form method="POST" action="{{ route('mixed.admin.set-test-mode', ['event' => $eventId]) }}">
                            @csrf
                            <input type="hidden" name="is_test" value="{{ $event->is_test ? '0' : '1' }}">
                            <x-ui.button type="submit" variant="secondary" :block="false">{{ $event->is_test ? __('mixed::admin.config.test_switch_off') : __('mixed::admin.config.test_switch_on') }}</x-ui.button>
                        </form>
                    @endif
                </div>
                @unless ($isGlobalAdmin)
                    <p class="text-body-s text-ink-3">{{ __('mixed::admin.config.test_global_only') }}</p>
                @endunless
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
