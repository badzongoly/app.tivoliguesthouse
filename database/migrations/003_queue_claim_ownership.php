<?php
return static function(PDO $db): void {
    $query=$db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='security_mail_queue' AND column_name='claim_token'");$query->execute();
    if(!(int)$query->fetchColumn()){$db->exec('ALTER TABLE security_mail_queue ADD claim_token CHAR(32) NULL');}
};