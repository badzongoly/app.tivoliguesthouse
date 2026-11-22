<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Repository\DepartureFacts;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** Duty acknowledgement and physical cash custody are deliberately separate events. */
final class HandoverService
{
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function finance():FinanceService{return new FinanceService($this->access,$this->now());}
    public function owner(int $actor):bool{return $this->finance()->canPostLate($actor);}
    public function permit(int $actor):void{
        $this->access->requirePrivilege($actor,'bookings.manage');
        if(!$this->eligible($actor))throw new PermissionDenied('This staff account is not eligible for operational handovers.');
        if(!$this->finance()->installed())throw new RuntimeException('Activate the existing finance setup before recording handovers.');
    }
    private function eligible(int $id):bool{
        $u=$this->access->repo->user($id);
        if(!$u||!$u['active']||!in_array('bookings.manage',$this->access->repo->privileges($id),true))return false;
        $r=$this->access->repo;
        if($r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn()
            &&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('exited','suspended') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$id,substr($this->now(),0,10)])->fetchColumn())return false;
        return true;
    }
    public function recipients(int $actor):array{
        $this->permit($actor);$rows=$this->access->repo->run('SELECT u.id,u.name FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE a.active=1 AND u.id<>? ORDER BY u.name,u.id',[$actor])->fetchAll();
        return array_values(array_filter($rows,fn($u)=>$this->eligible((int)$u['id'])));
    }
    private function hash(array $data):string{return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));}
    private function id(mixed $id):int{if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,17}$/D',$id))throw new RuntimeException('Choose a valid handover record.');return (int)$id;}
    private function event(string $kind,int $actor,array $data,int $target=0,int $amount=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'handover',(string)$target,$amount,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);
        return (int)$this->access->repo->db->lastInsertId();
    }
    private function time(string $text):string{
        $text=str_replace('T',' ',$text);if(strlen($text)===16)$text.=':00';
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$text,new \DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d H:i:s')!==$text||$text<'2000-01-01 00:00:00'||$text>$this->now())throw new RuntimeException('Enter actual duty times in UTC, no later than now.');
        return $text;
    }
    public function draft(int $actor):array{
        $this->permit($actor);$r=$this->access->repo;$finance=$this->finance();$cash=$finance->cash();$stays=new StayService($this->access,$this->now());
        $sections=['guests'=>['title'=>'Guests & Stay Accounts','items'=>[]],'cash'=>['title'=>'Cash & MoMo','items'=>[]],'rooms'=>['title'=>'Rooms & Property','items'=>[]],'tasks'=>['title'=>'Incidents & Pending Work','items'=>[]]];
        $item=static function(string $label,string $detail,string $link=''):array{return compact('label','detail','link');};
        foreach($r->run("SELECT b.*,room.name room_name,COALESCE(d.stay_type,'overnight') stay_type,d.due_at FROM (".DepartureFacts::source(true).") b JOIN rooms room ON room.id=b.room_id LEFT JOIN stay_details d ON d.booking_id=b.id WHERE b.operational_status='active' ORDER BY room.name,b.id")->fetchAll() as $b){
            $bill=$stays->bill($b);$sections['guests']['items'][]=$item('Room '.$b['room_name'].' · '.$b['guest_name'],($b['stay_type']==='short'?'Short Stay'.($b['due_at']?' · Due '.\display_date($b['due_at']).' UTC':' · Departure Deadline Unknown'):'Overnight').' · '.($bill['legacy_unknown']?'Historical Rate Unknown':'Balance GHS '.R::decimal($bill['balance'])),'occupancy.php?booking_id='.$b['id']);
        }
        foreach($stays->departureCases($actor) as $case)if(!$case['resolved'])$sections['tasks']['items'][]=$item('Departure #'.$case['id'].' · '.$case['guest_name'],$case['case']['status'].($case['bill']['provisional']?' · Provisional Bill':''),'occupancy.php?booking_id='.$case['id']);
        $own=$cash['custodians'][$actor]??['held'=>0,'reserved'=>0,'available'=>0];
        $sections['cash']['items'][]=$item('My Sales Cash','GHS '.R::decimal($own['held']).' Accountable · GHS '.R::decimal($own['reserved']).' Reserved For Transfer','finance.php?page=deposits');
        foreach($cash['rows'] as $row)if(($row['custody'][$actor]??0)>0)$sections['cash']['items'][]=$item('Cash Source #'.$row['id'],'GHS '.R::decimal($row['custody'][$actor]).' · Deposit Due '.\display_date($row['due']),'finance.php?page=deposits');
        foreach($finance->unmatched(substr($this->now(),0,10)) as $receipt){
            $collector=$receipt['source']==='income'?$r->run('SELECT actor_id FROM finance_events WHERE id=?',[$receipt['id']])->fetchColumn():$r->run('SELECT actor_id FROM stay_receipts WHERE payment_id=?',[$receipt['id']])->fetchColumn();
            if((int)$collector===$actor)$sections['cash']['items'][]=$item('MoMo #'.$receipt['source'].':'.$receipt['id'],'GHS '.$receipt['amount'].' · Awaiting Independent Match','finance.php?page=daily');
        }
        foreach($r->run("SELECT room.id,room.name,room.status,COALESCE(s.ready_state,'unverified') ready_state FROM rooms room LEFT JOIN stay_room_settings s ON s.room_id=room.id ORDER BY room.name")->fetchAll() as $room)if($room['status']!=='occupied'&&($room['status']!=='vacant'||$room['ready_state']!=='ready'))$sections['rooms']['items'][]=$item('Room '.$room['name'],$room['status'].' · '.$room['ready_state'],'occupancy.php?section=rooms');
        foreach($r->run("SELECT f.id,f.reason,f.priority,room.name FROM stay_faults f JOIN rooms room ON room.id=f.room_id WHERE f.status<>'closed' ORDER BY f.id")->fetchAll() as $fault)$sections['rooms']['items'][]=$item('Fault #'.$fault['id'].' · Room '.$fault['name'],$fault['priority'].' · '.$fault['reason'],'occupancy.php?section=rooms&tab=faults');
        foreach($r->run("SELECT id,description,amount,expense_date FROM expense_submissions WHERE status='pending' AND submitted_by=? ORDER BY id",[$actor])->fetchAll() as $expense)$sections['tasks']['items'][]=$item('Expense Request #'.$expense['id'],$expense['description'].' · GHS '.$expense['amount'].' · Awaiting Approval','expenses.php');
        foreach((new HousekeepingService($this->access,$this->now()))->tasks($actor) as $task)$sections['tasks']['items'][]=$item('Housekeeping #'.$task['root']['id'].' · Room '.$task['root']['data']['room_name'],$task['status'].($task['valid']?'':' · Room facts changed'),'housekeeping.php?id='.$task['root']['id']);
        foreach((new StockCountService($this->access,$this->now()))->attention($actor,true)['rows'] as $count)$sections['tasks']['items'][]=$item('Stock Count #'.$count['root']['id'],ucfirst($count['status']),'stock-counts.php?id='.$count['root']['id']);
        foreach((new PurchaseCorrectionService($this->access,$this->now()))->providerOutstanding($actor,true) as $provider)$sections['tasks']['items'][]=$item('Provider #'.$provider['source_id'].' · '.$provider['supplier'],'Supplier Recovery Due GHS '.R::decimal($provider['supplier_due']),'financial-corrections.php?area=purchases&source_type=provider&source_id='.$provider['source_id']);
        foreach((new PurchaseService($this->access,$this->now()))->forHandover($actor) as $purchase)$sections['tasks']['items'][]=$item('Purchase #'.$purchase['root']['id'].' · '.$purchase['root']['data']['title'],$purchase['status']==='posted'?($purchase['settlement_blockers']?'Payment Evidence Needs Reconciliation':($purchase['paid']>$purchase['receipt']['cost']?'Supplier Recovery Due':'Payment Due')):ucwords(str_replace('_',' ',$purchase['status'])),'purchases.php?id='.$purchase['root']['id']);
        $snapshot=['sections'=>$sections,'cash'=>$own];return ['snapshot'=>$snapshot,'token'=>$this->hash([$actor,$snapshot]),'recipients'=>$this->recipients($actor)];
    }
    public function detail(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$row=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind IN ('handover_submitted','cash_transfer_sent') AND e.target_type='handover'",[$id])->fetch();
        if(!$row)throw new RuntimeException('Handover record not found.');
        $row['data']=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$to=(int)$row['data']['recipient_id'];
        if($actor!==(int)$row['actor_id']&&$actor!==$to&&!$this->owner($actor))throw new PermissionDenied('Only the outgoing staff member, recipient or designated owner may view this record.');
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='handover' AND e.target_id=? AND e.kind IN ('handover_acknowledged','handover_reviewed','cash_transfer_acknowledged','cash_transfer_disputed','cash_transfer_reviewed','cd_acknowledged','cd_cash_returned') ORDER BY e.id",[(string)$id])->fetchAll();
        $status='pending';$ack=null;$review=null;
        foreach($history as &$event){$event['data']=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
            if($event['kind']==='handover_acknowledged'){$ack=$event;$status=!empty($event['data']['discrepancy'])?'exception':'acknowledged';}
            if($event['kind']==='handover_reviewed'){$review=$event;$status='acknowledged';}
            if($event['kind']==='cash_transfer_disputed')$status='disputed';
            if($event['kind']==='cash_transfer_acknowledged'){$ack=$event;$status='acknowledged';}
        }unset($event);
        if($row['kind']==='cash_transfer_sent'){
            $current=\Tivoli\Repository\ReceiptReallocation::event($r,$row);$currentHistory=array_map(fn($e)=>\Tivoli\Repository\ReceiptReallocation::event($r,$e),$history);$resolution=\Tivoli\Domain\Finance\CashResolution::position('handover',$id,(int)$row['amount_minor'],$currentHistory,$current['data']['allocations']);$row['current_allocations']=$current['data']['allocations'];$row['allocation_revision']=$current['allocation_revision']??0;foreach($currentHistory as $i=>$e)$history[$i]['allocation_revision']=$e['allocation_revision']??0;
            if($resolution['touched'])$status=$resolution['remaining']===0?($resolution['returned']?'resolved_returned':'acknowledged'):'partially_acknowledged';
        }
        $recipient=$r->run('SELECT name FROM users WHERE id=?',[$to])->fetchColumn();
        return ['event'=>$row,'recipient_name'=>$recipient,'status'=>$status,'ack'=>$ack,'review'=>$review,'history'=>$history,'version'=>$this->hash([$row['id'],array_column($history,'id'),$row['allocation_revision']??0,array_column($history,'allocation_revision')])];
    }
    public function register(int $actor,int $before=0):array{
        $this->permit($actor);$where=$this->owner($actor)?'':' AND (e.actor_id=? OR JSON_UNQUOTE(JSON_EXTRACT(e.payload,\'$.recipient_id\'))=?)';$params=$this->owner($actor)?[]:[$actor,(string)$actor];
        if($before>0){$where.=' AND e.id<?';$params[]=$before;}
        $ids=$this->access->repo->run("SELECT e.id FROM finance_events e WHERE e.target_type='handover' AND e.kind IN ('handover_submitted','cash_transfer_sent')".$where.' ORDER BY e.id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);
        $more=count($ids)>50;if($more)array_pop($ids);$rows=array_map(fn($id)=>$this->detail($actor,(int)$id),$ids);
        return ['rows'=>$rows,'next'=>$more?(int)end($ids):0];
    }
    public function attention(int $actor):array{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may review all handovers.');
        $pending=0;$exceptions=0;$cash=0;$rows=[];
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='handover' AND kind IN ('handover_submitted','cash_transfer_sent') ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){$case=$this->detail($actor,(int)$id);if(in_array($case['status'],['acknowledged','resolved_returned'],true))continue;$rows[]=$case;
            if($case['event']['kind']==='cash_transfer_sent')$cash++;elseif($case['status']==='exception')$exceptions++;else $pending++;
        }return compact('pending','exceptions','cash','rows');
    }
    public function execute(int $actor,string $action,array $input,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the form before recording this action.');
            foreach($input as $value)if(!is_string($value))throw new RuntimeException('Invalid handover form.');
            unset($input['csrf'],$input['operation_key'],$input['action']);ksort($input);$fingerprint=$this->hash(['handover.'.$action,$input]);
            return $this->access->transaction(function()use($actor,$action,$input,$key,$fingerprint){
                $this->permit($actor);$r=$this->access->repo;
                $old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fingerprint))throw new RuntimeException('This form was already used with different details.');$this->access->audit($actor,'handover.replayed','handover',(string)$old['event_id'],'recorded');return ['id'=>(int)$old['event_id'],'replayed'=>true];}
                $id=$this->command($actor,$action,$input);$r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$id]);$this->access->audit($actor,'handover.'.$action,'handover',(string)$id,'succeeded');
                return ['id'=>$id,'replayed'=>false];
            });
        }catch(Throwable $error){$this->access->audit($actor,'handover.failed','handover',null,'denied',['action'=>substr($action,0,40)]);throw $error;}
    }
    private function command(int $actor,string $action,array $in):int{
        if(in_array($action,['submit','cash_send'],true)){
            $recipient=$this->id($in['recipient_id']??'');if($recipient===$actor||!$this->eligible($recipient))throw new RuntimeException('Choose another active staff account with guest-operation access.');
            $name=$this->access->repo->run('SELECT name FROM users WHERE id=?',[$recipient])->fetchColumn();
            if($action==='submit'){
                foreach(['guests','cash','rooms','tasks'] as $section)if(($in['review_'.$section]??'')!=='1')throw new RuntimeException('Review every section before submitting.');
                if(($in['confirmed']??'')!=='1')throw new RuntimeException('Confirm that the handover is accurate.');
                $start=$this->time(R::text($in['duty_start']??'',19,true));$end=$this->time(R::text($in['duty_end']??'',19,true));if($end<$start)throw new RuntimeException('Duty end must not precede duty start.');
                $draft=$this->draft($actor);if(!hash_equals($draft['token'],$in['review_token']??''))throw new RuntimeException('Open items changed. Review a fresh handover before submitting.');
                return $this->event('handover_submitted',$actor,['recipient_id'=>$recipient,'recipient_name'=>$name,'sender_name'=>$this->access->repo->user($actor)['name'],'duty_start'=>$start,'duty_end'=>$end,'notes'=>R::text($in['notes']??'',2000),'snapshot'=>$draft['snapshot']]);
            }
            if(($in['passed']??'')!=='1')throw new RuntimeException('Confirm that you physically handed over this cash.');
            $amount=R::minor(R::text($in['amount']??'',24,true));if($amount<=0||$amount>999999999999)throw new RuntimeException('Enter a positive cash amount within the ledger limit.');
            $cash=$this->finance()->cash();$left=$amount;$allocations=[];
            foreach($cash['rows'] as $row){if($row['created_at']>$this->now())continue;if(($row['custody_since'][$actor]??$row['created_at'])>$this->now())continue;$n=min($left,$row['available'][$actor]??0);if($n){$allocations[(string)$row['id']]=$n;$left-=$n;}if(!$left)break;}
            if($left)throw new RuntimeException('Transfer exceeds your available cash. Deposits and pending transfers already reserve their cash.');
            return $this->event('cash_transfer_sent',$actor,['recipient_id'=>$recipient,'recipient_name'=>$name,'sender_name'=>$this->access->repo->user($actor)['name'],'allocations'=>$allocations,'comment'=>R::text($in['comment']??'',2000)],0,$amount);
        }
        $id=$this->id($in['id']??'');$case=$this->detail($actor,$id);if(!hash_equals($case['version'],$in['version']??''))throw new RuntimeException('This record changed. Reload and review the latest history.');
        $event=$case['event'];$duty=$event['kind']==='handover_submitted';$to=(int)$event['data']['recipient_id'];$comment=R::text($in['comment']??'',2000);
        if($action==='review'){
            if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may record this review.');
            if($duty&&$case['status']!=='exception')throw new RuntimeException('There is no incoming handover discrepancy to review.');
            if(!$duty&&$case['status']==='acknowledged')throw new RuntimeException('This cash transfer is already acknowledged.');
            return $this->event($duty?'handover_reviewed':'cash_transfer_reviewed',$actor,['comment'=>R::text($comment,2000,true)],$id);
        }
        if(!$duty&&in_array($action,['cash_ack','cash_dispute'],true))CashDiscrepancyService::guard($this->access,'handover',$id);
        if($actor!==$to)throw new PermissionDenied('Only the named recipient may acknowledge or dispute receipt.');
        if($action==='ack'&&$duty){
            if($case['status']!=='pending'||($in['reviewed']??'')!=='1')throw new RuntimeException('Review this pending handover before acknowledging.');
            $discrepancy=($in['flag']??'')==='1'?R::text($in['discrepancy']??'',2000,true):'';
            return $this->event('handover_acknowledged',$actor,compact('comment','discrepancy'),$id);
        }
        if(!$duty&&$action==='cash_dispute'&&$case['status']==='pending')return $this->event('cash_transfer_disputed',$actor,['comment'=>R::text($comment,2000,true)],$id);
        if(!$duty&&$action==='cash_ack'&&in_array($case['status'],['pending','disputed'],true)){
            if(($in['received']??'')!=='1')throw new RuntimeException('Confirm actual receipt of the full cash amount.');
            if($case['status']==='disputed')$comment=R::text($comment,2000,true);
            return $this->event('cash_transfer_acknowledged',$actor,['comment'=>$comment],$id,(int)$event['amount_minor']);
        }
        throw new RuntimeException('This action is not available for the current record.');
    }
}
