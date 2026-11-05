<?php

namespace Tests\Feature\Ui;

use App\Services\UiLinter;
use App\Services\UiRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthAccountLintTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function views(): array
    {
        return [
            'auth-shell' => ['resources/views/components/layout/auth-shell.blade.php'],
            'login' => ['resources/views/auth/login.blade.php'],
        ]; // later tasks append
    }

    #[Test]
    #[DataProvider('views')]
    public function view_has_no_ui_lint_findings(string $path): void
    {
        $findings = array_filter(
            (new UiLinter([resource_path('views')], UiRules::all(), base_path()))->run(),
            fn (array $f): bool => $f['file'] === $path,
        );
        $this->assertSame([], array_values($findings));
    }
}
