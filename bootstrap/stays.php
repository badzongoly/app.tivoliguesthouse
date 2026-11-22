<?php
require_once __DIR__.'/security.php';
function stay_service(): \Tivoli\Service\StayService { static $service; return $service ??= new \Tivoli\Service\StayService(access_service()); }
function stay_url(array $query=[]): string { return access_url('occupancy.php').($query?'?'.http_build_query($query):''); }
function stay_fields(string $action,int $bookingId=0,array $extra=[]): void {
    global $error; $retryKey=($error && ($_POST['action'] ?? '')===$action && preg_match('/^[a-f0-9]{32}$/D',$_POST['operation_key'] ?? ''))?$_POST['operation_key']:bin2hex(random_bytes(16));
    $fields=['csrf'=>access_csrf(),'action'=>$action,'booking_id'=>(string)$bookingId,'operation_key'=>$retryKey]+$extra;
    foreach($fields as $name=>$value){?><input type="hidden" name="<?=access_h($name)?>" value="<?=access_h($value)?>"><?php }
}
function stay_input(string $label,string $name,mixed $value='',string $type='text',bool $required=false,string $attributes=''): void {
    global $formValues;if(isset($formValues[$name])&&is_string($formValues[$name])&&$type!=='file')$value=$formValues[$name];
    ?><label class="stay-field"><?=access_h(stay_label($label))?><?=$required?' *':''?><input type="<?=access_h($type)?>" name="<?=access_h($name)?>" value="<?=access_h($value)?>" <?=$required?'required':''?> <?=$attributes?>></label><?php
}
function stay_select(string $label,string $name,array $options,mixed $value='',bool $required=false): void {
    global $formValues;if(isset($formValues[$name])&&is_string($formValues[$name]))$value=$formValues[$name];
    ?><label class="stay-field"><?=access_h(stay_label($label))?><?=$required?' *':''?><select name="<?=access_h($name)?>" <?=$required?'required':''?>><?php foreach($options as $key=>$text):?><option value="<?=access_h($key)?>" <?=(string)$key===(string)$value?'selected':''?>><?=access_h($text)?></option><?php endforeach?></select></label><?php
}
function stay_textarea(string $label,string $name,string $value='',bool $required=false): void {
    ?><label class="stay-field stay-wide"><?=access_h(stay_label($label))?><?=$required?' *':''?><textarea name="<?=access_h($name)?>" rows="3" maxlength="2000" <?=$required?'required':''?>><?=access_h($value)?></textarea></label><?php
}
function stay_check(string $label,string $name,bool $required=false,bool $checked=false): void {
    ?><label class="stay-check"><input type="checkbox" name="<?=access_h($name)?>" value="1" <?=$checked?'checked':''?> <?=$required?'required':''?>><?=access_h(stay_label($label))?></label><?php
}
function stay_money(int|string $value): string {return '₵'.\Tivoli\Service\StayService::money((int)$value);}
function stay_decimal(mixed $value): string {
    $text=(string)$value; if(!preg_match('/^-?\d+(?:\.\d{1,2})?$/D',$text))return '—';
    [$whole,$fraction]=array_pad(explode('.',$text,2),2,'');return '₵'.$whole.'.'.str_pad($fraction,2,'0');
}
function stay_countries(): array { $names=json_decode(file_get_contents(dirname(__DIR__).'/resources/countries.json'),true,512,JSON_THROW_ON_ERROR);return array_combine($names,$names); }
require_once __DIR__.'/stay-identity.php';

function stay_label(string $label): string {
    $label=mb_convert_case($label,MB_CASE_TITLE,'UTF-8');
    return preg_replace_callback('/\b(Id|Ghs|Utc|Pdf|Jpeg|Png|Csv|Hr|Mb|Momo)\b/u',static fn($m)=>['Id'=>'ID','Ghs'=>'GHS','Utc'=>'UTC','Pdf'=>'PDF','Jpeg'=>'JPEG','Png'=>'PNG','Csv'=>'CSV','Hr'=>'HR','Mb'=>'MB','Momo'=>'MoMo'][$m[0]],$label);
}
function stay_room_action(array $room): ?array {
    if(!empty($room['booking_id'])||!empty($room['blocked'])||$room['status']==='maintenance')return null;
    if($room['status']==='dirty'&&$room['ready_state']!=='awaiting_inspection')return ['Cleaning Complete','clean'];
    if(in_array($room['ready_state'],['unverified','awaiting_inspection'],true)&&in_array($room['status'],['vacant','dirty'],true))return ['Inspect & Release Room','inspect'];
    return null;
}
function stay_event_label(string $action): string {
    return match($action){
        'housekeeping.completed'=>'Cleaning Completed','housekeeping.inspect'=>'Housekeeping Inspection Recorded','housekeeping.start'=>'Housekeeping Started','housekeeping.progress'=>'Cleaning Progress Saved','housekeeping.request_exception'=>'Self-Inspection Requested','housekeeping.decide_exception'=>'Self-Inspection Decision','housekeeping.followup'=>'Self-Inspection Follow-Up','housekeeping.restart'=>'Housekeeping Restarted','housekeeping.failed'=>'Housekeeping Action Rejected',
        'stay.checkin'=>'Guest Checked In','stay.checkout'=>'Guest Checked Out',
        'stay.clean'=>'Cleaning Completed','stay.inspect'=>'Room Inspected & Released',
        'stay.payment','stay.clear_balance'=>'Payment Recorded','stay.extra'=>'Extra Purchased',
        'stay.allow_checkout'=>'Checkout With Balance Approved',
        'stay.extra_save'=>'Extras Catalogue Updated','stay.room_save'=>'Room Master Updated',
        'stay.fault'=>'Room Fault Reported','stay.repair'=>'Repair Recorded','stay.release_fault'=>'Repair Inspection Released',
        'stay.area_audit'=>'Area Audit Recorded','stay.area_followup'=>'Area Audit Follow-Up','stay.room_audit'=>'Room Audit Recorded','stay.stock_in'=>'Stock Received','stay.stock_count'=>'Stock Count Recorded',
        'stay.incidental'=>'Incidental Requested','stay.review_incidental'=>'Incidental Reviewed','stay.refund'=>'Refund Recorded',
        'stay.action_failed'=>'Action Rejected','stay.identity_downloaded'=>'Private ID Downloaded',
        default=>stay_label(str_replace(['stay.','_'],['',' '],$action))
    };
}
