<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** Blind counting windows and owner-authorized additive inventory differences. */
final class StockCountService
{
    public const REASONS=['Missing / Unexplained','Damage','Expiry','Surplus Found','Count / Recording Error','Other'];
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor);}
    public function permit(int $actor):void{
        $r=$this->access->repo;$u=$r->user($actor);
        if(!$u||!$u['active']||!array_intersect(['bookings.manage','stock.manage'],$r->privileges($actor)))throw new PermissionDenied('You cannot access stock counts.');
        if(!(new FinanceService($this->access))->installed())throw new RuntimeException('The existing finance setup must be active before stock counting.');
        if($r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn()
            &&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,substr($this->now(),0,10)])->fetchColumn())throw new PermissionDenied('This staff account is not eligible for stock counting.');
    }
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid stock count or item.');return (int)$v;}
    private function event(int $actor,string $kind,array $data,int $id=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'stock_count',(string)$id,0,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);
        return (int)$this->access->repo->db->lastInsertId();
    }
    public function options(int $actor):array{
        $this->permit($actor);return $this->access->repo->run("SELECT e.id,e.name,e.active FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE COALESCE(s.tracked,1)=1 ORDER BY e.name,e.id")->fetchAll();
    }
    private function position(int $id):array{
        $r=$this->access->repo;$x=$r->run('SELECT e.id,e.name,e.stock_qty,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.id=?',[$id])->fetch();
        if(!$x||!$x['tracked'])throw new RuntimeException('This item is no longer tracked stock. Obtain an owner review.');
        $rows=$r->run('SELECT * FROM stock_movements WHERE extra_id=? ORDER BY id',[$id])->fetchAll();
        $hold=\Tivoli\Repository\StockAvailability::hold($r,$id);$raw=R::quantity((string)$x['stock_qty']);if($hold['held']>$raw)throw new RuntimeException('Recorded stock is below reported holds. Investigate before counting.');
        return ['expected'=>$raw-$hold['held'],'on_hand'=>$raw,'held'=>$hold['held'],'held_issues'=>$hold['issues']??[],'hold_token'=>$hold['token'],'cursor'=>$rows?(int)end($rows)['id']:0,'hash'=>$this->hash($rows),'rows'=>$rows];
    }
    private function load(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;
        $root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind='count_started' AND e.target_type='stock_count'",[$id])->fetch();
        if(!$root)throw new RuntimeException('Stock count not found.');
        if((int)$root['actor_id']!==$actor&&!$this->owner($actor))throw new PermissionDenied('Only the counter and designated owner may view this count.');
        $root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$lines=$root['data']['items'];$status='counting';$decision=null;
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='stock_count' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$d=$e['data'];
            switch($e['kind']){
                case 'count_window':$lines[$d['extra_id']]['window']=$d['window'];$lines[$d['extra_id']]['complete']=false;break;
                case 'count_line':$lines[$d['extra_id']]=array_merge($lines[$d['extra_id']],$d['line']);break;
                case 'count_submitted':foreach($d['reasons'] as $key=>$reason)$lines[$key]=array_merge($lines[$key],$reason);$status='submitted';break;
                case 'count_disputed':$status='disputed';break;
                case 'count_clarified':$status='submitted';break;
                case 'count_recount':$status='recount';break;
                case 'count_superseded':$status='superseded';break;
                case 'count_cancelled':$status='cancelled';break;
                case 'count_approved':$status='approved';$decision=$e;break;
            }
        }unset($e);
        if($status==='counting'&&count(array_filter($lines,fn($l)=>!empty($l['complete'])))===count($lines))$status='ready';
        return ['root'=>$root,'lines'=>$lines,'history'=>$history,'status'=>$status,'decision'=>$decision,'version'=>$this->hash([$id,array_column($history,'id')])];
    }
    private function comparison(array $line,int $extra):array{
        $p=$this->position($extra);$window=$line['window'];$old=[];$after=[];$delta=0;$adjustment=false;
        foreach($p['rows'] as $m){if((int)$m['id']<=$window['cursor'])$old[]=$m;else{
            $after[]=$m;$text=(string)$m['qty'];$delta+=match($m['movement_type']){'out'=>-R::quantity($text),'in'=>R::quantity($text),'adjustment'=>(str_starts_with($text,'-')?-1:1)*R::quantity(ltrim($text,'-')),default=>throw new RuntimeException('Unknown movement type requires owner investigation.')};
            if($m['movement_type']==='adjustment')$adjustment=true;
        }}
        $integrity=hash_equals($window['hash'],$this->hash($old))&&($window['on_hand']??$window['expected'])+$delta===$p['on_hand']&&hash_equals($window['hold_token']??hash('sha256','[]'),$p['hold_token']);
        $difference=$line['qty']-$window['expected'];$projected=$p['on_hand']+$difference;
        return ['expected'=>$window['expected'],'difference'=>$difference,'later'=>$delta,'current'=>$p['on_hand'],'held'=>$p['held'],'projected'=>$projected,'stale'=>$adjustment,'integrity'=>$integrity,'position_hash'=>$p['hash'],'cursor'=>$p['cursor']];
    }
    public function detail(int $actor,int $id):array{
        $c=$this->load($actor,$id);$positions=[];
        $c['blind']=count(array_filter($c['lines'],fn($l)=>!empty($l['complete'])))!==count($c['lines']);
        if($c['blind']){
            foreach($c['lines'] as &$l){unset($l['window']);}unset($l);
            foreach($c['history'] as &$e){if(in_array($e['kind'],['count_window','count_line'],true)){$e['data']=['extra_id'=>$e['data']['extra_id']];$e['payload']='{}';}}unset($e);
        }else{
            foreach($c['lines'] as $extra=>&$l){if(empty($l['complete']))continue;
                try{$cmp=$this->comparison($l,(int)$extra)+['available'=>true];}
                catch(RuntimeException $e){if($e instanceof \PDOException)throw $e;$cmp=['expected'=>$l['window']['expected'],'difference'=>$l['qty']-$l['window']['expected'],'later'=>null,'current'=>null,'projected'=>null,'stale'=>true,'integrity'=>false,'position_hash'=>null,'cursor'=>null,'available'=>false];}
                // Approval's own movement is an adjustment; use its frozen decision for history.
                if($c['status']==='approved'){if($cmp['later']!==null)$cmp['later']-=$c['decision']['data']['lines'][$extra]['difference'];$cmp['projected']=$cmp['current'];$cmp['stale']=false;}
                $l+=$cmp;$positions[$extra]=$cmp;
            }unset($l);
        }
        $c['review_token']=$this->hash([$c['version'],$positions]);
        return $c;
    }
    public function register(int $actor,int $before=0):array{
        $this->permit($actor);$where=$this->owner($actor)?'':' AND actor_id=?';$params=$this->owner($actor)?[]:[$actor];
        if($before>0){$where.=' AND id<?';$params[]=$before;}
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='stock_count' AND kind='count_started'".$where.' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);
        $more=count($ids)>50;if($more)array_pop($ids);
        return ['rows'=>array_map(fn($id)=>$this->detail($actor,(int)$id),$ids),'next'=>$more?(int)end($ids):0];
    }
    public function attention(int $actor,bool $own=false):array{
        $this->permit($actor);if(!$own&&!$this->owner($actor))throw new PermissionDenied('Only the designated owner may review all counts.');
        // Select open statuses before loading quantity histories; closed counts stay in the register.
        $where=$own?' AND root.actor_id=?':'';
        $ids=$this->access->repo->run("SELECT root.id FROM finance_events root WHERE root.target_type='stock_count' AND root.kind='count_started'".$where." AND (SELECT state.kind FROM finance_events state WHERE state.target_type='stock_count' AND state.target_id=CAST(root.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND state.kind IN ('count_submitted','count_disputed','count_clarified','count_recount','count_superseded','count_cancelled','count_approved') ORDER BY state.id DESC LIMIT 1) IN ('count_submitted','count_disputed','count_clarified','count_recount') ORDER BY root.id DESC",$own?[$actor]:[])->fetchAll(\PDO::FETCH_COLUMN);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if(in_array($c['status'],['submitted','disputed','recount'],true))$rows[]=$c;}
        return ['rows'=>$rows,'count'=>count($rows)];
    }
    public function execute(int $actor,string $action,array $input,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the form before submitting.');
            $file=null;if(isset($input['_evidence'])){if(!in_array($action,['submit','clarify'],true))throw new RuntimeException('Attach evidence when submitting or clarifying the count.');$file=FinanceAttachment::checked($input['_evidence']);unset($input['_evidence']);}
            foreach($input as $v)if(!is_string($v))throw new RuntimeException('Invalid stock count form.');
            unset($input['csrf'],$input['operation_key'],$input['action']);ksort($input);$fp=$this->hash(['stockcount.'.$action,$input,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$input,$key,$fp,$file){
                $this->permit($actor);$r=$this->access->repo;$prior=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($prior){
                    if(!hash_equals($prior['fingerprint'],$fp))throw new RuntimeException('This form was used for different details.');
                    $ev=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='stock_count'",[$prior['event_id']])->fetch();if(!$ev)throw new RuntimeException('This operation belongs to another workflow.');
                    $id=$ev['kind']==='count_started'?(int)$ev['id']:(int)$ev['target_id'];$this->access->audit($actor,'stockcount.replayed','stock_count',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$ev['id'],'replayed'=>true];
                }
                [$id,$ev]=$this->command($actor,$action,$input);
                (new InventoryCostingService($this->access,$this->now()))->count($actor,$ev);
                if($file)FinanceAttachment::save($this->access,$actor,'stockcount',$ev,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$ev]);
                $this->access->audit($actor,'stockcount.'.$action,'stock_count',(string)$id,'succeeded',['event_id'=>$ev]);
                return ['id'=>$id,'event_id'=>$ev,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'stockcount.failed','stock_count',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in):array{
        $r=$this->access->repo;
        if($action==='start'){
            $list=json_decode($in['items']??'',true,16,JSON_THROW_ON_ERROR);
            if(!is_array($list)||!array_is_list($list)||count($list)<1||count($list)>50)throw new RuntimeException('Choose from 1 to 50 tracked items.');
            $items=[];foreach($list as $key){if(!is_int($key)&&!is_string($key))throw new RuntimeException('Invalid stock item.');$id=$this->id((string)$key);if(isset($items[$id]))throw new RuntimeException('Choose each item once.');$pos=$this->position($id);$name=$r->run('SELECT name FROM extras WHERE id=?',[$id])->fetchColumn();$items[$id]=['name'=>$name,'complete'=>false];}
            $parent=isset($in['parent'])?$this->id($in['parent']):null;
            if($parent){$c=$this->load($actor,$parent);if((int)$c['root']['actor_id']!==$actor||$c['status']!=='recount')throw new RuntimeException('Only the original counter may start the requested recount.');$a=array_keys($c['lines']);$b=array_keys($items);sort($a);sort($b);if($a!==$b)throw new RuntimeException('Recount the same selected items.');}
            $purpose=R::text($in['purpose']??'',80,true);if(!in_array($purpose,['Weekly Stock Count','Shift Check','Investigate A Discrepancy'],true))throw new RuntimeException('Choose a count purpose.');
            $id=$this->event($actor,'count_started',['items'=>$items,'purpose'=>$purpose,'comment'=>R::text($in['comment']??'',2000),'parent'=>$parent]);return [$id,$id];
        }
        $id=$this->id($in['id']??'');$c=$this->load($actor,$id);
        if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This count changed. Reload its current history.');
        $staff=(int)$c['root']['actor_id']===$actor;
        if(in_array($action,['approve','dispute','recount'],true)){if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner may decide stock counts.');}
        elseif(!$staff)throw new PermissionDenied('Only the original counter may perform this action.');
        $data=[];
        if(in_array($action,['begin','finish'],true)){
            if(!in_array($c['status'],['counting','ready'],true))throw new RuntimeException('This count is no longer editable.');
            $extra=$this->id($in['extra_id']??'');if(!isset($c['lines'][$extra]))throw new RuntimeException('This item is outside the count scope.');
            $position=$this->position($extra);$window=array_diff_key($position,['rows'=>1]);
            if($action==='begin'){$data=['extra_id'=>$extra,'window'=>$window,'began_at'=>$this->now()];$kind='count_window';}
            else{
                $line=$c['lines'][$extra];if(!isset($line['window'])||$this->hash($line['window'])!==$this->hash($window))throw new RuntimeException('Stock moved during this counting window. Begin this item again and physically recount it.');
                if(($in['confirmed']??'')!=='1')throw new RuntimeException('Confirm physical counting and separately held stock.');
                $qty=R::quantity(R::text($in['qty']??'',24,true));
                $data=['extra_id'=>$extra,'line'=>['qty'=>$qty,'note'=>R::text($in['note']??'',2000),'complete'=>true,'counted_at'=>$this->now()]];$kind='count_line';
            }
        }elseif($action==='submit'){
            if($c['status']!=='ready'||($in['confirmed']??'')!=='1')throw new RuntimeException('Finish every selected item and confirm the count before submitting.');
            $reasons=json_decode($in['reasons']??'',true,32,JSON_THROW_ON_ERROR);if(!is_array($reasons))throw new RuntimeException('Invalid count explanations.');$clean=[];
            foreach($c['lines'] as $extra=>$l){$cmp=$this->comparison($l,(int)$extra);if(!$cmp['integrity']||$cmp['stale'])throw new RuntimeException('The stock evidence changed. Begin affected items again and recount before submitting.');
                $reason=R::text($reasons[$extra]['reason']??'',80,$cmp['difference']!==0);$note=R::text($reasons[$extra]['note']??$l['note'],2000,$cmp['difference']!==0);
                if($reason!==''&&!in_array($reason,self::REASONS,true))throw new RuntimeException('Select a valid variance reason.');$clean[$extra]=compact('reason','note');
            }
            if($parent=$c['root']['data']['parent']){$original=$this->load($actor,(int)$parent);if($original['status']!=='recount')throw new RuntimeException('The original count no longer awaits this recount.');$this->event($actor,'count_superseded',['recount_id'=>$id],(int)$parent);}
            $kind='count_submitted';$data=['reasons'=>$clean];
        }elseif($action==='cancel'){
            if(!in_array($c['status'],['counting','ready'],true))throw new RuntimeException('Only an unfinished count can be cancelled.');$kind='count_cancelled';$data=['reason'=>R::text($in['reason']??'',2000,true)];
        }elseif(in_array($action,['recount','dispute'],true)){
            if(!in_array($c['status'],['submitted','disputed'],true))throw new RuntimeException('Only a pending count may be disputed or sent for recount.');
            $kind=$action==='recount'?'count_recount':'count_disputed';$data=['reason'=>R::text($in['reason']??'',2000,true)];
        }elseif($action==='clarify'){
            if($c['status']!=='disputed')throw new RuntimeException('Only a disputed count accepts clarification.');
            $kind='count_clarified';$data=['reason'=>R::text($in['reason']??'',2000,true)];
        }elseif($action==='approve'){
            $this->access->requirePrivilege($actor,'stock.manage');
            if($c['status']!=='submitted')throw new RuntimeException('Only a submitted count may be approved.');
            $preview=$this->detail($actor,$id);
            if(!hash_equals($preview['review_token'],$in['review_token']??''))throw new RuntimeException('Stock changed since preview. Reload and verify the current balances.');
            $decision=R::text($in['decision']??'',2000,true);$exception=R::text($in['exception_reason']??'',2000,$staff);
            if(($in['verified']??'')!=='1')throw new RuntimeException('Verify the physical count and its evidence.');
            $snapshots=[];
            foreach($preview['lines'] as $extra=>$line){
                if(!$line['integrity']||$line['stale'])throw new RuntimeException('Unexplained changes or another adjustment require an evidenced linked recount.');
                if($line['projected']<($line['held']??0)||$line['projected']>999999999999)throw new RuntimeException('This difference would produce an invalid stock balance. Request a recount.');
                $movement=null;
                if($line['difference']!==0){
                    if(($in['authorized']??'')!=='1')throw new RuntimeException('Authorize these exact quantity differences.');
                    $r->run('UPDATE extras SET stock_qty=? WHERE id=?',[R::decimal($line['projected']),$extra]);
                    $r->run("INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,'adjustment',?,?,'stock_count',?,?,?)",[$extra,R::decimal($line['difference']),'Count #'.$id.' / '.$line['reason'],$id,$actor,$this->now()]);$movement=(int)$r->db->lastInsertId();
                }
                $snapshots[$extra]=['before'=>$line['current'],'difference'=>$line['difference'],'after'=>$line['projected'],'movement_id'=>$movement,'counted_at'=>$line['counted_at'],'held_issues'=>$line['window']['held_issues']??[],'reason'=>$line['reason'],'note'=>$line['note']];
            }
            $kind='count_approved';$data=['decision'=>$decision,'self_review_exception'=>$exception,'lines'=>$snapshots];
        }else throw new RuntimeException('Unknown stock count action.');
        return [$id,$this->event($actor,$kind,$data,$id)];
    }
}
