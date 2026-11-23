<?php
// Presentation only; AccessService rechecks permissions and target authority.
$accessPreviewLabels=[];
foreach($privileges as $privilege){
    $label=trim((string)$privilege['description']);
    $accessPreviewLabels[(int)$privilege['id']]=$label!==''?$label:(string)$privilege['code'];
}
$accessRoleCodes=[];$accessRoleNames=[];$accessPrivilegeCodes=array_column($privileges,'code','id');
foreach($roles as $accessRole){
    $accessRoleNames[(int)$accessRole['id']]=$accessRole['name'];
    $accessRoleCodes[(int)$accessRole['id']]=array_values(array_intersect_key($accessPrivilegeCodes,array_flip($rolePrivileges[$accessRole['id']] ?? [])));
}
$accessActorAdmin=false;foreach($userRoles[$user['id']] ?? [] as $accessRoleId)if(($accessRoleNames[$accessRoleId] ?? '')==='admin')$accessActorAdmin=true;
$accessUserEditable=[];
foreach($users as $accessTarget){
    $accessTargetCodes=[];$accessTargetAdmin=false;
    foreach($userRoles[$accessTarget['id']] ?? [] as $accessRoleId){$accessTargetCodes=array_merge($accessTargetCodes,$accessRoleCodes[$accessRoleId] ?? []);if(($accessRoleNames[$accessRoleId] ?? '')==='admin')$accessTargetAdmin=true;}
    $accessUserEditable[(int)$accessTarget['id']]=$accessActorAdmin || (!$accessTargetAdmin&&!array_diff($accessTargetCodes,$permissions));
}
$accessRoleEditable=[];$accessCanCreateUser=$can('users.manage');
foreach($roles as $accessRole){
    $accessRoleEditable[(int)$accessRole['id']]=$accessRole['name']!=='admin'&&($accessActorAdmin||!array_diff($accessRoleCodes[$accessRole['id']],$permissions));
    if($accessRole['name']==='clerk'&&array_diff($accessRoleCodes[$accessRole['id']],$permissions))$accessCanCreateUser=false;
}
$accessDefaultSection=$can('users.manage')||$can('roles.assign')?'staff':($can('roles.manage')?'roles':'privileges');
$accessFailedAction='';$accessFailedId=0;
if($error && ($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
    $accessSubmittedAction=is_string($_POST['action'] ?? null)?$_POST['action']:'';
    $accessFailureSections=['save_user'=>'staff','set_active'=>'staff','revoke_sessions'=>'staff','assign_roles'=>'staff','create_role'=>'roles','assign_privileges'=>'roles','create_privilege'=>'privileges'];
    if(isset($accessFailureSections[$accessSubmittedAction])){$accessDefaultSection=$accessFailureSections[$accessSubmittedAction];$accessFailedAction=$accessSubmittedAction;$accessFailedId=max(0,(int)filter_var($_POST['id'] ?? 0,FILTER_VALIDATE_INT));}
}

$accessSelectedAccount=max(0,(int)filter_var($_GET['account'] ?? 0,FILTER_VALIDATE_INT));
if($accessFailedAction && $accessDefaultSection==='staff')$accessSelectedAccount=$accessFailedId;
