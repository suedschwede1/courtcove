<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use Illuminate\Support\Facades\DB;

/**
 * Atomic token claims (ported from ZF2 TokenTable::claim/claimWhilePicking and DrawTable::lockForUpdate).
 *
 * The race is decided in the database, not in PHP: the `claimed_tpid IS NULL` condition inside the UPDATE
 * is the protection against two simultaneous clicks (a SELECT before the UPDATE would leave exactly that
 * gap open). The affected-rows count is the claim signal on purpose: the written tpid always differs from
 * the NULL guard, so MySQL's "0 rows for identical values" cannot hide a successful claim.
 *
 * The UNIQUE KEY (did, claimed_tpid) keeps one player from holding two tokens; its violation propagates
 * as in ZF2.
 *
 * SQLite (test driver only) does not serialise picks: lockDraw() takes no row lock there, and a deferred
 * BEGIN takes no lock on the SELECT either, so two concurrent picks could both recompute the partner block
 * before either writes. The claim statement itself stays atomic. The serialisation is proven on MySQL by
 * TokenRaceMysqlTest (composer test:mysql).
 */
final class TokenClaimer
{
    private const MYSQL_CLAIM = 'UPDATE bs_draw_tokens AS t INNER JOIN bs_draws AS d ON d.did = t.did '
        .'SET t.claimed_tpid = ?, t.claimed_uid = ?, t.claimed = ?, t.assigned_by = ? '
        .'WHERE t.did = ? AND t.dtid = ? AND t.claimed_tpid IS NULL '
        .'AND d.status = ?';

    // SQLite has no UPDATE ... JOIN; the correlated EXISTS keeps status check and claim in one statement.
    private const SQLITE_CLAIM = 'UPDATE bs_draw_tokens '
        .'SET claimed_tpid = ?, claimed_uid = ?, claimed = ?, assigned_by = ? '
        .'WHERE did = ? AND dtid = ? AND claimed_tpid IS NULL '
        .'AND EXISTS (SELECT 1 FROM bs_draws d WHERE d.did = bs_draw_tokens.did AND d.status = ?)';

    private const LOCK = 'SELECT did, status, discarded FROM bs_draws WHERE did = ?';

    /**
     * Locks the draw row for the rest of the transaction (FOR UPDATE; SQLite locks the whole database on
     * write and knows no row locks).
     *
     * @return object{did: int|string, status: string, discarded: ?string}|null
     */
    public function lockDraw(int $did): ?object
    {
        $row = DB::selectOne($this->lockSql(DB::connection()->getDriverName()), [$did]);

        return is_object($row) ? $row : null;
    }

    public function lockSql(string $driver): string
    {
        return $this->isMysql($driver) ? self::LOCK.' FOR UPDATE' : self::LOCK;
    }

    /**
     * Claims a token for a player (fallback assignment on close). True when this call claimed it, false
     * when somebody else got it in the meantime.
     */
    public function claim(int $dtid, int $tpid, ?int $uid, string $assignedBy): bool
    {
        $affected = DB::table('bs_draw_tokens')
            ->where('dtid', $dtid)
            ->whereNull('claimed_tpid')
            ->update([
                'claimed_tpid' => $tpid,
                'claimed_uid' => (int) $uid, // ZF2 cast: a player without a user is stored as 0
                'claimed' => now()->format('Y-m-d H:i:s'),
                'assigned_by' => $assignedBy,
            ]);

        return $affected === 1;
    }

    /**
     * A participant's pick, accepted only while the draw is in 'picking'. Status check and claim are one
     * atomic statement, so close can end the race safely.
     */
    public function claimWhilePicking(int $did, int $dtid, int $tpid, int $uid): bool
    {
        $affected = DB::affectingStatement($this->claimSql(DB::connection()->getDriverName()), [
            $tpid,
            $uid,
            now()->format('Y-m-d H:i:s'),
            'pick',
            $did,
            $dtid,
            'picking',
        ]);

        return $affected === 1;
    }

    public function claimSql(string $driver): string
    {
        return $this->isMysql($driver) ? self::MYSQL_CLAIM : self::SQLITE_CLAIM;
    }

    private function isMysql(string $driver): bool
    {
        return $driver === 'mysql' || $driver === 'mariadb';
    }
}
