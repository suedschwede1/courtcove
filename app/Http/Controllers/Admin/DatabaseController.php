<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class DatabaseController extends Controller
{
    private const MIGRATION_CONFIRMATION = 'MIGRATE';

    private const RESTORE_CONFIRMATION = 'RESTORE';

    public function __construct(
        private readonly DatabaseSchemaChecker $checker,
        private readonly DatabaseBackupService $backups,
    ) {}

    public function index(): View
    {
        return view('admin.database.index', [
            'migrations' => $this->checker->migrationStatus(),
            'tables' => $this->checker->checkTables(),
            'drift' => $this->checker->driftReport(),
            'backups' => $this->backups->list(),
            'hasPending' => $this->checker->hasPendingMigrations(),
            'migrationConfirmationValue' => self::MIGRATION_CONFIRMATION,
            'restoreConfirmationValue' => self::RESTORE_CONFIRMATION,
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (strtoupper(trim((string) $request->input('confirmation'))) !== self::MIGRATION_CONFIRMATION) {
                $validator->errors()->add(
                    'confirmation',
                    __('booking.admin.database.migrate_confirmation_invalid', [
                        'value' => self::MIGRATION_CONFIRMATION,
                    ]),
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->route('admin.database.index')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.database.index')
                ->withInput()
                ->with('error', __('booking.admin.database.migrate_failed_message', ['message' => $e->getMessage()]))
                ->with('output', trim(Artisan::output()));
        }

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.migrate_ran'))
            ->with('output', trim(Artisan::output()));
    }

    public function repair(string $table): RedirectResponse
    {
        abort_unless($this->checker->knowsTable($table), 404);

        try {
            $actions = $this->checker->repairTable($table);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.database.index')
                ->with('error', __('booking.admin.database.repair_failed', ['table' => $table, 'message' => $e->getMessage()]));
        }

        if ($actions === []) {
            return redirect()->route('admin.database.index')
                ->with('success', __('booking.admin.database.repair_nothing', ['table' => $table]));
        }

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.repair_done', ['table' => $table]))
            ->with('output', implode(PHP_EOL, $actions));
    }

    public function createBackup(): RedirectResponse
    {
        try {
            $filename = $this->backups->create();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.database.index')
                ->with('error', __('booking.admin.database.backup_create_failed', ['message' => $e->getMessage()]));
        }

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.backup_created', ['filename' => $filename]));
    }

    public function downloadBackup(string $filename): StreamedResponse
    {
        return Storage::disk('local')->download('backups/'.$filename, $filename);
    }

    public function deleteBackup(string $filename): RedirectResponse
    {
        $this->backups->delete($filename);

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.backup_deleted', ['filename' => $filename]));
    }

    public function restoreBackup(Request $request, string $filename): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (strtoupper(trim((string) $request->input('confirmation'))) !== self::RESTORE_CONFIRMATION) {
                $validator->errors()->add(
                    'confirmation',
                    __('booking.admin.database.restore_confirmation_invalid', [
                        'value' => self::RESTORE_CONFIRMATION,
                    ]),
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->route('admin.database.index')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $actions = $this->backups->restore($filename);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.database.index')
                ->with('error', __('booking.admin.database.restore_failed', ['message' => $e->getMessage()]));
        }

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.restore_done', ['filename' => $filename]))
            ->with('output', implode(PHP_EOL, $actions));
    }
}
