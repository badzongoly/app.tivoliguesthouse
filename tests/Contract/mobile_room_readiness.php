<?php
require dirname(__DIR__,2).'/src/Service/DepartureIncidents.php';
require dirname(__DIR__,2).'/src/Domain/Stay/StayRules.php';
require dirname(__DIR__,2).'/src/Service/StayService.php';
use Tivoli\Service\StayService;
$room = ['type'=>'Standard','status'=>'vacant','ready_state'=>'ready','rate'=>'300.00','short_enabled'=>1,'short_rate'=>'120.00'];
$cases = [
    [$room, true],
    [array_replace($room,['type'=>'Service']), false],
    [array_replace($room,['status'=>'dirty']), false],
    [array_replace($room,['ready_state'=>'awaiting_inspection']), false],
    [array_replace($room,['booking_id'=>12]), false],
    [array_replace($room,['blocked'=>true]), false],
    [array_replace($room,['rate'=>'0.00','short_enabled'=>0]), false],
    [array_replace($room,['rate'=>'0.00']), true],
    [array_replace($room,['rate'=>null,'short_rate'=>'invalid']), false],
    [array_replace($room,['rate'=>null]), true],
];
foreach ($cases as $index => [$candidate,$expected]) {
    if (StayService::checkInReady($candidate) !== $expected) throw new RuntimeException('Readiness case '.$index);
}
echo count($cases)." readiness cases passed\n";
