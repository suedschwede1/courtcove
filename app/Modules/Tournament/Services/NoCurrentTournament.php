<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

/** There is no real (non-test) tournament configured. */
final class NoCurrentTournament extends \RuntimeException {}
