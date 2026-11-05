<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

/**
 * A translatable rule decision: a `pyramid::check.*` key plus its placeholders.
 *
 * Kept as data (not a rendered sentence) so the viewer's locale decides the wording.
 */
final readonly class Verdict
{
    /**
     * @param  array<string, scalar|null>  $replace
     */
    public function __construct(public string $key, public array $replace = []) {}

    public function message(?string $locale = null): string
    {
        return __($this->key, $this->replace, $locale);
    }
}
