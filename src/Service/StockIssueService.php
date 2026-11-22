<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Repository\StockAvailability;
use Tivoli\Security\PermissionDenied;
use RuntimeException;

/** Reports reserve quantity; owner-verified actual disposition appends one physical outflow. */
final class StockIssueService
{
    public const KINDS=['damage'=>'Damage','expiry'=>'Expiry','missing'=>'Missing Stock','complimentary'=>'Complimentary Use'];
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    public function permit(int $actor):void{(new StockCountService($this->access,$this->now()))->permit($actor);}
    public function owner(int $actor):bool{return (new StockCountService($this->access,$this->now()))->owner($actor)&&in_array('stock.manage',$this->access->repo->privileges($actor),true);}
    private function id(mixed $value):int{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,17}$/D',$value))throw new RuntimeException('Choose a valid stock item or issue.');return (int)$value;}
    private function actual(mixed $value):string{
        $value=str_replace(' ','T',R::text($value,19,true));if(strlen($value)===16)$value.=':00';$d=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s',$value,new \DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d\TH:i:s')!==$value||$d->format('Y-m-d H:i:s')>$this->now())throw new RuntimeException('Enter a valid actual UTC time no later than now.');return $d->format('Y-m-d H:i:s');
    }
    private function event(int $actor,string $kind,array $data,int $id=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'stock_issue',(string)$id,0,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$this->access->repo->db->lastInsertId();
    }
    public function position(int $extra):array{
        $r=$this->access->repo;$x=$r->run('SELECT e.id,e.name,e.active,e.stock_qty,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.id=?',[$extra])->fetch();if(!$x)throw new RuntimeException('Stock item not found.');$h=StockAvailability::hold($r,$extra);$raw=R::quantity((string)$x['stock_qty']);$rows=$r->run('SELECT * FROM stock_movements WHERE extra_id=? ORDER BY id',[$extra])->fetchAll();return ['item'=>$x,'on_hand'=>$raw,'held'=>$h['held'],'available'=>$raw-$h['held'],'token'=>$this->hash([$x,$rows,$h])];
    }
    public function options(int $actor):array{
        $this->permit($actor);$r=$this->access->repo;$rows=$r->run('SELECT e.id,e.name,e.active,e.stock_qty,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE COALESCE(s.tracked,1)=1 ORDER BY e.name,e.id')->fetchAll();
        $affected=array_flip($r->run("SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.extra_id')) FROM finance_events WHERE target_type='stock_issue' AND kind='si_reported'")->fetchAll(\PDO::FETCH_COLUMN));$options=[];
        foreach($rows as $x){$raw=R::quantity((string)$x['stock_qty']);$held=isset($affected[$x['id']])?StockAvailability::hold($r,(int)$x['id'])['held']:0;$options[]=['item'=>$x,'on_hand'=>$raw,'held'=>$held,'available'=>$raw-$held];}return $options;
    }
    private function countHash(int $id,int $extra):string{
        $r=$this->access->repo;return $this->hash([$r->run("SELECT * FROM finance_events WHERE target_type='stock_count' AND (id=? OR target_id=?) ORDER BY id",[$id,(string)$id])->fetchAll(),$r->run("SELECT * FROM stock_movements WHERE ref_type='stock_count' AND ref_id=? AND extra_id=? ORDER BY id",[$id,$extra])->fetchAll()]);
    }
    private function guardCountsForDisposition(array $d,int $id):void{if(!$d['count_id'])$this->guardCounts($d['extra_id'],$d['actual_at'],$id,$d['qty']);}
    private function guardCountsForReport(string $position,int $extra,string $actual):void{if($position!=='counted')$this->guardCounts($extra,$actual);}
    private function guardCounts(int $extra,string $actual,int $issue=0,int $qty=0):void{
        $path='$.lines."'.$extra.'"';
        foreach($this->access->repo->run("SELECT payload FROM finance_events WHERE kind='count_approved' AND target_type='stock_count' AND JSON_EXTRACT(payload,?) IS NOT NULL",[$path])->fetchAll() as $event){
            $line=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR)['lines'][$extra];
            if(!isset($line['counted_at'])||($line['counted_at']>=$actual&&(!$issue||($line['held_issues'][$issue]??0)<$qty)))throw new RuntimeException('An approved count may already include this loss. Link its exact evidenced count loss or obtain source review; no second deduction is allowed.');
        }
    }
    private function countEvidence(int $actor,int $id,int $extra,string $kind,int $qty,string $actual):string{
        $c=(new StockCountService($this->access,$this->now()))->detail($actor,$id);$l=$c['decision']['data']['lines'][$extra]??null;$reason=match($kind){'damage'=>'Damage','expiry'=>'Expiry','missing'=>'Missing / Unexplained',default=>''};
        if($c['status']!=='approved'||!$l||$reason===''||$l['reason']!==$reason||$l['difference']!==-$qty||$l['after']!==$l['before']-$qty||$actual>$l['counted_at'])throw new RuntimeException('Link the exact evidenced loss from an approved matching count.');
        $m=$this->access->repo->run('SELECT * FROM stock_movements WHERE id=?',[$l['movement_id']])->fetch();
        if(!$m||(int)$m['extra_id']!==$extra||$m['movement_type']!=='adjustment'||(string)$m['qty']!==R::decimal(-$qty)||$m['ref_type']!=='stock_count'||(int)$m['ref_id']!==$id||(int)$m['created_by']!==(int)$c['decision']['actor_id']||$m['created_at']!==$c['decision']['created_at'])throw new RuntimeException('Original count movement evidence is unsupported.');
        return $this->countHash($id,$extra);
    }
    public function countOptions(int $actor,int $extra):array{
        $this->permit($actor);$rows=[];$r=$this->access->repo;$where=$this->owner($actor)?'':' AND actor_id=?';$ids=$r->run("SELECT id FROM finance_events WHERE target_type='stock_count' AND kind='count_started'".$where.' ORDER BY id DESC LIMIT 100',$where?[$actor]:[])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){$c=(new StockCountService($this->access,$this->now()))->detail($actor,(int)$id);$l=$c['decision']['data']['lines'][$extra]??null;if($c['status']!=='approved'||!$l||$l['difference']>=0||!in_array($l['reason'],['Damage','Expiry','Missing / Unexplained'],true))continue;$hash=$this->hash([(int)$id,$extra]);if($r->run("SELECT event_id FROM finance_claims WHERE scope='stock_issue_count' AND identity_hash=?",[$hash])->fetchColumn())continue;$rows[]=['id'=>(int)$id,'qty'=>-$l['difference'],'reason'=>$l['reason'],'at'=>$l['counted_at']];}return $rows;
    }
    public function detail(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind='si_reported' AND e.target_type='stock_issue'",[$id])->fetch();if(!$root)throw new RuntimeException('Stock issue not found.');$root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='stock_issue' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();$status='pending';$approval=null;$completion=null;$qty=$root['data']['qty'];
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);switch($e['kind']){case 'si_corrected':$qty=$e['data']['qty_after'];$status=$qty?'pending':'cancelled';$approval=null;break;case 'si_returned':$status='returned';break;case 'si_clarified':$status='pending';break;case 'si_approved':$status='approved';$approval=$e;break;case 'si_completed':$status='completed';$completion=$e;break;}}unset($e);
        $p=$this->position((int)$root['data']['extra_id']);$version=$this->hash([$root,array_column($history,'id')]);$deps=$root['data']['count_id']?$this->countHash($root['data']['count_id'],$root['data']['extra_id']):null;
        return ['qty'=>$qty,'root'=>$root,'history'=>$history,'status'=>$status,'approval'=>$approval,'completion'=>$completion,'position'=>$p,'version'=>$version,'review_token'=>$this->hash([$version,$p['token'],$deps])];
    }
    public function register(int $actor,int $before=0):array{
        $this->permit($actor);$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='stock_issue' AND kind='si_reported'".($before?' AND id<?':'').' ORDER BY id DESC LIMIT 51',$before?[$before]:[])->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);return ['rows'=>array_map(fn($id)=>$this->detail($actor,(int)$id),$ids),'next'=>$more?(int)end($ids):0];
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the action form.');if(in_array($action,['approve','return','dispose','correct_report'],true)&&!$this->owner($actor))throw new PermissionDenied('Only the designated owner with stock authority may decide or dispose stock.');
            $file=null;if(isset($in['_evidence'])){if(!in_array($action,['report','clarify'],true))throw new RuntimeException('Attach evidence to a report or clarification.');$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid stock issue form.');unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);$fp=$this->hash(['stockissue.'.$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$fp,$file){
                $this->permit($actor);if(in_array($action,['approve','return','dispose','correct_report'],true)&&!$this->owner($actor))throw new PermissionDenied('Owner stock authority was revoked.');$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fp))throw new RuntimeException('This key belongs to different details.');$ev=$r->run("SELECT * FROM finance_events WHERE id=? AND target_type='stock_issue'",[$old['event_id']])->fetch();if(!$ev)throw new RuntimeException('This key belongs to another workflow.');$id=$ev['kind']==='si_reported'?(int)$ev['id']:(int)$ev['target_id'];$c=$this->detail($actor,$id);if($action==='clarify'&&(int)$c['root']['actor_id']!==$actor)throw new PermissionDenied('Only the original reporter may clarify.');$this->access->audit($actor,'stockissue.replayed','stock_issue',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$ev['id'],'replayed'=>true];}
                [$id,$ev]=$this->command($actor,$action,$in);(new InventoryCostingService($this->access,$this->now()))->issue($actor,$id);if($file)FinanceAttachment::save($this->access,$actor,'stockissue',$ev,$file);$r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$ev]);$this->access->audit($actor,'stockissue.'.$action,'stock_issue',(string)$id,'succeeded',['event_id'=>$ev]);return ['id'=>$id,'event_id'=>$ev,'replayed'=>false];
            });
        }catch(\Throwable $e){$this->access->audit($actor,'stockissue.failed','stock_issue',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in):array{
        $r=$this->access->repo;
        if($action==='report'){
            $extra=$this->id($in['extra_id']??'');$p=$this->position($extra);$kind=R::text($in['kind']??'',30,true);$qty=R::quantity(R::text($in['qty']??'',24,true));$position=$in['position']??'';$actual=$this->actual($in['actual_at']??'');
            if(!$p['item']['tracked']||!isset(self::KINDS[$kind])||$qty<=0||!in_array($position,['held','gone','counted'],true)||$kind==='missing'&&$position==='held'||($in['verified']??'')!=='1')throw new RuntimeException('Verify tracked stock, reason, positive quantity and its actual position.');
            $count=0;$countHash=null;$claim=null;
            if($position==='counted'){$count=$this->id($in['count_id']??'');$countHash=$this->countEvidence($actor,$count,$extra,$kind,$qty,$actual);$claim=$this->hash([$count,$extra]);if($r->run("SELECT event_id FROM finance_claims WHERE scope='stock_issue_count' AND identity_hash=?",[$claim])->fetchColumn())throw new RuntimeException('This approved count loss was already linked.');}
            elseif($p['available']<$qty)throw new RuntimeException('This quantity exceeds current available stock after existing holds.');
            $this->guardCountsForReport($position,$extra,$actual);
            $id=$this->event($actor,'si_reported',['extra_id'=>$extra,'name'=>$p['item']['name'],'kind'=>$kind,'qty'=>$qty,'position'=>$position,'actual_at'=>$actual,'comment'=>R::text($in['comment']??'',2000),'count_id'=>$count,'count_hash'=>$countHash,'before'=>$p['on_hand'],'held_before'=>$p['held']]);if($claim)$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('stock_issue_count',?,?)",[$claim,$id]);return [$id,$id];
        }
        $id=$this->id($in['id']??'');$c=$this->detail($actor,$id);$d=$c['root']['data'];$d['qty']=$c['qty'];$p=$c['position'];if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This issue changed. Reload its current record.');
        if($action==='clarify'){
            if((int)$c['root']['actor_id']!==$actor)throw new PermissionDenied('Only the original reporter may clarify.');if($c['status']!=='returned')throw new RuntimeException('Only a returned report accepts clarification.');$ev=$this->event($actor,'si_clarified',['comment'=>R::text($in['comment']??'',2000,true)],$id);return [$id,$ev];
        }
        if(!in_array($action,['approve','return','dispose','correct_report'],true)||!$this->owner($actor))throw new PermissionDenied('Owner stock authority is required.');
        if(!hash_equals($c['review_token'],$in['review_token']??''))throw new RuntimeException('Stock or its source evidence changed. Reload the exact preview.');
        if($action==='correct_report'){
            if(!in_array($c['status'],['pending','returned','approved'],true)||$d['count_id'])throw new RuntimeException('Only an uncompleted unlinked report can have a mistaken hold corrected.');
            $qty=R::quantity(R::text($in['qty']??'',24,true));$reason=R::text($in['reason']??'',2000,true);
            if($qty>=$d['qty']||($in['verified']??'')!=='1')throw new RuntimeException('Reduce only the evidenced excess after physically verifying the released quantity is safe and saleable.');
            return [$id,$this->event($actor,'si_corrected',['qty_before'=>$d['qty'],'qty_after'=>$qty,'released'=>$d['qty']-$qty,'reason'=>$reason,'physically_verified_safe'=>true,'approval_revoked'=>$c['approval']?(int)$c['approval']['id']:null],$id)];
        }
        if($action==='return'){
            if(!in_array($c['status'],['pending','returned'],true))throw new RuntimeException('Only an unapproved report may be returned.');return [$id,$this->event($actor,'si_returned',['comment'=>R::text($in['comment']??'',2000,true),'hold_retained'=>true],$id)];
        }
        if(($in['verified']??'')!=='1'||!$p['item']['tracked'])throw new RuntimeException('Verify the original report and tracked stock before this decision.');
        if($d['count_id']){if(!hash_equals($d['count_hash'],$this->countEvidence($actor,$d['count_id'],$d['extra_id'],$d['kind'],$d['qty'],$d['actual_at'])))throw new RuntimeException('Original count evidence changed.');$claim=$r->run("SELECT event_id FROM finance_claims WHERE scope='stock_issue_count' AND identity_hash=?",[$this->hash([$d['count_id'],$d['extra_id']])])->fetchColumn();if((int)$claim!==$id)throw new RuntimeException('Original count allocation evidence is missing.');}
        $this->guardCountsForDisposition($d,$id);
        $ev=0;
        if($action==='approve'){
            if(!in_array($c['status'],['pending','returned'],true))throw new RuntimeException('Only an unapproved report may be approved.');$ev=$this->event($actor,'si_approved',['comment'=>R::text($in['comment']??'',2000),'verified'=>true,'qty'=>$d['qty'],'count_id'=>$d['count_id']],$id);if($d['position']!=='gone')return [$id,$ev];$actual=$d['actual_at'];
        }else{if($c['status']!=='approved')throw new RuntimeException('Approve the report before confirming actual disposition.');$actual=$this->actual($in['actual_at']??'');if($actual<$c['approval']['created_at'])throw new RuntimeException('Disposition cannot precede approval.');}
        $after=$p['on_hand'];$movement=null;
        if(!$d['count_id']){
            $after-=$d['qty'];if($after<0||$after<$p['held']-$d['qty'])throw new RuntimeException('Current stock no longer supports this outflow and other holds.');
            $r->run('UPDATE extras SET stock_qty=? WHERE id=?',[R::decimal($after),$d['extra_id']]);$r->run("INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,'out',?,?,'stock_issue',?,?,?)",[$d['extra_id'],R::decimal($d['qty']),self::KINDS[$d['kind']].' / Stock Issue #'.$id,$id,$actor,$this->now()]);$movement=(int)$r->db->lastInsertId();
        }
        $ev=$this->event($actor,'si_completed',['before'=>$p['on_hand'],'after'=>$after,'delta'=>$after-$p['on_hand'],'actual_at'=>$actual,'movement_id'=>$movement,'count_id'=>$d['count_id'],'disposition'=>$d['kind']==='complimentary'?'Complimentary Supply':($d['kind']==='missing'?'Missing Stock Confirmed':'Safe Disposal'),'approval_id'=>$action==='approve'?$ev:(int)$c['approval']['id']],$id);return [$id,$ev];
    }
}
