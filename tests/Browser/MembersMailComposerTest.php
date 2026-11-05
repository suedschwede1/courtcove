<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Modules\Members\Models\MailSend;
use App\Modules\Members\Models\MailTemplate;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class MembersMailComposerTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function loading_a_template_fills_the_editor(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $this->createTemplate();

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)->visit('/members/mail');
                $this->clickAlpine($browser, 'loadTemplate(');
                $browser->waitUsing(5, 100, fn () => $browser->inputValue('#mail-subject') === 'Betreff A')
                    ->assertInputValue('#mail-subject', 'Betreff A');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function preview_renders_markdown(): void
    {
        $admin = $this->createAdminUser('dusk_members');

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)
                    ->visit('/members/mail')
                    ->type('#mail-body', '**fett**');
                $this->clickAlpine($browser, 'showPreview = ! showPreview');
                $browser->waitForTextIn('[x-html="previewHtml"]', 'fett', 5)
                    ->assertPresent('[x-html="previewHtml"] strong');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function send_asks_for_confirmation(): void
    {
        $admin = $this->createAdminUser('dusk_members');

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)
                    ->visit('/members/mail')
                    ->type('#mail-subject', 'Betreff A')
                    ->type('#mail-body', 'Text A');
                $this->clickAlpine($browser, 'confirmOpen = true');
                $browser->waitFor('[x-show="confirmOpen"]', 5)
                    ->assertSeeIn('[x-show="confirmOpen"]', 'Betreff A');
                $this->clickAlpine($browser, 'confirmOpen = false');
                $browser->waitUntilMissing('[x-show="confirmOpen"]', 5);
            });

            $this->assertSame(0, MailSend::count());
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    private function createTemplate(): MailTemplate
    {
        return MailTemplate::create(['name' => 'Vorlage A', 'subject' => 'Betreff A', 'body' => 'Text A']);
    }

    /**
     * Clicks the first button whose Alpine `@click` handler starts with the
     * given expression; these handlers are the composer's stable hooks.
     */
    private function clickAlpine(Browser $browser, string $handlerPrefix): void
    {
        $browser->script(sprintf(
            'Array.from(document.querySelectorAll("button")).find((b) => (b.getAttribute("@click") || "").startsWith(%s)).click();',
            json_encode($handlerPrefix),
        ));
    }
}
