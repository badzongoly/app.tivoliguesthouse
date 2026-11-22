<?php
if($form==='area_followup'){
    $selected=null;$selectedKey=$_GET['audit_key'] ?? ($_POST['audit_key'] ?? '');
    foreach($areaAudits['rows'] as $candidate)if($candidate['target_id']===$selectedKey)$selected=$candidate;
    if(!$selected){?><p>Open the audit from the register to update its follow-up.</p><?php return;}
    if($selected['data']['status']==='completed'){?><p>This audit has been completed. Record a new audit for a new issue.</p><?php return;}
    ?><p><?=access_h($selected['data']['area'])?> · Week Of <?=access_h(display_date($selected['data']['week']))?></p>
    <form method="post" class="stay-form"><?php stay_fields('area_followup',0,['audit_key'=>$selected['target_id'],'version'=>$selected['version']]);
    stay_select('Follow-Up Status','status',['in_progress'=>'In Progress','completed'=>'Completed'],'in_progress',true);
    stay_input('Action Owner (Optional)','owner',$selected['data']['owner']);stay_input('Due Date (Optional)','due_date',$selected['data']['due_date'],'date');
    stay_textarea('Comment (Optional)','reason',$formValues['reason'] ?? '');
}else{?>
<form method="post" class="stay-form"><?php stay_fields('area_audit',0,['area_revision'=>$auditAreas['revision']]);
$options=[''=>'Choose Area'];foreach($auditAreas['entries'] as $entry)if($entry['active'])$options[$entry['id']]=$entry['name'];
stay_select('Area','area_id',$options,'',true);
stay_input('Action Owner / Due Date (Optional)','owner');
stay_select('Result','result',['pass'=>'Pass','corrective'=>'Corrective Action','unsafe'=>'Unsafe'],'pass',true);
stay_textarea('Comment (Optional)','reason',$formValues['reason'] ?? '');
?>
<?php }$cancelRendered=true;?>
<div class="stay-actions stay-form-actions"><button class="button" type="submit"><?=$form==='area_followup'?'Save Follow-Up':'Record Action'?></button><?php $link('Cancel',['section'=>'areas','range'=>'all']);?></div></form>
