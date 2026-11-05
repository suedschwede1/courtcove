<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\MailTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the mail composer's forms, Alpine bindings and template actions so the
 * restyle cannot silently break sending, test mails, templates or the preview.
 */
class MailComposerContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function composer_keeps_its_forms_and_bindings(): void
    {
        $templateId = MailTemplate::create(['name' => 'Vorlage A', 'subject' => 'Betreff A', 'body' => 'Text A'])->id;

        $html = (string) $this->actingAs($this->admin())->get('/members/mail')->assertOk()->getContent();

        $this->assertTag($html, 'form', ['id' => 'mail-send-form', 'method' => 'POST', 'action' => route('members.mail.send')]);
        foreach (['audience', 'subject', 'body'] as $f) {
            $this->assertTag($html, 'input', ['type' => 'hidden', 'name' => $f, 'x-model' => $f]);
        }
        $this->assertTag($html, 'input', ['id' => 'mail-subject', 'x-model' => 'subject', 'form' => 'mail-send-form']);
        $this->assertTag($html, 'textarea', ['id' => 'mail-body', 'x-model' => 'body', 'form' => 'mail-send-form',
            '@input.debounce.600ms' => 'if (showPreview) refreshPreview()']);
        $this->assertTag($html, 'form', ['action' => route('members.mail.test')]);
        $this->assertTag($html, 'input', ['name' => 'test_email', 'x-model' => 'testEmail']);
        $this->assertTag($html, 'form', ['action' => route('members.mail.templates.store')]);
        $this->assertTag($html, 'form', ['x-bind:action' => 'selectedTemplateUpdateUrl']);
        $this->assertTag($html, 'button', ['type' => 'submit', 'form' => 'mail-send-form']);   // confirm dialog
        $this->assertStringContainsString('x-show="confirmOpen"', $html);
        $this->assertStringContainsString('confirmOpen = true', $html);
        $this->assertStringContainsString('x-html="previewHtml"', $html);
        $this->assertStringContainsString(str_replace('/', '\/', route('members.mail.preview')), $html);
        $this->assertStringContainsString('loadTemplate(', $html);
        $this->assertMatchesRegularExpression('/@click="audience = [^"]*all/', $html);
        $this->assertStringContainsString(route('members.mail.templates.destroy', $templateId), $html);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
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
                $pattern = '/\s'.preg_quote($name, '/').'="'.preg_quote(e($value), '/').'"/';
                if (preg_match($pattern, $candidate) !== 1) {
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
