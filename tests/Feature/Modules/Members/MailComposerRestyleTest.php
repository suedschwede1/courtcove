<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
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

        $this->assertSame(1, preg_match('/<[a-z]+\b[^>]*\brole="dialog"[^>]*>/', $html, $match));
        $this->assertStringContainsString('aria-modal="true"', $match[0]);
        $this->assertStringContainsString('aria-labelledby="mail-confirm-title"', $match[0]);
        $this->assertMatchesRegularExpression('/<h2\b[^>]*\bid="mail-confirm-title"/', $html);
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
}
