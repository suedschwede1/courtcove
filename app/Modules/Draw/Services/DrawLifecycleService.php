<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\TournamentBase\Support\ActionRefused;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Open and delete (ported from ZF2 Draw AdminController::openAction / deleteAction).
 */
final class DrawLifecycleService
{
    /**
     * Opens picking for a token draw still in 'executed' (legacy path: since execute moves token draws
     * straight to 'picking', only older draws need this). An empty deadline keeps the stored one.
     *
     * @throws ActionRefused
     */
    public function open(Draw $draw, ?string $deadline): void
    {
        if (($draw->discarded !== null && $draw->discarded !== '') || $draw->status !== 'executed' || $draw->mode !== 'token') {
            throw new ActionRefused('draw::admin.open.order');
        }

        $deadline = trim((string) $deadline);
        $stored = $draw->pick_deadline;

        if ($deadline !== '') {
            $stored = $this->normaliseDeadline($deadline);
        }

        Draw::query()->whereKey((int) $draw->did)->update([
            'status' => 'picking',
            'pick_deadline' => $stored,
        ]);
    }

    /**
     * Deletes a discarded, never executed draw with no claimed token - all three are required:
     * an executed draw keeps its published result even when discarded, and a claimed token keeps the
     * participants' choice visible.
     *
     * @return string the success flash
     *
     * @throws ActionRefused
     */
    public function delete(Draw $draw): string
    {
        if ($draw->discarded === null || $draw->discarded === '') {
            throw new ActionRefused('draw::admin.delete.not_discarded');
        }

        if ($draw->final_seed !== null && $draw->final_seed !== '') {
            throw new ActionRefused('draw::admin.delete.executed');
        }

        $did = (int) $draw->did;
        $claimed = DrawToken::query()->where('did', $did)->whereNotNull('claimed_tpid')->where('claimed_tpid', '!=', 0)->count();

        if ($claimed > 0) {
            throw new ActionRefused('draw::admin.delete.claimed', ['count' => $claimed]);
        }

        DB::transaction(function () use ($did): void {
            DrawToken::query()->where('did', $did)->delete();
            DrawEligible::query()->where('did', $did)->delete();
            Draw::query()->whereKey($did)->delete();
        });

        return (string) __('draw::admin.delete.success', ['title' => (string) $draw->title]);
    }

    /**
     * The form sends datetime-local ("Y-m-dTH:i"); "Y-m-d H:i[:s]" is accepted too. ZF2 passed the raw value
     * to MySQL, where strict mode fails on anything else; here it must be a real date and is stored as
     * "Y-m-d H:i:s".
     */
    private function normaliseDeadline(string $value): string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        throw new ActionRefused('draw::admin.open.invalid_deadline');
    }
}
