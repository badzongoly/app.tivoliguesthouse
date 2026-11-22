<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Repository\ReceiptAccounting;

/** Called only from the existing writers, inside their shared transaction and posting lock. */
trait InventoryCostingSources
{
    private function source(int $actor,int $extra,string $identity,array $data,int $acquisition=0,?string $date=null):bool{
        if(!$this->opening($extra))return false;
        $r=$this->access->repo;$hash=$this->hash([$extra,$identity]);if($r->run("SELECT event_id FROM finance_claims WHERE scope='inventory_source' AND identity_hash=?",[$hash])->fetchColumn())return false;
        FinanceService::guardSource($this->access,$date??substr($this->now(),0,10));
        $ev=$this->event($actor,'ic_source',$extra,$data+['identity'=>$identity,'acquisition_delta'=>$acquisition],$date);$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('inventory_source',?,?)",[$hash,$ev]);return true;
    }
    public function purchase(int $actor,int $purchase,int $posted):void{
        $r=$this->access->repo;if(!ReceiptAccounting::installed($r))return;$e=$r->run("SELECT payload FROM finance_events WHERE id=? AND kind='purchase_posted' AND target_type='purchase' AND target_id=?",[$posted,(string)$purchase])->fetchColumn();if(!$e)throw new \RuntimeException('Original purchase posting missing.');$d=json_decode($e,true,512,JSON_THROW_ON_ERROR);if(!$d['stock_movement_id'])return;$m=$r->run('SELECT * FROM stock_movements WHERE id=?',[$d['stock_movement_id']])->fetch();if(!$m||$m['ref_type']!=='purchase'||(int)$m['ref_id']!==$purchase)throw new \RuntimeException('Original stock receipt link missing.');$extra=(int)$m['extra_id'];
        $opening=$this->opening($extra);if(!$opening)return;
        $declaration=$r->run("SELECT payload FROM finance_events WHERE id=? AND target_type='purchase' AND target_id=? AND kind='purchase_received'",[$d['declaration_id'],(string)$purchase])->fetchColumn();$actual=$declaration?(json_decode($declaration,true,512,JSON_THROW_ON_ERROR)['delivered_at']??null):null;
        $boundary=$opening['data']['counted_at']??null;if(!$boundary){$approval=$r->run("SELECT payload FROM finance_events WHERE id=? AND kind='count_approved'",[$opening['data']['count_approval_id']])->fetchColumn();$boundary=$approval?(json_decode($approval,true,512,JSON_THROW_ON_ERROR)['lines'][$extra]['counted_at']??null):null;}
        $afterCount=$actual&&$boundary&&($actual>$boundary||($actual===$boundary&&(int)($d['approval_id']??0)>(int)$opening['data']['count_approval_id']));$type=$afterCount?'receipt':'unsupported';
        if($this->source($actor,$extra,'purchase:'.$posted,['type'=>$type,'original_delivered_at'=>$actual,'opening_counted_at'=>$boundary,'purchase_id'=>$purchase,'event_id'=>$posted,'movement_id'=>(int)$m['id']],(int)$d['actual_cost'],$d['posting_date']))$this->value($actor,$extra);
    }
    public function movement(int $actor,int $movement):void{
        $r=$this->access->repo;if(!ReceiptAccounting::installed($r))return;$m=$r->run('SELECT * FROM stock_movements WHERE id=?',[$movement])->fetch();if(!$m)throw new \RuntimeException('Original stock movement missing.');$extra=(int)$m['extra_id'];if(!$this->opening($extra))return;
        $type=$m['ref_type']==='booking_extra'&&$m['movement_type']==='out'?'sale':($m['movement_type']==='in'?'surplus':'unsupported');
        if($this->source($actor,$extra,'movement:'.$movement,['type'=>$type,'qty'=>R::quantity(ltrim((string)$m['qty'],'-')),'movement_id'=>$movement]))$this->value($actor,$extra);
    }
    public function count(int $actor,int $event):void{
        $r=$this->access->repo;if(!ReceiptAccounting::installed($r))return;$json=$r->run("SELECT payload FROM finance_events WHERE id=? AND kind='count_approved' AND target_type='stock_count'",[$event])->fetchColumn();if(!$json)return;
        foreach(json_decode($json,true,512,JSON_THROW_ON_ERROR)['lines'] as $extra=>$line){if(!$line['difference'])continue;$delta=(int)$line['difference'];if($this->source($actor,(int)$extra,'count:'.$event,['type'=>$delta<0?'count_loss':'surplus','qty'=>abs($delta),'movement_id'=>$line['movement_id'],'event_id'=>$event]))$this->value($actor,(int)$extra);}
    }
    public function issue(int $actor,int $issue):void{
        $r=$this->access->repo;if(!ReceiptAccounting::installed($r))return;$json=$r->run("SELECT payload FROM finance_events WHERE id=? AND target_type='stock_issue' AND kind='si_reported'",[$issue])->fetchColumn();if(!$json)return;$root=json_decode($json,true,512,JSON_THROW_ON_ERROR);$extra=(int)$root['extra_id'];if(!$this->opening($extra))return;$changed=false;
        foreach($r->run("SELECT id,kind,payload FROM finance_events WHERE target_type='stock_issue' AND target_id=? AND kind IN ('si_approved','si_corrected','si_completed') ORDER BY id",[(string)$issue])->fetchAll() as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$type='memo';$data=[];
            if($e['kind']==='si_approved'&&!$root['count_id']&&$root['kind']!=='complimentary'){$type='loss';$data=['qty'=>$d['qty'],'loss'=>'approval:'.$e['id']];}
            if($e['kind']==='si_corrected'&&$d['approval_revoked']&&!$root['count_id']&&$root['kind']!=='complimentary'){$type='restore';$data=['loss'=>'approval:'.$d['approval_revoked']];}
            if($e['kind']==='si_completed'&&!$root['count_id']){$approval=$r->run('SELECT payload FROM finance_events WHERE id=?',[$d['approval_id']])->fetchColumn();$qty=json_decode($approval,true,512,JSON_THROW_ON_ERROR)['qty'];$type=$root['kind']==='complimentary'?'complimentary':'dispose';$data=['qty'=>$qty,'loss'=>'approval:'.$d['approval_id'],'movement_id'=>$d['movement_id']];}
            $changed=$this->source($actor,$extra,'issue:'.$e['id'],$data+['type'=>$type,'event_id'=>(int)$e['id']])||$changed;
        }if($changed)$this->value($actor,$extra);
    }
    public function correction(int $actor,int $event):void{
        $r=$this->access->repo;if(!ReceiptAccounting::installed($r))return;$json=$r->run("SELECT payload FROM finance_events WHERE id=? AND kind='pc_posted'",[$event])->fetchColumn();if(!$json)return;$d=json_decode($json,true,512,JSON_THROW_ON_ERROR);if($d['source_type']!=='purchase'||!$d['extra_id'])return;$extra=(int)$d['extra_id'];$type=$d['stock_basis']==='actual_return'?'return':'memo';
        $opening=$this->opening($extra);if(!$opening)return;$basis=$opening['data'];foreach($this->rows($extra) as $row)if($row['kind']==='ic_evidence')$basis=array_replace($basis,$row['data']);
        $eligible=(int)$basis['purchase_id']===(int)$d['source_id'];
        if(!$eligible)foreach($this->rows($extra) as $row)if($row['kind']==='ic_source'&&$row['data']['type']==='receipt'&&(int)$row['data']['purchase_id']===(int)$d['source_id']){$eligible=true;break;}
        // A historical purchase fully consumed before cutover is still an ordinary cost correction.
        // Do not cancel it with an invented inventory addback. Unmapped physical corrections require source review.
        if(!$eligible&&$type!=='return'&&!$d['stock_delta'])return;
        if(!$eligible&&$type!=='return')$type='unsupported';
        $data=['type'=>$type,'qty'=>(int)$d['return_qty'],'credit'=>-(int)$d['cost_delta'],'movement_id'=>$d['new_stock_movement_id'],'event_id'=>$event,'purchase_id'=>$d['source_id']];
        if($this->source($actor,$extra,'correction:'.$event,$data,$eligible||$type==='return'?(int)$d['cost_delta']:0,$d['posting_date']))$this->value($actor,$extra);
    }
    /** Separate bridge; the original summary's fields and financial contracts stay intact. */
    public function period(int $actor,string $start,string $end,int $recordedProfit):array{
        $this->permit($actor);foreach([$start,$end] as $date){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new \RuntimeException('Choose valid costing review dates.');}if($end<$start)throw new \RuntimeException('Invalid costing range.');
        $r=$this->access->repo;$out=['started'=>false,'complete'=>true,'partial'=>false,'cutover'=>null,'acquisition'=>0,'costs'=>['sales'=>0,'loss'=>0,'complimentary'=>0,'returns'=>0],'unknown'=>0,'unstarted'=>0,'inventory'=>0,'inventory_unknown'=>0,'recorded_profit'=>$recordedProfit,'profit'=>null];
        foreach($r->run("SELECT kind,effective_date,payload FROM finance_events WHERE target_type='inventory_cost' AND effective_date BETWEEN ? AND ? AND kind IN ('ic_source','ic_valued') ORDER BY id",[$start,$end])->fetchAll() as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if($e['kind']==='ic_source')$out['acquisition']+=(int)$d['acquisition_delta'];else foreach($d['cost_delta'] as $key=>$amount)$out['costs'][$key]+=$amount;}
        $openings=[];$states=[];
        foreach($r->run("SELECT id,kind,target_id,created_at,payload FROM finance_events WHERE target_type='inventory_cost' AND kind IN ('ic_opening','ic_valued') AND effective_date<=? ORDER BY id",[$end])->fetchAll() as $e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if($e['kind']==='ic_opening')$openings[(int)$e['target_id']]=$e;else $states[(int)$e['target_id']]=$e['data']['state'];}
        $movementItems=array_flip($r->run('SELECT DISTINCT extra_id FROM stock_movements WHERE created_at>=? AND created_at<?',[$start.' 00:00:00',(new \DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d').' 00:00:00'])->fetchAll(\PDO::FETCH_COLUMN));
        $cursors=[];$physical=[];$unmapped=[];$sourceMovements=[];$next=(new \DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        foreach($r->run("SELECT target_id,payload FROM finance_events WHERE target_type='inventory_cost' AND kind='ic_source' AND created_at<?",[$next])->fetchAll() as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if(!empty($d['movement_id']))$sourceMovements[(int)$e['target_id']][(int)$d['movement_id']]=true;}
        foreach($r->run('SELECT id,extra_id,movement_type,qty FROM stock_movements WHERE created_at<? ORDER BY id',[$next])->fetchAll() as $m){$id=(int)$m['extra_id'];$open=$openings[$id]??null;if(!$open||(int)$m['id']<=$open['data']['movement_cursor'])continue;$cursors[$id]=(int)$m['id'];$text=(string)$m['qty'];$delta=match($m['movement_type']){'out'=>-R::quantity($text),'in'=>R::quantity($text),'adjustment'=>(str_starts_with($text,'-')?-1:1)*R::quantity(ltrim($text,'-')),default=>0};$physical[$id]=($physical[$id]??0)+$delta;if(!isset($sourceMovements[$id][(int)$m['id']]))$unmapped[$id]=true;}

        foreach($r->run('SELECT e.id,e.stock_qty FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE COALESCE(s.tracked,1)=1 ORDER BY e.id')->fetchAll() as $x){
            $raw=$this->readQuantity((string)$x['stock_qty']);$open=$openings[(int)$x['id']]??null;if(!$open||substr($open['created_at'],0,10)>$end){if($raw!==0||isset($movementItems[$x['id']]))$out['unstarted']++;continue;}
            $out['started']=true;$cut=substr($open['created_at'],0,10);$out['cutover']=$out['cutover']===null?$cut:min($out['cutover'],$cut);if($cut>=$start)$out['partial']=true;
            $s=$states[(int)$x['id']]??null;if(!$s){$out['unknown']++;continue;}$out['unknown']+=$s['unknown'];if($s['error'])$out['unknown']++;
            $cursor=(int)($cursors[$x['id']]??$open['data']['movement_cursor']);$expected=$open['data']['qty']+($physical[$x['id']]??0);
            $bad=isset($unmapped[$x['id']])||$cursor!==$s['movement_cursor']||$s['qty']+$s['written_off']!==$expected||($end>=substr($this->now(),0,10)&&$expected!==$raw);
            if($bad)$out['unknown']++;if($bad||$s['error']||$s['value']===null)$out['inventory_unknown']++;else $out['inventory']+=$s['value'];
        }
        $out['complete']=$out['started']&&!$out['partial']&&!$out['unknown']&&!$out['unstarted'];$out['before_unknown']=$recordedProfit+$out['acquisition']-array_sum($out['costs']);if($out['complete'])$out['profit']=$out['before_unknown'];return $out;
    }
}
