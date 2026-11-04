<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Reports database migration status and validates that the bs_* tables
 * this app relies on actually have the columns the code expects. Exists
 * because the production host (shared hosting) has no SSH/cron
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
                'priority' => fn (Blueprint $t) => $t->float('priority', 24)->default(1),
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
                'faellig_am' => fn (Blueprint $t) => $t->date('faellig_am')->nullable(),
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
                'nuliga_id' => fn (Blueprint $t) => $t->unsignedInteger('nuliga_id')->nullable()->unique(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->unique(),
                'firstname' => fn (Blueprint $t) => $t->string('firstname', 128),
                'lastname' => fn (Blueprint $t) => $t->string('lastname', 128),
                'birthdate' => fn (Blueprint $t) => $t->date('birthdate')->nullable(),
                'gender' => fn (Blueprint $t) => $t->string('gender', 1)->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email', 128)->nullable(),
                'phone' => fn (Blueprint $t) => $t->string('phone', 64)->nullable(),
                'street' => fn (Blueprint $t) => $t->string('street', 255)->nullable(),
                'zip' => fn (Blueprint $t) => $t->string('zip', 10)->nullable(),
                'city' => fn (Blueprint $t) => $t->string('city', 128)->nullable(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete(),
                'joined_at' => fn (Blueprint $t) => $t->date('joined_at'),
                'left_at' => fn (Blueprint $t) => $t->date('left_at')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status')->default('aktiv'),
                'anonymized_at' => fn (Blueprint $t) => $t->timestamp('anonymized_at')->nullable(),
                'notiz' => fn (Blueprint $t) => $t->text('notiz')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'member_dues' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->constrained()->cascadeOnDelete(),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 8, 2),
                'status' => fn (Blueprint $t) => $t->string('status')->default('ausstehend'),
                'teilbetrag' => fn (Blueprint $t) => $t->decimal('teilbetrag', 8, 2)->nullable(),
                'kommentar' => fn (Blueprint $t) => $t->text('kommentar')->nullable(),
                'bezahlt_bis_fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('bezahlt_bis_fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete(),
                'faellig_am' => fn (Blueprint $t) => $t->date('faellig_am')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'member_changes' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'admin_user_id' => fn (Blueprint $t) => $t->unsignedInteger('admin_user_id')->nullable()->index(),
                'action' => fn (Blueprint $t) => $t->string('action', 16),
                'before' => fn (Blueprint $t) => $t->json('before')->nullable(),
                'after' => fn (Blueprint $t) => $t->json('after'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            ],
            'club_roles' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name'),
                'is_board' => fn (Blueprint $t) => $t->boolean('is_board')->default(false),
                'tasks' => fn (Blueprint $t) => $t->text('tasks')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'member_club_role' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'club_role_id' => fn (Blueprint $t) => $t->foreignId('club_role_id')->constrained()->cascadeOnDelete(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'club_role_mail_category' => [
                'club_role_id' => fn (Blueprint $t) => $t->foreignId('club_role_id')->constrained()->cascadeOnDelete(),
                'mail_category_id' => fn (Blueprint $t) => $t->foreignId('mail_category_id')->constrained('mail_categories')->cascadeOnDelete(),
            ],
            'mail_templates' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name'),
                'subject' => fn (Blueprint $t) => $t->string('subject'),
                'body' => fn (Blueprint $t) => $t->text('body'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'mail_categories' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 128)->unique(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'mail_sends' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'sent_by_user_id' => fn (Blueprint $t) => $t->unsignedInteger('sent_by_user_id')->nullable(),
                'audience_key' => fn (Blueprint $t) => $t->string('audience_key', 128),
                'audience_label' => fn (Blueprint $t) => $t->string('audience_label', 255),
                'recipient_count' => fn (Blueprint $t) => $t->unsignedInteger('recipient_count')->default(0),
                'from_name' => fn (Blueprint $t) => $t->string('from_name', 128),
                'from_address' => fn (Blueprint $t) => $t->string('from_address', 128),
                'subject' => fn (Blueprint $t) => $t->string('subject', 255),
                'body' => fn (Blueprint $t) => $t->text('body'),
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
            'login_history' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'ip' => fn (Blueprint $t) => $t->string('ip', 45),
                'country_code' => fn (Blueprint $t) => $t->string('country_code', 2)->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 16),
                'risk_flags' => fn (Blueprint $t) => $t->string('risk_flags', 128)->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable()->index(),
            ],
        ];
    }

    public function knowsTable(string $table): bool
    {
        return array_key_exists($table, self::columnDefinitions());
    }

    /**
     * Tables Laravel/queue/session infrastructure may create that are
     * intentionally not part of this app's manifest — new app tables should
     * never be added here, only framework-owned ones.
     *
     * @return array<int, string>
     */
    private static function frameworkTables(): array
    {
        return [
            'migrations', 'jobs', 'job_batches', 'failed_jobs',
            'sessions', 'cache', 'cache_locks',
            'password_reset_tokens', 'personal_access_tokens',
        ];
    }

    /**
     * Real tables in the database that this manifest doesn't know about —
     * i.e. a migration created a table but columnDefinitions() was never
     * updated for it. Used to keep the admin/database page (and its
     * automated test) honest as new tables/modules are added.
     *
     * @return array<int, string>
     */
    public function untrackedTables(): array
    {
        $known = array_merge(array_keys(self::columnDefinitions()), self::frameworkTables());

        return array_values(array_diff(
            array_map(static fn (array $table): string => $table['name'], Schema::getTables()),
            $known,
        ));
    }

    /**
     * Composite (multi-column) indexes/constraints that live outside the
     * per-column closures in columnDefinitions() — mirrored from later
     * migrations' table-level $table->index([...])/unique([...]) calls
     * (composite index migrations + the module unique-per-fiscal-year ones).
     *
     * @return array<string, array<int, Closure(Blueprint): mixed>>
     */
    private static function tableLevelConstraints(): array
    {
        // Explicit short names: MySQL caps identifiers at 64 chars, and the
        // default auto-generated name (table + all columns + suffix) can tip
        // over that limit once probeSignature() prefixes the table name with
        // "__drift_probe__". normalizeIndexes() ignores names anyway, so any
        // short unique name is fine here.
        return [
            'bs_users_meta' => [fn (Blueprint $t) => $t->index(['uid', 'key'], 'dp_bs_users_meta_uid_key')],
            'bs_squares_meta' => [fn (Blueprint $t) => $t->index(['sid', 'key'], 'dp_bs_squares_meta_sid_key')],
            'bs_bookings_meta' => [fn (Blueprint $t) => $t->index(['bid', 'key'], 'dp_bs_bookings_meta_bid_key')],
            'bs_events_meta' => [fn (Blueprint $t) => $t->index(['eid', 'key'], 'dp_bs_events_meta_eid_key')],
            'bs_bookings' => [fn (Blueprint $t) => $t->index(['sid', 'status'], 'dp_bs_bookings_sid_status')],
            'bs_reservations' => [fn (Blueprint $t) => $t->index(['date', 'bid'], 'dp_bs_reservations_date_bid')],
            'fee_category_rates' => [fn (Blueprint $t) => $t->unique(['fee_category_id', 'fiscal_year_id'], 'dp_fee_category_rates_unique')],
            'member_dues' => [fn (Blueprint $t) => $t->unique(['member_id', 'fiscal_year_id'], 'dp_member_dues_unique')],
            'payment_dues' => [fn (Blueprint $t) => $t->unique(['payment_id', 'member_due_id'], 'dp_payment_dues_unique')],
            'mail_sends' => [
                fn (Blueprint $t) => $t->index('sent_by_user_id', 'dp_mail_sends_sent_by_user_id'),
                fn (Blueprint $t) => $t->index('audience_key', 'dp_mail_sends_audience_key'),
            ],
            'login_history' => [
                fn (Blueprint $t) => $t->index(['user_id', 'created_at'], 'dp_login_history_user_created'),
            ],
            'club_role_mail_category' => [
                fn (Blueprint $t) => $t->primary(['club_role_id', 'mail_category_id']),
            ],
        ];
    }

    /**
     * Builds a throwaway table from the same closures used to validate a real
     * table, so the expected column/index signature is always computed by the
     * current DB driver (SQLite in tests, MySQL in production) instead of a
     * hardcoded type string that would only be correct for one driver.
     *
     * @param  array<string, Closure(Blueprint): mixed>  $definitions
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function probeSignature(string $table, array $definitions): array
    {
        $probeTable = "__drift_probe__{$table}";
        Schema::dropIfExists($probeTable);

        try {
            Schema::create($probeTable, function (Blueprint $blueprint) use ($definitions, $table): void {
                foreach ($definitions as $define) {
                    $define($blueprint);
                }

                foreach (self::tableLevelConstraints()[$table] ?? [] as $constraint) {
                    $constraint($blueprint);
                }
            });

            return [Schema::getColumns($probeTable), Schema::getIndexes($probeTable)];
        } finally {
            Schema::dropIfExists($probeTable);
        }
    }

    /**
     * Compares every existing table against its expected signature (built via
     * probeSignature()) and reports what doesn't match. Missing tables are
     * intentionally not reported here — checkTables() already covers those.
     *
     * @return array<int, array<string, mixed>>
     */
    public function driftReport(): array
    {
        $findings = [];

        foreach (self::columnDefinitions() as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                [$expectedColumns, $expectedIndexes] = $this->probeSignature($table, $definitions);
            } catch (Throwable $e) {
                $findings[] = ['table' => $table, 'kind' => 'drift_check_failed', 'message' => $e->getMessage()];

                continue;
            }

            $expectedByName = collect($expectedColumns)->keyBy('name');

            foreach (Schema::getColumns($table) as $column) {
                $expected = $expectedByName->get($column['name']);

                if ($expected === null) {
                    $findings[] = ['table' => $table, 'kind' => 'extra_column', 'column' => $column['name']];

                    continue;
                }

                if ($column['type'] !== $expected['type']
                    || $column['nullable'] !== $expected['nullable']
                    || $column['default'] !== $expected['default']
                ) {
                    $findings[] = [
                        'table' => $table,
                        'kind' => 'type_mismatch',
                        'column' => $column['name'],
                        'expected' => $this->describeColumn($expected),
                        'actual' => $this->describeColumn($column),
                    ];
                }
            }

            $actualIndexSignatures = $this->normalizeIndexes(Schema::getIndexes($table));
            $expectedIndexSignatures = $this->normalizeIndexes($expectedIndexes);

            foreach (array_diff($expectedIndexSignatures, $actualIndexSignatures) as $signature) {
                $findings[] = ['table' => $table, 'kind' => 'missing_index', 'signature' => $signature];
            }

            foreach (array_diff($actualIndexSignatures, $expectedIndexSignatures) as $signature) {
                $findings[] = ['table' => $table, 'kind' => 'extra_index', 'signature' => $signature];
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function describeColumn(array $column): string
    {
        $nullable = $column['nullable'] ? 'nullable' : 'not null';
        $default = $column['default'] !== null ? ", default {$column['default']}" : '';

        return "{$column['type']} {$nullable}{$default}";
    }

    /**
     * Reduces an index list to a comparable signature (sorted columns + unique
     * flag) since the auto-generated index name differs between the probe
     * table and the real table (they have different table-name prefixes).
     * Primary keys are excluded — already covered by column comparison.
     *
     * @param  array<int, array<string, mixed>>  $indexes
     * @return array<int, string>
     */
    private function normalizeIndexes(array $indexes): array
    {
        $signatures = [];

        foreach ($indexes as $index) {
            if ($index['primary']) {
                continue;
            }

            $columns = $index['columns'];
            sort($columns);
            $signatures[] = implode(',', $columns).'|'.($index['unique'] ? 'unique' : 'index');
        }

        sort($signatures);

        return $signatures;
    }

    /**
     * Creates the table (via its create migration) or adds missing columns.
     * Only ever adds what is missing — never drops or alters existing columns.
     *
     * @return array<int, string> human-readable list of actions performed
     */
    public function repairTable(string $table): array
    {
        $definitions = self::columnDefinitions()[$table] ?? null;

        if ($definitions === null) {
            throw new InvalidArgumentException("Unknown table [{$table}].");
        }

        if (! Schema::hasTable($table)) {
            return [$this->createTableFromMigration($table)];
        }

        $missing = array_values(array_diff(array_keys($definitions), Schema::getColumnListing($table)));

        if ($missing === []) {
            return [];
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definitions, $missing): void {
            foreach ($missing as $column) {
                ($definitions[$column])($blueprint);
            }
        });

        return array_map(
            static fn (string $column): string => "Added column {$column} to {$table}.",
            $missing,
        );
    }

    private function createTableFromMigration(string $table): string
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge(
            [database_path('migrations')],
            $migrator->paths(),
        ));

        $name = null;
        $path = null;
        foreach ($files as $migrationName => $migrationPath) {
            if (str_ends_with($migrationName, "create_{$table}_table")) {
                [$name, $path] = [$migrationName, $migrationPath];
                break;
            }
        }

        if ($name === null) {
            throw new RuntimeException("No create migration found for table [{$table}].");
        }

        $migration = require $path;
        $migration->up();

        $repository = $migrator->getRepository();
        if (! in_array($name, $repository->getRan(), true)) {
            $repository->log($name, $repository->getNextBatchNumber());
        }

        return "Created table {$table} by running migration {$name}.";
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
