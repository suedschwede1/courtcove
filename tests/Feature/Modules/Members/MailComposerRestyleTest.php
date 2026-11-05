<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\MailTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailComposerRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function render(): string
    {
        return (string) $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->get('/members/mail')
            ->assertOk()
            ->getContent();
    }

    #[Test]
    public function confirm_dialog_is_a_labelled_modal(): void
    {
        $html = $this->render();

        // The app header's phone menu is a dialog too; the confirm dialog is the one labelled by its title.
        $this->assertSame(1, preg_match('/<[a-z]+\b[^>]*\brole="dialog"[^>]*\baria-labelledby="mail-confirm-title"[^>]*>/', $html, $match));
        $this->assertStringContainsString('aria-modal="true"', $match[0]);
        $this->assertMatchesRegularExpression('/<h2\b[^>]*\bid="mail-confirm-title"/', $html);
    }

    #[Test]
    public function confirm_dialog_traps_focus_and_returns_it_to_the_send_button(): void
    {
        $html = $this->render();

        $this->assertTag($html, 'button', ['x-ref' => 'sendButton', '@click' => 'confirmOpen = true']);
        $this->assertStringContainsString('@keydown.tab="', $html);
        $this->assertStringContainsString('@keydown.escape.window="if (confirmOpen) { confirmOpen = false; $refs.sendButton.focus() }"', $html);
        $this->assertStringContainsString('@click.self="confirmOpen = false; $refs.sendButton.focus()"', $html);
        $this->assertTag($html, 'button', ['x-ref' => 'confirmCancel', '@click' => 'confirmOpen = false; $refs.sendButton.focus()']);
    }

    #[Test]
    public function selected_template_uses_its_own_class(): void
    {
        MailTemplate::create(['name' => 'Vorlage A', 'subject' => 'Betreff A', 'body' => 'Text A']);

        $html = $this->render();

        $this->assertStringContainsString("'mbr-template--selected': selectedTemplateId === ", $html);
        $this->assertStringNotContainsString("'mbr-selected'", $html);
    }

    #[Test]
    public function subject_and_body_errors_are_linked_to_their_fields(): void
    {
        // A send without subject and body redirects back with both validation errors.
        $html = (string) $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->followingRedirects()
            ->from('/members/mail')
            ->post(route('members.mail.send'), ['audience' => 'all', 'subject' => '', 'body' => ''])
            ->assertOk()
            ->getContent();

        $this->assertTag($html, 'input', ['id' => 'mail-subject', 'aria-invalid' => 'true', 'aria-describedby' => 'mail-subject-error']);
        $this->assertTag($html, 'textarea', ['id' => 'mail-body', 'aria-invalid' => 'true', 'aria-describedby' => 'mail-body-help mail-body-error']);
        $this->assertMatchesRegularExpression('/id="mail-subject-error"[^>]*>[^<]+</', $html);
        $this->assertMatchesRegularExpression('/id="mail-body-error"[^>]*>[^<]+</', $html);
    }

    #[Test]
    public function audiences_are_pressed_chips(): void
    {
        $html = $this->render();

        preg_match_all('/<button\b[^>]*\bclass="ds-chip"[^>]*>/', $html, $matches);
        // Fresh database: all, board, dormant (no mail categories).
        $this->assertCount(3, $matches[0]);
        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString('x-bind:aria-pressed=', $tag);
            $this->assertStringContainsString('@click="audience = ', $tag);
        }
        $this->assertMatchesRegularExpression('/role="group"\s+aria-label="'.preg_quote(e(__('members::admin.mail.audience')), '/').'"/', $html);
    }

    #[Test]
    public function bcc_label_is_translated(): void
    {
        $source = (string) file_get_contents(app_path('Modules/Members/resources/views/mail/index.blade.php'));

        $this->assertStringNotContainsString('>BCC<', $source);
        $this->assertStringContainsString('members::admin.mail.bcc', $source);
    }

    #[Test]
    public function no_inline_styles_or_hex(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString(':style=', $html);
        $this->assertStringNotContainsString('#0066cc', $html);
    }

    /**
     * Asserts that at least one `<$tag …>` opening tag carries every given
     * `name="value"` attribute, in any order.
     *
     * @param  array<string, string>  $attrs
     */
    private function assertTag(string $html, string $tag, array $attrs): void
    {
        preg_match_all('/<'.preg_quote($tag, '/').'\b[^>]*>/', $html, $matches);

        foreach ($matches[0] as $candidate) {
            $hasAll = true;
            foreach ($attrs as $name => $value) {
                if (preg_match('/\s'.preg_quote($name, '/').'="'.preg_quote(e($value), '/').'"/', $candidate) !== 1) {
                    $hasAll = false;
                    break;
                }
            }
            if ($hasAll) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(sprintf('No <%s> tag with %s found.', $tag, json_encode($attrs, JSON_UNESCAPED_SLASHES)));
    }
}
