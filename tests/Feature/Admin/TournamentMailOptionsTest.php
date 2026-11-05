<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\User;
use App\Modules\TournamentBase\Support\MailSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sender of the tournament mails is edited on the admin "Texte" page.
 */
class TournamentMailOptionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FULL = [
        'tournament_mail_from_address' => 'turnier@example.test',
        'tournament_mail_from_name' => 'Turnierleitung',
        'tournament_mail_reply_to_address' => 'sport@example.test',
        'tournament_mail_reply_to_name' => 'Sportwart',
        'tournament_mail_signature_club' => 'Sportverein Muster',
        'tournament_mail_signature_url' => 'https://verein.example.test',
        'tournament_mail_signature_text' => "Sportliche Grüße\n:club",
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_texts_page_shows_the_section_with_the_stored_values(): void
    {
        Option::query()->create(['key' => MailSender::OPTION_REPLY_TO_ADDRESS, 'value' => 'sport@example.test', 'locale' => null]);

        $this->actingAs($this->admin())->get('/admin/config')
            ->assertOk()
            ->assertSee('Absender der Turnier-Mails')
            ->assertSee('name="tournament_mail_reply_to_address"', false)
            ->assertSee('value="sport@example.test"', false);
    }

    #[Test]
    public function the_section_is_labelled_in_english_too(): void
    {
        $this->actingAs($this->admin())->withUnencryptedCookie('locale', 'en')->get('/admin/config')
            ->assertOk()
            ->assertSee('Tournament Email Sender')
            ->assertSee('Reply-To Address')
            ->assertDontSee('Absender der Turnier-Mails');
    }

    #[Test]
    public function all_fields_are_saved_and_used_by_the_mail_sender(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('turnier@example.test', Option::getValue(MailSender::OPTION_FROM_ADDRESS));
        $this->assertSame("Sportliche Grüße\n:club", Option::getValue(MailSender::OPTION_SIGNATURE_TEXT));

        $sender = $this->app->make(MailSender::class);
        $this->assertSame(['turnier@example.test', 'Turnierleitung'], [$sender->from()?->address, $sender->from()?->name]);
        $this->assertSame('sport@example.test', $sender->replyTo()[0]->address);
        $this->assertSame("\nSportliche Grüße\nSportverein Muster\n", $sender->signature('de'));
    }

    #[Test]
    public function an_emptied_field_removes_the_option_so_the_fallback_applies_again(): void
    {
        config(['mail.tournament.reply_to.address' => 'env@example.test']);
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect();

        $this->actingAs($this->admin())->put('/admin/config', ['tournament_mail_reply_to_address' => ''] + self::FULL)->assertRedirect();

        $this->assertSame(0, Option::query()->where('key', MailSender::OPTION_REPLY_TO_ADDRESS)->count());
        $this->assertSame('env@example.test', $this->app->make(MailSender::class)->replyTo()[0]->address);
        $this->assertSame(1, Option::query()->where('key', MailSender::OPTION_FROM_ADDRESS)->count());
    }

    #[Test]
    public function saving_other_fields_leaves_the_mail_options_alone(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', self::FULL)->assertRedirect();
        $this->actingAs($this->admin())->put('/admin/config', ['system_name' => 'Neues System'])->assertRedirect();

        $this->assertSame('sport@example.test', Option::getValue(MailSender::OPTION_REPLY_TO_ADDRESS));
    }

    #[Test]
    public function invalid_values_are_rejected_without_saving(): void
    {
        $cases = [
            'tournament_mail_from_address' => ['keine-adresse', str_repeat('a', 250).'@example.test', ['x']],
            'tournament_mail_reply_to_address' => ['a@b@example.test', ['x']],
            'tournament_mail_from_name' => ["Name\r\nBcc: x@example.test", str_repeat('n', 256), ['x']],
            'tournament_mail_reply_to_name' => ["Zeile\nZwei"],
            'tournament_mail_signature_club' => ["Verein\nZwei", str_repeat('v', 256)],
            'tournament_mail_signature_url' => ['keine url', 'https://example.test/'.str_repeat('p', 250), ['x']],
            'tournament_mail_signature_text' => [str_repeat('t', 1001), ['x']],
        ];
        $admin = $this->admin();

        foreach ($cases as $field => $values) {
            foreach ($values as $value) {
                $this->actingAs($admin)->put('/admin/config', [$field => $value])->assertSessionHasErrors($field);
            }
        }

        $this->assertSame(0, Option::query()->where('key', 'like', 'service.mail.tournament.%')->count());
    }

    #[Test]
    public function values_at_the_limits_are_accepted(): void
    {
        $this->actingAs($this->admin())->put('/admin/config', [
            'tournament_mail_from_name' => str_repeat('n', 255),
            'tournament_mail_signature_text' => str_repeat('t', 1000),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1000, mb_strlen((string) Option::getValue(MailSender::OPTION_SIGNATURE_TEXT)));
    }
}
