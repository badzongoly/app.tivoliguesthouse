<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Infrastructure\HrVault;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

final class PayrollService
{
    use PayrollPosting;
    use PayrollCorrections;
    private readonly HrVault $vault;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){require_once dirname(__DIR__,2).'/bootstrap/hr.php';}
    private function vault():HrVault{if(!isset($this->vault))$this->vault=new HrVault(\hr_config()['hr_key']);return $this->vault;}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function today():string{return substr($this->now(),0,10);}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor)&&in_array('hr.salary',$this->access->repo->privileges($actor),true);}
    public function permit(int $actor):void{
        $r=$this->access->repo;$u=$r->user($actor);if(!$u||!$u['active']||(!$this->owner($actor)&&!in_array('hr.self',$r->privileges($actor),true)))throw new PermissionDenied('You cannot access payroll statements.');
        if(!(new FinanceService($this->access))->installed())throw new RuntimeException('The existing finance setup must be active.');
    }
    private function onlyOwner(int $actor,bool $write=false):void{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner with salary authority may manage payroll.');
        if($write&&$this->access->repo->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,$this->today()])->fetchColumn())throw new PermissionDenied('This owner account is no longer eligible for payroll operations.');
    }
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid payroll or staff record.');return (int)$v;}
    private function period(mixed $v,bool $future=false):string{$s=R::text($v,7,true);if(!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$s)||(!$future&&$s>substr($this->today(),0,7)))throw new RuntimeException('Choose a payroll month no later than this month.');return $s;}
    private function salary(int $staff,string $period):array{
        $r=$this->access->repo;$row=$r->run('SELECT id,staff_no,user_id,status,exit_at,data FROM hr_staff WHERE id=?',[$staff])->fetch();
        if(!$row||!($row['status']==='active'||$row['status']==='exit_scheduled'&&$row['exit_at']>$this->today()))throw new RuntimeException('Choose staff currently eligible for monthly payroll. Approved liabilities remain retained separately.');
        $end=(new \DateTimeImmutable($period.'-01'))->modify('last day of this month')->format('Y-m-d');$cutoff=min($end,$this->today());
        $revision=$r->run('SELECT * FROM hr_salary_versions WHERE staff_id=? AND effective_date<=? ORDER BY effective_date DESC,id DESC LIMIT 1',[$staff,$cutoff])->fetch();if(!$revision)throw new RuntimeException('Set up the effective monthly salary and verified MoMo details in Staff & HR.');
        $data=json_decode($this->vault()->open($revision['payload'],'salary:'.$staff),true,512,JSON_THROW_ON_ERROR);$bio=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);
        $provider=$r->run("SELECT id,name,active,version FROM hr_catalogue WHERE id=? AND group_name='momo'",[$revision['momo_id']])->fetch();
        $digits=preg_replace('/[^0-9]/','',$data['momo_number']??'');
        if($revision['preferred']!=='momo'||($data['frequency']??'')!=='monthly'||!$provider||!$provider['active']||strlen($digits)<6||empty($data['momo_name']))throw new RuntimeException('Confirm monthly salary, active MoMo provider, registered payee and valid number in Staff & HR.');
        $base=R::minor((string)$data['amount']);if($base<1)throw new RuntimeException('Set a positive monthly salary first.');
        return ['staff_id'=>$staff,'staff_name'=>$bio['name']??$row['staff_no'],'staff_no'=>$row['staff_no'],'user_id'=>$row['user_id']?(int)$row['user_id']:null,'base'=>$base,'revision'=>(int)$revision['id'],'effective_date'=>$revision['effective_date'],'wallet'=>$provider['name'].' · Ending '.substr($digits,-4),'payee'=>$data['momo_name'],'token'=>$this->hash([$revision['id'],$revision['payload'],$row['staff_no'],$row['user_id'],$row['status'],$row['exit_at'],$bio['name']??'',$provider])];
    }
    private function event(int $actor,string $kind,array $data,int $root=0):int{
        $nonce=bin2hex(random_bytes(16));$data['_target']=$root;$safe=['payroll_id'=>$root,'staff_id'=>(int)$data['staff_id'],'period'=>$data['period'],'key'=>$nonce,'sealed'=>$this->vault()->seal(json_encode($data,JSON_THROW_ON_ERROR),'payroll:'.$kind.':'.$nonce)];
        if(isset($data['recipient_id']))$safe['recipient_id']=$data['recipient_id'];
        return $this->canonical($actor,$kind,'payroll',(string)$root,0,$this->today(),$safe);
    }
    private function decode(array $event):array{
        $safe=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
        if(!preg_match('/^[a-f0-9]{32}$/D',$safe['key']??''))throw new RuntimeException('Private payroll evidence unavailable.');
        $data=json_decode($this->vault()->open($safe['sealed'],'payroll:'.$event['kind'].':'.$safe['key']),true,512,JSON_THROW_ON_ERROR);
        if(($data['_target']??null)!==(int)$event['target_id']||($data['staff_id']??null)!==(int)$safe['staff_id']||($data['period']??null)!==$safe['period']||isset($safe['recipient_id'])&&($data['recipient_id']??null)!==$safe['recipient_id'])throw new RuntimeException('Private payroll evidence does not match its source.');
        return $data;
    }
    private function load(int $actor,int $id):array{
        $this->onlyOwner($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.target_type='payroll' AND e.kind='payroll_draft'",[$id])->fetch();
        if(!$root)throw new RuntimeException('Payroll record not found.');$root['data']=$this->decode($root);
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='payroll' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        $status='draft';$prepared=null;$approved=null;$requestId=null;$payments=[];$paid=0;$versions=[];$published=null;
        foreach($history as &$e){$e['data']=$this->decode($e);$d=$e['data'];switch($e['kind']){
            case 'payroll_prepared':$prepared=$d['snapshot'];$versions[]=$e;$status='review';break;
            case 'payroll_returned':$status='revision';break;
            case 'payroll_approved':$approved=$d['snapshot'];$requestId=(int)$d['request_id'];$status='approved';break;
            case 'payroll_paid':$paid+=(int)$d['amount'];$payments[]=$e;break;
            case 'payroll_published':$published=$e;break;
        }}unset($e);
        return $this->projectPayroll(['root'=>$root,'history'=>$history,'status'=>$status,'prepared'=>$prepared,'versions'=>$versions,'approved'=>$approved,'request_id'=>$requestId,'payments'=>$payments,'paid'=>$paid,'due'=>$approved?$approved['net']-$paid:null,'published'=>$published,'version'=>$this->hash([$id,array_column($history,'id')])]);
    }
    public function detail(int $actor,int $id):array{
        $c=$this->load($actor,$id);$c['recipient']=$this->recipient((int)$c['root']['data']['staff_id']);$c['current_salary']=null;$c['eligibility_error']='';
        if(!$c['approved'])try{$c['current_salary']=$this->salary((int)$c['root']['data']['staff_id'],$c['root']['data']['period']);}catch(RuntimeException $e){if($e instanceof \PDOException)throw $e;$c['eligibility_error']=$e->getMessage();}
        return $c;
    }
    private function recipient(int $staff):array{
        $r=$this->access->repo;$id=(int)$r->run('SELECT user_id FROM hr_staff WHERE id=?',[$staff])->fetchColumn();$u=$id?$r->user($id):null;$permissions=$id?$r->privileges($id):[];sort($permissions);
        return ['id'=>$id,'name'=>$u['name']??'No Linked Account','token'=>$this->hash([$staff,$id,$u?$u['name']:null,$u?$u['email']:null,$u?$u['active']:null,$permissions])];
    }
    public function options(int $actor):array{
        $this->onlyOwner($actor);$rows=$this->access->repo->run("SELECT id,staff_no,data,status FROM hr_staff WHERE status='active' OR (status='exit_scheduled' AND exit_at>?) ORDER BY id",[$this->today()])->fetchAll();foreach($rows as &$r){$bio=json_decode($r['data'],true,512,JSON_THROW_ON_ERROR);$r['name']=$bio['name']??$r['staff_no'];unset($r['data']);}return $rows;
    }
    public function register(int $actor,string $period='',int $before=0):array{
        $this->onlyOwner($actor);$where='';$params=[];if($period!==''){$this->period($period,true);$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.period'))=?";$params[]=$period;}if($before>0){$where.=' AND id<?';$params[]=$before;}
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='payroll' AND kind='payroll_draft'".$where.' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);
        return ['rows'=>array_map(fn($id)=>$this->detail($actor,(int)$id),$ids),'next'=>$more?(int)end($ids):0];
    }
    public function activity(int $actor,int $before=0):array{
        $this->onlyOwner($actor);$rows=$this->access->repo->run("SELECT e.id,e.kind,e.target_id,e.payload,e.created_at,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='payroll'".($before>0?' AND e.id<?':'').' ORDER BY e.id DESC LIMIT 51',$before>0?[$before]:[])->fetchAll();$more=count($rows)>50;if($more)array_pop($rows);foreach($rows as &$row){$data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$row['staff_id']=$data['staff_id'];$row['period']=$data['period'];unset($row['payload']);}return ['rows'=>$rows,'next'=>$more?(int)end($rows)['id']:0];
    }
    public function attentionCount(int $actor):int{
        $this->onlyOwner($actor);$state=[];
        // Read metadata once. Decode only corrections to distinguish private employer costs
        // from employee statement changes without loading every source/payment projection.
        foreach($this->access->repo->run("SELECT id,kind,target_id,payload FROM finance_events WHERE target_type='payroll' AND kind IN ('payroll_draft','payroll_approved','payroll_paid','payroll_corrected','payroll_published') ORDER BY id")->fetchAll() as $event){
            if($event['kind']==='payroll_draft'){$state[(int)$event['id']]=['published'=>0,'financial'=>0];continue;}$root=(int)$event['target_id'];if(!isset($state[$root]))continue;
            if($event['kind']==='payroll_published')$state[$root]['published']=(int)$event['id'];
            elseif($event['kind']==='payroll_corrected'){try{$effect=$this->affectsStatement($this->decode($event));}catch(\PDOException $e){throw $e;}catch(\Throwable $e){$effect=true;}if($effect)$state[$root]['financial']=(int)$event['id'];}
            else $state[$root]['financial']=(int)$event['id'];
        }
        return count(array_filter($state,fn($row)=>!$row['published']||$row['financial']>$row['published']));
    }
    public function paymentReference(int $actor,int $payroll,int $canonical):?array{
        $c=$this->load($actor,$payroll);foreach($c['payments'] as $e)if((int)$e['data']['canonical_id']===$canonical)return ['reference'=>$e['data']['reference'],'paid_at'=>$e['data']['paid_at'],'staff_name'=>$c['approved']['staff_name']];return null;
    }
    public function attention(int $actor):array{
        $this->onlyOwner($actor);$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='payroll' AND kind='payroll_draft' ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if(!$c['published_current']||$c['recovery_due']>0||$c['source_errors'])$rows[]=$c;}return ['rows'=>$rows,'count'=>count($rows)];
    }
    private function statementData(array $c):array{
        $p=$c['approved'];if(!$p)throw new RuntimeException('Approve payroll before previewing a statement.');
        return array_intersect_key($p,array_flip(['staff_id','staff_name','staff_no','base','allowance','overtime','deductions','gross','net','wallet','revision','adjustment','approved_by','approved_at','period']))
            +['payroll_id'=>(int)$c['root']['id'],'payments'=>array_map(fn($e)=>array_intersect_key($e['data'],array_flip(['amount','original_amount','reference','paid_at','corrections']))+['recorded_by'=>$e['actor_name']],$c['payments']),'paid'=>$c['paid'],'due'=>$c['due'],'recovery_due'=>$c['recovery_due'],'corrections'=>array_map(fn($e)=>['id'=>(int)$e['id'],'recorded_at'=>$e['created_at'],'recorded_by'=>$e['actor_name'],'type'=>$e['data']['type'],'employee_note'=>$e['data']['employee_note'],'net_before'=>$e['data']['before']['net'],'net_after'=>$e['data']['after']['net'],'paid_delta'=>$e['data']['paid_delta']],array_values(array_filter($c['corrections'],fn($event)=>$this->affectsStatement($event['data']))))];
    }
    public function statement(int $actor,int $id,int $publication=0):array{
        $this->permit($actor);$owner=$this->owner($actor);if($owner&&!$publication)return $this->statementData($this->load($actor,$id));
        $where=$publication?' AND id=?':'';$params=[(string)$id];if($publication)$params[]=$publication;if(!$owner){$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.recipient_id'))=?";$params[]=(string)$actor;}
        $event=$this->access->repo->run("SELECT * FROM finance_events WHERE target_type='payroll' AND target_id=? AND kind='payroll_published'".$where.' ORDER BY id DESC LIMIT 1',$params)->fetch();if(!$event)throw new PermissionDenied('Only your own published payslip is available.');
        $safe=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);if(!$owner&&!$this->access->repo->run('SELECT id FROM hr_staff WHERE id=? AND user_id=?',[$safe['staff_id'],$actor])->fetchColumn())throw new PermissionDenied('This published statement does not match your current staff identity.');
        $data=$this->decode($event);$newer=(bool)$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='payroll' AND target_id=? AND kind='payroll_published' AND id>? LIMIT 1",[(string)$id,(int)$event['id']])->fetchColumn();$later=false;
        if(!$newer)foreach($this->access->repo->run("SELECT id,kind,target_id,payload FROM finance_events WHERE target_type='payroll' AND target_id=? AND kind IN ('payroll_paid','payroll_corrected') AND id>? ORDER BY id",[(string)$id,(int)$event['id']])->fetchAll() as $change){
            if($change['kind']==='payroll_paid'){$later=true;break;}try{$later=$this->affectsStatement($this->decode($change));}catch(\PDOException $e){throw $e;}catch(\Throwable $e){$later=true;}if($later)break;
        }
        return $data['statement']+['statement_id'=>(int)$event['id'],'pending_revision'=>$later,'historical_version'=>$newer];
    }
    public function ownStatements(int $actor):array{
        $this->permit($actor);$ids=$this->access->repo->run("SELECT target_id FROM finance_events WHERE target_type='payroll' AND kind='payroll_published' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.recipient_id'))=? GROUP BY target_id ORDER BY MAX(id) DESC",[(string)$actor])->fetchAll(\PDO::FETCH_COLUMN);$rows=[];
        foreach($ids as $id)try{$rows[]=$this->statement($actor,(int)$id);}catch(PermissionDenied $e){continue;}return $rows;
    }
    public static function forRequest(\Tivoli\Repository\AccessRepository $r,int $id):int{return (int)$r->run("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payroll_id')) FROM finance_events WHERE id=? AND kind='payroll_requested'",[$id])->fetchColumn();}
    public static function pendingCosts(\Tivoli\Repository\AccessRepository $r,string $start,string $end):int{
        return (int)$r->run("SELECT COUNT(DISTINCT p.target_id) FROM finance_events p WHERE p.kind='payroll_prepared' AND p.target_type='payroll' AND p.effective_date BETWEEN ? AND ? AND NOT EXISTS(SELECT 1 FROM finance_events a WHERE a.kind='payroll_approved' AND a.target_type='payroll' AND a.target_id=p.target_id)",[$start,$end])->fetchColumn();
    }
    public function reports(int $actor,string $start,string $end):array{
        $this->onlyOwner($actor);foreach([$start,$end] as $date){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Choose valid report dates.');}if($start>$end)throw new RuntimeException('Start date must not follow end date.');
        $r=$this->access->repo;$staffCost=(new FinanceService($this->access,$this->now()))->summary($start,$end)['payroll'];
        $adjustments=\Tivoli\Repository\PayrollAccounting::period($r,$start,$end);$payments=(int)$r->run("SELECT COALESCE(SUM(p.amount_minor),0) FROM finance_events p JOIN finance_events q ON q.kind='payroll_requested' AND p.target_type='request' AND p.target_id=CAST(q.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci WHERE p.kind='cost_paid' AND p.effective_date BETWEEN ? AND ?",[$start,$end])->fetchColumn()+$adjustments['paid'];
        $ids=$r->run("SELECT target_id FROM finance_events WHERE target_type='payroll' AND kind='payroll_approved' AND effective_date BETWEEN ? AND ? ORDER BY id",[$start,$end])->fetchAll(\PDO::FETCH_COLUMN);$rows=array_map(fn($id)=>$this->detail($actor,(int)$id),$ids);
        return compact('start','end','staffCost','payments','rows','adjustments')+['adjustment_rows'=>$r->run("SELECT id,payload,created_at,amount_minor FROM finance_events WHERE kind='payroll_adjustment' AND effective_date BETWEEN ? AND ? ORDER BY id DESC",[$start,$end])->fetchAll(),'legacy_cost'=>$staffCost-$adjustments['cost']-array_sum(array_map(fn($c)=>$c['original_approved']['cost'],$rows))];
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->onlyOwner($actor,true);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the payroll form.');
            $file=null;if(isset($in['_evidence'])){if(!in_array($action,['pay','correct','recovery_followup'],true))throw new RuntimeException('Attach evidence to settlement, correction or recovery follow-up.');$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid payroll form.');unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);$fp=$this->hash(['payroll.'.$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$file,$fp){
                $this->onlyOwner($actor,true);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fp))throw new RuntimeException('This form was used for different payroll details.');$e=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='payroll'",[$old['event_id']])->fetch();if(!$e)throw new RuntimeException('This operation belongs to another workflow.');$id=$e['kind']==='payroll_draft'?(int)$e['id']:(int)$e['target_id'];$this->access->audit($actor,'payroll.replayed','payroll',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$ev]=$this->command($actor,$action,$in);if($file)FinanceAttachment::save($this->access,$actor,'payroll',$ev,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$ev]);$this->access->audit($actor,'payroll.'.$action,'payroll',(string)$id,'succeeded',['event_id'=>$ev]);return ['id'=>$id,'event_id'=>$ev,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'payroll.failed','payroll',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in):array{
        if($action==='start'){
            $staff=$this->id($in['staff_id']??'');$period=$this->period($in['period']??'');if(($in['not_recorded']??'')!=='1')throw new RuntimeException('Check prior payroll records before creating this staff/month.');
            $salary=$this->salary($staff,$period);$r=$this->access->repo;$hash=hash('sha256',mb_strtolower($staff.'|'.$period));
            $claim=$r->run("SELECT event_id FROM finance_claims WHERE scope='staff_payroll' AND identity_hash=?",[$hash])->fetchColumn();if($claim)throw new RuntimeException('Payroll already exists for this staff member and month. Review its retained source instead of posting a duplicate.');
            $id=$this->event($actor,'payroll_draft',['staff_id'=>$staff,'period'=>$period,'salary'=>$salary]);$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('staff_payroll',?,?)",[$hash,$id]);return [$id,$id];
        }
        $id=$this->id($in['id']??'');$c=$this->load($actor,$id);if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This payroll changed. Reload its current history.');$d=$c['root']['data'];$scope=['staff_id'=>$d['staff_id'],'period'=>$d['period']];
        if($action==='correct')return $this->correctPayroll($actor,$id,$c,$in);
        if($action==='recovery_followup'){if(!$c['approved'])throw new RuntimeException('Choose approved payroll for recovery/source follow-up.');return [$id,$this->event($actor,'payroll_recovery_note',$scope+['comment'=>R::text($in['comment']??'',2000,true),'recovery_due'=>$c['recovery_due'],'actual_money_moved'=>0],$id)];}
        if($action==='prepare'){
            if(!in_array($c['status'],['draft','revision'],true)||$c['approved'])throw new RuntimeException('Only an unapproved draft or returned payroll may be prepared.');
            $salary=$this->salary((int)$d['staff_id'],$d['period']);if(!hash_equals($salary['token'],$in['salary_token']??''))throw new RuntimeException('Salary or payee details changed. Reload and review the HR snapshot.');
            if(($in['checked']??'')!=='1')throw new RuntimeException('Review salary, attendance context and manual adjustments.');
            $a=[];foreach(['allowance','overtime','deductions','employer'] as $key)$a[$key]=R::minor(R::text($in[$key]??'0.00',24,true));
            $gross=$salary['base']+$a['allowance']+$a['overtime'];$net=$gross-$a['deductions'];$cost=$gross+$a['employer'];if($net<0||$cost>999999999999)throw new RuntimeException('Check deductions and the supported payroll amount limit.');
            $adjustment=R::text($in['adjustment']??'',2000,(bool)array_sum($a));$snapshot=$scope+$salary+$a+compact('gross','net','cost','adjustment')+['comment'=>R::text($in['comment']??'',2000)];
            return [$id,$this->event($actor,'payroll_prepared',$scope+['snapshot'=>$snapshot],$id)];
        }
        if($action==='return'){
            if($c['status']!=='review'||$c['approved'])throw new RuntimeException('Only unapproved payroll can return for revision.');
            return [$id,$this->event($actor,'payroll_returned',$scope+['reason'=>R::text($in['reason']??'',2000,true)],$id)];
        }
        if($action==='approve')return $this->approvePayroll($actor,$id,$c,$in);
        if($action==='pay')return $this->payPayroll($actor,$id,$c,$in);
        if($action==='publish'){
            $this->guardPayroll($c);if(!$c['approved']||$c['due']!==0||$c['recovery_due']!==0||$c['published_current']||($in['confirmed']??'')!=='1')throw new RuntimeException('Complete employee settlement before publishing once.');
            $r=$this->access->repo;$recipient=$this->recipient((int)$d['staff_id']);if(!hash_equals($recipient['token'],$in['recipient_token']??''))throw new RuntimeException('The linked employee account changed. Reload and verify the recipient again.');$user=$recipient['id'];$account=$user?$r->user($user):null;
            if(!$account||!$account['active']||!in_array('hr.self',$r->privileges($user),true))throw new RuntimeException('Verify and link an active staff account with own-HR access before publishing.');
            if($c['publications']){$first=$c['publications'][0]['data']['recipient_id'];if((int)$first!==$user)throw new RuntimeException('The frozen employee account differs. Resolve identity before reissuing this statement.');}
            $data=$scope+['replaces_statement_id'=>$c['published']?(int)$c['published']['id']:null,'recipient_id'=>$user,'statement'=>$this->statementData($c)+['published_at'=>$this->now()]];
            return [$id,$this->event($actor,'payroll_published',$data,$id)];
        }
        throw new RuntimeException('Unknown payroll action.');
    }
}
