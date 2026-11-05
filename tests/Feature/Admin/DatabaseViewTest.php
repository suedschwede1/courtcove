<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\DatabaseBackupService;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the restyled database page: status badges, every destructive form keeps its route and
 * confirm and is marked as danger, safe actions come first, command output stays readable.
 */
final class DatabaseViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private string $backup;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['status' => 'admin']);
        Storage::fake('local');
        $this->backup = (new DatabaseBackupService)->create();
        DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();
        Schema::table('bs_users', function (Blueprint $table): void {
            $table->dropColumn('last_ip');
        });

        $response = $this->actingAs($admin)
            ->withSession(['output' => "Line one\nLine two"])
            ->get(route('admin.database.index'));
        $response->assertOk();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query, ?DOMElement $context = null): DOMNodeList
    {
        $nodes = $this->xpath->query($query, $context);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    private function confirm(string $key): string
    {
        return 'return confirm('.Js::from(__($key)).')';
    }

    #[Test]
    public function migration_status_uses_badges(): void
    {
        $this->assertGreaterThan(0, $this->nodes('//table[contains(@class,"ds-table")]//span[contains(@class,"ds-badge--good")][normalize-space()="'.__('booking.admin.database.status_ran').'"]')->length);
        $this->assertSame(1, $this->nodes('//table[contains(@class,"ds-table")]//span[contains(@class,"ds-badge--attention")][normalize-space()="'.__('booking.admin.database.status_pending').'"]')->length);
    }

    #[Test]
    public function migrate_and_repair_keep_their_forms_and_confirms_as_danger(): void
    {
        $migrate = $this->nodes('//form[@method="POST"][@action="'.route('admin.database.migrate').'"]');
        $this->assertSame(1, $migrate->length);
        $form = $migrate->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertSame($this->confirm('booking.admin.database.migrate_confirm'), $form->getAttribute('onsubmit'));
        $this->assertSame(1, $this->nodes('//form[@action="'.route('admin.database.migrate').'"]//input[@id="db-migrate-confirmation"][@name="confirmation"]')->length);
        $this->assertSame(1, $this->nodes('//label[@for="db-migrate-confirmation"]')->length);
        $this->assertSame(1, $this->nodes('//form[@action="'.route('admin.database.migrate').'"]//button[@type="submit"][contains(@class,"ds-btn--danger")]')->length);

        $repair = $this->nodes('//form[@method="POST"][@action="'.route('admin.database.repair', 'bs_users').'"]');
        $this->assertSame(1, $repair->length);
        $form = $repair->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertSame($this->confirm('booking.admin.database.repair_confirm'), $form->getAttribute('onsubmit'));
        $this->assertSame(1, $this->nodes('//form[@action="'.route('admin.database.repair', 'bs_users').'"]//button[@type="submit"][contains(@class,"ds-btn--danger")]')->length);
        $this->assertGreaterThanOrEqual(1, $this->nodes('//tr[td[normalize-space()="bs_users"]]//td[contains(normalize-space(),"last_ip")]')->length, 'missing column listed in the row');
    }

    #[Test]
    public function backup_actions_keep_routes_confirms_and_order(): void
    {
        $create = $this->nodes('//form[@method="POST"][@action="'.route('admin.database.backups.create').'"]//button[@type="submit"]');
        $this->assertSame(1, $create->length);
        $this->assertInstanceOf(DOMElement::class, $create->item(0));
        $this->assertStringNotContainsString('ds-btn--danger', $create->item(0)->getAttribute('class'));

        $cell = '//tr[td[normalize-space()="'.$this->backup.'"]]';
        $controls = $this->nodes($cell.'//*[self::a or self::form]');
        $this->assertSame(3, $controls->length);
        $download = $controls->item(0);
        $this->assertInstanceOf(DOMElement::class, $download);
        $this->assertSame('a', $download->nodeName, 'download first');
        $this->assertSame(route('admin.database.backups.download', $this->backup), $download->getAttribute('href'));
        $this->assertStringContainsString('ds-btn--secondary', $download->getAttribute('class'));

        $delete = $controls->item(1);
        $this->assertInstanceOf(DOMElement::class, $delete);
        $this->assertSame(route('admin.database.backups.delete', $this->backup), $delete->getAttribute('action'));
        $this->assertSame($this->confirm('booking.admin.database.backup_delete_confirm'), $delete->getAttribute('onsubmit'));

        $restore = $controls->item(2);
        $this->assertInstanceOf(DOMElement::class, $restore);
        $this->assertSame(route('admin.database.backups.restore', $this->backup), $restore->getAttribute('action'));
        $this->assertSame($this->confirm('booking.admin.database.backup_restore_warning'), $restore->getAttribute('onsubmit'));
        $this->assertSame(1, $this->nodes($cell.'//form[@action="'.route('admin.database.backups.restore', $this->backup).'"]//input[@name="confirmation"][@aria-label]')->length);

        foreach ([$delete, $restore] as $form) {
            $this->assertSame(1, $this->nodes('.//button[@type="submit"][contains(@class,"ds-btn--danger")]', $form)->length);
            $this->assertSame(1, $this->nodes('.//input[@name="_token"]', $form)->length);
        }
    }

    #[Test]
    public function command_output_is_shown_in_a_mono_block(): void
    {
        $pre = $this->nodes('//pre[contains(@class,"ds-admin-output")]');
        $this->assertSame(1, $pre->length);
        $this->assertSame("Line one\nLine two", $pre->item(0)?->textContent);
    }
}
