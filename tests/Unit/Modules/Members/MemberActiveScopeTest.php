<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberActiveScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_scope_excludes_members_with_a_left_at_date(): void
    {
        Member::factory()->create(['firstname' => 'Aktiv', 'left_at' => null]);
        Member::factory()->create(['firstname' => 'Ausgetreten', 'left_at' => '2025-01-01']);

        $active = Member::query()->active()->pluck('firstname');

        $this->assertSame(['Aktiv'], $active->all());
    }
}
