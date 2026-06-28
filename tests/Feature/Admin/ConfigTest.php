<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/admin/config')->assertForbidden();
    }

    #[Test]
    public function edit_shows_current_values(): void
    {
        Option::create(['key' => 'service.name', 'value' => 'Buchungssystem', 'locale' => null]);
        Option::create(['key' => 'client.name.full', 'value' => 'Tennis-Booking', 'locale' => null]);
        $this->actingAs($this->admin())->get('/admin/config')->assertOk()->assertSee('Buchungssystem')->assertSee('Tennis-Booking');
    }

    #[Test]
    public function update_writes_default_locale_rows(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'system_name' => 'Neues System',
            'client_name_full' => 'Neuer Name',
            'contact_email' => 'info@example.com',
            'calendar_days' => '5',
            'registration' => '1',
            'maintenance' => '0',
        ])->assertRedirect();

        $this->assertSame('Neues System', Option::getValue('service.name'));
        $this->assertSame('Neuer Name', Option::getValue('client.name.full'));
        $this->assertSame('5', Option::getValue('service.calendar.days'));
    }

    #[Test]
    public function client_contact_fields_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'client_name_short'      => 'ASV',
            'client_phone'           => '666',
            'client_website'         => 'https://example.test',
            'client_website_contact' => 'https://example.test/#kontakt',
            'client_website_imprint' => 'https://example.test/impressum',
            'client_website_privacy' => 'https://example.test/datenschutz',
            'client_email_cc'        => '1',
        ])->assertRedirect();

        $this->assertSame('ASV', Option::getValue('client.name.short'));
        $this->assertSame('666', Option::getValue('client.contact.phone'));
        $this->assertSame('https://example.test', Option::getValue('client.website'));
        $this->assertSame('1', Option::getValue('client.contact.email.user-notifications'));
    }

    #[Test]
    public function service_and_subject_fields_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'service_name_short'   => 'BS',
            'service_description'  => 'Tennisplatz Online Reservierung',
            'subject_type'         => 'die Anlage',
            'subject_square_type'  => 'Platz',
            'subject_square_plural'=> 'Plätze',
            'subject_unit'         => 'Spieler',
            'subject_unit_plural'  => 'Spieler',
        ])->assertRedirect();

        $this->assertSame('BS', Option::getValue('service.name.short'));
        $this->assertSame('Tennisplatz Online Reservierung', Option::getValue('service.meta.description'));
        $this->assertSame('Platz', Option::getValue('subject.square.type'));
        $this->assertSame('Plätze', Option::getValue('subject.square.type.plural'));
    }

    #[Test]
    public function activation_and_day_exceptions_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'activation'   => 'manual',
            'calendar_hide' => "Sunday\n2026-12-25",
        ])->assertRedirect();

        $this->assertSame('manual', Option::getValue('service.user.activation'));
        $this->assertSame("Sunday\n2026-12-25", Option::getValue('service.calendar.day-exceptions'));
    }

    #[Test]
    public function activation_rejects_invalid_value(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'activation' => 'bogus',
        ])->assertSessionHasErrors('activation');
    }

    #[Test]
    public function public_header_uses_system_name(): void
    {
        Option::create(['key' => 'service.name', 'value' => 'Reservierungssystem', 'locale' => null]);
        Option::create(['key' => 'client.name.full', 'value' => 'Vereinsname', 'locale' => null]);

        $this->get('/calendar')
            ->assertOk()
            ->assertSee('Reservierungssystem')
            ->assertDontSee('Vereinsname');
    }
}
