<?php
use Tivoli\Repository\OwnerReports as Reports;
use Tivoli\Domain\Stay\StayRules;

function orUrl(array $query): string
{
    return access_h(access_url('owner_reports.php?'.http_build_query($query)));
}
function orMoney(int|string $minor): string
{
    return 'GH₵'.StayRules::decimal((int)$minor);
}
function orStayCell(int $booking): string|array
{
    global $workspace;
    if ($booking<1) return '—';
    $label='#'.$booking;
    return in_array('bookings.manage',$workspace['permissions']??[],true)
        ? ['label'=>$label,'href'=>access_url('occupancy.php?booking_id='.$booking)]
        : $label;
}
function orTable(string $caption,array $head,array $rows): void
{
    if (!$rows) { echo '<p class="or-note">No matching records.</p>'; return; }
    ?>
    <div class="or-scroll" tabindex="0" role="region" aria-label="<?=access_h($caption)?>">
        <table><caption><?=access_h($caption)?></caption>
            <thead><tr><?php foreach ($head as $label): ?><th scope="col"><?=access_h($label)?></th><?php endforeach ?></tr></thead>
            <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?php if(is_array($value)): ?><a href="<?=access_h($value['href'])?>"><?=access_h($value['label'])?></a><?php else: ?><?=access_h($value)?><?php endif ?></td><?php endforeach ?></tr><?php endforeach ?></tbody>
        </table>
    </div>
    <?php
}
function orBars(array $rows,string $label): void
{
    if (!$rows) { echo '<p class="or-note">No matching records.</p>'; return; }
    $max = max(1,...array_map(static fn($row)=>abs((int)$row[1]),$rows));
    ?>
    <ul class="or-bars" aria-label="<?=access_h($label)?>">
        <?php foreach ($rows as $bar): [$name,$amount]=$bar; ?>
            <li><div><?php if (!empty($bar[2])): ?><a href="<?=$bar[2]?>"><?=access_h($name)?></a><?php else: ?><span><?=access_h($name)?></span><?php endif ?><strong><?=orMoney($amount)?></strong></div>
                <meter min="0" max="<?=$max?>" value="<?=abs((int)$amount)?>" aria-hidden="true" class="<?=(int)$amount<0?'or-negative':''?>"></meter>
            </li>
        <?php endforeach ?>
    </ul>
    <?php
}
function orCards(array $cards): void
{
    ?><dl class="or-cards"><?php foreach ($cards as $card): [$label,$value,$note]=$card; ?>
        <div><dt><?=access_h($label)?></dt><dd><?=access_h($value)?></dd><small><?=access_h($note)?></small>
        <?php if (!empty($card[3])): ?><div><a href="<?=$card[3]?>">View <?=access_h($label)?></a></div><?php endif ?></div>
    <?php endforeach ?></dl><?php
}
function orPager(array $page,array $query,string $anchor='report-detail'): void
{
    if (!$page['count']) return;
    ?>
    <nav class="or-pages" aria-label="Detail pages">
        <?php if ($page['page']>1): ?><a href="<?=orUrl($query+['page'=>$page['page']-1])?>#<?=access_h($anchor)?>">Previous</a><?php endif ?>
        <span>Page <?=$page['page']?> of <?=$page['pages']?> · <?=$page['count']?> records</span>
        <?php if ($page['page']<$page['pages']): ?><a href="<?=orUrl($query+['page'=>$page['page']+1])?>#<?=access_h($anchor)?>">Next</a><?php endif ?>
    </nav>
    <?php
}
function orRoomState(array $room): string
{
    return match ($room['status']) {
        'occupied'=>'Occupied',
        'maintenance'=>'Maintenance',
        'dirty'=>$room['ready_state']==='awaiting_inspection'?'Awaiting Inspection':'Cleaning',
        'vacant'=>$room['ready_state']==='ready'?'Ready':'Vacant',
        default=>'Unknown',
    };
}
$f = $filters;
$base = ['view'=>$view,'start'=>$f['start'],'end'=>$f['end']];
if ($f['method']) $base['method'] = $f['method'];
if ($f['item']) $base['item'] = $f['item'];
if ($f['room']) $base['room'] = $f['room'];
$detailLink=static fn(string $detail): string=>orUrl($base+['detail'=>$detail]).'#source-detail';
$receiptLink=static fn(string $start,string $end): string=>orUrl(['view'=>'cash','start'=>$start,'end'=>$end]).'#report-detail';
$displayStart=$view==='monthly'?$f['month'].'-01':$f['start'];
require __DIR__.'/layouts/start.php';
?>
<div class="or">
    <header class="or-heading">
        <p class="or-kicker">Tivoli Guesthouse · Owner Workspace</p>
        <h1><?=access_h(Reports::VIEWS[$view])?></h1>
        <p><strong>Report Period</strong> <?=access_h(display_date($displayStart))?><?php if($displayStart!==$f['end']):?> to <?=access_h(display_date($f['end']))?><?php endif?> UTC</p>
        <?php if(!empty($data['finance'])&&in_array('payments.view',$workspace['permissions'],true)&&in_array($view,['daily','cash','monthly'],true)):?>
            <p><a href="<?=access_h(access_url('finance.php?'.http_build_query(['page'=>'payments','start'=>$displayStart,'end'=>$f['end']])))?>">Open Payment Log for This Period</a></p>
        <?php endif?>
        <?php if((new \Tivoli\Service\OwnerExceptionService(access_service()))->owner((int)$user['id'])):?><p><a class="or-button" href="<?=access_h(access_url('owner-exceptions.php'))?>">Open Owner Exceptions</a></p><?php endif?>
        <?php if(!empty($data['payroll_adjustments']['count'])):?><p>Staff payroll recording adjustments in this range: cost <?=orMoney($data['payroll_adjustments']['cost'])?>; recorded payout <?=orMoney($data['payroll_adjustments']['paid'])?>. Review full staff cost/profit in Finance &amp; Cash; the operating-expense chart retains its original basis.</p><?php endif?>
        <?php if(!empty($data['purchase_adjustments']['count'])):?><p>Linked purchase/provider corrections are posted in this range. Cost effect <?=orMoney($data['purchase_adjustments']['cost'])?>; recorded payout effect <?=orMoney($data['purchase_adjustments']['paid'])?>. No money moved by these corrections. Original costs, payments and closed snapshots remain retained.</p><?php endif?>
        <?php if($view==='monthly'&&!empty($data['expense_adjustments']['count'])):?><p>Costs include linked expense corrections by posting date. Original expense entries and closed snapshots remain fixed; corrections do not move money.</p><?php endif?>
        <?php if(in_array($view,['daily','cash','monthly'],true)&&!empty($data['receipt_adjustments']['count'])):?><p>Recorded totals include <?=(int)$data['receipt_adjustments']['count']?> receipt corrections: <?=orMoney($data['receipt_adjustments']['delta'])?>, posted in this range. No money moved; earned revenue is unchanged. Original receipt entries and adjustments remain separately visible.</p><?php endif?>
        <?php if(!empty($data['charge_adjustments']['count'])):?><p>Guest charge adjustments posted in this range: <?=orMoney($data['charge_adjustments']['total'])?>. Room <?=orMoney($data['charge_adjustments']['room'])?> · Extras <?=orMoney($data['charge_adjustments']['extras'])?> · Incidentals <?=orMoney($data['charge_adjustments']['incidental'])?>. These change charges, not collections or stock quantities. Earlier closed snapshots remain fixed.</p><?php endif?>
        <p>As of <?=access_h(display_date($f['asOf']))?> UTC · Accra property time<?php if ($view!=='daily'): ?> · GHS<?php endif ?></p>
    </header>
    <nav class="or-tabs" aria-label="Owner report views">
        <?php foreach (Reports::VIEWS as $key=>$label): ?>
            <a href="<?=orUrl(['view'=>$key])?>" <?=$key===$view?'aria-current="page"':''?>><?=access_h($label)?></a>
        <?php endforeach ?>
    </nav>
    <form class="or-filters" method="get" action="<?=access_h(access_url('owner_reports.php'))?>">
        <input type="hidden" name="view" value="<?=access_h($view)?>">
        <?php if ($view==='monthly'): ?>
            <label>Review Month <input type="month" name="month" value="<?=access_h($f['month'])?>" max="<?=access_h(substr($f['today'],0,7))?>" required></label>
        <?php else: ?>
            <?php if ($view!=='daily'): ?><label>From <input type="date" name="start" value="<?=access_h($f['start'])?>" max="<?=access_h($f['today'])?>" required></label><?php endif ?>
            <label><?=$view==='daily'?'Report Date':'Through'?><input type="date" name="end" value="<?=access_h($f['end'])?>" max="<?=access_h($f['today'])?>" required></label>
        <?php endif ?>
        <?php if ($view==='cash'): ?>
            <label>Receipt Method <select name="method"><option value="">All methods</option>
                <?php foreach (Reports::METHODS as $key=>$label): ?><option value="<?=$key?>" <?=$key===$f['method']?'selected':''?>><?=$label?></option><?php endforeach ?>
            </select></label>
        <?php elseif ($view==='room' && $data): ?>
            <label>Room <select name="room"><option value="">All rooms</option>
                <?php foreach ($data['rooms'] as $room): ?><option value="<?=(int)$room['id']?>" <?=(int)$room['id']===$f['room']?'selected':''?>><?=access_h($room['name'])?></option><?php endforeach ?>
            </select></label>
        <?php elseif ($view==='extras' && $data): ?>
            <label>Item <select name="item"><option value="">All items</option>
                <?php foreach ($data['catalog'] as $item): ?><option value="<?=(int)$item['extra_id']?>" <?=(int)$item['extra_id']===$f['item']?'selected':''?>><?=access_h($item['name'])?></option><?php endforeach ?>
            </select></label>
        <?php endif ?>
        <button type="submit">Apply Filters</button>
    </form>

    <p><a href="<?=access_h(access_url('finance.php?page=discrepancies'))?>">Review Cash &amp; MoMo Discrepancies</a></p>
    <?php if($expenseCorrectionAttention):?><p class="or-alert" role="status"><?=$expenseCorrectionAttention?> expense correction requests need attention. <a href="<?=access_h(access_url('financial-corrections.php?area=expenses&page=requests'))?>">Review Expense Corrections</a></p><?php endif?>
    <?php if($purchaseCorrectionAttention):?><p class="or-alert" role="status"><?=$purchaseCorrectionAttention?> purchase correction cases or supplier balances need attention. <a href="<?=access_h(access_url('financial-corrections.php?area=purchases'))?>">Review Purchase Corrections</a></p><?php endif?>
    <?php if($guestChargeAttention):?><p class="or-alert" role="status"><?=$guestChargeAttention?> guest charge requests need attention. <a href="<?=access_h(access_url('financial-corrections.php?area=charges&page=requests'))?>">Review Guest Charges</a></p><?php endif?>
    <?php if($correctionAttention):?><p class="or-alert" role="status"><?=$correctionAttention?> open receipt correction requests need attention. <a href="<?=access_h(access_url('financial-corrections.php?page=requests'))?>">Review Financial Corrections</a></p><?php endif?>
    <?php if($payrollAttention):?><p class="or-alert" role="status"><?=$payrollAttention?> payroll records need review, settlement or publication. <a href="<?=access_h(access_url('payroll.php?page=owner'))?>">Review Payroll</a></p><?php endif?>
    <?php if($housekeepingAttention):?><p class="or-alert" role="status"><?=$housekeepingAttention?> housekeeping exceptions or follow-up reviews need attention. <a href="<?=access_h(access_url('housekeeping.php?page=owner'))?>">Review Housekeeping</a></p><?php endif?>
    <?php if($stockCountAttention):?><p class="or-alert" role="status"><?=$stockCountAttention?> stock counts need review or recount. <a href="<?=access_h(access_url('stock-counts.php?page=owner'))?>">Review Stock Counts</a></p><?php endif?>
    <?php if($purchaseAttention):?><p class="or-alert" role="status"><?=$purchaseAttention?> purchase approvals, cost reviews or settlements need attention. <a href="<?=access_h(access_url('purchases.php?page=owner'))?>">Review Purchase Requests</a></p><?php endif?>
    <?php if($handoverAttention):?><p class="or-alert" role="status"><?=$handoverAttention?> handovers or cash transfers need attention. <a href="<?=access_h(access_url('handover.php?page=owner'))?>">Review Shift Handovers</a></p><?php endif?>
    <?php if($departureIncidentCount):?><p class="or-alert" role="status"><?=$departureIncidentCount?> departure incidents or collection follow-ups need attention. <a href="<?=access_h(access_url('occupancy.php?section=departures'))?>">Review Departure Incidents</a></p><?php endif?>
    <?php if ($error): ?>
        <p role="alert" class="or-alert"><?=access_h($error)?></p>
    <?php elseif ($f['detail'] && $data['detail']===null): ?>
        <p class="or-note">Source records are unavailable for this report.</p>
        <a href="<?=orUrl($base)?>">Back to Report</a>
    <?php elseif ($data['detail']!==null): ?>
        <?php require __DIR__.'/owner-report-detail.php'; ?>
    <?php elseif ($view==='daily'):
        $occupied = count(array_filter($data['rooms'],static fn($r)=>$r['status']==='occupied'));
        $conflicts = array_values(array_filter($data['rooms'],static fn($r)=>(int)$r['active']!==($r['status']==='occupied'?1:0)));
        orCards([
            ['Occupied Rooms',$data['rooms']?$occupied.' / '.count($data['rooms']):'Unavailable','Current room register','#room-status'],
            ['Payments Recorded',orMoney($data['payment']['minor']),$data['payment']['count'].($data['finance']?' receipts on ':' guest receipts on ').display_date($f['end']),$receiptLink($f['end'],$f['end'])],
            ['Room Charges at Departure',$data['roomCharges']===null?'Unavailable':orMoney($data['roomCharges']['minor']),$data['roomCharges']===null?'Saved departure snapshots unavailable':$data['roomCharges']['count'].' saved snapshots · historical coverage partial',$data['roomCharges']===null?null:$detailLink('room-charges')],
            ['Review Items',count($conflicts)+(int)$data['pending'],count($conflicts).' room conflicts · '.($data['pending']??'unavailable').' MoMo awaiting review','#daily-exceptions'],
        ]);
        ?>
        <section class="or-panel" id="room-status">
            <div class="or-section-title"><div><h2>Room Status</h2><p>Current snapshot · service rooms excluded</p></div>
                <a href="<?=access_h(access_url('occupancy.php'))?>">Open Room Board</a></div>
            <?php if (!$data['rooms']): ?><p class="or-note">No rooms in the current register.</p><?php endif ?>
            <ul class="or-rooms">
                <?php foreach ($data['rooms'] as $room): ?>
                    <li><strong><?=access_h($room['name'])?></strong><span><?=access_h($room['type'])?></span>
                        <span class="or-status or-<?=access_h($room['status'])?>"><?=orRoomState($room)?></span>
                        <?php if ((int)$room['active']!==($room['status']==='occupied'?1:0)): ?>
                            <small class="or-alert-text">Conflict · <?=(int)$room['active']?> active stays</small>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
        <div class="or-grid">
            <section class="or-panel"><h2>Arrivals & Departures</h2>
                <p><?=access_h(display_date($f['end']))?> · <?=$data['stayCounts']['arrivals']?> recorded arrivals · <?=$data['stayCounts']['departures']?> recorded departures</p>
                <a href="<?=$detailLink('arrivals')?>">View Arrivals</a> · <a href="<?=$detailLink('departures')?>">View Departures</a>
            </section>
            <section class="or-panel" id="daily-exceptions"><h2>Exceptions</h2>
                <?php if (!$conflicts && !$data['pending']): ?><p class="or-note">No exceptions in the available sources.</p><?php endif ?>
                <?php if ($conflicts): ?><ul><?php foreach ($conflicts as $room): ?>
                    <li><?=access_h($room['name'])?> · <?=orRoomState($room)?> · <?=(int)$room['active']?> active stays</li>
                <?php endforeach ?></ul><a href="<?=access_h(access_url('occupancy.php'))?>">Review Room Conflicts</a><?php endif ?>
                <?php if ($data['pending']): ?><p><?=$data['pending']?> recorded MoMo receipts await staff review.</p>
                    <a href="<?=$detailLink('momo')?>">View MoMo Receipts Awaiting Review</a>
                <?php endif ?>
            </section>
        </div>
    <?php elseif ($view==='room'):
        orCards([
            ['Recorded Occupied Room-Nights',$data['occupied'],'Deduplicated by room and date','#room-night-calendar'],
            ['Physical Occupancy','Unavailable','Requires historical room capacity'],
            ['Sellable Occupancy','Unavailable','Requires complete historical block intervals'],
        ]);
        ?>
        <section class="or-panel" id="room-night-calendar">
            <h2>Recorded Room-Night Calendar</h2>
            <p class="or-note">Check-in night included, departure date excluded. The current night is still in progress. Short stays and cancelled stays are excluded when identified. <?=$data['legacy']?> legacy stay-night records have unknown stay type. Past room moves and blocks have partial coverage.</p>
            <p>● Recorded stay · ! Overlap · — No stay found</p>
            <?php if (!$data['rooms']): ?><p class="or-note">No rooms in the current register.</p><?php else: ?>
            <div class="or-scroll" tabindex="0" role="region" aria-label="Room-night calendar">
                <table class="or-heatmap"><caption><?=access_h(display_date($f['start']).' to '.display_date($f['end']))?> · <?=$data['occupied']?> recorded room-nights</caption>
                    <thead><tr><th scope="col">Room</th>
                        <?php foreach ($data['days'] as $day): ?><th scope="col"><time datetime="<?=$day?>"><?=access_h(display_date($day))?></time></th><?php endforeach ?>
                        <th scope="col">Nights</th></tr></thead>
                    <tbody><?php foreach ($data['rooms'] as $room):
                        if ($f['room'] && $f['room']!==(int)$room['id']) continue;
                        $cells = $data['byRoom'][$room['id']] ?? [];
                        ?><tr><th scope="row"><?=access_h($room['name'])?></th>
                        <?php foreach ($data['days'] as $day):
                            $cell = $cells[$day] ?? null;
                            $overlap = $cell && (int)$cell['stays']>1;
                            ?><td class="<?=$overlap?'or-heat-conflict':($cell?'or-heat-sold':'')?>">
                            <?php if ($cell && !$overlap): ?>
                                <a aria-label="<?=access_h($room['name'].' · '.display_date($day).' · recorded stay #'.$cell['first_id'])?>" href="<?=access_h(access_url('occupancy.php?booking_id='.(int)$cell['first_id']))?>">●</a>
                            <?php elseif ($overlap): ?><span aria-label="<?=access_h($cell['stays'].' overlapping stays')?>">!</span>
                            <?php else: ?><span aria-label="No stay found">—</span><?php endif ?></td>
                        <?php endforeach ?><td><?=count($cells)?></td></tr>
                    <?php endforeach ?></tbody>
                </table>
            </div>
            <?php endif ?>
            <?php if ($data['overlaps']): ?><p class="or-alert"><?=count($data['overlaps'])?> room-date overlaps require review. Each affected night is counted once.</p><?php endif ?>
        </section>
    <?php elseif ($view==='cash'):
        orCards([
            ['Payments Recorded',orMoney(array_sum(array_map('intval',array_column($data['methods'],'minor')))),$data['page']['count'].' receipts · receipt timestamp','#report-detail'],
            ['Recorded Refunds',$data['refunds']===null?'Unavailable':orMoney($data['refunds']),'Separate outflow · all methods',$data['refunds']===null?null:$detailLink('refunds')],
            ['Physical Cash Variance','Unavailable','Tivoli’s workflow uses full cash deposits without a physical count'],
        ]);
        ?>
        <div class="or-grid">
            <section class="or-panel"><h2>Receipts by Method</h2>
                <?php orBars(array_map(static fn($r)=>[Reports::METHODS[$r['method']],$r['minor'],orUrl(array_replace($base,['method'=>$r['method']])).'#report-detail'],$data['methods']),'Recorded receipt amounts by method'); ?>
                <p class="or-note"><?=$data['finance']?'Guest and other-income receipts, including advances.':'Guest receipts only · other-income tracking unavailable.'?> <?=$data['undated']?> undated guest payments are outside date filters.</p>
            </section>
            <section class="or-panel"><h2>MoMo Review Coverage</h2>
                <?php if (!$data['finance']): ?><p class="or-note">Unavailable · finance matching has not been activated.</p>
                <?php else: orBars(array_map(static fn($r)=>[$r['coverage'],$r['minor']],$data['momo']),'All MoMo receipts in the selected dates'); ?>
                    <p class="or-note">Current effective MoMo receipts from the selected original-date cohort, as of report time. Recording adjustments are excluded from matching tasks; channel totals above retain separately dated adjustments. Matches use staff-recorded wallet evidence.</p>
                <?php endif ?>
                <?php if ($data['finance']): ?><a href="<?=$detailLink('momo')?>">View MoMo Receipt Coverage</a><?php endif ?>
            </section>
        </div>
        <section class="or-panel"><h2>Sales Cash by Recorder</h2>
            <?php if ($data['custody']===null): ?><p class="or-note">Unavailable · deposit tracking has not been activated.</p>
            <?php else: ?>
                <p class="or-note">Cash received in the selected dates, with deposit allocations as of report time. Recorder names come from the receipt audit. Historical cash outside custody tracking: <?=orMoney($data['custody']['historical'])?>.</p>
                <?php orTable('Tracked cash receipts and deposit allocations',['Recorder','Cash Received','Still Held','In Transit','Wallet Confirmed'],
                    array_map(static fn($r)=>[$r['name'],orMoney($r['receipts']),orMoney($r['held']),orMoney($r['transit']),orMoney($r['confirmed'])],$data['custody']['rows'])); ?>
                <a href="<?=$detailLink('custody')?>">View Tracked Cash Receipts</a>
            <?php endif ?>
        </section>
        <section class="or-panel"><h2>Cash → MoMo Deposit Movement</h2>
            <p class="or-note">Gross internal transfers by deposit date. Deposits are separate from receipts, refunds and cash expenses.</p>
            <?php if ($data['deposits']===null): ?><p class="or-note">Unavailable · deposit tracking has not been activated.</p>
            <?php else: orTable('Deposits in the selected dates',['State','Deposits','Gross Amount'],
                array_map(static fn($r)=>[$r['state'],$r['count'],orMoney($r['minor'])],$data['deposits'])); ?>
            <a href="<?=$detailLink('deposits')?>">View Deposit Movements</a><?php endif ?>
            <a href="<?=access_h(access_url('finance.php?page=deposits'))?>">Open Current Cash Custody Register</a>
        </section>
        <section class="or-panel" id="report-detail"><h2>Receipt Detail</h2>
            <?php orTable('Receipts in the selected dates',['Recorded At','Source','Stay','Method','Amount'],
                array_map(static fn($r)=>[display_date($r['created_at']),($r['source']==='payment'?'Guest payment #':($r['source']==='correction'?'Receipt adjustment #':'Other income #')).$r['id'],
                    orStayCell((int)$r['booking_id']),Reports::METHODS[$r['method']],orMoney($r['minor'])],$data['receipts']));
            orPager($data['page'],$base); ?>
        </section>
    <?php elseif ($view==='extras'):
        orCards([
            ['Recorded Extras Charges',orMoney($data['total']),$data['page']['count'].' stored sale lines plus linked adjustments','#report-detail'],
            ['Extras per Room-Night','Unavailable','Requires a verified common room and sales scope'],
            ['Unpaid Extras','Unavailable','Room and extras payment allocations are unknown'],
            ['Charge Adjustments',orMoney($data['charge_adjustments']['extras']??0),'Linked credits / debits by posting date · no stock movement','#charge-adjustments'],
        ]);
        ?>
        <div class="or-grid">
            <section class="or-panel"><h2>Top Items</h2><p class="or-note">Top 12 by net recorded charges, including linked adjustments posted in this range. Original sale quantities stay unchanged; unlinked lines remain separate.</p>
                <?php orBars(array_map(static fn($r)=>[$r['name'].' · '.$r['qty'].' units'.($r['unlinked']?' · line #'.$r['unlinked']:''),$r['minor'],$r['extra_id']?orUrl(array_replace($base,['item'=>(int)$r['extra_id']])).'#report-detail':null],$data['items']),'Top extras by charge amount'); ?>
            </section>
            <section class="or-panel"><h2>Daily Extras Charges</h2><p class="or-note">Original sales use their stored timestamp; charge corrections use their posting date.</p>
                <div class="or-series" tabindex="0" role="region" aria-label="Daily extras charges"><?php
                    $bars = []; foreach ($data['series'] as $day=>$minor) $bars[] = [display_date($day),$minor,orUrl(array_replace($base,['start'=>$day,'end'=>$day])).'#report-detail'];
                    orBars($bars,'Daily recorded extras charges'); ?></div>
            </section>
        </div>
        <section class="or-panel"><h2>Stock Link Coverage</h2>
            <p class="or-note"><?=$data['unlinked']===null?'Tracked-item settings unavailable.':access_h($data['unlinked'].' lines for currently tracked items have no confirmed sale link. Older movements may lack links. Sale amounts are counted independently.')?></p>
            <a href="<?=access_h(access_url('occupancy.php?section=extras&tab=stock'))?>">Open Stock Register</a>
        </section>
        <section class="or-panel" id="report-detail"><h2>Charge Detail</h2>
            <?php orTable('Recorded extras sale lines',['Recorded At','Line','Stay','Room','Item Snapshot','Quantity','Unit Price','Charge','Stock Link'],
                array_map(static fn($r)=>[display_date($r['created_at']),'#'.$r['id'],orStayCell((int)$r['booking_id']),$r['room']??'Unknown',
                    $r['description']?:($r['extra_id']?'Item #'.$r['extra_id']:'Unknown'),$r['qty'],'GH₵'.$r['unit_price'],
                    orMoney($r['minor']),$r['stock_link']?'Linked':'No confirmed link'],$data['lines']));
            orPager($data['page'],$base); ?>
            <section id="charge-adjustments">
                <h3>Linked Charge Adjustments</h3>
                <?php $extraAdjustments=array_filter($data['charge_adjustments']['rows']??[],static fn($entry)=>$entry['data']['charge_type']==='extra');
                orTable('Extras charge adjustments',['Posting Date','Adjustment','Stay','Original Line','Amount'],
                    array_map(static fn($entry)=>[display_date($entry['effective_date']),'#'.$entry['id'],
                        orStayCell((int)$entry['booking_id']), '#'.$entry['data']['source_id'],orMoney($entry['amount_minor'])],$extraAdjustments)); ?>
            </section>
        </section>
    <?php elseif ($view==='monthly'):
        $selected = $data['months'][$f['month']];
        orCards([
            ['Payments Recorded',orMoney($selected['receipts']),'Receipt timestamp · all methods',$receiptLink($displayStart,$f['end'])],
            ['Recorded Expenses',orMoney($selected['expenses']),'Expense business date · posted expense register',$detailLink('expenses')],
            ['Operating Result','Unavailable','Requires comparable earned-income and cost coverage'],
        ]);
        $bars = [];
        foreach ($data['months'] as $month=>$values) {
            $label = display_date($month).($month===substr($f['today'],0,7)?' · to date':'');
            $monthEnd=min($f['end'],(new DateTimeImmutable($month.'-01'))->modify('last day of this month')->format('Y-m-d'));
            $bars[] = [$label.' · Payments',$values['receipts'],$receiptLink($month.'-01',$monthEnd)];
            $bars[] = [$label.' · Expenses',$values['expenses'],orUrl(['view'=>'monthly','month'=>$month,'detail'=>'expenses']).'#source-detail'];
        }
        ?>
        <?php if ($data['finance']&&in_array('reports.view',$workspace['permissions'],true)): ?>
            <p><a href="<?=access_h(access_url('finance.php?'.http_build_query(['page'=>'profit','period'=>'month','start'=>$displayStart])))?>">Open Management Profit Review for This Month</a></p>
        <?php endif ?>
        <div class="or-grid">
            <section class="or-panel"><h2>Six-Month Comparison</h2>
                <p class="or-note"><?=$data['finance']?'Recorded receipts include advances.':'Guest receipts only · other-income tracking unavailable.'?> Expenses cover the posted expense register. Refunds, internal transfers, and separate payroll or provider events are outside these series.</p>
                <?php orBars($bars,'Six-month receipts and expenses on the same GHS scale'); ?>
            </section>
            <section class="or-panel"><h2>Expense Categories · <?=access_h(display_date($f['month']))?></h2>
                <?php orBars(array_map(static fn($r)=>[$r['name'],$r['minor'],orUrl($base+['detail'=>'expenses','detail_category'=>$r['category_id']??'uncategorised']).'#source-detail'],$data['categories']),'Expenses by category in the selected month'); ?>
                <a href="<?=$detailLink('expenses')?>">View Expenses for This Month</a>
            </section>
        </div>
    <?php endif ?>
</div>
<?php require __DIR__.'/layouts/end.php'; ?>

