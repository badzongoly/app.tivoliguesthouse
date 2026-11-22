<?php
namespace Tivoli\Service;
use Tivoli\Security\PermissionDenied;

/** Only explicit original allocation/case/adoption facts can retire a parent reminder. */
trait ReminderHandoffs
{
    private ?array $cashSnapshot=null;
    private function cashData():array{return $this->cashSnapshot??=(new FinanceService($this->access,$this->now))->cash();}
    public function cashRecipients(int $owner,string $key):array{
        if($owner!==$this->owner())throw new PermissionDenied('Owner source authority is required.');if(!str_starts_with($key,'cash:'))return [];$id=substr($key,5);
        foreach($this->cashData()['rows'] as $row)if(str_replace(':','-',(string)$row['id'])===$id)return array_map('intval',array_keys(array_filter($row['available'],fn($n)=>$n>0)));return [];
    }
    private function handoffRow(string $key,string $title,string $path):array{return ['key'=>$key,'category'=>'Original Source Handoff','title'=>$title,'state'=>$title,'path'=>$path,'resolved'=>false,'actionable'=>false,'decision'=>false,'responsible_id'=>null,'due'=>'','critical'=>false,'unavailable'=>false,'history'=>[]];}
    private function knownHandoff(int $actor,string $key):?array{
        if(preg_match('/^unavailable:([a-z_]+)$/D',$key,$m)){$state=$this->snapshot['coverage'][$m[1]]??null;if($state==='restricted')throw new PermissionDenied('Current source authority is required.');if($state==='available')return $this->handoffRow($key,'Source Check Available Again','owner-exceptions.php');}
        if(preg_match('/^legacy_(room|area):(.+)$/D',$key,$m)){$source=(new WeeklyAuditService($this->access,$this->now))->source($actor,$m[1],$m[2]);if($source['followup_id'])return $this->handoffRow($key,'Original Finding Linked To Follow-Up #'.$source['followup_id'],'weekly-audits.php?id='.$source['followup_id'].'#audit-action');return null;}
        $type='';$id=0;
        if(preg_match('/^(deposit|handover):([1-9][0-9]*)$/D',$key,$m)){$type=$m[1];$id=(int)$m[2];}
        elseif(preg_match('/^momo:(payment|income)-([1-9][0-9]*)$/D',$key,$m)){$type=$m[1];$id=(int)$m[2];}
        if($type!==''){$case=$this->access->repo->run("SELECT id FROM finance_events WHERE kind='cd_reported' AND target_type='cash_discrepancy' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_id'))=? ORDER BY id DESC LIMIT 1",[$type,(string)$id])->fetchColumn();if($case){(new CashDiscrepancyService($this->access,$this->now))->detail($actor,(int)$case);return $this->handoffRow($key,'Continue At Original Discrepancy #'.$case,'finance.php?page=discrepancies&id='.$case.'#discrepancy-action');}}
        if(str_starts_with($key,'cash:')){
            $id=substr($key,5);foreach($this->cashData()['rows'] as $row)if(str_replace(':','-',(string)$row['id'])===$id&&array_sum($row['available'])===0&&$row['outstanding']>0)return $this->handoffRow($key,'Cash Dispatched · Original Deposit Obligation Retained','finance.php?page=deposits#finance-action');
            if(ctype_digit($id)){$p=\Tivoli\Repository\ReceiptAccounting::position($this->access->repo,(int)$id);if($p['position']['method']==='momo')return $this->handoffRow($key,'Receipt Reclassified At Source · Continue Wallet Review','finance.php?page=daily#finance-action');}
        }
        return null;
    }
}
