<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MixedTournament\Domain\MemberImportService;
use App\Modules\MixedTournament\Domain\MixedRuleException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Club-wide member roster import (old AdminController::importMembersAction()). Global right only
 * (`can:admin.mixed` route middleware): an import replaces the whole roster.
 */
class MemberImportController extends Controller
{
    public function __construct(private readonly MemberImportService $import) {}

    public function show(): View
    {
        return view('mixed::admin.import-members', ['memberCount' => $this->import->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->route('mixed.admin.import-members');
        $upload = $request->file('csv');

        if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
            return $back->with('error', __('mixed::messages.csv_missing'));
        }

        try {
            $count = $this->import->import((string) file_get_contents($upload->getRealPath()));
        } catch (MixedRuleException $e) {
            return $back->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $back->with('success', __('mixed::messages.members_imported', ['count' => $count]));
    }
}
