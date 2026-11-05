<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembersListsRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function render(string $url): string
    {
        $admin = User::factory()->create(['status' => 'admin']);

        return (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();
    }

    #[Test]
    public function dashboard_bars_are_progress_elements(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        Member::factory()->create(['fee_category_id' => $category->id, 'birthdate' => now()->subYears(30)->toDateString()]);

        $html = $this->render('/members/dashboard');

        // five age buckets + unknown + one category
        $this->assertSame(7, substr_count($html, '<progress class="mbr-bar"'));
        $this->assertStringNotContainsString('style=', $html);
    }

    #[Test]
    public function kpis_use_the_shared_tile(): void
    {
        $this->assertSame(3, substr_count($this->render('/members/dashboard'), 'class="ds-kpi"'));
    }

    /** @return array<string, array{string}> */
    public static function listUrls(): array
    {
        return [
            'fiscal years' => ['/members/fiscal-years'],
            'functions' => ['/members/functions'],
            'fee categories' => ['/members/fee-categories'],
        ];
    }

    #[Test]
    #[DataProvider('listUrls')]
    public function lists_use_the_shared_table(string $url): void
    {
        FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        FeeCategory::create(['name' => 'Vollzahler']);
        ClubRole::create(['name' => 'Obmann', 'is_board' => true]);

        $html = $this->render($url);

        $this->assertStringContainsString('ds-table', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[Test]
    public function function_delete_keeps_its_confirm(): void
    {
        $role = ClubRole::create(['name' => 'Obmann', 'is_board' => true]);

        $html = $this->render('/members/functions');

        $this->assertStringContainsString('confirm(', $html);
        $this->assertStringContainsString(route('members.functions.destroy', $role), $html);
    }
}
