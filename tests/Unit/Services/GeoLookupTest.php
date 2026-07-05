<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GeoLookup;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Tests\TestCase;

class GeoLookupTest extends TestCase
{
    #[Test]
    public function resolves_the_country_code_for_a_known_ip(): void
    {
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);

        $this->assertSame('AT', (new GeoLookup)->resolveCountry('127.0.0.1'));
    }

    #[Test]
    public function returns_null_when_the_lookup_resolves_nothing(): void
    {
        Location::fake([]);

        $this->assertNull((new GeoLookup)->resolveCountry('127.0.0.1'));
    }

    #[Test]
    public function returns_null_when_the_maxmind_file_is_missing(): void
    {
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);

        $path = (string) config('location.maxmind.local.path');
        $backup = $path.'.bak';
        rename($path, $backup);

        try {
            $this->assertNull((new GeoLookup)->resolveCountry('127.0.0.1'));
        } finally {
            rename($backup, $path);
        }
    }
}
