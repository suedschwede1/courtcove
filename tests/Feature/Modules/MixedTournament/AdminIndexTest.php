<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedRound;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(): User
    {
        return User::factory()->status('enabled')->create();
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    #[Test]
    public function any_logged_in_user_sees_the_overview(): void
    {
        MixedEvent::factory()->create(['title' => 'Herbst-Beispiel', 'event_date' => '2027-11-07']);

        $this->actingAs($this->member())->get('/mixed-tournament/admin')
            ->assertOk()
            ->assertSee('Herbst-Beispiel')
            ->assertSee('Bewerbe')
            ->assertSee('Americano');
    }

    #[Test]
    public function the_overview_uses_the_admin_shell_with_the_mixed_entry_active(): void
    {
        $this->actingAs($this->admin())->get('/mixed-tournament/admin')
            ->assertOk()
            ->assertSee('ds-admin-sidebar', false)
            ->assertSee('href="'.route('mixed.admin.index').'" class="ds-admin-sidebar__link" aria-current="page"', false);
    }

    #[Test]
    public function create_form_only_for_global_admins(): void
    {
        $this->actingAs($this->member())->get('/mixed-tournament/admin')
            ->assertOk()
            ->assertDontSee(route('mixed.admin.create-event'))
            ->assertDontSee(route('mixed.admin.import-members'));

        $this->actingAs($this->admin())->get('/mixed-tournament/admin')
            ->assertOk()
            ->assertSee(route('mixed.admin.create-event'))
            ->assertSee(route('mixed.admin.import-members'))
            ->assertSee('name="event_date"', false)
            ->assertSee('value="americano" class="ds-option__input" checked', false);
    }

    #[Test]
    public function open_link_targets_by_role(): void
    {
        $event = MixedEvent::factory()->create();
        $reader = $this->member();
        $grantAdmin = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $grantAdmin->uid, 'role' => 'admin']);

        $this->actingAs($reader)->get('/mixed-tournament/admin')
            ->assertSee(route('mixed.event', ['event' => $event->id]))
            ->assertDontSee(route('mixed.admin.event', ['event' => $event->id]));

        $this->actingAs($grantAdmin)->get('/mixed-tournament/admin')
            ->assertSee(route('mixed.admin.event', ['event' => $event->id]));
    }

    #[Test]
    public function todo_lists_proposed_rounds_of_administered_events(): void
    {
        $mine = MixedEvent::factory()->create(['title' => 'Eigenes-Event']);
        $other = MixedEvent::factory()->create(['title' => 'Fremdes-Event']);
        MixedRound::factory()->create(['event_id' => $mine->id, 'round_no' => 3, 'status' => 'proposed']);
        MixedRound::factory()->create(['event_id' => $mine->id, 'round_no' => 2, 'status' => 'completed']);
        MixedRound::factory()->create(['event_id' => $other->id, 'round_no' => 5, 'status' => 'proposed']);
        $user = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $mine->id, 'uid' => $user->uid, 'role' => 'admin']);

        $this->actingAs($user)->get('/mixed-tournament/admin')
            ->assertSee('Zu erledigen')
            ->assertSee('Runde 3 wartet auf Bestätigung')
            ->assertSee('Eigenes-Event · Vorschlag erzeugt')
            ->assertDontSee('Runde 2 wartet auf Bestätigung')
            ->assertDontSee('Runde 5 wartet auf Bestätigung');

        $this->actingAs($this->member())->get('/mixed-tournament/admin')
            ->assertDontSee('Zu erledigen');
    }

    #[Test]
    public function create_event_validates_and_redirects(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => '   ', 'event_date' => '2027-05-01'])
            ->assertRedirect(route('mixed.admin.index'))
            ->assertSessionHas('error', 'Titel und Datum sind Pflichtfelder.');
        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => 'Mai', 'event_date' => '01.05.2027'])
            ->assertSessionHas('error', 'Titel und Datum sind Pflichtfelder.');
        $this->assertSame(0, MixedEvent::query()->count());

        $response = $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => '  Mai-Mixed ', 'event_date' => '2027-05-01']);
        $event = MixedEvent::query()->sole();
        $response->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', 'Event angelegt.');
        $this->assertSame('Mai-Mixed', $event->title);
        $this->assertSame('2027-05-01', (string) $event->event_date);

        $this->actingAs($this->member())->post('/mixed-tournament/admin/create-event', ['title' => 'X', 'event_date' => '2027-05-01'])
            ->assertForbidden();
    }

    #[Test]
    public function create_event_maps_mode(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => 'A', 'event_date' => '2027-05-01', 'mode' => 'mexicano']);
        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => 'B', 'event_date' => '2027-05-01', 'mode' => 'swiss']);
        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => 'C', 'event_date' => '2027-05-01']);

        $this->assertSame(['mexicano', 'americano', 'americano'], MixedEvent::query()->orderBy('id')->pluck('mode')->all());
    }

    #[Test]
    public function set_default_moves_the_flag(): void
    {
        $old = MixedEvent::factory()->create(['is_default' => 1]);
        $new = MixedEvent::factory()->create();

        $this->actingAs($this->admin())->post('/mixed-tournament/admin/event/'.$new->id.'/set-default')
            ->assertRedirect(route('mixed.admin.event', ['event' => $new->id]))
            ->assertSessionHas('success', 'Als Standard-Event markiert.');

        $this->assertFalse($old->fresh()->is_default);
        $this->assertTrue($new->fresh()->is_default);

        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $this->actingAs($this->member())->post('/mixed-tournament/admin/event/'.$test->id.'/set-default')->assertForbidden();
        $this->assertTrue($new->fresh()->is_default);
    }

    #[Test]
    public function import_page_shows_the_roster_count_and_the_expected_columns(): void
    {
        MixedMember::factory()->count(3)->create();

        $this->actingAs($this->admin())->get('/mixed-tournament/admin/import-members')
            ->assertOk()
            ->assertSee('Aktuell 3 Mitglieder hinterlegt')
            ->assertSee('Nachname')
            ->assertSee('GebDatum')
            ->assertSee('name="csv"', false)
            ->assertSee('accept=".csv,text/csv"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    #[Test]
    public function import_upload_replaces_the_roster(): void
    {
        MixedMember::factory()->create(['label' => 'Alt Eintrag']);
        $csv = mb_convert_encoding("Nachname;Vorname;Geschlecht;ITN;GebDatum\nBeispiel;Jürgen;maennlich;ITN 7,1;01.02.1980\nBeispiel;Anna;weiblich;;\n", 'Windows-1252', 'UTF-8');

        $this->actingAs($this->admin())->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', $csv),
        ])->assertRedirect(route('mixed.admin.import-members'))
            ->assertSessionHas('success', '2 Mitglieder importiert.');

        $this->assertSame(['Beispiel Anna', 'Beispiel Jürgen'], MixedMember::query()->orderBy('label')->pluck('label')->all());
        $this->assertSame('m', MixedMember::query()->where('label', 'Beispiel Jürgen')->value('gender'));
    }

    #[Test]
    public function import_missing_column_flash(): void
    {
        MixedMember::factory()->create(['label' => 'Alt Eintrag']);

        $this->actingAs($this->admin())->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', "Nachname;ITN\nBeispiel;5\n"),
        ])->assertRedirect(route('mixed.admin.import-members'))
            ->assertSessionHas('error', 'Spalte "Vorname" fehlt im CSV-Export.');

        $this->assertSame(['Alt Eintrag'], MixedMember::query()->pluck('label')->all());
    }

    #[Test]
    public function import_without_file_flash(): void
    {
        $this->actingAs($this->admin())->post('/mixed-tournament/admin/import-members')
            ->assertRedirect(route('mixed.admin.import-members'))
            ->assertSessionHas('error', 'Bitte eine CSV-Datei auswählen.');
    }

    #[Test]
    public function non_global_admin_cannot_import(): void
    {
        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $user = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $test->id, 'uid' => $user->uid, 'role' => 'admin']);

        $this->actingAs($user)->get('/mixed-tournament/admin/import-members')->assertForbidden();
        $this->actingAs($user)->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', "Nachname;Vorname\nA;B\n"),
        ])->assertForbidden();
    }

    #[Test]
    public function create_event_refuses_long_titles_and_array_input(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => str_repeat('a', 161), 'event_date' => '2027-05-01'])
            ->assertRedirect(route('mixed.admin.index'))
            ->assertSessionHas('error', 'Der Titel darf höchstens 160 Zeichen lang sein.');
        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => ['x'], 'event_date' => '2027-05-01'])
            ->assertSessionHas('error', 'Titel und Datum sind Pflichtfelder.');
        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => 'X', 'event_date' => ['2027-05-01']])
            ->assertSessionHas('error', 'Titel und Datum sind Pflichtfelder.');
        $this->assertSame(0, MixedEvent::query()->count());

        $this->actingAs($admin)->post('/mixed-tournament/admin/create-event', ['title' => str_repeat('ä', 160), 'event_date' => '2027-05-01'])
            ->assertSessionHas('success', 'Event angelegt.');

        $this->actingAs($admin)->get('/mixed-tournament/admin')->assertSee('maxlength="160"', false);
    }

    #[Test]
    public function test_mode_user_cannot_create_events(): void
    {
        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $user = $this->member();

        $this->actingAs($user)->get('/mixed-tournament/admin/event/'.$test->id)->assertOk();
        $this->actingAs($user)->post('/mixed-tournament/admin/create-event', ['title' => 'X', 'event_date' => '2027-05-01'])->assertForbidden();
        $this->assertSame(1, MixedEvent::query()->count());
    }

    #[Test]
    public function import_refuses_large_or_non_text_files(): void
    {
        MixedMember::factory()->create(['label' => 'Alt Eintrag']);
        $admin = $this->admin();

        $big = "Nachname;Vorname\n".str_repeat("Muster;Anna\n", 200000);
        $this->actingAs($admin)->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', $big),
        ])->assertRedirect(route('mixed.admin.import-members'))
            ->assertSessionHas('error', 'Bitte eine CSV-Textdatei mit höchstens 2 MB auswählen.');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $this->actingAs($admin)->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', (string) $png),
        ])->assertSessionHas('error', 'Bitte eine CSV-Textdatei mit höchstens 2 MB auswählen.');

        $this->actingAs($admin)->post('/mixed-tournament/admin/import-members', [
            'csv' => UploadedFile::fake()->createWithContent('export.csv', "Nachname;Vorname\n".str_repeat('x', 121).";Anna\n"),
        ])->assertSessionHas('error', 'Der Name "'.str_repeat('x', 40).'" ist länger als 120 Zeichen.');

        $this->assertSame(['Alt Eintrag'], MixedMember::query()->pluck('label')->all());
    }

    #[Test]
    public function no_source_system_name_in_the_module(): void
    {
        foreach (['de', 'en'] as $locale) {
            $this->assertStringNotContainsStringIgnoringCase('nu'.'liga', (string) file_get_contents(app_path('Modules/MixedTournament/resources/lang/'.$locale.'/admin.php')));
        }

        $this->actingAs($this->admin())->get('/mixed-tournament/admin/import-members')
            ->assertSee('Mitglieder-CSV-Export des Verbands')
            ->assertDontSee('nu'.'Liga');
    }

    #[Test]
    public function every_admin_and_messages_key_exists_in_both_locales(): void
    {
        foreach (['admin', 'messages'] as $file) {
            $de = Lang::get('mixed::'.$file, [], 'de');
            $en = Lang::get('mixed::'.$file, [], 'en');
            $this->assertIsArray($de, $file);
            $this->assertIsArray($en, $file);
            $this->assertSame(array_keys($this->flatten($de)), array_keys($this->flatten($en)), $file);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix.$key.'.');
            } else {
                $flat[$prefix.$key] = $value;
            }
        }
        ksort($flat);

        return $flat;
    }
}
