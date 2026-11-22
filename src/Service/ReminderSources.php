<?php
namespace Tivoli\Service;
use RuntimeException;
use Throwable;
use Tivoli\Security\PermissionDenied;

/** Original source authority; no source mutation or copied financial balance. */
final class ReminderSources
{
    use ReminderRoutine,ReminderHandoffs;
    private ?array $snapshot=null;
    public function __construct(private readonly AccessService $access,private readonly string $now,private readonly array $config,private readonly ?array $onlyKeys=null){if($onlyKeys!==null&&getenv('TIVOLI_TEST_MODE')!=='1')throw new RuntimeException('Synthetic source filters are test-only.');}
    public function reset():void{$this->snapshot=null;$this->cashSnapshot=null;}
    public function accepts(string $key):bool{return $this->onlyKeys===null||in_array($key,$this->onlyKeys,true);}
    public function owner():int{$id=(int)(getenv('TIVOLI_FINANCE_OWNER_USER_ID')?:($this->config['finance_owner_user_id']??0));return $id&&(new FinanceService($this->access,$this->now))->canPostLate($id)?$id:0;}
    public function active(int $actor):bool{
        $u=$this->access->repo->user($actor);if(!$u||!$u['active']||$u['must_change_password'])return false;
        if($this->access->repo->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn())if($this->access->repo->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?))",[$actor,substr($this->now,0,10)])->fetchColumn())return false;return true;
    }
    public function all(int $owner,bool $locked=false):array{
        if(!$owner||$owner!==$this->owner())throw new PermissionDenied('Only the designated owner can check shared reminders.');
        if($this->snapshot===null){$data=(new OwnerExceptionService($this->access,$this->now))->reminderSources($owner,$locked);$rows=[];foreach($data['rows'] as $row){if($this->onlyKeys!==null&&!in_array($row['key'],$this->onlyKeys,true))continue;$rows[$row['key']]=$row;}$rows+=$this->routineRows();$this->snapshot=['rows'=>$rows,'coverage'=>$data['coverage']];}return $this->snapshot;
    }
    public function episode(array $row):string{
        $reopen=0;foreach($row['history']??[] as $e)if(in_array($e['kind'],['cd_reopened','wa_source'],true))$reopen=max($reopen,(int)$e['id']);
        return hash('sha256',json_encode([$row['key'],$row['state'],$row['responsible_id'],$row['decision'],$reopen],JSON_THROW_ON_ERROR));
    }
    public function inspect(int $actor,string $key,bool $locked=false):array{
        if(!$this->active($actor))throw new PermissionDenied('This account is unavailable.');
        if($actor===$this->owner()){
            $data=$this->all($actor,$locked);if(isset($data['rows'][$key]))return $data['rows'][$key];
            // Restricted sources must never leak their old title/count after permission removal.
            $type=explode(':',$key,2)[0];$codes=$this->access->repo->privileges($actor);$feed=match($type){'provider'=>'providers','cash'=>'cash_sources','legacy_room','legacy_area'=>'legacy_audits',default=>$type};if(($data['coverage'][$feed]??'')==='restricted')throw new PermissionDenied('Current source authority is required.');
            if($type==='payroll'&&!in_array('hr.salary',$codes,true))throw new PermissionDenied('Private payroll authority is required.');
            if($type==='weekly'&&!array_intersect(['rooms.manage','bookings.manage'],$codes))throw new PermissionDenied('Audit authority is required.');
            $handoff=$this->knownHandoff($actor,$key);if($handoff)return $handoff;
            throw new RuntimeException('Original source is unavailable or no longer in the current owner queue.');
        }
        if(str_starts_with($key,'routine:'))return $this->inspectRoutine($actor,$key);
        if(!preg_match('/^([a-z_]+):([1-9][0-9]*)$/D',$key,$m)){
            if(str_starts_with($key,'cash:'))return $this->cash($actor,substr($key,5));throw new PermissionDenied('This source requires owner review.');
        }
        $type=$m[1];$id=(int)$m[2];$path='';$title='';$status='';$responsible=null;$resolved=false;$actionable=false;$due='';
        switch($type){
            case 'weekly':$c=(new WeeklyAuditService($this->access,$this->now))->detail($actor,$id);$title='Audit Follow-Up #'.$id;$status=$c['status'];$resolved=$status==='closed';$responsible=(int)$c['assignee'];$actionable=$status==='assigned'&&$responsible===$actor;$due=$c['due'];$path='weekly-audits.php?id='.$id.'#audit-action';break;
            case 'purchase':$c=(new PurchaseService($this->access,$this->now))->detail($actor,$id);$title='Purchase Request #'.$id;$status=$c['status'];$resolved=in_array($status,['rejected','cancelled'],true);$responsible=(int)$c['root']['actor_id'];$actionable=in_array($status,['approved','committed'],true)&&$responsible===$actor;$path='purchases.php?id='.$id.'#purchase-action';break;
            case 'stock_issue':$c=(new StockIssueService($this->access,$this->now))->detail($actor,$id);$title='Stock Issue #'.$id;$status=$c['status'];$resolved=in_array($status,['completed','cancelled'],true);$responsible=(int)$c['root']['actor_id'];$actionable=$status==='returned'&&$responsible===$actor;$path='stock-issues.php?id='.$id.'#stockissue-action';break;
            case 'stock_count':$c=(new StockCountService($this->access,$this->now))->detail($actor,$id);$title='Stock Count #'.$id;$status=$c['status'];$resolved=in_array($status,['approved','cancelled','superseded'],true);$responsible=(int)$c['root']['actor_id'];$actionable=!$resolved&&$status!=='submitted'&&$responsible===$actor;$path='stock-counts.php?id='.$id.'#stockcount-action';break;
            case 'handover':$c=(new HandoverService($this->access,$this->now))->detail($actor,$id);$title='Handover #'.$id;$status=$c['status'];$resolved=in_array($status,['acknowledged','resolved_returned'],true);$responsible=(int)($c['event']['data']['recipient_id']??0);$actionable=$status==='pending'&&$responsible===$actor;$path='handover.php?id='.$id.'#handover-action';break;
            case 'receipt_correction':case 'expense_correction':case 'purchase_correction':case 'guest_charge':
                $class=match($type){'receipt_correction'=>ReceiptCorrectionService::class,'expense_correction'=>ExpenseCorrectionService::class,'purchase_correction'=>PurchaseCorrectionService::class,default=>GuestChargeCorrectionService::class};$c=(new $class($this->access,$this->now))->detail($actor,$id);$title='Correction Request #'.$id;$status=$c['status'];$resolved=in_array($status,['posted','rejected','withdrawn'],true);$responsible=(int)$c['root']['actor_id'];$actionable=$status==='returned'&&$responsible===$actor;$path=match($type){'expense_correction'=>'financial-corrections.php?area=expenses&id='.$id.'#correction-action','purchase_correction'=>'financial-corrections.php?area=purchases&id='.$id.'#purchase-correction-action','guest_charge'=>'financial-corrections.php?area=charges&id='.$id.'#charge-action',default=>'financial-corrections.php?id='.$id.'#correction-action'};break;
            case 'discrepancy':$c=(new CashDiscrepancyService($this->access,$this->now))->detail($actor,$id);$title='Cash & MoMo Difference #'.$id;$status=$c['status'];$resolved=$status==='resolved';$responsible=0;foreach($c['history'] as $e){if(isset($e['data']['assignee_id']))$responsible=(int)$e['data']['assignee_id'];if(isset($e['data']['due']))$due=$e['data']['due'];}$actionable=!$resolved&&$responsible===$actor;$path='finance.php?page=discrepancies&id='.$id.'#discrepancy-action';break;
            case 'housekeeping':$c=(new HousekeepingService($this->access,$this->now))->detail($actor,$id);$title='Housekeeping Follow-Up #'.$id;$status=$c['status'];$resolved=in_array($status,['released','superseded'],true);$responsible=(int)$c['root']['actor_id'];$actionable=$c['valid']&&in_array($status,['cleaning','returned'],true)&&$responsible===$actor;$path='housekeeping.php?id='.$id.'#housekeeping-action';break;
            case 'recovery':$stay=new StayService($this->access,$this->now);$c=$stay->departureCase($actor,$id);$b=$this->access->repo->run('SELECT * FROM bookings WHERE id=?',[$id])->fetch();if(!$c||!$b)throw new RuntimeException('Original recovery source unavailable.');$bill=$stay->bill($b);$title='Guest Recovery Follow-Up · Stay #'.$id;$status=$c['status'];$responsible=(int)($c['assignment']['owner_id']??0);$due=$c['assignment']['due']??'';$resolved=$status==='retracted'||(!$bill['provisional']&&$bill['balance']===0&&$c['secured']);$actionable=!$resolved&&$responsible===$actor;$path='occupancy.php?booking_id='.$id.'#stay-workspace';break;
            case 'cash':return $this->cash($actor,(string)$id);
            default:throw new PermissionDenied('This source requires its owner workflow.');
        }
        return ['key'=>$key,'title'=>$title,'state'=>ucwords(str_replace('_',' ',$status)),'resolved'=>$resolved,'decision'=>false,'responsible_id'=>$responsible,'due'=>$due,'critical'=>false,'unavailable'=>false,'path'=>$path,'category'=>'Assigned Work','history'=>[],'actionable'=>$actionable];
    }
    public function shouldReceive(array $row,int $actor):bool{
        if($row['resolved'])return false;if(str_starts_with($row['key'],'cash:')&&$actor===$this->owner()){$holders=$this->cashRecipients($actor,$row['key']);if($holders)return in_array($actor,$holders,true)||$row['due']!==''&&$row['due']<substr($this->now,0,10);}$assigned=(int)($row['responsible_id']??0);
        if($assigned===$actor)return true;
        if($actor!==$this->owner())return false;
        if(($row['decision']??false)||($row['critical']??false)||$assigned===0||($row['due']??'')!==''&&$row['due']<substr($this->now,0,10))return true;
        if(!$this->active($assigned))return true;
        try{$staff=$this->inspect($assigned,$row['key'],$this->access->repo->db->inTransaction());return !$staff['resolved']&&!($staff['actionable']??false);}catch(\Throwable){return true;}
    }
    private function cash(int $actor,string $id):array{
        if($actor!==$this->owner())$this->access->requirePrivilege($actor,'bookings.manage');foreach($this->cashData()['rows'] as $r)if(str_replace(':','-',(string)$r['id'])===$id){$held=(int)($r['available'][$actor]??0);return ['key'=>'cash:'.$id,'title'=>'Cash Deposit Follow-Up','state'=>$held>0?'Cash Awaiting Full Deposit':'Cash No Longer Held By You','resolved'=>$r['outstanding']===0,'decision'=>false,'responsible_id'=>$actor,'due'=>$r['due'],'critical'=>false,'unavailable'=>false,'path'=>'finance.php?page=deposits#finance-action','category'=>'Money','history'=>[],'actionable'=>$held>0];}throw new RuntimeException('Original cash source unavailable.');
    }
}
