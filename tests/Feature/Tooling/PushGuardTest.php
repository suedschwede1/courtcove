<?php

declare(strict_types=1);

namespace Tests\Feature\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Process tests for .githooks/lib/guard.sh (pre-push, pre-commit "staged", commit-msg "message").
 * Every test builds a throw-away git repository and uses dummy terms in a temporary list; no real names involved.
 */
final class PushGuardTest extends TestCase
{
    private const ZERO = '0000000000000000000000000000000000000000';

    private string $dir;

    private string $terms;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || trim((string) shell_exec('command -v bash')) === '' || trim((string) shell_exec('command -v git')) === '') {
            $this->markTestSkipped('needs bash and git');
        }
        $this->dir = sys_get_temp_dir().'/guard-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->terms = $this->dir.'.terms';
        file_put_contents($this->terms, "# dummy list for tests\nacme-dummy\nzz[0-9]{3}x\n");
        $this->sh('git init -q -b main && git config user.name "Dev One" && git config user.email dev@example.test');
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            $this->sh('rm -rf '.escapeshellarg($this->dir).' '.escapeshellarg($this->terms), sys_get_temp_dir());
        }
    }

    /** @return array{0:int,1:string} */
    private function sh(string $cmd, ?string $cwd = null, array $env = [], string $stdin = ''): array
    {
        $proc = proc_open(['bash', '-c', $cmd], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd ?? $this->dir, array_merge($_ENV, [
            'PATH' => getenv('PATH'),
            'HOME' => $this->dir.'-home',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'BOOKING_GUARD_NO_GITLEAKS' => '1',
        ], $env));
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), $out];
    }

    /** @param array<string,string> $files */
    private function commit(array $files, string $message = 'Add files', string $author = 'Dev One <dev@example.test>'): string
    {
        foreach ($files as $path => $content) {
            $full = $this->dir.'/'.$path;
            is_dir(dirname($full)) or mkdir(dirname($full), 0777, true);
            file_put_contents($full, $content);
        }
        $this->sh('git add -A && git commit -q --author='.escapeshellarg($author).' -m '.escapeshellarg($message));

        return trim($this->sh('git rev-parse HEAD')[1]);
    }

    /** @return array{0:int,1:string} */
    private function push(string $sha, array $env = [], ?string $remoteSha = null): array
    {
        $env += ['BOOKING_GUARD_TERMS_FILE' => $this->terms];

        return $this->sh('bash '.escapeshellarg(dirname(__DIR__, 3).'/.githooks/pre-push').' origin url', null, $env,
            "refs/heads/main $sha refs/heads/main ".($remoteSha ?? self::ZERO)."\n");
    }

    public function test_clean_push_passes(): void
    {
        $sha = $this->commit(['a.txt' => "hello\n", 'docs/x.md' => "see 203.0.113.7 and 192.168.1.5 and 127.0.0.1\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(0, $code, $out);
    }

    public function test_missing_term_list_blocks_without_naming_terms(): void
    {
        $sha = $this->commit(['a.txt' => "hello\n"]);
        [$code, $out] = $this->push($sha, ['BOOKING_GUARD_TERMS_FILE' => $this->dir.'.nope']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('term list is missing', $out);
        $this->assertStringContainsString('security-runbook', $out);
    }

    public function test_term_in_diff_is_blocked_and_reported_without_the_term(): void
    {
        $sha = $this->commit(['src/a.txt' => "one\nthe Acme-Dummy club\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('src/a.txt:2', $out);
        $this->assertStringContainsString('entry #1', $out);
        $this->assertStringNotContainsStringIgnoringCase('acme-dummy', $out);
    }

    public function test_second_entry_is_numbered(): void
    {
        $sha = $this->commit(['b.txt' => "x zz123x y\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('b.txt:1', $out);
        $this->assertStringContainsString('entry #2', $out);
        $this->assertStringNotContainsString('zz123x', $out);
    }

    public function test_term_in_commit_message_is_blocked(): void
    {
        $sha = $this->commit(['a.txt' => "ok\n"], "Fix things\n\nfor acme-dummy members");
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('message:3', $out);
        $this->assertStringNotContainsStringIgnoringCase('acme-dummy', $out);
    }

    public function test_term_in_author_name_or_mail_is_blocked(): void
    {
        $sha = $this->commit(['a.txt' => "ok\n"], 'Neutral message', 'Zed Acme-Dummy <someone@example.test>');
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('identity:', $out);

        $this->sh('git reset -q --hard HEAD~1');
        $sha2 = $this->commit(['b.txt' => "ok\n"], 'Neutral message', 'Zed <zed@acme-dummy.test>');
        [$code2] = $this->push($sha2);
        $this->assertSame(1, $code2);
    }

    public function test_term_in_file_name_is_blocked_and_name_is_withheld(): void
    {
        $sha = $this->commit(['notes-acme-dummy.txt' => "clean content\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('file name', $out);
        $this->assertStringNotContainsStringIgnoringCase('acme-dummy', $out);
    }

    public function test_generic_rules_block_secrets_and_public_ips(): void
    {
        $cases = [
            'app/a.php' => "<?php\n\$x = ['password' => 'Tr0ub4dor-and-more'];\n",
            'b.txt' => '-----BEGIN RSA '.'PRIVATE KEY-----'."\n",
            'c.conf' => 'server 8.8.4.'."4;\n",
            '.env' => "A=b\n",
            'dump.sql' => "select 1;\n",
            'id.pem' => "x\n",
            'bundle.zip' => "x\n",
        ];
        $this->commit(['base.txt' => "base\n"]);
        foreach ($cases as $path => $content) {
            $sha = $this->commit([$path => $content]);
            [$code, $out] = $this->push($sha);
            $this->assertSame(1, $code, "$path should be blocked: $out");
            $this->sh('git reset -q --hard HEAD~1');
        }
    }

    public function test_generic_rules_do_not_flag_placeholders_examples_and_fixtures(): void
    {
        $sha = $this->commit([
            '.env.example' => "DB_PASSWORD=\nAPP_KEY=\n",
            'README.md' => "DB_PASSWORD=REDACTED\nip 198.51.100.4 192.0.2.9 10.1.2.3 1.1.1.1\n",
            'tests/Fixtures/live-schema.sql' => "create table t (id int);\n",
            'tests/Feature/x.php' => "<?php\n\$u = ['password' => 'a-long-dummy-password'];\n",
        ]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(0, $code, $out);
    }

    public function test_allow_marker_exempts_generic_rules_only(): void
    {
        $sha = $this->commit(['a.txt' => "resolver 8.8.4.4 # guard:allow\n"]);
        $this->assertSame(0, $this->push($sha)[0]);
        $sha2 = $this->commit(['b.txt' => "acme-dummy # guard:allow\n"]);
        $this->assertSame(1, $this->push($sha2)[0]);
    }

    public function test_only_new_commits_of_an_existing_ref_are_scanned(): void
    {
        $base = $this->commit(['a.txt' => "acme-dummy\n"]); // would be blocked, but already on the remote
        $head = $this->commit(['b.txt' => "fine\n"]);
        [$code, $out] = $this->push($head, [], $base);
        $this->assertSame(0, $code, $out);
        $this->assertSame(1, $this->push($head)[0], 'a new branch is checked against an empty base');
    }

    public function test_multi_ref_push_needs_confirmation(): void
    {
        $sha = $this->commit(['a.txt' => "fine\n"]);
        $stdin = "refs/heads/main $sha refs/heads/main ".self::ZERO."\nrefs/heads/other $sha refs/heads/other ".self::ZERO."\n";
        $hook = 'bash '.escapeshellarg(dirname(__DIR__, 3).'/.githooks/pre-push').' origin url';
        [$code, $out] = $this->sh($hook, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms], $stdin);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('2 refs', $out);
        [$code2, $out2] = $this->sh($hook, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms, 'BOOKING_GUARD_ALLOW_MULTI' => '1'], $stdin);
        $this->assertSame(0, $code2, $out2);
    }

    public function test_deleting_a_ref_is_not_scanned(): void
    {
        $hook = 'bash '.escapeshellarg(dirname(__DIR__, 3).'/.githooks/pre-push').' origin url';
        [$code] = $this->sh($hook, null, ['BOOKING_GUARD_TERMS_FILE' => $this->dir.'.nope'], 'refs/heads/old '.self::ZERO.' refs/heads/old '.str_repeat('a', 40)."\n");
        $this->assertSame(0, $code);
    }

    public function test_staged_check_blocks_a_term_in_the_index(): void
    {
        $this->commit(['a.txt' => "fine\n"]);
        file_put_contents($this->dir.'/a.txt', "fine\nacme-dummy\n");
        $this->sh('git add a.txt');
        $guard = 'bash '.escapeshellarg(dirname(__DIR__, 3).'/.githooks/lib/guard.sh').' staged';
        [$code, $out] = $this->sh($guard, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('a.txt:2', $out);
        $this->assertStringNotContainsStringIgnoringCase('acme-dummy', $out);

        $this->sh('git reset -q a.txt');
        [$ok] = $this->sh($guard, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms]);
        $this->assertSame(0, $ok);
    }

    public function test_crlf_and_bom_in_the_term_list_still_match(): void
    {
        file_put_contents($this->terms, "\xEF\xBB\xBF# list\r\nacme-dummy\r\nzz[0-9]{3}x\r\n");
        $sha = $this->commit(['a.txt' => "acme-dummy\n"]);
        $this->assertSame(1, $this->push($sha)[0]);
    }

    public function test_invalid_or_empty_matching_term_entries_refuse(): void
    {
        $sha = $this->commit(['a.txt' => "fine\n"]);
        file_put_contents($this->terms, "acme-dummy\n(unclosed\n");
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('entry #2 is not a valid', $out);
        file_put_contents($this->terms, "acme-dummy\nx*\n");
        [$code2, $out2] = $this->push($sha);
        $this->assertSame(1, $code2);
        $this->assertStringContainsString('matches the empty string', $out2);
    }

    public function test_non_utf8_line_with_a_term_is_still_reported(): void
    {
        $sha = $this->commit(['a.txt' => "caf\xE9 acme-dummy\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('a.txt:1', $out);
    }

    public function test_added_line_starting_with_plus_signs_is_scanned(): void
    {
        $this->commit(['a.txt' => "one\n"]);
        $sha = $this->commit(['a.txt' => "one\n++ acme-dummy\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('a.txt:2', $out);
    }

    public function test_merge_commit_changes_are_checked(): void
    {
        $this->commit(['base.txt' => "base\n"]);
        $this->sh('git checkout -q -b side');
        $this->commit(['side.txt' => "side\n"]);
        $this->sh('git checkout -q main');
        $this->commit(['main.txt' => "main\n"]);
        $this->sh('git merge -q --no-ff --no-commit side');
        file_put_contents($this->dir.'/conflict-fix.txt', "acme-dummy\n");
        $this->sh('git add -A && git commit -q -m "Merge side"');
        $sha = trim($this->sh('git rev-parse HEAD')[1]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('conflict-fix.txt:1', $out);
    }

    public function test_path_that_contains_a_term_is_withheld_in_findings(): void
    {
        $sha = $this->commit(['acme-dummy/readme.txt' => "x\nzz123x\n"]);
        [$code, $out] = $this->push($sha);
        $this->assertSame(1, $code);
        $this->assertStringNotContainsStringIgnoringCase('acme-dummy', $out);
        $this->assertStringContainsString('<path withheld>:2', $out);
    }

    public function test_message_check(): void
    {
        file_put_contents($this->dir.'/msg', "Add thing\n# comment line acme-dummy\n");
        $guard = 'bash '.escapeshellarg(dirname(__DIR__, 3).'/.githooks/lib/guard.sh').' message msg';
        $this->assertSame(0, $this->sh($guard, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms])[0], 'git comment lines are ignored');
        file_put_contents($this->dir.'/msg', "Add thing for acme-dummy\n");
        $this->assertSame(1, $this->sh($guard, null, ['BOOKING_GUARD_TERMS_FILE' => $this->terms])[0]);
    }
}
