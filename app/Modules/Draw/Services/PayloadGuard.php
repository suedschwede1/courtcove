<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\TournamentBase\Support\ActionRefused;

/**
 * Refuses payloads that PHP commits to but the public Python verifier (tools/verify_draw.py)
 * cannot reproduce byte for byte:
 *
 *  - U+2028/U+2029: PHP's JSON_UNESCAPED_UNICODE still escapes them, Python's ensure_ascii=False does not;
 *  - purely numeric slot names: PHP turns the keys "0".."n-1" into a JSON list, Python keeps an object;
 *  - invalid UTF-8: canonical() cannot encode it at all.
 *
 * Refusing (instead of normalising) keeps the published text exactly what the admin entered.
 */
final class PayloadGuard
{
    private const SEPARATORS = ["\u{2028}", "\u{2029}"];

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ActionRefused
     */
    public function assertVerifierSafe(array $payload): void
    {
        $strings = [];

        if (isset($payload['rule']) && is_string($payload['rule'])) {
            $strings[] = $payload['rule'];
        }
        foreach ((array) ($payload['entries'] ?? []) as $entry) {
            if (is_string($entry)) {
                $strings[] = $entry;
            }
        }
        $slots = array_map('strval', (array) ($payload['slots'] ?? []));
        array_push($strings, ...$slots);

        foreach ($strings as $text) {
            if (! mb_check_encoding($text, 'UTF-8')) {
                throw new ActionRefused('draw::admin.commit.invalid_utf8');
            }
        }

        foreach ($strings as $text) {
            if (str_contains($text, self::SEPARATORS[0]) || str_contains($text, self::SEPARATORS[1])) {
                throw new ActionRefused('draw::admin.commit.line_separator');
            }
        }

        foreach ($slots as $slot) {
            if (preg_match('/^[0-9]+$/', $slot) === 1) {
                throw new ActionRefused('draw::admin.commit.numeric_slot');
            }
        }
    }
}
