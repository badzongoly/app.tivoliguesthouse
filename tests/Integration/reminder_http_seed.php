<?php
require __DIR__.'/weekly_audit_http_seed.php';
$current=$s->detail($owner,$id);$legacy->execute($staff,'room_audit',['room_id'=>(string)$room,'result'=>'unsafe','reason'=>'Synthetic reminder critical follow-up','followup_id'=>(string)$id,'followup_version'=>$current['version']],wk());
$notify=new \Tivoli\Service\ReminderService($a,gmdate('Y-m-d H:i:s'),access_config(),['weekly:'.$id]);$notify->generate($owner,wk());$p=$notify->prefs($staff);$notify->execute($staff,'preferences',['email'=>'1','whatsapp'=>'1','consent'=>'1','phone'=>'+233000000014','version'=>(string)$p['version']],wk());
$fixture['reminders']=['owner'=>$notify->inbox($owner)['rows'][0]['id'],'staff'=>$notify->inbox($staff)['rows'][0]['id']];file_put_contents(dirname(__DIR__,2).'/.local-setup/reminder-http-fixture.json',json_encode($fixture,JSON_THROW_ON_ERROR));echo "Reminder HTTP fixture ready\n";
