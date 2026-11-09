<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Mcp\Tools\UpdateMemberTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberPrivacyRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function payload(Member $member): array
    {
        return ['member_number' => $member->member_number, 'firstname' => $member->firstname, 'lastname' => $member->lastname, 'joined_at' => $member->joined_at->toDateString(), 'left_at' => $member->left_at?->toDateString()];
    }

    #[Test]
    public function anonymization_redacts_every_history_copy_and_keeps_nonpersonal_changes(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = Member::factory()->create(['firstname' => 'PrivateReviewName', 'email' => 'private-review@example.test', 'left_at' => now()->subYears(5)->toDateString()]);
        MemberChange::create(['member_id' => $member->id, 'admin_user_id' => $admin->uid, 'action' => 'updated', 'before' => ['email' => 'older-private@example.test', 'status' => 'aktiv'], 'after' => ['email' => $member->email, 'status' => 'ausgetreten']]);
        $this->actingAs($admin)->post('/members/'.$member->id.'/anonymize', ['confirmation' => 'ANONYMISIEREN'])->assertRedirect()->assertSessionHasNoErrors();
        $changes = MemberChange::where('member_id', $member->id)->get();
        $this->assertCount(2, $changes);
        foreach ($changes as $change) {
            foreach (['before', 'after'] as $snapshot) {
                $this->assertSame([], array_intersect(Member::ANONYMIZED_FIELDS, array_keys($change->{$snapshot} ?? [])));
            }
        }
        $this->assertSame('aktiv', $changes->first()->before['status']);
        $this->get('/members/'.$member->id.'/edit')->assertOk()->assertDontSee('private-review@example.test')->assertDontSee('older-private@example.test')->assertDontSee('PrivateReviewName');
    }

    public static function lockedUpdates(): array
    {
        $cases = [];
        foreach (['web', 'rest', 'mcp'] as $entry) {
            foreach (['firstname' => 'Restored', 'lastname' => 'Identity', 'email' => 'restored@example.test', 'phone' => '123456', 'street' => 'Identity street', 'zip' => '1000', 'city' => 'Identity city', 'nuliga_id' => 123456, 'user_id' => 1, 'notiz' => 'Restored personal note'] as $field => $value) {
                $cases[$entry.' '.$field] = [$entry, $field, $value];
            }
        }

        return $cases;
    }

    #[Test]
    public function a_stale_instance_cannot_restore_identity_after_another_request_anonymizes(): void
    {
        $member = Member::factory()->create(['email' => 'private-review@example.test', 'left_at' => now()->subYears(5)->toDateString()]);
        $stale = Member::findOrFail($member->id);
        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->post('/members/'.$member->id.'/anonymize', ['confirmation' => 'ANONYMISIEREN'])->assertSessionHasNoErrors();
        try {
            $stale->update(['email' => 'restored@example.test']);
            $this->fail('A stale model restored personal data.');
        } catch (ValidationException) {
            $this->assertNull($member->fresh()->email);
        }
        $change = MemberChange::create(['member_id' => $member->id, 'action' => 'updated', 'before' => ['email' => 'stale@example.test'], 'after' => ['email' => 'restored@example.test']]);
        $this->assertSame([], $change->fresh()->before);
        $this->assertSame([], $change->fresh()->after);
    }

    #[Test]
    #[DataProvider('lockedUpdates')]
    public function every_update_interface_preserves_the_anonymized_field_lock(string $entry, string $field, string|int $value): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        Sanctum::actingAs($admin);
        $member = Member::factory()->create(['firstname' => 'Ehemaliges', 'lastname' => 'Mitglied #1', 'email' => null, 'left_at' => now()->subYears(5)->toDateString(), 'anonymized_at' => now()]);
        if ($field === 'user_id') {
            $value = $admin->uid;
        }
        $before = $member->fresh()->getRawOriginal();
        $data = [...$this->payload($member), $field => $value];
        if ($entry === 'web') {
            $this->put('/members/'.$member->id, $data)->assertSessionHasErrors('member');
        } elseif ($entry === 'rest') {
            $this->putJson('/api/v1/members/'.$member->id, $data)->assertUnprocessable()->assertJsonValidationErrors('member');
        } else {
            try {
                app(UpdateMemberTool::class)->handle(new McpRequest(['member_id' => $member->id, ...$data]));
                $this->fail('MCP restored a protected field.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('member', $exception->errors());
            }
        }
        $this->assertSame($before, $member->fresh()->getRawOriginal());
        $this->assertSame(0, MemberChange::where('member_id', $member->id)->count());
    }

    public static function entryPoints(): array
    {
        return [['web'], ['rest'], ['mcp']];
    }

    #[Test]
    #[DataProvider('entryPoints')]
    public function unrelated_edits_preserve_resting_status_and_unchanged_anonymized_fields(string $entry): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'admin']));
        foreach ([false, true] as $anonymized) {
            $member = Member::factory()->create(['status' => $anonymized ? 'ausgetreten' : 'ruhend', 'left_at' => $anonymized ? now()->subYears(5)->toDateString() : null, 'anonymized_at' => $anonymized ? now() : null]);
            $data = [...$this->payload($member), 'gender' => 'd'];
            if ($entry === 'web') {
                $this->put('/members/'.$member->id, $data)->assertRedirect()->assertSessionHasNoErrors();
            } elseif ($entry === 'rest') {
                $this->putJson('/api/v1/members/'.$member->id, $data)->assertOk();
            } else {
                app(UpdateMemberTool::class)->handle(new McpRequest(['member_id' => $member->id, ...$data]));
            }
            $this->assertSame($anonymized ? 'ausgetreten' : 'ruhend', $member->fresh()->status);
            $this->assertSame('d', $member->fresh()->gender);
        }
    }

    #[Test]
    public function data_migration_redacts_existing_anonymized_records_and_history(): void
    {
        $member = Member::factory()->create(['anonymized_at' => now(), 'email' => 'legacy-private@example.test']);
        DB::table('member_changes')->insert(['member_id' => $member->id, 'action' => 'updated', 'before' => json_encode(['email' => 'legacy-private@example.test']), 'after' => json_encode(['email' => null]), 'created_at' => now()]);
        $migration = require database_path('migrations/2026_11_09_000001_redact_anonymized_member_history.php');
        $migration->up();
        $migration->up();
        $this->assertNull($member->fresh()->email);
        $this->assertSame([], MemberChange::where('member_id', $member->id)->first()->before);
    }
}
