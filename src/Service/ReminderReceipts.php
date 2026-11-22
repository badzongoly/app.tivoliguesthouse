<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Security\PermissionDenied;

trait ReminderReceipts
{
    public function receiveStatus(string $body,string $signature):array{
        $secret=(string)($this->config['notifications']['whatsapp']['app_secret']??'');if(strlen($body)>100000||$secret===''||!hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$signature))throw new PermissionDenied('Invalid delivery receipt.');
        $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);$owner=$this->sources->owner();if(!$owner)throw new RuntimeException('Notification owner setup is unavailable.');$processed=0;
        foreach($data['entry']??[] as $entry)foreach($entry['changes']??[] as $change){$value=$change['value']??[];if((string)($value['metadata']['phone_number_id']??'')!==(string)($this->config['notifications']['whatsapp']['sender_id']??''))continue;
            foreach($value['statuses']??[] as $status){$provider=$status['id']??'';$kind=$status['status']??'';$time=$status['timestamp']??'';if(!is_string($provider)||$provider===''||strlen($provider)>512||!in_array($kind,['sent','delivered','read','failed'],true)||!is_string($time)||!ctype_digit($time))continue;$age=strtotime($this->now().' UTC')-(int)$time;if($age>86400||$age< -300)continue;
                $processed+=$this->access->transaction(function()use($owner,$provider,$kind,$status){
                    $matched=null;$attempt='';$candidate=$status['biz_opaque_callback_data']??'';
                    foreach([...$this->journal->events('n_delivery_state'),...$this->journal->events('n_delivery_receipt')] as $e){$d=$e['data'];if(($d['provider_id']??'')===$provider||($candidate!==''&&($d['attempt']??'')===$candidate&&$e['kind']==='nt_send_intent')){$root=$this->journal->find((int)$e['target_id']);if($root['kind']==='nt_delivery'&&$root['data']['channel']==='whatsapp'){$matched=(int)$root['id'];$attempt=$d['attempt']??$attempt;}}}
                    if(!$matched)return 0;$last=$this->journal->latest('n_delivery_state',(string)$matched);$currentAttempt='';foreach($this->journal->events('n_delivery_state',(string)$matched) as $state)if($state['kind']==='nt_send_intent')$currentAttempt=$state['data']['attempt'];$stale=$attempt!==''&&$currentAttempt!==''&&!hash_equals($currentAttempt,$attempt);if($attempt==='')$attempt=$last['data']['attempt']??'';
                    $claim=$this->journal->unique('n_callback',[$provider,$kind],function($hash)use($owner,$matched,$provider,$kind,$attempt,$last,$stale){$state=$kind==='sent'?'accepted':($kind==='failed'?'failed':'delivered');if(($last['data']['state']??'')==='delivered')$state='delivered';$apply=!$stale||in_array($kind,['delivered','read'],true);$d=['state'=>$state,'provider_id'=>$provider,'provider_status'=>$kind,'attempt'=>$attempt,'applied'=>$apply];if($state==='failed')$d['next_at']=(new \DateTimeImmutable($this->now(),new \DateTimeZone('UTC')))->modify('+10 minutes')->format('Y-m-d H:i:s');$id=$this->journal->event($owner,'nt_callback',$apply?'n_delivery_state':'n_delivery_receipt',(string)$matched,$d);$this->access->audit($owner,'reminders.provider_receipt','notification_delivery',(string)$matched,'recorded',['state'=>$state]);return $id;});return (int)$claim['created'];
                });
            }
        }return ['processed'=>$processed];
    }
}
