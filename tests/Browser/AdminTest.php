<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class AdminTest extends DuskTestCase
{
    use CreatesTestData;

    public function test_admin_can_access_admin_area(): void
    {
        $admin = $this->createAdminUser('dusk_admin');

        try {
            $this->browse(function (Browser $b) use ($admin): void {
                $b->loginAs($admin)
                  ->visit('/admin')
                  ->assertPathBeginsWith('/admin');
            });
        } finally {
            $this->deleteTestUser('dusk_admin');
        }
    }

    public function test_regular_user_cannot_access_admin_area(): void
    {
        $user = $this->createTestUser('dusk_noadmin');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->loginAs($user)
                  ->visit('/admin')
                  ->assertPathIsNot('/admin/dashboard');
            });
        } finally {
            $this->deleteTestUser('dusk_noadmin');
        }
    }

    public function test_admin_users_list_renders(): void
    {
        $admin = $this->createAdminUser('dusk_ulist');

        try {
            $this->browse(function (Browser $b) use ($admin): void {
                $b->loginAs($admin)
                  ->visit('/admin/users')
                  ->assertPresent('table')
                  ->assertPresent('input[type="checkbox"]');
            });
        } finally {
            $this->deleteTestUser('dusk_ulist');
        }
    }

    public function test_bulk_user_action_blocks_user(): void
    {
        $admin  = $this->createAdminUser('dusk_bulk_adm');
        $target = $this->createTestUser('dusk_bulk_tgt');

        try {
            $this->browse(function (Browser $b) use ($admin, $target): void {
                $b->loginAs($admin)
                  ->visit('/admin/users')
                  ->check('uids[]')
                  ->waitFor('[data-bulk-action]', 3)
                  ->press('Sperren')
                  ->waitForText('aktualisiert')
                  ->assertSee('aktualisiert');
            });
        } finally {
            $this->deleteTestUser('dusk_bulk_adm');
            $this->deleteTestUser('dusk_bulk_tgt');
        }
    }
}
