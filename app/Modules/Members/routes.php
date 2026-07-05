<?php

declare(strict_types=1);

use App\Modules\Members\Http\Controllers\ClubRoleController;
use App\Modules\Members\Http\Controllers\DashboardController;
use App\Modules\Members\Http\Controllers\FeeCategoryController;
use App\Modules\Members\Http\Controllers\FiscalYearController;
use App\Modules\Members\Http\Controllers\MailController;
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
        Route::post('members/dues', [MemberDueController::class, 'store'])->name('members.dues.store');
        Route::post('members/dues/generate', [MemberDueController::class, 'generate'])->name('members.dues.generate');
        Route::patch('members/dues/{due}/field', [MemberDueController::class, 'updateField'])->name('members.dues.update-field');
        Route::patch('members/dues/{due}/paid-through', [MemberDueController::class, 'markPaidThrough'])->name('members.dues.mark-paid-through');

        Route::get('members/{member}/payments/create', [PaymentController::class, 'create'])->name('members.payments.create');
        Route::post('members/{member}/payments', [PaymentController::class, 'store'])->name('members.payments.store');

        Route::patch('members/{member}/field', [MemberController::class, 'updateField'])->name('members.update-field');
        Route::post('members/{member}/anonymize', [MemberController::class, 'anonymize'])->name('members.anonymize');

        Route::get('members/functions', [ClubRoleController::class, 'index'])->name('members.functions.index');
        Route::get('members/functions/create', [ClubRoleController::class, 'create'])->name('members.functions.create');
        Route::post('members/functions', [ClubRoleController::class, 'store'])->name('members.functions.store');
        Route::get('members/functions/{clubRole}/edit', [ClubRoleController::class, 'edit'])->name('members.functions.edit');
        Route::put('members/functions/{clubRole}', [ClubRoleController::class, 'update'])->name('members.functions.update');
        Route::delete('members/functions/{clubRole}', [ClubRoleController::class, 'destroy'])->name('members.functions.destroy');

        Route::get('members/mail', [MailController::class, 'index'])->name('members.mail.index');
        Route::get('members/mail/config', [MailController::class, 'config'])->name('members.mail.config');
        Route::post('members/mail/sender', [MailController::class, 'updateSender'])->name('members.mail.sender.update');
        Route::post('members/mail/test', [MailController::class, 'sendTest'])->name('members.mail.test');
        Route::post('members/mail/send', [MailController::class, 'send'])->name('members.mail.send');
        Route::post('members/mail/templates', [MailController::class, 'storeTemplate'])->name('members.mail.templates.store');
        Route::put('members/mail/templates/{mailTemplate}', [MailController::class, 'updateTemplate'])->name('members.mail.templates.update');
        Route::delete('members/mail/templates/{mailTemplate}', [MailController::class, 'destroyTemplate'])->name('members.mail.templates.destroy');

        Route::resource('members', MemberController::class)->except(['show', 'destroy']);
    });
