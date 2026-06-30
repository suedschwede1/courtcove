<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Option;
use App\Services\PeakLimitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeakLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private PeakLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PeakLimitService;
    }

    #[Test]
    public function is_disabled_by_default(): void
    {
        $this->assertFalse($this->service->isEnabled());
    }

    #[Test]
    public function is_enabled_when_option_set(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $this->assertTrue($this->service->isEnabled());
    }

    #[Test]
    public function morning_window_is_peak(): void
    {
        $this->assertTrue($this->service->isPeakTime(Carbon::today()->setTime(8, 0)));
        $this->assertTrue($this->service->isPeakTime(Carbon::today()->setTime(11, 30)));
    }

    #[Test]
    public function morning_window_end_is_not_peak(): void
    {
        $this->assertFalse($this->service->isPeakTime(Carbon::today()->setTime(12, 0)));
    }

    #[Test]
    public function evening_window_is_peak(): void
    {
        $this->assertTrue($this->service->isPeakTime(Carbon::today()->setTime(17, 0)));
        $this->assertTrue($this->service->isPeakTime(Carbon::today()->setTime(20, 0)));
    }

    #[Test]
    public function evening_window_end_is_not_peak(): void
    {
        $this->assertFalse($this->service->isPeakTime(Carbon::today()->setTime(21, 0)));
    }

    #[Test]
    public function midday_off_peak_is_not_peak(): void
    {
        $this->assertFalse($this->service->isPeakTime(Carbon::today()->setTime(13, 0)));
    }

    #[Test]
    public function custom_windows_from_options_are_respected(): void
    {
        Option::create(['key' => 'peak_limit.window_1_start', 'value' => '09:00', 'locale' => null]);
        Option::create(['key' => 'peak_limit.window_1_end',   'value' => '11:00', 'locale' => null]);

        $this->assertTrue($this->service->isPeakTime(Carbon::today()->setTime(10, 0)));
        $this->assertFalse($this->service->isPeakTime(Carbon::today()->setTime(8, 0)));
    }
}
