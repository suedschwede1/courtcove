<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reads the manual commit form safely. No validation rules on purpose: DrawCommitService
 * collects the errors in the old order and flashes all of them. Array-shaped or missing fields
 * become empty strings, so `title[]=x` is refused like an empty title instead of a 500.
 * Access is checked by the route middleware (can:admin.draw) before this runs.
 */
final class CommitDrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array{title: string, entries: string, slots: string, rule: string, round: string, tid: string}
     */
    public function commitInput(): array
    {
        $input = [];

        foreach (['title', 'entries', 'slots', 'rule', 'round', 'tid'] as $key) {
            $value = $this->input($key);
            $input[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $input;
    }
}
