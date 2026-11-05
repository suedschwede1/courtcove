<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Models\Option;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberIsAnonymizableTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function an_active_member_is_not_anonymizable(): void
    {
        $member = Member::factory()->create(['left_at' => null]);

        $this->assertFalse($member->isAnonymizable());
    }

    #[Test]
    public function an_already_anonymized_member_is_not_anonymizable_again(): void
    {
        $member = Member::factory()->create([
            'left_at' => now()->subYears(5)->toDateString(),
            'anonymized_at' => now(),
        ]);

        $this->assertFalse($member->isAnonymizable());
    }

    #[Test]
    public function a_member_departed_within_the_retention_period_is_not_anonymizable(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subYears(2)->toDateString()]);

        $this->assertFalse($member->isAnonymizable());
    }

    #[Test]
    public function a_member_departed_past_the_default_three_year_retention_is_anonymizable(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subYears(3)->subDay()->toDateString()]);

        $this->assertTrue($member->isAnonymizable());
    }

    #[Test]
    public function the_retention_period_respects_the_configured_option(): void
    {
        Option::create(['key' => 'members.anonymization.retention_years', 'value' => '1', 'locale' => null]);

        $member = Member::factory()->create(['left_at' => now()->subYears(2)->toDateString()]);

        $this->assertTrue($member->isAnonymizable());
    }

    #[Test]
    public function a_malformed_retention_option_falls_back_to_the_safe_default(): void
    {
        Option::create(['key' => 'members.anonymization.retention_years', 'value' => 'abc', 'locale' => null]);

        $member = Member::factory()->create(['left_at' => now()->subYears(2)->toDateString()]);

        $this->assertFalse($member->isAnonymizable());
    }

    #[Test]
    public function the_anonymizable_from_date_is_the_departure_plus_the_retention_period(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        Option::create(['key' => 'members.anonymization.retention_years', 'value' => '2', 'locale' => null]);

        $member = Member::factory()->create(['left_at' => '2025-12-31']);

        $this->assertSame('2027-12-31', $member->anonymizableFrom()?->toDateString());
        $this->assertFalse($member->isAnonymizable());

        Carbon::setTestNow('2028-01-01 00:00:01');
        $this->assertTrue($member->isAnonymizable());
    }

    #[Test]
    public function an_active_or_anonymized_member_has_no_anonymizable_from_date(): void
    {
        $this->assertNull(Member::factory()->create(['left_at' => null])->anonymizableFrom());
        $this->assertNull(Member::factory()->create(['left_at' => '2020-01-01', 'anonymized_at' => now()])->anonymizableFrom());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
