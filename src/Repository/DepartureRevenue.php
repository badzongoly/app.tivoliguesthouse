<?php
namespace Tivoli\Repository;

/** One room-revenue source for ordinary departures and owner-reviewed incidents. */
final class DepartureRevenue
{
    public static function sql(bool $finance): string
    {
        $ordinary='SELECT d.booking_id,d.room_minor,d.departed_at posting_at,0 is_adjustment FROM stay_departures d';
        if(!$finance)return $ordinary;
        return $ordinary." WHERE NOT EXISTS(SELECT 1 FROM finance_events e
            WHERE e.kind='incident_room_revenue' AND e.target_type='booking'
            AND e.target_id=CAST(d.booking_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)
            UNION ALL SELECT CAST(e.target_id AS UNSIGNED),e.amount_minor,e.created_at,0
            FROM finance_events e WHERE e.kind='incident_room_revenue' AND e.target_type='booking'
            UNION ALL SELECT CAST(g.target_id AS UNSIGNED),g.amount_minor,g.created_at,1 FROM finance_events g
            WHERE g.kind='gc_posted' AND g.target_type='booking' AND JSON_UNQUOTE(JSON_EXTRACT(g.payload,'$.charge_type'))='room'";
    }
}
