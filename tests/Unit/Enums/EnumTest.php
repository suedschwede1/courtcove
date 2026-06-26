<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Enums\CouponType;
use App\Enums\EventStatus;
use App\Enums\ProductType;
use App\Enums\SquareStatus;
use App\Enums\UserStatus;
use App\Enums\Visibility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnumTest extends TestCase
{
    #[Test]
    public function booking_status_has_expected_cases(): void
    {
        $this->assertSame('enabled', BookingStatus::Enabled->value);
        $this->assertSame('disabled', BookingStatus::Disabled->value);
    }

    #[Test]
    public function billing_status_has_expected_cases(): void
    {
        $this->assertSame('pending', BillingStatus::Pending->value);
        $this->assertSame('paid', BillingStatus::Paid->value);
        $this->assertSame('cancelled', BillingStatus::Cancelled->value);
        $this->assertSame('uncollectable', BillingStatus::Uncollectable->value);
    }

    #[Test]
    public function square_status_has_expected_cases(): void
    {
        $this->assertSame('enabled', SquareStatus::Enabled->value);
        $this->assertSame('disabled', SquareStatus::Disabled->value);
        $this->assertSame('readonly', SquareStatus::Readonly->value);
    }

    #[Test]
    public function visibility_has_expected_cases(): void
    {
        $this->assertSame('public', Visibility::Public->value);
        $this->assertSame('private', Visibility::Private->value);
    }

    #[Test]
    public function user_status_has_expected_cases(): void
    {
        $this->assertSame('enabled', UserStatus::Enabled->value);
        $this->assertSame('disabled', UserStatus::Disabled->value);
    }

    #[Test]
    public function event_status_has_expected_cases(): void
    {
        $this->assertSame('enabled', EventStatus::Enabled->value);
        $this->assertSame('disabled', EventStatus::Disabled->value);
    }

    #[Test]
    public function coupon_type_has_expected_cases(): void
    {
        $this->assertSame('percent', CouponType::Percent->value);
        $this->assertSame('fixed', CouponType::Fixed->value);
    }

    #[Test]
    public function product_type_has_expected_cases(): void
    {
        $this->assertSame('single', ProductType::Single->value);
        $this->assertSame('subscription', ProductType::Subscription->value);
    }

    #[Test]
    public function booking_status_from_string_works(): void
    {
        $this->assertSame(BookingStatus::Enabled, BookingStatus::from('enabled'));
    }

    #[Test]
    public function all_enums_implement_string_backed(): void
    {
        foreach ([BookingStatus::Enabled, BillingStatus::Pending, SquareStatus::Enabled,
                  Visibility::Public, UserStatus::Enabled, EventStatus::Enabled,
                  CouponType::Percent, ProductType::Single] as $case) {
            $this->assertIsString($case->value);
        }
    }
}
