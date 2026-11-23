<?php
// Actual read-only template rendered with explicit capability inputs; no bootstrap or DB.
$testPermissions=[];$testAdmin=false;
function access_service():object{global $testPermissions,$testAdmin;return (object)['repo'=>new class($testPermissions,$testAdmin){public function __construct(private array $p,private bool $a){} public function privileges(int $id):array{return $this->p;}public function isAdmin(int $id):bool{return $this->a;}}];}
function access_url(string $s):string{return '/'.$s;}
function stay_url(array $q=[]):string{return '/occupancy.php?'.http_build_query($q);}
function workspace_asset_url(string $s):string{return '/'.$s;}
function access_h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$user=['id'=>1];$_SERVER['SCRIPT_NAME']='weekly-audits.php';$page='followups';$n=0;
foreach([
 [['bookings.manage','rooms.manage'],true,true,true,['Room Master','Out-Of-Order Log','Room Audits','Other Area Audits','Follow-Ups','Audit Duty']],
 [['rooms.manage'],true,false,true,['Room Audits','Other Area Audits','Follow-Ups']],
 [['bookings.manage'],false,false,true,['Room Audits','Other Area Audits','Follow-Ups']],
 [['rooms.manage'],false,false,true,['Room Audits','Other Area Audits','Follow-Ups']],
 [['bookings.manage'],false,false,false,['Follow-Ups']]
] as [$testPermissions,$testAdmin,$owner,$auditor,$expected]){
 $expectedKey=bin2hex(random_bytes(16));$key=$expectedKey;ob_start();require dirname(__DIR__,2).'/templates/partials/property-nav.php';$html=ob_get_clean();preg_match_all('/<a[^>]*>(.*?)<\/a>/s',$html,$links);
 if(array_map(fn($s)=>html_entity_decode($s,ENT_QUOTES,'UTF-8'),$links[1])!==$expected)throw new RuntimeException('Wrong tabs for '.implode(',',$testPermissions));$n++;
 if($key!==$expectedKey)throw new RuntimeException('Operation key overwritten');$n++;
}
echo "PASS $n property capability and form-key assertions\n";
