<?php
use Tivoli\Domain\Stay\StayRules as R;
$closed = $weekly['close'];
$totals = $weekly['snapshot'];
$cashAtClose = $totals['weekly_cash'] ?? null;
$outcomeLabel = static fn(array $event): string => match ($event['kind']) {
    'week_kept_open' => 'Week Kept Open',
    'week_case_resolved' => 'Follow-Up Evidence Recorded',
    'period_reopened' => 'Legacy Weekly Review Reopened',
    default => match ($event['data']['outcome'] ?? '') {
        'carry' => 'Locked With Open Exceptions',
        'clean' => 'Reconciled and Locked',
        default => 'Locked by Legacy Weekly Review',
    },
};
?>
<?php if(!empty($weekly['departure_incidents'])):?><section class="card"><h2>Open Departure Incidents</h2><p><?=$weekly['departure_incidents']?> incident or collection follow-ups remain visible across review periods. <a href="<?=access_h(access_url('occupancy.php?section=departures'))?>">Review Open Cases</a></p></section><?php endif?>
<?php if(isset($totals['stock_costing'])):$stockCost=$totals['stock_costing'];?><section class="card"><h2>Stock Cost Coverage</h2><p><?=$closed?'Retained Closed Snapshot':'Current Week Review'?> · <?=$stockCost['complete']?'Covered Stock Cost Result':'Incomplete / Partial Stock Cost Coverage'?></p><p>Recorded-Cost Result <?=access_h(fm($stockCost['recorded_profit']))?> · Linked Inventory Purchase Addback <?=access_h(fm($stockCost['acquisition']))?> · Consumed Stock Costs <?=access_h(fm(array_sum($stockCost['costs'])))?>.</p><p><?=$stockCost['complete']?'Stock Cost Management Profit':'Result Before Missing Costs / Partial Coverage'?> <?=access_h(fm($stockCost['before_unknown']))?>. Closing the cash review does not make unknown stock costs complete.</p><a href="<?=access_h(access_url('finance.php?page=profit&start='.$weekly['start']))?>#stock-costing">Open Stock Cost Review</a></section><?php endif?>
<section class="card" id="weekly-totals" tabindex="-1">
    <form method="get" class="finance-inline">
        <input type="hidden" name="page" value="weekly">
        <label>Week Starting Monday <input type="date" name="start" required value="<?=access_h($weekly['start'])?>" max="<?=access_h($s->priorWeekStart())?>"></label>
        <button>Review Week</button>
    </form>
    <h2><?=access_h(display_date($weekly['start']).' – '.display_date($weekly['end']))?> · <?=access_h($closed?$outcomeLabel($closed):'Week Open')?></h2>
    <nav class="app-section-nav" aria-label="Weekly review sections">
        <a href="#weekly-totals">Review Totals</a>
        <a href="#weekly-exceptions">Exceptions</a>
        <a href="#weekly-decision">Close Decision</a>
        <a href="#weekly-history">Review History</a>
    </nav>
    <?php if (isset($totals['receipts'],$totals['refunds'],$totals['expenses'])): ?>
    <div class="finance-scroll">
        <table><tbody>
            <?php foreach (['Cash Receipts'=>$totals['receipts']['cash']??0,'MoMo Receipts'=>$totals['receipts']['momo']??0] as $label=>$amount): ?>
                <tr><th scope="row"><?=access_h($label)?></th><td><?=access_h(fm($amount))?></td></tr>
            <?php endforeach ?>
            <?php foreach (array_diff_key($totals['receipts'],['cash'=>0,'momo'=>0]) as $method=>$amount): ?>
                <tr><th scope="row"><?=access_h(ucfirst($method).' Receipts (Historical Method)')?></th><td><?=access_h(fm($amount))?></td></tr>
            <?php endforeach ?>
            <tr><th scope="row">Total Recorded Receipts</th><td><?=access_h(fm(array_sum($totals['receipts'])))?></td></tr>
            <tr><th scope="row">Refunds Recorded</th><td><?=access_h(fm($totals['refunds']))?></td></tr>
            <tr><th scope="row">Net Receipts</th><td><?=access_h(fm(array_sum($totals['receipts'])-$totals['refunds']))?></td></tr>
            <tr><th scope="row">Approved Expenses</th><td><?=access_h(fm($totals['expenses']))?></td></tr>
            <?php if ($cashAtClose): ?>
                <tr><th scope="row">Tracked Cash Deposits Confirmed</th><td><?=access_h(fm($cashAtClose['confirmed']))?></td></tr>
                <tr><th scope="row">Tracked Cash Awaiting Deposit or Confirmation</th><td><?=access_h(fm($cashAtClose['outstanding']))?></td></tr>
            <?php endif ?>
        </tbody></table>
    </div>
    <p>Cash deposits move existing cash to MoMo. Deposit tracking covers cash receipts captured after finance activation.</p>
    <?php else: ?><p>This legacy review has no retained figures to display here.</p><?php endif ?>
</section>

<section class="card" id="weekly-exceptions" tabindex="-1">
    <h2><?=$closed?'Carried Exceptions':'Exceptions and Follow-Up'?></h2>
    <?php if (!$closed): ?>
        <?php if ($weekly['unmatched']): ?>
            <h3>Unmatched MoMo Receipts</h3>
            <p>Match every receipt for this week against the wallet before locking.</p>
            <ul><?php foreach ($weekly['unmatched'] as $receipt): ?>
                <li><?=access_h(($receipt['source']==='income'?'Activity':'Guest').' Receipt #'.$receipt['id'].' · '.display_date($receipt['created_at']).' · '.fm(R::minor($receipt['amount'])))?>
                    <?php if ($receipt['reference_no']): ?> · <?=access_h($receipt['reference_no'])?><?php endif ?>
                    <?php finance_action('Match Wallet Evidence','receipt_match',$receipt['id'],$receipt['source']); ?>
                </li>
            <?php endforeach ?></ul>
        <?php endif ?>
        <?php if ($cashAtClose['outstanding']>0): ?>
            <p>Confirm the outstanding deposits for this week before locking. <a href="<?=access_h(finance_url(['page'=>'deposits']))?>">Review Cash Deposits</a></p>
        <?php endif ?>
        <?php if ($weekly['pendingRequests']): ?>
            <p>Review the <?=access_h((string)$weekly['pendingRequests'])?> pending provider or payroll requests for this week before locking.</p>
        <?php endif ?>
    <?php endif ?>
    <?php if ($weekly['pending']): ?>
        <p>These expense submissions were excluded from approved expenses. <a href="<?=access_h(access_url('expenses.php'))?>">Open Expense Approval</a></p>
        <?php foreach ($weekly['pending'] as $case): $caseId=$case['id']; $followup=$weekly['followups'][$caseId]??null; ?>
            <details class="finance-source">
                <summary><?=access_h('Expense Submission #'.$caseId.' · '.display_date($case['date']).' · '.fm($case['amount_minor']))?></summary>
                <?php if ($closed): ?>
                    <p><?=access_h('Assigned to '.$case['owner_name'].' · Due '.display_date($case['due']))?></p>
                    <?php if ($followup): ?>
                        <p><?=access_h('Evidence recorded by '.$followup['actor_name'].' · '.display_date($followup['created_at']).' · '.$followup['data']['evidence'])?></p>
                        <?php if ($followup['data']['comment']): ?><p><?=access_h($followup['data']['comment'])?></p><?php endif ?>
                    <?php else: ?>
                        <form method="post" class="finance-form" data-confirm="Record this evidence against the carried expense?">
                            <?php ff('week_followup',['start'=>$weekly['start'],'case_id'=>(string)$caseId]); ?>
                            <?php fi('Evidence Reference','evidence'); fi('Follow-Up Comment','comment','','text',false); ?>
                            <p>Approve or reject the original submission in Expenses first. Eugene can approve a carried expense into today's open period using late posting evidence. Then record the evidence here.</p>
                            <button>Resolve With Evidence</button>
                        </form>
                    <?php endif ?>
                <?php else: ?>
                    <div class="finance-form">
                        <label>Assign To <select name="owner_<?=$caseId?>" form="weekly-close-form">
                            <option value="">Choose Active User</option>
                            <?php foreach ($weekly['assignees'] as $assignee): ?>
                                <option value="<?=(int)$assignee['id']?>"><?=access_h($assignee['name'])?></option>
                            <?php endforeach ?>
                        </select></label>
                        <label>Due Date <input name="due_<?=$caseId?>" type="date" min="<?=access_h($today)?>" form="weekly-close-form"></label>
                    </div>
                <?php endif ?>
            </details>
        <?php endforeach ?>
    <?php elseif ($closed || (!$weekly['unmatched'] && !$cashAtClose['outstanding'] && !$weekly['pendingRequests'])): ?>
        <p><?=$closed?'No expenses were carried by this review.':'No open exceptions were found for this week.'?></p>
    <?php endif ?>
</section>

<section class="card" id="weekly-decision" tabindex="-1">
    <h2>Close Decision</h2>
    <?php if ($closed): ?>
        <p>This week is locked. Authorized late entries post in an open period and retain their original activity date.</p>
    <?php else: ?>
        <form id="weekly-close-form" method="post" class="finance-form" data-confirm="Confirm this weekly decision? Locking freezes the reviewed totals and records any assigned exceptions.">
            <?php ff('week_decide',['start'=>$weekly['start'],'review_token'=>$weekly['token']]); ?>
            <fieldset>
                <legend>Choose an Outcome</legend>
                <label class="finance-check"><input type="radio" name="outcome" value="clean" required> Lock Reconciled Week</label>
                <label class="finance-check"><input type="radio" name="outcome" value="carry"> Lock With Open Exceptions</label>
                <label class="finance-check"><input type="radio" name="outcome" value="open"> Keep Week Open</label>
            </fieldset>
            <label class="finance-check"><input type="checkbox" name="complete" value="1"> I reviewed the receipts, refunds, expenses and deposits against their source records.</label>
            <p>To lock the week, confirm the source review and enter an evidence reference. Enter a reason if you carry expenses forward or keep the week open.</p>
            <?php fi('Source Evidence Reference','evidence','','text',false); fi('Decision Reason','comment','','text',false); ?>
            <button>Review Decision</button>
        </form>
    <?php endif ?>
</section>

<section class="card" id="weekly-history" tabindex="-1">
    <h2>Review History</h2>
    <?php if (!$weekly['history']): ?><p>No decisions recorded for this week.</p><?php endif ?>
    <?php foreach ($weekly['history'] as $entry): ?>
        <details>
            <summary><?=access_h($outcomeLabel($entry).' · '.$entry['actor_name'].' · '.display_date($entry['created_at']))?></summary>
            <?php if (!empty($entry['data']['comment'])): ?><p><?=access_h($entry['data']['comment'])?></p><?php endif ?>
            <?php if (!empty($entry['data']['evidence'])): ?><p><?=access_h('Evidence: '.$entry['data']['evidence'])?></p><?php endif ?>
            <?php if (isset($entry['data']['case_id'])): ?><p>Expense Submission #<?=(int)$entry['data']['case_id']?></p><?php endif ?>
            <?php if (isset($entry['data']['snapshot']['receipts'],$entry['data']['snapshot']['refunds'])): ?>
                <p><?=access_h(($entry['kind']==='week_kept_open'?'Net receipts at decision: ':'Saved net receipts: ').fm(array_sum($entry['data']['snapshot']['receipts'])-$entry['data']['snapshot']['refunds']))?></p>
            <?php endif ?>
        </details>
    <?php endforeach ?>
</section>
