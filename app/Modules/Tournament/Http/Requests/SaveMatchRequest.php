<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The posted match form, read safely.
 *
 * Deliberately without validation rules: Laravel would validate before the controller runs, so a stranger
 * would see field errors instead of the refusal. ZF2's order is kept instead: write permission, the lock on
 * a confirmed result, then the input (MatchResultService). This class only turns the request into plain
 * strings: array-shaped input (`s1a[]=6`) never reaches the score rules as an array.
 */
class SaveMatchRequest extends FormRequest
{
    /** Score fields of the form (ZF2 names). */
    public const SCORE_FIELDS = ['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'];

    public function authorize(): bool
    {
        return true; // write permission is decided by MatchWriteRule after the match is resolved
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array{s1a: string|null, s1b: string|null, s2a: string|null, s2b: string|null, mtba: string|null, mtbb: string|null, wo: 'a'|'b'|null, date: string, time: string, court: '1'|'2'|'3'|null}
     */
    public function matchInput(): array
    {
        $input = [];

        foreach (self::SCORE_FIELDS as $field) {
            $value = $this->post($field);
            // A non-string (array) value is kept as an invalid entry, so ScoreService rejects it with its own text.
            $input[$field] = match (true) {
                $value === null => null,
                is_string($value) => $value,
                default => '?',
            };
        }

        $wo = $this->text('wo');
        $court = $this->text('court');

        $input['wo'] = in_array($wo, ['a', 'b'], true) ? $wo : null;
        $input['date'] = trim($this->text('date'));
        $input['time'] = trim($this->text('time'));
        $input['court'] = in_array($court, ['1', '2', '3'], true) ? $court : null;

        return $input;
    }

    private function text(string $key): string
    {
        $value = $this->post($key);

        return is_string($value) ? $value : '';
    }
}
