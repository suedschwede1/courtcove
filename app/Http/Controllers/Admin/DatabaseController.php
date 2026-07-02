<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

final class DatabaseController extends Controller
{
    private const MIGRATION_CONFIRMATION = 'MIGRATE';

    public function __construct(
        private readonly DatabaseSchemaChecker $checker,
    ) {}

    public function index(): View
    {
        return view('admin.database.index', [
            'migrations' => $this->checker->migrationStatus(),
            'tables' => $this->checker->checkTables(),
            'hasPending' => $this->checker->hasPendingMigrations(),
            'migrationConfirmationValue' => self::MIGRATION_CONFIRMATION,
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
                ->with('error', __('booking.admin.database.migrate_failed'));
        }

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.migrate_ran'));
    }
}
