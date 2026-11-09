<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Module overview: the modules of the ModuleRegistry that the viewer may see. A guest who sees none is sent to the
 * login (modules need a login unless they declare otherwise).
 */
final class ModulesController extends Controller
{
    public function __invoke(Request $request, ModuleRegistry $registry): View|RedirectResponse
    {
        $user = $request->user();
        $modules = array_values(array_filter($registry->available(), static fn ($module): bool => $module->visibleTo($user)));

        if ($user === null && $modules === []) {
            return redirect()->route('login', ['redirect_to' => $request->fullUrl()]);
        }

        return view('modules.index', ['modules' => $modules]);
    }
}
