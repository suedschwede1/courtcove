<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\SimulatesSymlinkedDocroot;

/**
 * Runs public/index.php in a subprocess with public/ exposed through a real symlink:
 *
 *   <tmp>/bookingnew/{public,bootstrap,vendor,storage}   (stubs)
 *   <tmp>/link -> <tmp>/bookingnew/public
 *
 * Limitation: PHP CLI resolves the symlink for the primary script, so running it through the link
 * does not reproduce the PHP-FPM behaviour (unresolved __DIR__). The "unresolved" tests therefore
 * run a copy whose __DIR__ is replaced by the link path (see SimulatesSymlinkedDocroot); those are
 * the ones that fail on a front controller using '__DIR__.'/..'' directly.
 */
class PublicIndexSymlinkTest extends TestCase
{
    use SimulatesSymlinkedDocroot;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/publicindex-'.bin2hex(random_bytes(6));

        foreach (['public', 'bootstrap', 'vendor', 'storage/framework'] as $dir) {
            mkdir($this->root.'/bookingnew/'.$dir, 0777, true);
        }

        copy(dirname(__DIR__, 2).'/public/index.php', $this->root.'/bookingnew/public/index.php');

        file_put_contents($this->root.'/bookingnew/vendor/autoload.php', <<<'PHP'
<?php
namespace Illuminate\Http {
    class Request { public static function capture(): static { return new static; } }
}
namespace {
    echo "autoload|";
}
PHP);

        file_put_contents($this->root.'/bookingnew/bootstrap/app.php', <<<'PHP'
<?php
echo "bootstrap|leaked=".implode(",", array_intersect(["appRoot", "maintenance"], array_keys($GLOBALS)))."|";
return new class { public function handleRequest($request): void { echo "handled"; } };
PHP);

        symlink($this->root.'/bookingnew/public', $this->root.'/link');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    private function removeDir(string $dir): void
    {
        if (is_link($dir) || is_file($dir)) {
            unlink($dir);

            return;
        }

        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeDir("$dir/$entry");
            }
        }

        rmdir($dir);
    }

    /**
     * @return array{output: string, error: string}
     */
    private function runScript(string $script): array
    {
        $process = proc_open(
            [PHP_BINARY, '-n', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => (string) getenv('PATH')],
        );
        $this->assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        proc_close($process);

        return ['output' => $output, 'error' => $error];
    }

    #[Test]
    public function it_boots_through_the_symlink_path(): void
    {
        $result = $this->runScript($this->root.'/link/index.php');

        $this->assertSame('autoload|bootstrap|leaked=|handled', $result['output'], $result['error']);
    }

    #[Test]
    public function it_resolves_the_app_root_when_dir_is_the_unresolved_link_path(): void
    {
        $script = $this->copyWithUnresolvedDir(
            $this->root.'/bookingnew/public/index.php',
            $this->root.'/link',
            $this->root.'/bookingnew/public/index-fpm.php',
        );

        $result = $this->runScript($script);

        $this->assertSame('autoload|bootstrap|leaked=|handled', $result['output'], $result['error']);
    }

    #[Test]
    public function it_loads_the_maintenance_file_from_the_resolved_root(): void
    {
        file_put_contents($this->root.'/bookingnew/storage/framework/maintenance.php', '<?php echo "maintenance|";');
        $script = $this->copyWithUnresolvedDir(
            $this->root.'/bookingnew/public/index.php',
            $this->root.'/link',
            $this->root.'/bookingnew/public/index-fpm.php',
        );

        $result = $this->runScript($script);

        $this->assertSame('maintenance|autoload|bootstrap|leaked=|handled', $result['output'], $result['error']);
    }
}
