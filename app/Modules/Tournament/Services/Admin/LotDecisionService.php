<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Stores a lot order drawn on site for one ranking context (ported from ZF2 AdminController::lotAction).
 *
 * A manual entry always wins over the order derived from the draw seed (LotOrderService). It is stored as
 * `lot_orders[scope] = list<int>` with plain json_encode, the ZF2 shape.
 */
final class LotDecisionService
{
    public function __construct(private readonly TournamentManager $m) {}

    /** @throws ActionRefused */
    public function store(Tournament $t, string $scope, string $tpidList): void
    {
        $tid = (int) $t->tid;
        $scope = trim($scope);

        if (! in_array($scope, $this->scopes($tid), true)) {
            throw new ActionRefused('tournament::admin.lot.invalid_scope');
        }

        $parts = preg_split('/[ ,;]+/', trim($tpidList));
        $order = array_values(array_filter(array_map('intval', $parts === false ? [] : $parts)));

        if (count($order) < 2 || count($order) !== count(array_unique($order))) {
            throw new ActionRefused('tournament::admin.lot.two_unique');
        }

        foreach ($order as $tpid) {
            if ($this->m->player($tid, $tpid) === null) {
                throw new ActionRefused('tournament::admin.lot.foreign', ['tpid' => $tpid]);
            }
        }

        // Read-modify-write of the JSON column under the tournament row lock, so two entries cannot lose each other.
        DB::transaction(function () use ($tid, $scope, $order): void {
            $row = Tournament::query()->whereKey($tid)->lockForUpdate()->firstOrFail();
            $orders = $row->lotOrdersArray();
            $orders[$scope] = $order;

            Tournament::query()->whereKey($tid)->update(['lot_orders' => json_encode($orders)]);
        });

        $this->m->forget($tid);
    }

    /**
     * The lot contexts the format knows: every group (`group:<key>`) and the cross-group comparison of the
     * group winners and runners-up of every group phase (`phase:<key>:place:1|2`).
     *
     * @return list<string>
     */
    public function scopes(int $tid): array
    {
        try {
            $phases = $this->m->format($tid)->groupPhases();
        } catch (Exception) {
            return [];
        }

        $scopes = [];

        foreach ($phases as $phase) {
            foreach ($phase['groups'] ?? [] as $group) {
                $scopes[] = 'group:'.$group['key'];
            }

            $scopes[] = 'phase:'.$phase['key'].':place:1';
            $scopes[] = 'phase:'.$phase['key'].':place:2';
        }

        return $scopes;
    }
}
