<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecordPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests pin the public behaviour (config off); TournamentLoginRequiredTest covers the default.
        config(['tournament-base.require_login' => false]);
    }

    #[Test]
    public function anonymous_user_sees_a_committed_draw_without_secrets(): void
    {
        $draw = Draw::factory()->create();

        $this->get("draw/record/{$draw->slug}")
            ->assertOk()
            ->assertSee('Losprotokoll', false)
            ->assertSee($draw->commitment)
            ->assertSee('Noch nicht gezogen')
            ->assertDontSee($draw->server_seed)
            ->assertDontSee('Nachrechnen');
    }

    #[Test]
    public function revealed_draw_shows_result_and_seeds(): void
    {
        $draw = Draw::factory()->executed()->create();

        $response = $this->get("draw/record/{$draw->slug}")->assertOk();

        foreach (json_decode($draw->result, true) as $slot => $entry) {
            $response->assertSee((string) $slot)->assertSee((string) $entry);
        }
        $response->assertSee($draw->final_seed)
            ->assertSee($draw->beacon_value)
            ->assertSee($draw->server_seed)
            ->assertDontSee('Noch nicht gezogen');
    }

    #[Test]
    public function picking_token_draw_stays_hidden(): void
    {
        $draw = Draw::factory()->token()->executed()->create();

        $this->get("draw/record/{$draw->slug}")
            ->assertOk()
            ->assertDontSee($draw->server_seed)
            ->assertDontSee($draw->final_seed)
            ->assertDontSee($draw->beacon_value)
            ->assertDontSee('Nachrechnen')
            ->assertSee('Noch nicht gezogen');
    }

    #[Test]
    public function closed_token_draw_is_revealed(): void
    {
        $draw = Draw::factory()->token()->closed()->create();

        $this->get("draw/record/{$draw->slug}")
            ->assertOk()
            ->assertSee($draw->server_seed)
            ->assertSee($draw->final_seed)
            ->assertDontSee('Noch nicht gezogen');
    }

    #[Test]
    public function string_lots_are_not_cast_to_zero(): void
    {
        $draw = Draw::factory()
            ->withPayload(['entries' => ['Q1', 'Q2', 'Q3'], 'slots' => ['A1', 'A2', 'A3'], 'rule' => 'r'])
            ->executed()
            ->create();

        $html = $this->get("draw/record/{$draw->slug}")->assertOk()->assertSee('Q1')->getContent();

        $this->assertDoesNotMatchRegularExpression('#<td[^>]*data-label="Los"[^>]*>\s*0\s*</td>#', $html);
        foreach (json_decode($draw->result, true) as $entry) {
            $this->assertMatchesRegularExpression('#<td[^>]*data-label="Los"[^>]*>\s*'.preg_quote((string) $entry, '#').'\s*</td>#', $html);
        }
    }

    #[Test]
    public function discarded_draw_is_flagged(): void
    {
        $draw = Draw::factory()->discarded('Falsche Teilnehmerliste')->create();

        $this->get("draw/record/{$draw->slug}")
            ->assertOk()
            ->assertSee('Diese Ziehung wurde verworfen.')
            ->assertSee('Falsche Teilnehmerliste');
    }

    #[Test]
    public function unknown_slug_renders_404(): void
    {
        $this->get('draw/record/zzzz9999')
            ->assertNotFound()
            ->assertSee('Protokoll nicht gefunden');
    }

    #[Test]
    public function page_sends_no_cache_headers(): void
    {
        $draw = Draw::factory()->create();

        foreach ([$draw->slug, 'zzzz9999'] as $slug) {
            $response = $this->get("draw/record/{$slug}");
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
            $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
            $this->assertSame(0, $response->baseResponse->getMaxAge());
            $this->assertSame('no-cache', $response->headers->get('Pragma'));
        }
    }

    #[Test]
    public function json_link_honours_the_booking_base_path(): void
    {
        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);
        $draw = Draw::factory()->executed()->create();

        $html = $this->get("/booking/draw/record/{$draw->slug}")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="(https?://[^/"]+)?/booking/draw/record/'.preg_quote($draw->slug, '#').'/json"#', $html);
    }

    #[Test]
    public function english_locale_is_translated(): void
    {
        $draw = Draw::factory()->create();
        app()->setLocale('en');

        $this->get("draw/record/{$draw->slug}")
            ->assertOk()
            ->assertSee('Draw record')
            ->assertSee('Not drawn yet');
    }
}
