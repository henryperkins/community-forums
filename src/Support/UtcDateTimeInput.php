<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/** Strict UTC input: native datetime-local values and the existing form contract. */
final class UtcDateTimeInput
{
    public static function parse(string $value): ?DateTimeImmutable
    {
        $format = match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value) === 1 => 'Y-m-d\\TH:i',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D', $value) === 1 => 'Y-m-d\\TH:i:s',
            preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) === 1 => 'Y-m-d H:i:s',
            default => null,
        };
        if ($format === null || (int) substr($value, 0, 4) < 1000) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format($format) !== $value) {
            return null;
        }
        return $date;
    }
}
