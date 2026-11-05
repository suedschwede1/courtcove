<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\MixedTournamentServiceProvider;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MixedProviderTest extends TestCase
{
    #[Test]
    public function translations_load_in_both_locales(): void
    {
        $this->assertSame('Mixed-/Fun-Turnier', __('mixed::common.area', [], 'de'));
        $this->assertSame('Mixed/fun tournament', __('mixed::common.area', [], 'en'));
    }

    #[Test]
    public function de_and_en_have_the_same_keys(): void
    {
        foreach (['common', 'messages', 'info', 'print'] as $f) {
            $de = Arr::dot(require app_path("Modules/MixedTournament/resources/lang/de/$f.php"));
            $en = Arr::dot(require app_path("Modules/MixedTournament/resources/lang/en/$f.php"));
            $this->assertSame(array_keys($de), array_keys($en), $f);
        }
    }

    #[Test]
    public function no_ascii_transliterations_in_german_texts(): void
    {
        $words = ['naechste', 'Auffueller', 'waehlen', 'Plaetze', 'fuer', 'moeglich', 'koennen', 'muessen',
            'geloescht', 'bestaetigt', 'hinzugefuegt', 'ergaenzt', 'Ungueltig', 'abschliessen'];
        $pattern = '/\b\w*('.implode('|', array_map('preg_quote', $words)).')\w*\b/iu';

        foreach (glob(app_path('Modules/MixedTournament/resources/lang/de/*.php')) ?: [] as $file) {
            $this->assertSame(0, preg_match($pattern, (string) file_get_contents($file), $m), basename($file).': '.($m[0] ?? ''));
        }
    }

    #[Test]
    public function provider_is_registered(): void
    {
        $this->assertArrayHasKey(MixedTournamentServiceProvider::class, app()->getLoadedProviders());
    }
}
