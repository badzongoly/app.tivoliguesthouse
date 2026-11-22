<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Repository\NotificationJournal;
use Tivoli\Security\PermissionDenied;

trait ReminderRoutine
{
    private function routineJournal():NotificationJournal{return new NotificationJournal($this->access,$this->config['audit_key'],$this->now);}
    public function ensureRoutines(int $owner):void{
        if($this->onlyKeys!==null)return;if(!$owner||$owner!==$this->owner()||!$this->access->repo->db->inTransaction())throw new PermissionDenied('Scheduled checks require the owner operational lock.');$j=$this->routineJournal();
        $start=$j->unique('n_start',['application'],fn($hash)=>$j->event($owner,'nt_started','n_started','application',['started_at'=>$this->now]));$activated=$j->find($start['id'])['data']['started_at'];$today=substr($this->now,0,10);$week=WeeklyAreaAudit::week($today);$rules=$j->latest('n_rules','global');$time=$rules['data']['daily_time']??'09:00';
        // Only the current/preceding routine is caught up. Existing older obligations remain retained.
        foreach([$week,(new \DateTimeImmutable($week,new \DateTimeZone('UTC')))->modify('-7 days')->format('Y-m-d')] as $date){$due=(new \DateTimeImmutable($date,new \DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');if($due.' '.$time.':00'> $this->now||$due.' 23:59:59'<$activated)continue;
            $j->unique('n_routine',[$date],function($hash)use($j,$owner,$date,$due){$rooms=array_map('intval',$this->access->repo->run('SELECT id FROM rooms ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));$areas=array_column(array_filter(AuditAreas::current($this->access->repo)['entries'],fn($a)=>$a['active']),'id');return $j->event($owner,'nt_routine','n_routine',$date,['week'=>$date,'due'=>$due,'rooms'=>substr($this->now,0,10)>$due?[]:$rooms,'areas'=>substr($this->now,0,10)>$due?[]:$areas,'scope_verified'=>substr($this->now,0,10)<=$due]);});
        }
    }
    private function routineRows():array{
        $j=$this->routineJournal();$out=[];foreach($j->events('n_routine') as $event){$row=$this->routineRow($event['data']+['captured_at'=>$event['created_at']]);if($this->accepts($row['key']))$out[$row['key']]=$row;}return $out;
    }
    private function routineRow(array $scope):array{
        $j=$this->routineJournal();$rules=$j->latest('n_rules','global');$auditor=(int)($rules['data']['weekly_auditor']??0);$eligible=$auditor>0&&$this->active($auditor);if($eligible)try{(new WeeklyAuditService($this->access,$this->now))->requireAuditor($auditor);}catch(\Throwable){$eligible=false;}
        $evidenced=$scope['scope_verified']??(substr($scope['captured_at']??'',0,10)<=$scope['due']);
        if(!$evidenced)return ['key'=>'routine:'.$scope['week'],'category'=>'Property','title'=>'Weekly Audit Scope Review · '.\display_date($scope['week']),'state'=>'Historical Scope Requires Review','resolved'=>false,'decision'=>true,'responsible_id'=>$this->owner(),'due'=>$scope['due'],'critical'=>false,'unavailable'=>true,'history'=>[],'path'=>'weekly-audits.php?page=room&range=all#audit-action','facts'=>['Scope Status'=>'Historical Scope Unknown']];
        $start=$scope['week'].' 00:00:00';$end=(new \DateTimeImmutable($scope['week'],new \DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d').' 00:00:00';$rooms=array_map('intval',$this->access->repo->run('SELECT DISTINCT room_id FROM stay_room_audits WHERE created_at>=? AND created_at<?',[$start,$end])->fetchAll(\PDO::FETCH_COLUMN));$areas=[];
        foreach($this->access->repo->run("SELECT details FROM security_audit WHERE action='stay.area_audit' AND target_type='area_audit' AND outcome='succeeded' AND occurred_at>=? AND occurred_at<?",[$start,$end])->fetchAll() as $e){$d=json_decode($e['details'],true,512,JSON_THROW_ON_ERROR);if(isset($d['area_id']))$areas[]=$d['area_id'];}
        $missingRooms=array_diff($scope['rooms'],$rooms);$missingAreas=array_diff($scope['areas'],$areas);$hasScope=(bool)($scope['rooms']||$scope['areas']);$closed=$hasScope&&!$missingRooms&&!$missingAreas;
        return ['key'=>'routine:'.$scope['week'],'category'=>'Property','title'=>'Weekly Room & Area Audits · '.\display_date($scope['week']),'state'=>!$hasScope?'Audit Scope Requires Review':(!$eligible?'Choose An Authorized Weekly Auditor':($closed?'Required Audits Recorded':'Weekly Audits Due')),'resolved'=>$closed,'decision'=>!$eligible,'responsible_id'=>$eligible?$auditor:$this->owner(),'due'=>$scope['due'],'critical'=>false,'unavailable'=>!$hasScope,'history'=>[],'path'=>'weekly-audits.php?page=room&range=all#audit-action','facts'=>['Outstanding Rooms'=>count($missingRooms),'Outstanding Areas'=>count($missingAreas)]];
    }
    private function inspectRoutine(int $actor,string $key):array{
        $rows=$this->routineRows();$row=$rows[$key]??throw new RuntimeException('Original weekly routine unavailable.');if($actor!==$this->owner()&&$row['responsible_id']!==$actor)throw new PermissionDenied('This audit routine belongs to its current authorized auditor.');$row['actionable']=!$row['resolved'];return $row;
    }
}
