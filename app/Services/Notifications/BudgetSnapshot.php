<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/** Usage of the current month against the budget; all money values in millionths of the currency unit. */
final readonly class BudgetSnapshot
{
    /**
     * @param  array{messages: int, estimatedMicros: int}|null  $previousMonth  null when the month has no row
     */
    public function __construct(
        public string $month,
        public int $messages,
        public int $estimatedMicros,
        public int $budgetMicros,
        public int $percent,
        public ?array $previousMonth,
    ) {}
}
