<?php
define('RECONCILIATION_HELPERS_ONLY',true);require __DIR__.'/receipt_reconciliation.php';
// Append recovery only to the exact isolated fixture period after a failed test.
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('period_reopened','period','month:2043-05-01',0,'2043-05-15',?,?,'2043-05-15 16:00:00')",[$owner,json_encode(['reason'=>'Synthetic reconciliation fixture recovery after interrupted test'])]);echo "Synthetic fixture period reopened; no retained event removed.\n";
