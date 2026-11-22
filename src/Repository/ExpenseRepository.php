<?php
namespace Tivoli\Repository;
use RuntimeException;
final class ExpenseRepository
{
    public function __construct(public readonly AccessRepository $access) {}
    public function categories(): array { return $this->access->run('SELECT id,name FROM expense_categories WHERE active=1 ORDER BY name')->fetchAll(); }
    public function submissions(int $actor,bool $administrator,string $status='',int $before=0): array
    {
        if (!in_array($status,['','pending','approved','rejected'],true)) { throw new RuntimeException('Invalid submission status.'); }
        $where=[];$values=[];
        if (!$administrator) { $where[]='s.submitted_by=?';$values[]=$actor; }
        if ($status!=='') { $where[]='s.status=?';$values[]=$status; }
        if ($before>0) { $where[]='s.id<?';$values[]=$before; }
        return $this->access->run('SELECT s.*,c.name AS category_name,u.name AS submitter_name,r.name AS reviewer_name FROM expense_submissions s JOIN expense_categories c ON c.id=s.category_id JOIN users u ON u.id=s.submitted_by LEFT JOIN users r ON r.id=s.reviewed_by'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY s.id DESC LIMIT 100',$values)->fetchAll();
    }
    public function approved(array $filters): array
    {
        $where=['e.expense_date BETWEEN ? AND ?'];$values=[$filters['start'],$filters['end']];
        if ($filters['category_id']>0) { $where[]='e.category_id=?';$values[]=$filters['category_id']; }
        if ($filters['method']!=='all') { $where[]='e.method=?';$values[]=$filters['method']; }
        if ($filters['q']!=='') { $where[]='(e.vendor LIKE ? OR e.reference_no LIKE ? OR e.description LIKE ?)';$term='%'.$filters['q'].'%';array_push($values,$term,$term,$term); }
        $source=ExpenseAccounting::costSql(ReceiptAccounting::installed($this->access));$sql=' FROM ('.$source.') e JOIN expense_categories c ON c.id=e.category_id LEFT JOIN users u ON u.id=e.user_id WHERE '.implode(' AND ',$where);
        $db=$this->access->db;
        if($db->inTransaction()){throw new \LogicException('Ledger reads must start outside a write transaction.');}
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        try {
            $rows=$this->access->run('SELECT e.*,c.name AS category_name,u.name AS user_name'.$sql.' ORDER BY e.expense_date DESC,e.id DESC LIMIT 300',$values)->fetchAll();
            $summary=$this->access->run('SELECT COALESCE(SUM(e.amount),0.00) AS total,COUNT(*) AS count'.$sql,$values)->fetch();
            $db->commit();
            return ['rows'=>$rows,'total'=>(string)$summary['total'],'count'=>(int)$summary['count']];
        }catch(\Throwable $error){if($db->inTransaction()){$db->rollBack();}throw $error;}
    }
    public static function filters(array $query): array
    {
        $result=['start'=>gmdate('Y-m-01'),'end'=>gmdate('Y-m-t'),'category_id'=>0,'method'=>'all','q'=>''];
        foreach(array_keys($result) as $key){if(isset($query[$key])){if(!is_string($query[$key])){throw new RuntimeException('Invalid filter.');}$result[$key]=trim($query[$key]);}}
        foreach(['start','end'] as $key){$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$result[$key],new \DateTimeZone('UTC'));if(!$date || $date->format('Y-m-d')!==$result[$key]){throw new RuntimeException('Enter valid filter dates.');}}
        if ($result['start']>$result['end']) { throw new RuntimeException('Start date must not follow end date.'); }
        $category=filter_var($result['category_id'],FILTER_VALIDATE_INT);if($category===false || $category<0){throw new RuntimeException('Invalid category filter.');}$result['category_id']=$category;
        if(!in_array($result['method'],['all','cash','momo','card','bank','other'],true) || strlen($result['q'])>120){throw new RuntimeException('Invalid method or search filter.');}
        return $result;
    }
}