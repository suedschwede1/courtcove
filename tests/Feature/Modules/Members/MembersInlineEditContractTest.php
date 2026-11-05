<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the inline-edit wiring (Alpine hooks, data-field cells, save helpers)
 * of the members list, dues list and member edit page so the restyle cannot
 * silently break it.
 */
class MembersInlineEditContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?MemberDue $due = null;

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function pages(): array
    {
        $dueFields = ['status', 'teilbetrag', 'kommentar'];
        $dueCalls = [
            "saveDueField({due}, 'status'",
            "saveDueField({due}, 'teilbetrag'",
            "saveDueField({due}, 'kommentar'",
            'saveDuePaidThrough({due}, this.value)',
        ];

        return [
            'list' => [
                '/members',
                ['firstname', 'status', 'due-status', 'fee_category_id', 'email', 'phone'],
                [
                    'saveMemberField({member}, field, value)',
                    "saveMemberField({member}, 'status'",
                    "saveMemberField({member}, 'fee_category_id'",
                    "saveMemberField({member}, 'email'",
                    "saveMemberField({member}, 'phone'",
                    "saveDueField({due}, 'status'",
                    "saveDueField({due}, 'kommentar'",
                    'saveDuePaidThrough({due}, this.bezahltBis)',
                ],
            ],
            'dues' => ['/members/dues?fiscal_year_id={fy}', $dueFields, $dueCalls],
            'edit' => ['/members/{member}/edit', $dueFields, $dueCalls],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pageUrls(): array
    {
        return array_map(fn (array $case): array => [$case[0]], self::pages());
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $calls
     */
    #[Test]
    #[DataProvider('pages')]
    public function cells_keep_their_inline_edit_wiring(string $page, array $fields, array $calls): void
    {
        $html = $this->render($page);

        foreach ($fields as $field) {
            $this->assertStringContainsString('data-field="'.$field.'"', $html);
        }
        foreach ($calls as $call) {
            $this->assertStringContainsString($this->ids($call), $html);
        }
        $this->assertStringContainsString('@click="if (!editing) editing = true"', $html);
        $this->assertStringContainsString('@click.outside="if (!error) editing = false"', $html);
        $this->assertStringContainsString('@keydown.enter.prevent="$event.target.blur()"', $html);

        if ($page === '/members') {
            $this->assertStringContainsString(url('/members').'/${memberId}/field', $html);
        }
    }

    #[Test]
    #[DataProvider('pageUrls')]
    public function page_carries_the_csrf_token_and_defines_each_save_helper_once(string $page): void
    {
        $html = $this->render($page);

        $this->assertStringContainsString(session()->token(), $html);
        $this->assertStringContainsString("'X-CSRF-TOKEN'", $html);
        foreach (['saveMemberField', 'saveDueField', 'saveDuePaidThrough'] as $fn) {
            $this->assertLessThanOrEqual(1, preg_match_all('/window\.'.$fn.'\s*=/', $html), $fn);
        }
        $this->assertStringContainsString(url('/members').'/dues/${dueId}/field', $html);
        $this->assertStringContainsString(url('/members').'/dues/${dueId}/paid-through', $html);
    }

    private function render(string $page): string
    {
        $admin = User::factory()->create(['status' => 'admin']);

        return (string) $this->actingAs($admin)->get($this->ids($page))->assertOk()->getContent();
    }

    private function ids(string $value): string
    {
        $due = $this->fixture();

        return str_replace(
            ['{member}', '{due}', '{fy}'],
            [(string) $due->member_id, (string) $due->id, (string) $due->fiscal_year_id],
            $value,
        );
    }

    private function fixture(): MemberDue
    {
        if ($this->due !== null) {
            return $this->due;
        }

        $member = Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'status' => 'aktiv',
            'email' => 'anna.muster@example.com',
            'phone' => '0123 4567',
        ]);
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $member->update(['fee_category_id' => $category->id]);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        return $this->due = MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ]);
    }
}
