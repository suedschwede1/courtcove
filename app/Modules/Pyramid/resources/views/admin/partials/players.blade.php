{{--
    Participant table with search, status filter, inline edit and paging.
    Needs: $ladder, $ranking, $players (paginator), $page, $pages, $total, $search, $status, optional $editId.
    Edit works without scripts: the edit control is a link (?edit=ID) and the requested row is rendered open;
    with Alpine the same link toggles the row in place.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Modules\Pyramid\Support\PyramidStatus;

    $genderLabel = static fn (?string $gender): string => __('pyramid::admin.ladder.gender.'.($gender === 'f' || $gender === 'm' ? $gender : 'none'));
    $editId = (int) ($editId ?? 0);
    $listUrl = PyramidLinks::adminLadder((int) $ladder->id);
    $listQuery = array_filter(['q' => $search, 'status' => $status, 'page' => $page > 1 ? $page : ''], static fn ($value): bool => $value !== '' && $value !== null);
    $statusOptions = [
        '' => __('pyramid::admin.ladder.players.status_all'),
        'active' => __('pyramid::admin.ladder.status.active'),
        'paused' => __('pyramid::admin.ladder.status.paused'),
    ];
@endphp
<x-ui.card padding="none" aria-labelledby="pyramid-admin-players">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft px-4 py-3.5 md:px-5">
        <h2 id="pyramid-admin-players" class="font-display text-display-m text-ink">{{ __('pyramid::admin.ladder.players.title') }}</h2>

        @if ($ranking->isNotEmpty())
            {{-- Search and filter are a GET form: every page and filter stays a link. --}}
            <form method="GET" action="{{ PyramidLinks::adminLadder((int) $ladder->id) }}" class="flex flex-wrap items-center gap-2">
                <div class="w-44"><x-ui.input type="search" name="q" id="pyramid-admin-search" :value="$search" :aria-label="__('pyramid::admin.ladder.players.search_label')" :placeholder="__('pyramid::admin.ladder.players.search_label')" /></div>
                <div class="w-36"><x-ui.select name="status" id="pyramid-admin-status" :options="$statusOptions" :selected="$status" :aria-label="__('pyramid::admin.ladder.players.status_label')" /></div>
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('pyramid::admin.ladder.players.filter') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($ranking->isEmpty())
        <div class="flex flex-col gap-3 p-4 md:p-5">
            <p class="text-ink-2">{{ __('pyramid::admin.ladder.players.empty') }}</p>
            <form method="POST" action="{{ PyramidLinks::adminLadderAction('seed', (int) $ladder->id) }}">
                @csrf
                <x-ui.button type="submit" :block="false">{{ __('pyramid::admin.ladder.players.seed') }}</x-ui.button>
            </form>
        </div>
    @elseif ($players->isEmpty())
        <p class="p-4 text-ink-2 md:p-5">{{ __('pyramid::admin.ladder.players.none') }}</p>
    @else
        <x-ui.table :caption="__('pyramid::admin.ladder.players.caption')" x-data="{ editing: {{ $editId > 0 ? $editId : 'null' }} }">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_rank') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_name') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_gender') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_age') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_itn') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_status') }}</th>
                    <th scope="col">{{ __('pyramid::admin.ladder.players.col_action') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($players as $player)
                @php
                    $paused = $player->status === 'paused';
                    $playerId = (int) $player->id;
                @endphp
                <tr data-player-row>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_rank') }}" class="font-bold tabular-nums">{{ (int) $player->position }}</td>
                    <td class="ds-cell--title">{{ $player->label }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_gender') }}">{{ $genderLabel($player->gender) }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_age') }}">{{ $player->age === null ? '–' : (int) $player->age }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_itn') }}" class="tabular-nums">{{ PyramidFormat::itn($player->itn) }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_status') }}">
                        <x-ui.badge :tone="PyramidStatus::badgeGroup($player->status)">{{ __('pyramid::admin.ladder.status.'.($paused ? 'paused' : 'active')) }}</x-ui.badge>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5 xl:flex-nowrap">
                        <x-ui.button class="whitespace-nowrap !px-3" variant="secondary" :block="false" :href="$listUrl.'?'.http_build_query($listQuery + ['edit' => $playerId]).'#pyramid-pe-row-'.$playerId" x-on:click.prevent="editing = editing === {{ $playerId }} ? null : {{ $playerId }}">{{ __('pyramid::admin.ladder.players.edit') }}</x-ui.button>
                        <form method="POST" action="{{ PyramidLinks::adminPlayerAction('player-status', 'status', $playerId) }}" class="flex items-center gap-1.5">
                            @csrf
                            @if ($paused)
                                <x-ui.button class="whitespace-nowrap !px-3" type="submit" variant="secondary" :block="false" name="status" value="active">{{ __('pyramid::admin.ladder.players.activate') }}</x-ui.button>
                            @else
                                <x-ui.button class="whitespace-nowrap !px-3" type="submit" variant="secondary" :block="false" name="status" value="paused">{{ __('pyramid::admin.ladder.players.pause') }}</x-ui.button>
                            @endif
                        </form>
                        </div>
                    </td>
                </tr>
                {{-- The requested row has no x-cloak: it shows even if Alpine never loads. --}}
                <tr id="pyramid-pe-row-{{ $playerId }}" x-show="editing === {{ $playerId }}"@if ($editId !== $playerId) x-cloak @endif>
                    <td colspan="7">
                        <form method="POST" action="{{ PyramidLinks::adminPlayerAction('update-player', 'update', $playerId) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @csrf
                            <x-ui.field :for="'pyramid-pe-position-'.$playerId" :label="__('pyramid::admin.ladder.players.f_position')">
                                <x-ui.input type="number" name="position" :id="'pyramid-pe-position-'.$playerId" :value="(string) (int) $player->position" min="1" inputmode="numeric" />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-label-'.$playerId" :label="__('pyramid::admin.ladder.players.f_label')" class="sm:col-span-2">
                                <x-ui.input name="label" :id="'pyramid-pe-label-'.$playerId" :value="(string) $player->label" required />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-itn-'.$playerId" :label="__('pyramid::admin.ladder.players.f_itn')">
                                <x-ui.input name="itn" :id="'pyramid-pe-itn-'.$playerId" :value="(string) $player->itn" inputmode="decimal" />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-gender-'.$playerId" :label="__('pyramid::admin.ladder.players.f_gender')">
                                <x-ui.select name="gender" :id="'pyramid-pe-gender-'.$playerId" :options="['' => __('pyramid::admin.ladder.gender.none'), 'm' => __('pyramid::admin.ladder.gender.m'), 'f' => __('pyramid::admin.ladder.gender.f')]" :selected="(string) $player->gender" />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-age-'.$playerId" :label="__('pyramid::admin.ladder.players.f_age')">
                                <x-ui.input type="number" name="age" :id="'pyramid-pe-age-'.$playerId" :value="(string) $player->age" min="0" inputmode="numeric" />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-phone-'.$playerId" :label="__('pyramid::admin.ladder.players.f_phone')">
                                <x-ui.input type="tel" name="phone" :id="'pyramid-pe-phone-'.$playerId" :value="(string) $player->phone" />
                            </x-ui.field>
                            <x-ui.field :for="'pyramid-pe-note-'.$playerId" :label="__('pyramid::admin.ladder.players.f_note')" class="sm:col-span-2">
                                <x-ui.input name="note" :id="'pyramid-pe-note-'.$playerId" :value="(string) $player->note" />
                            </x-ui.field>
                            <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                                <x-ui.button type="submit" :block="false">{{ __('pyramid::admin.ladder.players.save') }}</x-ui.button>
                                <x-ui.button variant="text" :block="false" :href="$listUrl.($listQuery === [] ? '' : '?'.http_build_query($listQuery))" x-on:click.prevent="editing = null">{{ __('pyramid::admin.ladder.players.cancel') }}</x-ui.button>
                            </div>
                        </form>
                        <form method="POST" action="{{ PyramidLinks::adminPlayerAction('player-status', 'status', $playerId) }}" class="mt-3 border-t border-line-soft pt-3">
                            @csrf
                            <x-ui.button type="submit" variant="danger" :block="false" name="status" value="removed">{{ __('pyramid::admin.ladder.players.remove') }}</x-ui.button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($ranking->isNotEmpty())
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-4 py-3 md:px-5">
            @include('tournament-base::partials.pagination', [
                'paginator' => $players,
                'summary' => __('pyramid::admin.ladder.players.summary', ['page' => $page, 'pages' => $pages, 'total' => $total]),
            ])
            <form method="POST" action="{{ PyramidLinks::adminLadderAction('normalize', (int) $ladder->id) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('pyramid::admin.ladder.players.renumber') }}</x-ui.button>
            </form>
        </div>
    @endif
</x-ui.card>
