<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs public/zf2-bridge.php in a subprocess against a stub ZF2 index.php.
 *
 * The bridge is copied into <tmp>/app/public so its app-root-relative lookups
 * (zf2.path, default ../../zf2/public) resolve inside the temp tree.
 */
class Zf2BridgeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/zf2bridge-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/app/public', 0777, true);
        copy(dirname(__DIR__, 2).'/public/zf2-bridge.php', $this->root.'/app/public/zf2-bridge.php');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            is_dir("$dir/$entry") ? $this->removeDir("$dir/$entry") : unlink("$dir/$entry");
        }

        rmdir($dir);
    }

    private function stubZf2(string $publicDir): void
    {
        mkdir($publicDir, 0777, true);
        file_put_contents($publicDir.'/index.php', '<?php echo $_SERVER["SCRIPT_NAME"]."|".$_SERVER["PHP_SELF"]."|".basename($_SERVER["SCRIPT_FILENAME"])."|".basename(dirname($_SERVER["SCRIPT_FILENAME"]));');
    }

    /**
     * @param  array<string, string>  $env
     * @return array{output: string, code: string}
     */
    private function runBridge(string $scriptName, string $requestUri, array $env = []): array
    {
        $wrapper = '$_SERVER["SCRIPT_NAME"]='.var_export($scriptName, true).';'
            .'$_SERVER["REQUEST_URI"]='.var_export($requestUri, true).';'
            .'register_shutdown_function(function () { echo "\nHTTP=".var_export(http_response_code(), true); });'
            .'require '.var_export($this->root.'/app/public/zf2-bridge.php', true).';';

        $process = proc_open(
            [PHP_BINARY, '-n', '-r', $wrapper],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + ['PATH' => (string) getenv('PATH')],
        );
        $this->assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($process);

        [$output, $code] = explode("\nHTTP=", $stdout) + ['', ''];

        return ['output' => $output, 'code' => $code];
    }

    #[Test]
    public function it_maps_script_name_at_the_docroot(): void
    {
        $this->stubZf2($this->root.'/zf2/public');

        $result = $this->runBridge('/zf2-bridge.php', '/tournament');

        $this->assertSame('/index.php|/index.php|index.php|public', $result['output']);
    }

    #[Test]
    public function it_maps_script_name_under_a_base_path(): void
    {
        $this->stubZf2($this->root.'/zf2/public');

        $result = $this->runBridge('/booking/zf2-bridge.php', '/booking/pyramide');

        $this->assertSame('/booking/index.php|/booking/index.php|index.php|public', $result['output']);
    }

    #[Test]
    public function zf2_public_env_overrides_the_default(): void
    {
        $this->stubZf2($this->root.'/zf2/public');
        $this->stubZf2($this->root.'/elsewhere/web');

        $result = $this->runBridge('/zf2-bridge.php', '/draw', ['ZF2_PUBLIC' => $this->root.'/elsewhere/web']);

        $this->assertSame('/index.php|/index.php|index.php|web', $result['output']);
    }

    #[Test]
    public function zf2_path_file_overrides_the_default(): void
    {
        $this->stubZf2($this->root.'/zf2/public');
        $this->stubZf2($this->root.'/fromfile/htdocs');
        file_put_contents($this->root.'/app/zf2.path', '  '.$this->root."/fromfile/htdocs  \nignored second line\n");

        $result = $this->runBridge('/zf2-bridge.php', '/mixed-tournament');

        $this->assertSame('/index.php|/index.php|index.php|htdocs', $result['output']);
    }

    #[Test]
    public function env_wins_over_the_zf2_path_file(): void
    {
        $this->stubZf2($this->root.'/fromfile/htdocs');
        $this->stubZf2($this->root.'/elsewhere/web');
        file_put_contents($this->root.'/app/zf2.path', $this->root."/fromfile/htdocs\n");

        $result = $this->runBridge('/zf2-bridge.php', '/draw', ['ZF2_PUBLIC' => $this->root.'/elsewhere/web']);

        $this->assertSame('/index.php|/index.php|index.php|web', $result['output']);
    }

    #[Test]
    public function missing_zf2_answers_503_without_leaking_paths(): void
    {
        $result = $this->runBridge('/zf2-bridge.php', '/tournament');

        $this->assertSame('503', $result['code']);
        $this->assertNotSame('', trim($result['output']));
        $this->assertStringNotContainsString($this->root, $result['output']);
        $this->assertStringNotContainsString('zf2', strtolower($result['output']));
    }

    #[Test]
    public function the_bridge_is_not_reachable_directly(): void
    {
        $this->stubZf2($this->root.'/zf2/public');

        foreach (['/zf2-bridge.php', '/booking/zf2-bridge.php', '/zf2-bridge.php?x=1'] as $uri) {
            $result = $this->runBridge('/zf2-bridge.php', $uri);

            $this->assertSame('404', $result['code'], $uri);
            $this->assertStringNotContainsString('index.php', $result['output']);
        }
    }
}
