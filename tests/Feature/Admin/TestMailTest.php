<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestMailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_open_testmail_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/testmail')
            ->assertOk()
            ->assertViewIs('admin.testmail.index');
    }

    #[Test]
    public function regular_member_cannot_access_testmail(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/testmail')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_send_test_mail(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/testmail', ['email' => 'test@example.com'])
            ->assertRedirect('/admin/testmail')
            ->assertSessionHas('success');

        Mail::assertQueued(TestMail::class, fn ($m) => $m->hasTo('test@example.com'));
    }

    #[Test]
    public function testmail_requires_valid_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/admin/testmail', ['email' => 'kein-email'])
            ->assertSessionHasErrors(['email']);

        Mail::assertNothingQueued();
    }
}
