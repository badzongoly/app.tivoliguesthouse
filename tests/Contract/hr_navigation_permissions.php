<?php
// Render actual HR partials with synthetic state/capabilities; no bootstrap/DB.
$root=dirname(__DIR__,2);$checks=0;$testPermissions=[];$actor=11;$id=91;$version=7;
$can=static fn(string $code):bool=>in_array($code,$GLOBALS['testPermissions'],true);
function access_h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function access_url(string $p=''):string{return '/'.$p;}
function display_date(mixed $s):string{return (string)$s;}
function hr_fields(string $action,int $id=0,int $version=0):void{echo '<input name="action" value="'.access_h($action).'"><input name="id" value="'.$id.'"><input name="version" value="'.$version.'"><input name="operation_key" value="0123456789abcdef0123456789abcdef"><input name="csrf" value="synthetic">';}
function hr_reason(bool $password=false):void{echo '<textarea name="reason" required></textarea>';if($password)hr_input('Password','current_password','','password',true);}
function hr_input(string $label,string $name,mixed $value='',string $type='text',bool $required=false,string $extra=''):void{echo '<input name="'.$name.'" type="'.$type.'"'.($required?' required':'').'>';}
function hr_select(string $label,string $name,array $options,mixed $value='',bool $required=false):void{echo '<select name="'.$name.'"></select>';}
function hr_textarea(string $label,string $name,string $value='',bool $required=false,int $max=2000):void{echo '<textarea name="'.$name.'"'.($required?' required':'').'></textarea>';}
function hr_catalogue_select(string $label,string $name,array $rows,mixed $value='',bool $required=false):void{echo '<select name="'.$name.'"></select>';}
function check(bool $yes,string $label):void{global $checks;if(!$yes)throw new RuntimeException($label);++$checks;echo 'PASS '.$label."\n";}
function actions(string $html):array{preg_match_all('/name="action" value="([^"]+)"/',$html,$m);$out=$m[1];sort($out);return $out;}
function render(string $view):string{extract($GLOBALS,EXTR_SKIP);if(is_file($root.'/templates/hr/profile-context.php'))require $root.'/templates/hr/profile-context.php';ob_start();require $root.'/templates/hr/'.$view.'.php';return ob_get_clean();}
$all=['hr.view','hr.manage','hr.approve','hr.link','hr.self','hr.documents','hr.salary','hr.settings','users.manage'];
foreach([
 ['draft',$all,null,[],['activate','return_changes','offboard']],
 ['changes_requested',$all,null,[],['activate','return_changes','offboard']],
 ['active',$all,22,[],['confirm_probation','suspend','offboard']],
 ['active',$all,22,['probation_confirmed_at'=>'2026-11-01'],['suspend','offboard']],
 ['suspended',$all,22,[],['resume','offboard']],
 ['exit_scheduled',$all,22,[],['offboard']],
 ['exited',$all,22,[],[]],
 ['active',$all,11,[],['confirm_probation']],
 ['active',['hr.view','hr.manage'],22,[],[]],
 ['draft',['hr.view','hr.approve'],null,[],['activate','return_changes']],
 ['active',['hr.self'],11,[],[]],
] as [$status,$testPermissions,$linked,$data,$expected]){
 $staff=['id'=>$id,'version'=>$version,'status'=>$status,'user_id'=>$linked,'data'=>$data];$snapshot=['staff'=>$staff,'items'=>[],'leaves'=>[],'history'=>[]];
 $out=render('employment');sort($expected);check(actions($out)===$expected,'employment '.$status.' actions match state and capabilities '.implode(',',$testPermissions));
 check(!str_contains($out,'name="action" value="offboard"')||(str_contains($out,'data-hr-rich-form')&&str_contains($out,'name="current_password"')),'offboarding retains rich reason and password gate');
}
$data=['email'=>'synthetic-new@example.invalid'];$candidates=[];
foreach([
 ['draft',$all,['link_user']],['active',$all,['link_user','new_account']],['suspended',$all,[]],['exited',$all,[]],['exit_scheduled',$all,[]],['active',['hr.view','hr.link'],[]]
] as [$status,$testPermissions,$expected]){
 $staff=['id'=>$id,'version'=>$version,'status'=>$status,'user_id'=>null,'data'=>$data];$out=render('access');sort($expected);check(actions($out)===$expected,'account choices '.$status.' '.implode(',',$testPermissions));
}
$testPermissions=$all;$staff=['id'=>$id,'version'=>$version,'status'=>'active','user_id'=>22,'data'=>[]];$data=[];
$snapshot=['staff'=>$staff,'leaves'=>[['id'=>1,'status'=>'pending','requested_by'=>11],['id'=>2,'status'=>'pending','requested_by'=>22],['id'=>3,'status'=>'approved','requested_by'=>22]],'items'=>[]];
$out=render('leave');check(substr_count($out,'name="action" value="review_leave"')===1,'own requests and finalized leave do not repeat approval actions');
$staff['status']='exited';$out=render('leave');check(!str_contains($out,'name="action" value="request_leave"'),'exited staff retains leave history without new request form');
$snapshot['items']=[];$out=render('overview');check(!str_contains($out,'name="action" value="issue_item"'),'no new item issue after exit');
$staff=['id'=>0,'version'=>0,'status'=>'draft','user_id'=>null,'data'=>[]];$data=[];$catalogue=['document'=>[]];$out=render('onboarding');
check(substr_count($out,'data-hr-step=')===5&&substr_count($out,'data-hr-panel=')===5,'five onboarding steps retain actual information capture');
check(str_contains($out,'>Cancel</a>')&&str_contains($out,'type="submit" data-hr-save'),'wizard offers navigation-only Cancel beside one save action');
check(str_contains($out,'name="contract_file"')&&str_contains($out,'name="identity_file[]"'),'optional contract and multiple ID uploads remain');
echo "TOTAL $checks HR presentation capability assertions passed\n";
