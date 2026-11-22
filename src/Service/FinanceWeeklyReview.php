<?php
namespace Tivoli\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;

/** Weekly decisions share the ordinary posting lock and finance event transaction. */
trait FinanceWeeklyReview
{
    public function priorWeekStart(): string
    {
        return (new DateTimeImmutable($this->today(), new DateTimeZone('UTC')))
            ->modify('monday this week')->modify('-7 days')->format('Y-m-d');
    }

    public function weeklyReview(string $start): array
    {
        $start = $this->date($start);
        $monday = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        if ($monday->format('N') !== '1' || $start > $this->priorWeekStart()) {
            throw new RuntimeException('Choose the Monday of a completed Monday–Sunday week.');
        }
        $end = $monday->modify('+6 days')->format('Y-m-d');
        $history = $this->repo->run("SELECT f.*, u.name actor_name FROM finance_events f JOIN users u ON u.id=f.actor_id
            WHERE f.target_type='period' AND f.target_id=?
            AND f.kind IN ('period_locked','period_reopened','week_kept_open','week_case_resolved')
            ORDER BY f.id DESC", ['week:'.$start])->fetchAll();
        $close = null;
        $stateFound = false;
        $followups = [];
        foreach ($history as &$event) {
            $event['data'] = json_decode($event['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!$stateFound && in_array($event['kind'], ['period_locked','period_reopened'], true)) {
                $stateFound = true;
                if ($event['kind'] === 'period_locked') $close = $event;
            }
            if ($event['kind'] === 'week_case_resolved') {
                $followups[(int)$event['data']['case_id']] ??= $event;
            }
        }
        unset($event);
        if ($close) {
            $followups = array_filter($followups, fn($event) => (int)$event['data']['close_id'] === (int)$close['id']);
            return compact('start','end','close','history','followups') + [
                'snapshot'=>$close['data']['snapshot'] ?? [],
                'pending'=>$close['data']['carried'] ?? [],
            ];
        }

        $snapshot = $this->summary($start,$end);
        $configured=getenv('TIVOLI_FINANCE_OWNER_USER_ID');if($configured===false)$configured=\access_config()['finance_owner_user_id']??0;$costOwner=(int)$configured;
        $cs=new InventoryCostingService($this->access,$this->now());if($cs->owner($costOwner)){$costing=$cs->period($costOwner,$start,$end,$snapshot['profit']);if($costing['started'])$snapshot['stock_costing']=$costing;}
        unset($snapshot['active_stays']);
        ksort($snapshot['receipts']);
        // This is a period-specific review. Daily reconciliation covers older outstanding money.
        $unmatched = $this->unmatched($end,$start);
        $pending = $this->repo->run("SELECT id,expense_date,amount FROM expense_submissions
            WHERE status='pending' AND expense_date BETWEEN ? AND ? ORDER BY id", [$start,$end])->fetchAll();
        $pending = array_map(fn($row) => ['id'=>(int)$row['id'],'date'=>$row['expense_date'],'amount_minor'=>R::minor($row['amount'])],$pending);
        $pendingRequests = (int)$this->repo->run("SELECT COUNT(*) FROM finance_events req
            WHERE req.kind IN ('provider_requested','payroll_requested') AND req.effective_date BETWEEN ? AND ?
            AND NOT EXISTS(SELECT 1 FROM finance_events review WHERE review.target_type='request'
                AND review.target_id=CAST(req.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                AND review.kind IN ('request_approved','request_rejected'))", [$start,$end])->fetchColumn();

        $pendingRequests += PurchaseService::pendingCosts($this->repo,$start,$end);
        $pendingRequests += PayrollService::pendingCosts($this->repo,$start,$end);
        $tracked = $this->cash();
        $cashConfirmed = 0;
        $cashOutstanding = 0;
        $cashSources = [];
        foreach ($tracked['rows'] as $receipt) {
            $date = substr($receipt['created_at'],0,10);
            if ($date < $start || $date > $end) continue;
            $cashConfirmed += $receipt['confirmed'];
            $cashOutstanding += $receipt['outstanding'];
            $cashSources[(string)$receipt['id']] = $receipt['confirmed'];
        }
        $snapshot['weekly_cash'] = ['confirmed'=>$cashConfirmed,'outstanding'=>$cashOutstanding,'tracked_receipts'=>$cashSources];
        // The confirmation covers the data rendered by the form. Compare again under the posting lock.
        $token = hash('sha256',json_encode([$snapshot,$unmatched,$pending,$pendingRequests],JSON_THROW_ON_ERROR));
        return compact('start','end','close','history','followups','snapshot','pending','unmatched','pendingRequests','token');
    }

    private function weeklyCommand(int $actor, string $action, array $in): int
    {
        if (!$this->canPostLate($actor)) {
            throw new PermissionDenied('Only the designated finance owner may record a weekly reconciliation.');
        }
        $review = $this->weeklyReview($in['start'] ?? '');
        $key = 'week:'.$review['start'];
        $comment = R::text($in['comment'] ?? '',500);
        if ($action === 'week_followup') {
            $caseId = R::text($in['case_id'] ?? '',18,true);
            $case = null;
            foreach ($review['pending'] as $candidate) {
                if ((string)$candidate['id'] === $caseId) $case = $candidate;
            }
            if (!$review['close'] || !$case) throw new RuntimeException('Choose a case carried by this weekly close.');
            if (isset($review['followups'][$caseId])) throw new RuntimeException('This case is already resolved.');
            $source = $this->repo->run('SELECT status,expense_id FROM expense_submissions WHERE id=?',[$case['id']])->fetch();
            if (!$source || $source['status']==='pending') throw new RuntimeException('Review the source expense before resolving this case.');
            $evidence = R::text($in['evidence'] ?? '',120,true);
            return $this->event('week_case_resolved','period',$key,0,$this->today(),$actor,[
                'close_id'=>(int)$review['close']['id'],'case_id'=>$case['id'],'evidence'=>$evidence,'comment'=>$comment,'source_status'=>$source['status'],'expense_id'=>$source['expense_id'],
            ]);
        }
        if ($review['close']) throw new RuntimeException('This week is already locked.');
        $outcome = $in['outcome'] ?? '';
        if (!in_array($outcome,['clean','carry','open'],true)) throw new RuntimeException('Choose a close decision.');
        if ($outcome !== 'clean' && $comment === '') throw new RuntimeException('Enter a reason for this decision.');
        $data = ['workflow'=>'weekly_reconciliation','outcome'=>$outcome,'start'=>$review['start'],'end'=>$review['end'],
            'comment'=>$comment];
        if ($outcome === 'open') {
            return $this->event('week_kept_open','period',$key,0,$this->today(),$actor,$data+[
                'snapshot'=>$review['snapshot'],'evidence'=>R::text($in['evidence'] ?? '',120),
                'blockers'=>['unmatched_receipts'=>count($review['unmatched']),'pending_expenses'=>count($review['pending']),
                    'pending_requests'=>$review['pendingRequests'],'cash_outstanding'=>$review['snapshot']['weekly_cash']['outstanding']],
            ]);
        }
        if (!hash_equals($review['token'],$in['review_token'] ?? '')) {
            throw new RuntimeException('The figures or exceptions changed since you opened the review. Review the latest details and try again.');
        }
        if (($in['complete'] ?? '') !== '1') throw new RuntimeException('Confirm that you reviewed the source records.');
        if ($review['unmatched']) throw new RuntimeException('Match all MoMo receipts for this week before locking.');
        if ($review['snapshot']['weekly_cash']['outstanding'] > 0) {
            throw new RuntimeException('Confirm the remaining cash deposits for this week before locking.');
        }
        if ($review['pendingRequests']) throw new RuntimeException('Review pending provider, payroll and received purchase costs before locking.');
        if ($outcome === 'clean' && $review['pending']) throw new RuntimeException('Resolve pending expenses or choose to carry them forward.');
        if ($outcome === 'carry' && !$review['pending']) throw new RuntimeException('There are no pending expenses to carry forward.');
        $carried = [];
        foreach ($review['pending'] as $case) {
            $id = $case['id'];
            $ownerId = filter_var($in['owner_'.$id] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $due = $in['due_'.$id] ?? '';
            $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d',$due,new DateTimeZone('UTC'));
            $assignee = $ownerId ? $this->repo->user($ownerId) : null;
            if (!$assignee || !$assignee['active'] || !$dueDate || $dueDate->format('Y-m-d') !== $due || $due < $this->today()) {
                throw new RuntimeException('Assign every pending expense to an active user with a due date on or after today.');
            }
            $carried[] = $case + ['owner_id'=>$ownerId,'owner_name'=>$assignee['name'],'due'=>$due];
        }
        return $this->event('period_locked','period',$key,0,$this->today(),$actor,$data+[
            'snapshot'=>$review['snapshot'],'carried'=>$carried,'evidence'=>R::text($in['evidence'] ?? '',120,true),
        ]);
    }
}
