<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\UserActivated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserActivatedMailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function activation_mail_is_queued_when_user_is_created_with_enabled_status(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post('/admin/users', [
            'alias'    => 'Neues Mitglied',
            'email'    => 'neu@example.com',
            'status'   => 'enabled',
            'password' => 'secret123',
        ])->assertRedirect();

        Mail::assertQueued(UserActivated::class, fn ($m) => $m->hasTo('neu@example.com'));
    }

    #[Test]
    public function no_activation_mail_when_user_is_created_with_disabled_status(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post('/admin/users', [
            'alias'    => 'Gesperrtes Mitglied',
            'email'    => 'gesperrt@example.com',
            'status'   => 'disabled',
            'password' => 'secret123',
        ])->assertRedirect();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function activation_mail_is_queued_when_status_changes_to_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create(['status' => 'disabled', 'email' => 'member@example.com']);

        $this->actingAs($this->admin())->put("/admin/users/{$user->uid}", [
            'alias'  => $user->alias,
            'email'  => $user->email,
            'status' => 'enabled',
        ])->assertRedirect();

        Mail::assertQueued(UserActivated::class, fn ($m) => $m->hasTo('member@example.com'));
    }

    #[Test]
    public function no_activation_mail_when_already_enabled_user_is_updated(): void
    {
        Mail::fake();

        $user = User::factory()->create(['status' => 'enabled', 'email' => 'member@example.com']);

        $this->actingAs($this->admin())->put("/admin/users/{$user->uid}", [
            'alias'  => 'Neuer Alias',
            'email'  => $user->email,
            'status' => 'enabled',
        ])->assertRedirect();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function no_activation_mail_when_user_has_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['status' => 'disabled', 'email' => null]);

        $this->actingAs($this->admin())->put("/admin/users/{$user->uid}", [
            'alias'  => $user->alias,
            'email'  => null,
            'status' => 'enabled',
        ])->assertRedirect();

        Mail::assertNothingQueued();
    }
}
