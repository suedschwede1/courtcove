<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawCloser;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawLifecycleService;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Execute, open, close and delete (old Draw AdminController). Access is checked by the route middleware before
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

    /**
     * Close with fallback assignment, then apply and generate. Several flashes, shown as the module list.
     */
    public function close(Request $request, string $did, DrawCloser $closer): RedirectResponse
    {
        $draw = $this->find($did);

        if ($draw === null) {
            return $this->back('error', __('draw::admin.close.not_picking'));
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            foreach ($closer->close($draw, $user) as $flash) {
                $flash['type'] === 'error' ? ModuleFlash::error($flash['text']) : ModuleFlash::success($flash['text']);
            }
        } catch (ActionRefused $e) {
            return $this->back('error', __($e->key, $e->replace));
        }

        return redirect()->route('draw.index');
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
