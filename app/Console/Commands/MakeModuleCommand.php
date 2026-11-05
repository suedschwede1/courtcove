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
        $rawName = (string) $this->argument('name');

        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $rawName)) {
            $this->error("Invalid module name [{$rawName}]. Use only letters and digits, starting with a letter (e.g. Members) — no slashes, dots, or other path characters.");

            return self::FAILURE;
        }

        $name = Str::studly($rawName);
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
        File::put("{$modulePath}/Http/Controllers/DashboardController.php", $this->dashboardControllerStub($name, $lowerName));
        File::put("{$modulePath}/resources/views/layout.blade.php", $this->layoutStub($name, $lowerName));
        File::put("{$modulePath}/resources/views/dashboard.blade.php", $this->dashboardViewStub($name, $lowerName));

        $this->info("Module [{$name}] scaffolded at app/Modules/{$name}, with its own layout and dashboard (route: {$lowerName}.dashboard).");
        $this->line('Next steps:');
        $this->line("  1. Register App\\Modules\\{$name}\\{$name}ServiceProvider::class in bootstrap/providers.php");
        $this->line("  2. Add a \"{$name}\" <testsuite> entry to phpunit.xml pointing at tests/Feature/Modules/{$name} and tests/Unit/Modules/{$name}");
        $this->line("  3. Add an \"{$name}\" entry point (e.g. a header button) linking to route('{$lowerName}.dashboard'), gated by can('admin.{$lowerName}')");

        return self::SUCCESS;
    }

    private function providerStub(string $name, string $lowerName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Modules\\{$name};

        use Illuminate\Support\Facades\Route;
        use Illuminate\Support\ServiceProvider;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                \$this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

                Route::middleware('web')->group(function (): void {
                    \$this->loadRoutesFrom(__DIR__.'/routes.php');
                });

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

        use App\Modules\\{$name}\Http\Controllers\DashboardController;
        use Illuminate\Support\Facades\Route;

        Route::middleware(['auth', 'can:admin.{$lowerName}'])
            ->prefix('{$lowerName}')
            ->name('{$lowerName}.')
            ->group(function (): void {
                Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
            });

        PHP;
    }

    private function dashboardControllerStub(string $name, string $lowerName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Modules\\{$name}\Http\Controllers;

        use Illuminate\Contracts\View\View;

        class DashboardController
        {
            public function index(): View
            {
                return view('{$lowerName}::dashboard');
            }
        }

        PHP;
    }

    private function layoutStub(string $name, string $lowerName): string
    {
        return <<<BLADE
        @extends('layouts.base')

        @section('layout-title')
            {$name} - @yield('admin-title', '{$name} Dashboard')
        @endsection

        @section('body')
        <div class="flex min-h-screen bg-[#f3f1ec]">
            <aside style="width:180px; min-height:100vh; background:#1b1d21; flex-shrink:0; display:flex; flex-direction:column;">
                <div style="padding:14px 14px 10px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:8px;">
                    <span style="font-family:var(--font-body); font-size:10px; color:#6a6e73; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
                        {$name}
                    </span>
                </div>

                <nav style="flex:1; padding:6px 0;">
                    @foreach([
                        ['route' => '{$lowerName}.dashboard', 'pattern' => '{$lowerName}.dashboard', 'label' => '{$name} Dashboard'],
                    ] as \$item)
                        @php \$active = request()->routeIs(...explode(',', \$item['pattern'])); @endphp
                        <a href="{{ route(\$item['route']) }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ \$active ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ \$active ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ \$active ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ \$active ? '700' : '400' }}; background:{{ \$active ? '#34363b' : 'transparent' }}; border-left:{{ \$active ? '3px solid #bf4316' : '3px solid transparent' }};">
                            {{ \$item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="ui-admin-main">
                <header class="flex items-center justify-between border-b border-[#e7e3da] bg-white px-6 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">{$name}</p>
                        <h1 class="mt-1 text-lg font-bold text-[#151515]" style="font-family: var(--font-display)">@yield('admin-title', '{$name} Dashboard')</h1>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-[#6a6e73]">
                        @auth
                            <span>{{ auth()->user()->alias }}</span>
                            <a href="{{ route('calendar.index') }}" class="ui-btn ui-btn-outline">{{ __('booking.nav.back_to_calendar') }}</a>
                            @can('admin.see-menu')
                                <a href="{{ route('admin.dashboard') }}" class="ui-btn ui-btn-outline">{{ __('booking.nav.admin') }}</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="ui-btn ui-btn-outline">{{ __('booking.nav.logout') }}</button>
                            </form>
                        @endauth
                    </div>
                </header>

                @if(session('success'))
                    <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('success') }}</div>
                @endif
                @if(session('status'))
                    <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('status') }}</div>
                @endif
                @if(session('error'))
                    <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ session('error') }}</div>
                @endif
                @if(\$errors->any())
                    <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ \$errors->first() }}</div>
                @endif
                <main class="flex-1 p-6">@yield('admin-content')</main>
            </div>
        </div>
        @endsection

        BLADE;
    }

    private function dashboardViewStub(string $name, string $lowerName): string
    {
        return <<<BLADE
        @extends('{$lowerName}::layout')
        @section('admin-title', '{$name} Dashboard')
        @section('admin-content')
        <div class="ui-page">
            <div class="ui-page-header"><h1>{$name} Dashboard</h1></div>
            <div class="ui-card"><div class="ui-card-body">Welcome to the {$name} module.</div></div>
        </div>
        @endsection

        BLADE;
    }
}
