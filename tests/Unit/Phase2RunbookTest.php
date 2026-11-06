<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The phase 2 runbook is a local, untracked operator document (docs/deploy). This test checks its module 4
 * section (Tournament + interactive Draw) wherever the runbook is present: at its default location in the
 * checkout or at the path in PHASE2_RUNBOOK. Without the file the test is skipped (CI, fresh clones).
 */
class Phase2RunbookTest extends TestCase
{
    private const ROLLBACK_LINE = 'RewriteRule ^(tournament|draw|service)(/|$) zf2-bridge.php [L]';

    #[Test]
    public function runbook_lists_the_live_differences_and_rollback_line(): void
    {
        $section = $this->moduleFourSection();

        foreach ([
            'src/Tournament/Controller/IndexController.php',
            'src/Tournament/Service/StandingsService.php',
            'view/tournament/index/groups.phtml',
            'view/tournament/index/index.phtml',
            'phase2:preflight tournament',
            'phase2:preflight draw',
            'draw:verify --all',
            'phase2:scrub-staging --force',
            'allow.admin.tournament',
            self::ROLLBACK_LINE,
        ] as $needle) {
            $this->assertStringContainsString($needle, $section, $needle);
        }
    }

    #[Test]
    public function module_four_section_names_no_host_or_address(): void
    {
        $section = $this->moduleFourSection();

        $this->assertDoesNotMatchRegularExpression('#https?://#i', $section);
        $this->assertDoesNotMatchRegularExpression("#@(?!example\.test)#", $section);
        $this->assertDoesNotMatchRegularExpression("#[a-z0-9-]\.(at|com)\b#i", $section);
    }

    private function moduleFourSection(): string
    {
        $path = getenv('PHASE2_RUNBOOK') ?: dirname(__DIR__, 2).'/docs/deploy/2026-phase2-runbook.md';

        if (! is_file($path)) {
            $this->markTestSkipped('local runbook not present: '.$path);
        }

        $contents = (string) file_get_contents($path);
        $start = strpos($contents, '## Module 4: Tournament');
        $this->assertNotFalse($start, 'runbook has no module 4 section');
        $end = strpos($contents, '<!-- end module 4 -->', $start);
        $this->assertNotFalse($end, 'module 4 section has no end marker');

        return substr($contents, $start, $end - $start);
    }
}
