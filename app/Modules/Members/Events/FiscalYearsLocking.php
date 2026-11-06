<?php

declare(strict_types=1);

namespace App\Modules\Members\Events;

/**
 * Dispatched by FiscalYear::lockForWrite() right before the year rows are locked inside a write
 * transaction. Nothing in the app listens to it; it marks the point where a concurrent close
 * would race with the write (tests use it to close a year exactly there).
 */
final class FiscalYearsLocking
{
    /** @param  list<int>  $fiscalYearIds */
    public function __construct(public readonly array $fiscalYearIds) {}
}
