<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsFormsRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, string> field id => submitted name */
    private const FIELDS = [
        'fy-name' => 'name',
        'fy-starts-on' => 'starts_on',
        'fy-ends-on' => 'ends_on',
        'fy-faellig-am' => 'faellig_am',
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['create' => ['create'], 'edit' => ['edit']];
    }

    #[Test]
    #[DataProvider('pages')]
    public function fiscal_year_fields_have_a_label_an_id_and_their_original_name(string $which): void
    {
        $url = $which === 'create'
            ? route('members.fiscal-years.create')
            : route('members.fiscal-years.edit', FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']));

        $html = (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

        foreach (self::FIELDS as $id => $name) {
            $this->assertStringContainsString('<label class="ds-field__label" for="'.$id.'">', $html, "label for {$id}");
            $this->assertMatchesRegularExpression(
                '/<input[^>]*id="'.$id.'"[^>]*name="'.$name.'"|<input[^>]*name="'.$name.'"[^>]*id="'.$id.'"/',
                $html,
                "input {$id}",
            );
        }
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('ui-field', $html);
        $this->assertStringNotContainsString('ui-card', $html);
    }

    #[Test]
    public function dsgvo_retention_field_is_required_and_described_by_its_hint(): void
    {
        $html = (string) $this->actingAs($this->admin())->get(route('members.dsgvo.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="dsgvo-retention-years"[^>]*aria-describedby="dsgvo-retention-years-help"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="dsgvo-retention-years"[^>]*\srequired/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="dsgvo-retention-years"[^>]*value="3"/', $html);
        $this->assertStringContainsString('id="dsgvo-retention-years-help"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('ui-field', $html);
    }
}
