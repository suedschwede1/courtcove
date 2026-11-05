<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchCardComponentTest extends TestCase
{
    /** @param  array<string, mixed>  $props */
    private function render(array $props): string
    {
        return Blade::render('<x-tournament-base::match-card :context="$context" :badge="$badge" :players="$players" :score="$score" :meta="$meta" :action="$action" />', $props + [
            'context' => 'Gruppe C · Spiel 6',
            'badge' => ['group' => 'good', 'label' => 'gespielt'],
            'players' => [['name' => 'Demo Spieler 01', 'winner' => true], ['name' => 'Demo Spieler 02', 'winner' => false]],
            'score' => ['6 6', '2 1'],
            'meta' => 'Di, 3. Nov · Alpha',
            'action' => null,
        ]);
    }

    #[Test]
    public function winner_is_bold_and_score_tabular(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('Gruppe C · Spiel 6', $html);
        $this->assertStringContainsString('ds-badge--good', $html);
        $this->assertMatchesRegularExpression('#<span class="[^"]*font-bold[^"]*">Demo Spieler 01</span>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<span class="[^"]*font-bold[^"]*">Demo Spieler 02</span>#', $html);
        $this->assertMatchesRegularExpression('#<span class="[^"]*tabular-nums[^"]*">6 6</span>#', $html);
        $this->assertStringContainsString('2 1', $html);
        $this->assertStringContainsString('Di, 3. Nov · Alpha', $html);
        $this->assertStringNotContainsString('ds-btn', $html, 'played: no action');
        $this->assertStringContainsString('<article', $html);
    }

    #[Test]
    public function no_date_renders_noch_kein_termin(): void
    {
        $html = $this->render([
            'badge' => ['group' => 'attention', 'label' => 'ohne Termin'],
            'players' => [['name' => 'Demo Spieler 01', 'winner' => false], ['name' => 'Demo Spieler 02', 'winner' => false]],
            'score' => ['', ''],
            'meta' => null,
        ]);

        $this->assertStringContainsString('noch kein Termin', $html);
        $this->assertStringContainsString('ds-badge--attention', $html);
        $this->assertStringNotContainsString('font-bold', $html);
    }

    #[Test]
    public function action_renders_as_a_button_link_in_its_variant(): void
    {
        $html = $this->render(['action' => ['label' => 'Ergebnis eintragen', 'url' => '/tournament/match/7', 'variant' => 'secondary']]);

        $this->assertMatchesRegularExpression('#<a href="/tournament/match/7" class="ds-btn ds-btn--secondary ds-btn--block">#', $html);
        $this->assertStringContainsString('Ergebnis eintragen', $html);
    }

    #[Test]
    public function names_and_labels_are_escaped(): void
    {
        $html = $this->render(['players' => [['name' => '<b>x</b>', 'winner' => false], ['name' => 'B', 'winner' => false]]]);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    #[Test]
    public function component_passes_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/TournamentBase/resources/views/components'), UiRules::all()))->run());
    }
}
