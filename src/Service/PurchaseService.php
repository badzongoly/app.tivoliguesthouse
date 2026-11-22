<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** Prior authority, actual cost and settlement remain separate append-only facts. */
final class PurchaseService
{
    use PurchasePosting;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function finance():FinanceService{return new FinanceService($this->access,$this->now());}
    public function owner(int $actor):bool{return $this->finance()->canPostLate($actor);}
    public function permit(int $actor):void{
        $r=$this->access->repo;$u=$r->user($actor);
        if(!$u||!$u['active']||!array_intersect(['bookings.manage','expenses.submit','expenses.manage','stock.manage'],$r->privileges($actor)))throw new PermissionDenied('You cannot access purchase requests.');
        if(!$this->finance()->installed())throw new RuntimeException('The existing finance setup must be activated first.');
        if($r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn()
            &&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,substr($this->now(),0,10)])->fetchColumn())throw new PermissionDenied('This staff account is no longer eligible for purchasing operations.');
    }
    private function onlyOwner(int $actor):void{if(!$this->owner($actor))throw new PermissionDenied('Only the designated finance owner may perform this purchase review.');}
    private function event(string $kind,int $actor,array $data,int $purchase=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'purchase',(string)$purchase,0,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);
        return (int)$this->access->repo->db->lastInsertId();
    }
    private function identifier(mixed $value):int{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,17}$/D',$value))throw new RuntimeException('Choose a valid record.');return (int)$value;}
    private function amount(mixed $value):int{$n=R::minor(R::text($value,24,true));if($n<1||$n>999999999999)throw new RuntimeException('Enter a positive amount within the ledger limit.');return $n;}
    private function ledgerText(mixed $value,int $length):string{$s=R::text($value,$length,true);if(preg_match('/[\x{10000}-\x{10FFFF}]/u',$s))throw new RuntimeException('Remove emoji or supplementary characters from the ledger title or supplier.');return $s;}
    private function timestamp(mixed $value):string{
        $s=str_replace('T',' ',R::text($value,19,true));if(strlen($s)===16)$s.=':00';$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$s,new \DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d H:i:s')!==$s||$s<'2000-01-01 00:00:00'||$s>$this->now())throw new RuntimeException('Enter an actual UTC date and time, no later than now.');return $s;
    }
    public function options(int $actor):array{
        $this->permit($actor);$r=$this->access->repo;return [
            'categories'=>$r->run('SELECT id,name FROM expense_categories WHERE active=1 ORDER BY name,id')->fetchAll(),
            'extras'=>$r->run("SELECT e.id,e.name FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.active=1 AND COALESCE(s.tracked,1)=1 ORDER BY e.name,e.id")->fetchAll(),
            'payers'=>$r->run('SELECT id,name FROM users ORDER BY name,id')->fetchAll()];
    }
    public function detail(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;
        $root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.target_type='purchase' AND e.kind IN ('purchase_requested','purchase_unapproved')",[$id])->fetch();
        if(!$root)throw new RuntimeException('Purchase request not found.');
        if((int)$root['actor_id']!==$actor&&!$this->owner($actor))throw new PermissionDenied('Only the requester and designated owner may view this purchase.');
        $root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$d=$root['data'];
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='purchase' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        $status=$root['kind']==='purchase_unapproved'?'exception':'pending';$limit=0;$approval=null;$change=null;$commitment=null;$receipt=$d['receipt']??null;$expenseId=null;$stockMovement=null;$paid=0;$payments=[];$clarifications=[];$scope=['supplier'=>$d['supplier'],'purpose'=>$d['purpose']];
        foreach($history as &$event){$event['data']=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$p=$event['data'];
            switch($event['kind']){
                case 'purchase_approved':$status='approved';$limit=(int)$p['limit'];$approval=$event;$scope=$p['scope'];$change=null;break;
                case 'purchase_rejected':$status='rejected';break;
                case 'purchase_change':$change=$p;$status='change';break;
                case 'purchase_change_rejected':$change=null;$status='approved';break;
                case 'purchase_committed':$commitment=$p;$status='committed';break;
                case 'purchase_received':$receipt=$p+['event_id'=>(int)$event['id']];$status=$p['exception']?'exception':'declared';break;
                case 'purchase_disputed':$status='disputed';break;
                case 'purchase_clarified':$clarifications[]=$event;$status=($receipt['exception']??true)?'exception':'declared';break;
                case 'purchase_posted':$expenseId=(int)$p['expense_id'];$stockMovement=$p['stock_movement_id'];$status='posted';break;
                case 'purchase_paid':$paid+=(int)$p['amount'];$payments[]=$event;break;
                case 'purchase_cancelled':$status='cancelled';break;
            }
        }unset($event);
        if($root['kind']==='purchase_unapproved'&&$receipt)$receipt+=['event_id'=>(int)$root['id']];
        $originalReceipt=$receipt;$originalPaid=$paid;$settlementBlockers=[];$position=null;$corrections=[];if($expenseId&&$status==='posted'){$paymentRows=\Tivoli\Repository\PurchaseAccounting::paymentRows($r,'purchase',$id,$expenseId);$position=\Tivoli\Repository\PurchaseAccounting::position($r,'purchase',$id,\Tivoli\Domain\Stay\StayRules::minor((string)$r->run('SELECT amount FROM expenses WHERE id=?',[$expenseId])->fetchColumn()),$paymentRows,(int)($d['qty']??0),$expenseId);$receipt['cost']=$position['cost'];$settlementBlockers=\Tivoli\Repository\PurchaseAccounting::payoutBlockers($payments,$paymentRows,$id);$paid=$settlementBlockers?$originalPaid+array_sum(array_column(array_column($position['corrections'],'data'),'paid_delta')):$position['paid'];$corrections=$position['corrections'];}
        return ['settlement_blockers'=>$settlementBlockers,'original_receipt'=>$originalReceipt,'original_paid'=>$originalPaid,'position'=>$position,'corrections'=>$corrections,'root'=>$root,'history'=>$history,'status'=>$status,'limit'=>$limit,'approval'=>$approval,'scope'=>$scope,'change'=>$change,'commitment'=>$commitment,'receipt'=>$receipt,'expense_id'=>$expenseId,'stock_movement_id'=>$stockMovement,'paid'=>$paid,'payments'=>$payments,'clarifications'=>$clarifications,'version'=>hash('sha256',json_encode([$id,array_column($history,'id'),array_column($corrections,'id')],JSON_THROW_ON_ERROR))];
    }
    public function register(int $actor,int $before=0):array{
        $this->permit($actor);$where=$this->owner($actor)?'':' AND actor_id=?';$params=$this->owner($actor)?[]:[$actor];if($before>0){$where.=' AND id<?';$params[]=$before;}
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='purchase' AND kind IN ('purchase_requested','purchase_unapproved')".$where.' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);
        return ['rows'=>array_map(fn($id)=>$this->detail($actor,(int)$id),$ids),'next'=>$more?(int)end($ids):0];
    }
    public function forHandover(int $actor):array{
        $this->permit($actor);$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE actor_id=? AND target_type='purchase' AND kind IN ('purchase_requested','purchase_unapproved') ORDER BY id",[$actor])->fetchAll(\PDO::FETCH_COLUMN);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if(in_array($c['status'],['cancelled','rejected'],true)||($c['status']==='posted'&&!$c['settlement_blockers']&&$c['paid']===$c['receipt']['cost']))continue;$rows[]=$c;}return $rows;
    }
    public function attention(int $actor):array{
        $this->permit($actor);$this->onlyOwner($actor);$rows=[];$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='purchase' AND kind IN ('purchase_requested','purchase_unapproved') ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if(in_array($c['status'],['pending','change','declared','exception','disputed'],true)||($c['status']==='posted'&&($c['settlement_blockers']||$c['paid']!==$c['receipt']['cost'])))$rows[]=$c;}
        return ['rows'=>$rows,'count'=>count($rows)];
    }
    public static function forExpense(\Tivoli\Repository\AccessRepository $r,int $expense):int{
        return (int)$r->run("SELECT target_id FROM finance_events WHERE kind='purchase_posted' AND target_type='purchase' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.expense_id'))=? LIMIT 1",[(string)$expense])->fetchColumn();
    }
    public static function pendingCosts(\Tivoli\Repository\AccessRepository $r,string $start,string $end):int{
        $ids=$r->run("SELECT DISTINCT CAST(CASE WHEN kind='purchase_unapproved' THEN id ELSE target_id END AS UNSIGNED) id
            FROM finance_events received WHERE target_type='purchase' AND kind IN ('purchase_received','purchase_unapproved')
            AND DATE(CASE WHEN kind='purchase_unapproved' THEN JSON_UNQUOTE(JSON_EXTRACT(payload,'$.receipt.delivered_at')) ELSE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.delivered_at')) END) BETWEEN ? AND ?
            AND NOT EXISTS(SELECT 1 FROM finance_events posted WHERE posted.kind='purchase_posted' AND posted.target_type='purchase'
                AND posted.target_id=CAST(CASE WHEN received.kind='purchase_unapproved' THEN received.id ELSE received.target_id END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)",[$start,$end])->fetchAll(\PDO::FETCH_COLUMN);
        return count($ids);
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload this form before submitting.');
            $file=null;if(isset($in['_evidence'])&&!in_array($action,['request','report','receive','clarify','pay'],true))throw new RuntimeException('Evidence upload is not available for this action.');if(isset($in['_evidence'])){$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $value)if(!is_string($value))throw new RuntimeException('Invalid purchase form.');
            unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);$data=$in;if($file)$data['_evidence']=FinanceAttachment::meta($file);$fingerprint=hash('sha256',json_encode(['purchase.'.$action,$data],JSON_THROW_ON_ERROR));
            return $this->access->transaction(function()use($actor,$action,$in,$file,$key,$fingerprint){
                $this->permit($actor);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(in_array($action,['approve','post','pay','dispute'],true))$this->onlyOwner($actor);if(!hash_equals($old['fingerprint'],$fingerprint))throw new RuntimeException('This form was used for different purchase details.');$this->access->audit($actor,'purchase.replayed','purchase',(string)$old['event_id'],'recorded');return ['id'=>(int)$old['event_id'],'replayed'=>true];}
                $id=$this->command($actor,$action,$in,$file);if($file)FinanceAttachment::save($this->access,$actor,'purchase',$id,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$id]);$this->access->audit($actor,'purchase.'.$action,'purchase',(string)$id,'succeeded');return ['id'=>$id,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'purchase.failed','purchase',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function requestData(array $in):array{
        $r=$this->access->repo;$type=R::text($in['type']??'',20,true);if(!in_array($type,['expense','stock','provider'],true))throw new RuntimeException('Choose an operating expense, stock purchase or service provider.');
        $category=$this->identifier($in['category_id']??'');$name=$r->run('SELECT name FROM expense_categories WHERE id=? AND active=1',[$category])->fetchColumn();if(!$name)throw new RuntimeException('Choose an active expense category.');
        $phone=R::text($in['phone']??'',40);if($phone!==''&&!preg_match('/^[+0-9 ()-]{6,40}$/D',$phone))throw new RuntimeException('Enter a valid supplier phone number.');
        $data=['type'=>$type,'category_id'=>$category,'category_name'=>$name,'title'=>$this->ledgerText($in['title']??'',120),'supplier'=>$this->ledgerText($in['supplier']??'',120),'phone'=>$phone,'purpose'=>R::text($in['purpose']??'',2000,true),'estimate'=>$this->amount($in['estimate']??''),'method'=>$in['method']??'momo'];
        if(!in_array($data['method'],['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');
        if($type==='stock'){
            $extra=$this->identifier($in['extra_id']??'');$row=$r->run("SELECT e.id,e.name,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.id=? AND e.active=1",[$extra])->fetch();
            if(!$row||!$row['tracked'])throw new RuntimeException('Choose an active tracked stock item.');$qty=R::quantity(R::text($in['qty']??'',24,true));if($qty<1||$qty>999999999999)throw new RuntimeException('Enter a positive stock quantity.');
            $data+=['extra_id'=>$extra,'extra_name'=>$row['name'],'qty'=>$qty];
        }return $data;
    }
    private function receiveData(int $actor,array $in,?array $file,?array $case):array{
        if(($in['received']??'')!=='1')throw new RuntimeException('Confirm complete receipt of the goods or service.');
        $cost=$this->amount($in['actual_cost']??'');$at=$this->timestamp($in['delivered_at']??'');$missing=R::text($in['missing_evidence']??'',2000);
        if(!$file&&$missing==='')throw new RuntimeException('Upload evidence or describe the alternative evidence when no supplier receipt is available.');
        if($case&&$at<$case['commitment']['committed_at'])throw new RuntimeException('Delivery cannot precede the recorded actual commitment.');
        $status=$in['payment_status']??'unpaid';if(!in_array($status,['unpaid','paid'],true))throw new RuntimeException('Choose whether this cost was already paid.');
        $data=['cost'=>$cost,'delivered_at'=>$at,'description'=>R::text($in['description']??'',2000,true),'missing_evidence'=>$missing,'proof'=>$file?FinanceAttachment::meta($file):null,'payment'=>null,'exception'=>!$case||$cost>$case['limit']];
        if($status==='paid'){
            $data['payment']=$this->paymentData($in,$cost);
            if($case&&$data['payment']['paid_at']<$case['approval']['created_at'])$data['exception']=true;
        }
        return $data;
    }
    private function paymentData(array $in,int $amount):array{
        $method=$in['method']??'';if(!in_array($method,['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');
        $payer=$this->identifier($in['payer_id']??'');$name=$this->access->repo->run('SELECT name FROM users WHERE id=?',[$payer])->fetchColumn();if($name===false)throw new RuntimeException('Choose the actual payer account.');
        return ['amount'=>$amount,'method'=>$method,'payer_id'=>$payer,'payer_name'=>$name,'paid_at'=>$this->timestamp($in['paid_at']??''),'reference'=>R::text($in['reference']??'',120,$method==='momo')];
    }
    private function command(int $actor,string $action,array $in,?array $file):int{
        $r=$this->access->repo;
        if(in_array($action,['request','report'],true)){
            $data=$this->requestData($in);if($action==='report'&&$data['type']==='stock')throw new RuntimeException('Previously received or unapproved stock needs cost-only reconciliation. Report it as an Operating Expense with the existing stock evidence; do not add inventory again.');$data['quote']=$file?FinanceAttachment::meta($file):null;
            if($action==='report'){$data['receipt']=$this->receiveData($actor,$in,$file,null);$data['exception_reason']=R::text($in['exception_reason']??'',2000,true);}
            return $this->event($action==='report'?'purchase_unapproved':'purchase_requested',$actor,$data);
        }
        $id=$this->identifier($in['id']??'');$c=$this->detail($actor,$id);if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This purchase changed. Reload and review the current history.');$d=$c['root']['data'];$requester=(int)$c['root']['actor_id']===$actor;
        if(in_array($action,['approve','post','pay','dispute'],true))$this->onlyOwner($actor);
        elseif(!$requester)throw new PermissionDenied('Only the requester may record this operational action.');
        if($action==='approve'){
            if(!in_array($c['status'],['pending','change'],true))throw new RuntimeException('This request is not awaiting approval.');
            $reason=R::text($in['reason']??'',2000,true);$decision=$in['decision']??'';
            if($decision==='reject')return $this->event($c['change']?'purchase_change_rejected':'purchase_rejected',$actor,compact('reason'),$id);
            if($decision!=='approve')throw new RuntimeException('Choose Approve or Reject.');$limit=$this->amount($in['limit']??'');$scope=$c['change']['scope']??$c['scope'];
            return $this->event('purchase_approved',$actor,compact('limit','reason','scope'),$id);
        }
        if($action==='amend'){
            if($c['status']!=='approved'||$c['commitment'])throw new RuntimeException('Request revised approval before committing.');
            return $this->event('purchase_change',$actor,['limit'=>$this->amount($in['limit']??''),'reason'=>R::text($in['reason']??'',2000,true),'scope'=>['supplier'=>$this->ledgerText($in['supplier']??$c['scope']['supplier'],120),'purpose'=>R::text($in['scope']??$c['scope']['purpose'],2000,true)]],$id);
        }
        if($action==='commit'){
            if($c['status']!=='approved'||($in['confirmed']??'')!=='1')throw new RuntimeException('A current approval and scope confirmation are required.');
            $amount=$this->amount($in['amount']??'');$at=$this->timestamp($in['committed_at']??'');if($amount>$c['limit'])throw new RuntimeException('Commitment exceeds the approved limit. Request revised approval first.');
            if($at<$c['approval']['created_at'])throw new RuntimeException('Do not backdate a commitment before its actual approval. Report an unapproved cost as an exception instead.');
            return $this->event('purchase_committed',$actor,['amount'=>$amount,'committed_at'=>$at,'approval_id'=>(int)$c['approval']['id'],'scope'=>$c['scope']],$id);
        }
        if($action==='receive'){
            if($c['status']!=='committed')throw new RuntimeException('Record an approved commitment before the delivery.');
            return $this->event('purchase_received',$actor,$this->receiveData($actor,$in,$file,$c),$id);
        }
        if($action==='dispute'){
            if(!in_array($c['status'],['declared','exception'],true))throw new RuntimeException('Only an unposted declaration may be disputed.');
            return $this->event('purchase_disputed',$actor,['reason'=>R::text($in['reason']??'',2000,true)],$id);
        }
        if($action==='clarify'){
            if($c['status']!=='disputed')throw new RuntimeException('This declaration is not disputed.');
            return $this->event('purchase_clarified',$actor,['reason'=>R::text($in['reason']??'',2000,true),'proof'=>$file?FinanceAttachment::meta($file):null],$id);
        }
        if($action==='cancel'){
            if(!in_array($c['status'],['pending','approved'],true)||$c['commitment'])throw new RuntimeException('Only an uncommitted request can be cancelled.');
            return $this->event('purchase_cancelled',$actor,['reason'=>R::text($in['reason']??'',2000,true)],$id);
        }
        if($action==='post')return $this->postPurchase($actor,$id,$c,$in);
        if($action==='pay'){if($c['settlement_blockers'])throw new RuntimeException(implode(' ',$c['settlement_blockers']));
            if($c['status']!=='posted'||($in['paid']??'')!=='1')throw new RuntimeException('Confirm the cost and the completed payment first.');
            $amount=$this->amount($in['amount']??'');if($amount>$c['receipt']['cost']-$c['paid'])throw new RuntimeException('Payment exceeds the unsettled balance.');
            $details=$this->paymentData($in,$amount);$evidence=R::text($in['evidence']??'',2000);if(!$file&&$evidence==='')throw new RuntimeException('Upload payment evidence or explain what was independently checked.');
            $details['exception']=!$c['approval']||$details['paid_at']<$c['approval']['created_at'];$details['exception_reason']=R::text($in['exception_reason']??'',2000,$details['exception']);
            $details['evidence']=$evidence;$details['proof']=$file?FinanceAttachment::meta($file):null;
            return $this->payout($actor,$id,$c['expense_id'],$details);
        }
        throw new RuntimeException('Unknown purchase action.');
    }
}
