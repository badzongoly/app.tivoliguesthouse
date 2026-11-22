<?php
namespace Tivoli\Domain\Stay;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class StayRules
{
    public static function minor(string $value): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new RuntimeException('Enter a non-negative amount with at most two decimal places.');
        }
        return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    }

    public static function decimal(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value),100).'.'.str_pad((string)(abs($value)%100),2,'0',STR_PAD_LEFT);
    }

    public static function nights(string $start, string $end): int
    {
        $zone = new DateTimeZone('UTC');
        $in = new DateTimeImmutable($start, $zone);
        $out = new DateTimeImmutable($end, $zone);
        $first = $in->modify('+1 day')->setTime(12,0);
        $seconds = $out->getTimestamp() - $first->getTimestamp();
        return $seconds <= 0 ? 1 : 1 + intdiv($seconds + 86399, 86400);
    }

    public static function text(mixed $value, int $max, bool $required = false): string
    {
        if (!is_string($value)) { throw new RuntimeException('Invalid text input.'); }
        $value = trim($value);
        // Original ledger tables use utf8mb3. Do not silently truncate or change charset.
        if (!mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$max || ($required && $value==='') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]|[\x{10000}-\x{10FFFF}]/u',$value)) {
            throw new RuntimeException('Missing, invalid or overly long text.');
        }
        return $value;
    }

    public static function quantity(string $value): int
    {
        return self::minor($value); // Hundredths of a unit, preserving existing fractional stock.
    }

    public static function line(int $quantity, int $price): int
    {
        if ($quantity > 9999999999 || $price > 999999999999 || $quantity < 0 || $price < 0) {
            throw new RuntimeException('Quantity or price exceeds the supported limit.');
        }
        if ($quantity && $price > intdiv(PHP_INT_MAX-50,$quantity)) { throw new RuntimeException('Line total exceeds the supported limit.'); }
        $value = intdiv($quantity*$price+50,100); // Per-line half-up to GHS minor units.
        if ($value > 999999999999) { throw new RuntimeException('Line total exceeds the ledger limit.'); }
        return $value;
    }
}
