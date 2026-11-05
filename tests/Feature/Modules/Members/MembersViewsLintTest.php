<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Keeps restyled Members views free of UI lint findings. Each restyle task
 * appends the views it migrated (relative to the module's views directory).
 */
class MembersViewsLintTest extends TestCase
{
    /** @var list<string> */
    public const MIGRATED = [
        'layout.blade.php',
        'components/sidebar.blade.php',
        'components/page-header.blade.php',
        'partials/inline-save.blade.php',
        'members/index.blade.php',
    ];

    private const VIEWS_PREFIX = 'Modules/Members/resources/views/';

    #[Test]
    public function migrated_views_have_no_ui_lint_findings(): void
    {
        $findings = (new UiLinter([app_path('Modules/Members/resources/views')], UiRules::all(), base_path()))->run();

        $offending = [];
        foreach ($findings as $finding) {
            $file = str_replace('\\', '/', $finding['file']);
            $position = strpos($file, self::VIEWS_PREFIX);
            $relative = $position === false ? $file : substr($file, $position + strlen(self::VIEWS_PREFIX));

            if (in_array($relative, self::MIGRATED, true)) {
                $offending[] = "{$finding['rule']} {$relative}:{$finding['line']}";
            }
        }

        $this->assertSame([], $offending);
    }
}
