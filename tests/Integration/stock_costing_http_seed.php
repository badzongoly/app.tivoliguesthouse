<?php
require __DIR__.'/stock_issue_helpers.php';
$clock=gmdate('Y-m-d H:i:s');$svc=new \Tivoli\Service\StockCountService($a,$clock);$x=sx('8');$count=start($staff,[$x]);countLine($staff,$count,$x,'8');send($staff,$count);approve($count);
echo json_encode(['extra'=>$x,'name'=>$r->run('SELECT name FROM extras WHERE id=?',[$x])->fetchColumn(),'count'=>$count,'staff'=>$r->run('SELECT email FROM users WHERE id=?',[$staff])->fetchColumn(),'admin'=>$r->run('SELECT email FROM users WHERE id=?',[$admin])->fetchColumn()],JSON_THROW_ON_ERROR);
