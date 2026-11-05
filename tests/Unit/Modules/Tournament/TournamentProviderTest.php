<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Draw\DrawServiceProvider;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\TournamentServiceProvider;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentProviderTest extends TestCase
{
    #[Test]
    public function translations_load_in_both_locales(): void
    {
        $this->assertSame('Für den Turnierbereich musst du angemeldet sein.', __('tournament::common.login_required', [], 'de'));
        $this->assertSame('You need to be logged in for the tournament area.', __('tournament::common.login_required', [], 'en'));
        $this->assertSame('KO-Raster', __('tournament::common.tabs.bracket', [], 'de'));
    }

    #[Test]
    public function provider_is_registered_after_draw(): void
    {
        $p = require base_path('bootstrap/providers.php');

        $this->assertGreaterThan(
            array_search(DrawServiceProvider::class, $p, true),
            array_search(TournamentServiceProvider::class, $p, true),
        );
        $this->assertArrayHasKey(TournamentServiceProvider::class, app()->getLoadedProviders());
    }

    #[Test]
    public function de_and_en_key_sets_are_equal(): void
    {
        $dir = app_path('Modules/Tournament/resources/lang');
        $files = array_map('basename', glob($dir.'/de/*.php') ?: []);
        $this->assertNotEmpty($files);
        $this->assertSame($files, array_map('basename', glob($dir.'/en/*.php') ?: []));

        foreach ($files as $f) {
            $de = array_keys(Arr::dot(require "$dir/de/$f"));
            $en = array_keys(Arr::dot(require "$dir/en/$f"));
            $this->assertSame([], array_merge(array_diff($de, $en), array_diff($en, $de)), $f);
        }
    }

    #[Test]
    public function manager_is_bound_scoped(): void
    {
        $this->assertTrue(app()->bound(TournamentManager::class));
    }
}
