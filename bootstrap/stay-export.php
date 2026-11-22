<?php
// Export uses the same frozen/current bill as the account screen; no legacy rate guessing.
function stay_export_csv(int $actor): void
{
    $r=access_service()->repo;$service=stay_service();$bookingSource=\Tivoli\Repository\DepartureFacts::source((new \Tivoli\Service\FinanceService(access_service()))->installed());$where=["b.operational_status<>'active'"];$values=[];
    if(isset($_GET['room_id'])){$id=filter_var($_GET['room_id'],FILTER_VALIDATE_INT);if($id===false||$id<0)throw new RuntimeException('Invalid room filter.');if($id>0){$where[]='b.room_id=?';$values[]=$id;}}
    if(in_array($_GET['status'] ?? '',['checked_out','cancelled'],true)){$where[]='b.operational_status=?';$values[]=$_GET['status'];}
    foreach(['start'=>'>=','end'=>'<='] as $key=>$operator){if(empty($_GET[$key]))continue;$date=$_GET[$key];$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Invalid export date.');$where[]='COALESCE(b.actual_checkout,b.checkin_at) '.$operator.' ?';$values[]=$date.($key==='start'?' 00:00:00':' 23:59:59');}
    $rows=$r->run('SELECT b.*,r.name room_name,COALESCE(d.stay_type,\'overnight\') stay_type FROM ('.$bookingSource.') b JOIN rooms r ON r.id=b.room_id LEFT JOIN stay_details d ON d.booking_id=b.id WHERE '.implode(' AND ',$where).' ORDER BY COALESCE(b.actual_checkout,b.checkin_at) DESC,b.id DESC LIMIT 100',$values)->fetchAll();
    $output=[];foreach($rows as $b){$b['status']=$b['operational_status'];$b['checkout_at']=$b['actual_checkout'];$bill=$service->bill($b);$output[]=[$b['checkin_at'],$b['checkout_at'],$b['room_name'],$b['guest_name'],$b['stay_type']==='short'?'':$bill['nights'],$b['rate_per_night'] ?? '',$bill['legacy_unknown']?'':$service::money($bill['room']),$service::money($bill['extras']),$bill['legacy_unknown']?'':$service::money($bill['room']+$bill['extras']+$bill['incidental']),$service::money($bill['paid']),$b['status'],$b['stay_type'],$service::money($bill['incidental']),$bill['legacy_unknown']?'unknown historical rate':$service::money($bill['balance']),$bill['provisional']?'Provisional - Awaiting Owner':($bill['checkout_balance']!==null?'Final':'Existing Record - No Frozen Departure')];}
    access_service()->audit($actor,'stay.exported','booking',null,'succeeded',['rows'=>count($output),'limit'=>100,'definition'=>'authoritative_bill_with_stay_type_and_incidentals']);
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="bookings_export.csv"');$out=fopen('php://output','w');
    fputcsv($out,['Check-In','Check-Out','Room','Guest','Nights','Rate/Night','Room Total','Extras','Grand Total','Paid','Status','Stay Type','Incidentals','Balance / evidence','Review Status']);
    foreach($output as $line){fputcsv($out,array_map(static fn($v)=>is_string($v)&&preg_match('/^[\s]*[=+\-@]/u',$v)?"'".$v:$v,$line));}fclose($out);exit;
}
