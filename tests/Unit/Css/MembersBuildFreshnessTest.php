<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class MembersBuildFreshnessTest extends TestCase
{
    #[Test]
    public function every_mbr_class_used_in_module_views_is_built(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/Members/resources/views'), RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
        $this->assertNotEmpty($files);

        $missing = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            $strings = [];
            preg_match_all('/(?:x-bind:|:)?class="([^"]*)"/', $src, $m1);
            preg_match_all('/(?:x-bind:|:)class=\'([^\']*)\'/', $src, $m2);
            preg_match_all('/\'(mbr-[a-z0-9_-]+)\'/', $src, $m3);
            preg_match_all('/"(mbr-[a-z0-9_-]+)"/', $src, $m4);
            array_push($strings, ...$m1[1], ...$m2[1], ...$m3[1], ...$m4[1]);

            foreach ($strings as $s) {
                preg_match_all('/\bmbr-[a-z0-9_-]+/', $s, $t);
                foreach ($t[0] as $token) {
                    if (! preg_match('/\.'.preg_quote($token, '/').'(?![\w-])/', $css)) {
                        $missing[$token] = basename($file);
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Classes missing in the built CSS; run npm run build.');
    }
}
