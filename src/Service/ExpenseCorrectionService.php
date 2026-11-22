<?php
namespace Tivoli\Service;
use Tivoli\Repository\ExpenseAccounting as A;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

final class ExpenseCorrectionService
{
    use ExpenseCorrectionPosting;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function today():string{return substr($this->now(),0,10);}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid expense or correction.');return (int)$v;}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor)&&(new ExpenseService($this->access))->canManage($actor);}
    public function permit(int $actor,bool $write=false):void{
        $r=$this->access->repo;$u=$r->user($actor);
        if(!$u||!$u['active']||!array_intersect(['expenses.submit','expenses.manage','expenses.approve'],$r->privileges($actor)))throw new PermissionDenied('You cannot access expense corrections.');
        if(!A::installed($r))throw new RuntimeException('Complete the existing finance setup before using corrections.');
        if($write&&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,$this->today()])->fetchColumn())throw new PermissionDenied('This account cannot make operational corrections.');
    }
    private function event(int $actor,string $kind,array $data,int $root=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'expense_correction',(string)$root,0,$this->today(),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$this->access->repo->db->lastInsertId();
    }
    public function source(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$s=A::position($r,$id);$p=$s['original'];$position=$s['position'];
        if(!$this->owner($actor)&&(int)$p['user_id']!==$actor)throw new PermissionDenied('Only your own recorded expense sources are available.');
        $blockers=[];$money=[];$dependencies=[];$payer=null;
        if(PurchaseService::forExpense($r,$id))$blockers[]='This expense belongs to a purchase. Reconcile its stock, supplier cost and settlement together.';
        if(preg_match('/stock|inventory|capital|asset|payroll/i',$p['category_name']))$blockers[]='This source is not a supported ordinary operating expense.';
        if(!in_array($position['method'],['cash','momo'],true))$blockers[]='Historical methods remain retained; this method needs separate reconciliation.';
        if(A::collection($r,$id)!==$id)$blockers[]='This duplicate was compensated. Continue on its retained expense; incorrect duplicate identification needs separate reconciliation.';
        $cursor=(int)$r->run('SELECT expense_cursor FROM finance_schema WHERE id=1')->fetchColumn();
        if($id<=$cursor)$money[]='Historical payer and settlement provenance are unknown; do not invent them.';
        if(count($s['settlements'])!==1)$money[]='A single verified actual settlement is required. Incurring an expense does not prove payment.';
        else{
            $event=$s['settlements'][0];$d=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$payerValue=$d['actual_payer_id']??null;$payer=(is_int($payerValue)||is_string($payerValue))&&preg_match('/^[1-9][0-9]*$/D',(string)$payerValue)?(int)$payerValue:null;
            if(!$payer||!$r->user($payer)||!trim($d['destination']??'')||!trim($d['evidence']??''))$money[]='Actual payer, destination and payout evidence must be established separately.';
            if(($d['method']??'momo')!==$p['method']||$position['paid']!==$position['amount'])$money[]='Original cost and actual payout differ; reconcile supplier claims/payables first.';
            if(($d['method']??'momo')==='cash'&&($d['cash_funding']??'')!=='separate_from_full_sales_deposit')$money[]='Cash funding has not been established separately from full sales deposits.';
            $dependencies=$r->run("SELECT id,kind,payload FROM finance_events WHERE kind IN ('receipt_matched','expense_recovered','supplier_refund','cost_reconciled') AND ((target_type='expense' AND target_id=?) OR (target_type IN ('payout','cost') AND target_id=?)) ORDER BY id",[(string)$id,(string)$event['id']])->fetchAll();
            if($dependencies)$money[]='Independent matching or recovery exists. Reconcile these links before amount, method or duplicate correction.';
        }
        $originalDate=$p['expense_date'];$late=$r->run("SELECT payload FROM finance_events WHERE kind='late_posting' AND target_type='expense' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();if($late){$d=json_decode($late,true,512,JSON_THROW_ON_ERROR);$originalDate=$d['original_activity_date']??$originalDate;}
        $token=$this->hash([$p,$position,$s['settlements'],$dependencies]);
        $where=$this->owner($actor)?'':' AND e.user_id=?';$values=[$p['vendor'],$id];if(!$this->owner($actor))$values[]=$actor;
        $keepers=$r->run('SELECT e.id,e.amount,e.method FROM expenses e WHERE e.vendor=? AND e.id<>?'.$where.' ORDER BY e.id DESC LIMIT 50',$values)->fetchAll();
        return $s+['blockers'=>$blockers,'money_blockers'=>$money,'dependencies'=>$dependencies,'payer_id'=>$payer,'payer_name'=>$payer?($r->user($payer)['name']??null):null,'token'=>$token,'original_date'=>$originalDate,'original_locked'=>(new FinanceService($this->access))->periodLocked($p['expense_date']),'keepers'=>$keepers];
    }

    private function load(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind='ec_requested' AND e.target_type='expense_correction'",[$id])->fetch();
        if(!$root)throw new RuntimeException('Correction request not found.');
        if(!$this->owner($actor)&&(int)$root['actor_id']!==$actor)throw new PermissionDenied('Only the requester and designated owner may view this correction.');
        $root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='expense_correction' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        $proposal=$root['data']['proposal'];$revisions=[$root];$status='pending';$posted=null;
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);switch($e['kind']){case 'ec_revised':$proposal=$e['data']['proposal'];$revisions[]=$e;$status='pending';break;case 'ec_returned':$status='returned';break;case 'ec_rejected':$status='rejected';break;case 'ec_withdrawn':$status='withdrawn';break;case 'ec_approved':$status='posted';$posted=$e['data'];break;}}unset($e);
        return compact('root','history','proposal','revisions','status','posted')+['version'=>$this->hash([$id,array_column($history,'id')])];
    }
    public function detail(int $actor,int $id):array{
        $c=$this->load($actor,$id);$c['live']=$this->source($actor,(int)$c['proposal']['expense_id']);$c['stale']=!hash_equals($c['proposal']['source_token'],$c['live']['token']);return $c;
    }
    private function active(int $payment,?int $except=null):bool{
        $r=$this->access->repo;$ids=$r->run("SELECT id FROM finance_events WHERE kind='ec_requested' AND target_type='expense_correction' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.expense_id'))=?",[(string)$payment])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){if((int)$id===$except)continue;$last=$r->run("SELECT kind FROM finance_events WHERE target_type='expense_correction' AND target_id=? AND kind IN ('ec_approved','ec_rejected','ec_withdrawn') ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();if(!$last)return true;}return false;
    }
    public function register(int $actor,string $page='requests',int $before=0):array{
        $this->permit($actor);if($page==='review'&&!$this->owner($actor))throw new PermissionDenied('Only the designated owner opens the correction review queue.');
        $where='';$params=[];if(!$this->owner($actor)){$where.=' AND actor_id=?';$params[]=$actor;}if($before){$where.=' AND id<?';$params[]=$before;}
        $terminal="EXISTS(SELECT 1 FROM finance_events decision WHERE decision.target_type='expense_correction' AND decision.target_id=CAST(root.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND decision.kind IN ('ec_approved','ec_rejected','ec_withdrawn'))";if($page==='review')$where.=' AND NOT '.$terminal;elseif($page==='log')$where.=' AND '.$terminal;
        $ids=$this->access->repo->run("SELECT root.id FROM finance_events root WHERE root.kind='ec_requested' AND root.target_type='expense_correction'".$where.' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if($page==='review'&&$c['status']!=='pending'||$page==='log'&&!in_array($c['status'],['posted','rejected','withdrawn'],true))continue;$rows[]=$c;}
        return ['rows'=>$rows,'next'=>$more?(int)end($ids):0];
    }
    public function expenses(int $actor,int $before=0):array{
        $this->permit($actor);$where='';$params=[];if(!$this->owner($actor)){$where.=' AND e.user_id=?';$params[]=$actor;}if($before){$where.=' AND e.id<?';$params[]=$before;}
        $rows=$this->access->repo->run('SELECT e.*,c.name category_name,u.name actor_name FROM expenses e JOIN expense_categories c ON c.id=e.category_id LEFT JOIN users u ON u.id=e.user_id WHERE 1=1'.$where.' ORDER BY e.id DESC LIMIT 51',$params)->fetchAll();$more=count($rows)>50;if($more)array_pop($rows);foreach($rows as &$e)$e['position']=A::position($this->access->repo,(int)$e['id'])['position'];unset($e);return ['rows'=>$rows,'next'=>$more?(int)end($rows)['id']:0];
    }
    public function attentionCount(int $actor):int{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Owner review is restricted.');return (int)$this->access->repo->run("SELECT COUNT(*) FROM finance_events r WHERE r.kind='ec_requested' AND r.target_type='expense_correction' AND NOT EXISTS(SELECT 1 FROM finance_events e WHERE e.target_type='expense_correction' AND e.target_id=CAST(r.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND e.kind IN ('ec_approved','ec_rejected','ec_withdrawn'))")->fetchColumn();
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor,true);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the correction form.');
            $file=null;if(isset($in['_evidence'])){if(!in_array($action,['request','revise'],true))throw new RuntimeException('Attach evidence to the request or its revision.');$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid correction form.');
            unset($in['csrf'],$in['action'],$in['operation_key']);ksort($in);$fp=$this->hash([$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$file,$fp){
                $this->permit($actor,true);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($fp,$old['fingerprint']))throw new RuntimeException('This form was used for different details.');$e=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='expense_correction'",[$old['event_id']])->fetch();if(!$e)throw new RuntimeException('This form belongs to another workflow.');$id=$e['kind']==='ec_requested'?(int)$e['id']:(int)$e['target_id'];$this->load($actor,$id);$this->access->audit($actor,'expense_correction.replayed','expense_correction',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$event]=$this->command($actor,$action,$in,$file);if($file)FinanceAttachment::save($this->access,$actor,'expensecorrection',$event,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$event]);$this->access->audit($actor,'expense_correction.'.$action,'expense_correction',(string)$id,'succeeded',['event_id'=>$event]);return ['id'=>$id,'event_id'=>$event,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'expense_correction.failed','expense_correction',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in,?array $file):array{
        if($action==='request'){
            $payment=$this->id($in['expense_id']??'');if($this->active($payment))throw new RuntimeException('This receipt already has an active correction request.');
            $p=$this->proposal($actor,$payment,$in,$file);$id=$this->event($actor,'ec_requested',['expense_id'=>$payment,'proposal'=>$p]);return [$id,$id];
        }
        $id=$this->id($in['id']??'');$c=$this->load($actor,$id);if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This correction changed. Reload its current history.');
        if($action==='revise'){
            if((int)$c['root']['actor_id']!==$actor||$c['status']!=='returned')throw new PermissionDenied('Only the requester may revise a returned case.');
            $p=$this->proposal($actor,(int)$c['proposal']['expense_id'],$in,$file);return [$id,$this->event($actor,'ec_revised',['proposal'=>$p],$id)];
        }
        if($action==='withdraw'){
            if((int)$c['root']['actor_id']!==$actor||!in_array($c['status'],['pending','returned'],true))throw new PermissionDenied('Only the requester may withdraw an unposted case.');
            return [$id,$this->event($actor,'ec_withdrawn',[],$id)];
        }
        if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may decide or post corrections.');
        if($c['status']!=='pending')throw new RuntimeException('Only a pending correction may receive an owner decision.');
        if(in_array($action,['return','reject'],true))return [$id,$this->event($actor,$action==='return'?'ec_returned':'ec_rejected',['reason'=>R::text($in['reason']??'',2000,true)],$id)];
        if($action==='post')return $this->postCorrection($actor,$id,$c,$in);
        throw new RuntimeException('Unknown correction action.');
    }
}
