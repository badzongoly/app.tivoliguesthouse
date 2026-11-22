<?php
// Shared installer called only after the CLI target guards succeed.
function stay_install_schema(\Tivoli\Repository\AccessRepository $r): void
{
foreach (array_merge(require __DIR__.'/migrations/007_stays.php',require __DIR__.'/migrations/008_stay_refunds.php') as $sql) { $r->db->exec($sql); }
if(!$r->run("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='stay_departures' AND column_name='refund_cursor'")->fetchColumn())$r->db->exec('ALTER TABLE stay_departures ADD refund_cursor BIGINT NOT NULL DEFAULT 0');
// Only append an approved payment-method value; never rewrite historical payment rows.
$type=(string)$r->run("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payments' AND column_name='method'")->fetchColumn();
if ($type==="enum('cash','momo','card')") { $r->db->exec("ALTER TABLE payments MODIFY method ENUM('cash','momo','card','bank') NOT NULL"); }
elseif ($type!=="enum('cash','momo','card','bank')") { throw new RuntimeException('Unexpected payment-method definition; no conversion attempted.'); }
// Reject deletion/rewrite of posted financial evidence. Triggers are additive, never retroactive.
$triggers=[];
foreach (['payments','booking_extras','stock_movements','stay_operations','stay_receipts','stay_checkout_approvals','stay_departures','stay_identity','stay_room_audits','stay_refunds'] as $table) {
    foreach (['UPDATE','DELETE'] as $operation) {
        $name='stay_keep_'.$table.'_'.strtolower($operation);
        $triggers[$name]="CREATE TRIGGER `$name` BEFORE $operation ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Financial and audit evidence is retained; use an authorized compensating workflow'";
    }
}
foreach (['bookings','rooms','extras','stay_details','stay_incidentals','stay_faults'] as $table) {
    $name='stay_keep_'.$table.'_delete';$triggers[$name]="CREATE TRIGGER `$name` BEFORE DELETE ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical identity and guest evidence cannot be deleted'";
}
$triggers['stay_booking_terminal']="CREATE TRIGGER stay_booking_terminal BEFORE UPDATE ON bookings FOR EACH ROW BEGIN IF OLD.status<>'active' OR NEW.room_id<>OLD.room_id OR NOT(NEW.rate_per_night<=>OLD.rate_per_night) OR NEW.checkin_at<>OLD.checkin_at THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical stays and captured rate/time are protected'; END IF; END";
$triggers['stay_payment_active']="CREATE TRIGGER stay_payment_active BEFORE INSERT ON payments FOR EACH ROW BEGIN IF NOT EXISTS(SELECT 1 FROM bookings WHERE id=NEW.booking_id AND status='active') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payments cannot be added after departure'; END IF; END";
if ($r->run('SELECT 1 FROM stay_schema WHERE version=3')->fetchColumn()
    && $r->run("SELECT 1 FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name='stay_payment_recovery_guard'")->fetchColumn()) {
    unset($triggers['stay_payment_active']);
}
foreach ($triggers as $name=>$sql) { if (!$r->run('SELECT 1 FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?',[$name])->fetchColumn()) { $r->db->exec($sql); } }
$r->run('INSERT IGNORE INTO stay_schema(version) VALUES(2)');
}
