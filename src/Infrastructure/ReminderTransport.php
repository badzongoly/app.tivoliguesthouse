<?php
namespace Tivoli\Infrastructure;
use RuntimeException;

/** Optional real adapters. Tests and localhost cannot transmit messages. */
final class ReminderTransport
{
    public function __construct(private readonly array $config){}
    public function readiness():array{
        $n=$this->config['notifications']??[];$url=(string)($n['public_url']??'');$host=parse_url($url,PHP_URL_HOST);$base=(string)($this->config['base_url']??'');$remote=rtrim($base,'/')===rtrim($url,'/')&&parse_url($url,PHP_URL_SCHEME)==='https'&&$host&&!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)&&!filter_var($host,FILTER_VALIDATE_IP);$smtp=$this->config['smtp']??[];$wa=$n['whatsapp']??[];
        return ['email'=>$remote&&($n['email_enabled']??false)===true&&class_exists(\PHPMailer\PHPMailer\PHPMailer::class)&&!empty($smtp['host'])&&!empty($smtp['username'])&&!empty($smtp['password'])&&filter_var($smtp['from_email']??'',FILTER_VALIDATE_EMAIL)&&in_array($smtp['encryption']??'', ['tls','smtps'],true),'whatsapp'=>$remote&&($n['whatsapp_enabled']??false)===true&&extension_loaded('curl')&&preg_match('/^v[0-9]+\.0$/D',$wa['version']??'')&&preg_match('/^[0-9]+$/D',$wa['sender_id']??'')&&!empty($wa['access_token'])&&!empty($wa['app_secret'])&&preg_match('/^[a-z0-9_]+$/D',$wa['template']??'')];
    }
    public function send(array $job):array{
        if(getenv('TIVOLI_TEST_MODE')==='1')throw new RuntimeException('Real notification delivery is disabled in tests.');if(!$this->readiness()[$job['channel']])throw new RuntimeException('Delivery service is not configured.');$url=rtrim($this->config['notifications']['public_url'],'/').'/reminders.php';$body="Tivoli: You have operational work to review. Sign in to view your tasks.\n".$url;
        if($job['channel']==='email'){
            $s=$this->config['smtp'];$m=new \PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=$s['host'];$m->Port=(int)($s['port']??587);$m->SMTPAuth=true;$m->Username=$s['username'];$m->Password=$s['password'];$m->SMTPSecure=$s['encryption'];$m->SMTPDebug=0;$m->Timeout=10;$m->Timelimit=15;$m->CharSet='UTF-8';$m->setFrom($s['from_email'],$s['from_name']??'Tivoli Guesthouse');$m->addAddress($job['destination']);$m->Subject='Tivoli Operational Reminder';$m->Body=$body;
            try{$m->send();return ['state'=>'accepted','provider_id'=>$m->getLastMessageID()];}catch(\Throwable){return ['state'=>'unknown'];}
        }
        $w=$this->config['notifications']['whatsapp'];$payload=['messaging_product'=>'whatsapp','to'=>ltrim($job['destination'],'+'),'type'=>'template','biz_opaque_callback_data'=>$job['attempt'],'template'=>['name'=>$w['template'],'language'=>['code'=>$w['language']??'en']]];
        $ch=curl_init('https://graph.facebook.com/'.$w['version'].'/'.$w['sender_id'].'/messages');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$w['access_token'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);$reply=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_errno($ch);curl_close($ch);if($error||$reply===false)return ['state'=>'unknown'];$data=json_decode($reply,true);if($code>=200&&$code<300&&is_string($data['messages'][0]['id']??null))return ['state'=>'accepted','provider_id'=>$data['messages'][0]['id']];return ['state'=>$code>=400&&$code<500?'failed':'unknown'];
    }
}
