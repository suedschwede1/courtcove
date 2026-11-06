<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\ComparesDesignScreenshots;
use Tests\DuskTestCase;

/** Shared x-ui controls in the dev gallery (/_ui): tokens, phone targets, dialog focus. */
class DesignVisualRegressionTest extends DuskTestCase
{
    use ComparesDesignScreenshots;

    #[Test]
    public function shared_controls_match_the_desktop_reference(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->resize(1440, 1000)->visit('/_ui')->waitFor('[data-testid="ui-gallery"]');
            $this->assertSame('rgb(181, 68, 26)', $browser->script("return getComputedStyle(document.querySelector('.ds-btn--primary')).backgroundColor;")[0]);
            $this->assertSame('rgb(154, 43, 23)', $browser->script("return getComputedStyle(document.querySelector('.ds-field__error')).color;")[0]);
            $this->assertScreenshotMatchesBaseline($browser, 'design-controls-desktop');
        });
    }

    #[Test]
    public function shared_controls_have_phone_targets_and_no_horizontal_overflow(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->resize(390, 1000)->visit('/_ui')->waitFor('[data-testid="ui-gallery"]');
            $this->assertTrue((bool) $browser->script("return document.documentElement.scrollWidth <= innerWidth && [...document.querySelectorAll('[data-testid=ui-gallery] .ds-btn, [data-testid=ui-gallery] .ds-input')].filter(e => e.getClientRects().length).every(e => e.getBoundingClientRect().height >= 52);")[0]);
            $this->assertScreenshotMatchesBaseline($browser, 'design-controls-mobile');
        });
    }

    #[Test]
    public function dialog_opens_and_escape_returns_keyboard_focus(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->resize(390, 844)->visit('/_ui')->waitFor('[data-testid="gallery-dialog-open"]')
                ->click('[data-testid="gallery-dialog-open"]')
                ->waitFor('[data-testid="gallery-dialog"] .ds-modal__panel');
            $this->assertScreenshotMatchesBaseline($browser, 'design-dialog-mobile');
            $browser->keys('[data-testid="gallery-dialog"] .ds-modal__close', '{escape}')
                ->waitUntil("document.activeElement.matches('[data-testid=gallery-dialog-open]')");
            $this->assertFalse((bool) $browser->script("return document.querySelector('[data-testid=gallery-dialog]').hasAttribute('data-open');")[0]);
        });
    }
}
