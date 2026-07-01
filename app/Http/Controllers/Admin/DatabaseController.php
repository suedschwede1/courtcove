<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

final class DatabaseController extends Controller
{
    public function __construct(
        private readonly DatabaseSchemaChecker $checker,
    ) {}

    public function index(): View
    {
        return view('admin.database.index', [
            'migrations' => $this->checker->migrationStatus(),
            'tables' => $this->checker->checkTables(),
            'hasPending' => $this->checker->hasPendingMigrations(),
        ]);
    }

    public function migrate(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return redirect()->route('admin.database.index')
            ->with('success', __('booking.admin.database.migrate_ran'));
    }
}
