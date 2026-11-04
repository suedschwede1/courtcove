<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\StripFourByteCharacters;
use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripFourByteCharactersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TENNIS = "\u{1F3BE}";

    protected function setUp(): void
    {
        parent::setUp();

        $echo = static fn (Request $request) => response()->json([
            'all' => $request->all(),
            'input' => $request->input(),
            'query' => $request->query(),
            'json' => $request->isJson() ? $request->json()->all() : null,
            'validated' => $request->validate(['a' => 'nullable|string'])['a'] ?? null,
        ]);

        Route::middleware('web')->group(function () use ($echo): void {
            Route::any('_probe/legacy', $echo);
            Route::any('tournament/_probe', $echo);
            Route::any('members/_probe', $echo);
            Route::any('_probe/named', $echo)->name('members.probe');
        });
        Route::middleware('api')->any('api/_probe', $echo);
    }

    #[Test]
    public function nested_form_values_are_stripped_and_keys_are_untouched(): void
    {
        $t = self::TENNIS;

        $data = $this->post('_probe/legacy', [
            'a' => "Training {$t}",
            "k{$t}" => ['x' => ["{$t}deep{$t}"], 'n' => 5, 'b' => true],
        ])->json();

        $this->assertSame('Training ', $data['all']['a']);
        $this->assertSame('Training ', $data['input']['a']);
        $this->assertSame('Training ', $data['validated']);
        $this->assertSame(['x' => ['deep'], 'n' => 5, 'b' => true], $data['all']["k{$t}"]);
    }

    #[Test]
    public function json_body_is_stripped_for_all_input_accessors(): void
    {
        $t = self::TENNIS;

        $data = $this->postJson('_probe/legacy', ['a' => "Ball{$t}", 'list' => [["{$t}x"]]])->json();

        $this->assertSame('Ball', $data['all']['a']);
        $this->assertSame('Ball', $data['input']['a']);
        $this->assertSame('Ball', $data['json']['a']);
        $this->assertSame('Ball', $data['validated']);
        $this->assertSame([['x']], $data['json']['list']);
        $this->assertSame([['x']], $data['all']['list']);
    }

    #[Test]
    public function query_string_is_stripped(): void
    {
        $data = $this->get('_probe/legacy?a='.rawurlencode('Suche '.self::TENNIS))->json();

        $this->assertSame('Suche ', $data['query']['a']);
        $this->assertSame('Suche ', $data['all']['a']);
        $this->assertSame('Suche ', $data['input']['a']);
    }

    #[Test]
    public function three_byte_characters_are_kept_and_pure_emoji_becomes_empty(): void
    {
        $kept = 'Müller Straße € “Zitat” – ok ✓ 日本';

        $data = $this->postJson('_probe/legacy', ['a' => $kept, 'b' => self::TENNIS.self::TENNIS])->json();

        $this->assertSame($kept, $data['all']['a']);
        $this->assertSame('', $data['all']['b']);
    }

    #[Test]
    public function invalid_utf8_is_left_as_is_without_error(): void
    {
        $bad = "bad\xC3\x28".self::TENNIS;
        $request = Request::create('/_probe/legacy', 'POST', ['a' => $bad]);

        $response = (new StripFourByteCharacters)->handle($request, fn (Request $r) => response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame($bad, $request->input('a'));
    }

    #[Test]
    public function api_group_is_covered(): void
    {
        $data = $this->postJson('api/_probe', ['a' => 'API '.self::TENNIS])->json();

        $this->assertSame('API ', $data['all']['a']);
    }

    #[Test]
    public function tournament_paths_are_not_exempt_while_the_bridge_exists(): void
    {
        $data = $this->postJson('tournament/_probe', ['a' => 'Cup '.self::TENNIS])->json();

        $this->assertSame('Cup ', $data['all']['a']);
    }

    #[Test]
    public function members_routes_keep_four_byte_characters_by_path_and_by_route_name(): void
    {
        $value = 'Hi '.self::TENNIS;

        $this->assertSame($value, $this->postJson('members/_probe', ['a' => $value])->json('all.a'));
        $this->assertSame($value, $this->postJson('_probe/named', ['a' => $value])->json('all.a'));
        $this->assertSame($value, $this->get('members/_probe?a='.rawurlencode($value))->json('query.a'));
    }

    #[Test]
    public function the_opt_out_list_is_only_the_members_module(): void
    {
        $this->assertSame(['members.*'], StripFourByteCharacters::EXEMPT_ROUTE_NAMES);
        $this->assertSame(['members', 'members/*'], StripFourByteCharacters::EXEMPT_PATHS);
    }

    #[Test]
    public function booking_note_is_stored_without_emoji(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create([
            'status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid' => $square->sid,
            'date' => now()->addDays(3)->toDateString(),
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
            'mitspieler' => 'Training '.self::TENNIS,
        ])->assertRedirect();

        $booking = Booking::query()->where('uid', $user->uid)->firstOrFail();
        $value = (string) $booking->meta()->where('key', 'player-names')->value('value');

        $this->assertStringNotContainsString(self::TENNIS, $value);
        $this->assertSame('Training', unserialize($value)[0]['value']);
    }

    #[Test]
    public function admin_user_edit_strips_emoji_from_the_alias(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $u = User::factory()->create(['alias' => 'Alt', 'status' => 'enabled']);

        $this->actingAs($admin)->put("/admin/users/{$u->uid}", [
            'alias' => 'Max '.self::TENNIS, 'email' => $u->email, 'status' => 'enabled',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('Max ', $u->refresh()->alias);
    }

    #[Test]
    public function members_mail_template_keeps_emoji(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->post('/members/mail/templates', [
            'name' => 'Turnier '.self::TENNIS,
            'subject' => 'Los gehts '.self::TENNIS,
            'body' => 'Bis bald '.self::TENNIS,
        ])->assertRedirect(route('members.mail.index'));

        $this->assertDatabaseHas('mail_templates', [
            'name' => 'Turnier '.self::TENNIS,
            'subject' => 'Los gehts '.self::TENNIS,
        ]);
    }
}
