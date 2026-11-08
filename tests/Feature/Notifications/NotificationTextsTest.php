<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class NotificationTextsTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

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
        // Fictional stand-in list; the real denylist lives in the commit hook, not in tracked tests.
        $forbidden = '/acmemessaging|examplecloudsms|fictionalclub/i';

        foreach (['de', 'en'] as $locale) {
            $text = trans('booking.notifications.privacy_notice', [], $locale);
            $this->assertNotSame('booking.notifications.privacy_notice', $text);
            $this->assertStringContainsStringIgnoringCase('WhatsApp', $text);
            $this->assertSame(0, preg_match($forbidden, $text), "$locale privacy sentence names a provider or club");
        }
        $this->assertStringContainsString('Handynummer aus deinem Konto', trans('booking.notifications.privacy_notice', [], 'de'));
    }

    #[Test]
    public function text_version_key_matches_stored_version(): void
    {
        $user = $this->eligibleUser('+43 660 0000042');
        $consent = $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');

        foreach (['de', 'en'] as $locale) {
            $version = trans('booking.notifications.consent_text_version', [], $locale);
            $this->assertNotSame('booking.notifications.consent_text_version', $version);
            $this->assertSame($version, $consent->fresh()->consent_text_version);
        }
        $this->assertMatchesRegularExpression('/^wa-\d{4}-\d{2}-v\d+$/', $consent->consent_text_version);
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
