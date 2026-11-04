<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($member)->get('/admin/config')->assertForbidden();
        $this->actingAs($member)->get('/admin/config/verhalten')->assertForbidden();
    }

    #[Test]
    public function edit_shows_current_values(): void
    {
        Option::create(['key' => 'service.name', 'value' => 'Buchungssystem', 'locale' => null]);
        Option::create(['key' => 'client.name.full', 'value' => 'Tennis-Booking', 'locale' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/config')
            ->assertOk()
            ->assertSee('Buchungssystem')
            ->assertSee('Tennis-Booking');
    }

    #[Test]
    public function behavior_edit_shows_current_values(): void
    {
        Option::create(['key' => 'service.user.activation', 'value' => 'manual', 'locale' => null]);
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/config/verhalten')
            ->assertOk()
            ->assertSee('Stoßzeiten-Limit')
            ->assertSee('manual');
    }

    #[Test]
    public function update_writes_general_configuration_rows(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'system_name' => 'Neues System',
            'client_name_full' => 'Neuer Name',
            'contact_email' => 'info@example.com',
            'registration_email_help' => 'Mit dieser Adresse melden Sie sich an',
            'logo_path' => 'imgs-client/layout/custom-logo.png',
        ])->assertRedirect();

        $this->assertSame('Neues System', Option::getValue('service.name'));
        $this->assertSame('Neuer Name', Option::getValue('client.name.full'));
        $this->assertSame('Mit dieser Adresse melden Sie sich an', Option::getValue('service.user.registration.email_help'));
        $this->assertSame('imgs-client/layout/custom-logo.png', Option::getValue('service.brand.logo_path'));
    }

    #[Test]
    public function client_contact_fields_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'client_name_short' => 'ASV',
            'client_phone' => '666',
            'client_website' => 'https://example.test',
            'client_website_contact' => 'https://example.test/#kontakt',
            'client_website_imprint' => 'https://example.test/impressum',
            'client_website_privacy' => 'https://example.test/datenschutz',
            'client_email_cc' => '1',
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
            'service_name_short' => 'BS',
            'service_description' => 'Tennisplatz Online Reservierung',
            'subject_type' => 'die Anlage',
            'subject_square_type' => 'Platz',
            'subject_square_plural' => 'Plätze',
            'subject_unit' => 'Spieler',
            'subject_unit_plural' => 'Spieler',
        ])->assertRedirect();

        $this->assertSame('BS', Option::getValue('service.name.short'));
        $this->assertSame('Tennisplatz Online Reservierung', Option::getValue('service.meta.description'));
        $this->assertSame('Platz', Option::getValue('subject.square.type'));
        $this->assertSame('Plätze', Option::getValue('subject.square.type.plural'));
    }

    #[Test]
    public function behavior_fields_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', [
            'registration' => '1',
            'activation' => 'manual',
            'maintenance' => '0',
            'peak_limit_enabled' => '1',
            'peak_limit_w1_start' => '08:00',
            'peak_limit_w1_end' => '12:00',
            'peak_limit_w2_start' => '17:00',
            'peak_limit_w2_end' => '21:00',
        ])->assertRedirect();

        $this->assertSame('1', Option::getValue('service.user.registration'));
        $this->assertSame('manual', Option::getValue('service.user.activation'));
        $this->assertSame('1', Option::getValue('peak_limit.enabled'));
        $this->assertSame('08:00', Option::getValue('peak_limit.window_1_start'));
    }

    #[Test]
    public function activation_rejects_invalid_value(): void
    {
        $this->actingAs($this->admin())->put('/admin/config/verhalten', [
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
            ->assertSee('aria-label="Reservierungssystem"', false);
    }
}
