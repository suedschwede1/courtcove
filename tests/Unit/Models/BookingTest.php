<?php
declare(strict_types=1);
namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_label_uses_owner_name_meta_when_present(): void
    {
        $owner = User::factory()->create(['alias' => 'Real Member']);
        $booking = Booking::factory()->create(['uid' => $owner->uid]);
        $booking->meta()->create(['key' => 'owner-name', 'value' => 'Gast Mustermann']);

        $this->assertSame('Gast Mustermann', $booking->fresh()->load('meta', 'user')->owner_label);
    }

    #[Test]
    public function owner_label_falls_back_to_member_alias(): void
    {
        $owner = User::factory()->create(['alias' => 'Real Member']);
        $booking = Booking::factory()->create(['uid' => $owner->uid]);

        $this->assertSame('Real Member', $booking->fresh()->load('meta', 'user')->owner_label);
    }
}
