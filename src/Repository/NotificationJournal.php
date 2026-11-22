<?php
namespace Tivoli\Repository;
use RuntimeException;
use Tivoli\Service\AccessService;
use Tivoli\Infrastructure\ResetEnvelope;

/** Encrypted operational records, using existing durable claim/operation constraints. */
final class NotificationJournal
{
    public function __construct(public readonly AccessService $access,private readonly string $secret,private readonly string $now){}
    public function hash(mixed $value):string{return hash_hmac('sha256',json_encode($value,JSON_THROW_ON_ERROR),'reminders|'.$this->secret);}
    private function key():string{return hash_hmac('sha256','notification-journal-v1',$this->secret);}
    public function event(int $actor,string $kind,string $type,string $target,array $data):int{
        if(!$this->access->repo->db->inTransaction()||!str_starts_with($kind,'nt_')||!str_starts_with($type,'n_')||strlen($kind)>32||strlen($type)>24||strlen($target)>80)throw new RuntimeException('Invalid notification transaction.');
        $payload=json_encode(['version'=>1,'envelope'=>ResetEnvelope::encode($data,$this->key())],JSON_THROW_ON_ERROR);
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,0,?,?,?,?)',[$kind,$type,$target,substr($this->now,0,10),$actor,$payload,$this->now]);return (int)$this->access->repo->db->lastInsertId();
    }
    public function decode(array $row):array{
        if(!str_starts_with($row['kind'],'nt_')||!str_starts_with($row['target_type'],'n_'))throw new RuntimeException('Invalid notification record.');
        $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);if(($p['version']??null)!==1||!is_string($p['envelope']??null))throw new RuntimeException('Notification history requires review.');
        $row['data']=ResetEnvelope::decode($p['envelope'],$this->key());$row['id']=(int)$row['id'];$row['actor_id']=(int)$row['actor_id'];return $row;
    }
    public function events(string $type,?string $target=null):array{
        if(!str_starts_with($type,'n_'))throw new RuntimeException('Invalid notification source.');
        $rows=$this->access->repo->run('SELECT * FROM finance_events WHERE target_type=?'.($target!==null?' AND target_id=?':'').' ORDER BY id',$target!==null?[$type,$target]:[$type])->fetchAll();return array_map(fn($e)=>$this->decode($e),$rows);
    }
    public function find(int $id):array{$e=$this->access->repo->run('SELECT * FROM finance_events WHERE id=?',[$id])->fetch();if(!$e)throw new RuntimeException('Notification record unavailable.');return $this->decode($e);}
    public function latest(string $type,string $target):?array{$e=$this->access->repo->run('SELECT * FROM finance_events WHERE target_type=? AND target_id=? ORDER BY id DESC LIMIT 1',[$type,$target])->fetch();return $e?$this->decode($e):null;}
    public function unique(string $scope,array $identity,callable $create):array{
        if(!$this->access->repo->db->inTransaction()||!str_starts_with($scope,'n_')||strlen($scope)>24)throw new RuntimeException('Notification claim requires its transaction.');$hash=$this->hash([$scope,$identity]);$prior=$this->access->repo->run('SELECT event_id FROM finance_claims WHERE scope=? AND identity_hash=?',[$scope,$hash])->fetchColumn();if($prior)return ['id'=>(int)$prior,'created'=>false];
        $id=$create($hash);$this->access->repo->run('INSERT INTO finance_claims VALUES(?,?,?)',[$scope,$hash,$id]);return ['id'=>$id,'created'=>true];
    }
    public function operation(int $actor,string $action,array $input,string $key,callable $write):array{
        if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the reminder form before trying again.');$fingerprint=$this->hash(['notification',$action,$input]);
        return $this->access->transaction(function()use($actor,$action,$key,$fingerprint,$write){
            $u=$this->access->repo->user($actor);if(!$u||!$u['active'])throw new \Tivoli\Security\PermissionDenied('This account is unavailable.');
            $prior=$this->access->repo->run('SELECT fingerprint,event_id FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint))throw new RuntimeException('This operation key belongs to a different action. Reload the form.');$event=$this->find((int)$prior['event_id']);return $event['data']['result']+['replayed'=>true];}
            $result=$write();$id=$this->event($actor,'nt_operation','n_operation',(string)$actor,['action'=>$action,'result'=>$result]);$this->access->repo->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$id]);$this->access->audit($actor,'reminders.'.$action,'notification',isset($result['id'])?(string)$result['id']:null,'succeeded',['operation_event'=>$id]);return $result+['replayed'=>false];
        });
    }
}
