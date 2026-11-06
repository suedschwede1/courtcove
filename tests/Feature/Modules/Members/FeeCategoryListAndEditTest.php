<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Beitragskategorien: every existing category is listed (also without a fiscal year or rates)
 * and can be renamed and given rates per fiscal year on its edit page.
 */
class FeeCategoryListAndEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function fiscalYear(string $name = '2025/26', string $start = '2025-09-01', string $end = '2026-08-31'): FiscalYear
    {
        return FiscalYear::create(['name' => $name, 'starts_on' => $start, 'ends_on' => $end]);
    }

    #[Test]
    public function categories_are_listed_when_no_fiscal_year_and_no_rate_exists(): void
    {
        $full = FeeCategory::create(['name' => 'Vollmitglied']);
        FeeCategory::create(['name' => 'Jugend']);

        $this->actingAs($this->admin())->get(route('members.fee-categories.index'))
            ->assertOk()
            ->assertSee('Vollmitglied')
            ->assertSee('Jugend')
            ->assertSee(__('members::admin.fee_categories.no_rate'))
            ->assertSee(route('members.fee-categories.edit', $full), false)
            ->assertSee(__('members::admin.fee_categories.no_fiscal_year'))
            ->assertSee(route('members.fiscal-years.create'), false);
    }

    #[Test]
    public function categories_without_a_rate_show_the_no_rate_state_when_a_fiscal_year_exists(): void
    {
        $this->fiscalYear();
        FeeCategory::create(['name' => 'Vollmitglied']);

        $this->actingAs($this->admin())->get(route('members.fee-categories.index'))
            ->assertOk()
            ->assertSee('Vollmitglied')
            ->assertSee(__('members::admin.fee_categories.no_rate'))
            ->assertDontSee(__('members::admin.fee_categories.no_fiscal_year'));
    }

    #[Test]
    public function the_list_shows_the_rates_of_every_fiscal_year(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $old = $this->fiscalYear('2024/25', '2024-09-01', '2025-08-31');
        $new = $this->fiscalYear();
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $old->id, 'amount' => '110.00']);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $new->id, 'amount' => '120.00']);

        $this->actingAs($this->admin())->get(route('members.fee-categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['2024/25', '110,00', '2025/26', '120,00'])
            ->assertDontSee(__('members::admin.fee_categories.no_rate'));
    }

    #[Test]
    public function the_index_without_any_category_explains_what_to_do(): void
    {
        $this->actingAs($this->admin())->get(route('members.fee-categories.index'))
            ->assertOk()
            ->assertSee(__('members::admin.fee_categories.empty'));
    }

    #[Test]
    public function the_edit_page_shows_the_name_and_one_rate_field_per_fiscal_year(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $old = $this->fiscalYear('2024/25', '2024-09-01', '2025-08-31');
        $new = $this->fiscalYear();
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $old->id, 'amount' => '110.00']);

        $this->actingAs($this->admin())->get(route('members.fee-categories.edit', $category))
            ->assertOk()
            ->assertSee('value="Vollmitglied"', false)
            ->assertSee('name="rates['.$old->id.']"', false)
            ->assertSee('name="rates['.$new->id.']"', false)
            ->assertSee('value="110.00"', false);
    }

    #[Test]
    public function the_edit_page_without_a_fiscal_year_links_to_creating_one(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);

        $this->actingAs($this->admin())->get(route('members.fee-categories.edit', $category))
            ->assertOk()
            ->assertSee('value="Vollmitglied"', false)
            ->assertSee(__('members::admin.fee_categories.no_fiscal_year'))
            ->assertSee(route('members.fiscal-years.create'), false)
            ->assertDontSee('name="rates[', false);
    }

    #[Test]
    public function saving_renames_the_category_and_adds_and_changes_rates(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $old = $this->fiscalYear('2024/25', '2024-09-01', '2025-08-31');
        $new = $this->fiscalYear();
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $old->id, 'amount' => '110.00']);

        $this->actingAs($this->admin())->put(route('members.fee-categories.update', $category), [
            'name' => 'Erwachsene',
            'rates' => [$old->id => '115.50', $new->id => '125'],
        ])->assertRedirect(route('members.fee-categories.index'))->assertSessionHasNoErrors();

        $this->assertSame('Erwachsene', $category->fresh()?->name);
        $this->assertSame(2, FeeCategoryRate::count());
        $this->assertDatabaseHas('fee_category_rates', ['fee_category_id' => $category->id, 'fiscal_year_id' => $old->id, 'amount' => 115.50]);
        $this->assertDatabaseHas('fee_category_rates', ['fee_category_id' => $category->id, 'fiscal_year_id' => $new->id, 'amount' => 125.00]);
    }

    #[Test]
    public function an_empty_rate_field_keeps_the_existing_rate(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = $this->fiscalYear();
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $year->id, 'amount' => '110.00']);

        $this->actingAs($this->admin())->put(route('members.fee-categories.update', $category), [
            'name' => 'Vollmitglied',
            'rates' => [$year->id => ''],
        ])->assertRedirect(route('members.fee-categories.index'));

        $this->assertDatabaseHas('fee_category_rates', ['fee_category_id' => $category->id, 'amount' => 110.00]);
    }

    #[Test]
    public function saving_without_rates_only_renames(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);

        $this->actingAs($this->admin())->put(route('members.fee-categories.update', $category), [
            'name' => 'Erwachsene',
        ])->assertRedirect(route('members.fee-categories.index'));

        $this->assertSame('Erwachsene', $category->fresh()?->name);
        $this->assertSame(0, FeeCategoryRate::count());
    }

    /**
     * Rates keyed '__YEAR__' are re-keyed to the fiscal year created in the test.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidUpdates(): array
    {
        return [
            'name missing' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 65)], 'name'],
            'name as array' => [['name' => ['x']], 'name'],
            'rates not an array' => [['name' => 'Ok', 'rates' => 'x'], 'rates'],
            'negative rate' => [['name' => 'Ok', 'rates' => ['__YEAR__' => '-1']], 'rates.__YEAR__'],
            'rate above column limit' => [['name' => 'Ok', 'rates' => ['__YEAR__' => '1000000']], 'rates.__YEAR__'],
            'rate as array' => [['name' => 'Ok', 'rates' => ['__YEAR__' => ['1']]], 'rates.__YEAR__'],
            'rate with text' => [['name' => 'Ok', 'rates' => ['__YEAR__' => 'abc']], 'rates.__YEAR__'],
            'unknown fiscal year' => [['name' => 'Ok', 'rates' => ['999999' => '10']], 'rates'],
            'non numeric fiscal year key' => [['name' => 'Ok', 'rates' => ['abc' => '10']], 'rates'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('invalidUpdates')]
    public function invalid_input_is_rejected_without_a_server_error(array $payload, string $errorKey): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = $this->fiscalYear();
        if (isset($payload['rates']) && is_array($payload['rates']) && array_key_exists('__YEAR__', $payload['rates'])) {
            $payload['rates'] = [$year->id => $payload['rates']['__YEAR__']];
        }
        $errorKey = str_replace('__YEAR__', (string) $year->id, $errorKey);

        $this->actingAs($this->admin())
            ->from(route('members.fee-categories.edit', $category))
            ->put(route('members.fee-categories.update', $category), $payload)
            ->assertRedirect(route('members.fee-categories.edit', $category))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame('Vollmitglied', $category->fresh()?->name);
        $this->assertSame(0, FeeCategoryRate::count());
    }

    #[Test]
    public function the_pages_render_after_array_shaped_input_was_rejected(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = $this->fiscalYear();

        $this->actingAs($this->admin())
            ->from(route('members.fee-categories.edit', $category))
            ->followingRedirects()
            ->put(route('members.fee-categories.update', $category), ['name' => ['x'], 'rates' => [$year->id => ['1']]])
            ->assertOk()
            ->assertSee('value="Vollmitglied"', false);

        $this->actingAs($this->admin())
            ->from(route('members.fee-categories.index'))
            ->followingRedirects()
            ->post(route('members.fee-categories.store'), ['name' => ['x']])
            ->assertOk();

        $this->actingAs($this->admin())->get(route('members.fee-categories.index').'?fiscal_year_id[]=1')->assertOk();
    }

    #[Test]
    public function an_unknown_category_gives_404_to_admins(): void
    {
        $this->actingAs($this->admin())->get('/members/fee-categories/999999/edit')->assertNotFound();
        $this->actingAs($this->admin())->put('/members/fee-categories/999999', ['name' => 'x'])->assertNotFound();
    }

    #[Test]
    public function users_without_the_members_privilege_get_403_before_the_id_is_resolved(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $assist = User::factory()->status('assist')->create();
        $assist->syncPrivileges(['admin.booking']);
        $assist = $assist->fresh();
        $this->assertNotNull($assist);

        foreach ([$category->id, 999999] as $id) {
            $this->actingAs($assist)->get('/members/fee-categories/'.$id.'/edit')->assertForbidden();
            $this->actingAs($assist)->put('/members/fee-categories/'.$id, ['name' => 'Hack'])->assertForbidden();
        }
        $this->actingAs($assist)->get(route('members.fee-categories.index'))->assertForbidden();

        $this->assertSame('Vollmitglied', $category->fresh()?->name);
    }

    #[Test]
    public function guests_are_sent_to_the_login(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);

        $this->get('/members/fee-categories/'.$category->id.'/edit')->assertRedirect(route('login'));
        $this->put('/members/fee-categories/'.$category->id, ['name' => 'Hack'])->assertRedirect(route('login'));
        $this->assertSame('Vollmitglied', $category->fresh()?->name);
    }

    #[Test]
    public function an_assist_with_the_members_privilege_can_edit_after_a_real_login(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $assist = User::factory()->status('assist')->create(['email' => 'kassier@example.test', 'pw' => bcrypt('secret123')]);
        $assist->syncPrivileges(['admin.members']);

        $this->post('/login', ['email' => 'kassier@example.test', 'password' => 'secret123']);
        $this->assertAuthenticated();

        $this->get(route('members.fee-categories.index'))->assertOk()->assertSee('Vollmitglied');
        $this->get(route('members.fee-categories.edit', $category))->assertOk();
        $this->put(route('members.fee-categories.update', $category), ['name' => 'Erwachsene'])
            ->assertRedirect(route('members.fee-categories.index'));
        $this->assertSame('Erwachsene', $category->fresh()?->name);
    }

    #[Test]
    public function the_matrix_rejects_unknown_category_ids_and_array_shaped_year_without_a_server_error(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = $this->fiscalYear();

        $this->actingAs($this->admin())->post(route('members.fee-categories.rates.store'), [
            'fiscal_year_id' => $year->id,
            'rates' => [999999 => '10'],
        ])->assertSessionHasErrors('rates');

        $this->actingAs($this->admin())->post(route('members.fee-categories.rates.store'), [
            'fiscal_year_id' => [$year->id],
            'rates' => [$category->id => '10'],
        ])->assertSessionHasErrors('fiscal_year_id');

        $this->actingAs($this->admin())->post(route('members.fee-categories.rates.store'), [
            'fiscal_year_id' => $year->id,
            'rates' => [$category->id => '1000000'],
        ])->assertSessionHasErrors('rates.'.$category->id);

        $this->assertSame(0, FeeCategoryRate::count());
    }

    #[Test]
    public function creating_a_category_rejects_array_and_overlong_names(): void
    {
        $this->actingAs($this->admin())->post(route('members.fee-categories.store'), ['name' => ['x']])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin())->post(route('members.fee-categories.store'), ['name' => str_repeat('a', 65)])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, FeeCategory::count());
    }
}
