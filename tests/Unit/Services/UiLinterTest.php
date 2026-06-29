<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiLinterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/ui-linter-test-' . uniqid();
        mkdir($this->tmpDir . '/emails', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rimraf($this->tmpDir);
        parent::tearDown();
    }

    private function rimraf(string $dir): void
    {
        foreach (glob($dir . '/*') as $f) {
            is_dir($f) ? $this->rimraf($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function blade(string $subpath, string $content): string
    {
        $path = $this->tmpDir . '/' . $subpath;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
        return $path;
    }

    #[Test]
    public function it_returns_no_findings_for_clean_blade(): void
    {
        $this->blade('clean.blade.php', '<div class="text-[#151515]">Hallo</div>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $this->assertEmpty($linter->run());
    }

    #[Test]
    public function it_detects_clr02_text_red_500(): void
    {
        $this->blade('err.blade.php', '<p class="text-red-500">Fehler</p>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $findings = $linter->run();
        $this->assertCount(1, $findings);
        $this->assertSame('CLR-02', $findings[0]['rule']);
        $this->assertSame(1, $findings[0]['line']);
    }

    #[Test]
    public function it_detects_clr01_forbidden_hex(): void
    {
        $this->blade('hex.blade.php', '<div style="color: #ff0000">text</div>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $findings = $linter->run();
        $ids = array_column($findings, 'rule');
        $this->assertContains('CLR-01', $ids);
    }

    #[Test]
    public function it_skips_clr01_for_allowed_hex(): void
    {
        $this->blade('ok.blade.php', '<div class="text-[#bf4316]">ok</div>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $findings = array_filter($linter->run(), fn($f) => $f['rule'] === 'CLR-01');
        $this->assertEmpty($findings);
    }

    #[Test]
    public function it_skips_clr01_in_emails_dir(): void
    {
        $this->blade('emails/confirm.blade.php', '<td style="color:#ff0000">text</td>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $findings = array_filter($linter->run(), fn($f) => $f['rule'] === 'CLR-01');
        $this->assertEmpty($findings);
    }

    #[Test]
    public function it_detects_btn01_wrong_orange(): void
    {
        $this->blade('btn.blade.php', '<button class="bg-orange-500 text-white">Speichern</button>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertContains('BTN-01', $ids);
    }

    #[Test]
    public function it_detects_inp02_rounded_lg_on_input(): void
    {
        $this->blade('inp.blade.php', '<input type="text" class="border rounded-lg px-3">');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertContains('INP-02', $ids);
    }

    #[Test]
    public function it_detects_tbl01_thead_without_uppercase(): void
    {
        $content = implode("\n", [
            '<table>',
            '<thead class="bg-[#fafafa]">',
            '<tr><th>Name</th></tr>',
            '</thead>',
            '</table>',
        ]);
        $this->blade('tbl.blade.php', $content);
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertContains('TBL-01', $ids);
    }

    #[Test]
    public function it_does_not_flag_tbl01_when_uppercase_present(): void
    {
        $content = implode("\n", [
            '<table>',
            '<thead class="bg-[#fafafa] uppercase tracking-wide">',
            '<tr><th>Name</th></tr>',
            '</thead>',
            '</table>',
        ]);
        $this->blade('tbl_ok.blade.php', $content);
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertNotContains('TBL-01', $ids);
    }

    #[Test]
    public function it_detects_sty01_inline_style(): void
    {
        $this->blade('sty.blade.php', '<div style="color: red">text</div>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertContains('STY-01', $ids);
    }

    #[Test]
    public function it_skips_sty01_in_emails_dir(): void
    {
        $this->blade('emails/mail.blade.php', '<td style="color:red">text</td>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $ids = array_column($linter->run(), 'rule');
        $this->assertNotContains('STY-01', $ids);
    }

    #[Test]
    public function finding_contains_file_line_rule_message(): void
    {
        $this->blade('f.blade.php', '<p class="text-red-500">x</p>');
        $linter = new UiLinter($this->tmpDir, UiRules::all());
        $f = $linter->run()[0];
        $this->assertArrayHasKey('rule', $f);
        $this->assertArrayHasKey('file', $f);
        $this->assertArrayHasKey('line', $f);
        $this->assertArrayHasKey('message', $f);
    }
}
