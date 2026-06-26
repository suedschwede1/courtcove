<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\SquareStatus;
use App\Models\Booking;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\SquareProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquareModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function square_status_is_cast_to_enum(): void
    {
        $square = Square::factory()->create(['status' => 'enabled']);
        $this->assertInstanceOf(SquareStatus::class, $square->status);
        $this->assertSame(SquareStatus::Enabled, $square->status);
    }

    #[Test]
    public function square_has_many_bookings(): void
    {
        $square = Square::factory()->create();
        Booking::factory()->count(2)->create(['sid' => $square->sid]);
        $this->assertCount(2, $square->bookings);
    }

    #[Test]
    public function square_has_many_meta(): void
    {
        $square = Square::factory()->create();
        SquareMeta::factory()->count(3)->create(['sid' => $square->sid]);
        $this->assertCount(3, $square->meta);
    }

    #[Test]
    public function is_bookable_when_enabled(): void
    {
        $square = Square::factory()->create(['status' => 'enabled']);
        $this->assertTrue($square->isBookable());
    }

    #[Test]
    public function is_not_bookable_when_disabled(): void
    {
        $square = Square::factory()->create(['status' => 'disabled']);
        $this->assertFalse($square->isBookable());
    }

    #[Test]
    public function is_not_bookable_when_readonly(): void
    {
        $square = Square::factory()->create(['status' => 'readonly']);
        $this->assertFalse($square->isBookable());
    }

    #[Test]
    public function is_disabled_only_when_status_is_disabled(): void
    {
        $this->assertTrue(Square::factory()->create(['status' => 'disabled'])->isDisabled());
        $this->assertFalse(Square::factory()->create(['status' => 'enabled'])->isDisabled());
        $this->assertFalse(Square::factory()->create(['status' => 'readonly'])->isDisabled());
    }
}
