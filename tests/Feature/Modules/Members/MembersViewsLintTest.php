<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The whole Members module (views and its stylesheet) is free of UI lint findings.
 */
class MembersViewsLintTest extends TestCase
{
    #[Test]
    public function module_has_no_ui_lint_findings(): void
    {
        $roots = [app_path('Modules/Members/resources/views'), resource_path('css')];
        $findings = (new UiLinter($roots, UiRules::all(), base_path()))->run();

        $offending = [];
        foreach ($findings as $finding) {
            $file = str_replace('\\', '/', $finding['file']);

            if (str_contains($file, 'Modules/Members/resources/views/') || str_ends_with($file, 'resources/css/members.css')) {
                $offending[] = "{$finding['rule']} {$file}:{$finding['line']}";
            }
        }

        $this->assertSame([], $offending);
    }
}
