<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class FieldComponentsTest extends TestCase
{
    use NormalizesHtml;

    private function render(string $template): string
    {
        return $this->html((string) $this->blade($template));
    }

    #[Test]
    public function field_links_label_input_and_help(): void
    {
        $this->assertSame(
            '<div class="ds-field"><label class="ds-field__label" for="email">E-Mail</label><input type="email" name="email" id="email" value="" class="ds-input" aria-describedby="email-help"><p class="ds-field__help" id="email-help">Wir schreiben dir nur zur Buchung.</p></div>',
            $this->render('<x-ui.field label="E-Mail" for="email" help="Wir schreiben dir nur zur Buchung."><x-ui.input name="email" type="email" /></x-ui.field>'),
        );
    }

    #[Test]
    public function view_errors_mark_the_input_invalid(): void
    {
        $this->withViewErrors(['email' => 'Ungültig']);
        $html = $this->render('<x-ui.field label="E-Mail" for="email" help="Hilfe"><x-ui.input name="email" type="email" /></x-ui.field>');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-help email-error"', $html);
        $this->assertStringContainsString('class="ds-input ds-input--invalid"', $html);
        $this->assertStringContainsString('<p class="ds-field__help" id="email-help">Hilfe</p><p class="ds-field__error" id="email-error">Ungültig</p>', $html);
    }

    #[Test]
    public function explicit_error_prop_reaches_the_input(): void
    {
        $html = $this->render('<x-ui.field for="q" error="Pflichtfeld"><x-ui.input name="q" required /></x-ui.field>');

        $this->assertStringContainsString('aria-describedby="q-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString(' required', $html);
        $this->assertStringContainsString('<p class="ds-field__error" id="q-error">Pflichtfeld</p>', $html);
    }

    #[Test]
    public function standalone_input_has_no_aria_and_value_prop_wins(): void
    {
        $html = $this->render('<x-ui.input name="q" value="Court A" />');

        $this->assertSame('<input type="text" name="q" id="q" value="Court A" class="ds-input">', $html);
    }

    #[Test]
    public function score_variant(): void
    {
        $this->assertStringContainsString('class="ds-input ds-input--score" inputmode="numeric"', $this->render('<x-ui.input name="s1" variant="score" />'));
    }

    #[Test]
    public function select_with_options_and_placeholder(): void
    {
        $this->assertSame(
            '<select name="court" id="court" class="ds-select"><option value="1">Court A (1)</option><option value="2" selected>Court B (2)</option></select>',
            $this->render('<x-ui.select name="court" :options="[\'1\' => \'Court A (1)\', \'2\' => \'Court B (2)\']" selected="2" />'),
        );
        $this->assertStringContainsString(
            '<select name="court" id="court" class="ds-select"><option value="">Bitte wählen</option><option value="1">',
            $this->render('<x-ui.select name="court" :options="[\'1\' => \'A\']" placeholder="Bitte wählen" />'),
        );
    }

    #[Test]
    public function textarea(): void
    {
        $this->assertSame(
            '<textarea name="note" id="note" rows="3" class="ds-textarea"></textarea>',
            $this->render('<x-ui.textarea name="note" :rows="3" />'),
        );
    }

    #[Test]
    public function select_and_textarea_pick_up_field_context(): void
    {
        $this->withViewErrors(['note' => 'Zu kurz']);
        $html = $this->render('<x-ui.field for="note"><x-ui.textarea name="note" /></x-ui.field>');

        $this->assertStringContainsString('aria-describedby="note-error"', $html);
        $this->assertStringContainsString('ds-textarea ds-textarea--invalid', $html);
    }

    /** @return list<string> every id attribute value in the markup */
    private function ids(string $html): array
    {
        preg_match_all('/(?<![\w:-])id="([^"]*)"/', $html, $m);

        return $m[1];
    }

    #[Test]
    public function an_explicit_field_id_is_the_one_shared_control_id(): void
    {
        $html = $this->render('<x-ui.field name="email" id="contact-email" label="E-Mail" help="Hilfe" error="Ungültig"><x-ui.input type="email" /></x-ui.field>');

        $this->assertSame(['contact-email', 'contact-email-help', 'contact-email-error'], $this->ids($html));
        $this->assertStringContainsString('<div class="ds-field"><label class="ds-field__label" for="contact-email">', $html);
        $this->assertStringContainsString('name="email" id="contact-email"', $html);
        $this->assertStringContainsString('aria-describedby="contact-email-help contact-email-error"', $html);
    }

    #[Test]
    public function an_explicit_id_on_the_child_input_wins_for_label_help_and_error(): void
    {
        $html = $this->render('<x-ui.field for="email" label="E-Mail" help="Hilfe" error="Ungültig"><x-ui.input name="email" id="contact-email" /></x-ui.field>');

        $this->assertSame(['contact-email', 'contact-email-help', 'contact-email-error'], $this->ids($html));
        $this->assertStringContainsString('for="contact-email"', $html);
        $this->assertStringContainsString('aria-describedby="contact-email-help contact-email-error"', $html);
    }

    #[Test]
    public function the_field_name_reaches_the_child_input(): void
    {
        $this->withViewErrors(['email' => 'Ungültig']);
        $html = $this->render('<x-ui.field name="email" label="E-Mail"><x-ui.input /></x-ui.field>');

        $this->assertStringContainsString('<label class="ds-field__label" for="email">', $html);
        $this->assertStringContainsString('name="email" id="email"', $html);
        $this->assertStringContainsString('<p class="ds-field__error" id="email-error">Ungültig</p>', $html);
        $this->assertStringNotContainsString('<div class="ds-field" name=', $html);
    }

    #[Test]
    public function repeated_names_in_one_form_get_unique_associated_ids(): void
    {
        $html = $this->render('<form>'
            .'<x-ui.field for="email" label="Privat" help="A"><x-ui.input name="email" /></x-ui.field>'
            .'<x-ui.field for="email" label="Beruflich" error="B"><x-ui.input name="email" /></x-ui.field>'
            .'<x-ui.field for="note" label="Notiz"><x-ui.textarea name="note" /></x-ui.field>'
            .'<x-ui.field for="note" label="Notiz 2"><x-ui.select name="note" :options="[]" /></x-ui.field>'
            .'</form>');

        $this->assertSame(['email', 'email-help', 'email-2', 'email-2-error', 'note', 'note-2'], $this->ids($html));
        $this->assertStringContainsString('for="email">Privat', $html);
        $this->assertStringContainsString('for="email-2">Beruflich', $html);
        $this->assertStringContainsString('id="email-2" value="" class="ds-input ds-input--invalid" aria-describedby="email-2-error"', $html);
        $this->assertStringContainsString('for="note-2">Notiz 2', $html);
        $this->assertStringContainsString('<select name="note" id="note-2"', $html);
    }

    #[Test]
    public function an_auto_id_skips_ids_that_were_given_explicitly(): void
    {
        $html = $this->render('<x-ui.input name="q" id="q" /><x-ui.field for="q" label="Suche"><x-ui.input name="q" /></x-ui.field>');

        $this->assertSame(['q', 'q-2'], $this->ids($html));
        $this->assertStringContainsString('for="q-2"', $html);
    }

    #[Test]
    public function a_raw_control_in_the_slot_keeps_its_own_id_for_the_label(): void
    {
        $html = $this->render('<x-ui.field for="player" label="Spieler"><input type="hidden" id="token" name="token" value="x"><input type="text" id="admin-player" name="player" class="ds-input"></x-ui.field>');

        $this->assertStringContainsString('<label class="ds-field__label" for="admin-player">', $html);
    }

    #[Test]
    public function ids_restart_for_every_rendered_page(): void
    {
        $first = $this->render('<x-ui.field for="email" label="E"><x-ui.input name="email" /></x-ui.field>');
        $second = $this->render('<x-ui.field for="email" label="E"><x-ui.input name="email" /></x-ui.field>');

        $this->assertSame($first, $second);
        $this->assertSame(['email'], $this->ids($second));
    }

    #[Test]
    public function option_group_with_tiles(): void
    {
        $this->assertSame(
            '<fieldset class="ds-options ds-options--cols-2"><legend class="ds-field__label">Spielart</legend><div class="ds-options__grid"><label class="ds-option"><input type="radio" name="q" value="2" class="ds-option__input" checked><span class="ds-option__label">Einzel</span></label><label class="ds-option"><input type="radio" name="q" value="4" class="ds-option__input"><span class="ds-option__label">Doppel</span></label></div></fieldset>',
            $this->render('<x-ui.option-group legend="Spielart"><x-ui.option-tile name="q" value="2" label="Einzel" checked /><x-ui.option-tile name="q" value="4" label="Doppel" /></x-ui.option-group>'),
        );
        $this->assertStringContainsString('ds-options--cols-3', $this->render('<x-ui.option-group :columns="3"></x-ui.option-group>'));
    }
}
