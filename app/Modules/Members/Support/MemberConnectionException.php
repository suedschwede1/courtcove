<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use DomainException;

/** An Anschlussmitglied rule was violated; the message is already translated and can be shown as is. */
final class MemberConnectionException extends DomainException {}
