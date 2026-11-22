<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** Append-only finance controls. One PDO transaction, security-state lock and durable audit per command. */
final class FinanceService
{
    use FinanceIncome, FinanceWeeklyReview;
    public readonly \Tivoli\Repository\AccessRepository $repo;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){$this->repo=$access->repo;}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    public function today():string{return substr($this->now(),0,10);}
    public function installed():bool{return (bool)$this->repo->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='finance_schema'")->fetchColumn() && (bool)$this->repo->run('SELECT id FROM finance_schema WHERE id=1')->fetchColumn();}
    public function permit(int $actor,bool $admin=false,bool $salary=false):void{
        $u=$this->repo->user($actor);$p=$this->repo->privileges($actor);
        if(!$u||!$u['active']||!array_intersect(['bookings.manage','expenses.submit','expenses.manage','expenses.approve','reports.view','payments.view'],$p)||($admin&&(!$this->repo->isAdmin($actor)||!in_array('reports.view',$p,true)))||($salary&&!in_array('hr.salary',$p,true))){$this->access->audit($actor,'finance.access_denied','finance',null,'denied',['administrator_required'=>$admin,'salary_required'=>$salary]);throw new PermissionDenied('You do not have authority for this finance action.');}
    }
    public static function guardSource(AccessService $access,string $date):void{
        $s=new self($access);if(!$s->installed())return;
        if($s->dayClosed($date))throw new RuntimeException('Reopen this day’s reconciliation before recording financial activity.');
        if($s->periodLocked($date))throw new RuntimeException('The accounting period is locked. Record any late activity in an open period.');
    }
    public function canPostLate(int $actor): bool
    {
        $configured=getenv('TIVOLI_FINANCE_OWNER_USER_ID');
        if($configured===false)$configured=\access_config()['finance_owner_user_id'] ?? null;
        $owner=filter_var($configured,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        return $owner===$actor && $this->isAdmin($actor) && $this->installed();
    }

    public function lateDetails(int $actor,array $input,string $reference): ?array
    {
        if (($input['original_activity_date'] ?? '')==='') {
            if (($input['late_reason'] ?? '')!=='') throw new RuntimeException('Enter the original activity date for this late posting.');
            return null;
        }
        if (!$this->canPostLate($actor)) throw new PermissionDenied('Only the designated finance owner may record late activity.');
        $original=$this->date($input['original_activity_date']);
        if ($original >= $this->today() || !$this->periodLocked($original)) {
            throw new RuntimeException('Choose an earlier activity date in a locked accounting period.');
        }
        self::guardSource($this->access,$this->today());
        return [
            'original_activity_date'=>$original,
            'posting_date'=>$this->today(),
            'reason'=>R::text($input['late_reason'] ?? '',500,true),
            'evidence_reference'=>R::text($reference,120,true),
        ];
    }

    /** Link the existing source posting to late evidence in the same transaction. */
    public function recordLate(int $actor,string $type,int $sourceId,array $details): void
    {
        $id=$this->event('late_posting',$type,(string)$sourceId,0,$details['posting_date'],$actor,$details);
        $this->claim('late_'.$type,$details['evidence_reference'],$id);
    }

    public function authorizeDepartedReceipt(int $actor,int $bookingId,int $amount,string $method,array $details): int
    {
        $id=$this->event('departed_receipt_authorized','booking',(string)$bookingId,$amount,$this->today(),$actor,
            $details+['method'=>$method]);
        // Share the reference namespace with late receipts recorded on active stays.
        $this->claim('late_payment',$details['evidence_reference'],$id);
        return $id;
    }

    public function originalReceiptDate(array $payment,string $fallback): string
    {
        if (!empty($payment['recovery_event_id'])) {
            return $this->find((int)$payment['recovery_event_id'])['data']['original_activity_date'];
        }
        $payload=$this->repo->run("SELECT payload FROM finance_events
            WHERE kind='late_posting' AND target_type='payment' AND target_id=? ORDER BY id DESC LIMIT 1",
            [(string)$payment['id']])->fetchColumn();
        if($payload) return json_decode($payload,true,512,JSON_THROW_ON_ERROR)['original_activity_date'];
        return substr($payment['created_at'] ?? $fallback,0,10);
    }

    public function guardLateMomoReference(string $reference): void
    {
        $normalized=mb_strtolower(trim($reference),'UTF-8');\Tivoli\Repository\ReceiptAccounting::guardAlias($this->repo,$reference);\Tivoli\Repository\ExpenseAccounting::guardAlias($this->repo,$reference);
        if($this->repo->run("SELECT 1 FROM finance_claims WHERE scope='business_momo' AND identity_hash=?",
                [hash('sha256',$normalized)])->fetchColumn()
            || $this->repo->run("SELECT 1 FROM stay_receipts s JOIN payments p ON p.id=s.payment_id
                WHERE p.method='momo' AND LOWER(TRIM(s.reference_no))=? LIMIT 1",[$normalized])->fetchColumn()
            || $this->repo->run("SELECT 1 FROM finance_events WHERE kind='income_recorded'
                AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.method'))='momo'
                AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=? LIMIT 1",[$normalized])->fetchColumn()){
            throw new RuntimeException('This MoMo reference is already recorded. Review the existing transaction.');
        }
    }

    public static function recordRefund(AccessService $access,int $actor,int $refund,int $amount,string $proof,string $postedAt,string $method='momo',?string $actual=null,?string $payer=null):void{
        $s=new self($access,$postedAt);if(!$s->installed())return;
        $id=$s->event('refund_recorded','refund',(string)$refund,$amount,$s->today(),$actor,['method'=>$method,'reference'=>$proof,'actual_at'=>$actual,'actual_payer'=>$payer,'recorded_by'=>$actor]);$s->claim($method==='momo'?'business_momo':'cash_refund',$proof,$id);
    }
    public static function guardExpenseInvoice(AccessService $access,array $data):void{
        $s=new self($access);if(!$s->installed()||!trim($data['vendor'])||!trim($data['reference_no']))return;
        foreach($s->events('provider_requested') as $e)if(isset($e['data']['invoice'])&&mb_strtolower(trim($e['data']['payee']))===mb_strtolower(trim($data['vendor']))&&mb_strtolower(trim($e['data']['invoice']))===mb_strtolower(trim($data['reference_no'])))throw new RuntimeException('This invoice is already in Provider Payments; do not enter its cost again.');
    }
    public function isAdmin(int $actor):bool{$u=$this->repo->user($actor);return $u&&(bool)$u['active']&&$this->repo->isAdmin($actor)&&in_array('reports.view',$this->repo->privileges($actor),true);}
    private function date(mixed $s):string{$s=R::text($s,10,true);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s,new \DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$s||$s<'2000-01-01'||$s>$this->today())throw new RuntimeException('Enter a valid date, no later than today.');return $s;}
    private function amount(array $in,string $key='amount',bool $zero=false):int{$n=R::minor(R::text($in[$key]??'',24,true));if(!$zero&&$n===0)throw new RuntimeException('Enter a positive amount.');return $n;}
    private function event(string $kind,string $type,string $target,int $amount,string $date,int $actor,array $data):int{
        $this->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,$type,$target,$amount,$date,$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$this->repo->db->lastInsertId();
    }
    public function events(?string $kind=null):array{
        $rows=$this->repo->run('SELECT f.*,u.name actor_name FROM finance_events f JOIN users u ON u.id=f.actor_id'.($kind?' WHERE kind=?':'').' ORDER BY f.id',$kind?[$kind]:[])->fetchAll();foreach($rows as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$e['amount_minor']=(int)$e['amount_minor'];}return $rows;
    }
    private function find(int $id):array{$e=$this->repo->run('SELECT * FROM finance_events WHERE id=?',[$id])->fetch();if(!$e)throw new RuntimeException('Finance record not found.');$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);return $e;}
    private function linked(string $kind,string $type,string $id):bool{return (bool)$this->repo->run('SELECT id FROM finance_events WHERE kind=? AND target_type=? AND target_id=?',[$kind,$type,$id])->fetchColumn();}
    private function claim(string $scope,string $identity,int $id):void{if($scope==='business_momo'){\Tivoli\Repository\ExpenseAccounting::guardAlias($this->repo,$identity);$source=$this->find($id);\Tivoli\Repository\ReceiptAccounting::guardAlias($this->repo,$identity,$source['kind']==='receipt_matched'&&$source['target_type']==='payment'?(int)$source['target_id']:null);}$hash=hash('sha256',mb_strtolower(trim($identity),'UTF-8'));$prior=$this->repo->run('SELECT event_id FROM finance_claims WHERE scope=? AND identity_hash=?',[$scope,$hash])->fetchColumn();
        if($prior){
            $revision=$this->repo->run("SELECT payload FROM finance_events WHERE kind='claim_reused' AND target_type=? AND target_id=? ORDER BY id DESC LIMIT 1",[$scope,$hash])->fetchColumn();if($revision)$prior=json_decode($revision,true,512,JSON_THROW_ON_ERROR)['replacement_id'];
            if($scope==='business_momo'||!$this->linked('request_rejected','request',(string)$prior))throw new RuntimeException('This reference or invoice has already been recorded.');
            $this->event('claim_reused',$scope,$hash,0,$this->today(),(int)$this->find($id)['actor_id'],['rejected_request_id'=>(int)$prior,'replacement_id'=>$id]);return;
        }
        $this->repo->run('INSERT INTO finance_claims VALUES(?,?,?)',[$scope,$hash,$id]);}
    public function dayClosed(string $day):bool{$e=$this->repo->run("SELECT kind FROM finance_events WHERE target_type='day' AND target_id=? AND kind IN ('day_closed','day_reopened') ORDER BY id DESC LIMIT 1",[$day])->fetchColumn();return $e==='day_closed';}
    public function periodLocked(string $date):bool{
        $d=new \DateTimeImmutable($date,new \DateTimeZone('UTC'));foreach(['week:'.$d->modify('monday this week')->format('Y-m-d'),'month:'.$d->format('Y-m-01')] as $key){$e=$this->repo->run("SELECT kind FROM finance_events WHERE target_type='period' AND target_id=? AND kind IN ('period_locked','period_reopened') ORDER BY id DESC LIMIT 1",[$key])->fetchColumn();if($e==='period_locked')return true;}return false;
    }
    private function openCostDate(string $date):void{if($this->periodLocked($date))throw new RuntimeException('Reopen the reviewed period before adding a cost.');}
    public function cash():array{
        $cursor=(int)$this->repo->run('SELECT payment_cursor FROM finance_schema WHERE id=1')->fetchColumn();
        $rows=$this->repo->run("SELECT p.id,p.amount,p.method,p.created_at,r.reference_no,r.actor_id,u.name actor_name,b.guest_name,room.name room_name FROM payments p LEFT JOIN stay_receipts r ON r.payment_id=p.id LEFT JOIN users u ON u.id=r.actor_id JOIN bookings b ON b.id=p.booking_id JOIN rooms room ON room.id=b.room_id WHERE p.id>? ORDER BY p.created_at,p.id",[$cursor])->fetchAll();
        $rows=\Tivoli\Repository\ReceiptAccounting::project($this->repo,$rows);foreach($rows as $i=>&$row){$position=$row['position'];if($position['method']!=='cash'||$position['amount']<=0){unset($rows[$i]);continue;}$row['amount']=R::decimal($position['amount']);}unset($row);$rows=array_values($rows);
        foreach($this->events('income_recorded') as $e)if($e['data']['method']==='cash')$rows[]=['id'=>'income:'.$e['id'],'amount'=>R::decimal($e['amount_minor']),'created_at'=>$e['created_at'],'actor_id'=>$e['actor_id'],'actor_name'=>$e['actor_name'],'guest_name'=>$e['data']['payer'],'room_name'=>'Activity: '.$e['data']['activity']];
        usort($rows,static fn($a,$b)=>strcmp($a['created_at'],$b['created_at'])?:strnatcmp((string)$a['id'],(string)$b['id']));
        $allEvents=\Tivoli\Repository\ReceiptReallocation::events($this->events());$indexed=\Tivoli\Domain\Finance\CashResolution::index($allEvents);$deposits=[];$confirmed=[];$deposit_positions=[];foreach($allEvents as $e)if($e['kind']==='deposit_recorded'){$deposits[$e['id']]=$e;$position=\Tivoli\Domain\Finance\CashResolution::position('deposit',(int)$e['id'],(int)$e['amount_minor'],$indexed['deposit'][(string)$e['id']]??[],$e['data']['allocations']);$deposit_positions[$e['id']]=$position;if($position['verified']===(int)$e['amount_minor'])$confirmed[(string)$e['id']]=true;}
        $held=0;$transit=0;$due=0;$overdue=0;$converted=0;
        foreach($rows as &$r){$r['minor']=R::minor($r['amount']);$r['due']=(new \DateTimeImmutable(substr($r['created_at'],0,10)))->modify('+1 day')->format('Y-m-d');$r['reserved']=0;$r['confirmed']=0;$r['late']=false;foreach($deposits as $id=>$deposit){$allocated=(int)($deposit['data']['allocations'][(string)$r['id']]??0);$resolved=$deposit_positions[$id];$r['reserved']+=$allocated-($resolved['returns'][(string)$r['id']]??0);$verified=(int)($resolved['credits'][(string)$r['id']]??0);$r['confirmed']+=$verified;if($verified&&$deposit['effective_date']>$r['due'])$r['late']=true;}$r['held']=$r['minor']-$r['reserved'];$r['outstanding']=$r['minor']-$r['confirmed'];$held+=$r['held'];$transit+=$r['reserved']-$r['confirmed'];$converted+=$r['confirmed'];if($r['due']<=$this->today())$due+=$r['outstanding'];if($r['due']<$this->today())$overdue+=$r['outstanding'];}
        return \Tivoli\Domain\Finance\CashCustody::apply(compact('rows','held','transit','due','overdue','converted','deposits','confirmed','deposit_positions'),$allEvents);
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload this form before submitting.');
            $attachment=null;if(isset($in['_evidence'])){$attachment=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid finance form.');
            unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);$canonical=$in;if($attachment)$canonical['_evidence']=FinanceAttachment::meta($attachment);$fingerprint=hash('sha256',json_encode([$action,$canonical],JSON_THROW_ON_ERROR));if($attachment)$in['_evidence']=$attachment;
            return $this->access->transaction(function()use($actor,$action,$in,$key,$fingerprint){
                if(!$this->installed())throw new RuntimeException('Finance setup is required.');
                $admin=!in_array($action,['deposit_record','provider_request','income_record','income_complete'],true);$this->permit($actor,$admin,in_array($action,['payroll_request'],true));
                $old=$this->repo->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fingerprint))throw new RuntimeException('This form was already used for different details.');$this->access->audit($actor,'finance.replayed','finance',(string)$old['event_id'],'recorded');return ['id'=>(int)$old['event_id'],'replayed'=>true];}
                if($action==='deposit_record')$this->access->requirePrivilege($actor,'bookings.manage');if($action==='provider_request'&&!array_intersect(['bookings.manage','expenses.submit','expenses.manage'],$this->repo->privileges($actor)))throw new PermissionDenied('You cannot submit provider costs.');
                $posting=new self($this->access,$this->now());
                $id=$posting->command($actor,$action,$in);$this->repo->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$id]);
                $e=$this->find($id);$meta=['event_id'=>$id,'kind'=>$e['kind'],'amount_minor'=>(int)$e['amount_minor'],'effective_date'=>$e['effective_date'],'target_type'=>$e['target_type'],'target_id'=>$e['target_id']];
                $this->access->audit($actor,'finance.'.$action,'finance',(string)$id,'succeeded',$meta);return ['id'=>$id,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'finance.action_failed','finance',null,'denied',['action'=>substr($action,0,32),'reason'=>'permission_validation_state_or_storage_failure']);throw $e;}
    }
    private function command(int $actor,string $action,array $in):int{
        $today=$this->today();$r=$this->repo;
        if(in_array($action,['income_record','income_complete'],true))return $this->incomeCommand($actor,$action,$in);
        if(in_array($action,['week_decide','week_followup'],true))return $this->weeklyCommand($actor,$action,$in);
        if(in_array($action,['deposit_confirm','receipt_match','request_review','pay_cost'],true)&&!preg_match('/^[1-9][0-9]{0,17}$/D',$in['id']??''))throw new RuntimeException('Choose a valid source identity.');
        if($action==='deposit_record'){
            $amount=$this->amount($in);$fee=$this->amount($in,'fee',true);$date=$this->date($in['date']??'');if($fee>=$amount)throw new RuntimeException('Deposit fee must be less than the cash handed over.');
            $reference=R::text($in['reference']??'',120,true);$cash=$this->cash();if($amount>$cash['held'])throw new RuntimeException('Deposit exceeds the cash available; pending deposits already reserve their cash.');
            if($fee)$this->openCostDate($date);$left=$amount;$allocations=[];$custodyAllocations=[];
            foreach($cash['rows'] as $row){if(!$left)break;if(substr($row['created_at'],0,10)>$date)continue;
                foreach($row['available'] as $custodian=>$available){if(substr($row['custody_since'][$custodian]??$row['created_at'],0,10)>$date)continue;if(!$this->isAdmin($actor)&&(int)$custodian!==$actor)continue;$n=min($left,$available);if($n){$source=(string)$row['id'];$allocations[$source]=($allocations[$source]??0)+$n;$custodyAllocations[$source][$custodian]=$n;$left-=$n;}if(!$left)break;}
            }
            if($left)throw new RuntimeException('Deposit date precedes the receipts or custody being deposited, or this cash is already reserved.');
            $id=$this->event('deposit_recorded','wallet','business', $amount,$date,$actor,['fee'=>$fee,'reference'=>$reference,'allocations'=>$allocations,'custody_allocations'=>$custodyAllocations,'wallet'=>'Business MoMo','status'=>'in_transit']);$this->claim('business_momo',$reference,$id);return $id;
        }
        if($action==='deposit_confirm'){
            CashDiscrepancyService::guard($this->access,'deposit',(int)($in['id']??0));
            $id=(int)($in['id']??0);$e=$this->find($id);if($e['kind']!=='deposit_recorded')throw new RuntimeException('Choose a recorded deposit.');if($this->linked('deposit_confirmed','deposit',(string)$id))throw new RuntimeException('Deposit already confirmed.');
            $credit=(int)$e['amount_minor']-(int)$e['data']['fee'];
            // Older open forms may still supply an amount: never accept a conflicting value.
            if(isset($in['amount'])&&$this->amount($in)!==$credit)throw new RuntimeException('Wallet credit must exactly equal the deposit less its stated fee.');
            if(($in['verified']??'')!=='1')throw new RuntimeException('Check the independent business-wallet transaction before confirming.');if($e['data']['fee'])$this->openCostDate($e['effective_date']);
            return $this->event('deposit_confirmed','deposit',(string)$id,(int)$e['amount_minor'],$e['effective_date'],$actor,['fee'=>$e['data']['fee'],'credit_minor'=>$credit,'confirmation'=>'operator_attestation','evidence'=>R::text($in['evidence']??'',500),'reference'=>$e['data']['reference'],'recorded_by'=>$e['actor_id']]);
        }
        if($action==='receipt_match'){
            CashDiscrepancyService::guard($this->access,$in['type']??'payment',(int)($in['id']??0));
            $id=(int)($in['id']??0);$source=$in['type']??'payment';if(!in_array($source,['payment','income'],true))throw new RuntimeException('Choose a receipt source.');
            if($source==='income'){$e=$this->find($id);$p=$e['kind']==='income_recorded'&&$e['data']['method']==='momo'?['amount'=>R::decimal((int)$e['amount_minor'])]:null;}else{$view=\Tivoli\Repository\ReceiptAccounting::position($r,$id);$pos=$view['position'];$p=$pos['method']==='momo'&&$pos['amount']>0?['amount'=>R::decimal($pos['amount'])]:null;\Tivoli\Repository\ReceiptAccounting::guardAlias($r,$in['reference']??'',$id);\Tivoli\Repository\ExpenseAccounting::guardAlias($r,$in['reference']??'');if(($pos['last_id']||(($alias=\Tivoli\Repository\ReceiptAccounting::aliasOwner($r,$pos['reference']))!==null&&$alias===\Tivoli\Repository\ReceiptAccounting::collection($r,$id)))&&mb_strtolower(trim($in['reference']??''),'UTF-8')!==mb_strtolower(trim($pos['reference']),'UTF-8'))throw new RuntimeException('Match the current corrected reference.');}
            if(!$p||$this->linked('receipt_matched',$source,(string)$id))throw new RuntimeException('Choose an unmatched MoMo receipt.');
            if($this->amount($in)!==R::minor($p['amount'])||($in['verified']??'')!=='1')throw new RuntimeException('Verify the successful business-wallet credit and exact amount.');
            $id2=$this->event('receipt_matched',$source,(string)$id,R::minor($p['amount']),$today,$actor,['reference'=>R::text($in['reference']??'',120,true),'evidence'=>R::text($in['evidence']??'',500,true),'wallet'=>'Business MoMo']);$this->claim('business_momo',$in['reference'],$id2);return $id2;
        }
        if(in_array($action,['provider_request','payroll_request'],true)){
            $date=$this->date($in['date']??'');$this->openCostDate($date);$comment=R::text($in['comment']??'',500);
            if($action==='provider_request'){
                if(($in['accepted']??'')!=='1')throw new RuntimeException('Confirm that the work or service was received.');
                $amount=$this->amount($in);$payee=R::text($in['payee']??'',120,true);$phone=R::text($in['phone']??'',40,true);if(!preg_match('/^[+0-9 ()-]{6,40}$/D',$phone)||strlen(preg_replace('/[^0-9]/','',$phone))<6)throw new RuntimeException('Enter a valid provider phone number.');
                if(!isset($in['_evidence']))throw new RuntimeException('Upload work or receipt evidence (PDF, JPEG or PNG, at most 2 MB).');$file=FinanceAttachment::checked($in['_evidence']);
                $id=$this->event('provider_requested','provider','service',$amount,$date,$actor,['payee'=>$payee,'phone'=>$phone,'attachment'=>FinanceAttachment::meta($file),'evidence'=>'Uploaded work / receipt evidence','payable'=>$amount]);
                self::claimCostEvidence($this->access,$actor,'provider',$id,$file,$amount,$date);FinanceAttachment::save($this->access,$actor,'provider',$id,$file);return $id;
            }
            $staff=(int)($in['staff_id']??0);$period=R::text($in['period']??'',7,true);if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period)||$period>substr($today,0,7))throw new RuntimeException('Choose a payroll month no later than this month.');
            if(!$r->run("SELECT id FROM hr_staff WHERE id=? AND status='active'",[$staff])->fetchColumn())throw new RuntimeException('Choose active staff.');
            if(($in['attendance']??'')!=='1')throw new RuntimeException('Review attendance, leave and overtime before payroll.');
            $end=(new \DateTimeImmutable($period.'-01'))->modify('last day of this month')->format('Y-m-d');$salary=$r->run('SELECT * FROM hr_salary_versions WHERE staff_id=? AND effective_date<=? ORDER BY effective_date DESC,id DESC LIMIT 1',[$staff,$end])->fetch();if(!$salary)throw new RuntimeException('Set up salary and verified MoMo details in Staff & HR first.');
            require_once dirname(__DIR__,2).'/bootstrap/hr.php';$vault=new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);$data=json_decode($vault->open($salary['payload'],'salary:'.$staff),true,512,JSON_THROW_ON_ERROR);
            if($salary['preferred']!=='momo'||($data['frequency']??'')!=='monthly')throw new RuntimeException('This monthly payroll form requires monthly salary with MoMo details. Other pay frequencies need a separately reviewed payroll calculation.');
            $base=R::minor($data['amount']);$allow=$this->amount($in,'allowance',true);$ot=$this->amount($in,'overtime',true);$employer=$this->amount($in,'employer',true);$ded=$this->amount($in,'deductions',true);$advance=$this->amount($in,'advance',true);$gross=$base+$allow+$ot;$net=$gross-$ded-$advance;if($gross+$employer>999999999999)throw new RuntimeException('Staff cost exceeds the supported ledger limit.');if($net<0)throw new RuntimeException('Deductions and prior advances exceed gross pay.');
            $id=$this->event('payroll_requested','staff',(string)$staff,$gross+$employer,$date,$actor,['staff_id'=>$staff,'salary_revision'=>(int)$salary['id'],'period'=>$period,'base'=>$base,'allowance'=>$allow,'overtime'=>$ot,'employer'=>$employer,'deductions'=>$ded,'advance'=>$advance,'payable'=>$net,'evidence'=>$comment,'statutory_calculation'=>'reviewed_manual_inputs']);$this->claim('staff_payroll',$staff.'|'.$period,$id);return $id;
        }
        if($action==='request_review'){
            $id=(int)($in['id']??0);$e=$this->find($id);if(!in_array($e['kind'],['provider_requested','payroll_requested'],true))throw new RuntimeException('Choose a pending cost request.');if($e['kind']==='payroll_requested'){
                $this->permit($actor,true,true);if(!$this->canPostLate($actor))throw new PermissionDenied('Only the designated owner may review payroll.');
                if(PayrollService::forRequest($r,$id))throw new RuntimeException('Review this linked payroll from Payroll & Staff Payments.');
            }elseif((int)$e['actor_id']===$actor)throw new PermissionDenied('Another administrator must review your provider request.');
            if($this->linked('request_approved','request',(string)$id)||$this->linked('request_rejected','request',(string)$id))throw new RuntimeException('Request already reviewed.');
            $decision=$in['decision']??'';if(!in_array($decision,['approved','rejected'],true))throw new RuntimeException('Choose Approve or Reject.');if($decision==='approved')$this->openCostDate($e['effective_date']);
            return $this->event('request_'.$decision,'request',(string)$id,(int)$e['amount_minor'],$e['effective_date'],$actor,['reason'=>R::text($in['reason']??'',500,true),'request_kind'=>$e['kind']]);
        }
        if($action==='pay_cost'){
            $method=$in['method']??'momo';if(!in_array($method,['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');self::guardSource($this->access,$today);
            $type=$in['type']??'';$id=(int)($in['id']??0);$cursor=(int)$r->run('SELECT expense_cursor FROM finance_schema WHERE id=1')->fetchColumn();
            if($type==='expense'){if(PurchaseService::forExpense($r,$id))throw new RuntimeException('This expense belongs to a purchase. Record bounded settlement from that purchase instead.');$e=$r->run('SELECT * FROM expenses WHERE id=?',[$id])->fetch();if(!$e||$id<=$cursor)throw new RuntimeException('Historical expenses retain unknown settlement status; do not pay them again from this workflow.');$payable=R::minor($e['amount']);if($method!==$e['method'])throw new RuntimeException('Use the settlement method recorded on this expense.');}
            elseif($type==='request'){if(\Tivoli\Repository\PurchaseAccounting::entries($r,'provider',$id))throw new RuntimeException('Use the linked provider source to settle its effective remaining balance.');if($method!=='momo')throw new RuntimeException('Provider and payroll payouts use MoMo.');$e=$this->find($id);if(!in_array($e['kind'],['provider_requested','payroll_requested'],true)||!$this->linked('request_approved','request',(string)$id))throw new RuntimeException('Approve this request before payment.');if($e['kind']==='payroll_requested'){$this->permit($actor,true,true);if(!$this->canPostLate($actor))throw new PermissionDenied('Only the designated owner may record payroll settlement.');if(PayrollService::forRequest($r,$id))throw new RuntimeException('Settle this linked payroll from Payroll & Staff Payments.');}$payable=(int)$e['data']['payable'];}
            else throw new RuntimeException('Choose a payable cost.');
            if($this->linked('cost_paid',$type,(string)$id))throw new RuntimeException('Payment already recorded.');if($this->amount($in)!==$payable||($in['verified']??'')!=='1')throw new RuntimeException('Confirm the completed payment and its exact amount.');
            $destination=R::text($in['destination']??'',120,true);if(strlen(preg_replace('/[^0-9]/','',$destination))>4)throw new RuntimeException('Keep only the last four account digits in the payment record.');
            $reference=R::text($in['reference']??'',120,$method==='momo');
            $evidence=R::text($in['evidence']??'',500,true);
            $actualPayer=null;if($type==='expense'&&($in['actual_payer_id']??'')!==''){if(!is_string($in['actual_payer_id'])||!preg_match('/^[1-9][0-9]{0,17}$/D',$in['actual_payer_id'])||!$r->user((int)$in['actual_payer_id']))throw new RuntimeException('Choose the evidenced actual payer or leave it unknown.');$actualPayer=(int)$in['actual_payer_id'];}
            $late=$this->lateDetails($actor,$in,$reference);
            $id2=$this->event('cost_paid',$type,(string)$id,$payable,$today,$actor,['method'=>$method,'cash_funding'=>$method==='cash'?'separate_from_full_sales_deposit':null,'reference'=>$reference,'destination'=>$destination,'evidence'=>$evidence]+($type==='expense'?['actual_payer_id'=>$actualPayer]:[]));if($method==='momo')$this->claim('business_momo',$reference,$id2);
            if($late)$this->recordLate($actor,'payout',$id2,$late);
            return $id2;
        }
        if(in_array($action,['day_close','day_reopen'],true)){
            $date=$this->date($in['date']??'');$closed=$this->dayClosed($date);if(($action==='day_close'&&$closed)||($action==='day_reopen'&&!$closed))throw new RuntimeException('This daily reconciliation already has that state.');
            $comment=R::text($in['comment']??'',500);$snapshot=$this->summary($date,$date);$reviewCash=$this->cash();$snapshot['cash_at_review']=['held'=>$reviewCash['held'],'in_transit'=>$reviewCash['transit'],'wallet_confirmed'=>$reviewCash['converted'],'due_through_review_date'=>$reviewCash['due'],'reviewed_at'=>$this->now()];
            if($action==='day_close'){
                $cash=$this->cash();foreach($cash['rows'] as $row)if($row['due']<=$date&&$row['outstanding']>0)throw new RuntimeException('Confirm all cash deposits due by this date before closing.');
                foreach($cash['rows'] as $cashRow)if(substr($cashRow['created_at'],0,10)<=$date&&$cashRow['reserved']>$cashRow['confirmed'])throw new RuntimeException('Confirm deposits in transit through this review date before closing.');
                if($this->unmatched($date))throw new RuntimeException('Match MoMo receipts through this date before closing.');
            }
            return $this->event($action==='day_close'?'day_closed':'day_reopened','day',$date,0,$today,$actor,['comment'=>$comment,'snapshot'=>$snapshot]);
        }
        if(in_array($action,['period_lock','period_reopen'],true)){
            $period=$in['period']??'';
            if($period==='week')throw new RuntimeException('Manage weekly decisions in Weekly Reconciliation.');
            $start=$this->date($in['start']??'');$d=new \DateTimeImmutable($start);if($period!=='month'||$d->format('d')!=='01')throw new RuntimeException('Choose the first day of a month.');
            $key=$period.':'.$start;$last=$r->run("SELECT kind FROM finance_events WHERE target_type='period' AND target_id=? AND kind IN ('period_locked','period_reopened') ORDER BY id DESC LIMIT 1",[$key])->fetchColumn();if(($action==='period_lock'&&$last==='period_locked')||($action==='period_reopen'&&$last!=='period_locked'))throw new RuntimeException('Review already has that state.');
            $end=$d->modify('last day of this month')->format('Y-m-d');$snapshot=$this->summary($start,min($end,$today));
            $cs=new InventoryCostingService($this->access,$this->now());if($cs->owner($actor)){$costing=$cs->period($actor,$start,min($end,$today),$snapshot['profit']);if($costing['started'])$snapshot['stock_costing']=$costing;}
            if($action==='period_lock'){foreach($this->events() as $pending){if(in_array($pending['kind'],['provider_requested','payroll_requested'],true)&&$pending['effective_date']>=$start&&$pending['effective_date']<=min($end,$today)&&!$this->linked('request_approved','request',(string)$pending['id'])&&!$this->linked('request_rejected','request',(string)$pending['id']))throw new RuntimeException('Review pending provider and payroll costs before locking.');}if($r->run("SELECT id FROM expense_submissions WHERE status='pending' AND expense_date BETWEEN ? AND ? LIMIT 1",[$start,min($end,$today)])->fetchColumn())throw new RuntimeException('Review pending expense submissions before locking.');}
            if($action==='period_lock'&&PayrollService::pendingCosts($r,$start,min($end,$today)))throw new RuntimeException('Review prepared payroll before locking this period.');
            if($action==='period_lock'&&PurchaseService::pendingCosts($r,$start,min($end,$today)))throw new RuntimeException('Review received purchase costs before locking this period.');
            if($action==='period_lock'&&(($in['complete']??'')!=='1'||$this->unmatched(min($end,$today))))throw new RuntimeException('Match outstanding MoMo receipts and confirm cost completeness before review.');
            return $this->event($action==='period_lock'?'period_locked':'period_reopened','period',$key,0,$today,$actor,['comment'=>R::text($in['comment']??'',500,true),'snapshot'=>$snapshot,'start'=>$start,'end'=>min($end,$today),'definition'=>'management_estimate_completed_stay_room_revenue']);
        }
        if($action==='review_action'){$due=R::text($in['due']??'',10,true);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$due);if(!$d||$d->format('Y-m-d')!==$due)throw new RuntimeException('Enter a valid follow-up due date.');return $this->event('review_action','management','follow_up',0,$today,$actor,['action'=>R::text($in['comment']??'',500,true),'owner'=>R::text($in['owner']??'',120,true),'due'=>$due]);}
        throw new RuntimeException('Unknown finance action.');
    }
    public function unmatched(string $end,?string $start=null):array{
        $next=(new \DateTimeImmutable($end,new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        $scope=$start===null?'p.id>(SELECT payment_cursor FROM finance_schema WHERE id=1)':'p.created_at>=?';
        $params=$start===null?[$next]:[$start.' 00:00:00',$next];
        $rows=$this->repo->run("SELECT p.id,p.amount,p.method,p.created_at,s.reference_no FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id WHERE $scope AND p.created_at<? ORDER BY p.id",$params)->fetchAll();$resolutionEvents=\Tivoli\Domain\Finance\CashResolution::index($this->events());$rows=\Tivoli\Repository\ReceiptAccounting::project($this->repo,$rows);foreach($rows as $i=>&$p){$position=$p['position'];if($position['method']!=='momo'||$position['amount']<=0||\Tivoli\Domain\Finance\CashResolution::position('payment',(int)$p['id'],$position['amount'],$resolutionEvents['payment'][(string)$p['id']]??[])['verified']===$position['amount']){unset($rows[$i]);continue;}$p['amount']=R::decimal($position['amount']);$p['reference_no']=$position['reference'];$p['source']='payment';}unset($p);$rows=array_values($rows);
        foreach($this->events('income_recorded') as $e)if($e['created_at']<$next&&($start===null||$e['effective_date']>=$start)&&($e['data']['method']??'')==='momo'&&\Tivoli\Domain\Finance\CashResolution::position('income',(int)$e['id'],(int)$e['amount_minor'],$resolutionEvents['income'][(string)$e['id']]??[])['verified']<(int)$e['amount_minor'])$rows[]=['id'=>$e['id'],'amount'=>R::decimal($e['amount_minor']),'created_at'=>$e['created_at'],'reference_no'=>$e['data']['reference']??'','source'=>'income'];return $rows;
    }
    public function summary(string $start,string $end):array{
        $r=$this->repo;$from=$start.' 00:00:00';$to=(new \DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        $receipts=[];foreach($r->run('SELECT method,COALESCE(SUM(amount),0) total FROM payments WHERE created_at>=? AND created_at<? GROUP BY method',[$from,$to])->fetchAll() as $row)$receipts[$row['method']]=R::minor((string)$row['total']);
        $roomSource=\Tivoli\Repository\DepartureRevenue::sql(true);
        $rooms=(int)$r->run('SELECT COALESCE(SUM(room_minor),0) FROM ('.$roomSource.') d WHERE posting_at>=? AND posting_at<?',[$from,$to])->fetchColumn();
        $extras=0;foreach($r->run('SELECT qty,unit_price FROM booking_extras WHERE created_at>=? AND created_at<?',[$from,$to])->fetchAll() as $e)$extras+=R::line(R::minor((string)$e['qty']),R::minor((string)$e['unit_price']));
        $incidental=(int)$r->run("SELECT COALESCE(SUM(amount_minor),0) FROM stay_incidentals WHERE status='approved' AND reviewed_at>=? AND reviewed_at<?",[$from,$to])->fetchColumn();
        $activity=0;$advances=0;foreach($this->events() as $income){if($income['effective_date']<$start||$income['effective_date']>$end)continue;if($income['kind']==='income_recorded'){$m=$income['data']['method'];$receipts[$m]=($receipts[$m]??0)+$income['amount_minor'];if($income['data']['treatment']==='earned')$activity+=$income['amount_minor'];else $advances+=$income['amount_minor'];}elseif($income['kind']==='income_completed')$activity+=$income['amount_minor'];}
        $chargeAdjustments=\Tivoli\Repository\ChargeAccounting::period($r,$start,$end);$extras+=$chargeAdjustments['extras'];$incidental+=$chargeAdjustments['incidental'];
        $originalReceipts=$receipts;$receiptAdjustments=\Tivoli\Repository\ReceiptAccounting::period($r,$start,$end);foreach($receiptAdjustments['channels'] as $method=>$delta)if($delta)$receipts[$method]=($receipts[$method]??0)+$delta;
        $expenseAdjustments=\Tivoli\Repository\ExpenseAccounting::period($r,$start,$end);$expenses=R::minor((string)$r->run('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?',[$start,$end])->fetchColumn())+$expenseAdjustments['cost'];$payroll=0;$providers=0;$fees=0;$payments=0;$cashCosts=0;$momoCosts=0;
        foreach($this->events() as $e){if($e['effective_date']<$start||$e['effective_date']>$end)continue;if($e['kind']==='request_approved'){if($e['data']['request_kind']==='payroll_requested')$payroll+=$e['amount_minor'];else $providers+=$e['amount_minor'];}if($e['kind']==='deposit_confirmed')$fees+=(int)$e['data']['fee'];if($e['kind']==='cost_paid'){$payments+=$e['amount_minor'];if(($e['data']['method']??'momo')==='cash')$cashCosts+=$e['amount_minor'];else $momoCosts+=$e['amount_minor'];}}
        $refunds=(int)$r->run('SELECT COALESCE(SUM(amount_minor),0) FROM stay_refunds WHERE created_at>=? AND created_at<?',[$from,$to])->fetchColumn();
        $unknown=(int)$r->run("SELECT COUNT(*) FROM bookings b WHERE b.status='checked_out' AND b.checkout_at>=? AND b.checkout_at<? AND NOT EXISTS(SELECT 1 FROM stay_departures d WHERE d.booking_id=b.id) AND NOT EXISTS(SELECT 1 FROM finance_events i WHERE i.kind='departure_reported' AND i.target_type='booking' AND i.target_id=CAST(b.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)",[$from,$to])->fetchColumn();
        $purchaseAdjustments=\Tivoli\Repository\PurchaseAccounting::period($r,$start,$end);$expenses+=$purchaseAdjustments['expense_cost'];$providers+=$purchaseAdjustments['provider_cost'];$payments+=$purchaseAdjustments['paid'];$cashCosts+=$purchaseAdjustments['cash'];$momoCosts+=$purchaseAdjustments['momo'];
        $payrollAdjustments=\Tivoli\Repository\PayrollAccounting::period($r,$start,$end);$payroll+=$payrollAdjustments['cost'];$payments+=$payrollAdjustments['paid'];$momoCosts+=$payrollAdjustments['paid'];
        $active=(int)$r->run("SELECT COUNT(*) FROM bookings b WHERE b.status='active' AND NOT (".\Tivoli\Repository\DepartureFacts::reported('b').")")->fetchColumn();$payments+=$expenseAdjustments['paid'];$cashCosts+=$expenseAdjustments['cash'];$momoCosts+=$expenseAdjustments['momo'];$cost=$expenses+$payroll+$providers+$fees;
        $refundMethods=['cash'=>0,'momo'=>0,'unknown'=>0];foreach($r->run("SELECT x.amount_minor,(SELECT JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.method')) FROM finance_events e WHERE e.kind='refund_recorded' AND e.target_type='refund' AND e.target_id=CAST(x.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci ORDER BY e.id DESC LIMIT 1) method FROM stay_refunds x WHERE x.created_at>=? AND x.created_at<?",[$from,$to])->fetchAll() as $row)$refundMethods[$row['method']??'unknown']+=(int)$row['amount_minor'];
        return ['payroll_adjustments'=>$payrollAdjustments,'purchase_adjustments'=>$purchaseAdjustments,'charge_adjustments'=>$chargeAdjustments,'refund_methods'=>$refundMethods,'start'=>$start,'end'=>$end,'receipts'=>$receipts,'original_receipts'=>$originalReceipts,'receipt_adjustments'=>$receiptAdjustments,'room'=>$rooms,'extras'=>$extras,'incidentals'=>$incidental,'activity'=>$activity,'activity_advances'=>$advances,'cash_costs'=>$cashCosts,'momo_costs'=>$momoCosts,'cost'=>$cost,'expenses'=>$expenses,'expense_adjustments'=>$expenseAdjustments,'payroll'=>$payroll,'providers'=>$providers,'fees'=>$fees,'profit'=>$rooms+$extras+$incidental+$activity-$cost,'refunds'=>$refunds,'paid_costs'=>$payments,'unknown_departures'=>$unknown,'active_stays'=>$active,'basis'=>'Room revenue on completed stay departure, or open-period owner review for departure incidents; extras on sale; approved costs on incurred date. Active rooms and unknown historical room charges excluded. Inventory purchase costs may precede sale; this is a management estimate, not statutory accounts.'];
    }
}
