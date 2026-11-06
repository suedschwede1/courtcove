<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\User;
use App\Modules\TournamentBase\Support\MailSender;
use App\Support\Mail\SystemMailSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Reply-To of the booking and account mails is edited on the admin "Texte" page.
 */
class SystemMailOptionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FULL = [
        'system_mail_booking_reply_to' => 'office@example.test',
        'system_mail_account_reply_to' => 'support@example.test',
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function sender(): SystemMailSender
    {
        return $this->app->make(SystemMailSender::class);
    }

    #[Test]
    public function the_texts_page_shows_the_section_with_the_stored_values(): void
    {
        Option::query()->create(['key' => SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS, 'value' => 'support@example.test', 'locale' => null]);

        $this->actingAs($this->admin())->get('/admin/config')
            ->assertOk()
            ->assertSee('Antwortadresse für System-/Buchungsmails')
            ->assertSee('Antwortadresse für Buchungsmails')
            ->assertSee('Antwortadresse für Passwort-/Kontomails')
            ->assertSee('name="system_mail_booking_reply_to"', false)
            ->assertSee('value="support@example.test"', false);
    }

    #[Test]
    public function the_section_is_labelled_in_english_too(): void
    {
        $this->actingAs($this->admin())->withUnencryptedCookie('locale', 'en')->get('/admin/config')
            ->assertOk()
            ->assertSee('Reply-To Address for System and Booking Emails')
            ->assertSee('Reply-To for Booking Emails')
            ->assertSee('Reply-To for Password and Account Emails')
            ->assertDontSee('Antwortadresse für System-/Buchungsmails');
    }

    #[Test]
    public function both_fields_are_saved_and_used_by_the_mail_sender(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('office@example.test', $this->sender()->replyTo(SystemMailSender::SCOPE_BOOKING)[0]->address);
        $this->assertSame('support@example.test', $this->sender()->replyTo(SystemMailSender::SCOPE_ACCOUNT)[0]->address);
        $this->assertSame([], $this->app->make(MailSender::class)->replyTo());
    }

    #[Test]
    public function an_emptied_field_removes_the_option_so_the_env_value_or_none_applies(): void
    {
        config(['mail.system.booking.reply_to.address' => 'env-office@example.test']);
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect();

        $this->actingAs($this->admin())->put('/admin/config', [
            'system_mail_booking_reply_to' => '',
            'system_mail_account_reply_to' => '  ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, Option::query()->where('key', 'like', 'service.mail.system.%')->count());
        $this->assertSame('env-office@example.test', $this->sender()->replyTo(SystemMailSender::SCOPE_BOOKING)[0]->address);
        $this->assertSame([], $this->sender()->replyTo(SystemMailSender::SCOPE_ACCOUNT));
    }

    #[Test]
    public function saving_other_fields_leaves_the_reply_to_options_alone(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect();
        $this->actingAs($this->admin())->put('/admin/config', ['system_name' => 'Neues System'])->assertRedirect();

        $this->assertSame('office@example.test', Option::getValue(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS));
        $this->assertSame('support@example.test', Option::getValue(SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS));
    }

    #[Test]
    public function the_tournament_reply_to_is_saved_independently(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', self::FULL + ['tournament_mail_reply_to_address' => 'sport@example.test'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->put('/admin/config', ['system_mail_booking_reply_to' => ''])->assertRedirect();

        $this->assertSame('sport@example.test', $this->app->make(MailSender::class)->replyTo()[0]->address);
        $this->assertSame([], $this->sender()->replyTo(SystemMailSender::SCOPE_BOOKING));
        $this->assertSame('support@example.test', $this->sender()->replyTo(SystemMailSender::SCOPE_ACCOUNT)[0]->address);
    }

    #[Test]
    public function invalid_values_are_rejected_without_saving(): void
    {
        $invalid = [
            'keine-adresse',
            'a@b@example.test',
            "office@example.test\r\nBcc: x@example.test",
            'one@example.test, two@example.test',
            str_repeat('a', 250).'@example.test',
            ['office@example.test'],
            ['x' => ['y']],
        ];
        $admin = $this->admin();

        foreach (array_keys(self::FULL) as $field) {
            foreach ($invalid as $value) {
                $this->actingAs($admin)->put('/admin/config', [$field => $value])->assertSessionHasErrors($field);
            }
        }

        $this->assertSame(0, Option::query()->where('key', 'like', 'service.mail.system.%')->count());
    }

    #[Test]
    public function the_error_message_names_the_field_by_its_label(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/config', ['system_mail_account_reply_to' => 'keine-adresse'])
            ->assertSessionHasErrors(['system_mail_account_reply_to' => 'Das Feld Antwortadresse für Passwort-/Kontomails muss eine gültige E-Mail-Adresse sein.']);

        $this->actingAs($admin)->withUnencryptedCookie('locale', 'en')->put('/admin/config', ['tournament_mail_reply_to_address' => 'no-address'])
            ->assertSessionHasErrors(['tournament_mail_reply_to_address' => 'The Reply-To Address field must be a valid email address.']);
    }

    #[Test]
    public function an_address_at_the_length_limit_is_accepted(): void
    {
        $address = str_repeat('a', 64).'@'.str_repeat('b', 46).'.'.str_repeat('c', 45).'.'.str_repeat('d', 45).'.'.str_repeat('e', 45).'.test';
        $this->assertSame(254, strlen($address));

        $this->actingAs($this->admin())->put('/admin/config', ['system_mail_booking_reply_to' => $address])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($address, Option::getValue(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS));
    }
}
