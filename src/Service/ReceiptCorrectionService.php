<?php
namespace Tivoli\Service;
use Tivoli\Repository\ReceiptAccounting as A;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

final class ReceiptCorrectionService
{
    use ReceiptCorrectionPosting;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function today():string{return substr($this->now(),0,10);}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid receipt or correction.');return (int)$v;}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor)&&in_array('bookings.manage',$this->access->repo->privileges($actor),true);}
    public function permit(int $actor,bool $write=false):void{
        $r=$this->access->repo;$u=$r->user($actor);
        if(!$u||!$u['active']||!in_array('bookings.manage',$r->privileges($actor),true))throw new PermissionDenied('You cannot access guest receipt corrections.');
        if(!A::installed($r))throw new RuntimeException('Complete the existing finance setup before using corrections.');
        if($write&&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,$this->today()])->fetchColumn())throw new PermissionDenied('This account cannot make operational corrections.');
    }
    private function event(int $actor,string $kind,array $data,int $root=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'receipt_correction',(string)$root,0,$this->today(),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$this->access->repo->db->lastInsertId();
    }
    public function source(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$v=A::position($r,$id);$p=$v['original'];$position=$v['position'];
        if(!$this->owner($actor)&&(int)$p['actor_id']!==$actor)throw new PermissionDenied('Only your own authorized receipt records are available.');
        $booking=$r->run('SELECT b.*,r.name room_name FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id=?',[$p['booking_id']])->fetch();if(!$booking)throw new RuntimeException('Receipt stay is unavailable.');
        $bill=(new StayService($this->access,$this->now()))->bill($booking);$refundRows=$r->run('SELECT id,amount_minor FROM stay_refunds WHERE payment_id=? ORDER BY id',[$id])->fetchAll();$refunded=array_sum(array_map(fn($x)=>(int)$x['amount_minor'],$refundRows));
        $eventView=\Tivoli\Repository\ReceiptReallocation::events((new FinanceService($this->access,$this->now()))->events());$dependencies=ReceiptReconciliation::dependencies($eventView,$id);$blockers=[];
        if(!in_array($position['method'],['cash','momo'],true))$blockers[]='This historical payment method needs a separate evidenced correction design.';
        if(!$p['created_at'])$blockers[]='The original activity time is unknown; do not invent a historical date.';
        if($bill['legacy_unknown']||$bill['provisional'])$blockers[]='Finalize the evidenced guest bill before financial correction.';
        $collection=A::collection($r,$id);if($collection!==$id)$blockers[]='This receipt was compensated as a duplicate. Use retained collection receipt #'.$collection.'; an incorrect duplicate identification requires separate reconciliation.';
        $cursor=(int)$r->run('SELECT payment_cursor FROM finance_schema WHERE id=1')->fetchColumn();
        if($position['method']==='cash'&&(!$p['actor_id']||$id<=$cursor))$blockers[]='Historical cash custody is unknown; separate reconciliation is required.';
        $originalDate=$p['created_at']?(new FinanceService($this->access))->originalReceiptDate($p,$booking['checkin_at']):null;
        $token=$this->hash([$p,$position,$booking,$bill,$refundRows,$dependencies]);
        $keepers=$r->run('SELECT p.id,p.method,p.amount,s.reference_no FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id WHERE p.booking_id=? AND p.id<>? ORDER BY p.id',[$booking['id'],$id])->fetchAll();
        return ['original'=>$p,'position'=>$position,'booking'=>$booking,'bill'=>$bill,'refunded'=>$refunded,'blockers'=>array_values(array_unique($blockers)),'dependencies'=>$dependencies,'wallet_verified'=>$position['method']==='momo'?\Tivoli\Domain\Finance\CashResolution::position('payment',$id,$position['amount'],$eventView)['verified']:0,'token'=>$token,'original_date'=>$originalDate,'original_locked'=>$originalDate?(new FinanceService($this->access))->periodLocked($originalDate):false,'keepers'=>A::project($r,$keepers),'collection_id'=>$collection];
    }
    private function load(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind='rc_requested' AND e.target_type='receipt_correction'",[$id])->fetch();
        if(!$root)throw new RuntimeException('Correction request not found.');
        if(!$this->owner($actor)&&(int)$root['actor_id']!==$actor)throw new PermissionDenied('Only the requester and designated owner may view this correction.');
        $root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='receipt_correction' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        $proposal=$root['data']['proposal'];$revisions=[$root];$status='pending';$posted=null;
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);switch($e['kind']){case 'rc_revised':$proposal=$e['data']['proposal'];$revisions[]=$e;$status='pending';break;case 'rc_returned':$status='returned';break;case 'rc_rejected':$status='rejected';break;case 'rc_withdrawn':$status='withdrawn';break;case 'rc_approved':$status='posted';$posted=$e['data'];break;}}unset($e);
        return compact('root','history','proposal','revisions','status','posted')+['version'=>$this->hash([$id,array_column($history,'id')])];
    }
    public function detail(int $actor,int $id):array{
        $c=$this->load($actor,$id);$c['live']=$this->source($actor,(int)$c['proposal']['payment_id']);$c['stale']=!hash_equals($c['proposal']['source_token'],$c['live']['token']);return $c;
    }
    private function active(int $payment,?int $except=null):bool{
        $r=$this->access->repo;$ids=$r->run("SELECT id FROM finance_events WHERE kind='rc_requested' AND target_type='receipt_correction' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payment_id'))=?",[(string)$payment])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){if((int)$id===$except)continue;$last=$r->run("SELECT kind FROM finance_events WHERE target_type='receipt_correction' AND target_id=? AND kind IN ('rc_approved','rc_rejected','rc_withdrawn') ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();if(!$last)return true;}return false;
    }
    public function register(int $actor,string $page='requests',int $before=0):array{
        $this->permit($actor);if($page==='review'&&!$this->owner($actor))throw new PermissionDenied('Only the designated owner opens the correction review queue.');
        $where='';$params=[];if(!$this->owner($actor)){$where.=' AND actor_id=?';$params[]=$actor;}if($before){$where.=' AND id<?';$params[]=$before;}
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE kind='rc_requested' AND target_type='receipt_correction'".$where.' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if($page==='review'&&$c['status']!=='pending'||$page==='log'&&!in_array($c['status'],['posted','rejected','withdrawn'],true))continue;$rows[]=$c;}
        return ['rows'=>$rows,'next'=>$more?(int)end($ids):0];
    }
    public function receipts(int $actor,int $before=0):array{
        $this->permit($actor);$where='';$params=[];if(!$this->owner($actor)){$where.=' AND s.actor_id=?';$params[]=$actor;}if($before){$where.=' AND p.id<?';$params[]=$before;}
        $rows=$this->access->repo->run('SELECT p.*,s.actor_id,u.name actor_name,b.guest_name,r.name room_name FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id LEFT JOIN users u ON u.id=s.actor_id JOIN bookings b ON b.id=p.booking_id JOIN rooms r ON r.id=b.room_id WHERE 1=1'.$where.' ORDER BY p.id DESC LIMIT 51',$params)->fetchAll();$more=count($rows)>50;if($more)array_pop($rows);foreach($rows as &$p)$p['position']=A::position($this->access->repo,(int)$p['id'])['position'];unset($p);return ['rows'=>$rows,'next'=>$more?(int)end($rows)['id']:0];
    }
    public function attentionCount(int $actor):int{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Owner review is restricted.');return (int)$this->access->repo->run("SELECT COUNT(*) FROM finance_events r WHERE r.kind='rc_requested' AND r.target_type='receipt_correction' AND NOT EXISTS(SELECT 1 FROM finance_events e WHERE e.target_type='receipt_correction' AND e.target_id=CAST(r.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND e.kind IN ('rc_approved','rc_rejected','rc_withdrawn'))")->fetchColumn();
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor,true);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the correction form.');
            $file=null;if(isset($in['_evidence'])){if(!in_array($action,['request','revise'],true))throw new RuntimeException('Attach evidence to the request or its revision.');$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid correction form.');
            unset($in['csrf'],$in['action'],$in['operation_key']);ksort($in);$fp=$this->hash([$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$file,$fp){
                $this->permit($actor,true);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($fp,$old['fingerprint']))throw new RuntimeException('This form was used for different details.');$e=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='receipt_correction'",[$old['event_id']])->fetch();if(!$e)throw new RuntimeException('This form belongs to another workflow.');$id=$e['kind']==='rc_requested'?(int)$e['id']:(int)$e['target_id'];$this->load($actor,$id);$this->access->audit($actor,'receipt_correction.replayed','receipt_correction',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$event]=$this->command($actor,$action,$in,$file);if($file)FinanceAttachment::save($this->access,$actor,'correction',$event,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$event]);$this->access->audit($actor,'receipt_correction.'.$action,'receipt_correction',(string)$id,'succeeded',['event_id'=>$event]);return ['id'=>$id,'event_id'=>$event,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'receipt_correction.failed','receipt_correction',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in,?array $file):array{
        if($action==='request'){
            $payment=$this->id($in['payment_id']??'');if($this->active($payment))throw new RuntimeException('This receipt already has an active correction request.');
            $p=$this->proposal($actor,$payment,$in,$file);$id=$this->event($actor,'rc_requested',['payment_id'=>$payment,'booking_id'=>$p['booking_id'],'proposal'=>$p]);return [$id,$id];
        }
        $id=$this->id($in['id']??'');$c=$this->load($actor,$id);if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This correction changed. Reload its current history.');
        if($action==='revise'){
            if((int)$c['root']['actor_id']!==$actor||$c['status']!=='returned')throw new PermissionDenied('Only the requester may revise a returned case.');
            $p=$this->proposal($actor,(int)$c['proposal']['payment_id'],$in,$file);return [$id,$this->event($actor,'rc_revised',['proposal'=>$p],$id)];
        }
        if($action==='withdraw'){
            if((int)$c['root']['actor_id']!==$actor||!in_array($c['status'],['pending','returned'],true))throw new PermissionDenied('Only the requester may withdraw an unposted case.');
            return [$id,$this->event($actor,'rc_withdrawn',[],$id)];
        }
        if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may decide or post corrections.');
        if($c['status']!=='pending')throw new RuntimeException('Only a pending correction may receive an owner decision.');
        if(in_array($action,['return','reject'],true))return [$id,$this->event($actor,$action==='return'?'rc_returned':'rc_rejected',['reason'=>R::text($in['reason']??'',2000,true)],$id)];
        if($action==='post')return $this->postCorrection($actor,$id,$c,$in);
        throw new RuntimeException('Unknown correction action.');
    }
    public static function captureSnapshot(AccessService $a,int $actor,int $booking,string $at):void{
        $cursor=A::cursor($a->repo,$booking);if(!$cursor)return;if(!$a->repo->db->inTransaction())throw new RuntimeException('Receipt snapshot requires the departure transaction.');
        $a->repo->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('rc_snapshot','booking',?,0,?,?,?,?)",[(string)$booking,substr($at,0,10),$actor,json_encode(['cursor'=>$cursor],JSON_THROW_ON_ERROR),$at]);
        $a->audit($actor,'receipt_correction.snapshot','booking',(string)$booking,'recorded',['cursor'=>$cursor]);
    }
}
