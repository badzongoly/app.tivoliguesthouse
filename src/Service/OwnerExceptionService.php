<?php
namespace Tivoli\Service;
use RuntimeException;
use Throwable;
use Tivoli\Security\PermissionDenied;

/** Owner-only read model. Business decisions remain on their original guarded writers. */
final class OwnerExceptionService
{
    use OwnerExceptionFeeds, OwnerExceptionOperationalFeeds;
    public const CATEGORIES=['Money','Approvals','Corrections','Payroll','Stock','Property'];
    private array $rows=[];
    private array $coverage=[];
    private array $discrepancies=[];
    private array $names=[];
    private array $financeEvents=[];
    private array $eventIndex=[];
    private array $sourceIndex=[];
    private array $receipts=[];
    private array $caseEvents=[];
    private array $resolutionIndex=[];
    private array $deadlineIndex=[];
    private array $cash=[];
    private array $permissions=[];
    private array $timings=[];
    private int $actor=0;
    private string $selectedKey='';
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor);}
    public function permit(int $actor):void{if(!$this->owner($actor)){if(!$this->access->repo->db->inTransaction())$this->access->audit($actor,'owner_exceptions.denied','owner_exception',null,'denied');throw new PermissionDenied('Only the designated owner can open the Owner Exception Register.');}}
    private function name(?int $id):string{
        if(!$id)return 'Not Assigned';if(!array_key_exists($id,$this->names))$this->names[$id]=$this->access->repo->run('SELECT name FROM users WHERE id=?',[$id])->fetchColumn()?:'Original Account #'.$id;return $this->names[$id];
    }
    private function has(string ...$codes):bool{return (bool)array_intersect($codes,$this->permissions);}
    private function history(string $type,int|string $id):array{return $this->eventIndex[$type][(string)$id]??[];}
    private function money(int $minor):string{return 'GHS '.\Tivoli\Domain\Stay\StayRules::decimal($minor);}
    private function path(string $file,array $query=[],string $anchor=''):string{return $file.($query?'?'.http_build_query($query):'').($anchor?'#'.$anchor:'');}
    private function row(string $key,string $category,string $title,string $reference,string $path,string $state,bool $resolved,bool $decision,?int $responsible=null,string $due='',?int $amount=null,array $facts=[],array $history=[],array $links=[],bool $critical=false):array{
        $activity=[];
        foreach($history as $e){$kind=(string)($e['kind']??$e['action']??'source_recorded');$details=$e['data']??[];$activity[]=['id'=>(int)($e['id']??0),'key'=>$key,'kind'=>$kind,'label'=>trim(ucwords(str_replace('_',' ',preg_replace('/^(stay\.|wa_|cd_|rc_|ec_|pc_|gc_|si_|hk_|payroll_|purchase_|handover_)/','',$kind)))),'actor'=>$e['actor_name']??($e['actor_id']??null?$this->name((int)$e['actor_id']):'Unknown Historical Actor'),'at'=>$e['created_at']??$e['occurred_at']??'','comment'=>(string)($details['reason']??$details['comment']??$details['note']??''),'path'=>$path];}
        usort($activity,fn($a,$b)=>strcmp($b['at'],$a['at'])?:$b['id']<=>$a['id']);
        return ['key'=>$key,'category'=>$category,'title'=>$title,'reference'=>$reference,'path'=>$path,'state'=>$state,'resolved'=>$resolved,'decision'=>$decision&&!$resolved,'responsible_id'=>$responsible,'responsible'=>$this->name($responsible),'due'=>$due,'amount_minor'=>$amount,'facts'=>$facts,'history'=>$activity,'links'=>$links,'critical'=>$critical&&!$resolved,'unavailable'=>false,'updated_at'=>$activity[0]['at']??$this->now()];
    }
    private function add(array $row):void{
        if(isset($this->rows[$row['key']]))throw new RuntimeException('Duplicate source association requires review.');$this->rows[$row['key']]=$row;
    }
    private function unavailable(string $key,string $category,string $title,string $path=''):void{
        $row=$this->row($key,$category,$title,$key,$path,'Source Unavailable',false,true);$row['unavailable']=true;$this->add($row);
    }
    private function feed(string $name,string $category,bool $allowed,callable $read):void{
        if(!$allowed){$this->coverage[$name]='restricted';return;}
        $started=microtime(true);
        try{$read();$this->coverage[$name]=$this->coverage[$name]??'available';}
        catch(PermissionDenied){$this->coverage[$name]='restricted';}
        catch(Throwable){$this->coverage[$name]='unavailable';if(!isset($this->rows['unavailable:'.$name]))$this->unavailable('unavailable:'.$name,$category,$name.' Source Unavailable');}
        finally{$this->timings[$name]=round((microtime(true)-$started)*1000,2);if(PHP_SAPI==='cli'&&getenv('TIVOLI_TEST_MODE')==='1'&&getenv('TIVOLI_OWNER_PROFILE')==='1')fwrite(STDERR,$name.' '.$this->timings[$name]." ms\n");}
    }
    private function roots(string $name,string $category,string $type,array $kinds,callable $read):void{
        $marks=implode(',',array_fill(0,count($kinds),'?'));
        $roots=$this->access->repo->run("SELECT id,actor_id,created_at,payload,kind FROM finance_events WHERE target_type=? AND kind IN ($marks) ORDER BY id DESC",[$type,...$kinds])->fetchAll();
        foreach($roots as $root){try{$row=$read((int)$root['id'],$root);if($row)$this->add($row);}
            catch(PermissionDenied){$this->coverage[$name]='restricted';}
            catch(Throwable){$this->coverage[$name]='unavailable';$this->unavailable($name.':'.$root['id'],$category,$name.' #'.$root['id'].' · Source Unavailable',$this->fallbackPath($name,(int)$root['id']));}}
    }
    private function fallbackPath(string $name,int $id):string{return match($name){
        'discrepancy'=>$this->path('finance.php',['page'=>'discrepancies','id'=>$id],'discrepancy-action'),
        'weekly'=>$this->path('weekly-audits.php',['id'=>$id],'audit-action'),
        'purchase'=>$this->path('purchases.php',['id'=>$id],'purchase-action'),
        'payroll'=>$this->path('payroll.php',['id'=>$id],'payroll-action'),
        'stock_issue'=>$this->path('stock-issues.php',['id'=>$id],'stockissue-action'),
        'stock_count'=>$this->path('stock-counts.php',['id'=>$id],'stockcount-action'),
        'handover'=>$this->path('handover.php',['id'=>$id],'handover-action'),
        'housekeeping'=>$this->path('housekeeping.php',['id'=>$id],'housekeeping-action'),
        'receipt_correction'=>$this->path('financial-corrections.php',['id'=>$id],'correction-action'),
        'expense_correction'=>$this->path('financial-corrections.php',['area'=>'expenses','id'=>$id],'correction-action'),
        'purchase_correction'=>$this->path('financial-corrections.php',['area'=>'purchases','id'=>$id],'purchase-correction-action'),
        'guest_charge'=>$this->path('financial-corrections.php',['area'=>'charges','id'=>$id],'charge-action'),
        default=>''};}
    /** Internal reminder generation reuses all source rows, never the display page limit. */
    public function reminderSources(int $actor,bool $locked=false):array{
        if(!$locked){$view=$this->read($actor);return ['rows'=>array_values($this->rows),'coverage'=>$view['coverage']];}
        if(!$this->access->repo->db->inTransaction())throw new RuntimeException('Reminder source snapshot requires its operational lock.');
        $this->permit($actor);$this->actor=$actor;$this->selectedKey='';$this->rows=[];$this->coverage=[];$this->discrepancies=[];$this->names=[];$this->cash=[];$this->financeEvents=[];$this->eventIndex=[];$this->sourceIndex=[];$this->receipts=[];$this->caseEvents=[];$this->resolutionIndex=[];$this->deadlineIndex=[];$this->timings=[];
        $this->permissions=$this->access->repo->privileges($actor);$this->collect();return ['rows'=>array_values($this->rows),'coverage'=>$this->coverage];
    }
    private function query(array $query):array{
        foreach($query as $v)if(!is_string($v))throw new RuntimeException('Choose valid owner register filters.');
        $view=$query['view']??'open';$filter=$query['filter']??'all';$workflow=$query['workflow']??'All';$key=$query['key']??'';$page=$query['page']??'1';
        if(!in_array($view,['open','resolved','activity'],true)||!in_array($filter,['all','overdue','decision','staff'],true)||!in_array($workflow,['All',...self::CATEGORIES],true)||!preg_match('/^[1-9][0-9]{0,6}$/D',$page)||($key!==''&&!preg_match('/^[a-z_]+:[a-z0-9:_-]{1,90}$/D',$key)))throw new RuntimeException('Choose valid owner register filters.');
        return compact('view','filter','workflow','key')+['page'=>(int)$page];
    }
    public function read(int $actor,array $query=[]):array{
        $this->permit($actor);$filters=$this->query($query);$db=$this->access->repo->db;if($db->inTransaction())throw new RuntimeException('Open the owner register outside a business transaction.');
        $this->actor=$actor;$this->selectedKey=$filters['key'];$this->rows=[];$this->coverage=[];$this->discrepancies=[];$this->names=[];$this->cash=[];$this->financeEvents=[];$this->eventIndex=[];$this->sourceIndex=[];$this->receipts=[];$this->caseEvents=[];$this->resolutionIndex=[];$this->deadlineIndex=[];$this->timings=[];
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
        try{
            $this->permit($actor);$this->permissions=$this->access->repo->privileges($actor);$this->collect();$rows=array_values($this->rows);$open=0;$overdue=0;$decisions=0;$activity=[];$selected=null;
            foreach($rows as &$row){$row['overdue']=!$row['resolved']&&$row['due']!==''&&$row['due']<substr($this->now(),0,10);if(!$row['resolved']){$open++;if($row['overdue'])$overdue++;if($row['decision'])$decisions++;}if($row['key']===$filters['key'])$selected=$row;foreach($row['history'] as $event)$activity[$row['key'].':'.$event['id'].':'.$event['kind']]=$event;}unset($row);
            if($filters['key']!==''&&!$selected)throw new RuntimeException('This original source is unavailable or is outside your current authority.');
            $rows=array_values(array_filter($rows,fn($r)=>$r['resolved']===($filters['view']==='resolved')&&($filters['workflow']==='All'||$r['category']===$filters['workflow'])&&match($filters['filter']){'overdue'=>$r['overdue'],'decision'=>$r['decision'],'staff'=>!$r['decision'],default=>true}));
            usort($rows,fn($a,$b)=>(int)$b['critical']<=>(int)$a['critical']?:(int)$b['unavailable']<=>(int)$a['unavailable']?:(int)$b['overdue']<=>(int)$a['overdue']?:(int)$b['decision']<=>(int)$a['decision']?:strcmp($b['updated_at'],$a['updated_at'])?:strcmp($a['key'],$b['key']));
            $activity=array_values($activity);usort($activity,fn($a,$b)=>strcmp($b['at'],$a['at'])?:$b['id']<=>$a['id']);$total=$filters['view']==='activity'?count($activity):count($rows);$pages=max(1,(int)ceil($total/50));$offset=($filters['page']-1)*50;$result=['rows'=>array_slice($rows,$offset,50),'activity'=>array_slice($activity,$offset,50),'total'=>$total,'pages'=>$pages,'selected'=>$selected,'open'=>$open,'overdue'=>$overdue,'decisions'=>$decisions,'filters'=>$filters,'coverage'=>$this->coverage,'as_of'=>$this->now()];
            if(getenv('TIVOLI_TEST_MODE')==='1')$result['test_timings_ms']=$this->timings;
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        $this->access->audit($actor,'owner_exceptions.viewed','owner_exception',$filters['key']?:null,'succeeded',['view'=>$filters['view'],'workflow'=>$filters['workflow'],'source_unavailable'=>in_array('unavailable',$this->coverage,true)]);return $result;
    }
}
