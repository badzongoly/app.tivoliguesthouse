<?php
namespace Tivoli\Repository;

use DateTimeImmutable;
use InvalidArgumentException;

final class OwnerReports
{
    public const VIEWS = [
        'daily'=>'Daily Overview', 'room'=>'Room Performance', 'cash'=>'Cash & MoMo',
        'extras'=>'Extras', 'monthly'=>'Monthly Review',
    ];
    public const METHODS = ['cash'=>'Cash','momo'=>'MoMo','card'=>'Card','bank'=>'Bank','other'=>'Other','unknown'=>'Unknown'];
    private const DETAILS = [
        'daily'=>['room-charges','arrivals','departures','momo'],
        'cash'=>['refunds','momo','custody','deposits'],
        'monthly'=>['expenses'],
    ];
    private const PAGE_SIZE = 50;
    private const ROOMS = "LOWER(TRIM(r.name)) <> 'service' AND LOWER(TRIM(r.type)) <> 'service'";

    public function __construct(private readonly AccessRepository $repo) {}

    public static function filters(array $query, DateTimeImmutable $asOf): array
    {
        foreach (['view','start','end','room','item','method','page','detail','detail_category'] as $key) {
            if (isset($query[$key]) && !is_string($query[$key])) throw new InvalidArgumentException('Choose valid report filters.');
        }
        $view = $query['view'] ?? 'daily';
        if (!isset(self::VIEWS[$view])) throw new InvalidArgumentException('Choose a valid report view.');
        $today = $asOf->format('Y-m-d');
        $date = static function (string $value) use ($asOf, $today): DateTimeImmutable {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $asOf->getTimezone());
            if (!$d || $d->format('Y-m-d') !== $value || $value > $today || $value < '2000-01-01') {
                throw new InvalidArgumentException('Choose a valid date no later than today.');
            }
            return $d;
        };
        $end = $date($query['end'] ?? $today);
        $start = match ($view) {
            'daily'=>$end,
            'monthly'=>$end->modify('first day of this month')->modify('-5 months'),
            default=>$date($query['start'] ?? $end->modify('-6 days')->format('Y-m-d')),
        };
        $selectedMonth = $end->format('Y-m');
        if ($view === 'monthly') $end = $date(min($today,$end->modify('last day of this month')->format('Y-m-d')));
        if ($start > $end || ($view !== 'monthly' && $start->diff($end)->days >= ($view === 'room' ? 31 : 93))) {
            throw new InvalidArgumentException($view === 'room' ? 'Choose a range of up to 31 days.' : 'Choose a range of up to 93 days.');
        }
        $id = static function (string $key) use ($query): int {
            $value = $query[$key] ?? '';
            if ($value === '') return 0;
            if (!preg_match('/^[1-9][0-9]{0,8}$/D', $value)) throw new InvalidArgumentException('Choose a valid report filter.');
            return (int)$value;
        };
        $detail = $query['detail'] ?? '';
        if ($detail !== '' && !in_array($detail,self::DETAILS[$view] ?? [],true)) {
            throw new InvalidArgumentException('Choose a detail for this report.');
        }
        $category = $query['detail_category'] ?? '';
        if ($category !== '' && ($detail !== 'expenses'
            || ($category !== 'uncategorised' && !$id('detail_category')))) {
            throw new InvalidArgumentException('Choose a valid expense category detail.');
        }
        $method = $view === 'cash' ? ($query['method'] ?? '') : '';
        if ($method !== '' && !isset(self::METHODS[$method])) throw new InvalidArgumentException('Choose a valid payment method.');
        $from = $start->format('Y-m-d H:i:s');
        $until = min($end->modify('+1 day'), $asOf)->format('Y-m-d H:i:s');
        return [
            'view'=>$view, 'start'=>$start->format('Y-m-d'), 'end'=>$end->format('Y-m-d'),
            'month'=>$selectedMonth, 'from'=>$from, 'until'=>$until, 'asOf'=>$asOf->format('Y-m-d H:i:s'),
            'today'=>$today, 'method'=>$method, 'room'=>$view === 'room' ? $id('room') : 0,
            'item'=>$view === 'extras' ? $id('item') : 0, 'page'=>$id('page') ?: 1, 'detail'=>$detail, 'detail_category'=>$category,
        ];
    }

    public function read(array $f): array
    {
        $db = $this->repo->db;
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION READ ONLY');
        try {
            $tables = $this->repo->run("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()
                AND table_name IN ('stay_schema','finance_schema','stay_refunds')")->fetchAll(\PDO::FETCH_COLUMN);
            $stays = in_array('stay_schema', $tables, true)
                && (bool)$this->repo->run('SELECT 1 FROM stay_schema WHERE version=2')->fetchColumn();
            $finance = in_array('finance_schema', $tables, true)
                && (bool)$this->repo->run('SELECT 1 FROM finance_schema WHERE id=1')->fetchColumn();
            if (!empty($f['detail'])) {
                return ['detail'=>$this->detail($f,$stays,$finance,in_array('stay_refunds',$tables,true)),'finance'=>$finance];
            }
            $data=match ($f['view']) {
                'daily'=>$this->daily($f, $stays, $finance),
                'room'=>$this->roomPerformance($f, $stays, $finance),
                'cash'=>$this->cash($f, $finance, in_array('stay_refunds',$tables,true)),
                'extras'=>$this->extras($f, $stays),
                'monthly'=>$this->monthly($f, $finance),
            };
            $data['detail']=null;
            $data['payroll_adjustments']=$finance?PayrollAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf']):null;$data['purchase_adjustments']=$finance?PurchaseAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf']):null;$data['charge_adjustments']=$finance?ChargeAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf'],$f['item'],$f['room']):null;$data['receipt_adjustments']=$finance?ReceiptAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf']):null;$data['expense_adjustments']=$finance?ExpenseAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf']):null;return $data;
        } finally {
            $db->exec('ROLLBACK');
        }
    }

    private function rows(string $sql, array $values = []): array
    {
        return $this->repo->run($sql, $values)->fetchAll();
    }

    private function page(int $count, int $requested): array
    {
        $pages = max(1, intdiv($count+self::PAGE_SIZE-1,self::PAGE_SIZE));
        $page = min($pages,$requested);
        return ['count'=>$count,'page'=>$page,'pages'=>$pages,'offset'=>($page-1)*self::PAGE_SIZE];
    }

    private function receiptSource(array $f, bool $finance): array
    {
        $receiptSql=ReceiptAccounting::paymentsSql($finance);
        $sql = "SELECT p.source,p.id,p.booking_id,p.created_at,
                    CASE WHEN p.method IN ('cash','momo','card','bank','other') THEN CONVERT(p.method USING utf8mb4) COLLATE utf8mb4_unicode_ci ELSE 'unknown' END method,
                    CAST(p.amount*100 AS SIGNED) minor
                FROM ($receiptSql) p WHERE p.created_at>=? AND p.created_at<?";
        $values = [$f['from'],$f['until']];
        if ($finance) {
            $sql .= " UNION ALL SELECT 'income',i.id,NULL,i.created_at,
                CASE JSON_UNQUOTE(JSON_EXTRACT(i.payload,'$.method')) WHEN 'cash' THEN 'cash' WHEN 'momo' THEN 'momo' ELSE 'unknown' END,
                i.amount_minor FROM finance_events i WHERE i.kind='income_recorded' AND i.created_at>=? AND i.created_at<?";
            array_push($values,$f['from'],$f['until']);
        }
        return [$sql,$values];
    }

    private function roomChargeRows(bool $finance): string
    {
        return "SELECT d.booking_id,d.room_minor minor,d.posting_at,d.is_adjustment,r.name room
            FROM (".DepartureRevenue::sql($finance).") d JOIN bookings b ON b.id=d.booking_id JOIN rooms r ON r.id=b.room_id
            WHERE d.posting_at>=? AND d.posting_at<? AND ".self::ROOMS;
    }

    private function detail(array $f, bool $stays, bool $finance, bool $refunds): ?array
    {
        $detail = $f['detail'] ?? '';
        $values = [$f['from'],$f['until']];
        switch ($detail) {
            case 'room-charges':
                if (!$stays) return null;
                $sql = $this->roomChargeRows($finance);
                $order = 'posting_at DESC,booking_id DESC,is_adjustment DESC,minor';
                break;
            case 'arrivals':
            case 'departures':
                $arrival = $detail === 'arrivals';
                $stamp = $arrival ? 'b.checkin_at' : 'b.actual_checkout';
                $state = $arrival ? "IN ('active','checked_out')" : "='checked_out'";
                $sql = "SELECT b.id booking_id,r.name room,$stamp posting_at
                    FROM (".DepartureFacts::source($finance).") b JOIN rooms r ON r.id=b.room_id
                    WHERE b.operational_status $state AND $stamp>=? AND $stamp<? AND ".self::ROOMS;
                $order = 'posting_at DESC,booking_id DESC';
                break;
            case 'refunds':
                if (!$refunds) return null;
                $sql = 'SELECT id,booking_id,payment_id,created_at posting_at,amount_minor minor
                    FROM stay_refunds WHERE created_at>=? AND created_at<?';
                $order = 'posting_at DESC,id DESC';
                break;
            case 'expenses':
                $sql = "SELECT e.id,e.source_expense,e.source,e.expense_date posting_at,e.vendor,
                    COALESCE(c.name,'Uncategorised') category,CAST(e.amount*100 AS SIGNED) minor
                    FROM (".ExpenseAccounting::costSql($finance,$f['asOf']).") e
                    LEFT JOIN expense_categories c ON c.id=e.category_id WHERE e.expense_date>=? AND e.expense_date<=?";
                $values = [$f['month'].'-01',$f['end']];
                $category=$f['detail_category'] ?? '';
                if ($category==='uncategorised') {
                    $sql .= ' AND e.category_id IS NULL';
                } elseif ($category!=='') {
                    $sql .= ' AND e.category_id=?';
                    $values[]=(int)$category;
                }
                $order = 'posting_at DESC,source,id DESC,minor,category';
                break;
            case 'momo':
                if (!$finance) return null;
                [$source,$params]=$this->receiptSource($f,$finance);
                [$sql,$values]=$this->momoRows($source,$params,$f['asOf']);
                if ($f['view']==='daily') $sql = "SELECT * FROM ($sql) m WHERE coverage='Awaiting review'";
                $order = 'created_at DESC,source,id DESC';
                break;
            case 'custody':
                if (!$finance) return null;
                [$source,$params]=$this->receiptSource($f,$finance);
                [$sql,$values]=$this->custodyRows($f,$source,$params);
                $order = 'name,source,id DESC';
                break;
            case 'deposits':
                if (!$finance) return null;
                $sql = $this->depositRows($f);
                $values = [$f['start'],$f['end'],$f['asOf']];
                $order = 'posting_at DESC,id DESC,state';
                break;
            default:
                return null;
        }
        $count=(int)$this->repo->run("SELECT COUNT(*) FROM ($sql) detail_rows",$values)->fetchColumn();
        $page=$this->page($count,$f['page']);
        $rows=$this->rows("SELECT * FROM ($sql) detail_rows ORDER BY $order LIMIT ".self::PAGE_SIZE.' OFFSET '.$page['offset'],$values);
        return compact('page','rows');
    }

    private function daily(array $f, bool $stays, bool $finance): array
    {
        $bookingSource=DepartureFacts::source($finance);
        [$receipts,$values] = $this->receiptSource($f,$finance);
        $payment = $this->repo->run("SELECT COUNT(*) count,COALESCE(SUM(minor),0) minor FROM ($receipts) x",$values)->fetch();
        $rooms = $this->rows("SELECT r.id,r.name,r.type,r.status,".($stays?'s.ready_state':'NULL AS ready_state').",
                    COALESCE(a.active,0) active
                FROM rooms r ".($stays?'LEFT JOIN stay_room_settings s ON s.room_id=r.id ':'')."
                LEFT JOIN (SELECT room_id,COUNT(*) active FROM (".$bookingSource.") b WHERE b.operational_status='active' GROUP BY room_id) a ON a.room_id=r.id
                WHERE ".self::ROOMS." ORDER BY r.name,r.id");
        $stayCounts = $this->repo->run("SELECT
            (SELECT COUNT(*) FROM (".$bookingSource.") b JOIN rooms r ON r.id=b.room_id WHERE b.operational_status IN ('active','checked_out')
             AND b.checkin_at>=? AND b.checkin_at<? AND ".self::ROOMS.") arrivals,
            (SELECT COUNT(*) FROM (".$bookingSource.") b JOIN rooms r ON r.id=b.room_id WHERE b.operational_status='checked_out'
             AND b.actual_checkout>=? AND b.actual_checkout<? AND ".self::ROOMS.") departures",
            [$f['from'],$f['until'],$f['from'],$f['until']])->fetch();
        $roomCharges = $stays ? $this->repo->run("SELECT COALESCE(SUM(minor),0) minor,
            COALESCE(SUM(is_adjustment=0),0) count FROM (".$this->roomChargeRows($finance).") charges",
            [$f['from'],$f['until']])->fetch() : null;
        $pending = null;
        if ($finance) {
            [$momo,$momoValues]=$this->momoRows($receipts,$values,$f['asOf']);
            $pending=$this->repo->run("SELECT COUNT(*) FROM ($momo) m WHERE coverage='Awaiting review'",$momoValues)->fetchColumn();
        }
        return compact('payment','rooms','stayCounts','roomCharges','pending','finance');
    }

    private function roomPerformance(array $f, bool $stays,bool $finance): array
    {
        $bookingSource=DepartureFacts::source($finance);
        $rooms = $this->rows("SELECT r.id,r.name FROM rooms r WHERE ".self::ROOMS." ORDER BY r.name,r.id");
        if ($f['room'] && !in_array($f['room'],array_map('intval',array_column($rooms,'id')),true)) {
            throw new InvalidArgumentException('Choose a room from the report list.');
        }
        // The current night has not elapsed. Active stays contribute earlier calendar nights only.
        $days = [];
        for ($d = new DateTimeImmutable($f['start']); $d->format('Y-m-d') <= $f['end']; $d = $d->modify('+1 day')) {
            $days[] = $d->format('Y-m-d');
        }
        $calendar = implode(' UNION ALL ',array_fill(0,count($days),'SELECT CAST(? AS DATE) night'));
        $cells = $this->rows("SELECT r.id,c.night,COUNT(*) stays,MIN(b.id) first_id,
                ".($stays?"SUM(d.booking_id IS NULL)":"COUNT(*)")." legacy
            FROM ($calendar) c
            JOIN (".$bookingSource.") b ON b.checkin_at < DATE_ADD(c.night,INTERVAL 1 DAY)
                AND ((b.operational_status='checked_out' AND b.actual_checkout>=DATE_ADD(c.night,INTERVAL 1 DAY) AND b.actual_checkout<?)
                    OR (b.operational_status='active' AND c.night<?))
            JOIN rooms r ON r.id=b.room_id ".($stays?"LEFT JOIN stay_details d ON d.booking_id=b.id ":"")."
            WHERE ".self::ROOMS.($stays?" AND (d.booking_id IS NULL OR d.stay_type='overnight')":"").
            ($f['room']?' AND r.id=?':'')." GROUP BY r.id,c.night ORDER BY r.id,c.night",
            [...$days,$f['asOf'],$f['today'],...($f['room']?[$f['room']]:[])]);
        $byRoom = [];
        foreach ($cells as $cell) $byRoom[$cell['id']][$cell['night']] = $cell;
        $overlaps = array_values(array_filter($cells,static fn($cell)=>(int)$cell['stays']>1));
        return compact('rooms','days','byRoom','overlaps')+['occupied'=>count($cells),'legacy'=>array_sum(array_column($cells,'legacy'))];
    }

    private function cash(array $f, bool $finance, bool $refundsAvailable): array
    {
        [$source,$values] = $this->receiptSource($f,$finance);
        $where = $f['method'] ? ' WHERE x.method=?' : '';
        if ($f['method']) $values[] = $f['method'];
        $from = " FROM ($source) x".$where;
        $methods = $this->rows("SELECT x.method,COUNT(*) count,SUM(x.minor) minor".$from.' GROUP BY x.method ORDER BY x.method',$values);
        $page = $this->page(array_sum(array_column($methods,'count')),$f['page']);
        $receipts = $this->rows("SELECT x.*".$from.' ORDER BY x.created_at DESC,x.source,x.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.$page['offset'],$values);
        $momo = [];
        $deposits = null;
        $custody = null;
        if ($finance) {
            $unfilteredValues = array_slice($values,0,$f['method']?-1:null);
            [$momoSql,$momoValues]=$this->momoRows($source,$unfilteredValues,$f['asOf']);
            $momo=$this->rows("SELECT coverage,COUNT(*) count,SUM(minor) minor FROM ($momoSql) m GROUP BY coverage ORDER BY coverage",$momoValues);
            $custody = $this->custody($f,$source,$unfilteredValues);
            $depositSql = $this->depositRows($f);
            $deposits=$this->rows("SELECT state,COUNT(*) count,SUM(minor) minor FROM ($depositSql) d GROUP BY state ORDER BY state",[$f['start'],$f['end'],$f['asOf']]);
        }
        $refunds = $refundsAvailable ? $this->repo->run('SELECT COALESCE(SUM(amount_minor),0) FROM stay_refunds WHERE created_at>=? AND created_at<?',
            [$f['from'],$f['until']])->fetchColumn() : null;
        $undated = $this->repo->run('SELECT COUNT(*) FROM payments WHERE created_at IS NULL')->fetchColumn();
        return compact('methods','page','receipts','momo','finance','deposits','custody','refunds','undated');
    }

    private function momoRows(string $source,array $values,string $asOf): array
    {
        [$effective,$values]=$this->effectiveReceipts($source,$values,$asOf);
        $verified=\Tivoli\Domain\Finance\CashResolution::verifiedSql('x.source','x.id','x.minor',$this->repo->db->quote($asOf));
        $sql="SELECT x.*,CASE
            WHEN $verified>=x.minor THEN 'Matched by staff'
            WHEN x.source='payment' AND x.id<=(SELECT payment_cursor FROM finance_schema WHERE id=1) THEN 'Historical coverage unknown'
            ELSE 'Awaiting review' END coverage
            FROM ($effective) x WHERE x.method='momo' AND x.minor>0";
        return [$sql,$values];
    }

    private function depositRows(array $f): string
    {
        $cutoff=$this->repo->db->quote($f['asOf']);
        $verified=\Tivoli\Domain\Finance\CashResolution::verifiedSql("'deposit'",'f.id','f.amount_minor',$cutoff);
        $returned=\Tivoli\Domain\Finance\CashResolution::returnedSql('f.id',$cutoff);
        return "SELECT * FROM (
            SELECT q.id,q.posting_at,CASE mode WHEN 1 THEN 'Wallet confirmation recorded' WHEN 2 THEN 'In transit' ELSE 'Returned to custody' END state,
            CASE mode WHEN 1 THEN q.verified WHEN 2 THEN q.amount_minor-q.verified-q.returned ELSE q.returned END minor
            FROM (SELECT f.id,f.effective_date posting_at,f.amount_minor,$verified verified,$returned returned
                FROM finance_events f WHERE f.kind='deposit_recorded' AND f.effective_date>=? AND f.effective_date<=? AND f.created_at<?) q
            CROSS JOIN (SELECT 1 mode UNION ALL SELECT 2 UNION ALL SELECT 3) modes
            ) deposit_parts WHERE minor>0";
    }

    private function effectiveReceipts(string $receipts,array $values,string $asOf):array
    {
        $sql="SELECT raw.source,raw.id,raw.booking_id,raw.created_at,CASE WHEN latest.id IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(latest.payload,'$.after.method')) ELSE raw.method END method,CASE WHEN latest.id IS NOT NULL THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(latest.payload,'$.after.amount')) AS SIGNED) ELSE raw.minor END minor FROM ($receipts) raw LEFT JOIN finance_events latest ON raw.source='payment' AND latest.id=(SELECT MAX(c.id) FROM finance_events c WHERE c.kind='rc_posted' AND c.target_type='payment' AND c.target_id=CAST(raw.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND c.created_at<?) WHERE raw.source<>'correction'";
        $values[]=$asOf;return [$sql,$values];
    }

    private function custodyRows(array $f, string $receipts, array $values): array
    {
        // Current custody projection of original cohorts, not new cash or a restated receipt period.
        [$receipts,$values]=$this->effectiveReceipts($receipts,$values,$f['asOf']);
        // Mirror the existing full-deposit control. Cash costs have separate funding.
        // Receipt cohorts are restricted to the selected period. Allocations reflect the current report time.
        $allocation = <<<'SQL'
            CAST(JSON_UNQUOTE(JSON_EXTRACT(d.payload, CONCAT('$.allocations."',
                IF(x.source='income',CONCAT('income:',x.id),x.id),'"'))) AS SIGNED)
            SQL;
        $cutoff=$this->repo->db->quote($f['asOf']);
        $allocation=str_replace('d.payload',ReceiptReallocation::payloadSql('d',$cutoff),$allocation);
        $resolutionSource="IF(x.source='income',CONCAT('income:',x.id),x.id)";
        $resolutionQty="CAST(JSON_UNQUOTE(JSON_EXTRACT(z.payload,CONCAT('$.allocations.\"',$resolutionSource,'\"'))) AS SIGNED)";
        $resolutionQty=str_replace('z.payload',ReceiptReallocation::payloadSql('z',$cutoff),$resolutionQty);
        $returns="COALESCE((SELECT SUM($resolutionQty) FROM finance_events z WHERE z.kind='cd_cash_returned' AND z.target_type='deposit' AND z.target_id=CONVERT(d.id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND z.created_at<$cutoff),0)";
        $delta="COALESCE((SELECT SUM(CASE WHEN z.kind='cd_credit' THEN $resolutionQty ELSE -$resolutionQty END) FROM finance_events z WHERE z.kind IN ('cd_credit','cd_reversed') AND z.target_type='deposit' AND z.target_id=CONVERT(d.id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND z.created_at<$cutoff),0)";
        $sql="SELECT a.source,a.id,a.booking_id,a.created_at,a.actor_id,COALESCE(u.name,'Unknown recorder') name,a.minor receipts,
                a.minor-a.reserved held,a.reserved-a.confirmed transit,a.confirmed
            FROM (
                SELECT x.source,x.id,x.booking_id,x.created_at,x.minor,COALESCE(s.actor_id,i.actor_id) actor_id,
                    COALESCE(SUM($allocation-$returns),0) reserved,
                    COALESCE(SUM((CASE WHEN c.id IS NOT NULL THEN $allocation ELSE 0 END)+$delta),0) confirmed
                FROM ($receipts) x
                LEFT JOIN stay_receipts s ON x.source='payment' AND s.payment_id=x.id
                LEFT JOIN finance_events i ON x.source='income' AND i.id=x.id
                LEFT JOIN finance_events d ON d.target_type='wallet' AND d.target_id='business'
                    AND d.kind='deposit_recorded' AND d.created_at<? AND $allocation IS NOT NULL
                LEFT JOIN finance_events c ON c.id=(SELECT MIN(c1.id) FROM finance_events c1
                    WHERE c1.kind='deposit_confirmed' AND c1.target_type='deposit'
                    AND c1.target_id=CONVERT(d.id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND c1.created_at<?)
                WHERE x.method='cash' AND (x.source='income' OR x.id>(SELECT payment_cursor FROM finance_schema WHERE id=1))
                GROUP BY x.source,x.id,x.booking_id,x.created_at,x.minor,COALESCE(s.actor_id,i.actor_id)
            ) a LEFT JOIN users u ON u.id=a.actor_id";
        return [$sql,[...$values,$f['asOf'],$f['asOf']]];
    }

    private function custody(array $f,string $receipts,array $values): array
    {
        [$sql,$params]=$this->custodyRows($f,$receipts,$values);
        $rows=$this->rows("SELECT actor_id,name,SUM(receipts) receipts,SUM(held) held,
            SUM(transit) transit,SUM(confirmed) confirmed FROM ($sql) c GROUP BY actor_id,name ORDER BY name,actor_id",$params);
        [$receipts,$values]=$this->effectiveReceipts($receipts,$values,$f['asOf']);
        $historical = $this->repo->run("SELECT COALESCE(SUM(minor),0) FROM ($receipts) x WHERE x.method='cash'
            AND x.source='payment' AND x.id<=(SELECT payment_cursor FROM finance_schema WHERE id=1)",$values)->fetchColumn();
        return compact('rows','historical');
    }

    private function extras(array $f, bool $stays): array
    {
        $values = [$f['from'],$f['until']];
        $where = ' WHERE be.created_at>=? AND be.created_at<?';
        if ($f['item']) {
            $where .= ' AND be.extra_id=?';
            $values[] = $f['item'];
        }
        // Exact DECIMAL multiplication, with the existing finance convention of half-up per line.
        $amount = 'ROUND(be.qty*be.unit_price*100,0)';
        $from = ' FROM booking_extras be'.$where;
        $daily = $this->rows("SELECT DATE(be.created_at) day,COUNT(*) count,SUM($amount) minor".$from.' GROUP BY DATE(be.created_at) ORDER BY day',$values);
        $byDay = array_column($daily,'minor','day');
        $series = [];
        for ($d = new DateTimeImmutable($f['start']); $d->format('Y-m-d') <= $f['end']; $d = $d->modify('+1 day')) {
            $day = $d->format('Y-m-d');
            $series[$day] = $byDay[$day] ?? '0';
        }
        $catalog = $this->rows("SELECT be.extra_id,COALESCE(MAX(e.name),CONCAT('Item #',be.extra_id)) name
            FROM booking_extras be LEFT JOIN extras e ON e.id=be.extra_id WHERE be.extra_id IS NOT NULL
            GROUP BY be.extra_id ORDER BY name,be.extra_id");
        if ($f['item'] && !in_array($f['item'],array_map('intval',array_column($catalog,'extra_id')),true)) {
            throw new InvalidArgumentException('Choose an item from the report list.');
        }
        // Without an item ID, each line stays separate. Similar snapshot text does not establish identity.
        $items = $this->rows("SELECT be.extra_id,CASE WHEN be.extra_id IS NULL THEN be.id END unlinked,
            COALESCE(MAX(e.name),MAX(NULLIF(be.description,'')),'Unnamed item') name,SUM(be.qty) qty,SUM($amount) minor
            FROM booking_extras be LEFT JOIN extras e ON e.id=be.extra_id".$where.
            ' GROUP BY be.extra_id,CASE WHEN be.extra_id IS NULL THEN be.id END ORDER BY minor DESC,be.extra_id,unlinked',$values);
        $page = $this->page(array_sum(array_column($daily,'count')),$f['page']);
        $link = "EXISTS (SELECT 1 FROM stock_movements m WHERE m.ref_type='booking_extra' AND m.ref_id=be.id
            AND m.extra_id=be.extra_id AND m.movement_type='out')";
        $lines = $this->rows("SELECT be.id,be.booking_id,r.name room,be.extra_id,be.description,be.qty,be.unit_price,be.created_at,
                $amount minor,$link stock_link
            FROM booking_extras be LEFT JOIN bookings b ON b.id=be.booking_id LEFT JOIN rooms r ON r.id=b.room_id".$where.
            ' ORDER BY be.created_at DESC,be.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.$page['offset'],$values);
        $unlinked = $stays ? $this->repo->run("SELECT COUNT(*) FROM booking_extras be JOIN stay_extra_settings s
            ON s.extra_id=be.extra_id AND s.tracked=1".$where." AND NOT $link",$values)->fetchColumn() : null;
        $total = $this->repo->run("SELECT COALESCE(SUM($amount),0)".$from,$values)->fetchColumn();
        $adjustments=ChargeAccounting::period($this->repo,$f['start'],$f['end'],$f['asOf'],$f['item']);$total=(int)$total+$adjustments['extras'];
        foreach($adjustments['rows'] as $entry){$d=$entry['data'];if($d['charge_type']!=='extra')continue;$delta=(int)$entry['amount_minor'];$day=$entry['effective_date'];$series[$day]=(int)($series[$day]??0)+$delta;$found=false;foreach($items as &$item)if(($d['extra_id']!==null&&(int)$item['extra_id']===(int)$d['extra_id'])||($d['extra_id']===null&&$item['extra_id']===null&&(int)$item['unlinked']===(int)$d['source_id'])){$item['minor']=(int)$item['minor']+$delta;$found=true;break;}unset($item);if(!$found)$items[]=['extra_id'=>$d['extra_id'],'unlinked'=>$d['extra_id']===null?$d['source_id']:null,'name'=>'Charge Adjustment · '.$d['label'],'qty'=>'0.00','minor'=>$delta];}
        usort($items,static fn($a,$b)=>((int)$b['minor']<=>(int)$a['minor'])?:(((int)($a['extra_id']??0)<=>(int)($b['extra_id']??0))?:((int)($a['unlinked']??0)<=>(int)($b['unlinked']??0))));$items=array_slice($items,0,12);
        return compact('series','items','page','lines','catalog','unlinked','total');
    }

    private function monthly(array $f, bool $finance): array
    {
        [$receipts,$values] = $this->receiptSource($f,$finance);
        $income = array_column($this->rows("SELECT DATE_FORMAT(created_at,'%Y-%m') month,SUM(minor) minor
            FROM ($receipts) x GROUP BY DATE_FORMAT(created_at,'%Y-%m')",$values),'minor','month');
        $expenseSource=ExpenseAccounting::costSql($finance,$f['asOf']);
        $expenses = array_column($this->rows("SELECT DATE_FORMAT(expense_date,'%Y-%m') month,ROUND(SUM(amount)*100,0) minor
            FROM ($expenseSource) expenses WHERE expense_date>=? AND expense_date<=? GROUP BY DATE_FORMAT(expense_date,'%Y-%m')",
            [$f['start'],$f['end']]),'minor','month');
        $months = [];
        for ($d = new DateTimeImmutable($f['start']); $d->format('Y-m') <= $f['month']; $d = $d->modify('+1 month')) {
            $month = $d->format('Y-m');
            $months[$month] = ['receipts'=>$income[$month]??0,'expenses'=>$expenses[$month]??0];
        }
        $categories = $this->rows("SELECT e.category_id,COALESCE(c.name,'Uncategorised') name,ROUND(SUM(e.amount)*100,0) minor
            FROM ($expenseSource) e LEFT JOIN expense_categories c ON c.id=e.category_id
            WHERE e.expense_date>=? AND e.expense_date<=? GROUP BY e.category_id,c.name ORDER BY minor DESC,name,e.category_id",
            [$f['month'].'-01',$f['end']]);
        return compact('months','categories','finance');
    }
}
