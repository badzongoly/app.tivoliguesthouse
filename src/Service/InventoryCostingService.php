<?php
namespace Tivoli\Service;

use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Domain\Finance\InventoryPool;
use Tivoli\Repository\ReceiptAccounting;
use Tivoli\Repository\StockAvailability;
use Tivoli\Security\PermissionDenied;
use RuntimeException;

/** Owner-reviewed opening basis and immutable valuations; never writes stock, purchases or payments. */
final class InventoryCostingService
{
    use InventoryCostingSources;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function readQuantity(string $value):int{return (str_starts_with($value,'-')?-1:1)*R::quantity(ltrim($value,'-'));}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor)&&in_array('reports.view',$this->access->repo->privileges($actor),true);}
    public function permit(int $actor):void{if(!$this->owner($actor))throw new PermissionDenied('Only the designated finance owner may review stock costs.');}
    private function event(int $actor,string $kind,int $extra,array $data,?string $date=null):int{
        if(!$this->access->repo->db->inTransaction())throw new \LogicException('Costing requires its source transaction.');
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'inventory_cost',(string)$extra,0,$date??substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$this->access->repo->db->lastInsertId();
    }
    private function rows(int $extra):array{
        $rows=$this->access->repo->run("SELECT * FROM finance_events WHERE target_type='inventory_cost' AND target_id=? ORDER BY id",[(string)$extra])->fetchAll();foreach($rows as &$e)$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);return $rows;
    }
    private function opening(int $extra):?array{
        if(!ReceiptAccounting::installed($this->access->repo))return null;
        $row=$this->access->repo->run("SELECT * FROM finance_events WHERE target_type='inventory_cost' AND target_id=? AND kind='ic_opening' LIMIT 1",[(string)$extra])->fetch();if(!$row)return null;$row['data']=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);return $row;
    }
    public function item(int $actor,int $extra):array{
        $this->permit($actor);$item=$this->access->repo->run('SELECT id,name,stock_qty FROM extras WHERE id=?',[$extra])->fetch();if(!$item)throw new RuntimeException('Stock item not found.');$history=$this->rows($extra);$opening=null;$state=null;$basis=null;
        foreach($history as $e){if($e['kind']==='ic_opening'){$opening=$e;$basis=$e['data'];}if($e['kind']==='ic_evidence')$basis=array_replace($basis,$e['data']);if($e['kind']==='ic_valued')$state=$e['data']['state'];}
        $raw=$this->readQuantity((string)$item['stock_qty']);$cursor=(int)$this->access->repo->run('SELECT COALESCE(MAX(id),0) FROM stock_movements WHERE extra_id=?',[$extra])->fetchColumn();
        $last=$history?end($history):null;$supported=$state&&$state['error']===null&&$state['qty']+$state['written_off']===$raw&&$cursor===$state['movement_cursor'];
        return ['item'=>$item,'opening'=>$opening,'basis'=>$basis,'state'=>$state,'history'=>$history,'supported'=>(bool)$supported,'version'=>$this->hash([$last['id']??0,$item,$cursor])];
    }
    public function register(int $actor,string $search=''):array{
        $this->permit($actor);$search=R::text($search,120);$out=[];foreach($this->access->repo->run('SELECT e.id FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE COALESCE(s.tracked,1)=1 AND LOCATE(?,e.name)>0 ORDER BY e.name,e.id LIMIT 50',[$search])->fetchAll(\PDO::FETCH_COLUMN) as $id)$out[]=$this->item($actor,(int)$id);return $out;
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the stock-cost form.');unset($in['csrf'],$in['action'],$in['operation_key']);foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid stock-cost form.');ksort($in);$fp=$this->hash(['costing.'.$action,$in]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$fp){
                $this->permit($actor);$r=$this->access->repo;$old=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($old){if(!hash_equals($old['fingerprint'],$fp))throw new RuntimeException('This key belongs to different details.');$ev=$r->run("SELECT * FROM finance_events WHERE id=? AND target_type='inventory_cost'",[$old['event_id']])->fetch();if(!$ev)throw new RuntimeException('This key belongs to another workflow.');$this->access->audit($actor,'costing.replayed','inventory_cost',$ev['target_id'],'recorded');return ['event_id'=>(int)$ev['id'],'extra_id'=>(int)$ev['target_id'],'replayed'=>true];}
                FinanceService::guardSource($this->access,substr($this->now(),0,10));$extra=filter_var($in['extra_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$extra||($in['verified']??'')!=='1')throw new RuntimeException('Select and verify the original stock evidence.');
                $x=$r->run('SELECT e.*,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.id=? FOR UPDATE',[$extra])->fetch();if(!$x||!$x['tracked'])throw new RuntimeException('Choose tracked inventory.');
                $total=($in['total']??'')===''?null:R::minor($in['total']);$evidence=R::text($in['evidence']??'',2000,true);$purchase=filter_var($in['purchase_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
                if($action==='start'){
                    if($this->opening($extra))throw new RuntimeException('This item already has an opening basis. Add reviewed evidence instead.');
                    $count=filter_var($in['count_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$count)throw new RuntimeException('Choose an approved physical stock count.');$c=(new StockCountService($this->access,$this->now()))->detail($actor,$count);$line=$c['decision']['data']['lines'][$extra]??null;$l=$c['lines'][$extra]??null;
                    $cursor=(int)$r->run('SELECT COALESCE(MAX(id),0) FROM stock_movements WHERE extra_id=?',[$extra])->fetchColumn();$qty=R::quantity((string)$x['stock_qty']);
                    if($c['status']!=='approved'||!$line||!$l||!$l['integrity']||$qty!==$line['after']||$cursor!==max((int)$l['window']['cursor'],(int)$line['movement_id'])||$l['window']['held']!==0||StockAvailability::hold($r,$extra)['held']!==0||$c['decision']['created_at']>$this->now())throw new RuntimeException('Use a fresh approved count with unchanged stock and no held issues.');
                    $data=['method'=>'moving_weighted_average','currency'=>'GHS','qty'=>$qty,'total'=>$total,'count_id'=>$count,'count_approval_id'=>(int)$c['decision']['id'],'counted_at'=>$line['counted_at'],'movement_cursor'=>$cursor,'event_cursor'=>(int)$r->run('SELECT COALESCE(MAX(id),0) FROM finance_events')->fetchColumn(),'evidence'=>$evidence,'purchase_id'=>$purchase];
                    if($purchase)$this->validateOpeningPurchase($actor,$extra,$purchase,$qty,$total);
                    if($qty===0&&$total!==null&&$total!==0)throw new RuntimeException('Zero opening stock cannot have a positive value.');
                    $ev=$this->event($actor,'ic_opening',$extra,$data);$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('inventory_opening',?,?)",[$this->hash($extra),$ev]);
                }elseif($action==='evidence'){
                    $v=$this->item($actor,$extra);if(!$v['opening']||!hash_equals($v['version'],$in['version']??''))throw new RuntimeException('The cost basis changed. Reload the current evidence.');if($total===null)throw new RuntimeException('Enter the supported original opening total.');if($v['basis']['purchase_id'])throw new RuntimeException('Correct this linked source through Purchase Corrections.');
                    if($purchase)$this->validateOpeningPurchase($actor,$extra,$purchase,$v['opening']['data']['qty'],$total);
                    $ev=$this->event($actor,'ic_evidence',$extra,['total'=>$total,'evidence'=>$evidence,'purchase_id'=>$purchase,'purchase_basis_cursor'=>(int)$r->run('SELECT COALESCE(MAX(id),0) FROM finance_events')->fetchColumn(),'prior_event_id'=>(int)end($v['history'])['id']]);
                    if($purchase)foreach(\Tivoli\Repository\PurchaseAccounting::entries($r,'purchase',$purchase) as $correction)if((int)$correction['id']>$v['opening']['data']['event_cursor']&&!$correction['data']['stock_delta'])$this->source($actor,$extra,'correction:'.$correction['id'],['type'=>'memo','purchase_id'=>$purchase,'event_id'=>(int)$correction['id'],'late_opening_link'=>true],(int)$correction['data']['cost_delta']);
                }else throw new RuntimeException('Unknown stock-cost action.');
                $this->value($actor,$extra);$r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fp,$ev]);$this->access->audit($actor,'costing.'.$action,'inventory_cost',(string)$extra,'succeeded',['event_id'=>$ev]);return ['event_id'=>$ev,'extra_id'=>$extra,'replayed'=>false];
            });
        }catch(\Throwable $e){$this->access->audit($actor,'costing.failed','inventory_cost',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function validateOpeningPurchase(int $actor,int $extra,int $purchase,int $qty,?int $total):void{
        $v=(new PurchaseCorrectionService($this->access,$this->now()))->source($actor,'purchase',$purchase);
        $opening=$this->opening($extra);$cursor=$opening['data']['movement_cursor']??(int)$this->access->repo->run('SELECT COALESCE(MAX(id),0) FROM stock_movements WHERE extra_id=?',[$extra])->fetchColumn();
        if($v['movement_id']>$cursor||$v['extra_id']!==$extra||$v['net_qty']!==$qty||$v['cost']!==$total||$v['blockers']||$v['stock_blockers'])throw new RuntimeException('Opening purchase must support this exact complete remaining quantity and total; otherwise use independently reviewed opening evidence.');
    }
    private function value(int $actor,int $extra,?string $date=null):void{
        $rows=$this->rows($extra);$opening=$this->opening($extra);if(!$opening)return;$basis=$opening['data'];$last=null;$sources=[];foreach($rows as $e){if($e['kind']==='ic_evidence')$basis=array_replace($basis,$e['data']);if($e['kind']==='ic_source')$sources[]=$e['data'];if($e['kind']==='ic_valued')$last=$e['data']+['valuation_id'=>(int)$e['id']];}
        FinanceService::guardSource($this->access,substr($this->now(),0,10));
        if($basis['purchase_id'])foreach(\Tivoli\Repository\PurchaseAccounting::entries($this->access->repo,'purchase',$basis['purchase_id']) as $e){
            if((int)$e['id']>$opening['data']['event_cursor'])$basis['qty']+=(int)$e['data']['received_delta'];
            if((int)$e['id']>($basis['purchase_basis_cursor']??$opening['data']['event_cursor'])&&$e['data']['stock_basis']!=='actual_return')$basis['total']+=(int)$e['data']['cost_delta'];
        }
        $error=null;
        try{
            $p=new InventoryPool($basis['qty'],$basis['total']);
            foreach($sources as $s){switch($s['type']){
                case 'receipt':$v=$this->receiptBasis($s['purchase_id']);if($v['qty']>0)$p->receive($v['qty'],$v['value']);elseif($v['value']!==0)throw new RuntimeException('Zero corrected receipt retains unsupported cost.');break;
                case 'sale':$p->consume($s['qty'],'sales');break;
                case 'loss':$p->consume($s['qty'],'loss',$s['loss']);break;
                case 'count_loss':$p->consume($s['qty'],'loss');break;
                case 'surplus':$p->receive($s['qty'],null);break;
                case 'complimentary':$p->consume($s['qty'],'complimentary');break;
                case 'dispose':$p->dispose($s['loss'],$s['qty']);break;
                case 'restore':$p->restore($s['loss']);break;
                case 'return':$p->returned($s['qty'],$s['credit']);break;
                case 'memo':break;
                default:throw new RuntimeException('Unsupported stock source.');
            }}
            $state=$p->snapshot();
        }catch(\PDOException $e){throw $e;}catch(RuntimeException $e){$error='Source quantities or values require owner reconciliation.';$state=['qty'=>0,'value'=>null,'written_off'=>0,'costs'=>$last['state']['costs']??['sales'=>0,'loss'=>0,'complimentary'=>0,'returns'=>0],'unknown'=>1];}
        $r=$this->access->repo;$state['movement_cursor']=(int)$r->run('SELECT COALESCE(MAX(id),0) FROM stock_movements WHERE extra_id=?',[$extra])->fetchColumn();$raw=R::quantity((string)$r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn());
        $supported=array_filter(array_column($sources,'movement_id'));$actual=$r->run('SELECT id FROM stock_movements WHERE extra_id=? AND id>? ORDER BY id',[$extra,$opening['data']['movement_cursor']])->fetchAll(\PDO::FETCH_COLUMN);
        if(array_diff(array_map('intval',$actual),array_map('intval',$supported))||$state['qty']+$state['written_off']!==$raw)$error='Recorded stock and supported cost sources need reconciliation.';
        $state['error']=$error;if($error)$state['value']=null;
        $deltas=[];foreach($state['costs'] as $k=>$v)$deltas[$k]=$v-($last['state']['costs'][$k]??0);
        $ev=$this->event($actor,'ic_valued',$extra,['state'=>$state,'cost_delta'=>$deltas,'opening_id'=>(int)$opening['id'],'prior_valuation_id'=>$last['valuation_id']??null],$date);$this->access->audit($actor,'costing.valued','inventory_cost',(string)$extra,'recorded',['event_id'=>$ev,'complete'=>$error===null&&$state['unknown']===0]);
    }
    private function receiptBasis(int $purchase):array{
        $r=$this->access->repo;$root=$r->run("SELECT payload FROM finance_events WHERE id=? AND kind='purchase_requested'",[$purchase])->fetchColumn();$posted=$r->run("SELECT payload FROM finance_events WHERE target_type='purchase' AND target_id=? AND kind='purchase_posted' ORDER BY id LIMIT 1",[(string)$purchase])->fetchColumn();if(!$root||!$posted)throw new RuntimeException('Supported stock receipt missing.');$d=json_decode($root,true,512,JSON_THROW_ON_ERROR);$v=json_decode($posted,true,512,JSON_THROW_ON_ERROR);$qty=(int)$d['qty'];$value=(int)$v['actual_cost'];
        foreach(\Tivoli\Repository\PurchaseAccounting::entries($r,'purchase',$purchase,(int)$v['expense_id']) as $e){$qty+=(int)$e['data']['received_delta'];if($e['data']['stock_basis']!=='actual_return')$value+=(int)$e['data']['cost_delta'];}return ['qty'=>$qty,'value'=>$value];
    }
}
