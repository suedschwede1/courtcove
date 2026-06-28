<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_access_account_pages(): void
    {
        $this->get('/meine-buchungen')->assertRedirect('/login');
        $this->get('/mein-konto')->assertRedirect('/login');
    }

    #[Test]
    public function my_bookings_shows_only_own_active_bookings(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['alias' => 'Fremd']);
        $sq = Square::factory()->create();
        $mine = Booking::factory()->create(['uid' => $me->uid, 'sid' => $sq->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $mine->bid, 'date' => Carbon::today()->toDateString()]);
        $theirs = Booking::factory()->create(['uid' => $other->uid, 'sid' => $sq->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $theirs->bid, 'date' => Carbon::today()->toDateString()]);
        $cancelled = Booking::factory()->create(['uid' => $me->uid, 'sid' => $sq->sid, 'status' => 'cancelled']);

        $resp = $this->actingAs($me)->get('/meine-buchungen')->assertOk();
        $bookings = $resp->viewData('bookings');
        $ids = collect($bookings)->pluck('bid')->all();
        $this->assertContains($mine->bid, $ids);
        $this->assertNotContains($theirs->bid, $ids);
        $this->assertNotContains($cancelled->bid, $ids);
    }

    #[Test]
    public function account_edit_shows_profile(): void
    {
        $u = User::factory()->create(['alias' => 'Max Mustermann']);
        $u->setMeta('phone', '+43123');
        $this->actingAs($u)->get('/mein-konto')->assertOk()->assertSee('Max Mustermann')->assertSee('+43123');
    }

    #[Test]
    public function account_update_saves_alias_email_and_profile(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->put('/mein-konto', [
            'alias' => 'Neuer Name', 'email' => 'neu@example.com',
            'firstname' => 'Neu', 'phone' => '+4399', 'city' => 'Graz',
        ])->assertRedirect(route('account.edit'));

        $u->refresh();
        $this->assertSame('Neuer Name', $u->alias);
        $this->assertSame('neu@example.com', $u->email);
        $this->assertSame('Neu', $u->getMeta('firstname'));
        $this->assertSame('Graz', $u->getMeta('city'));
    }

    #[Test]
    public function password_change_requires_correct_current_password(): void
    {
        $u = User::factory()->create(['pw' => Hash::make('altpass1')]);

        // wrong current password -> error, unchanged
        $this->actingAs($u)->put('/mein-konto/passwort', [
            'current_password' => 'falsch', 'password' => 'neupass1', 'password_confirmation' => 'neupass1',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('altpass1', $u->fresh()->pw));

        // correct current password -> changed
        $this->actingAs($u)->put('/mein-konto/passwort', [
            'current_password' => 'altpass1', 'password' => 'neupass1', 'password_confirmation' => 'neupass1',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('neupass1', $u->fresh()->pw));
    }
}
