<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use RuntimeException;

/**
 * A manual commit was refused. Carries every collected error in the old order as
 * [translation key, replacements]; the controller flashes all of them (ZF2 flashed each one).
 */
final class CommitRefused extends RuntimeException
{
    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(', ', array_column($errors, 0)));
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (array $error): string => (string) __($error[0], $error[1]), $this->errors);
    }
}
