<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function setting_left_at_marks_the_member_ausgetreten(): void
    {
        $member = Member::factory()->create(['status' => 'aktiv', 'left_at' => null]);

        $member->setLeftAt('2026-03-01');

        $this->assertSame('2026-03-01', $member->fresh()->left_at->toDateString());
        $this->assertSame('ausgetreten', $member->fresh()->status);
    }

    #[Test]
    public function clearing_left_at_marks_the_member_aktiv(): void
    {
        $member = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2026-03-01']);

        $member->setLeftAt(null);

        $this->assertNull($member->fresh()->left_at);
        $this->assertSame('aktiv', $member->fresh()->status);
    }

    #[Test]
    public function setting_status_to_ruhend_leaves_left_at_untouched(): void
    {
        $member = Member::factory()->create(['status' => 'aktiv', 'left_at' => null]);

        $member->setStatus('ruhend');

        $this->assertNull($member->fresh()->left_at);
        $this->assertSame('ruhend', $member->fresh()->status);
    }

    #[Test]
    public function setting_status_to_aktiv_also_clears_left_at(): void
    {
        $member = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2026-03-01']);

        $member->setStatus('aktiv');

        $this->assertNull($member->fresh()->left_at);
        $this->assertSame('aktiv', $member->fresh()->status);
    }

    #[Test]
    public function active_scope_only_matches_aktiv_status(): void
    {
        Member::factory()->create(['status' => 'aktiv']);
        Member::factory()->create(['status' => 'ruhend']);
        Member::factory()->create(['status' => 'ausgetreten']);

        $this->assertSame(1, Member::active()->count());
    }
}
