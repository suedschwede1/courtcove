<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\LoginHistory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function record_inserts_a_row_for_a_known_user(): void
    {
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success');

        $this->assertDatabaseHas('login_history', [
            'user_id' => 1,
            'ip' => '127.0.0.1',
            'country_code' => 'AT',
            'status' => 'success',
            'risk_flags' => null,
        ]);
    }

    #[Test]
    public function record_stores_the_given_risk_flags(): void
    {
        LoginHistory::record(1, '127.0.0.1', 'RU', 'success', ['unusual_country', 'impossible_travel']);

        $this->assertDatabaseHas('login_history', [
            'user_id' => 1,
            'risk_flags' => 'unusual_country,impossible_travel',
        ]);
    }

    #[Test]
    public function record_does_nothing_for_an_unresolved_user(): void
    {
        LoginHistory::record(null, '127.0.0.1', 'AT', 'failed');

        $this->assertDatabaseCount('login_history', 0);
    }

    #[Test]
    public function record_prunes_rows_older_than_30_days_for_that_user(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success');

        Carbon::setTestNow('2026-02-05 12:00:00');
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success');

        $this->assertDatabaseCount('login_history', 1);
        $this->assertDatabaseHas('login_history', ['created_at' => '2026-02-05 12:00:00']);

        Carbon::setTestNow();
    }

    #[Test]
    public function record_does_not_prune_another_users_old_rows(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        LoginHistory::record(2, '127.0.0.1', 'AT', 'success');

        Carbon::setTestNow('2026-02-05 12:00:00');
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success');

        $this->assertDatabaseCount('login_history', 2);

        Carbon::setTestNow();
    }

    #[Test]
    public function risk_score_sums_flags_across_rows_in_the_last_30_days(): void
    {
        LoginHistory::record(1, '127.0.0.1', 'RU', 'success', ['unusual_country', 'impossible_travel']);
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success', ['recent_failed_attempts']);
        LoginHistory::record(1, '127.0.0.1', 'AT', 'success');

        $this->assertSame(3, LoginHistory::riskScore(1));
    }

    #[Test]
    public function risk_score_ignores_rows_older_than_30_days(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        LoginHistory::record(1, '127.0.0.1', 'RU', 'success', ['unusual_country']);

        Carbon::setTestNow('2026-02-05 12:00:00');
        $this->assertSame(0, LoginHistory::riskScore(1));

        Carbon::setTestNow();
    }
}
