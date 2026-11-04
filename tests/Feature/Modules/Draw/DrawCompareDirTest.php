<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Support\DrawRecordPresenter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawCompareDirTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/draw-compare-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    /** @param array<string, mixed> $record */
    private function write(string $slug, array $record): void
    {
        file_put_contents("{$this->dir}/{$slug}.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function record(Draw $draw): array
    {
        return json_decode(json_encode(DrawRecordPresenter::json($draw)), true);
    }

    #[Test]
    public function identical_record_passes(): void
    {
        $draw = Draw::factory()->executed()->create();
        $this->write($draw->slug, $this->record($draw->fresh()));

        $this->artisan('draw:verify', ['--compare-dir' => $this->dir])
            ->expectsOutputToContain("{$draw->slug} compare: identical")
            ->assertExitCode(0);
    }

    #[Test]
    public function key_order_is_ignored(): void
    {
        $draw = Draw::factory()->executed()->create();
        $this->write($draw->slug, array_reverse($this->record($draw->fresh()), true));

        $this->artisan('draw:verify', ['--compare-dir' => $this->dir])
            ->expectsOutputToContain("{$draw->slug} compare: identical")
            ->assertExitCode(0);
    }

    #[Test]
    public function changed_value_fails(): void
    {
        $draw = Draw::factory()->executed()->create();
        $record = $this->record($draw->fresh());
        $record['created'] = '1999-01-01 00:00:00';
        $this->write($draw->slug, $record);

        $this->artisan('draw:verify', ['--compare-dir' => $this->dir])
            ->expectsOutputToContain("{$draw->slug} compare: differs in created")
            ->assertExitCode(1);
    }

    #[Test]
    public function int_vs_string_entry_fails(): void
    {
        $draw = Draw::factory()->executed()->create();
        $record = $this->record($draw->fresh());
        $record['payload']['entries'][0] = (string) $record['payload']['entries'][0];
        $this->write($draw->slug, $record);

        $this->artisan('draw:verify', ['--compare-dir' => $this->dir])
            ->expectsOutputToContain("{$draw->slug} compare: differs in payload")
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_slug_fails(): void
    {
        $this->write('nosuchdraw', ['title' => 'x']);

        $this->artisan('draw:verify', ['--compare-dir' => $this->dir])
            ->expectsOutputToContain('nosuchdraw compare: missing in database')
            ->assertExitCode(1);
    }

    #[Test]
    public function revealed_slugs_lists_only_revealed_draws(): void
    {
        Draw::factory()->create(['slug' => 'committed1']);
        Draw::factory()->token()->executed()->create(['slug' => 'picking1']);
        $closed = Draw::factory()->token()->executed()->closed()->create(['slug' => 'closed1']);
        $direct = Draw::factory()->executed()->create(['slug' => 'direct1']);

        $code = Artisan::call('draw:verify', ['--revealed-slugs' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertSame([$closed->slug, $direct->slug], preg_split('/\R/', trim($output)));
        $this->assertSame(0, preg_match('/[0-9a-f]{64}/', $output));
    }
}
