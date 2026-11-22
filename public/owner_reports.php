<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';

use Tivoli\Repository\OwnerReports;

if (getenv('TIVOLI_OWNER_REPORTS') === '0') {
    http_response_code(404);
    exit('Owner Reports is disabled.');
}
if (!access_service()->repo->isAdmin((int)$user['id'])) {
    http_response_code(403);
    exit('Owner Reports is available to administrators only.');
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Owner Reports is read-only.');
}

$layoutTitle = 'Owner Reports';
$layoutPage = 'owner-reports';
$asOf = new DateTimeImmutable('now',new DateTimeZone('Africa/Accra'));
$view = is_string($_GET['view'] ?? null) && isset(OwnerReports::VIEWS[$_GET['view']]) ? $_GET['view'] : 'daily';
$report = new OwnerReports(access_service()->repo);
$error = '';
$data = [];$departureIncidentCount=0;$handoverAttention=0;$purchaseAttention=0;$stockCountAttention=0;$housekeepingAttention=0;$payrollAttention=0;$correctionAttention=0;$expenseCorrectionAttention=0;$guestChargeAttention=0;$purchaseCorrectionAttention=0;
$filters = OwnerReports::filters(['view'=>$view],$asOf);
try {
    $query = $_GET;
    if ($view === 'monthly' && isset($query['month'])) {
        if (!is_string($query['month']) || !preg_match('/^\d{4}-\d{2}$/D',$query['month'])) {
            throw new InvalidArgumentException('Choose a valid month.');
        }
        $query['end'] = $query['month'].'-01';
    }
    if (isset($query['export'])) throw new InvalidArgumentException('Owner Reports displays detail in the app.');
    $filters = OwnerReports::filters($query,$asOf);
    $data = $report->read($filters);
    $expenseCorrectionService=new \Tivoli\Service\ExpenseCorrectionService(access_service());if((new \Tivoli\Service\FinanceService(access_service()))->installed()&&$expenseCorrectionService->owner((int)$user['id']))$expenseCorrectionAttention=$expenseCorrectionService->attentionCount((int)$user['id']);
    if((new \Tivoli\Service\FinanceService(access_service()))->canPostLate((int)$user['id'])&&in_array('hr.salary',access_service()->repo->privileges((int)$user['id']),true))$payrollAttention=(new \Tivoli\Service\PayrollService(access_service()))->attentionCount((int)$user['id']);
    if((new \Tivoli\Service\FinanceService(access_service()))->canPostLate((int)$user['id'])
        &&in_array('bookings.manage',access_service()->repo->privileges((int)$user['id']),true)){
        $cases=(new \Tivoli\Service\StayService(access_service()))->departureCases((int)$user['id']);
        $departureIncidentCount=count(array_filter($cases,fn($row)=>!$row['resolved']));
        $handoverCases=(new \Tivoli\Service\HandoverService(access_service()))->attention((int)$user['id']);$handoverAttention=$handoverCases['pending']+$handoverCases['exceptions']+$handoverCases['cash'];
        $purchaseAttention=(new \Tivoli\Service\PurchaseService(access_service()))->attention((int)$user['id'])['count'];
        $stockCountAttention=(new \Tivoli\Service\StockCountService(access_service()))->attention((int)$user['id'])['count'];
        $housekeepingAttention=(new \Tivoli\Service\HousekeepingService(access_service()))->attention((int)$user['id'])['count'];
        $purchaseCorrectionAttention=(new \Tivoli\Service\PurchaseCorrectionService(access_service()))->attentionCount((int)$user['id']);
        $guestChargeAttention=(new \Tivoli\Service\GuestChargeCorrectionService(access_service()))->attentionCount((int)$user['id']);
        $correctionAttention=(new \Tivoli\Service\ReceiptCorrectionService(access_service()))->attentionCount((int)$user['id']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('Owner Reports query failed: '.get_class($e).' '.$e->getCode());
    http_response_code(503);
    $error = 'The report could not be loaded. Try again in a moment.';
}
require dirname(__DIR__).'/templates/owner-reports.php';
