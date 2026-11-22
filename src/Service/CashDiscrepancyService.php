<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Domain\Finance\CashResolution as C;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** One source-linked case; all balance effects, audit and durable retry result commit together. */
final class CashDiscrepancyService
{
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function finance():FinanceService{return new FinanceService($this->access,$this->now());}
    public function owner(int $actor):bool{return $this->finance()->canPostLate($actor);}
    public function permit(int $actor):void{$this->finance()->permit($actor);if(!$this->finance()->installed())throw new RuntimeException('Finance setup is required.');}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid source identity.');return (int)$v;}
    public static function caseId(\Tivoli\Repository\AccessRepository $r,string $type,int $source):int{return (int)$r->run("SELECT id FROM finance_events WHERE kind='cd_reported' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_id'))=? ORDER BY id LIMIT 1",[$type,(string)$source])->fetchColumn();}
    public static function hasWalletAllocation(\Tivoli\Repository\AccessRepository $r,string $type,int $source):bool{return (bool)$r->run("SELECT id FROM finance_events WHERE kind IN ('ra_wallet_in','ra_wallet_out') AND target_type=? AND target_id=? LIMIT 1",[$type,(string)$source])->fetchColumn();}
    public static function guard(AccessService $a,string $type,int $source):void{if(self::caseId($a->repo,$type,$source)||self::hasWalletAllocation($a->repo,$type,$source))throw new RuntimeException('This source has a discrepancy case. Continue through its linked resolution workflow.');}
    public function source(int $actor,string $type,int $id):array{
        $this->permit($actor);if(!in_array($type,['deposit','payment','income','handover'],true))throw new RuntimeException('Choose a deposit, MoMo receipt or cash handover.');$r=$this->access->repo;$events=$r->run('SELECT * FROM finance_events WHERE target_type=? AND target_id=? ORDER BY id',[$type,(string)$id])->fetchAll();foreach($events as &$event){$event['data']=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$event['amount_minor']=(int)$event['amount_minor'];}unset($event);$events=array_map(fn($e)=>\Tivoli\Repository\ReceiptReallocation::event($r,$e),$events);$recipient=null;$allocations=[];$fee=0;
        if($type==='payment'){
            $row=$r->run('SELECT p.*,s.actor_id,s.reference_no FROM payments p JOIN stay_receipts s ON s.payment_id=p.id WHERE p.id=? AND p.id>(SELECT payment_cursor FROM finance_schema WHERE id=1)',[$id])->fetch();
            if(!$row)throw new RuntimeException('This receipt has no supported post-activation source evidence.');$view=\Tivoli\Repository\ReceiptAccounting::position($r,$id);if(($view['position']['method']!=='momo'||$view['position']['amount']<=0)&&!self::caseId($r,'payment',$id))throw new RuntimeException('Choose an effective MoMo receipt.');$amount=$view['position']['method']==='momo'?$view['position']['amount']:0;$origin=(int)$row['actor_id'];$reference=$view['position']['reference'];$extra=$view;
        }else{
            $row=$r->run('SELECT * FROM finance_events WHERE id=?',[$id])->fetch();if(!$row)throw new RuntimeException('Source record not found.');$row['data']=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$row=\Tivoli\Repository\ReceiptReallocation::event($r,$row);$origin=(int)$row['actor_id'];$amount=(int)$row['amount_minor'];$reference=$row['data']['reference']??'';$extra=[];
            if($type==='deposit'&&$row['kind']==='deposit_recorded'){$allocations=$row['data']['allocations'];$fee=(int)$row['data']['fee'];}
            elseif($type==='income'&&$row['kind']==='income_recorded'&&($row['data']['method']??'')==='momo'){}
            elseif($type==='handover'&&$row['kind']==='cash_transfer_sent'){$recipient=(int)$row['data']['recipient_id'];$allocations=$row['data']['allocations'];}
            else throw new RuntimeException('The source is not supported by this workflow.');
        }
        if(!$origin||(!$this->owner($actor)&&$actor!==$origin&&$actor!==$recipient))throw new PermissionDenied('Only involved staff and the designated owner may view this discrepancy.');
        $position=C::position($type,$id,$amount,$events,$allocations);$linked=array_values(array_filter($events,fn($e)=>$e['target_type']===$type&&(int)$e['target_id']===$id));$token=$this->hash([$row,$extra,$linked]);
        return $position+compact('type','id','row','amount','origin','recipient','allocations','fee','reference','token')+['current_method'=>$type==='payment'?$view['position']['method']:null];
    }
    public function detail(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND kind='cd_reported' AND target_type='cash_discrepancy'",[$id])->fetch();if(!$root)throw new RuntimeException('Discrepancy not found.');$root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$source=$this->source($actor,$root['data']['source_type'],(int)$root['data']['source_id']);
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE (e.target_type='cash_discrepancy' AND e.target_id=?) OR ((e.kind LIKE 'cd_%' OR e.kind IN ('ra_wallet_in','ra_wallet_out')) AND JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.case_id'))=?) ORDER BY e.id",[(string)$id,(string)$id])->fetchAll();$resolved=false;
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if($e['kind']==='cd_reopened')$resolved=false;if(in_array($e['kind'],['cd_credit','cd_reversed','cd_acknowledged','cd_cash_returned','cd_evidence_confirmed'],true))$resolved=true;}unset($e);
        $status=!$resolved?'review_required':($source['remaining']===0?'resolved':($source['verified']>0?'partial':'open'));$version=$this->hash([$id,$source['token'],array_column($history,'id')]);return compact('root','source','history','status','version');
    }
    public function register(int $actor,int $before=0):array{
        $this->permit($actor);$ids=$this->access->repo->run("SELECT id,payload FROM finance_events WHERE kind='cd_reported' AND (?=0 OR id<?) ORDER BY id DESC LIMIT 101",[$before,$before])->fetchAll();$rows=[];foreach($ids as $e){try{$rows[]=$this->detail($actor,(int)$e['id']);}catch(PermissionDenied $denied){}}return ['rows'=>$rows,'next'=>count($ids)===101?(int)end($ids)['id']:0];
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{$this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload this action form.');$file=null;if(isset($in['_evidence'])){$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid discrepancy form.');unset($in['csrf'],$in['action'],$in['operation_key']);ksort($in);$fp=$this->hash(['cash_discrepancy.'.$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$fp,$file){$this->permit($actor);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fp))throw new RuntimeException('This key was already used with different details.');$e=$r->run('SELECT * FROM finance_events WHERE id=?',[$old['event_id']])->fetch();$data=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$id=$e['kind']==='cd_reported'?(int)$e['id']:(int)($data['case_id']??$e['target_id']);$this->detail($actor,$id);if(!in_array($action,['report','followup','reopen','acknowledge'],true)&&!$this->owner($actor))throw new PermissionDenied('Owner authority is required.');return ['id'=>$id,'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$event]=$this->command($actor,$action,$in);if($file)FinanceAttachment::save($this->access,$actor,'cashdiscrepancy',$event,$file);$r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$event]);$this->access->audit($actor,'cash_discrepancy.'.$action,'cash_discrepancy',(string)$id,'succeeded',['event_id'=>$event]);return ['id'=>$id,'event_id'=>$event,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'cash_discrepancy.failed','cash_discrepancy',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function event(int $actor,string $kind,string $type,int $target,int $amount,array $data):int{$r=$this->access->repo;$r->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,$type,(string)$target,$amount,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$r->db->lastInsertId();}
    private function command(int $actor,string $action,array $in):array{
        $r=$this->access->repo;$reason=R::text($in['reason']??'',2000,true);
        if($action==='report'){$type=R::text($in['type']??'',12,true);$source=$this->source($actor,$type,$this->id($in['source_id']??''));if(!hash_equals($source['token'],$in['source_token']??''))throw new RuntimeException('Source evidence changed. Reload the original record.');if(self::caseId($r,$type,$source['id']))throw new RuntimeException('This source already has a discrepancy case. Open its existing history.');$category=R::text($in['category']??'',20,true);if(!in_array($category,['short','delayed','failed','reversed','disputed'],true))throw new RuntimeException('Choose the discrepancy type.');$id=$this->event($actor,'cd_reported','cash_discrepancy',0,0,['source_type'=>$type,'source_id'=>$source['id'],'source_token'=>$source['token'],'category'=>$category,'reason'=>$reason]);return [$id,$id];}
        $id=$this->id($in['id']??'');$case=$this->detail($actor,$id);if(!hash_equals($case['version'],$in['version']??''))throw new RuntimeException('This source or case changed. Review its latest evidence.');$source=$case['source'];$type=$source['type'];
        if(in_array($action,['followup','reopen'],true)){
            $data=['case_id'=>$id,'reason'=>$reason];
            if(($in['assignee_id']??'')!==''||($in['due']??'')!==''){
                if(!$this->owner($actor))throw new PermissionDenied('Only the owner assigns follow-up responsibility and dates.');
                if(($in['assignee_id']??'')!==''){$assigned=$this->id($in['assignee_id']);$person=$r->user($assigned);if(!in_array($assigned,[$source['origin'],$source['recipient'],$actor],true)||!$person||!$person['active']||!array_intersect(['bookings.manage','reports.view'],$r->privileges($assigned)))throw new RuntimeException('Choose an active involved staff member or the owner; other users cannot access this private case.');$data['assignee_id']=$assigned;$data['assignee_name']=$person['name'];}
                if(($in['due']??'')!==''){$due=R::text($in['due'],10,true);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$due);if(!$d||$d->format('Y-m-d')!==$due||$due<substr($this->now(),0,10))throw new RuntimeException('Choose a valid follow-up date no earlier than today.');$data['due']=$due;}
            }
            return [$id,$this->event($actor,$action==='reopen'?'cd_reopened':'cd_followup','cash_discrepancy',$id,0,$data)];
        }
        if($action==='acknowledge'){if($type!=='handover'||$actor!==$source['recipient'])throw new PermissionDenied('Only the named recipient may acknowledge actual cash receipt.');(new HandoverService($this->access,$this->now()))->permit($actor);}
        elseif(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may resolve this discrepancy.');
        if(($in['verified']??'')!=='1')throw new RuntimeException('Confirm the independent evidence / actual receipt.');FinanceService::guardSource($this->access,substr($this->now(),0,10));
        $reference=R::text($in['reference']??'',120,$action!=='acknowledge');if($action==='acknowledge'&&$reference==='')$reference='Named Recipient Acknowledgement';$data=['case_id'=>$id,'reason'=>$reason,'reference'=>$reference,'original_activity_date'=>substr($source['row']['created_at'],0,10)];
        if($action==='confirm_evidence'){if($source['remaining']!==0)throw new RuntimeException('The unexplained residual must remain open.');return [$id,$this->event($actor,'cd_evidence_confirmed',$type,$source['id'],0,$data)];}
        $amount=R::minor(R::text($in['amount']??'',24,true));if($amount<=0||$amount>999999999999)throw new RuntimeException('Enter a positive supported amount.');if($source['fee']!==0)throw new RuntimeException('Existing deposit fees require separate evidenced reconciliation before partial or reversal posting.');
        $kind=match($action){'credit'=>'cd_credit','reverse'=>'cd_reversed','return_cash'=>'cd_cash_returned','acknowledge'=>'cd_acknowledged',default=>throw new RuntimeException('Unknown discrepancy action.')};
        if($action==='credit'&&$type==='handover')throw new RuntimeException('The named recipient must acknowledge cash.');if($action==='reverse'&&$type==='handover')throw new RuntimeException('A settled handover needs separately evidenced custody reconciliation.');if($action==='return_cash'&&!in_array($type,['deposit','handover'],true))throw new RuntimeException('A MoMo receipt cannot return physical cash.');
        $limit=$action==='reverse'?$source['verified']:$source['remaining'];if($amount>$limit)throw new RuntimeException('Amount exceeds the supported remaining balance.');
        if($source['allocations'])$data['allocations']=C::pick($action==='reverse'?$source['credits']:$source['available'],$amount);
        if($action==='return_cash'&&$type==='deposit'){
            $parts=$source['row']['data']['custody_allocations']??null;if(!$parts)throw new RuntimeException('Original custody is unknown; establish evidence before returning cash.');$returned=[];foreach(\Tivoli\Repository\ReceiptReallocation::events($this->finance()->events()) as $event)if($event['kind']==='cd_cash_returned'&&$event['target_type']==='deposit'&&(int)$event['target_id']===$source['id'])foreach($event['data']['custody_parts'] as $receipt=>$actors)foreach($actors as $custodian=>$part)$returned[$receipt][$custodian]=($returned[$receipt][$custodian]??0)+(int)$part;
            foreach($data['allocations'] as $receipt=>$part){$limits=[];if(count($parts[$receipt]??[])!==1)throw new RuntimeException('Multiple original custodians require explicit recipient reconciliation; do not guess who received returned cash.');foreach($parts[$receipt]??[] as $custodian=>$original){if(!(int)$custodian||!$r->user((int)$custodian))throw new RuntimeException('Original cash custodian is unsupported.');$limits[$custodian]=(int)$original-($returned[$receipt][$custodian]??0);}$data['custody_parts'][$receipt]=C::pick($limits,$part);}
        }
        $existingClaim=$action==='credit'?$this->claimCredit($type,$source,$reference):false;
        $event=$this->event($actor,$kind,$type,$source['id'],$amount,$data);
        if($action==='credit'&&!$existingClaim)$r->run('INSERT INTO finance_claims VALUES(?,?,?)',['business_momo',hash('sha256',mb_strtolower(trim($reference),'UTF-8')),$event]);
        return [$id,$event];
    }
    private function existingOwnDepositClaim(array $source,string $reference):bool{return $source['type']==='deposit'&&mb_strtolower(trim($source['reference']),'UTF-8')===mb_strtolower(trim($reference),'UTF-8');}
    private function claimCredit(string $type,array $source,string $ref):bool{
        $r=$this->access->repo;$normalized=mb_strtolower(trim($ref),'UTF-8');$hash=hash('sha256',$normalized);$claimed=$r->run("SELECT event_id FROM finance_claims WHERE scope='business_momo' AND identity_hash=?",[$hash])->fetchColumn();if($claimed&&!($this->existingOwnDepositClaim($source,$ref)&&(int)$claimed===$source['id']))throw new RuntimeException('This wallet reference is already claimed.');
        if($r->run("SELECT id FROM finance_events WHERE kind='cd_credit' AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=? LIMIT 1",[$normalized])->fetchColumn())throw new RuntimeException('This wallet credit evidence was already used.');
        if($this->existingOwnDepositClaim($source,$ref)&&$r->run("SELECT id FROM finance_events WHERE kind='deposit_confirmed' AND target_type='deposit' AND target_id=? LIMIT 1",[(string)$source['id']])->fetchColumn())throw new RuntimeException('The original wallet credit was already confirmed; retain it and evidence a distinct subsequent credit.');
        \Tivoli\Repository\ReceiptAccounting::guardAlias($r,$ref,$type==='payment'?$source['id']:null);\Tivoli\Repository\ExpenseAccounting::guardAlias($r,$ref);
        if($r->run("SELECT p.id FROM payments p JOIN stay_receipts s ON s.payment_id=p.id WHERE p.method='momo' AND LOWER(TRIM(s.reference_no))=? AND p.id<>? LIMIT 1",[$normalized,$type==='payment'?$source['id']:0])->fetchColumn())throw new RuntimeException('This reference identifies another receipt.');
        if($r->run("SELECT id FROM finance_events WHERE kind='income_recorded' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.method'))='momo' AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=? AND id<>? LIMIT 1",[$normalized,$type==='income'?$source['id']:0])->fetchColumn())throw new RuntimeException('This reference identifies another activity receipt.');
        return (bool)$claimed;
    }
}
