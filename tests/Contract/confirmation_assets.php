<?php
// Presentation-only contract. No application bootstrap, requests or database.
function access_url(string $path=''):string{return '/synthetic-app/'.$path;}
$root=dirname(__DIR__,2);
require $root.'/templates/layouts/context.php';
$checks=0;
function check(bool $condition,string $label):void{global $checks;if(!$condition){fwrite(STDERR,"FAIL $label\n");exit(1);}++$checks;echo "PASS $label\n";}
check(function_exists('workspace_asset_url'),'Changed assets have a content-versioned URL helper');
$assets=['settings-workspace','reminders','access-workspace','hr','form-returns','confirmations','finance','guest-charge-corrections','expense-corrections','payroll','purchase-corrections','receipt-corrections','purchases','handover','stock-issues','stock-counts','housekeeping','cash-discrepancies','stays'];
foreach($assets as $asset){
 $path='assets/js/'.$asset.'.js';$url=workspace_asset_url($path);
 check($url==='/synthetic-app/'.$path.'?v='.substr(hash_file('sha256',$root.'/public/'.$path),0,16),$asset.' URL matches current content');
 check(!preg_match('/\b(?:window\.)?confirm\s*\(/',file_get_contents($root.'/public/'.$path)),$asset.' has no legacy native prompt');
}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/templates'));
foreach($iterator as $file){if($file->getExtension()!=='php')continue;$source=file_get_contents($file->getPathname());foreach($assets as $asset){$literal="access_url('assets/js/$asset.js')";if(!str_contains($source,"assets/js/$asset.js"))continue;check(!str_contains($source,$literal),'No unversioned '.$asset.' include in '.substr($file->getPathname(),strlen($root)+1));}}
echo "TOTAL $checks asset assertions passed\n";
