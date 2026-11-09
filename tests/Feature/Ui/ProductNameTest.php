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
            ->assertSee('ds-auth__product-name', false)
            ->assertSee(config('booking.product_name'))
            ->assertSee(config('booking.product_tagline'))
            ->assertSee('<title>', false)
            ->assertSee('ds-auth__club', false)
            ->assertSee(config('booking.name'));
    }

    #[Test]
    public function every_sign_in_page_names_the_product_in_the_tab_title(): void
    {
        foreach ([route('login', absolute: false), route('register', absolute: false), route('password.forgot', absolute: false)] as $url) {
            $html = $this->get($url)->getContent();
            preg_match('#<title>(.*?)</title>#s', $html, $m);
            $this->assertStringContainsString(' – CourtCove', $m[1] ?? '', $url);
        }
    }

    #[Test]
    public function composer_metadata_uses_the_product_name(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertSame('courtcove/courtcove', $composer['name']);
        $this->assertStringStartsWith('CourtCove', $composer['description']);
    }
}
