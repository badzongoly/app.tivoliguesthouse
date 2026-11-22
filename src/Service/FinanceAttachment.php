<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Infrastructure\HrVault;
use Tivoli\Security\PermissionDenied;

/** Private encrypted evidence; only a committed audit registry entry grants access. */
final class FinanceAttachment
{
    public static function validate(string $bytes,string $name):array{
        if(strlen($bytes)<1||strlen($bytes)>2*1024*1024)throw new RuntimeException('Evidence must be a non-empty PDF, JPEG or PNG file up to 2 MB.');
        $allowed=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!isset($allowed[$ext])||$mime!==$allowed[$ext])throw new RuntimeException('Evidence content must match its PDF, JPEG or PNG filename.');
        if($mime==='application/pdf'){if(!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Invalid PDF evidence.');}
        elseif(!($image=@getimagesizefromstring($bytes))||$image['mime']!==$mime)throw new RuntimeException('Invalid image evidence.');
        return ['bytes'=>$bytes,'mime'=>$mime,'size'=>strlen($bytes),'sha'=>hash('sha256',$bytes)];
    }
    public static function uploaded(array $f):array{
        if(($f['error']??null)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_string($f['name']??null)||!is_uploaded_file($f['tmp_name']))throw new RuntimeException('Evidence upload failed. Select the file again (PDF, JPEG or PNG, at most 2 MB).');
        $bytes=file_get_contents($f['tmp_name'],false,null,0,2*1024*1024+1);if($bytes===false)throw new RuntimeException('Could not read uploaded evidence.');return self::validate($bytes,$f['name']);
    }
    public static function checked(array $f):array{$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];if(!isset($extensions[$f['mime']??''])||!is_string($f['bytes']??null))throw new RuntimeException('Invalid evidence.');return self::validate($f['bytes'],'evidence.'.$extensions[$f['mime']]);}
    public static function meta(array $f):array{return array_intersect_key(self::checked($f),array_flip(['mime','size','sha']));}
    public static function directory():string{return dirname(__DIR__,2).'/storage/private/'.(getenv('TIVOLI_TEST_MODE')==='1'?'finance-evidence-test':'finance-evidence');}
    public static function save(AccessService $a,int $actor,string $kind,int $id,array $file):void{
        if(!in_array($kind,['expense','submission','provider','incident','purchase','stockcount','stockissue','housekeeping','payroll','correction','expensecorrection','cashdiscrepancy','guestcharge','purchasecorrection'],true)||!$a->repo->db->inTransaction())throw new RuntimeException('Invalid evidence transaction.');$f=self::checked($file);
        require_once dirname(__DIR__,2).'/bootstrap/hr.php';$key=bin2hex(random_bytes(32));$dir=self::directory();if((!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))||is_link($dir))throw new RuntimeException('Private evidence storage unavailable.');
        $vault=new HrVault(\hr_config()['hr_key']);$cipher=$vault->seal($f['bytes'],'finance-evidence:'.$kind.':'.$id.':'.$key);$path=$dir.'/'.$key.'.enc';$stream=fopen($path,'xb');if(!$stream)throw new RuntimeException('Could not save private evidence.');try{if(fwrite($stream,$cipher)!==strlen($cipher)||!fflush($stream))throw new RuntimeException('Could not save private evidence.');}finally{fclose($stream);}@chmod($path,0600);
        $a->audit($actor,'finance.evidence_saved','finance_'.$kind,(string)$id,'succeeded',['storage_key'=>$key,'mime'=>$f['mime'],'size'=>$f['size'],'sha'=>$f['sha']]);
    }
    public static function available(AccessService $a,string $kind,int $id):bool{
        if(!in_array($kind,['expense','submission','provider','incident','purchase','stockcount','stockissue','housekeeping','payroll','correction','expensecorrection','cashdiscrepancy','guestcharge','purchasecorrection'],true))return false;
        if($a->repo->run("SELECT id FROM security_audit WHERE action='finance.evidence_saved' AND target_type=? AND target_id=? AND outcome='succeeded' LIMIT 1",['finance_'.$kind,(string)$id])->fetchColumn())return true;
        if($kind==='expense'){$submission=$a->repo->run('SELECT id FROM expense_submissions WHERE expense_id=?',[$id])->fetchColumn();return $submission&&self::available($a,'submission',(int)$submission);}return false;
    }
    public static function download(AccessService $a,int $actor,string $kind,int $id):array{
        try{return $a->transaction(function()use($a,$actor,$kind,$id){
            if(!in_array($kind,['expense','submission','provider','incident','purchase','stockcount','stockissue','housekeeping','payroll','correction','expensecorrection','cashdiscrepancy','guestcharge','purchasecorrection'],true)||$id<1)throw new RuntimeException('Invalid evidence request.');$r=$a->repo;$expenses=new ExpenseService($a);
            if($kind==='purchasecorrection'){$event=$r->run("SELECT id,kind,target_type,target_id,payload FROM finance_events WHERE id=? AND kind LIKE 'pc_%'",[$id])->fetch();if(!$event)throw new PermissionDenied('Linked correction evidence unavailable.');$service=new PurchaseCorrectionService($a);if($event['target_type']==='purchase_source'){$data=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$service->source($actor,$data['source_type'],(int)$data['source_id']);}else $service->detail($actor,$event['kind']==='pc_requested'?(int)$event['id']:(int)$event['target_id']);}
            elseif($kind==='guestcharge'){$event=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='guest_charge'",[$id])->fetch();if(!$event)throw new PermissionDenied('Charge evidence unavailable.');(new GuestChargeCorrectionService($a))->detail($actor,$event['kind']==='gc_requested'?(int)$event['id']:(int)$event['target_id']);}
            elseif($kind==='cashdiscrepancy'){
                $event=$r->run("SELECT id,kind,payload FROM finance_events WHERE id=? AND kind LIKE 'cd_%'",[$id])->fetch();if(!$event)throw new PermissionDenied('Discrepancy evidence unavailable.');$data=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);(new CashDiscrepancyService($a))->detail($actor,$event['kind']==='cd_reported'?(int)$event['id']:(int)$data['case_id']);
            }
            elseif($kind==='expensecorrection'){
                $event=$r->run("SELECT id,target_id,kind FROM finance_events WHERE id=? AND target_type='expense_correction'",[$id])->fetch();if(!$event)throw new PermissionDenied('Expense correction evidence unavailable.');
                (new ExpenseCorrectionService($a))->detail($actor,$event['kind']==='ec_requested'?(int)$event['id']:(int)$event['target_id']);
            }
            else if($kind==='correction'){
                $event=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='receipt_correction'",[$id])->fetch();if(!$event)throw new PermissionDenied('Correction evidence unavailable.');
                (new ReceiptCorrectionService($a))->detail($actor,$event['kind']==='rc_requested'?(int)$event['id']:(int)$event['target_id']);
            }
            elseif($kind==='payroll'){
                $event=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='payroll'",[$id])->fetch();if(!$event)throw new PermissionDenied('Payroll evidence unavailable.');
                (new PayrollService($a))->detail($actor,$event['kind']==='payroll_draft'?(int)$event['id']:(int)$event['target_id']);
            }
            elseif($kind==='housekeeping'){
                $event=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='housekeeping'",[$id])->fetch();if(!$event)throw new PermissionDenied('You cannot access this room evidence.');
                (new HousekeepingService($a))->detail($actor,$event['kind']==='hk_started'?(int)$event['id']:(int)$event['target_id']);
            }
            elseif($kind==='stockissue'){$event=$a->repo->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='stock_issue'",[$id])->fetch();if(!$event)throw new PermissionDenied('Stock issue evidence unavailable.');(new StockIssueService($a))->detail($actor,$event['kind']==='si_reported'?(int)$event['id']:(int)$event['target_id']);}
            elseif($kind==='stockcount'){
                $event=$r->run("SELECT id,target_id,kind FROM finance_events WHERE id=? AND target_type='stock_count'",[$id])->fetch();if(!$event)throw new PermissionDenied('You cannot access this stock count evidence.');
                $count=$event['kind']==='count_started'?(int)$event['id']:(int)$event['target_id'];(new StockCountService($a))->detail($actor,$count);
            }
            elseif($kind==='purchase'){
                $event=$r->run("SELECT id,target_id,kind FROM finance_events WHERE id=? AND target_type='purchase'",[$id])->fetch();if(!$event)throw new PermissionDenied('You cannot access this purchase evidence.');
                $purchase=in_array($event['kind'],['purchase_requested','purchase_unapproved'],true)?(int)$event['id']:(int)$event['target_id'];(new PurchaseService($a))->detail($actor,$purchase);
            }
            elseif($kind==='incident'){
                $a->requirePrivilege($actor,'bookings.manage');
                if(!(new FinanceService($a))->canPostLate($actor)||!$r->run("SELECT id FROM finance_events WHERE id=? AND kind='departure_reported'",[$id])->fetchColumn())throw new PermissionDenied('Only the finance owner can access departure evidence.');
            }
            elseif($kind==='provider'){$s=new FinanceService($a);$s->permit($actor);$e=$r->run("SELECT actor_id FROM finance_events WHERE id=? AND kind='provider_requested'",[$id])->fetch();if(!$e||(!$s->isAdmin($actor)&&(int)$e['actor_id']!==$actor))throw new PermissionDenied('You cannot access this evidence.');}
            elseif($kind==='expense'){if((!$expenses->canManage($actor)&&!$expenses->canReview($actor))||!$r->run('SELECT id FROM expenses WHERE id=?',[$id])->fetchColumn())throw new PermissionDenied('You cannot access this evidence.');}
            else{$row=$r->run('SELECT submitted_by FROM expense_submissions WHERE id=?',[$id])->fetch();if(!$row||(!$expenses->canManage($actor)&&!$expenses->canReview($actor)&&(! $expenses->canSubmit($actor)||(int)$row['submitted_by']!==$actor)))throw new PermissionDenied('You cannot access this evidence.');}
            $meta=$r->run("SELECT details FROM security_audit WHERE action='finance.evidence_saved' AND target_type=? AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",['finance_'.$kind,(string)$id])->fetchColumn();
            if(!$meta&&$kind==='expense'){$sub=$r->run('SELECT id FROM expense_submissions WHERE expense_id=?',[$id])->fetchColumn();if($sub){$kind='submission';$id=(int)$sub;$meta=$r->run("SELECT details FROM security_audit WHERE action='finance.evidence_saved' AND target_type='finance_submission' AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();}}
            if(!$meta)throw new RuntimeException('No evidence file is recorded.');$m=json_decode($meta,true,512,JSON_THROW_ON_ERROR);$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$m['mime']??'']??null;
            if(!$ext||!preg_match('/^[a-f0-9]{64}$/D',$m['storage_key']??''))throw new RuntimeException('Evidence unavailable.');$path=self::directory().'/'.$m['storage_key'].'.enc';if(!is_file($path)||is_link($path)||is_link(self::directory()))throw new RuntimeException('Evidence unavailable.');
            require_once dirname(__DIR__,2).'/bootstrap/hr.php';$vault=new HrVault(\hr_config()['hr_key']);$cipher=file_get_contents($path,false,null,0,4*1024*1024);if($cipher===false)throw new RuntimeException('Evidence unavailable.');$bytes=$vault->open($cipher,'finance-evidence:'.$kind.':'.$id.':'.$m['storage_key']);
            if(strlen($bytes)!==(int)$m['size']||!hash_equals($m['sha'],hash('sha256',$bytes)))throw new RuntimeException('Evidence integrity check failed.');$a->audit($actor,'finance.evidence_downloaded','finance_'.$kind,(string)$id,'succeeded');return ['bytes'=>$bytes,'mime'=>$m['mime'],'filename'=>'evidence-'.$kind.'-'.$id.'.'.$ext];
        });}catch(\Throwable $e){$a->audit($actor,'finance.evidence_denied','finance_evidence',null,'denied',['kind'=>in_array($kind,['expense','submission','provider','incident','purchase','stockcount','stockissue','housekeeping','payroll','correction','expensecorrection','cashdiscrepancy','guestcharge','purchasecorrection'],true)?$kind:'invalid']);throw $e;}
    }
}
