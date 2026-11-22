<?php
namespace Tivoli\Service;

use RuntimeException;
use Tivoli\Infrastructure\HrVault;
use Tivoli\Security\PermissionDenied;

/** Encrypted private files; committed append-only audit metadata is the attachment registry. */
final class FaultAttachment
{
    public static function validate(string $bytes,string $filename): array
    {
        if(strlen($bytes)<1||strlen($bytes)>5*1024*1024)throw new RuntimeException('Fault attachments must be non-empty PDF, JPEG or PNG files up to 5 MB.');
        $allowed=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!isset($allowed[$ext])||$allowed[$ext]!==$mime)throw new RuntimeException('The attachment content must match its PDF, JPEG or PNG filename.');
        if($mime==='application/pdf'){if(!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Invalid PDF attachment.');}
        elseif(!($image=@getimagesizefromstring($bytes))||($image['mime'] ?? '')!==$mime)throw new RuntimeException('Invalid image attachment.');
        return ['bytes'=>$bytes,'mime'=>$mime,'size'=>strlen($bytes),'sha'=>hash('sha256',$bytes)];
    }
    public static function uploaded(array $file): array
    {
        if(($file['error'] ?? null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name'] ?? null)||!is_string($file['name'] ?? null)||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('The fault attachment did not upload successfully. Choose it again or continue without it.');
        $bytes=file_get_contents($file['tmp_name'],false,null,0,5*1024*1024+1);
        if($bytes===false)throw new RuntimeException('Could not read fault attachment.');
        return self::validate($bytes,$file['name']);
    }
    public static function directory(): string
    {
        return dirname(__DIR__,2).'/storage/private/'.(getenv('TIVOLI_TEST_MODE')==='1'?'room-faults-test':'room-faults');
    }
    /** Inside the same transaction as the fault and required audit; no public storage. */
    public static function save(AccessService $access,int $actor,int $fault,array $file,string $kind='fault'): void
    {
        if(!in_array($kind,['fault','repair'],true))throw new RuntimeException('Invalid evidence type.');
        $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$file['mime'] ?? ''] ?? null;
        if(!$ext||!is_string($file['bytes'] ?? null))throw new RuntimeException('Invalid private fault attachment.');
        $verified=self::validate($file['bytes'],'attachment.'.$ext);
        $key=bin2hex(random_bytes(32));$dir=self::directory();
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Private fault storage is unavailable.');
        if(is_link($dir))throw new RuntimeException('Private fault storage is unavailable.');
        $vault=new HrVault(\hr_config()['hr_key']);$cipher=$vault->seal($verified['bytes'],($kind==='fault'?'room-fault:':'room-repair:').$fault.':'.$key);
        $path=$dir.'/'.$key.'.enc';$stream=fopen($path,'xb');if(!$stream)throw new RuntimeException('Could not save fault attachment.');
        try{if(fwrite($stream,$cipher)!==strlen($cipher)||!fflush($stream))throw new RuntimeException('Could not save fault attachment.');}finally{fclose($stream);}@chmod($path,0600);
        // An uncommitted orphan is encrypted and cannot be downloaded: only this registry grants access.
        $access->audit($actor,'stay.'.$kind.'_attachment_saved','room_fault',(string)$fault,'succeeded',['storage_key'=>$key,'mime'=>$verified['mime'],'size'=>$verified['size'],'sha'=>$verified['sha']]);
    }
    public static function download(AccessService $access,int $actor,int $fault,string $kind='fault'): array
    {
        if(!in_array($kind,['fault','repair'],true))throw new RuntimeException('Invalid evidence type.');
        try{return $access->transaction(function()use($access,$actor,$fault,$kind):array{
            $access->requirePrivilege($actor,'bookings.manage');$access->requirePrivilege($actor,'rooms.manage');
            if(!$access->repo->isAdmin($actor))throw new PermissionDenied('Only administrators can download fault attachments.');
            $row=$access->repo->run("SELECT a.details FROM security_audit a JOIN stay_faults f ON f.id=? WHERE a.target_type='room_fault' AND a.target_id=? AND a.action=? AND a.outcome='succeeded' ORDER BY a.id DESC LIMIT 1",[$fault,(string)$fault,'stay.'.$kind.'_attachment_saved'])->fetch();
            if(!$row)throw new RuntimeException('Fault attachment is unavailable.');$meta=json_decode($row['details'],true,512,JSON_THROW_ON_ERROR);
            $extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
            if(!preg_match('/^[a-f0-9]{64}$/D',$meta['storage_key'] ?? '')||!isset($extensions[$meta['mime'] ?? ''])||(int)$meta['size']<1||(int)$meta['size']>5*1024*1024)throw new RuntimeException('Fault attachment is unavailable.');
            $path=self::directory().'/'.$meta['storage_key'].'.enc';if(!is_file($path)||is_link($path))throw new RuntimeException('Fault attachment is unavailable.');
            $cipher=file_get_contents($path,false,null,0,7*1024*1024);if($cipher===false)throw new RuntimeException('Fault attachment is unavailable.');
            $vault=new HrVault(\hr_config()['hr_key']);$bytes=$vault->open($cipher,($kind==='fault'?'room-fault:':'room-repair:').$fault.':'.$meta['storage_key']);
            if(strlen($bytes)!==(int)$meta['size']||!hash_equals($meta['sha'],hash('sha256',$bytes)))throw new RuntimeException('Fault attachment integrity check failed.');
            $access->audit($actor,'stay.'.$kind.'_attachment_downloaded','room_fault',(string)$fault,'succeeded');
            return ['bytes'=>$bytes,'mime'=>$meta['mime'],'filename'=>$kind.'-'.$fault.'.'.$extensions[$meta['mime']]];
        });}catch(\Throwable $e){$access->audit($actor,'stay.'.$kind.'_attachment_download_failed','room_fault',(string)$fault,'denied',['reason'=>'permission_missing_or_storage_integrity_failure']);throw $e;}
    }
}
