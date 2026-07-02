<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    private string $modulePath;

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulePath);
        File::deleteDirectory(base_path('tests/Feature/Modules/Widgets'));
        File::deleteDirectory(base_path('tests/Unit/Modules/Widgets'));

        parent::tearDown();
    }

    #[Test]
    public function it_scaffolds_the_module_directory_structure(): void
    {
        $this->modulePath = base_path('app/Modules/Widgets');

        $this->artisan('make:module', ['name' => 'Widgets'])
            ->assertExitCode(0);

        $this->assertFileExists($this->modulePath.'/WidgetsServiceProvider.php');
        $this->assertFileExists($this->modulePath.'/routes.php');
        $this->assertDirectoryExists($this->modulePath.'/Http/Controllers');
        $this->assertDirectoryExists($this->modulePath.'/Models');
        $this->assertDirectoryExists($this->modulePath.'/Database/Migrations');
        $this->assertDirectoryExists($this->modulePath.'/resources/views');
        $this->assertDirectoryExists($this->modulePath.'/resources/lang');
        $this->assertDirectoryExists(base_path('tests/Feature/Modules/Widgets'));
        $this->assertDirectoryExists(base_path('tests/Unit/Modules/Widgets'));

        $provider = File::get($this->modulePath.'/WidgetsServiceProvider.php');
        $this->assertStringContainsString('namespace App\Modules\Widgets;', $provider);
        $this->assertStringContainsString('class WidgetsServiceProvider', $provider);
    }
}
