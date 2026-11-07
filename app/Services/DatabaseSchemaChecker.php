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
                'role' => fn (Blueprint $t) => $t->string('role', 16)->default('member'),
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
            'bs_draws' => [
                'did' => fn (Blueprint $t) => $t->increments('did'),
                'tid' => fn (Blueprint $t) => $t->unsignedInteger('tid')->nullable()->index(),
                'slug' => fn (Blueprint $t) => $t->string('slug', 32)->unique(),
                'title' => fn (Blueprint $t) => $t->string('title', 160),
                'status' => fn (Blueprint $t) => $t->enum('status', ['committed', 'picking', 'closed', 'executed'])->default('committed'),
                'mode' => fn (Blueprint $t) => $t->enum('mode', ['direct', 'token'])->default('token'),
                'payload' => fn (Blueprint $t) => $t->mediumText('payload'),
                'commitment' => fn (Blueprint $t) => $t->char('commitment', 64),
                'beacon_source' => fn (Blueprint $t) => $t->string('beacon_source', 255),
                'pick_deadline' => fn (Blueprint $t) => $t->dateTime('pick_deadline')->nullable(),
                'closed' => fn (Blueprint $t) => $t->dateTime('closed')->nullable(),
                'server_seed' => fn (Blueprint $t) => $t->char('server_seed', 64)->nullable(),
                'beacon_value' => fn (Blueprint $t) => $t->string('beacon_value', 255)->nullable(),
                'final_seed' => fn (Blueprint $t) => $t->char('final_seed', 64)->nullable(),
                'result' => fn (Blueprint $t) => $t->mediumText('result')->nullable(),
                'created_uid' => fn (Blueprint $t) => $t->unsignedInteger('created_uid'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
                'executed' => fn (Blueprint $t) => $t->dateTime('executed')->nullable(),
                'discarded' => fn (Blueprint $t) => $t->dateTime('discarded')->nullable(),
                'discarded_reason' => fn (Blueprint $t) => $t->string('discarded_reason', 160)->nullable(),
                'applied_at' => fn (Blueprint $t) => $t->dateTime('applied_at')->nullable(),
                'applied_uid' => fn (Blueprint $t) => $t->unsignedInteger('applied_uid')->nullable(),
            ],
            'bs_draw_tokens' => [
                'dtid' => fn (Blueprint $t) => $t->increments('dtid'),
                'did' => fn (Blueprint $t) => $t->unsignedInteger('did'),
                'position' => fn (Blueprint $t) => $t->unsignedSmallInteger('position'),
                'token' => fn (Blueprint $t) => $t->string('token', 16),
                'slot' => fn (Blueprint $t) => $t->string('slot', 8)->nullable(),
                'claimed_tpid' => fn (Blueprint $t) => $t->unsignedInteger('claimed_tpid')->nullable(),
                'claimed_uid' => fn (Blueprint $t) => $t->unsignedInteger('claimed_uid')->nullable(),
                'claimed' => fn (Blueprint $t) => $t->dateTime('claimed')->nullable(),
                'assigned_by' => fn (Blueprint $t) => $t->enum('assigned_by', ['pick', 'fallback'])->nullable(),
            ],
            'bs_draw_eligible' => [
                'deid' => fn (Blueprint $t) => $t->increments('deid'),
                'did' => fn (Blueprint $t) => $t->unsignedInteger('did'),
                'tpid' => fn (Blueprint $t) => $t->unsignedInteger('tpid'),
            ],
            'bs_pyramid_ladders' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'title' => fn (Blueprint $t) => $t->string('title', 160),
                'season' => fn (Blueprint $t) => $t->unsignedSmallInteger('season'),
                'category' => fn (Blueprint $t) => $t->string('category', 40)->nullable(),
                'status' => fn (Blueprint $t) => $t->enum('status', ['setup', 'running', 'closed'])->default('setup'),
                'itn_default' => fn (Blueprint $t) => $t->decimal('itn_default', 3, 1)->default(10.0),
                'days_to_play' => fn (Blueprint $t) => $t->unsignedSmallInteger('days_to_play')->default(7),
                'winner_protected_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('winner_protected_days')->default(3),
                'loser_cooldown_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('loser_cooldown_days')->default(7),
                'is_default' => fn (Blueprint $t) => $t->boolean('is_default')->default(0),
                'rules_version' => fn (Blueprint $t) => $t->string('rules_version', 40)->nullable(),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_pyramid_players' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'ladder_id' => fn (Blueprint $t) => $t->unsignedInteger('ladder_id'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->nullable(),
                'label' => fn (Blueprint $t) => $t->string('label', 120),
                'itn' => fn (Blueprint $t) => $t->decimal('itn', 3, 1)->nullable(),
                'itn_rated' => fn (Blueprint $t) => $t->decimal('itn_rated', 3, 1),
                'position' => fn (Blueprint $t) => $t->unsignedSmallInteger('position')->nullable(),
                'status' => fn (Blueprint $t) => $t->enum('status', ['waiting', 'entering', 'active', 'paused', 'removed'])->default('waiting'),
                'gender' => fn (Blueprint $t) => $t->enum('gender', ['m', 'f'])->nullable(),
                'age' => fn (Blueprint $t) => $t->unsignedTinyInteger('age')->nullable(),
                'phone' => fn (Blueprint $t) => $t->string('phone', 40)->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email', 128)->nullable(),
                'joined' => fn (Blueprint $t) => $t->dateTime('joined'),
                'note' => fn (Blueprint $t) => $t->string('note', 160)->nullable(),
            ],
            'bs_pyramid_challenges' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'ladder_id' => fn (Blueprint $t) => $t->unsignedInteger('ladder_id'),
                'challenger_id' => fn (Blueprint $t) => $t->unsignedInteger('challenger_id')->index(),
                'opponent_id' => fn (Blueprint $t) => $t->unsignedInteger('opponent_id')->index(),
                'challenger_pos' => fn (Blueprint $t) => $t->unsignedSmallInteger('challenger_pos')->nullable(),
                'opponent_pos' => fn (Blueprint $t) => $t->unsignedSmallInteger('opponent_pos')->nullable(),
                'status' => fn (Blueprint $t) => $t->enum('status', ['open', 'scheduled', 'played', 'declined', 'withdrawn', 'forfeited', 'expired'])->default('open'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
                'deadline' => fn (Blueprint $t) => $t->date('deadline')->nullable(),
                'scheduled_at' => fn (Blueprint $t) => $t->dateTime('scheduled_at')->nullable(),
                'score' => fn (Blueprint $t) => $t->string('score', 40)->nullable(),
                'winner_id' => fn (Blueprint $t) => $t->unsignedInteger('winner_id')->nullable(),
                'entered_uid' => fn (Blueprint $t) => $t->unsignedInteger('entered_uid')->nullable(),
                'entered' => fn (Blueprint $t) => $t->dateTime('entered')->nullable(),
                'decline_reason' => fn (Blueprint $t) => $t->enum('decline_reason', ['other_match', 'injury'])->nullable(),
                'admin_note' => fn (Blueprint $t) => $t->string('admin_note', 160)->nullable(),
                'resolved' => fn (Blueprint $t) => $t->dateTime('resolved')->nullable(),
            ],
            'bs_pyramid_events' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'ladder_id' => fn (Blueprint $t) => $t->unsignedInteger('ladder_id'),
                'challenge_id' => fn (Blueprint $t) => $t->unsignedInteger('challenge_id')->nullable(),
                'type' => fn (Blueprint $t) => $t->enum('type', ['challenge', 'schedule', 'result', 'join', 'admin']),
                'actor_uid' => fn (Blueprint $t) => $t->unsignedInteger('actor_uid')->nullable(),
                'payload' => fn (Blueprint $t) => $t->text('payload'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_pyramid_grants' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'ladder_id' => fn (Blueprint $t) => $t->unsignedInteger('ladder_id')->index(),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid'),
                'role' => fn (Blueprint $t) => $t->enum('role', ['admin'])->default('admin'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_pyramid_schema_versions' => [
                'version' => fn (Blueprint $t) => $t->unsignedInteger('version')->primary(),
                'applied' => fn (Blueprint $t) => $t->dateTime('applied'),
            ],
            'bs_mixed_events' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'title' => fn (Blueprint $t) => $t->string('title', 160),
                'event_date' => fn (Blueprint $t) => $t->date('event_date'),
                'status' => fn (Blueprint $t) => $t->enum('status', ['draft', 'running', 'done'])->default('draft'),
                'is_default' => fn (Blueprint $t) => $t->boolean('is_default')->default(0),
                'is_test' => fn (Blueprint $t) => $t->boolean('is_test')->default(0),
                'court_count' => fn (Blueprint $t) => $t->unsignedSmallInteger('court_count')->default(4),
                'mode' => fn (Blueprint $t) => $t->enum('mode', ['americano', 'mexicano'])->default('americano'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_mixed_participants' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'event_id' => fn (Blueprint $t) => $t->unsignedInteger('event_id'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->nullable(),
                'label' => fn (Blueprint $t) => $t->string('label', 120),
                'gender' => fn (Blueprint $t) => $t->enum('gender', ['m', 'f'])->nullable(),
                'itn' => fn (Blueprint $t) => $t->decimal('itn', 3, 1)->nullable(),
                'age' => fn (Blueprint $t) => $t->unsignedTinyInteger('age')->nullable(),
                'status' => fn (Blueprint $t) => $t->enum('status', ['active', 'paused'])->default('active'),
                'no_extra_match' => fn (Blueprint $t) => $t->boolean('no_extra_match')->default(0),
            ],
            'bs_mixed_rounds' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'event_id' => fn (Blueprint $t) => $t->unsignedInteger('event_id'),
                'round_no' => fn (Blueprint $t) => $t->unsignedSmallInteger('round_no'),
                'group_size_mode' => fn (Blueprint $t) => $t->enum('group_size_mode', ['4_only', '4_and_5'])->default('4_only'),
                'distribution' => fn (Blueprint $t) => $t->enum('distribution', ['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'])->default('random'),
                'status' => fn (Blueprint $t) => $t->enum('status', ['proposed', 'confirmed', 'completed'])->default('proposed'),
            ],
            'bs_mixed_round_players' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'round_id' => fn (Blueprint $t) => $t->unsignedInteger('round_id'),
                'participant_id' => fn (Blueprint $t) => $t->unsignedInteger('participant_id'),
                'group_no' => fn (Blueprint $t) => $t->unsignedTinyInteger('group_no')->nullable(),
            ],
            'bs_mixed_matches' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'round_id' => fn (Blueprint $t) => $t->unsignedInteger('round_id'),
                'group_no' => fn (Blueprint $t) => $t->unsignedTinyInteger('group_no'),
                'match_no' => fn (Blueprint $t) => $t->unsignedTinyInteger('match_no'),
                'team_a_p1' => fn (Blueprint $t) => $t->unsignedInteger('team_a_p1'),
                'team_a_p2' => fn (Blueprint $t) => $t->unsignedInteger('team_a_p2'),
                'team_b_p1' => fn (Blueprint $t) => $t->unsignedInteger('team_b_p1'),
                'team_b_p2' => fn (Blueprint $t) => $t->unsignedInteger('team_b_p2'),
                'games_a' => fn (Blueprint $t) => $t->unsignedTinyInteger('games_a')->nullable(),
                'games_b' => fn (Blueprint $t) => $t->unsignedTinyInteger('games_b')->nullable(),
                'is_extra' => fn (Blueprint $t) => $t->boolean('is_extra')->default(0),
            ],
            'bs_mixed_avoid_pairs' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'event_id' => fn (Blueprint $t) => $t->unsignedInteger('event_id'),
                'participant_a' => fn (Blueprint $t) => $t->unsignedInteger('participant_a'),
                'participant_b' => fn (Blueprint $t) => $t->unsignedInteger('participant_b'),
            ],
            'bs_mixed_members' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'label' => fn (Blueprint $t) => $t->string('label', 120)->index(),
                'gender' => fn (Blueprint $t) => $t->enum('gender', ['m', 'f'])->nullable(),
                'itn' => fn (Blueprint $t) => $t->decimal('itn', 3, 1)->nullable(),
                'birth_date' => fn (Blueprint $t) => $t->date('birth_date')->nullable(),
            ],
            'bs_mixed_event_grants' => [
                'id' => fn (Blueprint $t) => $t->increments('id'),
                'event_id' => fn (Blueprint $t) => $t->unsignedInteger('event_id')->index(),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->index(),
                'role' => fn (Blueprint $t) => $t->enum('role', ['score', 'admin']),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_mixed_schema_versions' => [
                'version' => fn (Blueprint $t) => $t->unsignedInteger('version')->primary(),
                'applied' => fn (Blueprint $t) => $t->dateTime('applied'),
            ],
            'bs_tournaments' => [
                'tid' => fn (Blueprint $t) => $t->increments('tid'),
                'title' => fn (Blueprint $t) => $t->string('title', 160),
                'season' => fn (Blueprint $t) => $t->unsignedSmallInteger('season'),
                'is_test' => fn (Blueprint $t) => $t->boolean('is_test')->default(0),
                'cloned_from' => fn (Blueprint $t) => $t->unsignedInteger('cloned_from')->nullable(),
                'status' => fn (Blueprint $t) => $t->enum('status', ['setup', 'drawn', 'running', 'finished'])->default('setup'),
                'active' => fn (Blueprint $t) => $t->boolean('active')->default(0)->index(),
                'format' => fn (Blueprint $t) => $t->mediumText('format')->nullable(),
                'lot_orders' => fn (Blueprint $t) => $t->mediumText('lot_orders')->nullable(),
                'itn_default' => fn (Blueprint $t) => $t->decimal('itn_default', 3, 1)->default(10.0),
                'points_per_win' => fn (Blueprint $t) => $t->unsignedTinyInteger('points_per_win')->default(2),
                'tiebreak_scope' => fn (Blueprint $t) => $t->enum('tiebreak_scope', ['tied', 'all'])->default('tied'),
                'runnerup_mode' => fn (Blueprint $t) => $t->enum('runnerup_mode', ['drop_last', 'points', 'per_match', 'lot'])->default('drop_last'),
                'created' => fn (Blueprint $t) => $t->dateTime('created'),
            ],
            'bs_tournament_players' => [
                'tpid' => fn (Blueprint $t) => $t->increments('tpid'),
                'tid' => fn (Blueprint $t) => $t->unsignedInteger('tid'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid')->nullable(),
                'label' => fn (Blueprint $t) => $t->string('label', 120),
                'gender' => fn (Blueprint $t) => $t->enum('gender', ['m', 'w'])->nullable(),
                'itn' => fn (Blueprint $t) => $t->decimal('itn', 3, 1)->nullable(),
                'itn_rated' => fn (Blueprint $t) => $t->decimal('itn_rated', 3, 1),
                'seed' => fn (Blueprint $t) => $t->unsignedSmallInteger('seed')->nullable(),
                'grp' => fn (Blueprint $t) => $t->char('grp', 1)->nullable(),
                'grp_pos' => fn (Blueprint $t) => $t->unsignedTinyInteger('grp_pos')->nullable(),
                'points_adjust' => fn (Blueprint $t) => $t->tinyInteger('points_adjust')->default(0),
                'phone' => fn (Blueprint $t) => $t->string('phone', 40)->nullable(),
                'partner_tpid' => fn (Blueprint $t) => $t->unsignedInteger('partner_tpid')->nullable()->index(),
                'note' => fn (Blueprint $t) => $t->string('note', 160)->nullable(),
            ],
            'bs_tournament_matches' => [
                'tmid' => fn (Blueprint $t) => $t->increments('tmid'),
                'tid' => fn (Blueprint $t) => $t->unsignedInteger('tid'),
                'phase' => fn (Blueprint $t) => $t->string('phase', 16),
                'mkey' => fn (Blueprint $t) => $t->string('mkey', 12)->nullable(),
                'grp' => fn (Blueprint $t) => $t->char('grp', 1)->nullable(),
                'rnd' => fn (Blueprint $t) => $t->unsignedTinyInteger('rnd')->nullable(),
                'slot' => fn (Blueprint $t) => $t->unsignedSmallInteger('slot')->nullable(),
                'tpid_a' => fn (Blueprint $t) => $t->unsignedInteger('tpid_a')->nullable(),
                'tpid_b' => fn (Blueprint $t) => $t->unsignedInteger('tpid_b')->nullable(),
                'deadline' => fn (Blueprint $t) => $t->date('deadline')->nullable(),
                'played' => fn (Blueprint $t) => $t->date('played')->nullable(),
                'played_time' => fn (Blueprint $t) => $t->time('played_time')->nullable(),
                'court' => fn (Blueprint $t) => $t->enum('court', ['1', '2', '3'])->nullable(),
                'score' => fn (Blueprint $t) => $t->string('score', 40)->nullable(),
                's1a' => fn (Blueprint $t) => $t->unsignedTinyInteger('s1a')->nullable(),
                's1b' => fn (Blueprint $t) => $t->unsignedTinyInteger('s1b')->nullable(),
                's2a' => fn (Blueprint $t) => $t->unsignedTinyInteger('s2a')->nullable(),
                's2b' => fn (Blueprint $t) => $t->unsignedTinyInteger('s2b')->nullable(),
                'mtba' => fn (Blueprint $t) => $t->unsignedTinyInteger('mtba')->nullable(),
                'mtbb' => fn (Blueprint $t) => $t->unsignedTinyInteger('mtbb')->nullable(),
                'sets_a' => fn (Blueprint $t) => $t->unsignedTinyInteger('sets_a')->nullable(),
                'sets_b' => fn (Blueprint $t) => $t->unsignedTinyInteger('sets_b')->nullable(),
                'games_a' => fn (Blueprint $t) => $t->unsignedSmallInteger('games_a')->nullable(),
                'games_b' => fn (Blueprint $t) => $t->unsignedSmallInteger('games_b')->nullable(),
                'retired' => fn (Blueprint $t) => $t->enum('retired', ['none', 'ret_a', 'ret_b', 'wo_a', 'wo_b'])->default('none'),
                'winner' => fn (Blueprint $t) => $t->unsignedInteger('winner')->nullable(),
                'entered_uid' => fn (Blueprint $t) => $t->unsignedInteger('entered_uid')->nullable(),
                'entered' => fn (Blueprint $t) => $t->dateTime('entered')->nullable(),
                'confirmed_uid' => fn (Blueprint $t) => $t->unsignedInteger('confirmed_uid')->nullable(),
                'confirmed' => fn (Blueprint $t) => $t->dateTime('confirmed')->nullable(),
            ],
            'bs_tournament_lines' => [
                'tlid' => fn (Blueprint $t) => $t->increments('tlid'),
                'tid' => fn (Blueprint $t) => $t->unsignedInteger('tid'),
                'phase' => fn (Blueprint $t) => $t->string('phase', 16),
                'line' => fn (Blueprint $t) => $t->unsignedSmallInteger('line'),
                'kind' => fn (Blueprint $t) => $t->enum('kind', ['seed', 'bye', 'pool']),
                'seed_no' => fn (Blueprint $t) => $t->unsignedTinyInteger('seed_no')->nullable(),
                'pool' => fn (Blueprint $t) => $t->string('pool', 16)->nullable(),
                'placeholder' => fn (Blueprint $t) => $t->string('placeholder', 16)->nullable(),
                'tpid' => fn (Blueprint $t) => $t->unsignedInteger('tpid')->nullable(),
            ],
            'fiscal_years' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 32),
                'starts_on' => fn (Blueprint $t) => $t->date('starts_on'),
                'ends_on' => fn (Blueprint $t) => $t->date('ends_on'),
                'faellig_am' => fn (Blueprint $t) => $t->date('faellig_am')->nullable(),
                'abgeschlossen_am' => fn (Blueprint $t) => $t->timestamp('abgeschlossen_am')->nullable(),
                'abgeschlossen_von' => fn (Blueprint $t) => $t->unsignedInteger('abgeschlossen_von')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'fee_categories' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 64),
                'billing_unit' => fn (Blueprint $t) => $t->string('billing_unit', 16)->default('member'),
                'deactivated_at' => fn (Blueprint $t) => $t->timestamp('deactivated_at')->nullable(),
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
                'household_id' => fn (Blueprint $t) => $t->foreignId('household_id')->nullable()->index()->constrained('member_households')->nullOnDelete(),
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
                'bezahlt_am' => fn (Blueprint $t) => $t->date('bezahlt_am')->nullable(),
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
            'member_households' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 128)->nullable(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete(),
                'payer_member_id' => fn (Blueprint $t) => $t->foreignId('payer_member_id')->nullable()->constrained('members')->nullOnDelete(),
                'dissolved_at' => fn (Blueprint $t) => $t->timestamp('dissolved_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'household_dues' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'household_id' => fn (Blueprint $t) => $t->foreignId('household_id')->constrained('member_households')->restrictOnDelete(),
                'fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete(),
                'fee_category_id' => fn (Blueprint $t) => $t->foreignId('fee_category_id')->constrained()->cascadeOnDelete(),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 8, 2),
                'status' => fn (Blueprint $t) => $t->string('status')->default('ausstehend'),
                'teilbetrag' => fn (Blueprint $t) => $t->decimal('teilbetrag', 8, 2)->nullable(),
                'kommentar' => fn (Blueprint $t) => $t->text('kommentar')->nullable(),
                'bezahlt_bis_fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('bezahlt_bis_fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete(),
                'faellig_am' => fn (Blueprint $t) => $t->date('faellig_am')->nullable(),
                'bezahlt_am' => fn (Blueprint $t) => $t->date('bezahlt_am')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'household_due_member' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'household_due_id' => fn (Blueprint $t) => $t->foreignId('household_due_id')->constrained('household_dues')->cascadeOnDelete(),
                'member_id' => fn (Blueprint $t) => $t->foreignId('member_id')->constrained()->cascadeOnDelete(),
                'fiscal_year_id' => fn (Blueprint $t) => $t->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'household_due_payment' => [
                'payment_id' => fn (Blueprint $t) => $t->foreignId('payment_id')->constrained()->cascadeOnDelete(),
                'household_due_id' => fn (Blueprint $t) => $t->foreignId('household_due_id')->constrained('household_dues')->cascadeOnDelete(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'household_suggestion_dismissals' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'signature' => fn (Blueprint $t) => $t->char('signature', 64)->unique(),
                'dismissed_by_user_id' => fn (Blueprint $t) => $t->unsignedInteger('dismissed_by_user_id')->nullable(),
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
            'player_exchange_searches' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'bid' => fn (Blueprint $t) => $t->unsignedInteger('bid')->unique(),
                'needed' => fn (Blueprint $t) => $t->unsignedTinyInteger('needed'),
                'ends_at' => fn (Blueprint $t) => $t->dateTime('ends_at'),
                'status' => fn (Blueprint $t) => $t->string('status', 12)->default('open'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ],
            'player_exchange_signups' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'search_id' => fn (Blueprint $t) => $t->unsignedBigInteger('search_id'),
                'uid' => fn (Blueprint $t) => $t->unsignedInteger('uid'),
                'slot' => fn (Blueprint $t) => $t->unsignedTinyInteger('slot'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            ],
            'scheduled_task_runs' => [
                'id' => fn (Blueprint $t) => $t->id(),
                'name' => fn (Blueprint $t) => $t->string('name', 100),
                'command' => fn (Blueprint $t) => $t->string('command', 255)->nullable(),
                'started_at' => fn (Blueprint $t) => $t->dateTime('started_at'),
                'finished_at' => fn (Blueprint $t) => $t->dateTime('finished_at')->nullable(),
                'duration_ms' => fn (Blueprint $t) => $t->unsignedInteger('duration_ms')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 16),
                'exit_code' => fn (Blueprint $t) => $t->integer('exit_code')->nullable(),
                'output_excerpt' => fn (Blueprint $t) => $t->text('output_excerpt')->nullable(),
                'triggered_by' => fn (Blueprint $t) => $t->string('triggered_by', 16)->default('scheduler'),
                'user_id' => fn (Blueprint $t) => $t->unsignedInteger('user_id')->nullable(),
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
            'bs_draw_tokens' => [
                fn (Blueprint $t) => $t->unique(['did', 'token'], 'dp_bs_draw_tokens_did_token'),
                fn (Blueprint $t) => $t->unique(['did', 'position'], 'dp_bs_draw_tokens_did_position'),
                fn (Blueprint $t) => $t->unique(['did', 'claimed_tpid'], 'dp_bs_draw_tokens_did_claimed'),
                fn (Blueprint $t) => $t->index(['did', 'claimed_tpid'], 'dp_bs_draw_tokens_did_claimed_idx'),
            ],
            'bs_draw_eligible' => [fn (Blueprint $t) => $t->unique(['did', 'tpid'], 'dp_bs_draw_eligible_did_tpid')],
            'bs_pyramid_ladders' => [fn (Blueprint $t) => $t->index(['season', 'category'], 'dp_bs_pyramid_ladders_season_category')],
            'bs_pyramid_players' => [
                fn (Blueprint $t) => $t->unique(['ladder_id', 'uid'], 'dp_bs_pyramid_players_ladder_uid'),
                fn (Blueprint $t) => $t->unique(['ladder_id', 'position'], 'dp_bs_pyramid_players_ladder_position'),
                fn (Blueprint $t) => $t->index(['ladder_id', 'status'], 'dp_bs_pyramid_players_ladder_status'),
            ],
            'bs_pyramid_challenges' => [
                fn (Blueprint $t) => $t->index(['ladder_id', 'status'], 'dp_bs_pyramid_challenges_ladder_status'),
                fn (Blueprint $t) => $t->index(['ladder_id', 'deadline'], 'dp_bs_pyramid_challenges_ladder_deadline'),
            ],
            'bs_pyramid_events' => [fn (Blueprint $t) => $t->index(['ladder_id', 'created'], 'dp_bs_pyramid_events_ladder_created')],
            'bs_pyramid_grants' => [fn (Blueprint $t) => $t->unique(['ladder_id', 'uid'], 'dp_bs_pyramid_grants_ladder_uid')],
            'bs_mixed_participants' => [
                fn (Blueprint $t) => $t->unique(['event_id', 'uid'], 'dp_bs_mixed_participants_event_uid'),
                fn (Blueprint $t) => $t->index('event_id', 'dp_bs_mixed_participants_event_id'),
            ],
            'bs_mixed_rounds' => [fn (Blueprint $t) => $t->unique(['event_id', 'round_no'], 'dp_bs_mixed_rounds_event_round')],
            'bs_mixed_round_players' => [
                fn (Blueprint $t) => $t->index(['round_id', 'participant_id'], 'dp_bs_mixed_round_players_round_participant'),
                fn (Blueprint $t) => $t->index(['round_id', 'group_no'], 'dp_bs_mixed_round_players_round_group'),
            ],
            'bs_mixed_matches' => [fn (Blueprint $t) => $t->index(['round_id', 'group_no'], 'dp_bs_mixed_matches_round_group')],
            'bs_mixed_avoid_pairs' => [
                fn (Blueprint $t) => $t->unique(['event_id', 'participant_a', 'participant_b'], 'dp_bs_mixed_avoid_pairs_event_pair'),
                fn (Blueprint $t) => $t->index('event_id', 'dp_bs_mixed_avoid_pairs_event_id'),
            ],
            'bs_mixed_event_grants' => [fn (Blueprint $t) => $t->unique(['event_id', 'uid'], 'dp_bs_mixed_event_grants_event_uid')],
            'bs_tournament_players' => [
                fn (Blueprint $t) => $t->unique(['tid', 'uid'], 'tn_bs_tournament_players_tid_uid'),
                fn (Blueprint $t) => $t->index(['tid', 'grp'], 'tn_bs_tournament_players_tid_grp'),
            ],
            'bs_tournament_matches' => [
                fn (Blueprint $t) => $t->unique(['tid', 'mkey'], 'tn_bs_tournament_matches_tid_mkey'),
                fn (Blueprint $t) => $t->index(['tid', 'phase', 'grp'], 'tn_bs_tournament_matches_tid_phase_grp'),
                fn (Blueprint $t) => $t->index(['tid', 'deadline'], 'tn_bs_tournament_matches_tid_deadline'),
                fn (Blueprint $t) => $t->index(['tid', 'phase', 'rnd', 'slot'], 'tn_bs_tournament_matches_tid_phase_rnd_slot'),
            ],
            'bs_tournament_lines' => [
                fn (Blueprint $t) => $t->unique(['tid', 'phase', 'line'], 'tn_bs_tournament_lines_tid_phase_line'),
                fn (Blueprint $t) => $t->index(['tid', 'phase', 'tpid'], 'tn_bs_tournament_lines_tid_phase_tpid'),
            ],
            'fee_category_rates' => [fn (Blueprint $t) => $t->unique(['fee_category_id', 'fiscal_year_id'], 'dp_fee_category_rates_unique')],
            'member_dues' => [fn (Blueprint $t) => $t->unique(['member_id', 'fiscal_year_id'], 'dp_member_dues_unique')],
            'payment_dues' => [fn (Blueprint $t) => $t->unique(['payment_id', 'member_due_id'], 'dp_payment_dues_unique')],
            'household_dues' => [fn (Blueprint $t) => $t->unique(['household_id', 'fiscal_year_id'], 'dp_household_dues_unique')],
            'household_due_member' => [
                fn (Blueprint $t) => $t->unique(['household_due_id', 'member_id'], 'dp_household_due_member_unique'),
                fn (Blueprint $t) => $t->unique(['member_id', 'fiscal_year_id'], 'dp_household_due_member_year_unique'),
            ],
            'household_due_payment' => [fn (Blueprint $t) => $t->unique(['payment_id', 'household_due_id'], 'dp_household_due_payment_unique')],
            'mail_sends' => [
                fn (Blueprint $t) => $t->index('sent_by_user_id', 'dp_mail_sends_sent_by_user_id'),
                fn (Blueprint $t) => $t->index('audience_key', 'dp_mail_sends_audience_key'),
            ],
            'login_history' => [
                fn (Blueprint $t) => $t->index(['user_id', 'created_at'], 'dp_login_history_user_created'),
            ],
            'player_exchange_signups' => [
                fn (Blueprint $t) => $t->unique(['search_id', 'uid'], 'dp_player_exchange_signups_search_uid'),
                fn (Blueprint $t) => $t->unique(['search_id', 'slot'], 'dp_player_exchange_signups_search_slot'),
            ],
            'scheduled_task_runs' => [
                fn (Blueprint $t) => $t->index(['name', 'started_at'], 'dp_scheduled_task_runs_name_started'),
                fn (Blueprint $t) => $t->index(['status', 'started_at'], 'dp_scheduled_task_runs_status_started'),
                fn (Blueprint $t) => $t->index('started_at', 'dp_scheduled_task_runs_started'),
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
