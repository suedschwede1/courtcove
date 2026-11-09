<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class AuthShellTest extends TestCase
{
    use NormalizesHtml;

    private function shell(string $template): string
    {
        return $this->html((string) $this->blade($template));
    }

    #[Test]
    public function shell_renders_panel_and_form_column(): void
    {
        $html = $this->shell('<x-layout.auth-shell title="T" intro="I"><p>Body</p><x-slot:footer><p>Foot</p></x-slot:footer></x-layout.auth-shell>');

        foreach ([
            '<div class="ds-auth">', '<div class="ds-auth__panel">', '<div class="ds-auth__lines" aria-hidden="true">',
            'ds-auth__line ds-auth__line--outer', 'ds-auth__line--singles', 'ds-auth__line--net',
            'ds-auth__line--service', 'ds-auth__line--center',
            '<main id="content" class="ds-auth__main">', '<h1 class="ds-auth__title">T</h1>',
            '<p class="ds-auth__intro">I</p>', '<p>Body</p>', '<div class="ds-auth__footer"><p>Foot</p></div>',
            '<span>Your club.</span>', '<span>Your court.</span>', '<span>Your game.</span>', e(__('booking.auth.panel.text')),
            'class="ds-logo ds-logo--44"', 'href="'.route('calendar.index').'"', e(__('booking.auth.back_to_calendar')),
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    #[Test]
    public function shell_without_intro_and_footer_renders_neither(): void
    {
        $html = $this->shell('<x-layout.auth-shell title="T"><p>Body</p></x-layout.auth-shell>');

        $this->assertStringNotContainsString('ds-auth__intro', $html);
        $this->assertStringNotContainsString('ds-auth__footer', $html);
    }

    #[Test]
    public function shell_offers_the_other_locale_only(): void
    {
        app()->setLocale('de');
        $html = $this->shell('<x-layout.auth-shell title="T"></x-layout.auth-shell>');

        $this->assertStringContainsString('<span class="ds-locale__current" aria-current="true">DE</span>', $html);
        $this->assertStringContainsString('href="'.route('lang.switch', ['locale' => 'en']).'"', $html);
        $this->assertStringNotContainsString('href="'.route('lang.switch', ['locale' => 'de']).'"', $html);
    }

    #[Test]
    public function shell_has_no_inline_style_and_one_h1(): void
    {
        $html = $this->shell('<x-layout.auth-shell title="T" intro="I"><p>Body</p></x-layout.auth-shell>');

        $this->assertStringNotContainsString('style=', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[Test]
    public function intro_is_hidden_on_phones_only_when_the_page_asks_for_it(): void
    {
        $this->assertStringContainsString('<p class="ds-auth__intro">I</p>', $this->shell('<x-layout.auth-shell title="T" intro="I"></x-layout.auth-shell>'));
        $this->assertStringContainsString(
            '<p class="ds-auth__intro ds-auth__intro--desktop">I</p>',
            $this->shell('<x-layout.auth-shell title="T" intro="I" :intro-desktop-only="true"></x-layout.auth-shell>'),
        );
    }
}
