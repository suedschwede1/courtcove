<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class GateTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    /** @return array<string, array{\Closure(User): void, string}> */
    public static function failingCases(): array
    {
        return [
            'kein Mitglied' => [static fn (User $u) => Member::query()->where('user_id', $u->uid)->delete(), 'no_member'],
            'ruhend' => [static fn (User $u) => Member::query()->where('user_id', $u->uid)->update(['status' => 'ruhend']), 'member_inactive'],
            'ausgetreten' => [static fn (User $u) => Member::query()->where('user_id', $u->uid)->update(['status' => 'ausgetreten']), 'member_inactive'],
            'anonymisiert' => [static fn (User $u) => Member::query()->where('user_id', $u->uid)->update(['anonymized_at' => now()]), 'member_inactive'],
            'gesperrter Benutzer' => [static fn (User $u) => User::query()->whereKey($u->uid)->update(['status' => 'blocked']), 'account_inactive'],
            'keine Nummer' => [static fn (User $u) => $u->meta()->where('key', 'phone')->delete(), 'no_phone'],
            'Festnetz' => [static fn (User $u) => $u->meta()->where('key', 'phone')->update(['value' => '01 0000000']), 'phone_not_mobile'],
            'ungueltig' => [static fn (User $u) => $u->meta()->where('key', 'phone')->update(['value' => 'abc']), 'phone_invalid'],
            'Boerse aus' => [static fn (User $u) => $u->meta()->where('key', 'player-exchange')->delete(), 'exchange_off'],
            'Hauptschalter aus' => [static fn (User $u) => Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']), 'disabled'],
        ];
    }

    #[Test]
    #[DataProvider('failingCases')]
    public function gate_fails_with_the_expected_reason(\Closure $breakIt, string $reason): void
    {
        $user = $this->eligibleUser();
        $breakIt($user);

        $result = $this->consents()->gate($user->fresh());

        $this->assertFalse($result->passed);
        $this->assertSame($reason, $result->reason);
    }

    #[Test]
    public function gate_passes_for_an_eligible_member_and_carries_the_number(): void
    {
        $user = $this->eligibleUser('0660 0000042');

        $result = $this->consents()->gate($user);

        $this->assertTrue($result->passed);
        $this->assertNull($result->reason);
        $this->assertSame('+436600000042', $result->e164);
        $this->assertSame('42', $result->last2);
    }

    #[Test]
    public function the_main_switch_is_checked_first(): void
    {
        $user = User::factory()->create(['status' => 'blocked']);

        $this->assertSame('disabled', $this->consents()->gate($user)->reason);
    }

    #[Test]
    public function admin_test_gate_skips_member_rules(): void
    {
        $user = $this->eligibleUser();
        Member::query()->where('user_id', $user->uid)->delete();
        $user->meta()->where('key', 'player-exchange')->delete();

        $this->assertSame('no_member', $this->consents()->gate($user)->reason);
        $this->assertTrue($this->consents()->gate($user, forAdminTest: true)->passed);

        Member::factory()->create(['user_id' => $user->uid, 'status' => 'ruhend']);
        $this->assertTrue($this->consents()->gate($user, forAdminTest: true)->passed);

        // Main switch, account status and the mobile number still count.
        $user->meta()->where('key', 'phone')->update(['value' => '01 0000000']);
        $this->assertSame('phone_not_mobile', $this->consents()->gate($user, forAdminTest: true)->reason);

        User::query()->whereKey($user->uid)->update(['status' => 'disabled']);
        $this->assertSame('account_inactive', $this->consents()->gate($user->fresh(), forAdminTest: true)->reason);

        Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']);
        $this->assertSame('disabled', $this->consents()->gate($user->fresh(), forAdminTest: true)->reason);
    }
}
