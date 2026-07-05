<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\LoginHistory;
use App\Services\LoginRiskEvaluator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginRiskEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private LoginRiskEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new LoginRiskEvaluator;
    }

    #[Test]
    public function first_ever_login_is_never_suspicious(): void
    {
        $this->assertSame([], $this->evaluator->triggeredFlags(1, 'AT'));
        $this->assertFalse($this->evaluator->isSuspicious(1, 'AT'));
    }

    #[Test]
    public function a_country_matching_history_is_not_suspicious(): void
    {
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        $this->assertSame([], $this->evaluator->triggeredFlags(1, 'AT'));
    }

    #[Test]
    public function a_new_country_not_in_history_is_suspicious(): void
    {
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        $this->assertSame(['unusual_country'], $this->evaluator->triggeredFlags(1, 'RU'));
        $this->assertTrue($this->evaluator->isSuspicious(1, 'RU'));
    }

    #[Test]
    public function a_null_country_never_triggers_the_country_checks(): void
    {
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        $this->assertSame([], $this->evaluator->triggeredFlags(1, null));
    }

    #[Test]
    public function two_recent_failed_attempts_are_suspicious(): void
    {
        LoginHistory::record(1, '1.1.1.1', 'AT', 'failed');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'failed');

        $this->assertSame(['recent_failed_attempts'], $this->evaluator->triggeredFlags(1, null));
    }

    #[Test]
    public function a_single_failed_attempt_is_not_suspicious(): void
    {
        LoginHistory::record(1, '1.1.1.1', 'AT', 'failed');

        $this->assertSame([], $this->evaluator->triggeredFlags(1, null));
    }

    #[Test]
    public function failed_attempts_older_than_an_hour_do_not_count(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'failed');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'failed');

        Carbon::setTestNow('2026-01-01 12:00:00');
        $this->assertSame([], $this->evaluator->triggeredFlags(1, null));

        Carbon::setTestNow();
    }

    #[Test]
    public function a_successful_login_from_a_different_country_within_24h_is_impossible_travel(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-01 12:00:00');
        $flags = $this->evaluator->triggeredFlags(1, 'RU');

        $this->assertContains('impossible_travel', $flags);
        $this->assertContains('unusual_country', $flags);

        Carbon::setTestNow();
    }

    #[Test]
    public function a_successful_login_from_a_different_country_after_24h_is_not_impossible_travel_but_is_still_unusual(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-03 10:00:00');
        $this->assertSame(['unusual_country'], $this->evaluator->triggeredFlags(1, 'RU'));

        Carbon::setTestNow();
    }

    #[Test]
    public function a_country_already_seen_within_30_days_is_not_unusual_even_if_not_the_most_recent(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-02 10:00:00');
        LoginHistory::record(1, '1.1.1.1', 'RU', 'success');

        Carbon::setTestNow('2026-01-03 10:00:00');
        $this->assertSame([], $this->evaluator->triggeredFlags(1, 'AT'));

        Carbon::setTestNow();
    }

    #[Test]
    public function logins_from_different_countries_within_the_minimum_gap_do_not_count_as_impossible_travel(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00.000000');
        LoginHistory::record(1, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-01 10:00:05.000000');
        $this->assertSame(['unusual_country'], $this->evaluator->triggeredFlags(1, 'RU'));

        Carbon::setTestNow();
    }
}
