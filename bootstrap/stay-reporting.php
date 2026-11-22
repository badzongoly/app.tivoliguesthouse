<?php
// Read-only compatibility adapter: short sessions are not overnight room-nights.
function stay_reporting_exclusion(string $alias): string
{
    if(!in_array($alias,['b','bookings'],true))throw new RuntimeException('Invalid booking query alias.');
    $r=access_service()->repo;
    if(!$r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='stay_schema'")->fetchColumn())return '';
    if(!$r->run('SELECT version FROM stay_schema WHERE version=2')->fetchColumn())return '';
    return " AND NOT EXISTS(SELECT 1 FROM stay_details stay_d WHERE stay_d.booking_id=$alias.id AND stay_d.stay_type='short') ";
}
