<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OptionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function get_value_returns_stored_value(): void
    {
        Option::create(['key' => 'site_name', 'value' => 'Tennis-Booking']);
        $this->assertEquals('Tennis-Booking', Option::getValue('site_name'));
    }

    #[Test]
    public function get_value_returns_default_when_missing(): void
    {
        $this->assertEquals('fallback', Option::getValue('nonexistent', 'fallback'));
    }

    #[Test]
    public function get_value_returns_null_by_default(): void
    {
        $this->assertNull(Option::getValue('nonexistent'));
    }

    #[Test]
    public function get_value_prefers_locale_specific_row(): void
    {
        Option::create(['key' => 'client.name.full', 'value' => 'Default', 'locale' => null]);
        Option::create(['key' => 'client.name.full', 'value' => 'Example', 'locale' => 'de-DE']);

        $this->assertEquals('Example', Option::getValue('client.name.full', null, 'de-DE'));
        $this->assertEquals('Default', Option::getValue('client.name.full'));
    }
}
