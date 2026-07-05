<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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
    public function migrate_flashes_the_artisan_output_on_success(): void
    {
        DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();

        $this->actingAs($this->admin())
            ->post('/admin/database/migrate', ['confirmation' => 'MIGRATE'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success')
            ->assertSessionHas('output', fn (string $output): bool => str_contains($output, '2026_06_28_155630_create_jobs_table'));
    }

    #[Test]
    public function migrate_failure_shows_the_exception_message(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('SQLSTATE boom'));
        Artisan::shouldReceive('output')->andReturn('');

        $this->actingAs($this->admin())
            ->post('/admin/database/migrate', ['confirmation' => 'MIGRATE'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'SQLSTATE boom'));
    }

    #[Test]
    public function database_page_renders_flashed_output(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['output' => 'OUTPUT-ZEILE-42'])
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('OUTPUT-ZEILE-42');
    }

    #[Test]
    public function admin_can_repair_a_table_with_a_missing_column(): void
    {
        $admin = $this->admin();

        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $this->actingAs($admin)
            ->post('/admin/database/repair/bs_users')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success')
            ->assertSessionHas('output', fn (string $output): bool => str_contains($output, 'last_ip'));

        $this->assertTrue(Schema::hasColumn('bs_users', 'last_ip'));
    }

    #[Test]
    public function admin_can_repair_a_missing_table(): void
    {
        Schema::drop('bs_events_meta');

        $this->actingAs($this->admin())
            ->post('/admin/database/repair/bs_events_meta')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success');

        $this->assertTrue(Schema::hasTable('bs_events_meta'));
    }

    #[Test]
    public function repairing_a_healthy_table_reports_nothing_to_do(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/repair/bs_users')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success', __('booking.admin.database.repair_nothing', ['table' => 'bs_users']));
    }

    #[Test]
    public function repairing_an_unknown_table_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/repair/migrations')
            ->assertNotFound();
    }

    #[Test]
    public function regular_member_cannot_repair_tables(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->post('/admin/database/repair/bs_users')
            ->assertForbidden();
    }

    #[Test]
    public function database_page_offers_repair_for_a_problem_table(): void
    {
        $admin = $this->admin();

        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $this->actingAs($admin)
            ->get('/admin/database')
            ->assertOk()
            ->assertSee(__('booking.admin.database.repair_button'))
            ->assertSee(route('admin.database.repair', 'bs_users'));
    }

    #[Test]
    public function database_page_shows_no_drift_message_for_a_healthy_schema(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee(__('booking.admin.database.drift_none'));
    }

    #[Test]
    public function database_page_shows_an_extra_column_drift_finding(): void
    {
        $admin = $this->admin();

        Schema::table('bs_users', function (Blueprint $table): void {
            $table->string('legacy_note', 32)->nullable();
        });

        $this->actingAs($admin)
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('bs_users')
            ->assertSee('legacy_note');
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

    #[Test]
    public function admin_can_create_a_backup(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post('/admin/database/backups')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success');

        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    #[Test]
    public function admin_can_download_a_backup(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();

        $this->actingAs($this->admin())
            ->get("/admin/database/backups/{$filename}/download")
            ->assertOk()
            ->assertHeader('Content-Disposition', "attachment; filename={$filename}");
    }

    #[Test]
    public function admin_can_delete_a_backup(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();

        $this->actingAs($this->admin())
            ->post("/admin/database/backups/{$filename}/delete")
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing("backups/{$filename}");
    }

    #[Test]
    public function restore_requires_typed_confirmation(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();

        $this->from('/admin/database')
            ->actingAs($this->admin())
            ->post("/admin/database/backups/{$filename}/restore", ['confirmation' => 'wrong'])
            ->assertRedirect('/admin/database')
            ->assertSessionHasErrors('confirmation');
    }

    #[Test]
    public function admin_can_restore_with_valid_confirmation(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();

        $this->actingAs($this->admin())
            ->post("/admin/database/backups/{$filename}/restore", ['confirmation' => 'RESTORE'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('success')
            ->assertSessionHas('output');
    }

    #[Test]
    public function regular_member_cannot_manage_backups(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($member)->post('/admin/database/backups')->assertForbidden();
        $this->actingAs($member)->post("/admin/database/backups/{$filename}/delete")->assertForbidden();
        $this->actingAs($member)->post("/admin/database/backups/{$filename}/restore", ['confirmation' => 'RESTORE'])->assertForbidden();
    }

    #[Test]
    public function an_invalid_backup_filename_in_the_url_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/backups/../../etc/passwd/delete')
            ->assertNotFound();
    }

    #[Test]
    public function database_page_lists_an_existing_backup(): void
    {
        Storage::fake('local');
        $filename = (new DatabaseBackupService())->create();

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee($filename)
            ->assertSee(route('admin.database.backups.download', $filename));
    }

    #[Test]
    public function database_page_shows_no_backups_message_when_empty(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee(__('booking.admin.database.backup_none'));
    }
}
