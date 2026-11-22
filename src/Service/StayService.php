<?php
namespace Tivoli\Service;

use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;
use Throwable;

/** One PDO transaction for each complete guest/stock operation and its durable audit. */
final class StayService
{
    use DepartureIncidents;
    public readonly \Tivoli\Repository\AccessRepository $repo;
    public function __construct(private readonly AccessService $access, private readonly ?string $clock=null) { $this->repo=$access->repo; }
    public static function money(int $minor): string { return R::decimal($minor); }
    private function now(): string { return $this->clock ?? gmdate('Y-m-d H:i:s'); }
    private function admin(int $actor): void
    {
        if (!$this->repo->isAdmin($actor)) { throw new \Tivoli\Security\PermissionDenied('An administrator must perform this action.'); }
    }
    private function row(string $table,int $id): array
    {
        if (!in_array($table,['rooms','bookings','extras','stay_faults','stay_incidentals'],true)) { throw new RuntimeException('Invalid resource.'); }
        return $this->repo->run('SELECT * FROM '.$table.' WHERE id=? FOR UPDATE',[$id])->fetch() ?: throw new RuntimeException('Record not found.');
    }
    private function active(array $b): void { $case=$this->departureRecord((int)$b['id']);if ($b['status']!=='active'||($case&&$case['status']!=='retracted')) { throw new RuntimeException('Payments, purchases and departure cannot be recorded after checkout.'); } }
    private function settings(int $room): array
    {
        $this->repo->run("INSERT IGNORE INTO stay_room_settings(room_id) VALUES(?)",[$room]);
        return $this->repo->run('SELECT * FROM stay_room_settings WHERE room_id=? FOR UPDATE',[$room])->fetch();
    }
    private function blocked(int $room): bool { return (bool)$this->repo->run("SELECT id FROM stay_faults WHERE room_id=? AND priority='Critical' AND status<>'closed' LIMIT 1",[$room])->fetchColumn() || $this->incidentRoomHeld($room); }
    private function roomUnused(int $room): void
    {
        if ($this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition()." LIMIT 1 FOR UPDATE",[$room])->fetchColumn()) { throw new RuntimeException('This room still has an active guest.'); }
    }
    /** Authoritative physical room facts; read-only and shared with housekeeping guards. */
    public function housekeepingRoom(int $room): array
    {
        $row=$this->repo->run("SELECT r.id,r.name,r.type,r.status,COALESCE(s.ready_state,'unverified') ready_state FROM rooms r LEFT JOIN stay_room_settings s ON s.room_id=r.id WHERE r.id=?",[$room])->fetch();
        if(!$row)throw new RuntimeException('Room not found.');
        $row['occupied']=(bool)$this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition()." LIMIT 1",[$room])->fetchColumn();
        $row['blocked']=$this->blocked($room)||$row['status']==='maintenance'||$row['ready_state']==='blocked';
        $bookings=$this->repo->run('SELECT id,status,checkin_at,checkout_at FROM bookings WHERE room_id=? ORDER BY id',[$room])->fetchAll();
        $faults=$this->repo->run("SELECT id,status,repair_at,closed_at FROM stay_faults WHERE room_id=? AND priority='Critical' ORDER BY id",[$room])->fetchAll();
        $reports=(new FinanceService($this->access))->installed()?$this->repo->run("SELECT id FROM finance_events WHERE kind='departure_reported' AND target_type='booking' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=? ORDER BY id",[(string)$room])->fetchAll(\PDO::FETCH_COLUMN):[];
        $row['cycle']=hash('sha256',json_encode([$bookings,$faults,$reports],JSON_THROW_ON_ERROR));return $row;
    }
    private function method(array $in): string
    {
        $method=$in['method'] ?? 'cash';
        if (!in_array($method,['cash','momo'],true)) { throw new RuntimeException('Choose a valid payment method.'); }
        return $method;
    }
    private function payment(int $actor,array $b,int $amount,string $method,string $reference,string $note,string $postedAt,?int $recoveryEvent=null): int
    {
        if ($recoveryEvent===null) $this->active($b);
        if ($amount<=0 || $amount>999999999999) { throw new RuntimeException('Enter a positive payment within the ledger limit.'); }
        if($reference!==''){\Tivoli\Repository\ReceiptAccounting::guardAlias($this->repo,$reference);\Tivoli\Repository\ExpenseAccounting::guardAlias($this->repo,$reference);}
        if ($reference!=='' && $this->repo->run('SELECT p.id FROM payments p JOIN stay_receipts r ON r.payment_id=p.id WHERE p.booking_id=? AND r.reference_no=?',[$b['id'],$reference])->fetchColumn()) { throw new RuntimeException('This payment reference is already recorded for the stay.'); }
        $values=[$b['id'],$method,R::decimal($amount),$note,$postedAt];
        if ($recoveryEvent===null) {
            $this->repo->run('INSERT INTO payments(booking_id,method,amount,note,created_at) VALUES(?,?,?,?,?)',$values);
        } else {
            $this->repo->run('INSERT INTO payments(booking_id,method,amount,note,created_at,recovery_event_id) VALUES(?,?,?,?,?,?)',[...$values,$recoveryEvent]);
        }
        $id=(int)$this->repo->db->lastInsertId();
        $this->repo->run('INSERT INTO stay_receipts(payment_id,actor_id,reference_no,recorded_at) VALUES(?,?,?,?)',[$id,$actor,$reference,$postedAt]);
        return $id;
    }
    private function recoveryInstalled(): bool
    {
        return (bool)$this->repo->run('SELECT 1 FROM stay_schema WHERE version=3')->fetchColumn();
    }

    public function bill(array $b,?string $at=null,bool $ignoreReported=false): array
    {
        $detail=$this->repo->run('SELECT * FROM stay_details WHERE booking_id=?',[$b['id']])->fetch() ?: [];
        $end=$b['checkout_at'] ?: ($at ?? $this->now());$nights=R::nights($b['checkin_at'],$end);
        $room=$b['rate_per_night']===null?0:R::minor((string)$b['rate_per_night'])*(($detail['stay_type'] ?? 'overnight')==='short'?1:$nights);
        $exact=(string)$this->repo->run('SELECT CAST(ROUND(COALESCE(SUM(qty*unit_price),0),2) AS CHAR) FROM booking_extras WHERE booking_id=?',[$b['id']])->fetchColumn();$extras=R::minor($exact);
        $incidental=(int)$this->repo->run("SELECT COALESCE(SUM(amount_minor),0) FROM stay_incidentals WHERE booking_id=? AND status='approved'",[$b['id']])->fetchColumn();
        $paid=0;foreach($this->repo->run('SELECT amount FROM payments WHERE booking_id=?',[$b['id']])->fetchAll() as $p) { $paid+=R::minor((string)$p['amount']); }
        $receiptAdjustments=\Tivoli\Repository\ReceiptAccounting::delta($this->repo,(int)$b['id']);$paid+=$receiptAdjustments;
        $refunds=(int)$this->repo->run('SELECT COALESCE(SUM(amount_minor),0) FROM stay_refunds WHERE booking_id=?',[$b['id']])->fetchColumn();$paid-=$refunds;$balance=$room+$extras+$incidental-$paid;
        $approval=$this->repo->run('SELECT * FROM stay_checkout_approvals WHERE booking_id=? ORDER BY id DESC LIMIT 1',[$b['id']])->fetch();
        $frozen=$this->repo->run('SELECT * FROM stay_departures WHERE booking_id=?',[$b['id']])->fetch();
        $incident=!$ignoreReported?$this->departureRecord((int)$b['id']):null;
        $provisional=$incident&&in_array($incident['status'],['pending','disputed'],true);
        if($provisional){
            $room=(int)$incident['report']['data']['snapshot']['room'];
            $extras=(int)$incident['report']['data']['snapshot']['extras'];
            $nights=(int)$incident['report']['data']['snapshot']['nights'];
            $balance=$room+$extras+$incidental-$paid;
        }
        $recovered=0;
        if ($frozen) {
            $room=(int)$frozen['room_minor'];$extras=(int)$frozen['extras_minor'];$incidental=(int)$frozen['incidental_minor'];
            $laterRefunds=(int)$this->repo->run('SELECT COALESCE(SUM(amount_minor),0) FROM stay_refunds WHERE booking_id=? AND id>?',[$b['id'],(int)$frozen['refund_cursor']])->fetchColumn();
            if ($this->recoveryInstalled()) {
                $recovered=(int)$this->repo->run('SELECT COALESCE(SUM(amount*100),0) FROM payments WHERE booking_id=? AND recovery_event_id IS NOT NULL',[$b['id']])->fetchColumn();
            }
            $receiptAdjustments=\Tivoli\Repository\ReceiptAccounting::delta($this->repo,(int)$b['id'],\Tivoli\Repository\ReceiptAccounting::frozenCursor($this->repo,(int)$b['id']));
            $paid=(int)$frozen['paid_minor']+$recovered-$laterRefunds+$receiptAdjustments;
            $balance=(int)$frozen['balance_minor']-$recovered+$laterRefunds-$receiptAdjustments;
        }
        $chargeAdjustments=\Tivoli\Repository\ChargeAccounting::totals($this->repo,(int)$b['id'],$frozen?\Tivoli\Repository\ChargeAccounting::frozenCursor($this->repo,(int)$b['id']):0);if($provisional){$afterReport=\Tivoli\Repository\ChargeAccounting::totals($this->repo,(int)$b['id'],(int)($incident['report']['data']['snapshot']['charge_cursor']??0));$chargeAdjustments['room']=$afterReport['room'];$chargeAdjustments['extras']=$afterReport['extras'];}$room+=$chargeAdjustments['room'];$extras+=$chargeAdjustments['extras'];$incidental+=$chargeAdjustments['incidental'];$balance+=array_sum($chargeAdjustments);
        $chargeEntries=\Tivoli\Repository\ChargeAccounting::entries($this->repo,(int)$b['id']);$lastCharge=$chargeEntries?end($chargeEntries):null;return ['charge_cursor'=>$lastCharge?(int)$lastCharge['id']:0,'charge_adjustments'=>$chargeAdjustments,'room'=>$room,'extras'=>$extras,'incidental'=>$incidental,'paid'=>$paid,'balance'=>$balance,'nights'=>$nights,'legacy_unknown'=>$b['rate_per_night']===null,'approval'=>$approval && $balance<=(int)$approval['amount_minor'] && (new FinanceService($this->access))->canPostLate((int)$approval['approved_by']),'refunds'=>$refunds,'approval_record'=>$approval ?: null,
            'provisional'=>(bool)$provisional,'departure_unknown'=>$provisional&&$incident['report']['data']['actual_departed_at']===null,'checkout_balance'=>$frozen?(int)$frozen['balance_minor']:null,'recovered'=>$recovered,'receipt_adjustments'=>$receiptAdjustments];
    }
    public function execute(int $actor,string $action,array $input,string $operationKey): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$operationKey)) { throw new RuntimeException('Invalid form identity. Reload and try again.'); }
        $canonical=$input;unset($canonical['csrf'],$canonical['operation_key']);
        if (isset($canonical['_identity'])) { $canonical['_identity']=array_intersect_key($canonical['_identity'],array_flip(['mime','size','sha'])); }
        if(isset($canonical['_fault_file'])){if(!is_array($canonical['_fault_file']))throw new RuntimeException('Invalid fault attachment input.');$canonical['_fault_file']=array_intersect_key($canonical['_fault_file'],array_flip(['mime','size','sha']));}
        if(isset($canonical['_incident_file']))$canonical['_incident_file']=FinanceAttachment::meta($canonical['_incident_file']);
        ksort($canonical);$fingerprint=hash('sha256',json_encode([$action,$canonical],JSON_THROW_ON_ERROR));
        try {
            return $this->access->transaction(function() use($actor,$action,$input,$operationKey,$fingerprint): array {
                if(!in_array($action,['room_audit','area_audit'],true))$this->access->requirePrivilege($actor,'bookings.manage');
                $this->installed();
                if(in_array($action,['room_audit','area_audit'],true))(new WeeklyAuditService($this->access,$this->now()))->requireAuditor($actor);
                elseif($action==='area_followup')$this->admin($actor);
                if(in_array($action,['recover_receipt','collect_outstanding'],true) && !(new FinanceService($this->access))->canPostLate($actor)){
                    throw new \Tivoli\Security\PermissionDenied('Only the designated finance owner can recover a departed receipt.');
                }
                $capability=match($action){'extra_save','extra_categories'=>'extras.manage','stock_in','stock_count'=>'stock.manage','inspection_checklist','cleaning_checklist','area_catalogue','area_audit','area_followup','room_save','fault','repair','release_fault','clean','inspect','room_audit'=>'rooms.manage',default=>'bookings.manage'};
                if(!in_array($action,['room_audit','area_audit'],true))$this->access->requirePrivilege($actor,$capability);
                if($action==='refund'&&!(new FinanceService($this->access,$this->now()))->canPostLate($actor))throw new \Tivoli\Security\PermissionDenied('Only the configured finance owner records guest refunds.');
                $prior=$this->repo->run('SELECT * FROM stay_operations WHERE actor_id=? AND operation_key=?',[$actor,$operationKey])->fetch();
                if ($prior) { if (!hash_equals($prior['fingerprint'],$fingerprint)) { throw new RuntimeException('This form was already used with different details.'); } return json_decode($prior['result_json'],true,512,JSON_THROW_ON_ERROR); }
                $postedAt=$this->now();
                if(in_array($action,['checkin','payment','clear_balance','extra','checkout','review_incidental','refund','recover_receipt'],true))FinanceService::guardSource($this->access,substr($postedAt,0,10));
                $result=$this->command($actor,$action,$input,$postedAt);
                $areaAction=in_array($action,['area_audit','area_followup'],true);$this->access->audit($actor,'stay.'.$action,$areaAction?'area_audit':'booking',$areaAction?$result['audit']['audit_key']:(isset($result['booking_id'])?(string)$result['booking_id']:null),'succeeded',$result['audit'] ?? []);
                if(in_array($action,['room_audit','area_audit'],true))$result['followup_id']=(new WeeklyAuditService($this->access,$this->now()))->capture($actor,$action==='room_audit'?'room':'area',$action==='room_audit'?(string)$result['audit']['room_audit_id']:$result['audit']['audit_key'],$input);
                $this->repo->run('INSERT INTO stay_operations(actor_id,operation_key,fingerprint,result_json) VALUES(?,?,?,?)',[$actor,$operationKey,$fingerprint,json_encode($result,JSON_THROW_ON_ERROR)]);
                unset($result['audit']);return $result;
            });
        } catch(Throwable $error) {
            $this->access->audit($actor,'stay.action_failed','booking',isset($input['booking_id'])?(string)(int)$input['booking_id']:null,'denied',['action'=>$action,'reason'=>'permission_validation_state_or_storage_failure']);throw $error;
        }
    }
    public function installed(): void { if(!$this->repo->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='stay_schema'")->fetchColumn() || !$this->repo->run('SELECT version FROM stay_schema WHERE version=2')->fetchColumn())throw new RuntimeException('Occupancy setup must complete before this module can be used.'); }
    private function command(int $actor,string $action,array $in,string $postedAt): array
    {
        $bookingId=(int)($in['booking_id'] ?? 0);$id=(int)($in['id'] ?? 0);$reason=R::text($in['reason'] ?? '',500);
        if(in_array($action,['report_departure','secure_departure_room','review_departure','departure_followup','collect_outstanding'],true))return $this->departureCommand($actor,$action,$in,$postedAt);
        if($action==='recover_receipt'){
            if(!$this->recoveryInstalled()) throw new RuntimeException('Departed receipt recovery setup is required.');
            $booking=$this->row('bookings',$bookingId);
            if($booking['status']!=='checked_out') throw new RuntimeException('Choose a checked-out stay.');
            $bill=$this->bill($booking,$postedAt);
            if($bill['checkout_balance']===null || $bill['legacy_unknown']){
                throw new RuntimeException('This stay has no verified checkout balance to recover against.');
            }
            $amount=R::minor(R::text($in['amount'] ?? '',30,true));
            if($amount<=0 || $amount>$bill['balance']){
                throw new RuntimeException('The receipt must fit the current outstanding balance.');
            }
            $reference=R::text($in['reference'] ?? '',120,true);
            $method=$this->method($in);
            $finance=new FinanceService($this->access,$postedAt);
            $details=$finance->lateDetails($actor,$in,$reference);
            if(!$details || $details['original_activity_date']<substr($booking['checkin_at'],0,10)){
                throw new RuntimeException('Enter an original activity date in a locked period on or after check-in.');
            }
            if($method==='momo')$finance->guardLateMomoReference($reference);
            $event=$finance->authorizeDepartedReceipt($actor,$bookingId,$amount,$method,$details);
            $payment=$this->payment($actor,$booking,$amount,$method,$reference,'Recovered after checkout',$postedAt,$event);
            return ['booking_id'=>$bookingId,'audit'=>['payment_id'=>$payment,'recovery_event_id'=>$event,
                'amount_minor'=>$amount,'remaining_minor'=>$bill['balance']-$amount]+$details];
        }
        if($action==='refund'){
            $this->admin($actor);$b=$this->row('bookings',$bookingId);$bill=$this->bill($b,$postedAt);$refundMethod=$this->method($in);if(($in['completed']??'')!=='1')throw new RuntimeException('Confirm the refund was actually completed.');$payer=R::text($in['actual_payer']??'',120,true);$actual=R::text($in['actual_at']??'',19,true);$actual=str_replace('T',' ',$actual);if(strlen($actual)===16)$actual.=':00';$dt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$actual,new \DateTimeZone('UTC'));if(!$dt||$dt->format('Y-m-d H:i:s')!==$actual||$actual>$postedAt||$actual<'2000-01-01 00:00:00')throw new RuntimeException('Enter the evidenced actual refund time, no later than now.');$payment=$this->repo->run('SELECT * FROM payments WHERE id=? AND booking_id=? FOR UPDATE',[(int)($in['payment_id'] ?? 0),$bookingId])->fetch();$amount=R::minor(R::text($in['amount'] ?? '',30,true));$used=(int)$this->repo->run('SELECT COALESCE(SUM(amount_minor),0) FROM stay_refunds WHERE payment_id=?',[(int)($in['payment_id'] ?? 0)])->fetchColumn();$proof=R::text($in['proof'] ?? '',120,true);
            if($bill['provisional'])throw new RuntimeException('Finalize the departure incident before recording a refund.');if($b['status']!=='active'&&!$this->repo->run('SELECT 1 FROM stay_departures WHERE booking_id=?',[$bookingId])->fetchColumn())throw new RuntimeException('A verified final bill is required before refunding this historical stay.');if(!isset($in['method']))throw new RuntimeException('Choose the actual Cash or MoMo refund method.');
            $refundCase=$this->departureRecord($bookingId);
            if($refundCase&&$refundCase['status']==='confirmed')$this->requireDepartureOwner($actor);
            if($bill['legacy_unknown']||!$payment||$amount<=0||$amount>-$bill['balance']||$amount>\Tivoli\Repository\ReceiptAccounting::position($this->repo,(int)$payment['id'])['position']['amount']-$used||$reason==='')throw new RuntimeException('Refund must fit the overpayment and remaining original receipt, with proof and reason.');
            $finance=new FinanceService($this->access,$postedAt);
            $actualDate=substr($actual,0,10);$collectedDate=$finance->originalReceiptDate($payment,$b['checkin_at']);if($actualDate<$collectedDate||($collectedDate===substr($payment['created_at'],0,10)&&$actual<$payment['created_at']))throw new RuntimeException('Actual refund cannot precede the evidenced original collection.');if(($in['original_activity_date']??'')!==''&&$in['original_activity_date']!==$actualDate)throw new RuntimeException('Actual refund time and original activity date must agree.');
            $late=$finance->lateDetails($actor,$in,$proof);if(!$late&&$actualDate<substr($postedAt,0,10)&&$finance->periodLocked($actualDate))$late=['original_activity_date'=>$actualDate,'posting_date'=>substr($postedAt,0,10),'reason'=>$reason,'evidence_reference'=>$proof];
            if($late && $late['original_activity_date']<$finance->originalReceiptDate($payment,$b['checkin_at'])){
                throw new RuntimeException('The refund activity date cannot precede the original receipt.');
            }
            $this->repo->run('INSERT INTO stay_refunds(booking_id,payment_id,amount_minor,actor_id,proof,reason,created_at) VALUES(?,?,?,?,?,?,?)',[$bookingId,$payment['id'],$amount,$actor,$proof,$reason,$postedAt]);$refundId=(int)$this->repo->db->lastInsertId();FinanceService::recordRefund($this->access,$actor,$refundId,$amount,$proof,$postedAt,$refundMethod,$actual,$payer);
            if($late)$finance->recordLate($actor,'refund',$refundId,$late);
            return ['booking_id'=>$bookingId,'audit'=>['refund_id'=>$refundId,'payment_id'=>$payment['id'],'amount_minor'=>$amount,'proof'=>$proof,'reason'=>$reason]];
        }
        if ($action==='checkin') {
            $room=$this->row('rooms',(int)($in['room_id'] ?? 0));$setting=$this->settings((int)$room['id']);$this->roomUnused((int)$room['id']);
            if ($room['status']!=='vacant' || $setting['ready_state']!=='ready' || $this->blocked((int)$room['id'])) { throw new RuntimeException('The room must be inspected and ready before check-in.'); }
            $name=R::text($in['guest_name'] ?? '',120,true);$phone=R::text($in['phone'] ?? '',60,true);$gender=$in['gender'] ?? '';$type=$in['stay_type'] ?? '';
            if (!in_array($gender,['Male','Female','Other','Prefer not to say'],true) || !in_array($type,['overnight','short'],true)) { throw new RuntimeException('Choose gender and stay type.'); }
            $nationality=R::text($in['nationality'] ?? '',80,true);
            $countries=json_decode(file_get_contents(dirname(__DIR__,2).'/resources/countries.json'),true,512,JSON_THROW_ON_ERROR);
            if (!in_array($nationality,$countries,true)) { throw new RuntimeException('Choose nationality from the list.'); }
            $method=$this->method(['method'=>$in['payment_type']??'']);
            $rate=$type==='short'?$setting['short_rate']:$room['rate'];
            if (R::minor((string)$rate)<=0 || ($type==='short' && !$setting['short_enabled'])) { throw new RuntimeException('This stay type needs an approved positive room rate.'); }
            $amount=R::minor(R::text($in['amount']??'',30,true));
            if($amount<R::minor((string)$rate)||$amount>999999999999)throw new RuntimeException('Record at least the first accommodation charge received, within the supported amount limit.');
            if(R::minor(R::text($in['expected_rate']??'',30,true))!==R::minor((string)$rate))throw new RuntimeException('The room rate changed. Review the current rate and received amount before checking in.');
            foreach(['identity_verified','payment_attested','room_checked'] as $check)if(($in[$check]??'')!=='1')throw new RuntimeException('Confirm guest identity, room readiness and the payment received.');
            $reference=R::text($in['reference']??'',120);
            $now=$postedAt;$due=$type==='short'?(new \DateTimeImmutable($now,new \DateTimeZone('UTC')))->modify('+2 hours')->format('Y-m-d H:i:s'):null;
            $this->repo->run('INSERT INTO bookings(room_id,rate_per_night,guest_name,gender,nationality,contact,checkin_at,status) VALUES(?,?,?,?,?,?,?,?)',[$room['id'],$rate,$name,$gender==='Prefer not to say'?null:$gender,$nationality,$phone,$now,'active']);
            $bookingId=(int)$this->repo->db->lastInsertId();
            $idType=R::text($in['id_type'] ?? '',40);
            $this->repo->run('INSERT INTO stay_details(booking_id,stay_type,gender,nationality,id_type,payment_type,identity_verified,payment_attested,room_checked,due_at,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$bookingId,$type,$gender,$nationality,$idType,R::text($in['payment_type'] ?? '',12),isset($in['identity_verified'])?1:0,isset($in['payment_attested'])?1:0,isset($in['room_checked'])?1:0,$due,$actor,$now]);
            if (isset($in['_identity'])) {
                $d=$in['_identity'];if (($d['owner'] ?? null)!==$actor || !in_array($d['mime'] ?? '',['application/pdf','image/jpeg','image/png'],true) || ($d['size'] ?? 0)<1 || $d['size']>5242880) { throw new RuntimeException('Invalid private attachment.'); }
                $this->repo->run('INSERT INTO stay_identity(booking_id,cipher,mime,size,sha,owner) VALUES(?,?,?,?,?,?)',[$bookingId,$d['cipher'],$d['mime'],$d['size'],$d['sha'],$actor]);
            }
            $payment=$this->payment($actor,['id'=>$bookingId,'status'=>'active'],$amount,$method,$reference,'Accommodation payment at check-in',$postedAt);
            $this->repo->run("UPDATE rooms SET status='occupied' WHERE id=?",[$room['id']]);
            return ['booking_id'=>$bookingId,'audit'=>['room_id'=>(int)$room['id'],'stay_type'=>$type,'rate'=>$rate,'payment_id'=>$payment,'amount'=>R::decimal($amount),'method'=>$method,'provider_verified'=>false,'identity_attached'=>isset($in['_identity']),'identity_verified'=>true,'payment_attested'=>true]];
        }
        if (in_array($action,['payment','clear_balance','extra','allow_checkout','checkout','incidental','review_incidental'],true)) {
            $b=$this->row('bookings',$bookingId);
            $pendingDeparture=$action==='review_incidental'?$this->departureRecord($bookingId):null;
            if(!$pendingDeparture||!in_array($pendingDeparture['status'],['pending','disputed'],true))$this->active($b);
            $this->row('rooms',(int)$b['room_id']);
            $transitionAt=$postedAt;$bill=$this->bill($b,$transitionAt);
            if ($bill['legacy_unknown']) { throw new RuntimeException('This historical stay has an unknown rate. Preserve the record and request an evidenced administrator review.'); }
            if (in_array($action,['checkout','clear_balance','allow_checkout'],true) && (!isset($in['expected_balance']) || (string)$bill['balance']!==$in['expected_balance'])) { throw new RuntimeException('The balance changed. Review the guest account and confirm again.'); }
            if ($action==='payment' || $action==='clear_balance') {
                $amount=$action==='clear_balance'?$bill['balance']:R::minor(R::text($in['amount'] ?? '',30,true));
                $reference=R::text($in['reference'] ?? '',120);
                $finance=new FinanceService($this->access,$transitionAt);
                $late=$action==='payment'?$finance->lateDetails($actor,$in,$reference):null;
                if($late && $late['original_activity_date']<substr($b['checkin_at'],0,10)){
                    throw new RuntimeException('The receipt activity date cannot precede check-in.');
                }
                $method=$this->method($in);
                if($late && $method==='momo')$finance->guardLateMomoReference($reference);
                $payment=$this->payment($actor,$b,$amount,$method,$reference,'Cashier-recorded '.($action==='clear_balance'?'full settlement':'payment'),$postedAt);
                if($late)$finance->recordLate($actor,'payment',$payment,$late);
                return ['booking_id'=>$bookingId,'audit'=>['payment_id'=>$payment,'amount'=>R::decimal($amount),'method'=>$in['method'] ?? 'cash','provider_verified'=>false]];
            }
            if ($action==='allow_checkout') {
                if(!(new FinanceService($this->access))->canPostLate($actor))throw new \Tivoli\Security\PermissionDenied('Only the designated finance owner may authorize departure with debt.');
                if($reason==='')throw new RuntimeException('Record the reason for authorizing departure with debt.');
                if ($bill['balance']<=0) { throw new RuntimeException('There is no debt requiring checkout approval.'); }
                $this->repo->run('INSERT INTO stay_checkout_approvals(booking_id,amount_minor,approved_by,approved_at) VALUES(?,?,?,?)',[$bookingId,$bill['balance'],$actor,$postedAt]);
                return ['booking_id'=>$bookingId,'audit'=>['approval_id'=>(int)$this->repo->db->lastInsertId(),'approved_balance'=>$bill['balance'],'reason'=>$reason,'owner_authorized'=>true]];
            }
            if ($action==='checkout') {
                if ($bill['balance']>0 && !$bill['approval']) { throw new RuntimeException('Clear all outstanding or obtain finance owner authorization for checkout.'); }
                if (empty($in['extras_checked']) || empty($in['keys_returned'])) { throw new RuntimeException('Review the bill and recover the keys before departure.'); }
                $occupants=$this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition()." FOR UPDATE",[$b['room_id']])->fetchAll(\PDO::FETCH_COLUMN);
                if (count($occupants)!==1 || (int)$occupants[0]!==$bookingId) { throw new RuntimeException('Resolve the room occupancy conflict before departure.'); }
                $now=$transitionAt;
                $this->repo->run("UPDATE bookings SET status='checked_out',checkout_at=? WHERE id=?",[$now,$bookingId]);
                $this->repo->run('INSERT INTO stay_departures(booking_id,room_minor,extras_minor,incidental_minor,paid_minor,balance_minor,actor_id,departed_at,note,refund_cursor) VALUES(?,?,?,?,?,?,?,?,?,?)',[$bookingId,$bill['room'],$bill['extras'],$bill['incidental'],$bill['paid'],$bill['balance'],$actor,$now,$reason,(int)$this->repo->run('SELECT COALESCE(MAX(id),0) FROM stay_refunds WHERE booking_id=?',[$bookingId])->fetchColumn()]);
                ReceiptCorrectionService::captureSnapshot($this->access,$actor,$bookingId,$postedAt);\Tivoli\Repository\ChargeAccounting::snapshot($this->access,$actor,$bookingId,$postedAt);
                $this->settings((int)$b['room_id']);$this->repo->run("UPDATE rooms SET status='dirty' WHERE id=?",[$b['room_id']]);$this->repo->run("UPDATE stay_room_settings SET ready_state='cleaning' WHERE room_id=?",[$b['room_id']]);
                return ['booking_id'=>$bookingId,'audit'=>['departed_at'=>$now,'balance'=>$bill['balance'],'note'=>$reason,'approval_id'=>$bill['approval_record']['id'] ?? null]];
            }
            if ($action==='extra') {
                $x=$this->row('extras',(int)($in['extra_id'] ?? 0));$m=$this->repo->run('SELECT * FROM stay_extra_settings WHERE extra_id=?',[$x['id']])->fetch() ?: ['tracked'=>1];
                $qty=R::quantity(R::text($in['qty'] ?? '',30,true));$stock=R::quantity((string)$x['stock_qty']);$price=R::minor((string)$x['price']);
                if($m['tracked']&&$qty>$stock-\Tivoli\Repository\StockAvailability::hold($this->repo,(int)$x['id'])['held'])throw new RuntimeException('Quantity exceeds available stock after reported holds.');
                if (!$x['active'] || $qty<=0 || $price<=0 || ($m['tracked'] && $qty>$stock)) { throw new RuntimeException('Choose an active item, positive quantity and sufficient stock.'); }
                $settlement=$in['settlement'] ?? 'now';if (!in_array($settlement,['now','room'],true)) { throw new RuntimeException('Choose settlement.'); }
                $method=$this->method($in);$amount=R::line($qty,$price);if(($qty*$price)%100!==0)throw new RuntimeException('Choose a quantity and price whose line total is an exact pesewa.');
                $this->repo->run('INSERT INTO booking_extras(booking_id,extra_id,description,qty,unit_price,created_at) VALUES(?,?,?,?,?,?)',[$bookingId,$x['id'],$x['name'],R::decimal($qty),$x['price'],$postedAt]);$sale=(int)$this->repo->db->lastInsertId();
                if ($m['tracked']) { $this->repo->run('UPDATE extras SET stock_qty=? WHERE id=?',[R::decimal($stock-$qty),$x['id']]);$this->movement($actor,(int)$x['id'],'out',$qty,'Guest purchase','booking_extra',$sale); }
                $payment=$settlement==='now'?$this->payment($actor,$b,$amount,$method,R::text($in['reference'] ?? '',120),'Pay now extra #'.$sale,$postedAt):null;
                return ['booking_id'=>$bookingId,'audit'=>['sale_id'=>$sale,'payment_id'=>$payment,'extra_id'=>$x['id'],'qty'=>R::decimal($qty),'unit_price'=>$x['price'],'amount'=>R::decimal($amount)]];
            }
            if ($action==='incidental') {
                $amount=R::minor(R::text($in['amount'] ?? '',30,true));if ($amount<=0 || $reason==='') { throw new RuntimeException('An incidental needs a positive amount and supporting reason.'); }
                $this->repo->run('INSERT INTO stay_incidentals(booking_id,description,amount_minor,reason,submitted_by,created_at) VALUES(?,?,?,?,?,?)',[$bookingId,R::text($in['description'] ?? '',160,true),$amount,$reason,$actor,$this->now()]);
                return ['booking_id'=>$bookingId,'audit'=>['incidental_id'=>(int)$this->repo->db->lastInsertId(),'amount_minor'=>$amount,'reason'=>$reason]];
            }
            $this->admin($actor);$q=$this->row('stay_incidentals',(int)($in['incidental_id'] ?? $id));
            if ((int)$q['booking_id']!==$bookingId || $q['status']!=='pending' || (int)$q['submitted_by']===$actor || !in_array($in['decision'] ?? '',['approved','rejected'],true) || $reason==='') { throw new RuntimeException('A different administrator must review a pending request with a reason.'); }
            $this->repo->run('UPDATE stay_incidentals SET status=?,reviewed_by=?,reviewed_at=?,review_reason=? WHERE id=?',[$in['decision'],$actor,$postedAt,$reason,$q['id']]);
            return ['booking_id'=>$bookingId,'audit'=>['incidental_id'=>$q['id'],'decision'=>$in['decision'],'reason'=>$reason]];
        }
        return $this->manage($actor,$action,$in,$reason);
    }
    private function movement(int $actor,int $extra,string $type,int $qty,string $note,string $ref,int $refId): void
    {
        $this->repo->run('INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$extra,$type,R::decimal($qty),$note,$ref,$refId,$actor,$this->now()]);
        (new InventoryCostingService($this->access,$this->now()))->movement($actor,(int)$this->repo->db->lastInsertId());
    }
    public function roomTypes(): array
    {
        $types=['Standard','Double','Queen','King','Suite'];
        foreach($this->repo->run("SELECT DISTINCT type FROM rooms WHERE type<>'' ORDER BY type")->fetchAll(\PDO::FETCH_COLUMN) as $type)$types[]=(string)$type;
        $types=array_values(array_unique($types));return array_combine($types,$types);
    }
    private function manage(int $actor,string $action,array $in,string $reason): array
    {
        if(in_array($action,['room_audit','area_audit'],true))(new WeeklyAuditService($this->access,$this->now()))->requireAuditor($actor);else $this->admin($actor);$id=(int)($in['id'] ?? 0);
        if($action==='extra_categories'){$this->access->requirePrivilege($actor,'hr.settings');return ExtraCategories::change($this->repo,$in);}
        if($action==='area_catalogue'){$this->access->requirePrivilege($actor,'hr.settings');return AuditAreas::change($this->repo,$in);}
        if($action==='area_audit')return WeeklyAreaAudit::record($this->repo,$in,$this->now());
        if($action==='area_followup')return WeeklyAreaAudit::followup($this->repo,$in);
        if(in_array($action,['cleaning_checklist','inspection_checklist'],true)){$this->access->requirePrivilege($actor,'hr.settings');return CleaningChecklist::change($this->repo,$in,$action==='inspection_checklist'?'inspection':'cleaning');}
        if ($reason==='' && !in_array($action,['clean','inspect','room_save','fault','repair','room_audit','extra_save','stock_in','stock_count'],true)) { throw new RuntimeException('Record a reason for this action.'); }
        if ($action==='extra_save') {
            $x=$id?$this->row('extras',$id):null;$price=R::minor(R::text($in['price'] ?? '',30,true));$name=R::text($in['name'] ?? '',120,true);$category=R::text($in['category'] ?? 'General',80,true);$low=R::quantity(R::text($in['low_stock'] ?? '0',30,true));
            if ($price<=0) { throw new RuntimeException('Set a positive selling price.'); }
            $meta=$x?$this->repo->run('SELECT * FROM stay_extra_settings WHERE extra_id=?',[$id])->fetch():null;
            $previousCategory=$x?($meta['category'] ?? 'General'):null;
            if($category!==$previousCategory && empty(ExtraCategories::current($this->repo)['entries'][$category])){
                throw new RuntimeException('Choose an active extra category.');
            }
            $tracked=$meta?(int)$meta['tracked']:(isset($in['tracked'])?1:0);if(!$meta&&$x&&!$tracked&&R::quantity((string)$x['stock_qty'])!==0)throw new RuntimeException('Reconcile existing stock before classifying this item as a service.');
            if ($x) { $this->repo->run('UPDATE extras SET name=?,price=?,active=? WHERE id=?',[$name,R::decimal($price),isset($in['active'])?1:0,$id]); }
            else { $this->repo->run('INSERT INTO extras(name,price,active,stock_qty) VALUES(?,?,?,0)',[$name,R::decimal($price),isset($in['active'])?1:0]);$id=(int)$this->repo->db->lastInsertId(); }
            $this->repo->run('INSERT INTO stay_extra_settings(extra_id,category,tracked,low_stock) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE category=VALUES(category),low_stock=VALUES(low_stock)',[$id,$category,$tracked,R::decimal($low)]);
            return ['audit'=>['extra_id'=>$id,'before'=>$x?['name'=>$x['name'],'price'=>$x['price'],'active'=>$x['active']]:null,'after'=>['name'=>$name,'price'=>R::decimal($price),'active'=>isset($in['active'])],'previous_category'=>$previousCategory,'category'=>$category,'reason'=>$reason]];
        }
        if (in_array($action,['stock_in','stock_count'],true)) {
            $x=$this->row('extras',(int)($in['extra_id'] ?? $id));$meta=$this->repo->run('SELECT * FROM stay_extra_settings WHERE extra_id=?',[$x['id']])->fetch();if ($meta && !$meta['tracked']) { throw new RuntimeException('Services do not have stock.'); }
            $qty=R::quantity(R::text($in['qty'] ?? '',30,true));$old=R::quantity((string)$x['stock_qty']);$delta=$action==='stock_in'?$qty:$qty-$old;$new=$old+$delta;
            $held=\Tivoli\Repository\StockAvailability::hold($this->repo,(int)$x['id'])['held'];if($new<$held||$action==='stock_count'&&$held>0)throw new RuntimeException('Use reviewed Stock Counts; reported stock holds must be retained.');
            if (($action==='stock_in' && $qty<=0) || $new>999999999999) { throw new RuntimeException('Check stock quantity.'); }
            $this->repo->run('UPDATE extras SET stock_qty=? WHERE id=?',[R::decimal($new),$x['id']]);$this->movement($actor,(int)$x['id'],$action==='stock_in'?'in':'adjustment',$delta,$reason,'stay_stock',0);
            return ['audit'=>['extra_id'=>$x['id'],'before'=>R::decimal($old),'after'=>R::decimal($new),'reason'=>$reason]];
        }
        if ($action==='room_save') {
            $r=$id?$this->row('rooms',$id):['name'=>'','type'=>'','rate'=>null];$rate=R::minor(R::text($in['rate'] ?? '',30,true));$short=R::minor(R::text($in['short_rate'] ?? '0',30,true));
            $type=R::text($in['type'] ?? $r['type'],60,true);if(!array_key_exists($type,$this->roomTypes()))throw new RuntimeException('Choose a room type from the list.');
            if ($rate<=0 || (isset($in['enabled']) && $short<=0)) { throw new RuntimeException('Enabled stay types require a positive rate.'); }
            if(!$id){$this->repo->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,?,?,'vacant')",[R::text($in['name'] ?? '',60,true),$type,R::decimal($rate)]);$id=(int)$this->repo->db->lastInsertId();}
            $this->settings($id);$this->repo->run('UPDATE rooms SET name=?,type=?,rate=? WHERE id=?',[R::text($in['name'] ?? $r['name'],60,true),$type,R::decimal($rate),$id]);
            $this->repo->run('UPDATE stay_room_settings SET short_rate=?,short_enabled=? WHERE room_id=?',[R::decimal($short),isset($in['enabled'])?1:0,$id]);
            return ['audit'=>['room_id'=>$id,'before_rate'=>$r['rate'],'after_rate'=>R::decimal($rate),'short_rate'=>R::decimal($short),'reason'=>$reason]];
        }
        $roomId=(int)($in['room_id'] ?? $id);
        if (in_array($action,['repair','release_fault'],true)) { $f=$this->row('stay_faults',(int)($in['fault_id'] ?? $id));$roomId=(int)$f['room_id']; }
        $room=$this->row('rooms',$roomId);$setting=$this->settings($roomId);
        if(in_array($action,['clean','inspect'],true)&&HousekeepingService::hasAttempts($this->repo,$roomId))throw new RuntimeException('Use the current Housekeeping attempt. This older writer cannot bypass cleaner and inspector controls.');
        if ($action==='fault') {
            $faultId=$this->newFault($actor,$roomId,$reason,R::text($in['owner'] ?? '',120,true),R::text($in['priority'] ?? 'Routine',20,true));
            if(isset($in['_fault_file'])){if(!is_array($in['_fault_file']))throw new RuntimeException('Invalid fault attachment.');FaultAttachment::save($this->access,$actor,$faultId,$in['_fault_file']);}
            return ['fault_id'=>$faultId,'audit'=>['room_id'=>$roomId,'fault_id'=>$faultId,'reason'=>$reason,'attachment'=>isset($in['_fault_file'])]];
        } elseif ($action==='repair') {
            if(!in_array($f['status'],['open','awaiting_inspection'],true))throw new RuntimeException('This fault has already been closed.');
            if($f['status']==='open')$this->repo->run("UPDATE stay_faults SET status='closed',repair_reason=?,repair_by=?,repair_at=?,closed_at=? WHERE id=?",[$reason,$actor,$this->now(),$this->now(),$f['id']]);
            else $this->repo->run("UPDATE stay_faults SET status='closed',closed_at=? WHERE id=?",[$this->now(),$f['id']]);
            if(isset($in['_fault_file'])){if(!is_array($in['_fault_file']))throw new RuntimeException('Invalid repair evidence.');FaultAttachment::save($this->access,$actor,(int)$f['id'],$in['_fault_file'],'repair');}
            $this->restoreAfterRepair($actor,$roomId);
            return ['fault_id'=>(int)$f['id'],'audit'=>['room_id'=>$roomId,'fault_id'=>(int)$f['id'],'reason'=>$reason,'attachment'=>isset($in['_fault_file']),'previous_status'=>$f['status'],'status'=>'closed']];
        } elseif ($action==='release_fault') {
            throw new RuntimeException('The separate repair-release step has been removed. Open Record Repair to close this fault.');
        } elseif ($action==='clean') {
            $this->roomUnused($roomId);if ($room['status']!=='dirty' || $setting['ready_state']!=='cleaning' || $this->blocked($roomId)) { throw new RuntimeException('Only an unblocked cleaning room can be marked cleaned.'); }
            $completed=CleaningChecklist::complete($this->repo,$in);
            $this->repo->run("UPDATE stay_room_settings SET ready_state='awaiting_inspection' WHERE room_id=?",[$roomId]);
            return ['audit'=>['room_id'=>$roomId,'reason'=>$reason,'checklist'=>$completed]];
        } elseif ($action==='inspect') {
            $this->roomUnused($roomId);if ($this->blocked($roomId) || !in_array($setting['ready_state'],['unverified','awaiting_inspection'],true) || !in_array($room['status'],['dirty','vacant'],true)) { throw new RuntimeException('Complete cleaning or repair before inspector release.'); }
            $completed=CleaningChecklist::complete($this->repo,$in,'inspection');
            $this->repo->run("UPDATE rooms SET status='vacant' WHERE id=?",[$roomId]);$this->repo->run("UPDATE stay_room_settings SET ready_state='ready' WHERE room_id=?",[$roomId]);
            return ['audit'=>['room_id'=>$roomId,'reason'=>$reason,'checklist'=>$completed]];
        } elseif ($action==='room_audit') {
            $result=R::text($in['result'] ?? '',24,true);if (!in_array($result,['pass','corrective','unsafe'],true)) { throw new RuntimeException('Choose an audit result.'); }
            $owner=R::text($in['owner'] ?? '',120);$this->repo->run('INSERT INTO stay_room_audits(room_id,result,reason,owner,actor_id,created_at) VALUES(?,?,?,?,?,?)',[$roomId,$result,$reason,$owner,$actor,$this->now()]);
            $auditId=(int)$this->repo->db->lastInsertId();$faultId=$result==='unsafe'?$this->newFault($actor,$roomId,'Weekly audit: '.$reason,$owner,'Critical'):0;
            return ['audit'=>['room_id'=>$roomId,'room_audit_id'=>$auditId,'fault_id'=>$faultId,'reason'=>$reason]];
        } else { throw new RuntimeException('Unknown occupancy action.'); }
        return ['audit'=>['room_id'=>$roomId,'reason'=>$reason]];
    }
    private function newFault(int $actor,int $room,string $reason,string $owner,string $priority): int
    {
        if(!in_array($priority,['Routine','Urgent','Critical'],true))throw new RuntimeException('Choose Routine, Urgent or Critical priority.');
        $this->repo->run('INSERT INTO stay_faults(room_id,reason,priority,owner,created_by,created_at) VALUES(?,?,?,?,?,?)',[$room,$reason,$priority,$owner,$actor,$this->now()]);
        $faultId=(int)$this->repo->db->lastInsertId();
        if($priority==='Critical'){
            $r=$this->row('rooms',$room);$settings=$this->settings($room);
            if($settings['ready_state']!=='blocked')$this->access->audit($actor,'stay.room_blocked','room',(string)$room,'succeeded',['fault_id'=>$faultId,'before_status'=>$r['status'],'before_readiness'=>$settings['ready_state']]);
            if(!$this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition(),[$room])->fetchColumn())$this->repo->run("UPDATE rooms SET status='maintenance' WHERE id=?",[$room]);
            $this->repo->run("UPDATE stay_room_settings SET ready_state='blocked' WHERE room_id=?",[$room]);
        }
        return $faultId;
    }
    private function restoreAfterRepair(int $actor,int $room): void
    {
        if($this->blocked($room))return;
        $settings=$this->settings($room);if($settings['ready_state']!=='blocked')return;
        $current=$this->row('rooms',$room);
        $event=$this->repo->run("SELECT details FROM security_audit WHERE action='stay.room_blocked' AND target_type='room' AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",[(string)$room])->fetchColumn();
        $before=$event?json_decode($event,true,512,JSON_THROW_ON_ERROR):[];
        $readiness=$before['before_readiness'] ?? 'unverified';
        if(!in_array($readiness,['ready','cleaning','awaiting_inspection','unverified'],true))$readiness='unverified';
        $occupied=(bool)$this->repo->run("SELECT b.id FROM bookings b WHERE b.room_id=? AND b.status='active'".$this->occupancyCondition(),[$room])->fetchColumn();
        $status=$occupied?'occupied':(($before['before_status'] ?? '')==='maintenance'?'maintenance':(in_array($readiness,['cleaning','awaiting_inspection'],true)?'dirty':'vacant'));
        $this->repo->run('UPDATE rooms SET status=? WHERE id=?',[$status,$room]);$this->repo->run('UPDATE stay_room_settings SET ready_state=? WHERE room_id=?',[$readiness,$room]);
        $this->access->audit($actor,'stay.room_block_cleared','room',(string)$room,'succeeded',['before_status'=>$current['status'],'readiness'=>$readiness,'status'=>$status,'prior_condition_known'=>(bool)$event]);
    }
    public function areaAudits(int $actor,array $filters=[]): array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');$this->admin($actor);$this->access->requirePrivilege($actor,'rooms.manage');$this->installed();
        $result=WeeklyAreaAudit::listing($this->repo,$filters,$this->now());
        $this->access->audit($actor,'stay.area_audits_viewed','area_audit',null,'succeeded',['rows'=>count($result['rows']),'week'=>$result['week']]);return $result;
    }
    public function folios(int $actor,int $before=0,array $filters=[]): array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');$this->installed();
        if($before<0)throw new RuntimeException('Invalid account cursor.');
        $today=new \DateTimeImmutable($this->now(),new \DateTimeZone('UTC'));
        $monday=$today->modify('-'.((int)$today->format('N')-1).' days');
        $all=($filters['range'] ?? '')==='all';
        $start=$filters['start'] ?? $monday->format('Y-m-d');$end=$filters['end'] ?? $monday->modify('+6 days')->format('Y-m-d');
        if(!$all){
            foreach([$start,$end] as $date){$parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC')):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Choose valid start and end dates.');}
            if($start>$end)throw new RuntimeException('Start date must be on or before end date.');
        }
        $where=[];$values=[];
        $bookingSource=\Tivoli\Repository\DepartureFacts::source((new FinanceService($this->access))->installed());
        if(!$all){$where[]="b.checkin_at<=? AND (b.actual_checkout>=? OR b.operational_status='active' OR (b.actual_checkout IS NULL AND b.checkin_at>=?))";$values=[$end.' 23:59:59',$start.' 00:00:00',$start.' 00:00:00'];}
        if($before){$where[]='b.id<?';$values[]=$before;}
        $rows=$this->repo->run("SELECT b.id,b.guest_name,b.operational_status status,b.checkin_at,b.actual_checkout checkout_at,r.name room_name,COALESCE(d.stay_type,'overnight') stay_type FROM (".$bookingSource.") b JOIN rooms r ON r.id=b.room_id LEFT JOIN stay_details d ON d.booking_id=b.id".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY b.id DESC LIMIT 51',$values)->fetchAll();
        $more=count($rows)>50;if($more)array_pop($rows);
        $rows=array_map(fn($row)=>$this->displayDeparture($row),$rows);
        $range=$all?['range'=>'all']:['start'=>$start,'end'=>$end];
        $this->access->audit($actor,'stay.folios_viewed','booking',null,'succeeded',['rows'=>count($rows),'before'=>$before,'filter'=>$range,'definition'=>'stays_overlapping_inclusive_dates_utc']);
        return ['rows'=>$rows,'next_before'=>$more?(int)$rows[count($rows)-1]['id']:null,'filters'=>$range];
    }
    public function operationalActivity(int $actor,int $before=0): array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');$this->installed();
        if($before<0)throw new RuntimeException('Invalid activity cursor.');
        $rows=$this->repo->run("SELECT a.id,a.actor_id,u.name actor_name,a.action,a.target_type,a.target_id,a.outcome,a.occurred_at created_at,a.details payload FROM security_audit a LEFT JOIN users u ON u.id=a.actor_id WHERE (a.action LIKE 'stay.%' OR a.action IN ('housekeeping.start','housekeeping.progress','housekeeping.completed','housekeeping.inspect','housekeeping.request_exception','housekeeping.decide_exception','housekeeping.followup','housekeeping.restart','housekeeping.failed')) AND a.action NOT IN ('stay.viewed','stay.folios_viewed','stay.activity_viewed','stay.area_audits_viewed','stay.departure_cases_viewed')".($before?' AND a.id<?':'').' ORDER BY a.id DESC LIMIT 51',$before?[$before]:[])->fetchAll();
        $more=count($rows)>50;if($more)array_pop($rows);
        $this->access->audit($actor,'stay.activity_viewed','booking',null,'succeeded',['rows'=>count($rows),'before'=>$before]);
        return ['rows'=>$rows,'next_before'=>$more?(int)$rows[count($rows)-1]['id']:null];
    }
    public function snapshot(int $actor,int $bookingId=0): array
    {
        $this->access->requirePrivilege($actor,'bookings.manage');
        $this->installed();
        $rooms=$this->repo->run("SELECT r.*,COALESCE(s.short_rate,'0.00') short_rate,COALESCE(s.short_enabled,0) short_enabled,COALESCE(s.ready_state,'unverified') ready_state,b.id booking_id,b.guest_name,COALESCE(d.stay_type,'overnight') stay_type,d.due_at,EXISTS(SELECT 1 FROM stay_faults f WHERE f.room_id=r.id AND f.priority='Critical' AND f.status<>'closed') blocked FROM rooms r LEFT JOIN stay_room_settings s ON s.room_id=r.id LEFT JOIN bookings b ON b.room_id=r.id AND b.status='active'".$this->occupancyCondition()." LEFT JOIN stay_details d ON d.booking_id=b.id ORDER BY r.name")->fetchAll();
        $data=['rooms'=>$rooms,'recent'=>$this->repo->run("SELECT b.*,r.name room_name,COALESCE(d.stay_type,'overnight') stay_type FROM bookings b JOIN rooms r ON r.id=b.room_id LEFT JOIN stay_details d ON d.booking_id=b.id WHERE b.status='checked_out' ORDER BY b.checkout_at DESC,b.id DESC LIMIT 100")->fetchAll(),'extras'=>$this->repo->run("SELECT e.*,COALESCE(m.category,'General') category,COALESCE(m.tracked,1) tracked,COALESCE(m.low_stock,0) low_stock,(m.extra_id IS NOT NULL) configured FROM extras e LEFT JOIN stay_extra_settings m ON m.extra_id=e.id ORDER BY e.active DESC,e.name")->fetchAll(),'faults'=>$this->repo->run('SELECT f.*,r.name room_name,EXISTS(SELECT 1 FROM security_audit a WHERE a.target_type=\'room_fault\' AND a.target_id=CAST(f.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND a.action=\'stay.fault_attachment_saved\' AND a.outcome=\'succeeded\') attachment_available,EXISTS(SELECT 1 FROM security_audit a WHERE a.target_type=\'room_fault\' AND a.target_id=CAST(f.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND a.action=\'stay.repair_attachment_saved\' AND a.outcome=\'succeeded\') repair_attachment_available FROM stay_faults f JOIN rooms r ON r.id=f.room_id ORDER BY f.id DESC LIMIT 100')->fetchAll(),'audits'=>$this->repo->run('SELECT a.*,r.name room_name FROM stay_room_audits a JOIN rooms r ON r.id=a.room_id ORDER BY a.id DESC LIMIT 100')->fetchAll(),'movements'=>$this->repo->run('SELECT m.*,e.name extra_name FROM stock_movements m JOIN extras e ON e.id=m.extra_id ORDER BY m.id DESC LIMIT 100')->fetchAll(),'refunds'=>[],'booking'=>null,'bill'=>null,'payments'=>[],'lines'=>[],'incidentals'=>[],'activity'=>[]];
        $heldByItem=[];
        if((new FinanceService($this->access))->installed())foreach($this->repo->run("SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.extra_id')) FROM finance_events WHERE target_type='stock_issue' AND kind='si_reported'")->fetchAll(\PDO::FETCH_COLUMN) as $extra)$heldByItem[$extra]=\Tivoli\Repository\StockAvailability::hold($this->repo,(int)$extra)['held'];
        foreach($data['extras'] as &$extra){$extra['held_qty']=R::decimal($heldByItem[$extra['id']]??0);$extra['available_qty']=R::decimal(R::quantity((string)$extra['stock_qty'])-($heldByItem[$extra['id']]??0));}unset($extra);
        if ($bookingId>0) {
            $b=$this->repo->run("SELECT b.*,r.name room_name,r.type,d.stay_type,d.gender detail_gender,d.id_type,d.identity_verified,d.payment_attested,d.room_checked,d.due_at,EXISTS(SELECT 1 FROM stay_identity i WHERE i.booking_id=b.id) identity_available FROM bookings b JOIN rooms r ON r.id=b.room_id LEFT JOIN stay_details d ON d.booking_id=b.id WHERE b.id=?",[$bookingId])->fetch();
            if (!$b) { throw new RuntimeException('Guest account not found.'); }$b['stay_type']=$b['stay_type'] ?? 'overnight';$b['gender']=$b['detail_gender'] ?? $b['gender'];$data['booking']=$this->displayDeparture($b);$data['bill']=$this->bill($b);$data['departure_case']=$this->departureRecord($bookingId);
            $data['prior_departure_cases']=$data['departure_case']?$this->priorDepartureCases($bookingId,(int)$data['departure_case']['report']['id']):[];
            $refundEvidence=\Tivoli\Repository\ReceiptAccounting::installed($this->repo)?"(SELECT e.payload FROM finance_events e WHERE e.kind='refund_recorded' AND e.target_type='refund' AND e.target_id=CAST(x.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci ORDER BY e.id DESC LIMIT 1)":'NULL';
            $data['refunds']=$this->repo->run('SELECT x.*,u.name recorded_by_name,'.$refundEvidence.' refund_metadata FROM stay_refunds x LEFT JOIN users u ON u.id=x.actor_id WHERE x.booking_id=? ORDER BY x.id',[$bookingId])->fetchAll();
            $recovery=$this->recoveryInstalled();
            $data['payments']=$this->repo->run('SELECT p.*,s.actor_id,s.reference_no,cashier.name cashier_name'
                .($recovery?",JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.original_activity_date')) recovered_activity_date,JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.mode')) recovery_mode":'')
                .' FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id LEFT JOIN users cashier ON cashier.id=s.actor_id'
                .($recovery?' LEFT JOIN finance_events e ON e.id=p.recovery_event_id':'')
                .' WHERE p.booking_id=? ORDER BY p.id',[$bookingId])->fetchAll();
            $data['payments']=\Tivoli\Repository\ReceiptAccounting::project($this->repo,$data['payments']);
            $data['charge_corrections']=\Tivoli\Repository\ChargeAccounting::entries($this->repo,$bookingId);$data['original_room_charge']=$data['bill']['room'];foreach($data['charge_corrections'] as $entry)if($entry['data']['charge_type']==='room')$data['original_room_charge']-=$entry['amount_minor'];
            $data['receipt_corrections']=(new FinanceService($this->access))->installed()?$this->repo->run("SELECT id,payload,created_at FROM finance_events WHERE kind='rc_posted' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.booking_id'))=? ORDER BY id",[(string)$bookingId])->fetchAll():[];
            $data['can_recover_receipt']=$recovery && $b['status']==='checked_out'
                && $data['bill']['checkout_balance']!==null && !$data['bill']['legacy_unknown'] && $data['bill']['balance']>0
                && (new FinanceService($this->access))->canPostLate($actor);
            $data['lines']=$this->repo->run('SELECT * FROM booking_extras WHERE booking_id=? ORDER BY id',[$bookingId])->fetchAll();$data['incidentals']=$this->repo->run('SELECT * FROM stay_incidentals WHERE booking_id=? ORDER BY id',[$bookingId])->fetchAll();
            $data['activity']=$this->repo->run("SELECT action,occurred_at created_at,details payload FROM security_audit WHERE target_type='booking' AND target_id=? AND action LIKE 'stay.%' ORDER BY id DESC LIMIT 100",[(string)$bookingId])->fetchAll();
            $this->access->audit($actor,'stay.viewed','booking',(string)$bookingId,'succeeded');
        }
        return $data;
    }
}
