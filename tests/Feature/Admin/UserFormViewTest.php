<?php

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserFormViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private string $html;

    private User $member;

    private function load(string $html): void
    {
        $this->html = $html;
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function renderEdit(): void
    {
        $this->member = User::factory()->create(['alias' => 'Pat Example', 'status' => 'enabled']);
        LoginHistory::record((int) $this->member->uid, '1.1.1.1', 'RU', 'success', ['unusual_country']);

        $admin = User::factory()->create(['status' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.users.edit', $this->member));
        $response->assertOk();
        $this->load($response->getContent());
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    #[Test]
    public function edit_sections_are_cards_with_labelled_fields(): void
    {
        $this->renderEdit();

        $titles = [];
        foreach ($this->nodes('//h2[contains(@class,"ds-card__title")]') as $h2) {
            $titles[] = trim($h2->textContent);
        }
        $this->assertSame([
            __('booking.admin.users.section_account'),
            __('booking.admin.users.section_profile'),
            __('booking.admin.users.section_access'),
            __('booking.admin.users.reset_password'),
            __('booking.admin.users.delete_user'),
        ], $titles);

        $controls = $this->nodes('//*[@id][starts-with(@id,"uf-")][self::input or self::select or self::textarea]');
        $this->assertGreaterThanOrEqual(8, $controls->length);
        foreach ($controls as $control) {
            $id = $control->getAttribute('id');
            $this->assertSame(1, $this->nodes('//label[@for="'.$id.'"]')->length, $id.' has no label');
        }

        $this->assertSame(1, $this->nodes('//select[@id="uf-privileges"][@multiple][contains(@class,"ds-select")]')->length);
        $this->assertSame(0, $this->nodes('//*[contains(@class,"ds-card__title")][self::h1]')->length);
        $this->assertSame(1, $this->nodes('//h1')->length);
        $this->assertStringContainsString('PRIVILEGE_PRESETS', $this->html);
    }

    #[Test]
    public function edit_header_links_login_history_and_shows_risk(): void
    {
        $this->renderEdit();

        $this->assertSame(1, $this->nodes('//*[contains(@class,"ds-admin-head__actions")]//a[@href="'.route('admin.users.login-history', $this->member).'"]')->length);

        $meta = $this->nodes('//*[contains(@class,"ds-admin-head__meta")]');
        $this->assertSame(1, $meta->length);
        $this->assertStringContainsString(__('booking.admin.users.login_history_risk_score'), $meta->item(0)->textContent);

        $score = (string) LoginHistory::riskScore((int) $this->member->uid);
        $this->assertGreaterThan(0, (int) $score);
        $badge = $this->nodes('//*[contains(@class,"ds-admin-head__meta")]//*[contains(@class,"ds-badge")]');
        $this->assertSame(1, $badge->length);
        $this->assertSame($score, $badge->item(0)->textContent);

        $this->assertSame(1, $this->nodes('//nav[contains(@class,"ds-admin-crumbs")]//a[@href="'.route('admin.users.index').'"]')->length);
    }

    #[Test]
    public function actions_and_danger_zone(): void
    {
        $this->renderEdit();

        $update = $this->nodes('//form[@action="'.route('admin.users.update', $this->member).'"][.//input[@name="_method"][@value="PUT"]]');
        $this->assertSame(1, $update->length);
        $form = $update->item(0);
        $last = null;
        foreach ($form->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $last = $child;
            }
        }
        $this->assertNotNull($last);
        $this->assertSame('div', $last->nodeName);
        $this->assertStringContainsString('ds-admin-actions', $last->getAttribute('class'));

        $primary = $this->xpath->query('.//button[@type="submit"][contains(@class,"ds-btn--primary")]', $last);
        $this->assertSame(1, $primary->length);
        $this->assertSame(__('booking.admin.common.save'), trim($primary->item(0)->textContent));
        $cancel = $this->xpath->query('.//a[contains(@class,"ds-btn--secondary")][@href="'.route('admin.users.index').'"]', $last);
        $this->assertSame(1, $cancel->length);

        $delete = $this->nodes('//form[@action="'.route('admin.users.destroy', $this->member).'"][.//input[@name="_method"][@value="DELETE"]]');
        $this->assertSame(1, $delete->length);
        $this->assertStringContainsString('return confirm(', $delete->item(0)->getAttribute('onsubmit'));
        $this->assertSame(1, $this->xpath->query('.//button[@type="submit"][contains(@class,"ds-btn--danger")]', $delete->item(0))->length);

        $this->assertSame(1, $this->nodes('//form[@action="'.route('admin.users.password', $this->member).'"]//input[@id="uf-new-pw"][@type="password"]')->length);
    }

    #[Test]
    public function create_page_uses_the_same_partial(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.users.create'));
        $response->assertOk();
        $this->load($response->getContent());

        $this->assertSame(1, $this->nodes('//h2[contains(@class,"ds-card__title")][normalize-space(.)="'.__('booking.admin.users.section_account').'"]')->length);
        $this->assertSame(1, $this->nodes('//input[@id="uf-pw"][@type="password"]')->length);
        $this->assertSame(1, $this->nodes('//select[@id="uf-privileges"][@multiple]')->length);
        $primary = $this->nodes('//form[@action="'.route('admin.users.store').'"]//button[@type="submit"][contains(@class,"ds-btn--primary")]');
        $this->assertSame(1, $primary->length);
        $this->assertSame(__('booking.admin.common.create'), trim($primary->item(0)->textContent));
        $this->assertSame(1, $this->nodes('//h1')->length);
        $this->assertSame(0, $this->nodes('//form[contains(@action,"/password")]')->length);
    }
}
