<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerExchangeAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function account_page_shows_the_exchange_option_and_saves_it(): void
    {
        $service = app(PlayerExchangeService::class);
        $user = User::factory()->create(['alias' => 'Anna', 'email' => 'anna@example.test']);

        $this->actingAs($user)->get(route('account.edit'))
            ->assertOk()
            ->assertSee('name="player_exchange"', false);

        $this->actingAs($user)->put(route('account.update'), [
            'alias' => 'Anna',
            'email' => 'anna@example.test',
            'player_exchange' => '1',
        ])->assertRedirect(route('account.edit'));
        $this->assertTrue($service->isOptedIn($user));

        $this->actingAs($user)->get(route('account.edit'))->assertSee('checked', false);

        $this->actingAs($user)->put(route('account.update'), [
            'alias' => 'Anna',
            'email' => 'anna@example.test',
        ])->assertRedirect(route('account.edit'));
        $this->assertFalse($service->isOptedIn($user));
    }

    #[Test]
    public function after_a_validation_error_the_box_shows_what_was_submitted(): void
    {
        $service = app(PlayerExchangeService::class);
        $user = User::factory()->create(['alias' => 'Anna', 'email' => 'anna@example.test']);
        $service->optIn($user);
        $box = '/<input type="checkbox" name="player_exchange"[^>]*>/';

        // Unticked, but the e-mail is invalid: the box stays unticked, the membership unchanged.
        $this->actingAs($user)->from(route('account.edit'))->put(route('account.update'), [
            'alias' => 'Anna',
            'email' => 'not-an-email',
        ])->assertRedirect(route('account.edit'))->assertSessionHasErrors('email');
        $html = (string) $this->actingAs($user)->get(route('account.edit'))->getContent();
        $this->assertSame(1, preg_match($box, $html, $m));
        $this->assertStringNotContainsString('checked', $m[0]);
        $this->assertTrue($service->isOptedIn($user));

        // Without any submitted profile form the stored choice shows.
        $html = (string) $this->actingAs($user)->get(route('account.edit'))->getContent();
        $this->assertSame(1, preg_match($box, $html, $m));
        $this->assertStringContainsString('checked', $m[0]);
    }

    #[Test]
    public function signed_unsubscribe_link_opts_out_without_login(): void
    {
        $service = app(PlayerExchangeService::class);
        $user = User::factory()->create();
        $service->optIn($user);

        $this->get($service->unsubscribeUrl($user))
            ->assertOk()
            ->assertSee(__('booking.account.player_exchange_unsubscribed'));

        $this->assertFalse($service->isOptedIn($user));
    }

    #[Test]
    public function tampered_unsubscribe_link_is_forbidden(): void
    {
        $service = app(PlayerExchangeService::class);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service->optIn($user);
        $service->optIn($other);

        $url = str_replace('/'.$user->uid, '/'.$other->uid, $service->unsubscribeUrl($user));

        $this->get($url)->assertForbidden();
        $this->assertTrue($service->isOptedIn($other));
        $this->assertTrue($service->isOptedIn($user));
    }
}
