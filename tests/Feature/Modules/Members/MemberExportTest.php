<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function export_streams_a_csv_with_all_members(): void
    {
        Member::factory()->create(['firstname' => 'Erika', 'lastname' => 'Musterfrau', 'email' => 'erika@example.com']);

        $response = $this->actingAs($this->admin())->get('/members/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Erika', $content);
        $this->assertStringContainsString('Musterfrau', $content);
        $this->assertStringContainsString('erika@example.com', $content);
    }
}
