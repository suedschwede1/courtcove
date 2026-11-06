<?php

declare(strict_types=1);

namespace Tests\Unit\Css;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The committed public/build must equal a fresh `npm run build`: the manifest only
 * references existing files and no stale, unreferenced asset stays behind.
 */
final class BuildManifestIntegrityTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = storage_path('framework/testing/build-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->fixture.'/assets', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    /** @param array<string, mixed> $manifest */
    private function writeFixture(array $manifest, array $files): void
    {
        file_put_contents($this->fixture.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        foreach ($files as $file) {
            file_put_contents($this->fixture.'/'.$file, 'x');
        }
    }

    #[Test]
    public function the_committed_build_is_consistent(): void
    {
        $this->assertSame([], BuildManifest::problems(public_path('build')));
    }

    #[Test]
    public function a_stale_unreferenced_asset_is_reported(): void
    {
        $this->writeFixture(
            ['resources/css/app.css' => ['file' => 'assets/app-new.css', 'isEntry' => true]],
            ['assets/app-new.css', 'assets/app-old.css'],
        );

        $this->assertSame(['stale asset not referenced by the manifest: assets/app-old.css'], BuildManifest::problems($this->fixture));
    }

    #[Test]
    public function a_manifest_entry_without_its_file_is_reported(): void
    {
        $this->writeFixture(
            ['resources/js/app.js' => ['file' => 'assets/app-gone.js', 'css' => ['assets/app-gone.css'], 'isEntry' => true]],
            [],
        );

        $this->assertSame([
            'manifest references a missing file: assets/app-gone.js',
            'manifest references a missing file: assets/app-gone.css',
        ], BuildManifest::problems($this->fixture));
    }

    #[Test]
    public function the_ci_build_check_compares_added_and_deleted_files_too(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/design.yml'));

        // A plain `git diff` ignores untracked files a fresh build adds (new hashes).
        $this->assertDoesNotMatchRegularExpression('/git diff --exit-code -- public\/build/', $workflow);
        $this->assertStringContainsString('rm -rf public/build', $workflow);
        $this->assertStringContainsString('git add -A public/build', $workflow);
        $this->assertStringContainsString('git diff --cached --exit-code -- public/build', $workflow);
    }
}
