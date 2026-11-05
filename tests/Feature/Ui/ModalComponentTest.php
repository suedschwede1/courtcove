<?php

namespace Tests\Feature\Ui;

use DOMDocument;
use DOMXPath;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModalComponentTest extends TestCase
{
    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        // libxml drops attribute names starting with "@" (Alpine shorthand); rename them so they stay readable as "at-*".
        $doc->loadHTML('<?xml encoding="utf-8"?>'.preg_replace('/\s@([\w.:-]+)=/', ' at-$1=', $html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function hasClass(string $class): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    #[Test]
    public function modal_is_a_labelled_alpine_dialog(): void
    {
        $html = (string) $this->blade('<x-ui.modal id="demo" show="open" eyebrow="Neue Buchung"><x-slot:title>Court A buchen</x-slot:title>
Body
<x-slot:footer>F</x-slot:footer></x-ui.modal>');
        $xp = $this->xpath($html);

        $root = $xp->query('//div[@id="demo"]['.$this->hasClass('ds-modal').']');
        $this->assertSame(1, $root->length);
        $el = $root->item(0);
        $this->assertSame('dialog', $el->getAttribute('role'));
        $this->assertSame('true', $el->getAttribute('aria-modal'));
        $this->assertSame('demo-title', $el->getAttribute('aria-labelledby'));
        $this->assertSame('dsModal', $el->getAttribute('x-data'));
        $this->assertSame('open', $el->getAttribute('x-show'));
        $this->assertTrue($el->hasAttribute('x-cloak'));
        $this->assertSame('sync(open)', $el->getAttribute('x-effect'));
        $this->assertSame('open = false', $el->getAttribute('at-keydown.escape.stop'));
        $this->assertSame('trap($event)', $el->getAttribute('at-keydown.tab'));
        $this->assertTrue($el->hasAttribute('x-transition.opacity'));
        $this->assertSame("(open) ? '' : null", $el->getAttribute('x-bind:data-open'));

        $this->assertSame('Court A buchen', trim($xp->query('//h2[@id="demo-title"]')->item(0)->textContent));
        $this->assertSame('Neue Buchung', trim($xp->query('//p['.$this->hasClass('ds-modal__eyebrow').']')->item(0)->textContent));
        $this->assertStringContainsString('Body', $xp->query('//div['.$this->hasClass('ds-modal__body').']')->item(0)->textContent);
        $this->assertStringContainsString('F', $xp->query('//div['.$this->hasClass('ds-modal__footer').']')->item(0)->textContent);
        $this->assertSame('open = false', $xp->query('//div['.$this->hasClass('ds-modal__backdrop').']')->item(0)->getAttribute('at-click'));
        $this->assertSame('true', $xp->query('//span['.$this->hasClass('ds-modal__handle').']')->item(0)->getAttribute('aria-hidden'));

        $close = $xp->query('//button['.$this->hasClass('ds-modal__close').']');
        $this->assertSame(1, $close->length);
        $this->assertSame(__('booking.modal.close'), $close->item(0)->getAttribute('aria-label'));
        $this->assertSame('open = false', $close->item(0)->getAttribute('x-on:click'));
    }

    #[Test]
    public function custom_close_statement_is_used_everywhere(): void
    {
        $html = (string) $this->blade('<x-ui.modal id="c" show="open" close="close()" label="X">B</x-ui.modal>');
        $xp = $this->xpath($html);

        $this->assertSame('close()', $xp->query('//div[@id="c"]')->item(0)->getAttribute('at-keydown.escape.stop'));
        $this->assertSame('close()', $xp->query('//div['.$this->hasClass('ds-modal__backdrop').']')->item(0)->getAttribute('at-click'));
        $this->assertSame('close()', $xp->query('//button['.$this->hasClass('ds-modal__close').']')->item(0)->getAttribute('x-on:click'));
    }

    #[Test]
    public function label_replaces_title_when_no_title_slot(): void
    {
        $html = (string) $this->blade('<x-ui.modal id="f" show="open" label="Admin-Formular" size="frame">X</x-ui.modal>');
        $xp = $this->xpath($html);

        $root = $xp->query('//div[@id="f"]')->item(0);
        $this->assertSame('Admin-Formular', $root->getAttribute('aria-label'));
        $this->assertFalse($root->hasAttribute('aria-labelledby'));
        $this->assertSame(0, $xp->query('//h2')->length);
        // The framed page brings its own title bar and close button: no shell header, handle or close.
        $this->assertSame(0, $xp->query('//header')->length);
        $this->assertSame(0, $xp->query('//*['.$this->hasClass('ds-modal__close').']')->length);
        $this->assertSame(0, $xp->query('//*['.$this->hasClass('ds-modal__handle').']')->length);
        $this->assertSame('ds-modal__panel ds-modal__panel--frame', $xp->query('//div['.$this->hasClass('ds-modal__panel').']')->item(0)->getAttribute('class'));
        $this->assertSame(0, $xp->query('//div['.$this->hasClass('ds-modal__footer').']')->length);
    }

    #[Test]
    public function unknown_size_throws(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unknown modal size [xl].');

        $this->blade('<x-ui.modal id="a" show="open" size="xl">X</x-ui.modal>');
    }

    #[Test]
    public function scroll_lock_is_derived_from_open_modals(): void
    {
        $js = (string) file_get_contents(resource_path('js/modal.js'));
        $this->assertStringContainsString('export function syncScrollLock', $js);
        $this->assertStringContainsString('.ds-modal[data-open]', $js);
        $this->assertStringContainsString('ds-scroll-lock', $js);
        $this->assertStringContainsString('data-modal-initial', $js);

        $app = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertMatchesRegularExpression("/Alpine\\.data\\('dsModal',\\s*dsModal\\)[\\s\\S]*Alpine\\.start\\(\\)/", $app);
    }
}
