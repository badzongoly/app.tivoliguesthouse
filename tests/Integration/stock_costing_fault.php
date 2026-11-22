<?php
require __DIR__.'/stock_costing_purchases.php';
// Fault injection at a real PDO query boundary, not an alternate production code path.
final class CostSourceFaultPDO extends PDO
{
    public bool $failSource=false;
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        if($this->failSource&&str_contains($query,"AND kind='purchase_requested'"))throw new PDOException('Synthetic source query failure');
        return parent::prepare($query,$options);
    }
}
$config=access_config();$pdo=new CostSourceFaultPDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET time_zone='+00:00'");
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3307||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$pdo->query('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$faultAccess=new \Tivoli\Service\AccessService(new \Tivoli\Repository\AccessRepository($pdo),sk(),'127.0.0.1',$config['audit_key']);
$x=openingCost('20','40');costPurchase($x);$stayFault=new \Tivoli\Service\StayService($faultAccess,$clock);$in=['booking_id'=>(string)$booking,'extra_id'=>(string)$x,'qty'=>'2','settlement'=>'room'];$key=sk();$before=cv($x);$raw=$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn();$pdo->failSource=true;
sd(fn()=>$stayFault->execute($staff,'extra',$in,$key),'source database failure rolls back rather than becoming an unknown cost');$pdo->failSource=false;
sa(cv($x)===$before&&$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn()===$raw&&!$r->run('SELECT event_id FROM finance_operations WHERE actor_id=? AND operation_key=?',[$staff,$key])->fetchColumn(),'source query failure retains stock, cost state and no committed operation');
$stayFault->execute($staff,'extra',$in,$key);sa(cv($x)['costs']['sales']===467&&cv($x)['qty']===2800,'same key recovers the original sale and exact cost once after source failure');
echo "TOTAL $n stock costing source-fault assertions passed.\n";
