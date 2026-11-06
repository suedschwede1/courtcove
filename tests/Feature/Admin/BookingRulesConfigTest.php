<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\Square;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Card "Buchungsregeln" under Verhalten; the court form keeps only per-court settings. */
class BookingRulesConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @param array<string, mixed> $overrides */
    private function behavior(array $overrides = []): array
    {
        return array_merge([
            'registration' => '1', 'activation' => 'manual', 'maintenance' => '0', 'quotes_enabled' => '1',
            'max_active_bookings' => '0', 'peak_limit_enabled' => '0', 'peak_limit_max' => '0',
            'peak_limit_w1_start' => '08:00', 'peak_limit_w1_end' => '12:00',
            'peak_limit_w2_start' => '17:00', 'peak_limit_w2_end' => '21:00',
            'rule_time_block' => '60', 'rule_min_range_book' => '30', 'rule_range_book' => '14',
            'rule_short_booking_window' => '15', 'rule_range_cancel' => '1.5',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function courtPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Center', 'alias' => '', 'status' => 'enabled', 'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'name_visibility' => 'private', 'time_start' => '08:00', 'time_end' => '22:00', 'label_free' => '',
        ], $overrides);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    #[Test]
    public function the_rules_are_saved_in_seconds(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior())->assertSessionHasNoErrors();

        $this->assertSame('3600', Option::getValue('booking.time_block'));
        $this->assertSame('1800', Option::getValue('booking.min_range_book'));
        $this->assertSame((string) (14 * 86400), Option::getValue('booking.range_book'));
        $this->assertSame('900', Option::getValue('booking.short_booking_window'));
        $this->assertSame('5400', Option::getValue('booking.range_cancel'));
    }

    #[Test]
    public function zeros_are_saved_and_an_emptied_field_removes_the_option(): void
    {
        Option::create(['key' => 'booking.range_book', 'value' => '86400', 'locale' => null]);

        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior([
            'rule_min_range_book' => '0', 'rule_short_booking_window' => '0', 'rule_range_cancel' => '0', 'rule_range_book' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('0', Option::getValue('booking.min_range_book'));
        $this->assertSame('0', Option::getValue('booking.range_cancel'));
        $this->assertNull(Option::getValue('booking.range_book'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInput(): array
    {
        return [
            'grid zero' => [['rule_time_block' => '0'], 'rule_time_block'],
            'grid fraction' => [['rule_time_block' => '7.5'], 'rule_time_block'],
            'negative vorlauf' => [['rule_min_range_book' => '-5'], 'rule_min_range_book'],
            'text range' => [['rule_range_book' => 'zwei Wochen'], 'rule_range_book'],
            'array window' => [['rule_short_booking_window' => ['15']], 'rule_short_booking_window'],
            'negative cancel' => [['rule_range_cancel' => '-1'], 'rule_range_cancel'],
            'cancel three decimals' => [['rule_range_cancel' => '0.001'], 'rule_range_cancel'],
            'vorlauf beyond range' => [['rule_min_range_book' => (string) (15 * 1440), 'rule_range_book' => '14'], 'rule_min_range_book'],
        ];
    }

    /** @param array<string, mixed> $overrides */
    #[Test]
    #[DataProvider('invalidInput')]
    public function invalid_input_is_rejected_without_saving(array $overrides, string $field): void
    {
        $this->actingAs($this->admin())->from(route('admin.config.behavior.edit'))
            ->put('/admin/config/verhalten', $this->behavior($overrides))
            ->assertRedirect(route('admin.config.behavior.edit'))
            ->assertSessionHasErrors($field);

        $this->assertNull(Option::getValue('booking.time_block'));
    }

    #[Test]
    public function the_behavior_page_shows_the_rules_card_in_display_units(): void
    {
        foreach (['booking.time_block' => '1800', 'booking.min_range_book' => '600', 'booking.range_book' => '1209600',
            'booking.short_booking_window' => '900', 'booking.range_cancel' => '5400'] as $key => $value) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        }

        $html = $this->actingAs($this->admin())->get('/admin/config/verhalten')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $this->assertStringContainsString(__('booking.admin.booking_rules.title'), $html);
        foreach (['rule_time_block' => '30', 'rule_min_range_book' => '10', 'rule_range_book' => '14', 'rule_short_booking_window' => '15', 'rule_range_cancel' => '1.5'] as $name => $shown) {
            $input = $xp->query('//input[@type="number"][@name="'.$name.'"]')->item(0);
            $this->assertNotNull($input, $name);
            $this->assertSame($shown, $input->getAttribute('value'), $name);
            $this->assertSame(1, $xp->query('//label[@for="'.$input->getAttribute('id').'"]')->length, $name.' label');
        }
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        $keys = ['title', 'intro', 'time_block', 'time_block_hint', 'min_range_book', 'min_range_book_hint', 'range_book', 'range_book_hint',
            'short_booking_window', 'short_booking_window_hint', 'range_cancel', 'range_cancel_hint', 'vorlauf_beyond_range'];
        foreach ($keys as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.admin.booking_rules.'.$key, __('booking.admin.booking_rules.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }

    // --- court form -------------------------------------------------------------------------------------

    #[Test]
    public function the_court_form_keeps_only_per_court_settings(): void
    {
        $square = Square::factory()->create();

        foreach ([route('admin.squares.create'), route('admin.squares.edit', $square)] as $url) {
            $xp = $this->xpath($this->actingAs($this->admin())->get($url)->assertOk()->getContent());
            foreach (['time_block', 'min_range_book', 'range_book', 'short_booking_window', 'range_cancel', 'pseudo_time_block_bookable'] as $gone) {
                $this->assertSame(0, $xp->query('//input[@name="'.$gone.'"]')->length, $url.' '.$gone);
            }
            foreach (['name', 'time_start', 'time_end', 'allow_notes', 'label_free', 'readonly_message', 'peak_limit_enabled'] as $kept) {
                $this->assertSame(1, $xp->query('//input[@name="'.$kept.'"]')->length, $url.' '.$kept);
            }
        }
    }

    #[Test]
    public function saving_a_court_keeps_its_stored_rule_columns_and_meta(): void
    {
        $square = Square::factory()->create(['time_block' => 1800, 'min_range_book' => 600, 'range_book' => 86400, 'range_cancel' => 7200]);
        $square->setMeta('short-booking-window', '900');
        $square->setMeta('pseudo-time-block-bookable', 'true');

        $this->actingAs($this->admin())->put(route('admin.squares.update', $square), $this->courtPayload([
            'time_block' => '5', 'min_range_book' => '5', 'range_book' => '5', 'short_booking_window' => '5', 'range_cancel' => '5',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $square = $square->fresh();
        $this->assertSame([1800, 600, 86400, 7200], [(int) $square->time_block, (int) $square->min_range_book, (int) $square->range_book, (int) $square->range_cancel]);
        $this->assertSame('900', $square->getMeta('short-booking-window'));
        $this->assertSame('true', $square->getMeta('pseudo-time-block-bookable'));
    }

    #[Test]
    public function a_new_court_stores_the_global_rule_values_in_its_columns(): void
    {
        Option::create(['key' => 'booking.time_block', 'value' => '1800', 'locale' => null]);
        Option::create(['key' => 'booking.range_cancel', 'value' => '7200', 'locale' => null]);
        Square::factory()->create(['min_range_book' => 600, 'range_book' => 86400]);

        $this->actingAs($this->admin())->post(route('admin.squares.store'), $this->courtPayload(['name' => 'New']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $square = Square::query()->where('name', 'New')->firstOrFail();
        $this->assertSame(1800, (int) $square->time_block, 'global');
        $this->assertSame(7200, (int) $square->range_cancel, 'global');
        $this->assertSame(600, (int) $square->min_range_book, 'first court');
        $this->assertSame(86400, (int) $square->range_book, 'first court');
    }
}
