<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawViewsLintTest extends TestCase
{
    #[Test]
    public function module_views_pass_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Draw/resources/views'), UiRules::all()))->run());
    }
}
