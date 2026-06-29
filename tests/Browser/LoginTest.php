<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class LoginTest extends DuskTestCase
{
    use CreatesTestData;

    public function test_login_page_renders(): void
    {
        $this->browse(function (Browser $b): void {
            $b->visit('/login')
              ->assertSee('Anmelden')
              ->assertPresent('input[name="email"]')
              ->assertPresent('input[name="password"]');
        });
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = $this->createTestUser('dusk_login');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->visit('/login')
                  ->type('email', $user->email)
                  ->type('password', 'password123')
                  ->press('Anmelden')
                  ->waitForLocation('/calendar')
                  ->assertPathIs('/calendar');
            });
        } finally {
            $this->deleteTestUser('dusk_login');
        }
    }

    public function test_login_with_wrong_password_shows_error(): void
    {
        $user = $this->createTestUser('dusk_badlogin');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->visit('/login')
                  ->type('email', $user->email)
                  ->type('password', 'wrongpassword')
                  ->press('Anmelden')
                  ->waitForText('ungültig')
                  ->assertSee('ungültig');
            });
        } finally {
            $this->deleteTestUser('dusk_badlogin');
        }
    }

    public function test_logout_redirects_to_login(): void
    {
        $user = $this->createTestUser('dusk_logout');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->loginAs($user)
                  ->visit('/calendar')
                  ->clickLink('Abmelden')
                  ->waitForLocation('/login')
                  ->assertPathIs('/login');
            });
        } finally {
            $this->deleteTestUser('dusk_logout');
        }
    }
}
