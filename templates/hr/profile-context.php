<?php
// Presentation only. HrService still authorizes and validates every submission.
$hrProfileStatus=$staff['status'];
$hrEmploymentActions=[];
if ($can('hr.approve')) {
    if (in_array($hrProfileStatus,['draft','changes_requested'],true)) {
        $hrEmploymentActions=['activate'=>'Approve & Activate','return_changes'=>'Return For Changes'];
    } elseif ($hrProfileStatus==='active' && empty($data['probation_confirmed_at'])) {
        $hrEmploymentActions=['confirm_probation'=>'Confirm Probation'];
    }
}
$hrCanChangeAccess=$can('hr.manage') && $can('users.manage') && $hrProfileStatus!=='exited' && (int)($staff['user_id'] ?? 0)!==$actor;
$hrAccessActions=$hrCanChangeAccess?match($hrProfileStatus){'active'=>['suspend'=>'Suspend Staff & Access'],'suspended'=>['resume'=>'Resume Staff & Access'],default=>[]}:[];
$hrCanLinkAccount=$can('hr.link') && $can('users.manage') && !in_array($hrProfileStatus,['suspended','exit_scheduled','exited'],true);
$hrCanRequestLeave=$hrProfileStatus==='active' && ($can('hr.manage') || ($can('hr.self') && (int)($staff['user_id'] ?? 0)===$actor));
$hrCanReviewLeave=$can('hr.approve') && (int)($staff['user_id'] ?? 0)!==$actor;
$hrCanIssueItem=$can('hr.manage') && $hrProfileStatus!=='exited';
