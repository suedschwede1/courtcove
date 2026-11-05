<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The full-page edit form posts the players as player_name_2..4 (not `mitspieler`),
 * so saving must work with exactly those field names.
 */
class AdminBookingEditFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: User, 1: Booking, 2: Square} */
    private function seedBooking(int $quantity): array
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $admin->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => $quantity,
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        return [$admin, $booking, $square];
    }

    /** Exactly the fields the full-page (non-create) form submits. */
    private function formPayload(Square $square, int $quantity, array $overrides = []): array
    {
        return array_merge([
            'booked_for' => 'Gast Beispiel',
            'sid' => $square->sid,
            'date' => '2026-07-03',
            'date_end' => '2026-07-03',
            'repeat_type' => 'once',
            'time_start' => '10:00',
            'time_end' => '11:00',
            'quantity' => $quantity,
            'status' => 'single',
            'player_name_2' => 'Partner Eins',
            'player_name_3' => '',
            'player_name_4' => '',
            'admin_note' => '',
        ], $overrides);
    }

    #[Test]
    public function full_page_edit_form_renders_the_player_name_fields(): void
    {
        [$admin, $booking] = $this->seedBooking(2);

        $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit")
            ->assertOk()
            ->assertSee('name="player_name_2"', false)
            ->assertDontSee('name="mitspieler"', false);
    }

    #[Test]
    public function full_page_edit_form_saves_a_singles_booking(): void
    {
        [$admin, $booking, $square] = $this->seedBooking(2);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->formPayload($square, 2))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertSame(['Partner Eins'], $booking->fresh(['meta'])->player_names);
    }

    #[Test]
    public function full_page_edit_form_saves_all_three_doubles_players_separately(): void
    {
        [$admin, $booking, $square] = $this->seedBooking(4);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->formPayload($square, 4, [
            'player_name_3' => 'Partner Zwei',
            'player_name_4' => 'Partner Drei',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Partner Eins', 'Partner Zwei', 'Partner Drei'],
            $booking->fresh(['meta'])->player_names,
        );
    }

    #[Test]
    public function edit_without_any_player_is_still_rejected(): void
    {
        [$admin, $booking, $square] = $this->seedBooking(2);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->formPayload($square, 2, [
            'player_name_2' => '',
        ]))->assertSessionHasErrors();
    }
}
