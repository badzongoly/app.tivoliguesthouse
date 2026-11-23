<?php
$detail=$f['detail'];
[$title,$head]=match($detail) {
    'room-charges'=>['Room Charges at Departure',['Posted At','Room','Stay','Source','Amount']],
    'arrivals'=>['Recorded Arrivals',['Check-In','Room','Stay']],
    'departures'=>['Recorded Departures',['Departure','Room','Stay']],
    'refunds'=>['Recorded Refunds',['Recorded At','Refund','Stay','Original Receipt','Amount']],
    'expenses'=>['Recorded Expenses',['Business Date','Entry','Original Expense','Category','Vendor','Amount']],
    'momo'=>['MoMo Receipt Coverage',['Recorded At','Receipt','Stay','Coverage','Amount']],
    'custody'=>['Tracked Cash Receipts',['Recorded At','Receipt','Recorder','Received','Still Held','In Transit','Wallet Confirmed']],
    'deposits'=>['Cash Deposit Movements',['Deposit Date','Deposit','State','Gross Amount']],
};
$rows=$data['detail']['rows'];
$detailQuery=$base+['detail'=>$detail];
if ($f['detail_category']!=='') $detailQuery['detail_category']=$f['detail_category'];
$sourceName=static fn(array $row): string=>($row['source']==='income'?'Other income #':'Receipt #').$row['id'];
$stay=static fn(array $row): string|array=>orStayCell((int)($row['booking_id']??0));
?>
<section class="or-panel" id="source-detail" tabindex="-1">
    <a href="<?=orUrl($base)?>">Back to Report</a>
    <h2><?=access_h($title)?></h2>
    <?php if ($f['detail_category']!==''): ?>
        <a href="<?=orUrl($base+['detail'=>'expenses'])?>#source-detail">View All Expenses for This Month</a>
    <?php endif ?>
    <?php if(in_array($detail,['momo','custody','deposits','refunds'],true)&&$f['method']): ?>
        <p class="or-note">The receipt method filter applies to recorded receipts. This section uses the same scope as its own report total.</p>
    <?php endif ?>
    <?php orTable($title,$head,array_map(static function(array $row) use($detail,$sourceName,$stay): array {
        $posted=display_date($row['posting_at']??$row['created_at']);
        return match($detail) {
            'room-charges'=>[$posted,$row['room'],$stay($row),$row['is_adjustment']?'Charge adjustment':'Departure charge',orMoney($row['minor'])],
            'arrivals','departures'=>[$posted,$row['room'],$stay($row)],
            'refunds'=>[$posted,'#'.$row['id'],$stay($row),'#'.$row['payment_id'],orMoney($row['minor'])],
            'expenses'=>[$posted,($row['source']==='expense'?'Expense #':'Adjustment #').$row['id'],'#'.$row['source_expense'],$row['category'],$row['vendor'],orMoney($row['minor'])],
            'momo'=>[$posted,$sourceName($row),$stay($row),$row['coverage'],orMoney($row['minor'])],
            'custody'=>[$posted,$sourceName($row),$row['name'],orMoney($row['receipts']),orMoney($row['held']),orMoney($row['transit']),orMoney($row['confirmed'])],
            'deposits'=>[$posted,'#'.$row['id'],$row['state'],orMoney($row['minor'])],
        };
    },$rows));
    orPager($data['detail']['page'],$detailQuery,'source-detail'); ?>
</section>
