<section class="card stay-incident"><h2>Departure Incidents &amp; Collection Follow-Up</h2><p>Unreviewed departures, unsecured rooms and outstanding guest balances remain visible until resolved.</p>
<?php $openCases=array_filter($departureCases,fn($row)=>!$row['resolved']);if(!$openCases):?><p>No open departure incidents.</p><?php endif?>
<?php foreach($openCases as $row):$case=$row['case'];$assignment=$case['assignment'];?>
<article class="stay-event"><h3><?=access_h($row['guest_name'])?> · Room <?=access_h($row['room_name'])?></h3>
<p><?=access_h(match($case['status']){'pending'=>'Awaiting Eugene','disputed'=>'Disputed · Investigation Open',default=>'Finalised · Follow-Up Open'})?> · <?=$row['bill']['legacy_unknown']?'Unknown Historical Rate':stay_money($row['bill']['balance'])?><?=$row['bill']['provisional']?' · Provisional':''?></p>
<?php if(!$case['secured']):?><p>Room Security Hold</p><?php endif?>
<?php if($assignment&&$assignment['owner_id']):?><p><?=access_h($assignment['owner_name'].' · Due '.display_date($assignment['due']))?><?=$assignment['due']<gmdate('Y-m-d')?' · Overdue':''?></p><?php endif?>
<?php $link('Show Details',['booking_id'=>$row['id']]);?></article>
<?php endforeach?>
<details><summary>Resolved History</summary><?php foreach($departureCases as $row):if(!$row['resolved']&&(int)$row['report_count']<2)continue;?><p><?=access_h($row['guest_name'].' · Room '.$row['room_name'].($row['case']['status']==='retracted'?' · Report Retracted':($row['resolved']?' · Settled':' · Earlier Retracted Reports')))?> <?php $link('Show Details',['booking_id'=>$row['id']]);?></p><?php endforeach?></details>
</section>
