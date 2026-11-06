<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\MailCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Array-shaped input (`name[]=x`) where a single value is expected, and a single value where a list is expected,
 * is rejected by validation; re-showing the form with that old input must not fail (old() then returns an array).
 */
class MembersFormArrayInputTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * method, action, form page, fields sent as arrays, fields sent as plain strings (list fields).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: list<string>, 4: list<string>}>
     */
    public static function forms(): array
    {
        return [
            'fiscal year create' => ['post', '/members/fiscal-years', '/members/fiscal-years/create', ['name', 'starts_on', 'ends_on', 'faellig_am'], []],
            'fiscal year edit' => ['put', '/members/fiscal-years/{fy}', '/members/fiscal-years/{fy}/edit', ['name', 'starts_on', 'ends_on', 'faellig_am'], []],
            'function create' => ['post', '/members/functions', '/members/functions/create', ['name', 'is_board', 'tasks'], ['mail_category_ids']],
            'function edit' => ['put', '/members/functions/{role}', '/members/functions/{role}/edit', ['name', 'is_board', 'tasks'], ['mail_category_ids']],
            'member create' => ['post', '/members', '/members/create', ['member_number', 'nuliga_id', 'user_id', 'firstname', 'lastname', 'birthdate', 'gender', 'email', 'phone', 'street', 'zip', 'city', 'fee_category_id', 'joined_at'], ['club_roles']],
            'member edit' => ['put', '/members/{member}', '/members/{member}/edit', ['member_number', 'nuliga_id', 'user_id', 'firstname', 'lastname', 'birthdate', 'gender', 'email', 'phone', 'street', 'zip', 'city', 'fee_category_id', 'joined_at', 'left_at', 'notiz'], ['club_roles']],
            'anonymize' => ['post', '/members/{member}/anonymize', '/members/{member}/edit', ['confirmation'], []],
            'payment' => ['post', '/members/{member}/payments', '/members/{member}/payments/create', ['amount', 'paid_at', 'note'], ['member_due_ids']],
            'manual due (dues page)' => ['post', '/members/dues', '/members/dues', ['member_id', 'fiscal_year_id', 'fee_category_id', 'amount', 'teilbetrag', 'kommentar'], []],
            'manual due (detail page)' => ['post', '/members/dues', '/members/{member}/edit', ['member_id', 'fiscal_year_id', 'fee_category_id', 'amount', 'teilbetrag', 'kommentar'], []],
            'generate dues' => ['post', '/members/dues/generate', '/members/dues', ['fiscal_year_id'], []],
            'bulk mark paid' => ['post', '/members/dues/bulk-mark-paid', '/members/dues', ['bezahlt_bis_fiscal_year_id', 'fiscal_year_id'], ['due_ids']],
            'mail sender' => ['post', '/members/mail/sender', '/members/mail/config', ['from_name', 'from_address'], []],
            'mail category' => ['post', '/members/mail/categories', '/members/mail/config', ['name'], []],
            'mail category edit' => ['put', '/members/mail/categories/{mailcat}', '/members/mail/config', ['name'], []],
            'mail send' => ['post', '/members/mail/send', '/members/mail', ['audience', 'subject', 'body'], []],
            'mail test' => ['post', '/members/mail/test', '/members/mail', ['test_email', 'subject', 'body'], []],
            'mail template' => ['post', '/members/mail/templates', '/members/mail', ['name', 'subject', 'body'], []],
            'data protection' => ['post', '/members/dsgvo', '/members/dsgvo', ['retention_years'], []],
            'fee category' => ['post', '/members/fee-categories', '/members/fee-categories', ['name'], []],
        ];
    }

    /**
     * @param  list<string>  $arrayFields
     * @param  list<string>  $listFields
     */
    #[Test]
    #[DataProvider('forms')]
    public function array_shaped_input_is_rejected_and_the_form_shows_again(string $method, string $action, string $page, array $arrayFields, array $listFields): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $category = FeeCategory::create(['name' => 'Erwachsen']);
        $member = Member::factory()->create(['fee_category_id' => $category->id]);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fiscalYear->id, 'fee_category_id' => $category->id, 'amount' => 100]);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);
        $mailCategory = MailCategory::create(['name' => 'Newsletter']);
        $ids = ['{fy}' => $fiscalYear->id, '{role}' => $role->id, '{member}' => $member->id, '{mailcat}' => $mailCategory->id];
        $action = strtr($action, $ids);
        $page = strtr($page, $ids);

        $data = [];
        foreach ($arrayFields as $field) {
            $data[$field] = ['x', 'y'];
        }
        foreach ($listFields as $field) {
            $data[$field] = 'x';
        }

        $response = $this->actingAs($admin)->from($page)->{$method}($action, $data);
        $this->assertContains($response->status(), [302, 422], $action.' gave '.$response->status());

        // The form page re-renders with the rejected old input.
        $this->actingAs($admin)->get($page)->assertOk();
    }
}
