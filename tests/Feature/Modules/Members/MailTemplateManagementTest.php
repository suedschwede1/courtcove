<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\MailTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailTemplateManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_create_a_template(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/templates', [
            'name' => 'Begrüßung',
            'subject' => 'Willkommen',
            'body' => 'Hallo und willkommen!',
        ])->assertRedirect(route('members.mail.index'));

        $this->assertDatabaseHas('mail_templates', ['name' => 'Begrüßung', 'subject' => 'Willkommen']);
    }

    #[Test]
    public function admin_can_update_a_template(): void
    {
        $template = MailTemplate::create(['name' => 'Alt', 'subject' => 'A', 'body' => 'B']);

        $this->actingAs($this->admin())->put("/members/mail/templates/{$template->id}", [
            'name' => 'Neu',
            'subject' => 'C',
            'body' => 'D',
        ])->assertRedirect(route('members.mail.index'));

        $this->assertSame('Neu', $template->fresh()->name);
    }

    #[Test]
    public function admin_can_delete_a_template(): void
    {
        $template = MailTemplate::create(['name' => 'Weg', 'subject' => 'A', 'body' => 'B']);

        $this->actingAs($this->admin())->delete("/members/mail/templates/{$template->id}")
            ->assertRedirect(route('members.mail.index'));

        $this->assertDatabaseMissing('mail_templates', ['id' => $template->id]);
    }

    #[Test]
    public function the_index_lists_templates(): void
    {
        MailTemplate::create(['name' => 'Vorlage A', 'subject' => 'X', 'body' => 'Y']);

        $this->actingAs($this->admin())->get('/members/mail')
            ->assertOk()->assertSee('Vorlage A');
    }

    #[Test]
    public function the_index_shows_the_editor_template_actions(): void
    {
        MailTemplate::create(['name' => 'Vorlage A', 'subject' => 'X', 'body' => 'Y']);

        $this->actingAs($this->admin())->get('/members/mail')
            ->assertOk()
            ->assertSee('Vorlage A')
            ->assertSee(__('members::admin.mail.save_template_changes'))
            ->assertSee(__('members::admin.mail.save_as_template'))
            ->assertSee(__('members::admin.mail.show_preview'));
    }
}
