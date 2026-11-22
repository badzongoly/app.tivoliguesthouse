<?php
namespace Tivoli\Repository;

/** Operational reports leave the original stay untouched until final owner review. */
final class DepartureFacts
{
    public static function source(bool $finance): string
    {
        if(!$finance)return 'SELECT booking.*,booking.status operational_status,booking.checkout_at actual_checkout FROM bookings booking';
        $reported=self::reported('booking');
        return "SELECT booking.*,CASE WHEN $reported THEN 'checked_out' ELSE booking.status END operational_status,
            CASE WHEN booking.status='active' AND $reported THEN (SELECT NULLIF(JSON_UNQUOTE(JSON_EXTRACT(report.payload,'$.actual_departed_at')),'null')
                FROM finance_events report WHERE report.kind='departure_reported' AND report.target_type='booking'
                AND report.target_id=CAST(booking.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                ORDER BY report.id DESC LIMIT 1) ELSE booking.checkout_at END actual_checkout FROM bookings booking";
    }

    public static function reported(string $alias): string
    {
        if(!in_array($alias,['b','a','booking'],true))throw new \RuntimeException('Invalid booking alias.');
        return "EXISTS(SELECT 1 FROM finance_events report WHERE report.kind='departure_reported'
            AND report.target_type='booking'
            AND report.target_id=CAST($alias.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            AND NOT EXISTS(SELECT 1 FROM finance_events retract WHERE retract.kind='departure_retracted'
                AND retract.target_type='departure_incident'
                AND retract.target_id=CAST(report.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)
            AND report.id=(SELECT MAX(latest.id) FROM finance_events latest WHERE latest.kind='departure_reported'
                AND latest.target_type='booking' AND latest.target_id=report.target_id))";
    }
}
