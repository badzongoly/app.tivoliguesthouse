<?php

use Tivoli\Repository\AccessRepository;

return static function (AccessRepository $repo): void {
    if (!$repo->run('SELECT 1 FROM stay_schema WHERE version=2')->fetchColumn()
        || !$repo->run('SELECT 1 FROM finance_schema WHERE id=1')->fetchColumn()) {
        throw new RuntimeException('Install stays and finance before enabling departed receipt recovery.');
    }
    if (!$repo->run("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE()
        AND table_name='payments' AND column_name='recovery_event_id'")->fetchColumn()) {
        $repo->db->exec('ALTER TABLE payments ADD recovery_event_id BIGINT NULL,
            ADD UNIQUE KEY payment_recovery_once (recovery_event_id),
            ADD CONSTRAINT payment_recovery_event FOREIGN KEY (recovery_event_id) REFERENCES finance_events(id)');
    }
    $indexes=$repo->run("SELECT COLUMN_NAME,NON_UNIQUE,SEQ_IN_INDEX FROM information_schema.statistics
        WHERE table_schema=DATABASE() AND table_name='payments' AND index_name='payment_recovery_once'")->fetchAll();
    $foreignKeys=$repo->run("SELECT COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
        FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name='payments'
        AND constraint_name='payment_recovery_event'")->fetchAll();
    $database=$repo->run('SELECT DATABASE()')->fetchColumn();
    $foreignKey=$foreignKeys[0] ?? [];
    if(count($indexes)!==1 || $indexes[0]['COLUMN_NAME']!=='recovery_event_id' || (int)$indexes[0]['NON_UNIQUE']!==0
        || count($foreignKeys)!==1 || $foreignKey['COLUMN_NAME']!=='recovery_event_id' || $foreignKey['REFERENCED_TABLE_SCHEMA']!==$database
        || $foreignKey['REFERENCED_TABLE_NAME']!=='finance_events' || $foreignKey['REFERENCED_COLUMN_NAME']!=='id'){
        throw new RuntimeException('Recovery link constraints do not match the migration.');
    }
    $body=<<<'SQL'
            BEGIN
                IF NEW.recovery_event_id IS NULL THEN
                    IF NOT EXISTS (SELECT 1 FROM bookings WHERE id=NEW.booking_id AND status='active') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ordinary payments require an active stay';
                    END IF;
                ELSEIF NOT EXISTS (
                    SELECT 1 FROM finance_events e
                    JOIN bookings b ON b.id=NEW.booking_id AND b.status='checked_out'
                    JOIN stay_departures d ON d.booking_id=b.id
                    WHERE e.id=NEW.recovery_event_id AND e.kind='departed_receipt_authorized'
                        AND e.target_type='booking'
                        AND e.target_id=CAST(NEW.booking_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                        AND e.amount_minor=NEW.amount*100
                        AND e.created_at=NEW.created_at AND e.effective_date=DATE(NEW.created_at)
                        AND JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.method'))=NEW.method
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Departed receipts require matching recovery authorization';
                END IF;
            END
            SQL;
    $findGuard=static fn()=>$repo->run("SELECT EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT
        FROM information_schema.triggers WHERE trigger_schema=DATABASE()
        AND trigger_name='stay_payment_recovery_guard'")->fetch();
    $guard=$findGuard();
    if(!$guard){
        $repo->db->exec('CREATE TRIGGER stay_payment_recovery_guard BEFORE INSERT ON payments FOR EACH ROW '.$body);
        $guard=$findGuard();
    }
    $normalize=static fn(string $sql)=>preg_replace('/\s+/',' ',trim($sql));
    if(!$guard || $guard['EVENT_OBJECT_TABLE']!=='payments' || $guard['ACTION_TIMING']!=='BEFORE'
        || $guard['EVENT_MANIPULATION']!=='INSERT' || $normalize($guard['ACTION_STATEMENT'])!==$normalize($body)){
        throw new RuntimeException('Replacement payment guard does not match the migration.');
    }
    // The existing trigger remains until the replacement is installed and checked.
    $repo->db->exec('DROP TRIGGER IF EXISTS stay_payment_active');
    $repo->run('INSERT IGNORE INTO stay_schema(version) VALUES(3)');
};
