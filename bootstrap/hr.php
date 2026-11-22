<?php
require_once __DIR__.'/security.php';
function hr_service(): \Tivoli\Service\HrService { static $service;return $service ??= new \Tivoli\Service\HrService(access_service()); }
function hr_config(): array {
    $testing=getenv('TIVOLI_TEST_MODE')==='1';$path=dirname(__DIR__).'/config/'.($testing?'hr-test.local.php':'hr.local.php');
    if(!is_file($path)){throw new RuntimeException('Staff HR setup is required before private records can be used.');}
    $config=require $path;
    if(!is_array($config)||!preg_match('/^[a-f0-9]{64}$/D',$config['hr_key'] ?? '')){throw new RuntimeException('Staff HR encryption configuration is unavailable.');}
    return array_merge(access_config(),$config);
}
function hr_private(): \Tivoli\Service\HrPrivateService { static $service;return $service ??= new \Tivoli\Service\HrPrivateService(access_service(),hr_config(),dirname(__DIR__)); }
function hr_fields(string $action,int $id=0,int $version=0): void {
    ?><input type="hidden" name="csrf" value="<?=access_h(access_csrf())?>"><input type="hidden" name="action" value="<?=access_h($action)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="version" value="<?=$version?>"><input type="hidden" name="operation_key" value="<?=bin2hex(random_bytes(16))?>"><?php
}
function hr_input(string $label,string $name,mixed $value='',string $type='text',bool $required=false,string $attributes=''): void {
    ?><label class="hr-field"><?=access_h($label)?><?=$required?' *':''?><input type="<?=access_h($type)?>" name="<?=access_h($name)?>" value="<?=access_h($value)?>" <?=$required?'required':''?> <?=$attributes?>></label><?php
}
function hr_textarea(string $label,string $name,string $value='',bool $required=false,int $max=2000): void {
    ?><label class="hr-field"><?=access_h($label)?><?=$required?' *':''?><textarea name="<?=access_h($name)?>" rows="3" maxlength="<?=$max?>" <?=$required?'required':''?>><?=access_h($value)?></textarea></label><?php
}
function hr_select(string $label,string $name,array $options,mixed $value='',bool $required=false): void {
    ?><label class="hr-field"><?=access_h($label)?><?=$required?' *':''?><select name="<?=access_h($name)?>" <?=$required?'required':''?>><?php foreach($options as $key=>$text):?><option value="<?=access_h($key)?>" <?=(string)$key===(string)$value?'selected':''?>><?=access_h($text)?></option><?php endforeach?></select></label><?php
}
function hr_catalogue_select(string $label,string $name,array $rows,mixed $value='',bool $required=false): void {
    ?><label class="hr-field"><?=access_h($label)?><?=$required?' *':''?><select name="<?=access_h($name)?>" <?=$required?'required':''?>><option value="">Choose <?=access_h(strtolower($label))?></option><?php foreach($rows as $row):if(!$row['active']&&(string)$row['id']!==(string)$value)continue;?><option value="<?=(int)$row['id']?>" <?=(string)$row['id']===(string)$value?'selected':''?> ><?=access_h($row['name'])?><?=$row['active']?'':' · Inactive; existing selection retained'?></option><?php endforeach?></select></label><?php
}
function hr_reason(bool $password=false): void {hr_textarea('Reason','reason','',true,500);if($password)hr_input('Confirm your current password','current_password','','password',true,'autocomplete="current-password"');}
function hr_due_for_user(int $userId): bool {
    $repo=access_service()->repo;
    if(!$repo->run("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_schema'")->fetchColumn())return false;
    if($repo->run("SELECT id FROM hr_staff WHERE user_id=? AND status IN ('exited','suspended')",[$userId])->fetchColumn())return true;
    if($repo->run("SELECT id FROM hr_staff WHERE user_id=? AND status='exit_scheduled' AND exit_at<=UTC_DATE()",[$userId])->fetchColumn()){
        try{hr_service()->processDueExits($userId);}catch(RuntimeException $deferred){return true;}
        return true;
    }
    return false;
}

function hr_uploaded_entries(array $file): array {
    if(!is_array($file['error'] ?? null))return [$file];
    if(count($file['error'])>10)throw new RuntimeException('Attach at most ten ID documents at a time.');
    $result=[];foreach(array_keys($file['error']) as $index){$entry=[];foreach(['name','type','tmp_name','error','size'] as $key){if(!isset($file[$key])||!is_array($file[$key])||!isset($file[$key][$index])||is_array($file[$key][$index]))throw new RuntimeException('Invalid attachment input.');$entry[$key]=$file[$key][$index];}$result[]=$entry;}return $result;
}
