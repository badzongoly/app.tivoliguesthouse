<?php
require_once dirname(__DIR__,2).'/bootstrap/security.php';
try {
    access_session(); $user=access_current();
    if (!$user) { header('Location: auth/login.php'); exit; }
    if ($user['must_change_password']) { header('Location: '.access_url('auth/change-password.php')); exit; }
    $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($endpoint === 'dashboard.php' && ($home = access_home_path($user)) !== 'dashboard.php') {
        header('Location: '.access_url($home));
        exit;
    }
    $privilege=match($endpoint) {
        'occupancy.php','guest-id.php','handover.php'=>'bookings.manage','dashboard.php'=>'dashboard.view','bookings.php','checkin.php','checkout.php'=>'bookings.manage',
        'rooms.php','fault-attachment.php'=>'rooms.manage','extras.php'=>'extras.manage','stock.php'=>'stock.manage',
        'extra_sales.php'=>'sales.view','reports.php','owner_reports.php'=>'reports.view','payments.php'=>'payments.view',
        'reminders.php','owner-exceptions.php','financial-corrections.php','payroll.php','weekly-audits.php','housekeeping.php','stock-counts.php','stock-issues.php','purchases.php','finance.php','finance-evidence.php'=>null,'expenses.php'=>null,'admin_recovery.php'=>'recovery.manage',
        'audit.php'=>'audit.view','users.php'=>null,'staff.php'=>null,'hr-settings.php','cleaning-settings.php'=>'hr.settings','hr-document.php'=>'hr.documents',
        default=>throw new RuntimeException('Unknown protected endpoint.')
    };
    if ($privilege) { access_service()->requirePrivilege((int)$user['id'],$privilege); }
    elseif (in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''),['finance.php','finance-evidence.php'],true)) {
        if($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='correction')(new \Tivoli\Service\ReceiptCorrectionService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='payroll')(new \Tivoli\Service\PayrollService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='expensecorrection')(new \Tivoli\Service\ExpenseCorrectionService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='housekeeping')(new \Tivoli\Service\HousekeepingService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='stockissue')(new \Tivoli\Service\StockIssueService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='stockcount')(new \Tivoli\Service\StockCountService(access_service()))->permit((int)$user['id']);
        elseif($endpoint==='finance-evidence.php'&&($_GET['kind']??'')==='purchase')(new \Tivoli\Service\PurchaseService(access_service()))->permit((int)$user['id']);
        else (new \Tivoli\Service\FinanceService(access_service()))->permit((int)$user['id']);
    }
    elseif ($endpoint==='financial-corrections.php') { if(is_string($_GET['area']??null)&&$_GET['area']==='expenses')(new \Tivoli\Service\ExpenseCorrectionService(access_service()))->permit((int)$user['id']);else (new \Tivoli\Service\ReceiptCorrectionService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='reminders.php') { (new \Tivoli\Service\ReminderService(access_service(),null,access_config()))->permit((int)$user['id']); }
    elseif ($endpoint==='owner-exceptions.php') { (new \Tivoli\Service\OwnerExceptionService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='payroll.php') { (new \Tivoli\Service\PayrollService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='weekly-audits.php') { (new \Tivoli\Service\WeeklyAuditService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='housekeeping.php') { (new \Tivoli\Service\HousekeepingService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='stock-issues.php') { (new \Tivoli\Service\StockIssueService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='stock-counts.php') { (new \Tivoli\Service\StockCountService(access_service()))->permit((int)$user['id']); }
    elseif ($endpoint==='purchases.php') { (new \Tivoli\Service\PurchaseService(access_service()))->permit((int)$user['id']); }
    elseif (basename($_SERVER['SCRIPT_NAME'] ?? '')==='expenses.php') {
        if(!array_intersect(['expenses.submit','expenses.manage','expenses.approve'],access_service()->repo->privileges((int)$user['id']))){access_service()->audit((int)$user['id'],'access.denied','page','expenses','denied');throw new RuntimeException('You do not have permission to access expenses.');}
    }
    elseif (basename($_SERVER['SCRIPT_NAME'] ?? '')==='staff.php') {
        if(!array_intersect(['hr.view','hr.self'],access_service()->repo->privileges((int)$user['id'])))throw new \Tivoli\Security\PermissionDenied('You cannot access staff records.');
    }
    elseif (!array_intersect(['users.manage','roles.manage','roles.assign','privileges.manage'],access_service()->repo->privileges((int)$user['id']))) {
        access_service()->audit((int)$user['id'],'access.denied','page','users','denied'); throw new RuntimeException('You do not have permission to manage access.');
    }

    // Legacy routes may serve reads during setup. A missing marker must never enable old writers.
    $stayPage=$endpoint;
    if(in_array($stayPage,['bookings.php','checkin.php','checkout.php','rooms.php','extras.php','stock.php','admin_recovery.php'],true)){
        $legacyDelete=in_array($stayPage,['rooms.php','extras.php'],true) && isset($_GET['delete']);
        if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET' || $legacyDelete){
            access_service()->audit((int)$user['id'],'stay.legacy_write_denied','page',$stayPage,'denied');
            http_response_code(409);exit('This older form is no longer active. Open Check-in & occupancy and review the current record. Historical recovery writes require a separately reviewed correction workflow.');
        }
        $repo=access_service()->repo;
        $installed=$repo->run("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='stay_schema'")->fetchColumn()
            && $repo->run('SELECT version FROM stay_schema WHERE version=2')->fetchColumn();
        if($installed && $stayPage!=='admin_recovery.php'){
            $target=match($stayPage){'extras.php'=>'occupancy.php?section=extras','stock.php'=>'occupancy.php?section=extras&tab=stock','rooms.php'=>'occupancy.php?section=rooms',default=>'occupancy.php'};
            if($stayPage==='bookings.php' && isset($_GET['export'])){$target='occupancy.php?'.http_build_query(array_intersect_key($_GET,array_flip(['export','room_id','status','start','end'])));}
            if(in_array($stayPage,['checkin.php','checkout.php'],true)){
                if(isset($_GET['booking_id']))$target.='?booking_id='.(int)$_GET['booking_id'];
                elseif(isset($_GET['room_id']))$target.='?form=checkin&room_id='.(int)$_GET['room_id'];
            }
            header('Location: '.access_url($target));exit;
        }
    }
} catch (RuntimeException $error) {
    http_response_code($error instanceof PDOException ? 503 : 403);
    exit($error instanceof PDOException ? 'Authentication is temporarily unavailable.' : access_h($error->getMessage()));
} catch (Throwable $error) { http_response_code(503); exit('Authentication is temporarily unavailable.'); }