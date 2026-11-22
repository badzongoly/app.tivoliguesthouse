<?php
namespace Tivoli\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;

/** Facts, review and recovery are separate append-only events in the existing stay transaction. */
trait DepartureIncidents
{
    private function incidentEvent(string $kind,string $type,int $target,int $actor,array $data,int $amount=0): int
    {
        $this->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',
            [$kind,$type,(string)$target,$amount,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);
        return (int)$this->repo->db->lastInsertId();
    }

    private function departureRecord(int $booking,?int $reportId=null): ?array
    {
        if(!(new FinanceService($this->access))->installed())return null;
        $report=$this->repo->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id
            WHERE e.kind='departure_reported' AND e.target_type='booking' AND e.target_id=?".($reportId?' AND e.id=?':'')." ORDER BY e.id DESC LIMIT 1",$reportId?[(string)$booking,$reportId]:[(string)$booking])->fetch();
        if(!$report)return null;
        $report['data']=json_decode($report['payload'],true,512,JSON_THROW_ON_ERROR);
        $history=$this->repo->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id
            WHERE e.target_type='departure_incident' AND e.target_id=? ORDER BY e.id",[(string)$report['id']])->fetchAll();
        $status='pending';$secured=(bool)$report['data']['room_cleared'];$assignment=null;
        foreach($history as &$event){
            $event['data']=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
            if($event['kind']==='departure_confirmed')$status='confirmed';
            if($event['kind']==='departure_disputed')$status='disputed';
            if($event['kind']==='departure_retracted')$status='retracted';
            if($event['kind']==='departure_secured')$secured=true;
            if(in_array($event['kind'],['departure_confirmed','departure_followup'],true))$assignment=$event['data'];
        }
        unset($event);
        return compact('report','history','status','secured','assignment');
    }

    private function priorDepartureCases(int $booking,int $currentReport): array
    {
        $ids=$this->repo->run("SELECT id FROM finance_events WHERE kind='departure_reported'
            AND target_type='booking' AND target_id=? AND id<? ORDER BY id DESC",[(string)$booking,$currentReport])->fetchAll(\PDO::FETCH_COLUMN);
        return array_map(fn($id)=>$this->departureRecord($booking,(int)$id),$ids);
    }

    public function departureCase(int $actor,int $booking): ?array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');
        return $this->departureRecord($booking);
    }

    public function departureCases(int $actor): array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');
        if(!(new FinanceService($this->access))->installed())return [];
        $rows=$this->repo->run("SELECT DISTINCT b.id,b.guest_name,b.room_id,r.name room_name,
            (SELECT COUNT(*) FROM finance_events reports WHERE reports.kind='departure_reported' AND reports.target_type='booking' AND reports.target_id=e.target_id) report_count FROM finance_events e
            JOIN bookings b ON e.target_id=CAST(b.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            JOIN rooms r ON r.id=b.room_id WHERE e.kind='departure_reported' AND e.target_type='booking' ORDER BY b.id DESC")->fetchAll();
        $result=[];
        foreach($rows as $row){
            $case=$this->departureRecord((int)$row['id']);
            $booking=$this->repo->run('SELECT * FROM bookings WHERE id=?',[$row['id']])->fetch();
            $bill=$this->bill($booking);
            $row['case']=$case;$row['bill']=$bill;
            $row['resolved']=$case['status']==='retracted'||($case['status']==='confirmed'&&$bill['balance']===0&&$case['secured']);
            $result[]=$row;
        }
        $this->access->audit($actor,'stay.departure_cases_viewed','booking',null,'succeeded',['cases'=>count($result)]);
        return $result;
    }

    private function incidentRoomHeld(int $room): bool
    {
        if(!(new FinanceService($this->access))->installed())return false;
        $ids=$this->repo->run("SELECT DISTINCT target_id FROM finance_events WHERE kind='departure_reported'
            AND target_type='booking' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=?",[(string)$room])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $booking){
            $case=$this->departureRecord((int)$booking);
            if($case&&$case['status']!=='retracted'&&!$case['secured'])return true;
        }
        return false;
    }

    public function displayDeparture(array $booking): array
    {
        $case=$this->departureRecord((int)$booking['id']);
        if($case&&in_array($case['status'],['pending','disputed'],true)){
            $booking['status']='checked_out';
            $booking['checkout_at']=$case['report']['data']['actual_departed_at'];
        }
        return $booking;
    }

    private function occupancyCondition(): string
    {
        return (new FinanceService($this->access))->installed()?' AND NOT ('.\Tivoli\Repository\DepartureFacts::reported('b').')':'';
    }

    private function incidentTime(mixed $input,string $minimum,string $maximum): string
    {
        $text=R::text($input,19,true);
        $text=str_replace('T',' ',$text);
        if(strlen($text)===16)$text.=':00';
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$text,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$text||$text<$minimum||$text>$maximum){
            throw new RuntimeException('Enter a valid UTC time between check-in and discovery, no later than now.');
        }
        return $text;
    }

    private function requireDepartureOwner(int $actor): void
    {
        $this->access->requirePrivilege($actor,'bookings.manage');
        if(!(new FinanceService($this->access))->canPostLate($actor)){
            throw new PermissionDenied('Only the designated finance owner may review or collect a departed balance.');
        }
    }

    public function departurePreview(int $actor,int $booking,string $actual): array
    {
        $this->requireDepartureOwner($actor);
        $case=$this->departureRecord($booking);
        if(!$case||!in_array($case['status'],['pending','disputed'],true))throw new RuntimeException('Choose an incident awaiting final review.');
        $b=$this->repo->run('SELECT * FROM bookings WHERE id=?',[$booking])->fetch();
        $actual=$this->incidentTime($actual,$b['checkin_at'],$case['report']['data']['discovered_at']);
        $bill=$this->bill(array_replace($b,['checkout_at'=>$actual]),$this->now(),true);
        if($bill['legacy_unknown'])throw new RuntimeException('The historical rate is unknown. Obtain evidenced rate reconciliation before finalizing.');
        $token=hash('sha256',json_encode([$case['report']['id'],$case['history'],$actual,$bill],JSON_THROW_ON_ERROR));
        $this->access->audit($actor,'stay.departure_previewed','booking',(string)$booking,'succeeded',['incident_id'=>(int)$case['report']['id'],'actual_departed_at'=>$actual]);
        return ['actual_departed_at'=>$actual,'bill'=>$bill,'token'=>$token];
    }

    private function incidentAssignment(array $in,bool $required): array
    {
        if(!$required&&($in['owner_id']??'')===''&&($in['due']??'')==='')return ['owner_id'=>null,'owner_name'=>null,'due'=>null];
        $id=filter_var($in['owner_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $user=$id?$this->repo->user($id):null;
        $due=R::text($in['due']??'',10,true);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$due,new DateTimeZone('UTC'));
        if(!$user||!$user['active']||!$date||$date->format('Y-m-d')!==$due||$due<substr($this->now(),0,10)){
            throw new RuntimeException('Choose an active collection owner and a due date on or after today.');
        }
        return ['owner_id'=>$id,'owner_name'=>$user['name'],'due'=>$due];
    }

    private function incidentTurnover(int $room): void
    {
        $this->roomUnused($room);$this->settings($room);
        if($this->blocked($room)){
            $this->repo->run("UPDATE rooms SET status='maintenance' WHERE id=?",[$room]);
            $this->repo->run("UPDATE stay_room_settings SET ready_state='blocked' WHERE room_id=?",[$room]);
        }else{
            $this->repo->run("UPDATE rooms SET status='dirty' WHERE id=?",[$room]);
            $this->repo->run("UPDATE stay_room_settings SET ready_state='cleaning' WHERE room_id=?",[$room]);
        }
    }

    private function departureCommand(int $actor,string $action,array $in,string $postedAt): array
    {
        $finance=new FinanceService($this->access,$postedAt);
        if(!$finance->installed())throw new RuntimeException('Finance setup is required for departure incidents.');
        $id=filter_var($in['booking_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id)throw new RuntimeException('Choose a valid guest stay.');
        $booking=$this->row('bookings',$id);$this->row('rooms',(int)$booking['room_id']);
        $case=$this->departureRecord($id);
        $reason=R::text($in['reason']??'',500,true);
        if($action==='report_departure'){
            $this->active($booking);
            if($case&&$case['status']!=='retracted')throw new RuntimeException('A departure incident is already recorded.');
            $occupants=$this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition()." FOR UPDATE",[$booking['room_id']])->fetchAll(\PDO::FETCH_COLUMN);
            if(count($occupants)!==1||(int)$occupants[0]!==$id)throw new RuntimeException('Resolve the room occupancy conflict before reporting departure.');
            $discovered=$this->incidentTime($in['discovered_at']??'',$booking['checkin_at'],$postedAt);
            $actual=($in['actual_departed_at']??'')===''?null:$this->incidentTime($in['actual_departed_at'],$booking['checkin_at'],$discovered);
            $snapshot=$this->bill(array_replace($booking,['checkout_at'=>$actual??$discovered]),$postedAt,true);
            $clear=($in['vacant']??'')==='1'&&($in['secured']??'')==='1';
            $report=$this->incidentEvent('departure_reported','booking',$id,$actor,[
                'room_id'=>(int)$booking['room_id'],'discovered_at'=>$discovered,'actual_departed_at'=>$actual,
                'description'=>$reason,'snapshot'=>$snapshot,'room_cleared'=>$clear,'vacant'=>($in['vacant']??'')==='1',
                'secured'=>($in['secured']??'')==='1','attachment'=>isset($in['_incident_file']),
            ]);
            if(isset($in['_incident_file']))FinanceAttachment::save($this->access,$actor,'incident',$report,$in['_incident_file']);
            $this->incidentTurnover((int)$booking['room_id']);
            return ['booking_id'=>$id,'audit'=>['incident_id'=>$report,'room_id'=>(int)$booking['room_id'],'reported_departure'=>$actual,'discovered_at'=>$discovered,'room_cleared'=>$clear]];
        }

        if($action==='collect_outstanding'){
            $this->requireDepartureOwner($actor);
            if(!$this->recoveryInstalled()||$booking['status']!=='checked_out')throw new RuntimeException('Choose a departed stay with recovery setup.');
            FinanceService::guardSource($this->access,substr($postedAt,0,10));
            $bill=$this->bill($booking,$postedAt);
            if($bill['checkout_balance']===null||$bill['legacy_unknown'])throw new RuntimeException('Finalize the evidenced departure bill before collecting.');
            if((string)$bill['balance']!==($in['expected_balance']??''))throw new RuntimeException('The balance changed. Review the account again.');
            $amount=R::minor(R::text($in['amount']??'',30,true));
            if($amount<=0||$amount>$bill['balance'])throw new RuntimeException('Enter a collection within the outstanding balance.');
            $method=$this->method($in);$reference=R::text($in['reference']??'',120,true);
            if($method==='momo')$finance->guardLateMomoReference($reference);
            $details=['original_activity_date'=>substr($postedAt,0,10),'posting_date'=>substr($postedAt,0,10),
                'reason'=>$reason,'evidence_reference'=>$reference,'mode'=>'new_collection','actual_departure'=>$booking['checkout_at']];
            $authorization=$finance->authorizeDepartedReceipt($actor,$id,$amount,$method,$details);
            $payment=$this->payment($actor,$booking,$amount,$method,$reference,'Outstanding balance collection',$postedAt,$authorization);
            return ['booking_id'=>$id,'audit'=>['payment_id'=>$payment,'authorization_id'=>$authorization,'amount_minor'=>$amount,'remaining_minor'=>$bill['balance']-$amount,'incident_id'=>$case['report']['id']??null]];
        }

        if(!$case||$case['status']==='retracted')throw new RuntimeException('Choose a reported departure incident.');
        $reportId=(int)$case['report']['id'];
        if($action==='secure_departure_room'){
            if($case['secured'])throw new RuntimeException('Room security is already verified.');
            if(($in['vacant']??'')!=='1'||($in['secured']??'')!=='1')throw new RuntimeException('Verify both vacancy and secured access.');
            $this->roomUnused((int)$booking['room_id']);
            $event=$this->incidentEvent('departure_secured','departure_incident',$reportId,$actor,['reason'=>$reason,'room_id'=>(int)$booking['room_id']]);
            $this->incidentTurnover((int)$booking['room_id']);
            return ['booking_id'=>$id,'audit'=>['incident_id'=>$reportId,'event_id'=>$event,'room_secured'=>true]];
        }
        $this->requireDepartureOwner($actor);
        if($action==='departure_followup'){
            $bill=$this->bill($booking,$postedAt);
            $assignment=$this->incidentAssignment($in,$bill['balance']!==0||$case['status']!=='confirmed');
            $event=$this->incidentEvent('departure_followup','departure_incident',$reportId,$actor,$assignment+['reason'=>$reason]);
            return ['booking_id'=>$id,'audit'=>['incident_id'=>$reportId,'event_id'=>$event]];
        }
        if($action!=='review_departure'||$case['status']==='confirmed')throw new RuntimeException('This incident is already finalized or the action is invalid.');
        $decision=$in['decision']??'';
        if($decision==='dispute'){
            $event=$this->incidentEvent('departure_disputed','departure_incident',$reportId,$actor,['reason'=>$reason]);
        }elseif($decision==='retract'){
            $reuse=$this->repo->run('SELECT id FROM bookings WHERE room_id=? AND id<>? AND checkin_at>=? LIMIT 1',
                [$booking['room_id'],$id,$case['report']['created_at']])->fetchColumn();
            if($reuse)throw new RuntimeException('The room has been reused. Investigate without restoring the old occupancy.');
            $this->roomUnused((int)$booking['room_id']);
            $event=$this->incidentEvent('departure_retracted','departure_incident',$reportId,$actor,['reason'=>$reason]);
            $this->repo->run("UPDATE rooms SET status='occupied' WHERE id=?",[$booking['room_id']]);
            $this->repo->run("UPDATE stay_room_settings SET ready_state=? WHERE room_id=?",[$this->blocked((int)$booking['room_id'])?'blocked':'ready',$booking['room_id']]);
        }elseif($decision==='confirm'){
            FinanceService::guardSource($this->access,substr($postedAt,0,10));
            $preview=$this->departurePreview($actor,$id,R::text($in['actual_departed_at']??'',19,true));
            if(!hash_equals($preview['token'],$in['review_token']??''))throw new RuntimeException('The review changed. Preview the final charges again.');
            if($this->repo->run("SELECT id FROM stay_incidentals WHERE booking_id=? AND status='pending' LIMIT 1",[$id])->fetchColumn())throw new RuntimeException('Review pending incidentals before finalizing.');
            $bill=$preview['bill'];$actual=$preview['actual_departed_at'];
            $assignment=$this->incidentAssignment($in,$bill['balance']!==0);
            $this->repo->run("UPDATE bookings SET status='checked_out',checkout_at=? WHERE id=?",[$actual,$id]);
            $this->repo->run('INSERT INTO stay_departures(booking_id,room_minor,extras_minor,incidental_minor,paid_minor,balance_minor,actor_id,departed_at,note,refund_cursor) VALUES(?,?,?,?,?,?,?,?,?,?)',
                [$id,$bill['room'],$bill['extras'],$bill['incidental'],$bill['paid'],$bill['balance'],$actor,$actual,$reason,
                    (int)$this->repo->run('SELECT COALESCE(MAX(id),0) FROM stay_refunds WHERE booking_id=?',[$id])->fetchColumn()]);
            ReceiptCorrectionService::captureSnapshot($this->access,$actor,$id,$postedAt);\Tivoli\Repository\ChargeAccounting::snapshot($this->access,$actor,$id,$postedAt);
            $this->incidentEvent('incident_room_revenue','booking',$id,$actor,['incident_id'=>$reportId,'actual_departed_at'=>$actual,'reason'=>$reason],$bill['room']);
            $event=$this->incidentEvent('departure_confirmed','departure_incident',$reportId,$actor,$assignment+[
                'actual_departed_at'=>$actual,'posting_date'=>substr($postedAt,0,10),'snapshot'=>$bill,'reason'=>$reason,
            ]);
        }else throw new RuntimeException('Choose Confirm, Dispute or Retract.');
        return ['booking_id'=>$id,'audit'=>['incident_id'=>$reportId,'event_id'=>$event,'decision'=>$decision,'reason'=>$reason]];
    }
}
