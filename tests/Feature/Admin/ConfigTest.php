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
