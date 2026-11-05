<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rules the app uses whose German text was missing showed the raw key ("validation.decimal"),
 * because the fallback locale is German as well.
 */
class GermanValidationMessagesTest extends TestCase
{
    #[Test]
    public function rules_used_by_the_app_have_german_messages(): void
    {
        app()->setLocale('de');

        $messages = Validator::make(
            ['amount' => '1.234', 'modes' => 'x', 'extra' => 'y'],
            ['amount' => 'decimal:0,2', 'modes' => 'list', 'extra' => 'prohibited'],
        )->errors()->all();

        $this->assertSame([
            'Das Feld amount muss 0-2 Nachkommastellen haben.',
            'Das Feld modes muss eine Liste sein.',
            'Das Feld extra ist nicht erlaubt.',
        ], $messages);
    }
}
