<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Scheduling\ScheduleDefinition;
use App\Console\Scheduling\SchedulerOverview;
use App\Console\Scheduling\TaskRunner;
use App\Http\Controllers\Controller;
use App\Models\ScheduledTaskRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * Admin page "Geplante Aufgaben": scheduler health, the tasks of ScheduleDefinition, the queue (only when a
 * queue is used) and the run log; "Jetzt ausführen" for one task of the Schedule. Access: admin group
 * (auth + admin.mode) and can:admin.config (effective right, so only in admin mode).
 */
final class SchedulerController extends Controller
{
    public function index(Request $request, SchedulerOverview $overview): View
    {
        $task = $request->query('task');
        $status = $request->query('status');
        $filters = [
            'task' => is_string($task) ? mb_substr($task, 0, 100) : '',
            'status' => is_string($status) && in_array($status, ScheduledTaskRun::STATUSES, true) ? $status : '',
        ];

        return view('admin.scheduler.index', [
            'health' => $overview->health(),
            'tasks' => $overview->tasks(),
            'queue' => $overview->queue(),
            'runs' => $overview->recentRuns($filters),
            'filters' => $filters,
        ]);
    }

    public function run(Request $request, string $task, TaskRunner $runner, SchedulerOverview $overview): RedirectResponse
    {
        $event = ScheduleDefinition::find($task);
        abort_if($event === null || ! $overview->isRunnable($event), 404);

        $run = $runner->run($event, $request->user()?->getAuthIdentifier());

        $titleKey = 'booking.admin.scheduler.tasks.'.$task.'.title';
        $title = Lang::has($titleKey) ? (string) __($titleKey) : $task;
        $message = __('booking.admin.scheduler.run.result_'.$run->status, ['task' => $title, 'ms' => (int) $run->duration_ms]);

        return redirect()->route('admin.scheduler.index')->with($run->status === 'ok' ? 'success' : 'error', $message);
    }

    public function retryFailed(): RedirectResponse
    {
        abort_unless(ScheduleDefinition::usesQueue(), 404);
        Artisan::call('queue:retry', ['id' => ['all']]);

        return redirect()->route('admin.scheduler.index')->with('success', __('booking.admin.scheduler.queue.retried'));
    }

    public function flushFailed(): RedirectResponse
    {
        abort_unless(ScheduleDefinition::usesQueue(), 404);
        Artisan::call('queue:flush');

        return redirect()->route('admin.scheduler.index')->with('success', __('booking.admin.scheduler.queue.flushed'));
    }
}
