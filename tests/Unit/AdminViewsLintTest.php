<?php

namespace Tests\Unit;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins every migrated admin view to zero lint findings and no legacy ui-* classes.
 */
final class AdminViewsLintTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function views(): array
    {
        $paths = [
            'components/layout/admin-sidebar.blade.php',
            'layouts/admin.blade.php',
            'components/admin/page-header.blade.php',
            'layouts/popup.blade.php',
            'admin/dashboard.blade.php',
            'admin/statistics/index.blade.php',
            'admin/bookings/edit.blade.php',
            'admin/bookings/_update.blade.php',
            'admin/bookings/_create.blade.php',
            'admin/bookings/_script.blade.php',
        ];

        return array_combine($paths, array_map(fn (string $p) => [$p], $paths));
    }

    #[Test]
    #[DataProvider('views')]
    public function view_has_no_lint_findings(string $path): void
    {
        $findings = (new UiLinter(resource_path('views/'.dirname($path)), UiRules::all(), base_path()))->run();
        $own = array_values(array_filter($findings, fn (array $f) => str_ends_with(str_replace('\\', '/', $f['file']), $path)));

        $this->assertSame([], $own, $path.' has lint findings');
    }

    #[Test]
    #[DataProvider('views')]
    public function view_uses_no_legacy_ui_classes(string $path): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$path));

        $this->assertDoesNotMatchRegularExpression('/(class="[^"]*|[\'\s])ui-[a-z]/', $source, $path.' still uses legacy ui-* classes');
    }
}
