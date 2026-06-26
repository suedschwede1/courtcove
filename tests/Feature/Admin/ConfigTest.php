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
    private function admin(): User { return User::factory()->create(['status' => 'admin']); }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/admin/config')->assertForbidden();
    }

    #[Test]
    public function edit_shows_current_values(): void
    {
        Option::create(['key' => 'client.name.full', 'value' => 'Example Club', 'locale' => null]);
        $this->actingAs($this->admin())->get('/admin/config')->assertOk()->assertSee('Example Club');
    }

    #[Test]
    public function update_writes_default_locale_rows(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'client_name_full' => 'Neuer Name',
            'contact_email'    => 'info@example.com',
            'calendar_days'    => '5',
            'registration'     => '1',
            'maintenance'      => '0',
        ])->assertRedirect();

        $this->assertSame('Neuer Name', Option::getValue('client.name.full'));
        $this->assertSame('5', Option::getValue('service.calendar.days'));
    }
}
