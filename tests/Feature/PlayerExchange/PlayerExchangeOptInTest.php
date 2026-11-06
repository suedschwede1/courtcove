<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerExchangeOptInTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function opt_in_and_out_toggle_the_flag(): void
    {
        $service = app(PlayerExchangeService::class);
        $user = User::factory()->create();

        $this->assertFalse($service->isOptedIn($user));

        $service->optIn($user);
        $this->assertTrue($service->isOptedIn($user));
        $this->assertSame('1', $user->fresh()->getMeta('player-exchange'));

        $service->optOut($user);
        $this->assertFalse($service->isOptedIn($user));
        $this->assertNull($user->fresh()->getMeta('player-exchange'));
    }

    #[Test]
    public function recipients_exclude_blocked_users_users_without_email_and_the_excluded_user(): void
    {
        $service = app(PlayerExchangeService::class);

        $included = User::factory()->create(['email' => 'anna@example.test']);
        $excluded = User::factory()->create(['email' => 'bert@example.test']);
        $blocked = User::factory()->create(['email' => 'carl@example.test', 'status' => 'blocked']);
        $noEmail = User::factory()->create(['email' => '']);
        $notOptedIn = User::factory()->create(['email' => 'dora@example.test']);

        foreach ([$included, $excluded, $blocked, $noEmail] as $user) {
            $service->optIn($user);
        }

        $recipients = $service->recipients($excluded);

        $this->assertSame([$included->uid], $recipients->pluck('uid')->all());
        $this->assertNotContains($notOptedIn->uid, $recipients->pluck('uid')->all());
        $this->assertCount(2, $service->recipients());
    }
}
