<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function the_product_name_has_one_central_config_entry(): void
    {
        $this->assertSame('CourtCove', config('booking.product_name'));
        $this->assertSame('Your club. Your court. Your game.', config('booking.product_tagline'));
    }

    #[Test]
    public function the_product_name_does_not_replace_the_club_name(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Powered by CourtCove')
            ->assertSee('ds-auth__club', false)
            ->assertSee(config('booking.name'));
    }

    #[Test]
    public function composer_metadata_uses_the_product_name(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertSame('courtcove/courtcove', $composer['name']);
        $this->assertStringStartsWith('CourtCove', $composer['description']);
    }
}
