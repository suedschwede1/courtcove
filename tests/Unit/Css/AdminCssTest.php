<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminCssTest extends TestCase
{
    #[Test]
    public function every_admin_class_is_built(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        $files = array_merge(
            glob(resource_path('views/components/admin/*.blade.php')) ?: [],
            glob(resource_path('views/components/layout/admin-sidebar.blade.php')) ?: [],
            glob(resource_path('views/layouts/{admin,popup}.blade.php'), GLOB_BRACE) ?: [],
            $this->adminViews(resource_path('views/admin')),
        );

        $missing = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            preg_match_all('/\bds-(?:admin-[a-z0-9_-]+|popup[a-z0-9_-]*)/', $src, $m);
            foreach ($m[0] as $token) {
                if (! preg_match('/\.'.preg_quote($token, '/').'(?![\w-])/', $css)) {
                    $missing[$token] = basename($file);
                }
            }
        }

        $this->assertSame([], $missing, 'Admin classes missing in the built CSS; run npm run build.');
    }

    #[Test]
    public function admin_rules_live_only_in_admin_css(): void
    {
        $ds = (string) file_get_contents(resource_path('css/ds.css'));

        $this->assertStringNotContainsString('.ds-admin', $ds);
        $this->assertStringNotContainsString('.ds-popup', $ds);
    }

    /**
     * @return list<string>
     */
    private function adminViews(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
