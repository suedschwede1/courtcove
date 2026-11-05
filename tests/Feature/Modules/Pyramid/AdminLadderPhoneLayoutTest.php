<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phone layout of the ladder administration (Admin-Pyramide-Mobil): admin bar with back arrow and eyebrow, compact
 * participant rows with one status action, and the fixed "Teilnehmer anlegen" bar. Desktop keeps the table.
 */
class AdminLadderPhoneLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function page(): array
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Herren 2026']);
        $player = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 2)->firstOrFail();
        $player->update(['label' => 'Lukas Hofer', 'itn' => '5.8']);
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 3)->update(['status' => 'paused']);

        $html = (string) $this->actingAs(User::factory()->status('admin')->create())
            ->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->getContent();

        return [$ladder, $player, $html];
    }

    #[Test]
    public function the_admin_bar_has_a_back_arrow_to_the_overview_and_the_pyramid_eyebrow(): void
    {
        [, , $html] = $this->page();

        $this->assertMatchesRegularExpression('#<header class="ds-admin-bar[^"]*".*?href="'.preg_quote(route('pyramide.admin.index'), '#').'".*?<p class="ds-admin-bar__eyebrow">Forderungspyramide verwalten</p>\s*<p class="ds-admin-bar__title">Herren 2026</p>#s', $html);
    }

    #[Test]
    public function rows_carry_a_compact_phone_line_and_an_edit_link_on_the_name(): void
    {
        [$ladder, $player, $html] = $this->page();
        $editUrl = route('pyramide.admin.ladder', ['ladderId' => $ladder->id]).'?edit='.$player->id.'#pyramid-pe-row-'.$player->id;

        $this->assertStringContainsString('ITN 5,8 · Aktiv', $html);
        $this->assertStringContainsString('· Pausiert', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(htmlspecialchars($editUrl), '#').'" class="[^"]*md:hidden[^"]*"[^>]*>.*?Lukas Hofer.*?</a>#s', $html);
        // The desktop table columns stay; on phones they are hidden.
        $this->assertStringContainsString('>Alter</th>', $html);
    }

    #[Test]
    public function the_phone_bar_opens_the_add_player_form(): void
    {
        [, , $html] = $this->page();

        $this->assertMatchesRegularExpression('#<div class="[^"]*fixed[^"]*md:hidden[^"]*"[^>]*>\s*<a href="\#pyramid-admin-add"[^>]*>.*?Teilnehmer anlegen#s', $html);
        $this->assertStringContainsString('id="pyramid-admin-add"', $html);
    }
}
