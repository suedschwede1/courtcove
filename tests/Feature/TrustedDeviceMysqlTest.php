<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/** The trusted_devices table under MySQL: unique token hash, validity query, revocation. */
#[Group('mysql')]
class TrustedDeviceMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::table('trusted_devices')->delete();
        DB::table('bs_users')->delete();
    }

    private function device(int $userId, array $overrides = []): TrustedDevice
    {
        return TrustedDevice::create(array_merge([
            'user_id' => $userId,
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'label' => 'Firefox auf Windows',
            'created_at' => now(),
            'expires_at' => now()->addDays(30),
        ], $overrides));
    }

    #[Test]
    public function the_token_hash_is_a_unique_char_64_column(): void
    {
        $column = collect(Schema::getColumns('trusted_devices'))->firstWhere('name', 'token_hash');
        $this->assertSame('char(64)', $column['type']);

        $hash = hash('sha256', 'x');
        $this->device(1, ['token_hash' => $hash]);

        $this->expectException(QueryException::class);
        $this->device(2, ['token_hash' => $hash]);
    }

    #[Test]
    public function only_unrevoked_unexpired_devices_are_valid_and_revoke_all_ends_them(): void
    {
        $user = User::factory()->create(['status' => 'admin']);
        $this->device((int) $user->uid);
        $this->device((int) $user->uid, ['expires_at' => now()->subMinute()]);
        $this->device((int) $user->uid, ['revoked_at' => now()->subHour()]);

        $this->assertSame(1, TrustedDevice::query()->valid()->where('user_id', $user->uid)->count());

        $this->assertSame(2, app(TrustedDeviceService::class)->revokeAll($user), 'valid and expired rows are revoked, the revoked one is left');
        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $user->uid)->count());
    }
}
