<?php

declare(strict_types=1);

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class LoginTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function login_page_renders(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->assertPresent('input[name="email"]')
                ->assertPresent('input[name="password"]')
                ->assertPresent('button[type="submit"]');
        });
    }

    #[Test]
    public function login_with_valid_credentials(): void
    {
        $user = $this->createTestUser('dusk_login');

        try {
            $this->browse(function (Browser $browser) use ($user): void {
                $browser->visit('/login')
                    ->type('email', (string) $user->email)
                    ->type('password', 'password123')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/calendar')
                    ->assertPathIs('/calendar');
            });
        } finally {
            $this->deleteTestUser('dusk_login');
        }
    }

    #[Test]
    public function login_with_wrong_password_stays_on_login_page(): void
    {
        $user = $this->createTestUser('dusk_badlogin');

        try {
            $this->browse(function (Browser $browser) use ($user): void {
                $browser->visit('/login')
                    ->type('email', (string) $user->email)
                    ->type('password', 'wrongpassword')
                    ->click('button[type="submit"]')
                    ->waitFor('input[name="email"]')
                    ->assertPathIs('/login')
                    ->assertInputValue('email', (string) $user->email)
                    ->assertPresent('div.text-sm.text-red-600, p.text-xs.text-red-600');
            });
        } finally {
            $this->deleteTestUser('dusk_badlogin');
        }
    }

    #[Test]
    public function logout_redirects_to_login(): void
    {
        $user = $this->createTestUser('dusk_logout');
        $this->createSquare(alias: 'Court One');

        try {
            $this->browse(function (Browser $browser) use ($user): void {
                $browser->loginAs($user)
                    ->visit('/calendar')
                    ->click('form[action$="/logout"] button[type="submit"]')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login');
            });
        } finally {
            $this->deleteTestUser('dusk_logout');
        }
    }
}
