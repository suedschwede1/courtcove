<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationTextsTest extends TestCase
{
    #[Test]
    public function consent_text_has_all_required_notices_in_de_and_en(): void
    {
        $required = [
            'de' => ['Konto', 'eigene', 'STOP', 'jederzeit', 'Name'],
            'en' => ['account', 'own', 'STOP', 'any time', 'name'],
        ];

        foreach ($required as $locale => $words) {
            $text = trans('booking.notifications.consent_text_v1', [], $locale);
            $this->assertNotSame('booking.notifications.consent_text_v1', $text, "missing consent text ($locale)");

            foreach ($words as $word) {
                $this->assertStringContainsStringIgnoringCase($word, $text, "$locale consent text lacks '$word'");
            }
        }
    }

    #[Test]
    public function privacy_sentence_exists_in_both_languages_without_provider_names(): void
    {
        foreach (['de', 'en'] as $locale) {
            $text = trans('booking.notifications.privacy_notice', [], $locale);
            $this->assertNotSame('booking.notifications.privacy_notice', $text);
            $this->assertStringContainsStringIgnoringCase('WhatsApp', $text);
        }
        $this->assertStringContainsString('Handynummer aus deinem Konto', trans('booking.notifications.privacy_notice', [], 'de'));
    }

    #[Test]
    public function text_version_key_matches_stored_version(): void
    {
        $this->assertSame('wa-2026-11-v1', trans('booking.notifications.consent_text_version', [], 'de'));
        $this->assertSame('wa-2026-11-v1', trans('booking.notifications.consent_text_version', [], 'en'));
        $this->assertStringEndsWith('_v1', 'consent_text_v1');
        $this->assertStringEndsWith('v1', (string) trans('booking.notifications.consent_text_version'));
    }

    #[Test]
    public function notification_lang_files_have_the_same_keys_in_de_and_en(): void
    {
        $de = array_keys(Arr::dot(require lang_path('de/booking/notifications.php')));
        $en = array_keys(Arr::dot(require lang_path('en/booking/notifications.php')));
        sort($de);
        sort($en);

        $this->assertSame($de, $en);
    }

    #[Test]
    public function no_provider_or_club_names_in_notification_lang_files(): void
    {
        // Fictional stand-in list; the real denylist lives in the commit hook, not in tracked tests.
        $forbidden = '/acmemessaging|examplecloudsms|fictionalclub/i';

        foreach (['de', 'en'] as $locale) {
            $content = (string) file_get_contents(lang_path("$locale/booking/notifications.php"));
            $this->assertSame(0, preg_match($forbidden, $content), "$locale notifications lang file names a provider or club");
        }

        $this->assertSame(1, preg_match($forbidden, 'Sent via AcmeMessaging'), 'the pattern itself must work');
    }
}
