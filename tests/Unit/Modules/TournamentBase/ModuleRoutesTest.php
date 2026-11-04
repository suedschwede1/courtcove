<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Http\Middleware\StripFourByteCharacters;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use PHPUnit\Framework\TestCase;

class ModuleRoutesTest extends TestCase
{
    #[Test]
    public function module_routes_use_the_web_group(): void
    {
        $this->assertSame(['web'], ModuleRoutes::middleware());
    }

    #[Test]
    public function global_stripping_middleware_exists(): void
    {
        $this->assertTrue(class_exists(StripFourByteCharacters::class));
    }
}
