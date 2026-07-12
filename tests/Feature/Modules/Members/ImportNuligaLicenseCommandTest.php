<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportNuligaLicenseCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const HEADER = 'Nachname;Vorname;Geburtsname;Geburtsort;GebDatum;ITN;Pass-Lizenz-Nr;Lizenztyp;Lizenzgueltigkeit;ID-Nr;Nationalitaet;Geschlecht;GeschlechtSpielbetrieb;Email;Strasse;PLZ;Ort;Land;TelPrivat;TelGeschaeft;TelMobil;Fax;VereinsNr';

    /** Schreibt eine Windows-1252-kodierte nuLiga-Export-CSV (wie sie der echte Export liefert). */
    private function writeCsv(array $rows): string
    {
        $lines = array_merge([self::HEADER], $rows);
        $contents = mb_convert_encoding(implode("\r\n", $lines)."\r\n", 'Windows-1252', 'UTF-8');

        $file = sys_get_temp_dir().'/nuliga-license-import-test.csv';
        file_put_contents($file, $contents);

        return $file;
    }

    #[Test]
    public function sets_license_number_on_matching_member(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'nuliga_id' => null,
        ]);

        $file = $this->writeCsv([
            'Muster;Anna;;;01.02.1980;ITN9,9;-;Gold;30.04.2027;288563;AUT;weiblich;weiblich;anna@example.com;Teststr. 1;4400;Sampletown;AUT;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])->assertSuccessful();

        $this->assertSame(288563, Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function overwrites_an_existing_different_license_number(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'nuliga_id' => 999999,
        ]);

        $file = $this->writeCsv([
            'Muster;Anna;;;01.02.1980;ITN9,9;-;Gold;30.04.2027;288563;AUT;weiblich;weiblich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])->assertSuccessful();

        $this->assertSame(288563, Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function treats_the_000000_placeholder_as_no_license_and_leaves_existing_value_untouched(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'nuliga_id' => 123456,
        ]);

        $file = $this->writeCsv([
            'Muster;Anna;;;01.02.1980;-;-;-;-;000000;AUT;weiblich;weiblich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])->assertSuccessful();

        $this->assertSame(123456, Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function warns_and_skips_when_no_matching_member_is_found(): void
    {
        $file = $this->writeCsv([
            'Unbekannt;Otto;;;01.02.1980;ITN9,9;-;Gold;30.04.2027;288563;AUT;maennlich;maennlich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])
            ->expectsOutputToContain('Kein Mitglied gefunden für Otto Unbekannt')
            ->assertSuccessful();

        $this->assertSame(0, Member::count());
    }

    #[Test]
    public function warns_and_skips_on_conflict_with_another_members_license_number(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'nuliga_id' => 288563,
        ]);
        Member::factory()->create([
            'firstname' => 'Bernd',
            'lastname' => 'Alt',
            'birthdate' => '1950-04-03',
            'nuliga_id' => null,
        ]);

        $file = $this->writeCsv([
            'Alt;Bernd;;;03.04.1950;ITN5,0;-;Gold;30.04.2027;288563;AUT;maennlich;maennlich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])
            ->expectsOutputToContain('288563')
            ->assertSuccessful();

        $this->assertSame(288563, Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
        $this->assertNull(Member::where('lastname', 'Alt')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function matches_members_with_umlauts_in_the_name(): void
    {
        Member::factory()->create([
            'firstname' => 'Hans',
            'lastname' => 'Greinöcker',
            'birthdate' => '1958-12-07',
            'nuliga_id' => null,
        ]);

        $file = $this->writeCsv([
            'Greinöcker;Hans;;;07.12.1958;-;-;-;-;56335;AUT;maennlich;maennlich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file])->assertSuccessful();

        $this->assertSame(56335, Member::where('lastname', 'Greinöcker')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function dry_run_reports_changes_without_saving_them(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'nuliga_id' => null,
        ]);

        $file = $this->writeCsv([
            'Muster;Anna;;;01.02.1980;ITN9,9;-;Gold;30.04.2027;288563;AUT;weiblich;weiblich;;;;;;;;;;41019',
        ]);

        $this->artisan('members:import-nuliga-license', ['file' => $file, '--dry-run' => true])
            ->expectsOutputToContain('Würde Anna Muster')
            ->assertSuccessful();

        $this->assertNull(Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function fails_for_missing_file(): void
    {
        $this->artisan('members:import-nuliga-license', ['file' => 'C:/does/not/exist.csv'])->assertFailed();
    }
}
