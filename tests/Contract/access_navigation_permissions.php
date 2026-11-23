<?php
// Actual presentation partials with explicit synthetic permissions; no DB/bootstrap.
$root=dirname(__DIR__,2);$n=0;$error='';
function access_url(string $p=''):string{return '/'.$p;}
function access_h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function access_management_fields(string $action,int $id=0):void{echo '<input name="action" value="'.$action.'"><input name="id" value="'.$id.'"><input name="csrf" value="synthetic"><input name="reason" required><input name="current_password" type="password" required>';}
function check(bool $yes,string $label):void{global $n;if(!$yes)throw new RuntimeException($label);++$n;echo 'PASS '.$label."\n";}
$user=['id'=>1];$users=[['id'=>1,'name'=>'Self','email'=>'self@example.invalid','role'=>'admin','active'=>1,'must_change_password'=>0],['id'=>2,'name'=>'Clerk','email'=>'clerk@example.invalid','role'=>'clerk','active'=>1,'must_change_password'=>1],['id'=>3,'name'=>'Other Administrator','email'=>'other@example.invalid','role'=>'admin','active'=>0,'must_change_password'=>0]];
$roles=[['id'=>1,'name'=>'admin','description'=>'Administrator','system_role'=>1],['id'=>2,'name'=>'clerk','description'=>'Clerk','system_role'=>1],['id'=>3,'name'=>'manager','description'=>'Delegated','system_role'=>0]];
$privileges=[['id'=>1,'code'=>'users.manage','description'=>'Accounts'],['id'=>2,'code'=>'roles.assign','description'=>'Assign'],['id'=>3,'code'=>'roles.manage','description'=>'Roles'],['id'=>4,'code'=>'privileges.manage','description'=>'Definitions'],['id'=>5,'code'=>'bookings.manage','description'=>'Bookings']];
$rolePrivileges=[1=>[1,2,3,4,5],2=>[5],3=>[1,2,5]];
$userRoles=[1=>[1],2=>[2],3=>[1]];
foreach([
 ['administrator',[1],['users.manage','roles.assign','roles.manage','privileges.manage','bookings.manage'],1,4,true],
 ['delegate',[3],['users.manage','roles.assign','bookings.manage'],1,0,true],
 ['assigner',[3],['roles.assign','bookings.manage'],0,0,false],
 ['roles-only',[3],['roles.manage'],0,0,false],
 ['definitions-only',[3],['privileges.manage'],0,0,false],
] as [$label,$actorRoles,$permissions,$selfState,$higherForms,$canCreate]){
 $userRoles[1]=$actorRoles;$can=static fn(string $p):bool=>in_array($p,$GLOBALS['permissions'],true);
 $_GET['account']=0;if(is_file($root.'/templates/access/context.php'))require $root.'/templates/access/context.php';
 ob_start();if(is_file($root.'/templates/access/users.php'))require $root.'/templates/access/users.php';else{$s=file_get_contents($root.'/templates/access/manage.php');$s=substr($s,strpos($s,'<section id="staff">'));$s=substr($s,0,strpos($s,'<section id="roles">'));eval('?>'.$s);}$html=ob_get_clean();
 $listHtml=$html;check(substr_count($listHtml,'name="action"')<=1,$label.' directory renders only creation, not every account editor');
 foreach([1,2,3] as $selected){$_GET['account']=$selected;require $root.'/templates/access/context.php';ob_start();require $root.'/templates/access/users.php';$html.=ob_get_clean();}
 preg_match_all('/<form\b[^>]*>(.*?)<\/form>/s',$html,$forms);$actions=[];
 foreach($forms[1] as $form){preg_match('/name="id" value="(\d+)"/',$form,$m);preg_match('/name="action" value="([^"]+)"/',$form,$a);if($m&&$a)$actions[(int)$m[1]][]=$a[1];}
 foreach($actions as &$targetActions)$targetActions=array_values(array_unique($targetActions));unset($targetActions);
 check(!in_array('set_active',$actions[1]??[],true),$label.' never offers self-deactivation');
 check(count($actions[3]??[])===$higherForms,$label.' higher-authority account is appropriately read-only');
 check(in_array('save_user',$actions[0]??[],true)===$canCreate,$label.' create-account control matches clerk-grant authority');
 check(($can('roles.assign')&&$can('users.manage'))?in_array('assign_roles',$actions[2]??[],true):true,$label.' distinct role assignment remains reachable');
 foreach($forms[1] as $form)check(str_contains($form,'name="csrf"')&&str_contains($form,'name="current_password"')&&str_contains($form,'name="reason"'),$label.' each mutation retains CSRF/password/reason');
}
echo "TOTAL $n System Access presentation assertions passed\n";
