<?php
namespace Tivoli\Service;
use Tivoli\Repository\ExpenseRepository;
use Tivoli\Domain\Money\ExpenseAmount;
use RuntimeException;
use Throwable;
final class ExpenseService
{
    public readonly ExpenseRepository $repo;
    public function __construct(private readonly AccessService $access) { $this->repo=new ExpenseRepository($access->repo); }
    public function administrator(int $actor): bool
    {
        $user=$this->access->repo->user($actor);
        return $user && $user['active'] && $this->access->repo->isAdmin($actor);
    }
    public function canSubmit(int $actor): bool
    {
        $user=$this->access->repo->user($actor);
        return $user && $user['active'] && (bool)array_intersect(['expenses.submit','expenses.manage'],$this->access->repo->privileges($actor));
    }
    public function canManage(int $actor): bool { return $this->administrator($actor) && in_array('expenses.manage',$this->access->repo->privileges($actor),true); }
    public function canReview(int $actor): bool { return $this->administrator($actor) && in_array('expenses.approve',$this->access->repo->privileges($actor),true); }
    private function payload(array $input): array
    {
        foreach(['expense_date','category_id','method','amount','vendor','reference_no','description'] as $key){if(!is_string($input[$key] ?? '')){throw new RuntimeException('Invalid expense input.');}}
        if ((string)($input['id'] ?? '0')!=='0') { throw new RuntimeException('Posted expenses cannot be edited.'); }
        $date=$input['expense_date'] ?? '';$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));
        if(!$parsed || $parsed->format('Y-m-d')!==$date || $date<'1000-01-01'){throw new RuntimeException('Enter a valid expense date.');}
        $category=filter_var($input['category_id'] ?? '',FILTER_VALIDATE_INT);
        if($category===false || $category<1){throw new RuntimeException('Choose a valid category.');}
        $method=$input['method'] ?? '';if(!in_array($method,['cash','momo','card','bank','other'],true)){throw new RuntimeException('Choose a valid payment method.');}
        $data=['expense_date'=>$date,'category_id'=>$category,'method'=>$method,'amount'=>ExpenseAmount::parse(trim($input['amount'] ?? ''))];
        foreach(['vendor'=>120,'reference_no'=>80,'description'=>255] as $key=>$limit){$value=trim($input[$key] ?? '');if(!mb_check_encoding($value,'UTF-8') || mb_strlen($value,'UTF-8')>$limit || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)){throw new RuntimeException('Invalid or overly long '.$key.'.');}if(preg_match('/[\x{10000}-\x{10FFFF}]/u',$value)){throw new RuntimeException('Expense details must use characters supported by the existing ledger; remove emoji or supplementary characters.');}$data[$key]=$value;}
        return $data;
    }
    public function submit(int $actor,array $input,string $operationKey): array
    {
        try {
            if(!preg_match('/^[a-f0-9]{32}$/D',$operationKey)){throw new RuntimeException('Invalid form identity. Reload and try again.');}
            $finance=new FinanceService($this->access);if($finance->installed()&&!in_array($input['method']??'',['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo for new expenses.');
            $attachment=isset($input['_evidence'])?FinanceAttachment::checked($input['_evidence']):null;
            $data=$this->payload($input);
            $fingerprintData=$attachment?[$data,FinanceAttachment::meta($attachment)]:$data;
            if(($input['original_activity_date'] ?? '')!==''){
                $fingerprintData=['posting'=>$fingerprintData,'original_activity_date'=>$input['original_activity_date'],'late_reason'=>$input['late_reason'] ?? ''];
            }
            $fingerprint=hash('sha256',json_encode($fingerprintData,JSON_THROW_ON_ERROR));
            return $this->access->transaction(function() use($actor,$operationKey,$fingerprint,$data,$attachment,$input){
                if(!$this->canSubmit($actor)){throw new \Tivoli\Security\PermissionDenied('You do not have permission to submit expenses.');}
                $r=$this->access->repo;
                $prior=$r->run('SELECT fingerprint,result_type,result_id FROM expense_operations WHERE actor_id=? AND operation_key=?',[$actor,$operationKey])->fetch();
                if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint)){throw new RuntimeException('This form has already been used for different expense details.');}$this->access->audit($actor,'expense.submission_replayed',$prior['result_type'],(string)$prior['result_id'],'recorded');return ['type'=>$prior['result_type'],'id'=>(int)$prior['result_id'],'replayed'=>true];}
                if((new FinanceService($this->access))->installed()&&!in_array($data['method'],['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo for new expenses.');
                $finance=new FinanceService($this->access);
                $late=$finance->lateDetails($actor,$input,$data['reference_no']);
                if($late){
                    if(!$this->canManage($actor)) throw new \Tivoli\Security\PermissionDenied('Late expenses require expense management authority.');
                    $data['expense_date']=$late['posting_date'];
                    if($r->run('SELECT id FROM expenses WHERE reference_no=? AND vendor=? LIMIT 1',[$data['reference_no'],$data['vendor']])->fetchColumn()){
                        throw new RuntimeException('This vendor reference is already recorded as an expense.');
                    }
                }
                FinanceService::guardSource($this->access,$data['expense_date']);FinanceService::guardExpenseInvoice($this->access,$data);
                if(!$r->run('SELECT id FROM expense_categories WHERE id=? AND active=1 FOR UPDATE',[$data['category_id']])->fetchColumn()){throw new RuntimeException('Choose an active expense category.');}
                if($this->canManage($actor)){
                    $id=$this->post($actor,$data);$type='expense';$action='expense.recorded';
                }else{
                    $r->run('INSERT INTO expense_submissions(submitted_by,expense_date,category_id,method,amount,vendor,reference_no,description) VALUES(?,?,?,?,?,?,?,?)',[$actor,...array_values($data)]);
                    $id=(int)$r->db->lastInsertId();$type='submission';$action='expense.submitted';
                }
                if($late)$finance->recordLate($actor,'expense',$id,$late);
                if($attachment){FinanceService::claimCostEvidence($this->access,$actor,$type,$id,$attachment,\Tivoli\Domain\Stay\StayRules::minor($data['amount']),($late['original_activity_date'] ?? $data['expense_date']));FinanceAttachment::save($this->access,$actor,$type,$id,$attachment);}
                $r->run('INSERT INTO expense_operations(actor_id,operation_key,fingerprint,result_type,result_id) VALUES(?,?,?,?,?)',[$actor,$operationKey,$fingerprint,$type,$id]);
                $this->access->audit($actor,$action,$type,(string)$id,'succeeded',['after'=>$data,'counted'=>$type==='expense','status'=>$type==='expense'?'posted':'pending']);
                return ['type'=>$type,'id'=>$id,'replayed'=>false];
            });
        }catch(Throwable $error){$this->access->audit($actor,'expense.submission_failed','submission',null,'failed',['reason'=>'validation_permission_or_storage_failure']);throw $error;}
    }
    private function post(int $submittedBy,array $data): int
    {
        $r=$this->access->repo;
        $r->run('INSERT INTO expenses(expense_date,category_id,method,amount,vendor,reference_no,description,user_id) VALUES(?,?,?,?,?,?,?,?)',[...array_values($data),$submittedBy]);
        return (int)$r->db->lastInsertId();
    }
    public function review(int $actor,int $submissionId,string $decision,string $reason,array $lateInput=[]): array
    {
        try {
            $reason=trim($reason);
            if($submissionId<1 || !in_array($decision,['approved','rejected'],true) || mb_strlen($reason,'UTF-8')<5 || mb_strlen($reason,'UTF-8')>500 || !mb_check_encoding($reason,'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$reason)){throw new RuntimeException('Choose a valid decision and provide a reason of 5–500 characters.');}
            return $this->access->transaction(function() use($actor,$submissionId,$decision,$reason,$lateInput){
                if(!$this->canReview($actor)){throw new \Tivoli\Security\PermissionDenied('Only an authorized administrator can review expense submissions.');}
                $r=$this->access->repo;$submission=$r->run('SELECT * FROM expense_submissions WHERE id=? FOR UPDATE',[$submissionId])->fetch();
                if(!$submission){throw new RuntimeException('Submission not found.');}
                if((int)$submission['submitted_by']===$actor){throw new \Tivoli\Security\PermissionDenied('You cannot review your own submission.');}
                if($submission['status']!=='pending'){
                    if($submission['status']!==$decision){throw new RuntimeException('This submission has already been reviewed.');}
                    $this->access->audit($actor,'expense.review_replayed','submission',(string)$submissionId,'recorded',['status'=>$submission['status'],'original_reviewer_id'=>(int)$submission['reviewed_by']]);
                    return ['status'=>$submission['status'],'expense_id'=>$submission['expense_id']?(int)$submission['expense_id']:null,'replayed'=>true];
                }
                $data=array_intersect_key($submission,array_flip(['expense_date','category_id','method','amount','vendor','reference_no','description']));
                // Use explicit insertion order rather than relying on SELECT * column order.
                $ordered=[];foreach(['expense_date','category_id','method','amount','vendor','reference_no','description'] as $key){$ordered[$key]=$data[$key];}
                $late=null;
                $finance=new FinanceService($this->access);
                if($decision==='approved'&&($lateInput['original_activity_date']??'')!==''){
                    $late=$finance->lateDetails($actor,$lateInput,$lateInput['late_reference']??'');
                    if($late['original_activity_date']!==$ordered['expense_date'])throw new RuntimeException('The original activity date must match the submitted expense date.');
                    $ordered['expense_date']=$late['posting_date'];
                }
                if($decision==='approved'){FinanceService::guardSource($this->access,$ordered['expense_date']);FinanceService::guardExpenseInvoice($this->access,$ordered);}
                $expenseId=$decision==='approved'?$this->post((int)$submission['submitted_by'],$ordered):null;
                if($late)$finance->recordLate($actor,'expense',$expenseId,$late);
                $r->run('UPDATE expense_submissions SET status=?,reviewed_by=?,reviewed_at=UTC_TIMESTAMP(6),review_reason=?,expense_id=? WHERE id=?',[$decision,$actor,$reason,$expenseId,$submissionId]);
                $this->access->audit($actor,'expense.'.$decision,'submission',(string)$submissionId,'succeeded',['before'=>['status'=>'pending'],'after'=>['status'=>$decision,'expense_id'=>$expenseId],'reason'=>$reason,'submitted_by'=>(int)$submission['submitted_by'],'expense'=>$ordered,'counted'=>$decision==='approved']);
                return ['status'=>$decision,'expense_id'=>$expenseId,'replayed'=>false];
            });
        }catch(Throwable $error){$this->access->audit($actor,'expense.review_failed','submission',$submissionId>0?(string)$submissionId:null,'failed',['reason'=>'validation_permission_state_or_storage_failure']);throw $error;}
    }
    public function addCategory(int $actor,string $name): void
    {
        try{
            $name=trim($name);if($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name,'UTF-8')>80 || preg_match('/[\x{10000}-\x{10FFFF}]/u',$name)){throw new RuntimeException('Enter a category name of 1–80 characters.');}
            $this->access->transaction(function() use($actor,$name){if(!$this->canManage($actor)){throw new \Tivoli\Security\PermissionDenied('Only an authorized administrator can add categories.');}$r=$this->access->repo;$r->run('INSERT INTO expense_categories(name,active) VALUES(?,1)',[$name]);$this->access->audit($actor,'expense.category_created','expense_category',(string)$r->db->lastInsertId(),'succeeded',['name'=>$name]);});
        }catch(Throwable $error){$this->access->audit($actor,'expense.category_failed','expense_category',null,'failed',['reason'=>'validation_permission_or_storage_failure']);throw $error;}
    }
}