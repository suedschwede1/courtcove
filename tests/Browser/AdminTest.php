<?php

declare(strict_types=1);

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class AdminTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function admin_can_access_admin_area(): void
    {
        $admin = $this->createAdminUser('dusk_admin');

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)
                    ->visit('/admin')
                    ->assertPathIs('/admin')
                    ->assertPresent('.ui-grid-4');
            });
        } finally {
            $this->deleteTestUser('dusk_admin');
        }
    }

    #[Test]
    public function regular_user_does_not_see_admin_navigation(): void
    {
        $user = $this->createTestUser('dusk_noadmin');
        $this->createSquare(alias: 'Court One');

        try {
            $this->browse(function (Browser $browser) use ($user): void {
                $browser->loginAs($user)
                    ->visit('/calendar')
                    ->assertDontSeeLink('Administration');
            });
        } finally {
            $this->deleteTestUser('dusk_noadmin');
        }
    }

    #[Test]
    public function admin_users_list_renders_after_search(): void
    {
        $admin = $this->createAdminUser('dusk_ulist');

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)
                    ->visit('/admin/users?q=dusk_ulist')
                    ->assertPresent('table.ui-table')
                    ->assertSee('dusk_ulist');
            });
        } finally {
            $this->deleteTestUser('dusk_ulist');
        }
    }

    #[Test]
    public function bulk_user_action_blocks_the_selected_user(): void
    {
        $admin = $this->createAdminUser('dusk_bulk_adm');
        $target = $this->createTestUser('dusk_bulk_tgt');

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->loginAs($admin)
                    ->visit('/admin/users?q=dusk_bulk_tgt')
                    ->script([
                        'window.confirm = () => true;',
                        'document.querySelector("tbody input[type=checkbox]")?.click();',
                    ]);

                $browser->waitFor('button[name="action"][value="blocked"]', 3)
                    ->click('button[name="action"][value="blocked"]');
            });

            $this->assertSame('blocked', $target->fresh()?->status);
        } finally {
            $this->deleteTestUser('dusk_bulk_adm');
            $this->deleteTestUser('dusk_bulk_tgt');
        }
    }
}
