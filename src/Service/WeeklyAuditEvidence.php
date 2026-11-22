<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Infrastructure\HrVault;
use Tivoli\Security\PermissionDenied;

/** Optional encrypted completion evidence, accessible only through its committed task. */
final class WeeklyAuditEvidence
{
    public static function directory():string{return dirname(__DIR__,2).'/storage/private/'.(getenv('TIVOLI_TEST_MODE')==='1'?'weekly-audits-test':'weekly-audits');}
    public static function checked(mixed $file):array{
        if(!is_array($file)||!is_string($file['bytes']??null))throw new RuntimeException('Invalid completion evidence.');$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$file['mime']??'']??null;if(!$ext)throw new RuntimeException('Choose PDF, JPEG or PNG evidence.');return FaultAttachment::validate($file['bytes'],'evidence.'.$ext);
    }
    public static function save(AccessService $a,int $actor,int $event,array $file):void{
        if(!$a->repo->db->inTransaction())throw new RuntimeException('Evidence requires an audit transaction.');$file=self::checked($file);require_once dirname(__DIR__,2).'/bootstrap/hr.php';$vault=new HrVault(\hr_config()['hr_key']);$key=bin2hex(random_bytes(32));$dir=self::directory();
        if(is_link($dir)||!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Private evidence storage is unavailable.');
        $cipher=$vault->seal($file['bytes'],'weekly-audit:'.$event.':'.$key);$path=$dir.'/'.$key.'.enc';$stream=fopen($path,'xb');if(!$stream)throw new RuntimeException('Could not save private evidence.');try{if(fwrite($stream,$cipher)!==strlen($cipher)||!fflush($stream))throw new RuntimeException('Could not save private evidence.');}finally{fclose($stream);}@chmod($path,0600);
        $a->audit($actor,'weekly_audit.evidence_saved','weekly_audit_evidence',(string)$event,'succeeded',['storage_key'=>$key,'mime'=>$file['mime'],'size'=>$file['size'],'sha'=>$file['sha']]);
    }
    public static function available(AccessService $a,int $event):bool{return (bool)$a->repo->run("SELECT id FROM security_audit WHERE action='weekly_audit.evidence_saved' AND target_type='weekly_audit_evidence' AND target_id=? AND outcome='succeeded' LIMIT 1",[(string)$event])->fetchColumn();}
    public static function download(AccessService $a,int $actor,int $event):array{
        return $a->transaction(function()use($a,$actor,$event){$e=$a->repo->run("SELECT target_id FROM finance_events WHERE id=? AND kind='wa_done' AND target_type='weekly_audit'",[$event])->fetch();if(!$e)throw new PermissionDenied('Completion evidence unavailable.');(new WeeklyAuditService($a))->detail($actor,(int)$e['target_id']);
            $value=$a->repo->run("SELECT details FROM security_audit WHERE action='weekly_audit.evidence_saved' AND target_type='weekly_audit_evidence' AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",[(string)$event])->fetchColumn();if(!$value)throw new RuntimeException('No evidence file is recorded.');$m=json_decode($value,true,512,JSON_THROW_ON_ERROR);$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$m['mime']??'']??null;
            if(!$ext||!preg_match('/^[a-f0-9]{64}$/D',$m['storage_key']??'')||(int)($m['size']??0)<1||(int)$m['size']>5*1024*1024)throw new RuntimeException('Evidence unavailable.');$path=self::directory().'/'.$m['storage_key'].'.enc';if(is_link(self::directory())||is_link($path)||!is_file($path))throw new RuntimeException('Evidence unavailable.');$cipher=file_get_contents($path,false,null,0,7*1024*1024);if($cipher===false)throw new RuntimeException('Evidence unavailable.');require_once dirname(__DIR__,2).'/bootstrap/hr.php';$bytes=(new HrVault(\hr_config()['hr_key']))->open($cipher,'weekly-audit:'.$event.':'.$m['storage_key']);if(strlen($bytes)!==(int)$m['size']||!hash_equals($m['sha'],hash('sha256',$bytes)))throw new RuntimeException('Evidence integrity check failed.');$a->audit($actor,'weekly_audit.evidence_downloaded','weekly_audit_evidence',(string)$event,'succeeded');return ['bytes'=>$bytes,'mime'=>$m['mime'],'filename'=>'audit-completion-'.$event.'.'.$ext];
        });
    }
}
