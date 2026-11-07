@extends('layouts.admin')
@section('admin-title', __('booking.admin.scheduler.title'))
@section('admin-content')
@php
    $healthTone = ['green' => 'good', 'yellow' => 'attention', 'red' => 'problem'][$health['status']];
    $runTone = ['ok' => 'good', 'failed' => 'problem', 'skipped' => 'attention'];
    $formatTime = fn ($time) => $time?->locale(app()->getLocale())->isoFormat('dd, D. MMM, HH:mm') ?? '';
    $taskOptions = collect($tasks)->mapWithKeys(fn ($t) => [$t['name'] => $t['title']])->all();
    $statusOptions = collect(\App\Models\ScheduledTaskRun::STATUSES)->mapWithKeys(fn ($s) => [$s => __('booking.admin.scheduler.status.'.$s)])->all();
    $titles = collect($tasks)->mapWithKeys(fn ($t) => [$t['name'] => $t['title']]);
@endphp
<div class="ds-admin-stack" data-scheduler>
    <x-admin.page-header :title="__('booking.admin.scheduler.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.scheduler.intro') }}</p>
    </x-admin.page-header>

    <x-ui.card :title="__('booking.admin.scheduler.health.title')" padding="lg" data-health="{{ $health['status'] }}">
        <div class="ds-admin-badges">
            <x-ui.badge :tone="$healthTone">{{ __('booking.admin.scheduler.health.'.$health['status']) }}</x-ui.badge>
            <span class="ds-admin-note">
                @if($health['last'] !== null)
                    {{ __('booking.admin.scheduler.health.last_tick', ['ago' => $health['last']->locale(app()->getLocale())->diffForHumans(), 'time' => $formatTime($health['last'])]) }}
                @else
                    {{ __('booking.admin.scheduler.health.never') }}
                @endif
            </span>
        </div>
        @if($health['status'] === 'red')
            <x-ui.notice tone="problem" data-cron-warning>
                <p>{{ __('booking.admin.scheduler.health.warning') }}</p>
                <pre class="ds-admin-output" aria-label="{{ __('booking.admin.scheduler.health.copy_label') }}">{{ $health['cron'] }}</pre>
                <p>{{ __('booking.admin.scheduler.health.php_hint', ['php' => $health['php']]) }}</p>
            </x-ui.notice>
        @endif
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.scheduler.tasks.title')">
        <x-ui.table :caption="__('booking.admin.scheduler.tasks.title')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.name') }}</th>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.about') }}</th>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.schedule') }}</th>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.next_run') }}</th>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.last_run') }}</th>
                    <th scope="col">{{ __('booking.admin.scheduler.tasks.failures') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('booking.admin.scheduler.tasks.run_now') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach($tasks as $task)
                <tr data-task="{{ $task['name'] }}">
                    <td class="ds-cell--title" data-label="{{ __('booking.admin.scheduler.tasks.name') }}">{{ $task['title'] }}</td>
                    <td data-label="{{ __('booking.admin.scheduler.tasks.about') }}">
                        @if($task['description'] !== '')
                            {{ $task['description'] }}<br>
                        @endif
                        <span class="ds-admin-mono">{{ $task['command'] ?? __('booking.admin.scheduler.tasks.callback') }}</span>
                    </td>
                    <td data-label="{{ __('booking.admin.scheduler.tasks.schedule') }}">{{ $task['schedule'] }}</td>
                    <td data-label="{{ __('booking.admin.scheduler.tasks.next_run') }}">{{ $formatTime($task['next_run']) }}</td>
                    <td data-label="{{ __('booking.admin.scheduler.tasks.last_run') }}">
                        @if($task['running'])
                            <x-ui.badge tone="attention">{{ __('booking.admin.scheduler.tasks.running') }}</x-ui.badge>
                        @elseif($task['heartbeat'] !== null && ($task['last_run'] === null || $task['last_run']->started_at->lt($task['heartbeat'])))
                            <x-ui.badge tone="good">{{ __('booking.admin.scheduler.status.ok') }}</x-ui.badge> {{ $formatTime($task['heartbeat']) }}
                        @elseif($task['last_run'] !== null)
                            <x-ui.badge :tone="$runTone[$task['last_run']->status] ?? 'neutral'">{{ __('booking.admin.scheduler.status.'.$task['last_run']->status) }}</x-ui.badge>
                            {{ $formatTime($task['last_run']->started_at) }} · {{ __('booking.admin.scheduler.runs.ms', ['ms' => (int) $task['last_run']->duration_ms]) }}
                        @else
                            {{ __('booking.admin.scheduler.tasks.never') }}
                        @endif
                    </td>
                    <td data-label="{{ __('booking.admin.scheduler.tasks.failures') }}">
                        @if($task['failures_7d'] > 0)
                            <x-ui.badge tone="problem">{{ $task['failures_7d'] }}</x-ui.badge>
                        @else
                            0
                        @endif
                    </td>
                    <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.scheduler.tasks.run_now') }}">
                        @if($task['runnable'])
                            <form method="POST" action="{{ route('admin.scheduler.run', ['task' => $task['name']]) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.scheduler.tasks.confirm_run', ['task' => $task['title']])) }})">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" :block="false" :disabled="$task['running']">{{ __('booking.admin.scheduler.tasks.run_now') }}</x-ui.button>
                            </form>
                        @else
                            <span class="ds-admin-note">{{ __('booking.admin.scheduler.tasks.not_runnable') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card :title="__('booking.admin.scheduler.queue.title')" padding="lg" data-queue="{{ $queue['enabled'] ? 'on' : 'sync' }}">
        @if(! $queue['enabled'])
            <p class="ds-admin-note">{{ __('booking.admin.scheduler.queue.sync') }}</p>
        @else
            <div class="ds-admin-kpis">
                <x-ui.card as="div" class="ds-admin-kpi">
                    <x-ui.kpi :label="__('booking.admin.scheduler.queue.connection')" :value="$queue['connection']" />
                </x-ui.card>
                <x-ui.card as="div" class="ds-admin-kpi">
                    <x-ui.kpi :label="__('booking.admin.scheduler.queue.waiting')" :value="$queue['waiting'] ?? __('booking.admin.scheduler.queue.unknown')" />
                </x-ui.card>
                <x-ui.card as="div" class="ds-admin-kpi">
                    <x-ui.kpi :label="__('booking.admin.scheduler.queue.failed')" :value="$queue['failed']" />
                </x-ui.card>
            </div>
            @if($queue['failed'] === 0)
                <p class="ds-admin-note">{{ __('booking.admin.scheduler.queue.none_failed') }}</p>
            @else
                <x-ui.table :caption="__('booking.admin.scheduler.queue.failed')">
                    <x-slot:head>
                        <tr>
                            <th scope="col">{{ __('booking.admin.scheduler.queue.failed_at') }}</th>
                            <th scope="col">{{ __('booking.admin.scheduler.queue.error') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach($queue['failed_jobs'] as $job)
                        <tr>
                            <td data-label="{{ __('booking.admin.scheduler.queue.failed_at') }}">{{ $job->failed_at }}</td>
                            <td class="ds-admin-mono" data-label="{{ __('booking.admin.scheduler.queue.error') }}">{{ \Illuminate\Support\Str::limit(strtok((string) $job->exception, "\n") ?: '', 160) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div class="ds-admin-row-actions">
                    <form method="POST" action="{{ route('admin.scheduler.queue.retry') }}" onsubmit="return confirm({{ Js::from(__('booking.admin.scheduler.queue.confirm_retry')) }})">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.scheduler.queue.retry') }}</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin.scheduler.queue.flush') }}" onsubmit="return confirm({{ Js::from(__('booking.admin.scheduler.queue.confirm_flush')) }})">
                        @csrf
                        <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.scheduler.queue.flush') }}</x-ui.button>
                    </form>
                </div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card padding="lg">
        <form method="GET" action="{{ route('admin.scheduler.index') }}" class="ds-admin-filter">
            <x-ui.field :label="__('booking.admin.scheduler.tasks.name')" for="sr-task">
                <x-ui.select name="task" id="sr-task" :options="$taskOptions" :selected="$filters['task']" :placeholder="__('booking.admin.scheduler.runs.all_tasks')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.scheduler.runs.result')" for="sr-status">
                <x-ui.select name="status" id="sr-status" :options="$statusOptions" :selected="$filters['status']" :placeholder="__('booking.admin.scheduler.runs.all_status')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.scheduler.runs.title')">
        @if($runs->isEmpty())
            <p class="ds-admin-note">{{ __('booking.admin.scheduler.runs.empty') }}</p>
        @else
            <x-ui.table :caption="__('booking.admin.scheduler.runs.title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.scheduler.tasks.name') }}</th>
                        <th scope="col">{{ __('booking.admin.scheduler.runs.started') }}</th>
                        <th scope="col">{{ __('booking.admin.scheduler.runs.duration') }}</th>
                        <th scope="col">{{ __('booking.admin.scheduler.runs.result') }}</th>
                        <th scope="col">{{ __('booking.admin.scheduler.runs.trigger') }}</th>
                        <th scope="col">{{ __('booking.admin.scheduler.runs.output') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($runs as $run)
                    <tr data-run-status="{{ $run->status }}">
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.scheduler.tasks.name') }}">{{ $titles[$run->name] ?? $run->name }}</td>
                        <td data-label="{{ __('booking.admin.scheduler.runs.started') }}">{{ $formatTime($run->started_at) }}</td>
                        <td data-label="{{ __('booking.admin.scheduler.runs.duration') }}">{{ __('booking.admin.scheduler.runs.ms', ['ms' => (int) $run->duration_ms]) }}</td>
                        <td data-label="{{ __('booking.admin.scheduler.runs.result') }}"><x-ui.badge :tone="$runTone[$run->status] ?? 'neutral'">{{ __('booking.admin.scheduler.status.'.$run->status) }}</x-ui.badge></td>
                        <td data-label="{{ __('booking.admin.scheduler.runs.trigger') }}">{{ __('booking.admin.scheduler.trigger.'.$run->triggered_by) }}</td>
                        <td class="ds-admin-mono" data-label="{{ __('booking.admin.scheduler.runs.output') }}">{{ \Illuminate\Support\Str::limit((string) $run->output_excerpt, 200) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
@endsection
