<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_view_database_status_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertViewIs('admin.database.index')
            ->assertSee('2026_06_26_073602_create_bs_users_table');
    }

    #[Test]
    public function regular_member_cannot_access_database_status_page(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/database')
            ->assertForbidden();
    }

    #[Test]
    public function admin_must_confirm_before_running_pending_migrations(): void
    {
        DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();

        $this->from('/admin/database')
            ->actingAs($this->admin())
            ->post('/admin/database/migrate', ['confirmation' => 'wrong'])
            ->assertRedirect('/admin/database')
            ->assertSessionHasErrors('confirmation')
            ->assertSessionHasInput('confirmation', 'wrong');

        $this->assertDatabaseMissing('migrations', ['migration' => '2026_06_28_155630_create_jobs_table']);
    }

    #[Test]
    public function admin_can_run_pending_migrations_with_valid_confirmation(): void
    {
        DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();

        $this->actingAs($this->admin())
            ->post('/admin/database/migrate', ['confirmation' => 'MIGRATE'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('migrations', ['migration' => '2026_06_28_155630_create_jobs_table']);
    }

    #[Test]
    public function regular_member_cannot_run_migrations(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->post('/admin/database/migrate', ['confirmation' => 'MIGRATE'])
            ->assertForbidden();
    }

    #[Test]
    public function database_status_page_shows_a_missing_column_when_schema_drifts(): void
    {
        $admin = $this->admin();

        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $this->actingAs($admin)
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('bs_users')
            ->assertSee('last_ip');
    }
}
