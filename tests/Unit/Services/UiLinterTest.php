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

    /** @return array<string, array{string}> */
    public static function literalColourFunctionsInViews(): array
    {
        return [
            'tailwind rgb' => ['<div class="bg-[rgb(255,0,255)]">x</div>'],
            'tailwind rgba' => ['<div class="text-[rgba(0,0,0,.5)]">x</div>'],
            'tailwind hsl' => ['<div class="border-[hsl(10_50%_50%)]">x</div>'],
            'tailwind hsla' => ['<div class="bg-[hsla(10,50%,50%,.4)]">x</div>'],
            'tailwind oklch' => ['<div class="fill-[oklch(0.7_0.1_30)]">x</div>'],
            'tailwind shadow' => ['<div class="shadow-[0_0_0_1px_rgba(0,0,0,.1)]">x</div>'],
            'inline style attribute' => ['<div style="color: rgb(255 0 255)">x</div>'],
            'single-quoted style attribute' => ["<div style='background: hsl(200 50% 50%)'>x</div>"],
            'style block' => ["<style>\n  .x { color: oklch(0.6 0.2 30); }\n</style>"],
            'alpine bound style' => ['<div :style="{ color: \'rgba(255, 0, 255, 0.5)\' }">x</div>'],
            'alpine x-bind style' => ['<div x-bind:style="active ? \'background: rgb(1 2 3)\' : \'\'">x</div>'],
            'uppercase function' => ['<div class="bg-[RGB(1,2,3)]">x</div>'],
        ];
    }

    #[Test]
    #[DataProvider('literalColourFunctionsInViews')]
    public function literal_colour_functions_in_views_are_flagged(string $markup): void
    {
        $this->writeView('a.blade.php', $markup);
        $this->assertSame(1, $this->countRule('CLR-03'), $markup);
    }

    /** @return array<string, array{string}> */
    public static function tokenColourFormsInViews(): array
    {
        return [
            'token class' => ['<div class="bg-clay text-on-clay">x</div>'],
            'arbitrary token var' => ['<div class="bg-[var(--color-clay)]">x</div>'],
            'rgb with a token channel var' => ['<div class="bg-[rgb(var(--color-clay-rgb)/0.5)]">x</div>'],
            'color-mix of tokens' => ['<div x-bind:style="\'background: color-mix(in srgb, var(--color-clay) 20%, transparent)\'">x</div>'],
            'prose mentioning rgb' => ['<p>RGB values (red, green, blue)</p>'],
        ];
    }

    #[Test]
    #[DataProvider('tokenColourFormsInViews')]
    public function token_colour_forms_in_views_pass(string $markup): void
    {
        $this->writeView('a.blade.php', $markup);
        $this->assertSame(0, $this->countRule('CLR-03'), $markup);
    }

    #[Test]
    public function literal_colour_functions_in_css_are_allowed_only_as_app_css_custom_properties(): void
    {
        $this->writeFile('resources/css/app.css', ":root {\n  --ds-backdrop: rgba(29, 27, 24, 0.55);\n}\n@theme static {\n  --shadow-dialog: 0 20px 50px rgba(0, 0, 0, 0.25);\n}\n");
        $this->assertSame(0, $this->countRule('CSS-02'));

        $this->writeFile('resources/css/app.css', "a { color: rgb(1 2 3); }\n");
        $this->assertSame(1, $this->countRule('CSS-02'));

        $this->writeFile('resources/css/other.css', ":root { --x: hsl(1 2% 3%); }\n.a { background: oklch(0.6 0.2 30); }\n.b { color: HSLA(1, 2%, 3%, .5); }\n");
        $this->assertSame(4, $this->countRule('CSS-02'));

        $this->writeFile('resources/css/other.css', ".a { background: rgb(var(--color-ink-rgb) / 0.5); color: color-mix(in srgb, var(--color-ink) 50%, transparent); }\n");
        $this->assertSame(1, $this->countRule('CSS-02'));
    }

    #[Test]
    public function four_and_eight_digit_hex_colours_are_flagged(): void
    {
        $this->writeView('a.blade.php', '<div class="text-[#ff00ff80]">x</div>');
        $this->writeView('b.blade.php', '<div style="color:#f0f8">x</div>');
        $this->writeFile('resources/css/other.css', ".a { color: #12345678; }\n");
        $this->assertSame(2, $this->countRule('CLR-01'));
        $this->assertSame(1, $this->countRule('CSS-01'));
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
        $this->assertSame('<p class="text-red-600">x</p>', $f['source']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $f['fingerprint']);
    }

    #[Test]
    public function css_and_multiple_roots_are_scanned_but_other_files_are_not(): void
    {
        $this->writeFile('resources/views/a.blade.php', '<p class="text-red-500">x</p>');
        $this->writeFile('app/Modules/Members/resources/views/mail/index.blade.php', '<p class="text-blue-500">x</p>');
        $this->writeFile('resources/css/sample.css', '.card { color: #0066cc; }');
        $this->writeFile('resources/views/ignored.php', '#ff0000');

        $linter = new UiLinter([$this->tmpDir.'/resources', $this->tmpDir.'/app/Modules', $this->tmpDir.'/resources'], UiRules::all(), $this->tmpDir);

        $this->assertCount(3, $linter->files());
        $this->assertEqualsCanonicalizing(['CSS-01', 'CLR-02', 'CLR-02'], array_column($linter->run(), 'rule'));
        $this->assertContains('app/Modules/Members/resources/views/mail/index.blade.php', array_column($linter->run(), 'file'));
    }

    #[Test]
    public function comments_are_ignored_without_losing_line_numbers(): void
    {
        $this->writeView('page.blade.php', "{{-- #f00 --}}\n<!-- text-red-500 -->\n<p class=\"text-[#00f]\">Text</p>");
        $this->writeFile('resources/css/page.css', "/* #123456\n   #654321 */\na { color: #abcdef; }\n");

        $findings = (new UiLinter($this->tmpDir, UiRules::all()))->run();
        $pairs = array_map(fn (array $f): array => [$f['rule'], $f['line']], $findings);

        $this->assertEqualsCanonicalizing([['CLR-01', 3], ['CSS-01', 3]], $pairs);
    }

    #[Test]
    public function a_path_glob_in_blade_does_not_open_a_comment(): void
    {
        $this->writeView('page.blade.php', "<a class=\"{{ request()->is('admin/*') ? 'x' : '' }}\">A</a>\n<p class=\"text-red-500\">x</p>\n<p>*/</p>");
        $this->assertSame(1, $this->countRule('CLR-02'));
    }

    #[Test]
    public function inserting_lines_preserves_a_fingerprint_but_changing_the_violation_does_not(): void
    {
        $this->writeView('page.blade.php', '<p class="text-[#f00]">Text</p>');
        $original = (new UiLinter($this->tmpDir, UiRules::all()))->run()[0]['fingerprint'];

        $this->writeView('page.blade.php', "\n\n".'<p class="text-[#f00]">Text</p>');
        $this->assertSame($original, (new UiLinter($this->tmpDir, UiRules::all()))->run()[0]['fingerprint']);

        $this->writeView('page.blade.php', '<p class="text-[#0f0]">Text</p>');
        $this->assertNotSame($original, (new UiLinter($this->tmpDir, UiRules::all()))->run()[0]['fingerprint']);
    }
}
