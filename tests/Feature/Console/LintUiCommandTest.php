<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LintUiCommandTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = storage_path('framework/testing/ui-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->fixture, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    /** Points the command at an isolated fixture with one legacy finding. */
    private function useFixture(): void
    {
        config(['ui-design.roots' => [$this->fixture], 'ui-design.baseline' => $this->fixture.'/baseline.json', 'ui-design.migrated' => []]);
        file_put_contents($this->fixture.'/page.blade.php', '<p class="text-red-500">Legacy</p>');
    }

    #[Test]
    public function the_repository_passes_its_own_design_checks(): void
    {
        $this->artisan('lint:ui --summary')
            ->expectsOutputToContain('0 blocking findings')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_when_findings_exceed_the_limit(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        $this->artisan('lint:ui --summary --fail-over=0')
            ->expectsOutputToContain('warnings in')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_succeeds_when_findings_are_within_the_limit(): void
    {
        $this->artisan('lint:ui --summary --fail-over=1000000')
            ->expectsOutputToContain('warnings in')
            ->assertExitCode(0);
    }

    #[Test]
    public function known_debt_passes_but_a_new_violation_fails(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        $this->artisan('lint:ui --summary')->assertSuccessful();
        file_put_contents($this->fixture.'/page.blade.php', "\n<p class=\"text-red-500\">Legacy</p>\n<p class=\"text-blue-500\">New</p>");
        $this->artisan('lint:ui --summary')->assertFailed();
    }

    #[Test]
    public function duplicate_occurrences_cannot_reuse_one_exception(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        file_put_contents($this->fixture.'/page.blade.php', '<p class="text-red-500">Legacy</p>'."\n".'<p class="text-red-500">Legacy</p>');
        $this->artisan('lint:ui --summary')->assertFailed();
    }

    #[Test]
    public function migrated_paths_are_strict_even_if_a_baseline_entry_exists(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        config(['ui-design.migrated' => ['storage/framework/testing/']]);
        $this->artisan('lint:ui --summary')->assertFailed();
        $this->artisan('lint:ui --write-baseline')->assertFailed();
        unlink($this->fixture.'/baseline.json');
        $this->artisan('lint:ui --write-baseline')->assertFailed();
        $this->assertFileDoesNotExist($this->fixture.'/baseline.json');
    }

    #[Test]
    public function strict_mode_and_invalid_baselines_fail(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        $this->artisan('lint:ui --strict --summary')->assertFailed();
        file_put_contents($this->fixture.'/baseline.json', '{bad json');
        $this->artisan('lint:ui --summary')->assertFailed();
    }

    #[Test]
    public function baseline_cannot_be_silently_replaced_and_pruning_never_accepts_new_debt(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --write-baseline')->assertSuccessful();
        $before = file_get_contents($this->fixture.'/baseline.json');
        $this->artisan('lint:ui --write-baseline')->assertFailed();
        $this->assertSame($before, file_get_contents($this->fixture.'/baseline.json'));
        file_put_contents($this->fixture.'/page.blade.php', '<p class="text-blue-500">New</p>');
        $this->artisan('lint:ui --prune-baseline --summary')->assertFailed();
        $this->assertSame([], json_decode((string) file_get_contents($this->fixture.'/baseline.json'), true)['findings']);
    }

    #[Test]
    public function unknown_rules_and_filtered_baseline_operations_fail(): void
    {
        $this->useFixture();
        $this->artisan('lint:ui --rule=TYPO')->assertFailed();
        $this->artisan('lint:ui --rule=CLR --write-baseline')->assertFailed();
    }

    #[Test]
    public function missing_configuration_and_empty_source_roots_never_pass(): void
    {
        config(['ui-design.roots' => null]);
        $this->artisan('lint:ui --summary')->assertFailed();
        config(['ui-design.roots' => []]);
        $this->artisan('lint:ui --summary')->assertFailed();
    }
}
