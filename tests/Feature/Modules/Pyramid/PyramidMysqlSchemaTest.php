<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Compares the migrated bs_pyramid_* columns with the live DDL of the old
 * application (sql/pyramid_schema.sql): type, nullability, default, enum sets and
 * charset. Read-only against information_schema; runs with composer test:mysql.
 */
#[Group('mysql')]
class PyramidMysqlSchemaTest extends MysqlTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }
    }

    /**
     * column => [COLUMN_TYPE, nullable, default (null = no default / NULL)]
     *
     * @return array<string, array<string, array{string, bool, ?string}>>
     */
    private function expected(): array
    {
        return [
            'bs_pyramid_ladders' => [
                'id' => ['int unsigned', false, null],
                'title' => ['varchar(160)', false, null],
                'season' => ['smallint unsigned', false, null],
                'category' => ['varchar(40)', true, null],
                'status' => ["enum('setup','running','closed')", false, 'setup'],
                'itn_default' => ['decimal(3,1)', false, '10.0'],
                'days_to_play' => ['smallint unsigned', false, '7'],
                'winner_protected_days' => ['smallint unsigned', false, '3'],
                'loser_cooldown_days' => ['smallint unsigned', false, '7'],
                'is_default' => ['tinyint(1)', false, '0'],
                'rules_version' => ['varchar(40)', true, null],
                'created' => ['datetime', false, null],
            ],
            'bs_pyramid_players' => [
                'id' => ['int unsigned', false, null],
                'ladder_id' => ['int unsigned', false, null],
                'uid' => ['int unsigned', true, null],
                'label' => ['varchar(120)', false, null],
                'itn' => ['decimal(3,1)', true, null],
                'itn_rated' => ['decimal(3,1)', false, null],
                'position' => ['smallint unsigned', true, null],
                'status' => ["enum('waiting','entering','active','paused','removed')", false, 'waiting'],
                'gender' => ["enum('m','f')", true, null],
                'age' => ['tinyint unsigned', true, null],
                'phone' => ['varchar(40)', true, null],
                'email' => ['varchar(128)', true, null],
                'joined' => ['datetime', false, null],
                'note' => ['varchar(160)', true, null],
            ],
            'bs_pyramid_challenges' => [
                'id' => ['int unsigned', false, null],
                'ladder_id' => ['int unsigned', false, null],
                'challenger_id' => ['int unsigned', false, null],
                'opponent_id' => ['int unsigned', false, null],
                'challenger_pos' => ['smallint unsigned', true, null],
                'opponent_pos' => ['smallint unsigned', true, null],
                'status' => ["enum('open','scheduled','played','declined','withdrawn','forfeited','expired')", false, 'open'],
                'created' => ['datetime', false, null],
                'deadline' => ['date', true, null],
                'scheduled_at' => ['datetime', true, null],
                'score' => ['varchar(40)', true, null],
                'winner_id' => ['int unsigned', true, null],
                'entered_uid' => ['int unsigned', true, null],
                'entered' => ['datetime', true, null],
                'decline_reason' => ["enum('other_match','injury')", true, null],
                'admin_note' => ['varchar(160)', true, null],
                'resolved' => ['datetime', true, null],
            ],
            'bs_pyramid_events' => [
                'id' => ['int unsigned', false, null],
                'ladder_id' => ['int unsigned', false, null],
                'challenge_id' => ['int unsigned', true, null],
                'type' => ["enum('challenge','schedule','result','join','admin')", false, null],
                'actor_uid' => ['int unsigned', true, null],
                'payload' => ['text', false, null],
                'created' => ['datetime', false, null],
            ],
            'bs_pyramid_grants' => [
                'id' => ['int unsigned', false, null],
                'ladder_id' => ['int unsigned', false, null],
                'uid' => ['int unsigned', false, null],
                'role' => ["enum('admin')", false, 'admin'],
                'created' => ['datetime', false, null],
            ],
            'bs_pyramid_schema_versions' => [
                'version' => ['int unsigned', false, null],
                'applied' => ['datetime', false, null],
            ],
        ];
    }

    #[Test]
    public function columns_match_the_live_ddl(): void
    {
        foreach ($this->expected() as $table => $columns) {
            $rows = DB::select(
                'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS dflt,
                        CHARACTER_SET_NAME AS charset
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                  ORDER BY ORDINAL_POSITION',
                [$table],
            );

            $this->assertSame(array_keys($columns), array_map(fn (object $r): string => $r->name, $rows), $table);

            foreach ($rows as $row) {
                [$type, $nullable, $default] = $columns[$row->name];
                $label = $table.'.'.$row->name;

                $this->assertSame($type, strtolower($row->type), $label.' type');
                $this->assertSame($nullable, $row->nullable === 'YES', $label.' nullable');
                $this->assertSame($default, $row->dflt === null ? null : trim((string) $row->dflt, "'"), $label.' default');

                if ($row->charset !== null) {
                    $this->assertSame('utf8mb4', $row->charset, $label.' charset');
                }
            }
        }
    }

    #[Test]
    public function tables_use_innodb_and_utf8mb4_unicode_ci(): void
    {
        foreach (array_keys($this->expected()) as $table) {
            $row = DB::selectOne(
                'SELECT t.ENGINE AS engine, t.TABLE_COLLATION AS collation, c.CHARACTER_SET_NAME AS charset
                   FROM information_schema.TABLES t
                   JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY c ON c.COLLATION_NAME = t.TABLE_COLLATION
                  WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = ?',
                [$table],
            );

            $this->assertSame('InnoDB', $row->engine, $table);
            $this->assertSame('utf8mb4', $row->charset, $table);
            $this->assertSame('utf8mb4_unicode_ci', $row->collation, $table);
        }
    }
}
