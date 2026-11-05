<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackModalTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param array<string, string> $session */
    private function page(array $session): string
    {
        $user = User::factory()->create(['status' => 'enabled']);

        return $this->actingAs($user)->withSession($session)->get('/calendar')->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    #[Test]
    public function success_shows_title_message_and_quote(): void
    {
        $html = $this->page(['success' => 'Buchung storniert.', 'booking_quote' => 'Example quote']);
        $x = $this->xpath($html);

        $modal = $x->query('//div[@id="feedback-modal"][@role="dialog"]');
        $this->assertSame(1, $modal->length);
        $this->assertSame(__('booking.feedback.success'), trim($x->query('//div[@id="feedback-modal"]//h2')->item(0)->textContent));
        $this->assertStringContainsString('Buchung storniert.', $x->query('//div[@id="feedback-modal"]//*[contains(@class,"ds-notice--good")]')->item(0)->textContent);
        $this->assertStringContainsString('Example quote', $modal->item(0)->textContent);

        $buttons = $x->query('//div[@id="feedback-modal"]//div[contains(@class,"ds-modal__footer")]//button');
        $this->assertSame(1, $buttons->length);
        $this->assertSame(__('booking.feedback.close'), trim($buttons->item(0)->textContent));
        $this->assertTrue($buttons->item(0)->hasAttribute('data-modal-initial'));

        $modalHtml = $modal->item(0)->ownerDocument->saveHTML($modal->item(0));
        $this->assertStringNotContainsString('&#x2715;', $modalHtml);
        $this->assertStringNotContainsString('✕', $modalHtml);
    }

    #[Test]
    public function error_uses_alert_and_timer_pauses_on_interaction(): void
    {
        $html = $this->page(['error' => 'Nicht möglich.']);
        $x = $this->xpath($html);

        $this->assertSame(__('booking.feedback.notice'), trim($x->query('//div[@id="feedback-modal"]//h2')->item(0)->textContent));
        $alert = $x->query('//div[@id="feedback-modal"]//*[@role="alert"][contains(@class,"ds-notice--problem")]');
        $this->assertSame(1, $alert->length);
        $this->assertStringContainsString('Nicht möglich.', $alert->item(0)->textContent);

        $this->assertStringContainsString('setTimeout(() => open = false, 4000)', $html);
        $this->assertStringContainsString('@pointerenter="clearTimeout(timer)"', $html);
        $this->assertStringContainsString('@focusin="clearTimeout(timer)"', $html);
    }

    #[Test]
    public function no_flash_renders_no_feedback_modal(): void
    {
        $this->assertStringNotContainsString('id="feedback-modal"', $this->page([]));
    }

    #[Test]
    public function legacy_feedback_script_is_gone(): void
    {
        $this->assertStringNotContainsString('feedback-modal', file_get_contents(public_path('js/booking.js')));
    }
}
