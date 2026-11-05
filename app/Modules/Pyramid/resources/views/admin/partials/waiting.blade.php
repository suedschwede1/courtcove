{{-- Waiting list with the two ways in: admit at the bottom, or release to challenge in. Needs: $ladder, $waiting. --}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.waiting.title')">
    <div class="flex flex-col gap-4">
        @if ($waiting->isEmpty())
            <p class="text-ink-2">{{ __('pyramid::admin.ladder.waiting.none') }}</p>
        @endif

        @foreach ($waiting as $player)
            @php
                $playerId = (int) $player->id;
                $entering = $player->status === 'entering';
                $gender = __('pyramid::admin.ladder.gender.'.($player->gender === 'f' || $player->gender === 'm' ? $player->gender : 'none'));
            @endphp
            <div class="flex flex-col gap-2 border-t border-line pt-4 first:border-t-0 first:pt-0">
                <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                    <span>{{ __('pyramid::admin.ladder.waiting.line', [
                        'label' => $player->label,
                        'gender' => $gender,
                        'age' => $player->age === null ? __('pyramid::admin.ladder.waiting.age_unknown') : __('pyramid::admin.ladder.waiting.age_years', ['count' => (int) $player->age]),
                        'itn' => $player->itn === null ? __('pyramid::admin.ladder.waiting.itn_none') : PyramidFormat::itn($player->itn),
                    ]) }}</span>
                    @if ($entering)
                        <x-ui.badge tone="attention">{{ __('pyramid::admin.ladder.waiting.entering') }}</x-ui.badge>
                    @endif
                </p>

                {{-- Only a waiting player can be admitted: a released one must first be taken back, so "admit" never silently overwrites a release. --}}
                @unless ($entering)
                    <form method="POST" action="{{ PyramidLinks::adminPlayerAction('admit', 'admit', $playerId) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-ui.field :for="'pyramid-wi-'.$playerId" :label="__('pyramid::admin.ladder.waiting.itn')" class="w-24">
                            <x-ui.input name="itn" :id="'pyramid-wi-'.$playerId" :value="(string) $player->itn" :placeholder="PyramidFormat::itn($ladder->itn_default)" inputmode="decimal" />
                        </x-ui.field>
                        <x-ui.button type="submit" :block="false" class="bg-court text-on-court">{{ __('pyramid::admin.ladder.waiting.admit') }}</x-ui.button>
                    </form>
                @endunless

                <form method="POST" action="{{ PyramidLinks::adminPlayerAction('release-entry', 'release-entry', $playerId) }}">
                    @csrf
                    <input type="hidden" name="release" value="{{ $entering ? '0' : '1' }}">
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ $entering ? __('pyramid::admin.ladder.waiting.unrelease') : __('pyramid::admin.ladder.waiting.release') }}</x-ui.button>
                </form>
            </div>
        @endforeach
    </div>
</x-ui.card>
