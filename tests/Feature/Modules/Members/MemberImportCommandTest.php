<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(): string
    {
        $currentYy = now()->year % 100;
        $lastYy = $currentYy - 1;

        $csv = <<<CSV
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt {$currentYy},,anna@example.com,Teststr. 1,4400,Sampletown,,0650 1234567,weiblich,01.02.1980,,46,Ja
        Alt,Bernd,Pensionist,bezahlt {$lastYy},,,Weg 2,4400,Sampletown,70 658,,maennlich,03.04.1950,,76,Nein
        CSV;

        $file = sys_get_temp_dir().'/members-import-test.csv';
        file_put_contents($file, $csv);

        return $file;
    }

    #[Test]
    public function imports_members_categories_and_paid_status_from_csv(): void
    {
        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])->assertSuccessful();

        $currentYear = now()->year;
        $lastYear = $currentYear - 1;

        $anna = Member::where('lastname', 'Muster')->firstOrFail();
        $this->assertSame(1, $anna->member_number);
        $this->assertSame('Anna', $anna->firstname);
        $this->assertSame('1980-02-01', $anna->birthdate->toDateString());
        $this->assertSame('anna@example.com', $anna->email);
        $this->assertSame('0650 1234567', $anna->phone);
        $this->assertSame('Teststr. 1, 4400 Sampletown', $anna->address);

        $bernd = Member::where('lastname', 'Alt')->firstOrFail();
        $this->assertSame(2, $bernd->member_number);
        $this->assertNull($bernd->email);
        $this->assertSame('70 658', $bernd->phone);

        // Mitgliedsarten aus der CSV werden auf die Kategorienamen der App gemappt.
        $this->assertNotNull(FeeCategory::where('name', 'Vollzahler')->first());
        $this->assertNotNull(FeeCategory::where('name', 'Pensionisten')->first());

        $currentFy = FiscalYear::where('name', (string) $currentYear)->firstOrFail();
        $lastFy = FiscalYear::where('name', (string) $lastYear)->firstOrFail();

        // Anna paid the current year: one due, marked paid.
        $annaDue = MemberDue::where('member_id', $anna->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertTrue($annaDue->isPaid());
        $this->assertSame('Vollzahler', $annaDue->feeCategory->name);

        // Bernd paid last year: paid due last year, open due this year.
        $berndLast = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $lastFy->id)->firstOrFail();
        $this->assertTrue($berndLast->isPaid());
        $berndCurrent = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertFalse($berndCurrent->isPaid());
    }

    #[Test]
    public function rerunning_the_import_skips_existing_members(): void
    {
        $file = $this->writeCsv();

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();
        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(2, Member::count());
        $this->assertSame(1, FeeCategory::where('name', 'Vollzahler')->count());
        $this->assertSame(3, MemberDue::count());
    }

    #[Test]
    public function rerun_generates_dues_for_existing_members(): void
    {
        $currentYear = now()->year;
        $currentYy = $currentYear % 100;

        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])->assertSuccessful();

        // Nächster Export: Bernd hat inzwischen das aktuelle Jahr bezahlt.
        $csv = <<<CSV
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Alt,Bernd,Pensionist,bezahlt {$currentYy},,,Weg 2,4400,Sampletown,70 658,,maennlich,03.04.1950,,76,Nein
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-rerun.csv';
        file_put_contents($file, $csv);

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(2, Member::count());

        $bernd = Member::where('lastname', 'Alt')->firstOrFail();
        $currentFy = FiscalYear::where('name', (string) $currentYear)->firstOrFail();
        $due = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertTrue($due->isPaid());

        // Nochmaliger Lauf derselben Datei erzeugt keine doppelte Zahlung.
        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();
        $this->assertSame(1, $due->payments()->count());
    }

    #[Test]
    public function fails_for_missing_file(): void
    {
        $this->artisan('members:import-csv', ['file' => 'C:/does/not/exist.csv'])->assertFailed();
    }
}
