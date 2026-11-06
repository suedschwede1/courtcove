<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a player exchange action is refused; the message is user-visible (translated). */
final class PlayerExchangeException extends RuntimeException {}
