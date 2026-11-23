<?php
require __DIR__.'/access_ux_guard.php';
$a=access_service();$tag=bin2hex(random_bytes(5));$admin=(int)$repo->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();$password='Synthetic Access Navigation Passphrase 123!';$people=[];$fixtureRoles=[];
foreach(['delegate'=>['users.manage','roles.assign'],'role_editor'=>['roles.manage'],'privilege_editor'=>['privileges.manage'],'limited'=>[]] as $label=>$codes){
 $code='access-ux-'.$label.'-'.$tag;
 $a->manage($admin,'Synthetic Admin Passphrase 123!','create_role',['code'=>$code,'description'=>'Synthetic navigation role','reason'=>'Synthetic access UI fixture']);$role=(int)$repo->run('SELECT id FROM security_roles WHERE name=?',[$code])->fetchColumn();
 $ids=[];foreach($codes as $p)$ids[]=(string)$repo->run('SELECT id FROM security_privileges WHERE code=?',[$p])->fetchColumn();
 $a->manage($admin,'Synthetic Admin Passphrase 123!','assign_privileges',['id'=>$role,'ids'=>$ids,'reason'=>'Synthetic navigation capability fixture']);
 $email=$label.'-'.$tag.'@example.invalid';$a->manage($admin,'Synthetic Admin Passphrase 123!','save_user',['name'=>'Synthetic Access '.$label.' '.$tag,'email'=>$email,'password'=>$password,'reason'=>'Synthetic navigation account fixture']);$id=(int)$repo->run('SELECT id FROM users WHERE email=?',[$email])->fetchColumn();
 $a->changePassword($id,$password,$password.' Changed');$a->manage($admin,'Synthetic Admin Passphrase 123!','assign_roles',['id'=>$id,'ids'=>[(string)$role],'reason'=>'Synthetic navigation role fixture']);$people[$label]=['id'=>$id,'email'=>$email];$fixtureRoles[$label]=$role;
}
file_put_contents(dirname(__DIR__,2).'/.local-setup/access-ux-fixture.json',json_encode(['people'=>$people,'roles'=>$fixtureRoles,'password'=>$password.' Changed','admin'=>$admin],JSON_THROW_ON_ERROR));
echo "PASS exact guarded synthetic System Access fixture created\n";
