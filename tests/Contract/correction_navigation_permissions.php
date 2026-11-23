<?php
namespace Tivoli\Service {
    final class PayrollService {public function __construct(mixed $unused){} public function owner(int $id):bool{return false;}}
}
namespace {
// Render the actual read-only template with capability inputs; no app bootstrap or DB connection.
$navPermissions=[];
function access_service():object{global $navPermissions;return (object)['repo'=>new class($navPermissions){public function __construct(private array $p){}public function privileges(int $id):array{return $this->p;}}];}
function access_url(string $s):string{return '/'.$s;}
function access_h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$actor=7;$_GET=['area'=>'expenses'];$n=0;
foreach([
 [['expenses.approve'],false,false,true],
 [['expenses.submit'],true,false,true],
 [['stock.manage'],true,false,false],
 [['bookings.manage'],true,true,false]
] as [$navPermissions,$purchase,$guests,$expenses]){
 ob_start();require dirname(__DIR__,2).'/templates/finance/correction-sources.php';$html=ob_get_clean();
 foreach(['Purchases &amp; Providers'=>$purchase,'Guest Receipts'=>$guests,'Expenses'=>$expenses] as $label=>$expected){if(str_contains($html,'>'.$label.'</a>')!==$expected)throw new \RuntimeException('Wrong source visibility: '.implode(',',$navPermissions).' / '.$label);$n++;}
}
echo "PASS $n correction navigation permission assertions\n";
}
