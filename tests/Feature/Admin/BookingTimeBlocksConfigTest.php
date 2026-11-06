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

/** Global "Zeitblock min./max. buchbar" under Verhalten; the court form no longer has them. */
class BookingTimeBlocksConfigTest extends TestCase
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
            'time_block_bookable_min' => '60', 'time_block_bookable_max' => '60',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function courtPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Center', 'alias' => '', 'status' => 'enabled', 'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'name_visibility' => 'private', 'time_start' => '08:00', 'time_end' => '22:00', 'time_block' => 60,
            'min_range_book' => 0, 'range_book' => 56, 'short_booking_window' => 0, 'range_cancel' => 24, 'label_free' => '',
        ], $overrides);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    #[Test]
    public function minutes_are_saved_as_seconds(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior(['time_block_bookable_min' => '30', 'time_block_bookable_max' => '90']))
            ->assertSessionHasNoErrors();

        $this->assertSame('1800', Option::getValue('booking.time_block_bookable_min'));
        $this->assertSame('5400', Option::getValue('booking.time_block_bookable_max'));
    }

    #[Test]
    public function zero_max_means_unlimited_and_is_accepted_with_any_minimum(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior(['time_block_bookable_min' => '90', 'time_block_bookable_max' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame('0', Option::getValue('booking.time_block_bookable_max'));
    }

    #[Test]
    public function an_emptied_field_removes_the_option_so_the_court_values_apply_again(): void
    {
        Option::create(['key' => 'booking.time_block_bookable_min', 'value' => '1800', 'locale' => null]);

        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior(['time_block_bookable_min' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Option::getValue('booking.time_block_bookable_min'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInput(): array
    {
        return [
            'negative min' => [['time_block_bookable_min' => '-1'], 'time_block_bookable_min'],
            'fraction min' => [['time_block_bookable_min' => '7.5'], 'time_block_bookable_min'],
            'text max' => [['time_block_bookable_max' => 'eine Stunde'], 'time_block_bookable_max'],
            'array max' => [['time_block_bookable_max' => ['60']], 'time_block_bookable_max'],
            'max below min' => [['time_block_bookable_min' => '90', 'time_block_bookable_max' => '60'], 'time_block_bookable_max'],
            'more than a day' => [['time_block_bookable_max' => '1441'], 'time_block_bookable_max'],
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

        $this->assertNull(Option::getValue('booking.time_block_bookable_min'));
        $this->assertNull(Option::getValue('booking.time_block_bookable_max'));
    }

    #[Test]
    public function the_behavior_page_shows_both_fields_in_minutes_with_help(): void
    {
        Option::create(['key' => 'booking.time_block_bookable_min', 'value' => '1800', 'locale' => null]);
        Option::create(['key' => 'booking.time_block_bookable_max', 'value' => '3600', 'locale' => null]);

        $html = $this->actingAs($this->admin())->get('/admin/config/verhalten')->assertOk()->getContent();
        $xp = $this->xpath($html);

        foreach (['time_block_bookable_min' => '30', 'time_block_bookable_max' => '60'] as $name => $minutes) {
            $input = $xp->query('//input[@type="number"][@name="'.$name.'"]')->item(0);
            $this->assertNotNull($input, $name);
            $this->assertSame($minutes, $input->getAttribute('value'), $name);
            $this->assertSame(1, $xp->query('//label[@for="'.$input->getAttribute('id').'"]')->length, $name.' label');
        }
        $this->assertStringContainsString(e(__('booking.admin.time_blocks.min_hint')), $html);
        $this->assertStringContainsString(e(__('booking.admin.time_blocks.max_hint')), $html);
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['title', 'min_label', 'min_hint', 'max_label', 'max_hint', 'max_below_min'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.admin.time_blocks.'.$key, __('booking.admin.time_blocks.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }

    // --- court form -------------------------------------------------------------------------------------

    #[Test]
    public function the_court_form_has_no_bookable_time_block_fields_any_more(): void
    {
        $square = Square::factory()->create();

        foreach ([route('admin.squares.create'), route('admin.squares.edit', $square)] as $url) {
            $xp = $this->xpath($this->actingAs($this->admin())->get($url)->assertOk()->getContent());
            $this->assertSame(0, $xp->query('//input[@name="time_block_bookable"]')->length, $url);
            $this->assertSame(0, $xp->query('//input[@name="time_block_bookable_max"]')->length, $url);
            $this->assertSame(1, $xp->query('//input[@name="time_block"]')->length, $url.' grid stays');
            $this->assertSame(1, $xp->query('//input[@name="pseudo_time_block_bookable"]')->length, $url.' pseudo stays');
        }
    }

    #[Test]
    public function saving_a_court_keeps_its_stored_columns(): void
    {
        $square = Square::factory()->create(['time_block_bookable' => 2700, 'time_block_bookable_max' => 5400]);

        $this->actingAs($this->admin())->put(route('admin.squares.update', $square), $this->courtPayload([
            'time_block_bookable' => '1', 'time_block_bookable_max' => '1',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $square->refresh();
        $this->assertSame(2700, (int) $square->time_block_bookable);
        $this->assertSame(5400, (int) $square->time_block_bookable_max);
    }

    #[Test]
    public function a_new_court_stores_the_global_values_in_its_columns(): void
    {
        Option::create(['key' => 'booking.time_block_bookable_min', 'value' => '3600', 'locale' => null]);
        Option::create(['key' => 'booking.time_block_bookable_max', 'value' => '7200', 'locale' => null]);

        $this->actingAs($this->admin())->post(route('admin.squares.store'), $this->courtPayload(['name' => 'New']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $square = Square::query()->where('name', 'New')->firstOrFail();
        $this->assertSame(3600, (int) $square->time_block_bookable);
        $this->assertSame(7200, (int) $square->time_block_bookable_max);
    }

    #[Test]
    public function a_new_court_without_global_values_copies_the_first_court(): void
    {
        Square::factory()->create(['time_block_bookable' => 2700, 'time_block_bookable_max' => 5400]);

        $this->actingAs($this->admin())->post(route('admin.squares.store'), $this->courtPayload(['name' => 'New']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $square = Square::query()->where('name', 'New')->firstOrFail();
        $this->assertSame(2700, (int) $square->time_block_bookable);
        $this->assertSame(5400, (int) $square->time_block_bookable_max);
    }
}
