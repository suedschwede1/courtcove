<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\User;
use App\Services\PlayerExchangeService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The player exchange switch in the header (desktop) and the phone menu: the same opt-in and
 * opt-out as the checkbox in My account, one tap, state visible, switch role.
 */
class ExchangeMenuSwitchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    #[Test]
    public function switching_on_opts_in_with_the_menu_as_source_and_redirects_with_a_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('player-exchange.set', ['state' => 'on']), ['redirect' => '/calendar?date=2026-11-10'])
            ->assertRedirect('/calendar?date=2026-11-10')
            ->assertSessionHas('success', __('booking.messages.player_exchange.switched_on'));

        $this->assertTrue($this->exchange()->isOptedIn($user));
        $this->assertStringEndsWith('|menu|v1', (string) $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function switching_off_opts_out_and_deletes_the_consent_record(): void
    {
        $user = User::factory()->create();
        $this->exchange()->optIn($user, 'account');

        $this->actingAs($user)
            ->post(route('player-exchange.set', ['state' => 'off']), ['redirect' => '/my-bookings'])
            ->assertRedirect('/my-bookings')
            ->assertSessionHas('success', __('booking.messages.player_exchange.switched_off'));

        $this->assertFalse($this->exchange()->isOptedIn($user));
        $this->assertNull($user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function switching_on_twice_keeps_the_first_consent_record(): void
    {
        $user = User::factory()->create();
        $this->exchange()->optIn($user, 'join');
        $record = $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY);

        $this->actingAs($user)->post(route('player-exchange.set', ['state' => 'on']))->assertRedirect();

        $this->assertSame($record, $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function the_redirect_never_leaves_the_app(): void
    {
        $user = User::factory()->create();

        foreach (['https://evil.example/x', '//evil.example', '/\\evil.example', ''] as $target) {
            $this->actingAs($user)
                ->post(route('player-exchange.set', ['state' => 'on']), ['redirect' => $target])
                ->assertRedirect('/calendar');
        }
    }

    #[Test]
    public function guests_cannot_switch_and_unknown_states_do_not_exist(): void
    {
        $this->post('/player-exchange/on')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->post('/player-exchange/maybe')->assertNotFound();
    }

    #[Test]
    public function the_switch_is_a_switch_in_header_and_phone_menu_and_shows_the_state(): void
    {
        $user = User::factory()->create();

        foreach ([false, true] as $on) {
            if ($on) {
                $this->exchange()->optIn($user);
            }
            $html = $this->actingAs($user)->get(route('calendar.index'))->assertOk()->getContent();
            $xp = $this->xpath($html);

            foreach (['bar', 'menu'] as $variant) {
                $buttons = $xp->query('//button[@form="player-exchange-form-'.$variant.'"]');
                $this->assertSame(1, $buttons->length, $variant);
                $button = $buttons->item(0);
                $this->assertSame('switch', $button->getAttribute('role'));
                $this->assertSame($on ? 'true' : 'false', $button->getAttribute('aria-checked'), $variant);
                $this->assertSame(
                    $on ? __('booking.nav.player_exchange_off') : __('booking.nav.player_exchange_on'),
                    $button->getAttribute('aria-label'),
                    $variant,
                );

                $form = $xp->query('//form[@id="player-exchange-form-'.$variant.'"]')->item(0);
                $this->assertSame(
                    route('player-exchange.set', ['state' => $on ? 'off' : 'on']),
                    $form->getAttribute('action'),
                    $variant,
                );
                $this->assertSame(1, $xp->query('//form[@id="player-exchange-form-'.$variant.'"]//input[@name="_token"]')->length);
            }

            // The consent hint sits in the phone menu next to the switch and is announced with it.
            $hint = $xp->query('//*[@id="player-exchange-hint-menu"]')->item(0);
            $this->assertNotNull($hint);
            $this->assertSame(__('booking.nav.player_exchange_hint'), trim($hint->textContent));
            $this->assertSame('player-exchange-hint-menu', $xp->query('//button[@form="player-exchange-form-menu"]')->item(0)->getAttribute('aria-describedby'));
        }
    }

    #[Test]
    public function guests_see_no_switch(): void
    {
        $html = $this->get(route('calendar.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('player-exchange-form-', $html);
    }

    #[Test]
    public function the_account_checkbox_and_the_menu_switch_share_the_service(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('player-exchange.set', ['state' => 'on']))->assertRedirect();
        $this->assertTrue($this->exchange()->isOptedIn($user));

        // The account page shows the same state as ticked.
        $html = $this->actingAs($user)->get(route('account.edit'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="player_exchange"[^>]*checked/', $html);
    }
}
