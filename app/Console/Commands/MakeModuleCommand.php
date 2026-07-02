<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : StudlyCase module name, e.g. Members}';

    protected $description = 'Scaffold a new self-contained module under app/Modules/<Name>';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $lowerName = Str::lower($name);
        $modulePath = base_path("app/Modules/{$name}");

        if (File::isDirectory($modulePath)) {
            $this->error("Module [{$name}] already exists at {$modulePath}.");

            return self::FAILURE;
        }

        foreach ([
            "{$modulePath}/Http/Controllers",
            "{$modulePath}/Models",
            "{$modulePath}/Requests",
            "{$modulePath}/Database/Migrations",
            "{$modulePath}/resources/views",
            "{$modulePath}/resources/lang",
            base_path("tests/Feature/Modules/{$name}"),
            base_path("tests/Unit/Modules/{$name}"),
        ] as $directory) {
            File::ensureDirectoryExists($directory);
        }

        File::put("{$modulePath}/{$name}ServiceProvider.php", $this->providerStub($name, $lowerName));
        File::put("{$modulePath}/routes.php", $this->routesStub($name, $lowerName));

        $this->info("Module [{$name}] scaffolded at app/Modules/{$name}.");
        $this->line('Next steps:');
        $this->line("  1. Register App\\Modules\\{$name}\\{$name}ServiceProvider::class in bootstrap/providers.php");
        $this->line("  2. Add a \"{$name}\" <testsuite> entry to phpunit.xml pointing at tests/Feature/Modules/{$name} and tests/Unit/Modules/{$name}");

        return self::SUCCESS;
    }

    private function providerStub(string $name, string $lowerName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Modules\\{$name};

        use Illuminate\Support\ServiceProvider;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                \$this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
                \$this->loadRoutesFrom(__DIR__.'/routes.php');
                \$this->loadViewsFrom(__DIR__.'/resources/views', '{$lowerName}');
                \$this->loadTranslationsFrom(__DIR__.'/resources/lang', '{$lowerName}');
            }
        }

        PHP;
    }

    private function routesStub(string $name, string $lowerName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;

        Route::middleware(['auth', 'can:admin.{$lowerName}'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                // Route::resource('{$lowerName}', \App\Modules\\{$name}\Http\Controllers\ExampleController::class);
            });

        PHP;
    }
}
