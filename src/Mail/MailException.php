<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** Thrown when a transport fails to hand a message off for delivery. */
final class MailException extends RuntimeException
{
}
