<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Models\Option;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DsgvoSettingsController
{
    public function index(): View
    {
        return view('members::dsgvo.index', [
            'retentionYears' => (int) Option::getValue('members.anonymization.retention_years', 3),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'retention_years' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        Option::updateOrCreate(
            ['key' => 'members.anonymization.retention_years', 'locale' => null],
            ['value' => $validated['retention_years']],
        );

        return redirect()->route('members.dsgvo.index')
            ->with('success', __('members::admin.dsgvo.saved'));
    }
}
