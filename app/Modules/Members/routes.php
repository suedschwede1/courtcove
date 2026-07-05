<?php

declare(strict_types=1);

use App\Modules\Members\Http\Controllers\DashboardController;
use App\Modules\Members\Http\Controllers\FeeCategoryController;
use App\Modules\Members\Http\Controllers\FiscalYearController;
use App\Modules\Members\Http\Controllers\MemberController;
use App\Modules\Members\Http\Controllers\MemberDueController;
use App\Modules\Members\Http\Controllers\MemberExportController;
use App\Modules\Members\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:admin.members'])
    ->group(function (): void {
        Route::get('members/dashboard', [DashboardController::class, 'index'])->name('members.dashboard');
        Route::get('members/export', [MemberExportController::class, 'export'])->name('members.export');

        Route::get('members/fiscal-years', [FiscalYearController::class, 'index'])->name('members.fiscal-years.index');
        Route::get('members/fiscal-years/create', [FiscalYearController::class, 'create'])->name('members.fiscal-years.create');
        Route::post('members/fiscal-years', [FiscalYearController::class, 'store'])->name('members.fiscal-years.store');
        Route::get('members/fiscal-years/{fiscalYear}/edit', [FiscalYearController::class, 'edit'])->name('members.fiscal-years.edit');
        Route::put('members/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'update'])->name('members.fiscal-years.update');

        Route::get('members/fee-categories', [FeeCategoryController::class, 'index'])->name('members.fee-categories.index');
        Route::post('members/fee-categories', [FeeCategoryController::class, 'store'])->name('members.fee-categories.store');
        Route::post('members/fee-categories/rates', [FeeCategoryController::class, 'storeRates'])->name('members.fee-categories.rates.store');

        Route::get('members/dues', [MemberDueController::class, 'index'])->name('members.dues.index');
        Route::get('members/dues/create', [MemberDueController::class, 'create'])->name('members.dues.create');
        Route::post('members/dues', [MemberDueController::class, 'store'])->name('members.dues.store');
        Route::post('members/dues/generate', [MemberDueController::class, 'generate'])->name('members.dues.generate');

        Route::get('members/{member}/payments/create', [PaymentController::class, 'create'])->name('members.payments.create');
        Route::post('members/{member}/payments', [PaymentController::class, 'store'])->name('members.payments.store');

        Route::patch('members/{member}/field', [MemberController::class, 'updateField'])->name('members.update-field');

        Route::resource('members', MemberController::class)->except(['show']);
    });
