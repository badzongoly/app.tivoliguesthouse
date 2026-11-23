<?php
// Actual shared navigation with synthetic permission inputs; no DB/bootstrap.
$root=dirname(__DIR__,2);$n=0;$financeActive=true;$kind='cleaning';$section='payments';
function access_url(string $p=''):string{return '/'.$p;}
function access_h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function workspace_asset_url(string $p):string{return '/'.$p;}
function check(bool $v,string $label):void{global $n;if(!$v)throw new RuntimeException($label);++$n;echo 'PASS '.$label."\n";}
function nav():string{global $root,$workspace,$financeActive,$kind,$section,$key,$settingsCanEditOperations;if(is_file($root.'/templates/partials/settings-nav.php')){ob_start();require $root.'/templates/partials/settings-nav.php';return ob_get_clean();}$s=file_get_contents($root.'/templates/stays/cleaning-settings.php');preg_match('/<nav\b.*?<\/nav>/s',$s,$m);ob_start();eval('?>'.$m[0]);return ob_get_clean();}
$full=['hr.settings','rooms.manage','extras.manage','bookings.manage'];$workspace=['roles'=>['admin'],'permissions'=>$full,'locked'=>false];$_SERVER['SCRIPT_NAME']='/cleaning-settings.php';$expectedKey=bin2hex(random_bytes(16));$key=$expectedKey;
check(str_contains(nav(),'Notifications'),'Settings offers the same Notifications entry from checklist pages');
$all=['Payment Lists','Document Types','Cleaning Checklist','Inspection Checklist','Audit Areas','Extra Categories','Notifications'];
foreach([['hr-settings.php','payments','',0],['hr-settings.php','documents','',1],['cleaning-settings.php','','cleaning',2],['cleaning-settings.php','','inspection',3],['cleaning-settings.php','','areas',4],['cleaning-settings.php','','extras',5],['reminders.php','','',6]] as [$file,$section,$kind,$selected]){
 $_SERVER['SCRIPT_NAME']='/'.$file;$html=nav();preg_match_all('/<a[^>]*>([^<]+)<\/a>/',$html,$links);
 check(array_map(fn($s)=>html_entity_decode($s,ENT_QUOTES,'UTF-8'),$links[1])===$all,$file.' one canonical tab order '.$selected);
 check(substr_count($html,'aria-current="page"')===1&&preg_match('/<a[^>]*aria-current="page"[^>]*>'.preg_quote($all[$selected],'/').'<\/a>/',$html),$file.' selects the actual section');
 check($key===$expectedKey,'Navigation never overwrites the operation key');
}
foreach([
 [['admin'],['hr.settings'],true,['Payment Lists','Document Types','Notifications']],
 [['admin'],['hr.settings','rooms.manage'],true,['Payment Lists','Document Types','Cleaning Checklist','Inspection Checklist','Audit Areas','Notifications']],
 [['admin'],['hr.settings','extras.manage'],true,['Payment Lists','Document Types','Extra Categories','Notifications']],
 [['custom'],['hr.settings','rooms.manage','extras.manage'],true,['Payment Lists','Document Types','Notifications']],
 [['clerk'],['hr.self'],true,['Notifications']],
 [['admin'],$full,false,array_slice($all,0,6)],
] as [$roles,$permissions,$financeActive,$expected]){
 $workspace=['roles'=>$roles,'permissions'=>$permissions,'locked'=>false];$html=nav();preg_match_all('/<a[^>]*>([^<]+)<\/a>/',$html,$links);check($links[1]===$expected,'Only endpoint-compatible Settings entries are shown');
}

function stay_fields(string $action,int $id=0,array $extra=[]):void{echo '<input name="action" value="'.$action.'">';}
function stay_input(string $label,string $name,mixed $value='',string $type='text',bool $required=false,string $attrs=''):void{echo '<input name="'.$name.'">';}
function stay_select(string $label,string $name,array $opts,mixed $value='',bool $required=false):void{echo '<select name="'.$name.'"></select>';}
foreach(['cleaning','inspection','areas','extras'] as $kind){
 foreach([false,true] as $writer){
  $workspace=['roles'=>['admin'],'permissions'=>['hr.settings','rooms.manage','extras.manage',...($writer?['bookings.manage']:[])],'locked'=>false];nav();
  $list=['revision'=>'synthetic','sections'=>[['id'=>'1','title'=>'Original Checks','items'=>[['id'=>'i1','text'=>'Original Saved Check']]]],'entries'=>$kind==='areas'?[['id'=>'a1','name'=>'Original Saved Area','active'=>true]]:['Original Saved Category'=>true]];
  $source=file_get_contents($root.'/templates/stays/'.($kind==='areas'?'area-settings':($kind==='extras'?'extra-category-settings':'cleaning-settings')).'.php');
  $source=substr($source,strpos($source,'<?php if($list)'));$source=substr($source,0,strrpos($source,"<?php require dirname(__DIR__).'/layouts/end.php';"));
  ob_start();eval('?>'.$source);$rendered=ob_get_clean();
  check(str_contains($rendered,'<form')===$writer,$kind.' editor requires full existing writer capabilities');
  check(str_contains($rendered,'Original Saved'),$kind.' retains original values for read-only readers');
 }
}
echo "TOTAL $n Settings navigation assertions passed\n";
