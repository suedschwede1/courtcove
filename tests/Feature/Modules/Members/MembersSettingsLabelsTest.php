<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The settings pages of the Members area use the labels of the Mitglieder-* mockups: "Geschäftsjahre",
 * "Mail-Konfiguration" and "Datenschutz" (English: "Fiscal years", "Mail configuration", "Data protection").
 * Only visible texts changed: the URLs /members/fiscal-years, /members/mail/config and /members/dsgvo stay.
 */
class MembersSettingsLabelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function pages(): array
    {
        return [
            'fiscal years de' => ['de', '/members/fiscal-years', ['<h1', 'Geschäftsjahre', 'Neues Geschäftsjahr']],
            'fiscal year create de' => ['de', '/members/fiscal-years/create', ['Neues Geschäftsjahr']],
            'fee categories de' => ['de', '/members/fee-categories', ['Geschäftsjahre', 'Beitragssätze pro Geschäftsjahr']],
            'dues de' => ['de', '/members/dues', ['Geschäftsjahre', 'Geschäftsjahr für neue Beiträge']],
            'fiscal years en' => ['en', '/members/fiscal-years', ['Fiscal years', 'New fiscal year']],
            'mail config de' => ['de', '/members/mail/config', ['Mail-Konfiguration']],
            'mail config en' => ['en', '/members/mail/config', ['Mail configuration']],
            'data protection de' => ['de', '/members/dsgvo', ['Datenschutz']],
            'data protection en' => ['en', '/members/dsgvo', ['Data protection']],
            'list de (sidebar)' => ['de', '/members', ['Geschäftsjahre', 'Mail-Konfiguration', 'Datenschutz']],
            'list en (sidebar)' => ['en', '/members', ['Fiscal years', 'Mail configuration', 'Data protection']],
        ];
    }

    /** @param  list<string>  $expected */
    #[Test]
    #[DataProvider('pages')]
    public function settings_pages_show_the_mockup_labels(string $locale, string $url, array $expected): void
    {
        FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        $html = (string) $this->actingAs($this->admin())->withUnencryptedCookie('locale', $locale)->get($url)->assertOk()->getContent();

        foreach ($expected as $label) {
            $this->assertStringContainsString($label, $html, $url);
        }
        foreach (['Fiskaljahr', 'DSGVO', 'Mail-Einstellungen', 'GDPR', 'Mail Settings'] as $old) {
            $this->assertStringNotContainsString($old, $html, $url.' still shows '.$old);
        }
    }

    #[Test]
    public function the_old_urls_stay(): void
    {
        $this->assertSame(url('/members/fiscal-years'), route('members.fiscal-years.index'));
        $this->assertSame(url('/members/mail/config'), route('members.mail.config'));
        $this->assertSame(url('/members/dsgvo'), route('members.dsgvo.index'));
    }

    #[Test]
    public function the_data_protection_flash_uses_the_new_label(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/dsgvo', ['retention_years' => 4])
            ->assertRedirect('/members/dsgvo')
            ->assertSessionHas('success', 'Datenschutz-Einstellungen gespeichert.');

        $this->actingAs($admin)->withUnencryptedCookie('locale', 'en')->post('/members/dsgvo', ['retention_years' => 5])
            ->assertSessionHas('success', 'Data protection settings saved.');
    }
}
