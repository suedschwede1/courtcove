<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawLifecycleService;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Execute, open and delete (old Draw AdminController). Access is checked by the route middleware before
 * the id is resolved; an unknown id flashes the old message and returns to the overview, as in ZF2.
 * Integrity failures of the stored draw (payload/commitment, token list) are not caught: they are a
 * tamper alarm, as in ZF2.
 */
final class DrawLifecycleController extends Controller
{
    public function execute(string $did, DrawExecutor $executor): RedirectResponse
    {
        $draw = $this->find($did);

        if ($draw === null) {
            return $this->back('error', __('draw::admin.execute.not_found'));
        }

        try {
            return $this->back('success', $executor->execute($draw));
        } catch (ActionRefused $e) {
            return $this->back('error', __($e->key, $e->replace));
        }
    }

    public function open(Request $request, string $did, DrawLifecycleService $lifecycle): RedirectResponse
    {
        $draw = $this->find($did);

        if ($draw === null) {
            return $this->back('error', __('draw::admin.open.order'));
        }

        $deadline = $request->input('deadline');

        try {
            $lifecycle->open($draw, is_scalar($deadline) ? (string) $deadline : null);
        } catch (ActionRefused $e) {
            return $this->back('error', __($e->key, $e->replace));
        }

        return $this->back('success', __('draw::admin.open.success'));
    }

    public function delete(string $did, DrawLifecycleService $lifecycle): RedirectResponse
    {
        $draw = $this->find($did);

        if ($draw === null) {
            return $this->back('error', __('draw::admin.delete.not_found'));
        }

        try {
            return $this->back('success', $lifecycle->delete($draw));
        } catch (ActionRefused $e) {
            return $this->back('error', __($e->key, $e->replace));
        }
    }

    public function redirectToIndex(): RedirectResponse
    {
        return redirect()->route('draw.index');
    }

    private function find(string $did): ?Draw
    {
        return ctype_digit($did) && strlen($did) <= 10 ? Draw::query()->find((int) $did) : null;
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->route('draw.index')->with($type, $message);
    }
}
