<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
$expenseService=new \Tivoli\Service\ExpenseService(access_service());
$actor=(int)$user['id'];$canManage=$expenseService->canManage($actor);$canReview=$expenseService->canReview($actor);$canSubmit=$expenseService->canSubmit($actor);
$error='';$notice='';$categories=[];$submissions=[];$approved=['rows'=>[],'total'=>'0.00','count'=>0];
$filters=['start'=>gmdate('Y-m-01'),'end'=>gmdate('Y-m-t'),'category_id'=>0,'method'=>'all','q'=>''];$status=$canReview?'pending':'';$before=0;
try {
    if (isset($_GET['delete']) || isset($_GET['edit'])) {
        access_service()->audit($actor,'expense.legacy_mutation_denied','expense',null,'denied');
        http_response_code(405);throw new RuntimeException('Posted expenses are retained and cannot be edited or deleted. Corrections need a separate traceable workflow.');
    }
    $categories=$expenseService->repo->categories();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        access_check_csrf();
        foreach($_POST as $value){if(!is_string($value)){throw new RuntimeException('Invalid form input.');}}
        $action=$_POST['action'] ?? '';
        if ($action==='save_expense') {
            access_service()->audit($actor,'purchase.obsolete_expense_form','expense',null,'denied');http_response_code(409);throw new RuntimeException('New costs use Purchase Requests. Report already-incurred unapproved costs there as explicit exceptions.');
        } elseif ($action==='review_expense') {
            $id=filter_var($_POST['submission_id'] ?? '',FILTER_VALIDATE_INT);if($id===false || $id<1){throw new RuntimeException('Invalid submission.');}
            $result=$expenseService->review($actor,$id,$_POST['decision'] ?? '',$_POST['reason'] ?? '',$_POST);$message=$result['status'];
        } elseif ($action==='add_category') {
            $expenseService->addCategory($actor,$_POST['cat_name'] ?? '');$message='category';
        } else { throw new RuntimeException('Unknown expense action.'); }
        $_SESSION['expense_notice']=$message;
        header('Location: expenses.php');exit;
    }
    if(isset($_GET['export']) && !$canManage){http_response_code(403);access_service()->audit($actor,'expense.export_denied','expense',null,'denied');throw new RuntimeException('Only authorized administrators may export the expense ledger.');}
    $filters=\Tivoli\Repository\ExpenseRepository::filters($_GET);
    if(isset($_GET['status'])){if(!is_string($_GET['status'])){throw new RuntimeException('Invalid status filter.');}$status=$_GET['status'];}
    if(isset($_GET['before'])){$before=filter_var($_GET['before'],FILTER_VALIDATE_INT);if($before===false || $before<1){throw new RuntimeException('Invalid page cursor.');}}
    $submissions=$expenseService->repo->submissions($actor,$canManage || $canReview,$status,$before);
    if($canManage){$approved=$expenseService->repo->approved($filters);}
    if(($_GET['export'] ?? '')==='csv') {
        access_service()->audit($actor,'expense.exported','expense',null,'succeeded',['rows'=>count($approved['rows']),'start'=>$filters['start'],'end'=>$filters['end'],'approved_only'=>true,'row_limit'=>300]);
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="expenses_export.csv"');
        $out=fopen('php://output','w');fputcsv($out,['Date','Category','Method','Amount','Vendor','Reference','Description','Recorded By']);
        $safe=static function(mixed $value): string{$text=(string)$value;return preg_match('/^[\s]*[=+\-@]/u',$text)?"'".$text:$text;};
        foreach($approved['rows'] as $row){fputcsv($out,[$row['expense_date'],$safe($row['category_name']),strtoupper($row['method']),$row['amount'],$safe($row['vendor']),$safe($row['reference_no']),$safe($row['description']),$safe($row['user_name'])]);}
        fclose($out);exit;
    }
    access_service()->audit($actor,'expense.viewed','expense',null,'succeeded',['scope'=>($canManage || $canReview)?'administrator':'own_submissions','submission_rows'=>count($submissions),'ledger_rows'=>count($approved['rows'])]);
    $saved=is_string($_SESSION['expense_notice'] ?? null)?$_SESSION['expense_notice']:'';unset($_SESSION['expense_notice']);
    $notice=match($saved){'posted'=>'Expense recorded and included in totals.','submitted'=>'Expense submitted. It does not count until an administrator approves it.','approved'=>'Submission approved. Its expense is counted once.','rejected'=>'Submission rejected. It is retained and excluded from totals.','category'=>'Category added.',default=>''};
} catch(Throwable $exception) {
    $error=access_error($exception);
    $submissions=[];$approved=['rows'=>[],'total'=>'0.00','count'=>0];
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{access_service()->audit($actor,'expense.form_rejected','expense',null,'denied',['reason'=>'validation_permission_or_storage_failure']);}catch(Throwable $auditFailure){$error='Expenses are temporarily unavailable.';http_response_code(503);}
    }
    if($exception instanceof \Tivoli\Security\PermissionDenied){http_response_code(403);}elseif($exception instanceof PDOException){http_response_code(503);}elseif(http_response_code()===200){http_response_code(400);}
}
$operationKey=bin2hex(random_bytes(16));
$formValues=['expense_date'=>gmdate('Y-m-d'),'category_id'=>'','method'=>'momo','amount'=>'','vendor'=>'','reference_no'=>'','description'=>'','original_activity_date'=>'','late_reason'=>''];
if($error && ($_POST['action'] ?? '')==='save_expense' && ($_POST['id'] ?? '0')==='0' && is_string($_POST['operation_key'] ?? null) && preg_match('/^[a-f0-9]{32}$/D',$_POST['operation_key'])){
    // Preserve the operation identity even when commit/audit outcome is uncertain.
    $operationKey=$_POST['operation_key'];
    foreach($formValues as $key=>$value){if(is_string($_POST[$key] ?? null)){$formValues[$key]=$_POST[$key];}}
}
require dirname(__DIR__).'/templates/expenses/index.php';