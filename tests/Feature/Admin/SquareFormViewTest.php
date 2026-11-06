<?php

namespace Tests\Feature\Admin;

use App\Models\Square;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the restyled court form: section cards, option tiles for the flags, page header.
 */
final class SquareFormViewTest extends TestCase
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
    public function edit_sections_are_cards_and_flags_are_option_tiles(): void
    {
        $square = Square::factory()->create(['name' => 'Court A']);
        $this->load(route('admin.squares.edit', $square));

        // Buchungszeitraum card and the grid/pseudo fields moved to Verhalten > Buchungsregeln (2026-11-06).
        $this->assertGreaterThanOrEqual(4, $this->nodes('//main//section[contains(@class,"ds-admin-form-card")]')->length);
        foreach (['allow_notes', 'peak_limit_enabled'] as $name) {
            $this->assertSame(1, $this->nodes('//label[contains(@class,"ds-option")]/input[@type="checkbox"][@name="'.$name.'"][@value="1"]')->length, $name.' tile');
        }
        $this->assertSame(1, $this->nodes('//h1[contains(@class,"ds-admin-head__title")]')->length);
        $this->assertSame(1, $this->nodes('//nav[contains(@class,"ds-admin-crumbs")]/a[@href="'.route('admin.squares.index').'"]')->length);
        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="_method"][@value="PUT"]')->length);
        $this->assertSame(1, $this->nodes('//div[contains(@class,"ds-admin-actions")]//button[@type="submit"]')->length);
    }

    #[Test]
    public function every_number_field_keeps_its_name_and_label(): void
    {
        $this->load(route('admin.squares.create'));

        foreach ($this->nodes('//main//input[@type="number"]') as $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $this->assertSame(1, $this->nodes('//label[@for="'.$input->getAttribute('id').'"]')->length, $input->getAttribute('name').' has a label');
        }
        $this->assertSame(1, $this->nodes('//main//input[@type="number"]')->length); // only Priorität; the booking rules moved to Verhalten
    }
}
