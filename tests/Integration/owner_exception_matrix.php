<?php
require __DIR__.'/stock_counts.php';
use Tivoli\Service\OwnerExceptionService;
use Tivoli\Repository\OwnerGuestBalances;
$queue=new OwnerExceptionService($a,$clock);
$cancelled=start($staff,[sx()]);sc($staff,'cancel',sv($cancelled,$staff)+['reason'=>'Synthetic unused count cancelled']);
$view=$queue->read($owner,['key'=>'stock_count:'.$cancelled]);
sa($view['selected']['resolved']&&$svc->detail($owner,$cancelled)['status']==='cancelled','Cancelled stock count resolves same original queue identity');
$missing=OwnerGuestBalances::read($r,[['id'=>2147483647,'kind'=>'departure_reported','target_type'=>'booking','target_id'=>'2147483647','data'=>['room_cleared'=>false]]]);
sa(count(array_filter($missing,fn($v)=>$v['id']===2147483647&&!empty($v['unavailable'])))===1,'Known missing original stay remains keyed and unavailable');
echo "Owner exception matrix: $n assertions passed\n";

