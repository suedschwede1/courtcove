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

class MemberFormRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, string> field id => submitted name */
    private const FIELDS = [
        'mf-firstname' => 'firstname',
        'mf-lastname' => 'lastname',
        'mf-birthdate' => 'birthdate',
        'mf-gender' => 'gender',
        'mf-email' => 'email',
        'mf-phone' => 'phone',
        'mf-street' => 'street',
        'mf-zip' => 'zip',
        'mf-city' => 'city',
        'mf-member-number' => 'member_number',
        'mf-nuliga-id' => 'nuliga_id',
        'mf-user-id' => 'user_id',
        'mf-fee-category' => 'fee_category_id',
        'mf-joined-at' => 'joined_at',
        'mf-left-at' => 'left_at',
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['create' => ['create'], 'edit' => ['edit']];
    }

    private function page(string $which): string
    {
        $url = $which === 'create'
            ? route('members.create')
            : route('members.edit', Member::factory()->create());

        return (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
    }

    #[Test]
    #[DataProvider('pages')]
    public function every_field_has_a_label_an_id_and_its_original_name(string $which): void
    {
        $html = $this->page($which);

        foreach (self::FIELDS as $id => $name) {
            $this->assertStringContainsString('<label class="ds-field__label" for="'.$id.'">', $html, "label for {$id}");
            if ($id === 'mf-user-id') {
                $this->assertMatchesRegularExpression('/<input[^>]*id="mf-user-id"/', $html);
                $this->assertMatchesRegularExpression('/<input type="hidden" name="user_id"/', $html);

                continue;
            }
            $this->assertMatchesRegularExpression(
                '/<(input|select)[^>]*id="'.$id.'"[^>]*name="'.$name.'"|<(input|select)[^>]*name="'.$name.'"[^>]*id="'.$id.'"/',
                $html,
                "element {$id}",
            );
        }
        $this->assertStringNotContainsString('ui-field', $html);
        $this->assertStringNotContainsString('ui-card', $html);
    }

    #[Test]
    public function validation_error_links_the_message_to_the_field(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->from(route('members.create'))
            ->post(route('members.store'), ['member_number' => 1, 'lastname' => 'Muster'])
            ->assertRedirect(route('members.create'));

        $html = (string) $this->actingAs($admin)->get(route('members.create'))->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="mf-firstname"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="mf-firstname"[^>]*aria-describedby="mf-firstname-error"/', $html);
        $this->assertStringContainsString('id="mf-firstname-error"', $html);
    }

    #[Test]
    public function payment_page_rows_have_named_checkboxes_with_labels(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $html = (string) $this->actingAs($this->admin())
            ->get(route('members.payments.create', $member))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="checkbox" name="member_due_ids\[\]"[^>]*aria-label="[^"]+"/', $html);
        $this->assertStringContainsString('for="pf-amount"', $html);
        $this->assertStringNotContainsString('style="', $html);
        $this->assertStringNotContainsString('ui-card', $html);
    }
}
