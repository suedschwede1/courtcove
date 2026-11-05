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
        if (isset($this->modulePath)) {
            File::deleteDirectory($this->modulePath);
        }
        File::deleteDirectory(base_path('tests/Feature/Modules/Widgets'));
        File::deleteDirectory(base_path('tests/Unit/Modules/Widgets'));

        // Safety net in case a path-traversal regression creates stray directories.
        File::deleteDirectory(base_path('etc'));
        File::deleteDirectory(base_path('app/etc'));
        File::deleteDirectory(base_path('tests/etc'));

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

    #[Test]
    public function it_scaffolds_an_own_area_with_a_dashboard(): void
    {
        $this->modulePath = base_path('app/Modules/Widgets');

        $this->artisan('make:module', ['name' => 'Widgets'])
            ->assertExitCode(0);

        $this->assertFileExists($this->modulePath.'/Http/Controllers/DashboardController.php');
        $this->assertFileExists($this->modulePath.'/resources/views/dashboard.blade.php');
        $this->assertFileExists($this->modulePath.'/resources/views/layout.blade.php');

        $controller = File::get($this->modulePath.'/Http/Controllers/DashboardController.php');
        $this->assertStringContainsString('namespace App\Modules\Widgets\Http\Controllers;', $controller);
        $this->assertStringContainsString('class DashboardController', $controller);
        $this->assertStringContainsString("view('widgets::dashboard')", $controller);

        $routes = File::get($this->modulePath.'/routes.php');
        $this->assertStringContainsString("prefix('widgets')", $routes);
        $this->assertStringContainsString("name('widgets.')", $routes);
        $this->assertStringContainsString("Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')", $routes);

        $dashboardView = File::get($this->modulePath.'/resources/views/dashboard.blade.php');
        $this->assertStringContainsString("@extends('widgets::layout')", $dashboardView);

        $layout = File::get($this->modulePath.'/resources/views/layout.blade.php');
        $this->assertStringContainsString('widgets.dashboard', $layout);
        $this->assertStringContainsString("@extends('layouts.base')", $layout);
        $this->assertStringNotContainsString('fonts.googleapis', $layout);

        $provider = File::get($this->modulePath.'/WidgetsServiceProvider.php');
        $this->assertStringContainsString("Route::middleware('web')", $provider);
    }

    #[Test]
    public function it_rejects_a_path_traversal_attempt_without_touching_the_filesystem(): void
    {
        $this->artisan('make:module', ['name' => '../../etc/evil'])
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist(base_path('etc'));
        $this->assertDirectoryDoesNotExist(base_path('app/etc'));
        $this->assertDirectoryDoesNotExist(base_path('tests/etc'));
    }
}
