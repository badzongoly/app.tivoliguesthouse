<?php
require __DIR__.'/stock_costing_purchases.php';
use Tivoli\Service\FinanceService;
use Tivoli\Service\InventoryCostingService;
$f=new FinanceService($a,'2037-01-15 15:00:00');$cs=new InventoryCostingService($a,'2037-01-15 15:00:00');
$bridge=$cs->period($owner,'2036-08-01','2036-08-31',250000);sa($bridge['partial']&&!$bridge['complete']&&$bridge['profit']===null,'cutover month discloses partial coverage instead of final profit');sa($bridge['before_unknown']===250000+$bridge['acquisition']-array_sum($bridge['costs']),'profit bridge adds purchases back once and replaces consumed costs');
$start=$f->priorWeekStart();$review=$f->weeklyReview($start);sa(isset($review['snapshot']['stock_costing']),'existing weekly review captures stock cost coverage');
if(!$review['close']){$f->execute($owner,'week_decide',['start'=>$start,'outcome'=>'clean','complete'=>'1','review_token'=>$review['token'],'evidence'=>'Synthetic immutable weekly costing review'],sk());$review=$f->weeklyReview($start);}
$closed=$review['close'];$bytes=$r->run('SELECT * FROM finance_events WHERE id=?',[$closed['id']])->fetch();
$v=$cs->item($owner,$x);$old=$v['basis']['total'];$cs->execute($owner,'evidence',['extra_id'=>(string)$x,'version'=>$v['version'],'total'=>\Tivoli\Domain\Stay\StayRules::decimal($old+100),'evidence'=>'Synthetic independently verified new opening evidence in an open period','verified'=>'1'],sk());
sa($r->run('SELECT * FROM finance_events WHERE id=?',[$closed['id']])->fetch()===$bytes&&$f->weeklyReview($start)['snapshot']===$review['snapshot'],'later evidence preserves original closed week byte for byte');
$locked=new InventoryCostingService($a,$start.' 15:00:00');$v=$locked->item($owner,$x);sd(fn()=>$locked->execute($owner,'evidence',['extra_id'=>(string)$x,'version'=>$v['version'],'total'=>'52','evidence'=>'Cannot post in closed week','verified'=>'1'],sk()),'late opening evidence cannot post into a closed period');
echo "TOTAL $n stock costing period assertions passed.\n";
