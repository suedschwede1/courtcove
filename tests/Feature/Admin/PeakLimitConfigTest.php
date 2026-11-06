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

/** Peak-time limit configuration: everything under Verhalten, per court only the switch. */
class PeakLimitConfigTest extends TestCase
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
            'max_active_bookings' => '5',
            'peak_limit_enabled' => '1', 'peak_limit_max' => '2',
            'peak_limit_w1_start' => '08:00', 'peak_limit_w1_end' => '12:00',
            'peak_limit_w2_start' => '17:00', 'peak_limit_w2_end' => '21:00',
        ], $overrides);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    #[Test]
    public function the_peak_limit_max_is_saved_with_switch_and_windows(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior())
            ->assertRedirect(route('admin.config.behavior.edit'))->assertSessionHasNoErrors();

        $this->assertSame('2', Option::getValue('peak_limit.max'));
        $this->assertSame('1', Option::getValue('peak_limit.enabled'));
        $this->assertSame('17:00', Option::getValue('peak_limit.window_2_start'));
    }

    #[Test]
    public function zero_is_accepted_as_peak_limit_max_and_means_the_fallback(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior(['peak_limit_max' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame('0', Option::getValue('peak_limit.max'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInput(): array
    {
        return [
            'negative max' => [['peak_limit_max' => '-1'], 'peak_limit_max'],
            'fraction max' => [['peak_limit_max' => '1.5'], 'peak_limit_max'],
            'text max' => [['peak_limit_max' => 'zwei'], 'peak_limit_max'],
            'array max' => [['peak_limit_max' => ['2']], 'peak_limit_max'],
            'hour 24' => [['peak_limit_w1_start' => '24:00'], 'peak_limit_w1_start'],
            'minute 60' => [['peak_limit_w2_end' => '20:60'], 'peak_limit_w2_end'],
            'no colon' => [['peak_limit_w1_end' => '1200'], 'peak_limit_w1_end'],
            'array time' => [['peak_limit_w1_start' => ['08:00']], 'peak_limit_w1_start'],
            'window 1 end before start' => [['peak_limit_w1_start' => '12:00', 'peak_limit_w1_end' => '08:00'], 'peak_limit_w1_end'],
            'window 2 empty' => [['peak_limit_w2_start' => '18:00', 'peak_limit_w2_end' => '18:00'], 'peak_limit_w2_end'],
            'windows overlap' => [['peak_limit_w1_end' => '18:00'], 'peak_limit_w2_start'],
            'window 2 before window 1' => [['peak_limit_w1_start' => '17:00', 'peak_limit_w1_end' => '21:00', 'peak_limit_w2_start' => '08:00', 'peak_limit_w2_end' => '12:00'], 'peak_limit_w2_start'],
        ];
    }

    /** @param array<string, mixed> $overrides */
    #[Test]
    #[DataProvider('invalidInput')]
    public function invalid_peak_input_is_rejected_without_saving(array $overrides, string $field): void
    {
        $this->actingAs($this->admin())->from(route('admin.config.behavior.edit'))
            ->put('/admin/config/verhalten', $this->behavior($overrides))
            ->assertRedirect(route('admin.config.behavior.edit'))
            ->assertSessionHasErrors($field);

        $this->assertNull(Option::getValue('peak_limit.max'));
    }

    #[Test]
    public function windows_that_touch_are_allowed(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', $this->behavior(['peak_limit_w1_end' => '17:00']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_behavior_page_has_the_peak_card_with_limit_field_and_example_help(): void
    {
        Option::create(['key' => 'peak_limit.max', 'value' => '3', 'locale' => null]);

        $html = $this->actingAs($this->admin())->get('/admin/config/verhalten')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $input = $xp->query('//input[@type="number"][@name="peak_limit_max"]')->item(0);
        $this->assertNotNull($input);
        $this->assertSame('3', $input->getAttribute('value'));
        $this->assertSame('0', $input->getAttribute('min'));
        $this->assertSame(1, $xp->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
        $this->assertStringContainsString(__('booking.admin.peak_limit.max_label'), $html);
        $this->assertStringContainsString(e(__('booking.admin.peak_limit.max_hint')), $html);
        $this->assertStringContainsString(e(__('booking.admin.peak_limit.example')), $html);
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['max_label', 'max_hint', 'example', 'window_order'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.admin.peak_limit.'.$key, __('booking.admin.peak_limit.'.$key, [], $locale), $locale.' '.$key);
            }
        }
        foreach (['peak_limit_global_off', 'peak_limit_global_off_link'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.admin.squares.'.$key, __('booking.admin.squares.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }

    // --- court form -----------------------------------------------------------------------------------

    #[Test]
    public function the_court_switch_is_visible_and_disabled_with_a_hint_when_the_limit_is_globally_off(): void
    {
        $square = Square::factory()->create();
        $square->setMeta('peak_limit_enabled', '1');

        $html = $this->actingAs($this->admin())->get(route('admin.squares.edit', $square))->assertOk()->getContent();
        $xp = $this->xpath($html);

        $box = $xp->query('//input[@type="checkbox"][@name="peak_limit_enabled"]')->item(0);
        $this->assertNotNull($box, 'switch always visible');
        $this->assertTrue($box->hasAttribute('disabled'));
        $this->assertTrue($box->hasAttribute('checked'), 'stored value shown');
        $this->assertStringContainsString(__('booking.admin.squares.peak_limit_global_off'), $html);
        $this->assertSame(1, $xp->query('//p[@id="sf-peak-global-off"]/a[@href="'.route('admin.config.behavior.edit').'"]')->length);
    }

    #[Test]
    public function the_court_switch_is_editable_when_the_limit_is_globally_on(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $html = $this->actingAs($this->admin())->get(route('admin.squares.create'))->assertOk()->getContent();
        $box = $this->xpath($html)->query('//input[@type="checkbox"][@name="peak_limit_enabled"]')->item(0);

        $this->assertNotNull($box);
        $this->assertFalse($box->hasAttribute('disabled'));
        $this->assertStringNotContainsString(__('booking.admin.squares.peak_limit_global_off'), $html);
    }

    #[Test]
    public function saving_a_court_while_the_limit_is_globally_off_keeps_its_switch(): void
    {
        $square = Square::factory()->create(['name' => 'Center']);
        $square->setMeta('peak_limit_enabled', '1');

        $this->actingAs($this->admin())->put(route('admin.squares.update', $square), [
            'name' => 'Center', 'alias' => '', 'status' => 'enabled', 'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'name_visibility' => 'private', 'time_start' => '08:00', 'time_end' => '22:00',
            'time_block' => 60, 'time_block_bookable' => 60, 'time_block_bookable_max' => 60,
            'min_range_book' => 0, 'range_book' => 56, 'short_booking_window' => 0, 'range_cancel' => 24, 'label_free' => '',
        ])->assertRedirect();

        $this->assertSame('1', $square->fresh()->getMeta('peak_limit_enabled'));
    }

    #[Test]
    public function unticking_the_global_switch_turns_the_peak_limit_off(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);
        $payload = $this->behavior();
        unset($payload['peak_limit_enabled']);

        $this->actingAs($this->admin())->put('/admin/config/verhalten', $payload)->assertSessionHasNoErrors();

        $this->assertSame('0', Option::getValue('peak_limit.enabled'));
    }

    #[Test]
    public function the_court_switch_is_saved_when_the_limit_is_globally_on(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);
        $square = Square::factory()->create(['name' => 'Center']);
        $square->setMeta('peak_limit_enabled', '1');

        $this->actingAs($this->admin())->put(route('admin.squares.update', $square), [
            'name' => 'Center', 'alias' => '', 'status' => 'enabled', 'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'name_visibility' => 'private', 'time_start' => '08:00', 'time_end' => '22:00',
            'time_block' => 60, 'time_block_bookable' => 60, 'time_block_bookable_max' => 60,
            'min_range_book' => 0, 'range_book' => 56, 'short_booking_window' => 0, 'range_cancel' => 24, 'label_free' => '',
        ])->assertRedirect();

        $this->assertSame('0', $square->fresh()->getMeta('peak_limit_enabled'), 'unticked while editable = off');
    }
}
