<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Css\DesignTokensTest;

class UiLinterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/ui-linter-test-'.uniqid();
        mkdir($this->tmpDir.'/emails', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rimraf($this->tmpDir);
        parent::tearDown();
    }

    private function rimraf(string $dir): void
    {
        foreach (glob($dir.'/*') as $f) {
            is_dir($f) ? $this->rimraf($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function writeView(string $subpath, string $content): string
    {
        $path = $this->tmpDir.'/'.$subpath;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);

        return $path;
    }

    private function writeFile(string $subpath, string $content): string
    {
        return $this->writeView($subpath, $content);
    }

    /** @return list<string> rule ids found in the temp dir */
    private function ids(): array
    {
        return array_column((new UiLinter($this->tmpDir, UiRules::all()))->run(), 'rule');
    }

    private function countRule(string $rule): int
    {
        return count(array_filter($this->ids(), fn ($id) => $id === $rule));
    }

    #[Test]
    public function clean_view_has_no_findings(): void
    {
        $this->writeView('clean.blade.php', '<div class="bg-clay text-ink-2">Hallo</div>');
        $this->assertSame([], $this->ids());
    }

    #[Test]
    public function token_hex_contains_all_token_values(): void
    {
        foreach (DesignTokensTest::colorTokens() as [, $light, $dark]) {
            $this->assertContains(strtolower($light), UiRules::TOKEN_HEX);
            $this->assertContains(strtolower($dark), UiRules::TOKEN_HEX);
        }
    }

    #[Test]
    public function hex_in_view_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<div class="text-[#151515]">x</div>');
        $this->writeView('b.blade.php', '<div class="text-[#fff]">x</div>');
        $this->assertSame(2, $this->countRule('CLR-01'));
    }

    #[Test]
    public function entity_is_not_hex(): void
    {
        $this->writeView('a.blade.php', '<span>&#8250;</span>');
        $this->assertSame(0, $this->countRule('CLR-01'));
        $this->assertSame(1, $this->countRule('ICN-01'));
    }

    #[Test]
    public function emails_are_excluded(): void
    {
        $this->writeView('emails/x.blade.php', '<td style="color:#ff0000">text</td>');
        $this->assertSame([], $this->ids());
    }

    #[Test]
    public function tailwind_palette_colour_is_flagged(): void
    {
        foreach (['text-red-600', 'bg-green-50', 'border-gray-300'] as $i => $class) {
            $this->writeView("p{$i}.blade.php", "<div class=\"{$class}\">x</div>");
        }
        $this->writeView('ok.blade.php', '<div class="text-ink">x</div>');
        $this->assertSame(3, $this->countRule('CLR-02'));
    }

    #[Test]
    public function css_token_definitions_are_allowed_only_in_app_css(): void
    {
        $this->writeFile('resources/css/app.css', "@theme {\n  --color-clay: #B5441A;\n}\n");
        $this->assertSame(0, $this->countRule('CSS-01'));

        $this->writeFile('resources/css/app.css', "a { color: #123456; }\n");
        $this->assertSame(1, $this->countRule('CSS-01'));

        $this->writeFile('resources/css/other.css', "a { color: #B5441A; }\n");
        $this->assertSame(2, $this->countRule('CSS-01'));
    }

    #[Test]
    public function styled_raw_button_is_flagged_outside_components(): void
    {
        $this->writeView('pages/x.blade.php', '<button class="bg-white">x</button>');
        $this->writeView('components/ui/x.blade.php', '<button class="bg-white">x</button>');
        $this->assertSame(1, $this->countRule('BTN-01'));
    }

    #[Test]
    public function small_button_is_flagged(): void
    {
        foreach (['h-8', 'h-9', 'h-10'] as $i => $class) {
            $this->writeView("b{$i}.blade.php", "<button class=\"{$class}\">x</button>");
        }
        $this->assertSame(3, $this->countRule('BTN-02'));
    }

    #[Test]
    public function button_in_link_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<a href="x"><button>Go</button></a>');
        $this->assertSame(1, $this->countRule('BTN-03'));
    }

    #[Test]
    public function small_field_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<input class="h-9">');
        $this->assertSame(1, $this->countRule('INP-01'));
    }

    #[Test]
    public function rounded_full_select_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<select class="rounded-full"></select>');
        $this->assertSame(1, $this->countRule('INP-02'));
    }

    #[Test]
    public function custom_focus_ring_on_field_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<input class="focus:ring-[x]">');
        $this->assertSame(1, $this->countRule('INP-03'));
    }

    #[Test]
    public function square_badge_is_flagged_but_pill_is_not(): void
    {
        $this->writeView('a.blade.php', '<span class="px-2 py-0.5 text-xs rounded">x</span>');
        $this->assertSame(1, $this->countRule('BDG-01'));

        $this->writeView('a.blade.php', '<span class="px-2 py-0.5 text-xs rounded-full">x</span>');
        $this->assertSame(0, $this->countRule('BDG-01'));
    }

    #[Test]
    public function raw_table_is_flagged_unless_ds_table_or_component(): void
    {
        $this->writeView('pages/a.blade.php', '<table class="w-full"><tr><td>x</td></tr></table>');
        $this->assertSame(1, $this->countRule('TBL-01'));

        $this->writeView('pages/a.blade.php', '<table class="ds-table"><tr><td>x</td></tr></table>');
        $this->writeView('components/ui/t.blade.php', '<table class="w-full"></table>');
        $this->assertSame(0, $this->countRule('TBL-01'));
    }

    #[Test]
    public function tiny_font_size_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<p class="text-[10px]">x</p>');
        $this->assertSame(1, $this->countRule('TYP-01'));

        $this->writeView('a.blade.php', '<p class="text-[11px]">x</p>');
        $this->assertSame(0, $this->countRule('TYP-01'));
    }

    #[Test]
    public function foreign_font_families_are_flagged(): void
    {
        $this->writeView('a.blade.php', '<p class="font-serif">x</p>');
        $this->assertSame(1, $this->countRule('TYP-02'));

        $this->writeView('a.blade.php', '<p class="font-mono">x</p>');
        $this->assertSame(0, $this->countRule('TYP-02'));

        $this->writeView('a.blade.php', '<p>Red Hat Display</p>');
        $this->assertSame(1, $this->countRule('TYP-02'));
    }

    #[Test]
    public function inline_style_is_flagged_but_not_in_emails(): void
    {
        $this->writeView('emails/m.blade.php', '<td style="color:red">x</td>');
        $this->assertSame(0, $this->countRule('STY-01'));

        $this->writeView('a.blade.php', '<div style="color: red">x</div>');
        $this->assertSame(1, $this->countRule('STY-01'));
    }

    #[Test]
    public function inline_hover_handler_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<div onmouseover="x()">x</div>');
        $this->assertSame(1, $this->countRule('STY-02'));
    }

    #[Test]
    public function emoji_is_flagged(): void
    {
        $this->writeView('a.blade.php', '<p>'."\u{1F3BE}".'</p>');
        $this->assertSame(1, $this->countRule('EMO-01'));
    }

    /** @return array<string, array{string}> */
    public static function characterIcons(): array
    {
        return [
            'single angle' => ['&#8249;'], 'times entity' => ['&times;'], 'hex cross' => ['&#x2715;'],
            'arrow' => ['←'], 'hamburger' => ['☰'], 'ellipsis button' => ['<button>...</button>'],
        ];
    }

    #[Test]
    #[DataProvider('characterIcons')]
    public function character_icons_are_flagged(string $markup): void
    {
        $this->writeView('a.blade.php', '<span>'.$markup.'</span>');
        $this->assertSame(1, $this->countRule('ICN-01'));
    }

    #[Test]
    public function module_views_are_scanned(): void
    {
        $this->writeView('resources/views/x.blade.php', '<div class="text-[#151515]">x</div>');
        $this->writeView('app/Modules/Foo/resources/views/x.blade.php', '<div class="text-[#151515]">x</div>');

        $linter = new UiLinter(
            [$this->tmpDir.'/resources/views', $this->tmpDir.'/app/Modules/Foo/resources/views'],
            UiRules::all(),
            $this->tmpDir,
        );

        $files = array_column($linter->run(), 'file');
        $this->assertContains('app/Modules/Foo/resources/views/x.blade.php', $files);
        $this->assertContains('resources/views/x.blade.php', $files);
    }

    #[Test]
    public function finding_contains_file_line_rule_message(): void
    {
        $this->writeView('f.blade.php', '<p class="text-red-600">x</p>');
        $f = (new UiLinter($this->tmpDir, UiRules::all()))->run()[0];
        $this->assertSame('CLR-02', $f['rule']);
        $this->assertSame('f.blade.php', $f['file']);
        $this->assertSame(1, $f['line']);
        $this->assertNotSame('', $f['message']);
    }
}
