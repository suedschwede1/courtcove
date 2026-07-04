<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports database migration status and validates that the bs_* tables
 * this app relies on actually have the columns the code expects. Exists
 * because the production host (hosting.example.test shared hosting) has no SSH/cron
 * access — this is consumed by an admin page instead of `artisan migrate:status`.
 */
final class DatabaseSchemaChecker
{
    /**
     * Expected column definitions per table, mirrored from database/migrations/*
     * and the module migrations (app/Modules/<Name>/Database/Migrations). Kept as
     * a flat manifest (not derived from the migrations at runtime) so this works
     * without a second DB connection or extra PHP extensions on shared hosting.
     * The closures let repairTable() add a missing column with the right type.
     *
     * @return array<string, array<string, Closure(Blueprint): mixed>>
     */
    private static function columnDefinitions(): array
    {
        return [
            'bs_users' => [
                'uid' => fn (Blueprint $t) => $t->increments('uid'),
                'alias' => fn (Blueprint $t) => $t->string('alias', 128)->index(),
                'status' => fn (Blueprint $t) => $t->string('status', 64)->default('placeholder'),
                'email' => fn (Blueprint $t) => $t->string('email', 128)->nullable()->index(),
                'pw' => fn (Blueprint $t) => $t->string('pw', 256)->nullable(),
                'login_attempts' => fn (Blueprint $t) => $t->unsignedTinyInteger('login_attempts')->nullable(),
                'login_detent' => fn (Blueprint $t) => $t->dateTime('login_detent')->nullable(),
                'last_activity' => fn (Blueprint $t) => $t->dateTime('last_activity')->nullable(),
                'last_ip' => fn (Blueprint $t) => $t->string('last_ip', 64)->nullable(),
                'created' => fn (Blueprint $t) => $t->dateTime('created')->nullable(),
            ],
            'bs_users_meta' => [
                'umid' => fn (Blueprint $t) => $t->increments('umid'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->index(),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
            ],
            'bs_squares' => [
                'sid' => fn (Blueprint $t) => $t->increments('sid'),
                'name' => fn (Blueprint $t) => $t->string('name', 64),
                'status' => fn (Blueprint $t) => $t->string('status', 64)->default('enabled'),
                'priority' => fn (Blueprint $t) => $t->float('priority')->default(1),
                'capacity' => fn (Blueprint $t) => $t->unsignedInteger('capacity'),
                'capacity_heterogenic' => fn (Blueprint $t) => $t->boolean('capacity_heterogenic'),
                'allow_notes' => fn (Blueprint $t) => $t->boolean('allow_notes')->default(false),
                'time_start' => fn (Blueprint $t) => $t->time('time_start'),
                'time_end' => fn (Blueprint $t) => $t->time('time_end'),
                'time_block' => fn (Blueprint $t) => $t->unsignedInteger('time_block'),
                'time_block_bookable' => fn (Blueprint $t) => $t->unsignedInteger('time_block_bookable'),
                'time_block_bookable_max' => fn (Blueprint $t) => $t->unsignedInteger('time_block_bookable_max')->nullable(),
                'min_range_book' => fn (Blueprint $t) => $t->unsignedInteger('min_range_book')->default(0),
                'range_book' => fn (Blueprint $t) => $t->unsignedInteger('range_book')->nullable(),
                'max_active_bookings' => fn (Blueprint $t) => $t->unsignedInteger('max_active_bookings')->default(0),
                'range_cancel' => fn (Blueprint $t) => $t->unsignedInteger('range_cancel')->nullable(),
            ],
            'bs_squares_meta' => [
                'smid' => fn (Blueprint $t) => $t->increments('smid'),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->index(),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
                'locale' => fn (Blueprint $t) => $t->string('locale', 8)->nullable(),
            ],
            'bs_squares_products' => [
                'spid' => fn (Blueprint $t) => $t->increments('spid'),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->nullable()->index(),
                'priority' => fn (Blueprint $t) => $t->unsignedInteger('priority'),
                'date_start' => fn (Blueprint $t) => $t->date('date_start')->nullable(),
                'date_end' => fn (Blueprint $t) => $t->date('date_end')->nullable(),
                'name' => fn (Blueprint $t) => $t->string('name', 128),
                'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
                'options' => fn (Blueprint $t) => $t->string('options', 512),
                'price' => fn (Blueprint $t) => $t->unsignedInteger('price'),
                'rate' => fn (Blueprint $t) => $t->unsignedInteger('rate'),
                'gross' => fn (Blueprint $t) => $t->boolean('gross'),
                'locale' => fn (Blueprint $t) => $t->string('locale', 8)->nullable(),
            ],
            'bs_squares_pricing' => [
                'spid' => fn (Blueprint $t) => $t->increments('spid'),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->nullable()->index(),
                'priority' => fn (Blueprint $t) => $t->unsignedInteger('priority'),
                'date_start' => fn (Blueprint $t) => $t->date('date_start'),
                'date_end' => fn (Blueprint $t) => $t->date('date_end'),
                'day_start' => fn (Blueprint $t) => $t->unsignedTinyInteger('day_start')->nullable(),
                'day_end' => fn (Blueprint $t) => $t->unsignedTinyInteger('day_end')->nullable(),
                'time_start' => fn (Blueprint $t) => $t->time('time_start')->nullable(),
                'time_end' => fn (Blueprint $t) => $t->time('time_end')->nullable(),
                'price' => fn (Blueprint $t) => $t->unsignedInteger('price')->nullable(),
                'rate' => fn (Blueprint $t) => $t->unsignedInteger('rate')->nullable(),
                'gross' => fn (Blueprint $t) => $t->boolean('gross')->nullable(),
                'per_time_block' => fn (Blueprint $t) => $t->unsignedInteger('per_time_block')->nullable(),
                'per_quantity' => fn (Blueprint $t) => $t->boolean('per_quantity')->nullable(),
            ],
            'bs_squares_coupons' => [
                'scid' => fn (Blueprint $t) => $t->increments('scid'),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->nullable()->index(),
                'code' => fn (Blueprint $t) => $t->string('code', 64)->index(),
                'date_start' => fn (Blueprint $t) => $t->dateTime('date_start')->nullable(),
                'date_end' => fn (Blueprint $t) => $t->dateTime('date_end')->nullable(),
                'discount_for_booking' => fn (Blueprint $t) => $t->unsignedInteger('discount_for_booking'),
                'discount_for_products' => fn (Blueprint $t) => $t->unsignedInteger('discount_for_products'),
                'discount_in_percent' => fn (Blueprint $t) => $t->boolean('discount_in_percent'),
            ],
            'bs_bookings' => [
                'bid' => fn (Blueprint $t) => $t->increments('bid'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->index(),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->index(),
                'status' => fn (Blueprint $t) => $t->string('status', 64),
                'status_billing' => fn (Blueprint $t) => $t->string('status_billing', 64),
                'visibility' => fn (Blueprint $t) => $t->string('visibility', 64),
                'quantity' => fn (Blueprint $t) => $t->unsignedInteger('quantity'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_bookings_bills' => [
                'bbid' => fn (Blueprint $t) => $t->increments('bbid'),
                'bid' => fn (Blueprint $t) => $t->unsignedInteger('bid')->index(),
                'description' => fn (Blueprint $t) => $t->string('description', 512),
                'quantity' => fn (Blueprint $t) => $t->unsignedInteger('quantity')->nullable(),
                'time' => fn (Blueprint $t) => $t->unsignedInteger('time')->nullable(),
                'price' => fn (Blueprint $t) => $t->integer('price'),
                'rate' => fn (Blueprint $t) => $t->unsignedInteger('rate'),
                'gross' => fn (Blueprint $t) => $t->boolean('gross'),
            ],
            'bs_bookings_meta' => [
                'bmid' => fn (Blueprint $t) => $t->increments('bmid'),
                'bid' => fn (Blueprint $t) => $t->unsignedInteger('bid')->index(),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
            ],
            'bs_reservations' => [
                'rid' => fn (Blueprint $t) => $t->increments('rid'),
                'bid' => fn (Blueprint $t) => $t->unsignedInteger('bid')->index(),
                'date' => fn (Blueprint $t) => $t->date('date')->index(),
                'time_start' => fn (Blueprint $t) => $t->time('time_start'),
                'time_end' => fn (Blueprint $t) => $t->time('time_end'),
            ],
            'bs_events' => [
                'eid' => fn (Blueprint $t) => $t->increments('eid'),
                'sid' => fn (Blueprint $t) => $t->unsignedInteger('sid')->nullable()->index(),
                'status' => fn (Blueprint $t) => $t->string('status', 64)->default('enabled'),
                'datetime_start' => fn (Blueprint $t) => $t->dateTime('datetime_start')->index(),
                'datetime_end' => fn (Blueprint $t) => $t->dateTime('datetime_end')->index(),
                'capacity' => fn (Blueprint $t) => $t->unsignedInteger('capacity')->nullable(),
            ],
            'bs_events_meta' => [
                'emid' => fn (Blueprint $t) => $t->increments('emid'),
                'eid' => fn (Blueprint $t) => $t->unsignedInteger('eid')->index(),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
                'locale' => fn (Blueprint $t) => $t->string('locale', 8)->nullable(),
            ],
            'bs_options' => [
                'oid' => fn (Blueprint $t) => $t->increments('oid'),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
                'locale' => fn (Blueprint $t) => $t->string('locale', 8)->nullable(),
            ],
            'bs_reservations_meta' => [
                'rmid' => fn (Blueprint $t) => $t->increments('rmid'),
                'rid' => fn (Blueprint $t) => $t->unsignedInteger('rid')->index(),
                'key' => fn (Blueprint $t) => $t->string('key', 64)->index(),
                'value' => fn (Blueprint $t) => $t->text('value'),
            ],
            'fiscal_years' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 32),
                'starts_on' => fn (Blueprint $t) => $t->date('starts_on'),
                'ends_on' => fn (Blueprint $t) => $t->date('ends_on'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'fee_categories' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 64),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'fee_category_rates' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->constrained()->cascadeOnDelete(),
                'fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete(),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 8, 2),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'members' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_number' => fn (Blueprint $t) => $t->unsignedInteger('member_number')->unique(),
                'firstname' => fn (Blueprint $t) => $t->string('firstname', 128),
                'lastname' => fn (Blueprint $t) => $t->string('lastname', 128),
                'birthdate' => fn (Blueprint $t) => $t->date('birthdate')->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email', 128)->nullable(),
                'phone' => fn (Blueprint $t) => $t->string('phone', 64)->nullable(),
                'address' => fn (Blueprint $t) => $t->string('address', 255)->nullable(),
                'joined_at' => fn (Blueprint $t) => $t->date('joined_at'),
                'left_at' => fn (Blueprint $t) => $t->date('left_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'member_dues' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->constrained()->cascadeOnDelete(),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 8, 2),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'payments' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 8, 2),
                'paid_at' => fn (Blueprint $t) => $t->date('paid_at'),
                'note' => fn (Blueprint $t) => $t->string('note', 255)->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'payment_dues' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'payment_id' => fn (Blueprint $t) => $t->foreignId('payment_id')->constrained()->cascadeOnDelete(),
                'member_due_id' => fn (Blueprint $t) => $t->foreignId('member_due_id')->constrained()->cascadeOnDelete(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
        ];
    }

    public function knowsTable(string $table): bool
    {
        return array_key_exists($table, self::columnDefinitions());
    }

    /**
     * @return array<int, array{name: string, ran: bool}>
     */
    public function migrationStatus(): array
    {
        $migrator = app('migrator');

        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $files = $migrator->getMigrationFiles(array_merge(
            [database_path('migrations')],
            $migrator->paths(),
        ));

        return array_map(
            static fn (string $name): array => ['name' => $name, 'ran' => in_array($name, $ran, true)],
            array_keys($files),
        );
    }

    public function hasPendingMigrations(): bool
    {
        foreach ($this->migrationStatus() as $migration) {
            if (! $migration['ran']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{table: string, exists: bool, missing_columns: array<int, string>}>
     */
    public function checkTables(): array
    {
        $results = [];

        foreach (self::columnDefinitions() as $table => $definitions) {
            $expectedColumns = array_keys($definitions);
            $exists = Schema::hasTable($table);
            $missing = $exists
                ? array_values(array_diff($expectedColumns, Schema::getColumnListing($table)))
                : $expectedColumns;

            $results[] = [
                'table' => $table,
                'exists' => $exists,
                'missing_columns' => $missing,
            ];
        }

        return $results;
    }
}
