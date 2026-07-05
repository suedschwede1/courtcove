<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseBackupService $backups;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->backups = new DatabaseBackupService();
    }

    #[Test]
    public function create_writes_a_gzip_compressed_dump_containing_known_tables(): void
    {
        $filename = $this->backups->create();

        Storage::disk('local')->assertExists('backups/'.$filename);

        $sql = gzdecode(Storage::disk('local')->get('backups/'.$filename));

        $this->assertMatchesRegularExpression('/CREATE TABLE .bs_options./', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `members`', $sql);
    }

    #[Test]
    public function create_includes_row_data(): void
    {
        DB::table('bs_options')->insert(['key' => 'drift_test_key', 'value' => 'drift_test_value', 'locale' => null]);

        $filename = $this->backups->create();
        $sql = gzdecode(Storage::disk('local')->get('backups/'.$filename));

        $this->assertStringContainsString('drift_test_key', $sql);
        $this->assertStringContainsString('drift_test_value', $sql);
    }

    #[Test]
    public function create_returns_a_filename_matching_the_expected_pattern(): void
    {
        $filename = $this->backups->create();

        $this->assertMatchesRegularExpression('/^backup-\d{8}_\d{6}\.sql\.gz$/', $filename);
    }

    #[Test]
    public function create_with_suffix_appends_it_to_the_filename(): void
    {
        $filename = $this->backups->create('prerestore');

        $this->assertMatchesRegularExpression('/^backup-\d{8}_\d{6}-prerestore\.sql\.gz$/', $filename);
    }

    #[Test]
    public function list_returns_newest_first_with_correct_size(): void
    {
        $older = $this->backups->create();
        $this->travel(1)->seconds();
        $newer = $this->backups->create();

        $list = $this->backups->list();

        $this->assertSame($newer, $list[0]['filename']);
        $this->assertSame($older, $list[1]['filename']);
        $this->assertGreaterThan(0, $list[0]['sizeBytes']);
    }

    #[Test]
    public function delete_removes_the_file(): void
    {
        $filename = $this->backups->create();

        $this->backups->delete($filename);

        Storage::disk('local')->assertMissing('backups/'.$filename);
        $this->assertSame([], $this->backups->list());
    }

    #[Test]
    public function delete_of_a_missing_file_is_a_noop(): void
    {
        $this->backups->delete('backup-20200101_000000.sql.gz');

        $this->assertSame([], $this->backups->list());
    }

    #[Test]
    public function old_backups_beyond_the_retention_limit_are_pruned(): void
    {
        $filenames = [];

        for ($i = 0; $i < 11; $i++) {
            $filenames[] = $this->backups->create();
            $this->travel(1)->seconds();
        }

        $list = $this->backups->list();

        $this->assertCount(10, $list);
        $remaining = array_column($list, 'filename');
        $this->assertNotContains($filenames[0], $remaining);
        $this->assertContains($filenames[10], $remaining);
    }
}
