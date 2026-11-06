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
        $player->update(['label' => 'Berta Muster', 'itn' => '5.8']);
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 3)->update(['status' => 'paused']);

        $html = (string) $this->actingAs(User::factory()->status('admin')->create())
            ->get('/pyramide/admin/ladder/'.$ladder->id)
            ->assertOk()
            ->getContent();

        return [$ladder, $player, $html];
    }

    #[Test]
    public function the_phone_bar_has_a_back_arrow_to_the_overview_the_pyramid_eyebrow_and_the_menu(): void
    {
        [, , $html] = $this->page();

        $this->assertMatchesRegularExpression('#<header class="ds-phone-bar[^"]*"[^>]*>.*?href="'.preg_quote(route('pyramide.admin.index'), '#').'".*?<p class="ds-phone-bar__eyebrow">Forderungspyramide verwalten</p>\s*<p class="ds-phone-bar__title">Herren 2026</p>.*?aria-controls="admin-nav".*?</header>#s', $html);
    }

    #[Test]
    public function rows_carry_a_compact_phone_line_and_an_edit_link_on_the_name(): void
    {
        [$ladder, $player, $html] = $this->page();
        $editUrl = route('pyramide.admin.ladder', ['ladderId' => $ladder->id]).'?edit='.$player->id.'#pyramid-pe-row-'.$player->id;

        $this->assertStringContainsString('ITN 5,8 · Aktiv', $html);
        $this->assertStringContainsString('· Pausiert', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(htmlspecialchars($editUrl), '#').'" class="[^"]*md:hidden[^"]*"[^>]*>.*?Berta Muster.*?</a>#s', $html);
        // The desktop table columns stay; on phones they are hidden.
        $this->assertStringContainsString('>Alter</th>', $html);
    }

    #[Test]
    public function the_public_view_stays_reachable_on_phones(): void
    {
        [$ladder, , $html] = $this->page();

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('pyramide.ladder', ['ladderId' => $ladder->id]), '#').'" class="ds-btn ds-btn--text self-start md:hidden"><span>Öffentliche Ansicht</span></a>#', $html);
    }

    #[Test]
    public function the_phone_bar_opens_the_add_player_form(): void
    {
        [, , $html] = $this->page();

        $this->assertMatchesRegularExpression('#<div class="ds-phone-actionbar">\s*<a href="\#pyramid-admin-add"[^>]*>.*?Teilnehmer anlegen#s', $html);
        // The spacer reserves the bar's height (incl. the safe area, see ds.css) so nothing hides behind it.
        $this->assertStringContainsString('<div class="ds-phone-actionbar-spacer" aria-hidden="true"></div>', $html);
        $this->assertStringContainsString('id="pyramid-admin-add"', $html);
    }
}
