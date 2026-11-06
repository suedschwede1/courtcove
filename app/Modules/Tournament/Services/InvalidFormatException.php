<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

/** A tournament format configuration is malformed or violates its own rules (messages as in ZF2). */
final class InvalidFormatException extends \RuntimeException {}
