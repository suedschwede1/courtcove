<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Preflight;

final readonly class PreflightResult
{
    public const PASS = 'PASS';

    public const WARN = 'WARN';

    public const FAIL = 'FAIL';

    public const SKIP = 'SKIP';

    public function __construct(
        public string $status,
        public string $check,
        public string $detail = '',
    ) {}

    public static function pass(string $check, string $detail = ''): self
    {
        return new self(self::PASS, $check, $detail);
    }

    public static function warn(string $check, string $detail = ''): self
    {
        return new self(self::WARN, $check, $detail);
    }

    public static function fail(string $check, string $detail = ''): self
    {
        return new self(self::FAIL, $check, $detail);
    }

    public static function skip(string $check, string $detail = ''): self
    {
        return new self(self::SKIP, $check, $detail);
    }

    public function line(): string
    {
        return "[{$this->status}] {$this->check}".($this->detail === '' ? '' : ": {$this->detail}");
    }
}
