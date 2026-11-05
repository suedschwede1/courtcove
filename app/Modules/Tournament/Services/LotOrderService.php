<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;

/**
 * Placeholder: the lot tie-break (manual lot_orders plus the order derived from the first executed
 * draw) arrives with the next task of Plan D. Until then no tie is ever decided automatically, which
 * is ZF2 behaviour without a draw table.
 */
class LotOrderService
{
    /** @return array<string, mixed> */
    public function lotOrders(Tournament $t): array
    {
        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<int>
     */
    public function lotOrder(Tournament $t, string $scope, array $rows = []): array
    {
        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<int>
     */
    public function derivedLotOrder(Tournament $t, string $scope, array $rows): array
    {
        return [];
    }

    public function forget(int $tid): void {}
}
