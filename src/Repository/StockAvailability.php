<?php
namespace Tivoli\Repository;
use RuntimeException;

/** Quantity reservations are derived from retained issue events, never deducted twice. */
final class StockAvailability
{
    public static function hold(AccessRepository $r,int $extra):array
    {
        if(!$r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='finance_events'")->fetchColumn())return ['held'=>0,'token'=>hash('sha256','[]')];
        $roots=$r->run("SELECT id,payload FROM finance_events WHERE target_type='stock_issue' AND kind='si_reported' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.extra_id'))=? ORDER BY id",[(string)$extra])->fetchAll();
        $cases=[];
        foreach($roots as $root){$d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);if(!is_int($d['qty']??null)||$d['qty']<=0||!in_array($d['position']??'',['held','gone','counted'],true))throw new RuntimeException('Stock hold evidence is unsupported.');$cases[$root['id']]=['qty'=>$d['qty'],'position'=>$d['position'],'done'=>0];}
        if($roots){$ids=array_map(fn($x)=>(string)$x['id'],$roots);$sql=implode(',',array_fill(0,count($ids),'?'));foreach($r->run("SELECT id,kind,target_id,payload FROM finance_events WHERE target_type='stock_issue' AND kind IN ('si_completed','si_corrected') AND target_id IN ($sql) ORDER BY id",$ids)->fetchAll() as $e){$c=&$cases[$e['target_id']];$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if($c['done']||$c['qty']===0)throw new RuntimeException('Duplicate or invalid stock disposition evidence requires investigation.');if($e['kind']==='si_completed'){$c['done']=(int)$e['id'];}else{if($c['position']==='counted'||($d['qty_before']??null)!==$c['qty']||!is_int($d['qty_after']??null)||$d['qty_after']<0||$d['qty_after']>=$c['qty'])throw new RuntimeException('Unsupported report correction evidence.');$c['qty']=$d['qty_after'];}unset($c);}}
        $held=0;$state=[];$issues=[];
        foreach($cases as $id=>$c){if(!$c['done']&&$c['position']!=='counted'&&$c['qty']>0){$held+=$c['qty'];$issues[$id]=$c['qty'];}$state[]=[(int)$id,$c['qty'],$c['position'],$c['done']];}
        return ['held'=>$held,'issues'=>$issues,'token'=>hash('sha256',json_encode($state,JSON_THROW_ON_ERROR))];
    }
}
