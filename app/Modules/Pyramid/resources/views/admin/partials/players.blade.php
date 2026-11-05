{{--
    Participant table with search, status filter, inline edit and paging.
    Needs: $ladder, $ranking, $players (paginator), $page, $pages, $total, $search, $status, optional $editId.
    Edit works without scripts: the edit control is a link (?edit=ID) and the requested row is rendered open;
    with Alpine the same link toggles the row in place.
    Phones (Admin-Pyramide-Mobil): the card steps back and every row becomes a compact card of its own: number, name
    (the link into the edit area), "ITN · status" below, and the pause/activate action at the right; the other
    columns and the edit button are desktop only.
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
<x-ui.card padding="none" aria-labelledby="pyramid-admin-players" class="max-md:border-0 max-md:bg-transparent">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft px-4 py-3.5 max-md:border-0 max-md:px-0 max-md:pt-0 md:px-5">
        <h2 id="pyramid-admin-players" class="font-display text-display-m text-ink max-md:font-body max-md:text-label max-md:uppercase max-md:text-ink-3">{{ __('pyramid::admin.ladder.players.title') }}</h2>

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
                    $statusText = __('pyramid::admin.ladder.status.'.($paused ? 'paused' : 'active'));
                    $editUrl = $listUrl.'?'.http_build_query($listQuery + ['edit' => $playerId]).'#pyramid-pe-row-'.$playerId;
                    $toggle = 'editing = editing === '.$playerId.' ? null : '.$playerId;
                @endphp
                <tr data-player-row class="max-md:mb-2 max-md:flex max-md:min-h-14 max-md:items-center max-md:gap-2.5 max-md:rounded-lg max-md:border max-md:border-line max-md:bg-surface max-md:py-1.5 max-md:pl-3 max-md:pr-2">
                    <td class="font-bold tabular-nums max-md:w-7 max-md:shrink-0 max-md:font-display max-md:text-display-s max-md:text-ink-3">{{ (int) $player->position }}</td>
                    <td class="ds-cell--title max-md:min-w-0 max-md:flex-1">
                        <span class="max-md:hidden">{{ $player->label }}</span>
                        <a href="{{ $editUrl }}" class="flex min-w-0 flex-col text-ink no-underline md:hidden" x-on:click.prevent="{{ $toggle }}" x-bind:aria-expanded="editing === {{ $playerId }} ? 'true' : 'false'" aria-controls="pyramid-pe-row-{{ $playerId }}">
                            <span class="break-words font-bold">{{ $player->label }}</span>
                            <span class="text-body-s font-normal text-ink-2">{{ __('pyramid::admin.ladder.players.phone_line', ['itn' => PyramidFormat::itn($player->itn), 'status' => $statusText]) }}</span>
                            <span class="sr-only">{{ __('pyramid::admin.ladder.players.edit') }}</span>
                        </a>
                    </td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_gender') }}" class="max-md:hidden">{{ $genderLabel($player->gender) }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_age') }}" class="max-md:hidden">{{ $player->age === null ? '–' : (int) $player->age }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_itn') }}" class="tabular-nums max-md:hidden">{{ PyramidFormat::itn($player->itn) }}</td>
                    <td data-label="{{ __('pyramid::admin.ladder.players.col_status') }}" class="max-md:hidden">
                        <x-ui.badge :tone="PyramidStatus::badgeGroup($player->status)">{{ $statusText }}</x-ui.badge>
                    </td>
                    <td class="max-md:shrink-0">
                        <div class="flex flex-wrap items-center gap-1.5 xl:flex-nowrap">
                        <x-ui.button class="whitespace-nowrap !px-3 max-md:hidden" variant="secondary" :block="false" :href="$editUrl" x-on:click.prevent="{{ $toggle }}">{{ __('pyramid::admin.ladder.players.edit') }}</x-ui.button>
                        <form method="POST" action="{{ PyramidLinks::adminPlayerAction('player-status', 'status', $playerId) }}" class="flex items-center gap-1.5">
                            @csrf
                            @if ($paused)
                                <x-ui.button class="whitespace-nowrap !px-3 max-md:min-h-10 max-md:text-body-s" type="submit" variant="secondary" :block="false" name="status" value="active">{{ __('pyramid::admin.ladder.players.activate') }}</x-ui.button>
                            @else
                                <x-ui.button class="whitespace-nowrap !px-3 max-md:min-h-10 max-md:text-body-s" type="submit" variant="secondary" :block="false" name="status" value="paused">{{ __('pyramid::admin.ladder.players.pause') }}</x-ui.button>
                            @endif
                        </form>
                        </div>
                    </td>
                </tr>
                {{-- The requested row has no x-cloak: it shows even if Alpine never loads. On phones it is the card under the row. --}}
                <tr id="pyramid-pe-row-{{ $playerId }}" class="max-md:mb-2 max-md:rounded-lg max-md:border max-md:border-line max-md:bg-surface" x-show="editing === {{ $playerId }}"@if ($editId !== $playerId) x-cloak @endif>
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-4 py-3 max-md:border-0 max-md:px-0 md:px-5">
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
