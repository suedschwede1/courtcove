<?php

namespace Tests\Feature\Admin;

use App\Models\Option;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the restyled texts and behaviour settings: every field name kept, choices as option
 * tiles with the same names and values, one checked choice per group.
 */
final class ConfigViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private function load(string $url): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    #[Test]
    public function texts_form_keeps_every_field_in_section_cards(): void
    {
        $this->load(route('admin.config.edit'));

        $form = '//form[@method="POST"][@action="'.route('admin.config.update').'"]';
        $this->assertSame(1, $this->nodes($form.'//input[@name="_method"][@value="PUT"]')->length);
        $this->assertSame(5, $this->nodes($form.'//section[contains(@class,"ds-admin-form-card")]')->length);

        $names = ['client_name_full', 'client_name_short', 'contact_email', 'client_phone', 'client_website',
            'client_website_contact', 'client_website_imprint', 'client_website_privacy', 'logo_path',
            'system_name', 'service_name_short', 'service_description', 'subject_type', 'subject_square_type',
            'subject_square_plural', 'subject_unit', 'subject_unit_plural', 'registration_heading',
            'registration_welcome', 'registration_intro', 'registration_email_help', 'registration_privacy',
            'registration_success', 'tournament_mail_from_address', 'tournament_mail_from_name',
            'tournament_mail_reply_to_address', 'tournament_mail_reply_to_name', 'tournament_mail_signature_club',
            'tournament_mail_signature_url', 'tournament_mail_signature_text'];
        foreach ($names as $name) {
            $controls = $this->nodes($form.'//*[self::input or self::textarea][@name="'.$name.'"]');
            $this->assertSame(1, $controls->length, $name);
            $control = $controls->item(0);
            $this->assertInstanceOf(DOMElement::class, $control);
            $this->assertSame(1, $this->nodes('//label[@for="'.$control->getAttribute('id').'"]')->length, $name.' label');
        }
        $this->assertSame(1, $this->nodes($form.'//label[contains(@class,"ds-option")]/input[@type="checkbox"][@name="client_email_cc"][@value="1"]')->length);
        $this->assertSame(1, $this->nodes($form.'//div[contains(@class,"ds-admin-actions")]//button[@type="submit"]')->length);
    }

    #[Test]
    public function behaviour_choices_are_option_tiles_with_the_same_values(): void
    {
        Option::create(['key' => 'service.user.activation', 'value' => 'manual', 'locale' => null]);
        Option::create(['key' => 'service.user.registration', 'value' => '1', 'locale' => null]);
        $this->load(route('admin.config.behavior.edit'));

        $form = '//form[@method="POST"][@action="'.route('admin.config.behavior.update').'"]';
        $this->assertSame(1, $this->nodes($form.'//input[@name="_method"][@value="PUT"]')->length);

        $groups = [
            'registration' => [['0', '1'], '1'],
            'activation' => [['immediate', 'manual', 'manual-email', 'email'], 'manual'],
            'maintenance' => [['0', '1'], '0'],
            'quotes_enabled' => [['0', '1'], '1'],
        ];
        foreach ($groups as $name => [$values, $checked]) {
            $radios = $this->nodes($form.'//fieldset//label[contains(@class,"ds-option")]/input[@type="radio"][@name="'.$name.'"]');
            $this->assertSame($values, array_map(fn (DOMElement $r) => $r->getAttribute('value'), iterator_to_array($radios)), $name.' values');
            $this->assertSame(1, $this->nodes($form.'//input[@name="'.$name.'"][@checked]')->length, $name.' one checked');
            $this->assertSame(1, $this->nodes($form.'//input[@name="'.$name.'"][@value="'.$checked.'"][@checked]')->length, $name.' checked value');
        }

        $this->assertSame(1, $this->nodes($form.'//label[contains(@class,"ds-option")]/input[@type="checkbox"][@name="peak_limit_enabled"][@value="1"]')->length);
        foreach (['peak_limit_w1_start', 'peak_limit_w1_end', 'peak_limit_w2_start', 'peak_limit_w2_end'] as $name) {
            $input = $this->nodes($form.'//input[@type="time"][@name="'.$name.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $input, $name);
            $this->assertSame(1, $this->nodes('//label[@for="'.$input->getAttribute('id').'"]')->length, $name.' label');
        }
        $this->assertSame(1, $this->nodes($form.'//textarea[@name="calendar_hide"]')->length);
        $this->assertSame(1, $this->nodes($form.'//div[contains(@class,"ds-admin-actions")]//button[@type="submit"]')->length);
    }

    #[Test]
    public function unset_choices_fall_back_to_the_first_option_like_the_old_selects(): void
    {
        $this->load(route('admin.config.behavior.edit'));

        foreach (['registration' => '0', 'activation' => 'immediate', 'maintenance' => '0', 'quotes_enabled' => '1'] as $name => $value) {
            $this->assertSame(1, $this->nodes('//input[@name="'.$name.'"][@value="'.$value.'"][@checked]')->length, $name);
        }
    }
}
