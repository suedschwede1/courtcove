<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Mail\MemberBulkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rundmail Markdown (league/commonmark via Str::markdown with html_input=strip, allow_unsafe_links=false):
 * XSS attempts in the body never reach the preview or the sent mail as active HTML.
 */
class MailMarkdownSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function attacks(): array
    {
        return [
            'script tag' => ['Hallo <script>alert(1)</script> Welt'],
            'img onerror' => ['<img src=x onerror="alert(1)">'],
            'raw html block' => ["<div onclick=\"alert(1)\">\nKlick\n</div>"],
            'javascript link' => ['[Klick](javascript:alert(1))'],
            'javascript link encoded' => ['[Klick](jav&#x09;ascript:alert(1))'],
            'data uri link' => ['[Klick](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)'],
            'image javascript src' => ['![x](javascript:alert(1))'],
            'autolink javascript' => ['<javascript:alert(1)>'],
            'inline attribute syntax' => ['Text{onclick="alert(1)"}'],
            'svg onload' => ['<svg onload=alert(1)>'],
            'iframe' => ['<iframe src="https://example.test"></iframe>'],
        ];
    }

    private function assertInert(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/<(script|iframe|svg|object|embed|style|div)\b/i', $html);
        // Markdown images stay, but only with an empty or http(s) source.
        $this->assertDoesNotMatchRegularExpression('/<img\b(?![^>]*src="(|https?:[^"]*)")/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html, 'no event handler attribute');
        $this->assertDoesNotMatchRegularExpression('/href\s*=\s*"\s*(javascript|data):/i', $html);
        $this->assertDoesNotMatchRegularExpression('/src\s*=\s*"\s*javascript:/i', $html);
    }

    #[Test]
    #[DataProvider('attacks')]
    public function the_preview_neutralises_html_and_unsafe_links(string $body): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $html = (string) $this->actingAs($admin)->postJson('/members/mail/preview', ['body' => $body])->assertOk()->json('html');

        $this->assertInert($html);
    }

    #[Test]
    #[DataProvider('attacks')]
    public function the_sent_mail_neutralises_html_and_unsafe_links(string $body): void
    {
        $rendered = (new MemberBulkMail('Betreff', $body))->render();
        // Only the rendered body: between the wrapper and the footer of the mail layout.
        $start = (int) strpos($rendered, '<div class="wrap">') + strlen('<div class="wrap">');
        $bodyPart = substr($rendered, $start, (int) strpos($rendered, '<div class="footer">') - $start);

        $this->assertInert($bodyPart);
    }

    #[Test]
    public function ordinary_markdown_still_renders(): void
    {
        $html = (new MemberBulkMail('Betreff', "**Fett** und [Link](https://example.test)\n\n- Punkt"))->render();

        $this->assertStringContainsString('<strong>Fett</strong>', $html);
        $this->assertStringContainsString('<a href="https://example.test">Link</a>', $html);
        $this->assertStringContainsString('<li>Punkt</li>', $html);
    }
}
