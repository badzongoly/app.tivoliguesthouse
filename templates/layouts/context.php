<?php
// Shared presentation context only. Endpoint guards remain authoritative.
function workspace_context(array $actor): array {
    $page=basename($_SERVER['SCRIPT_NAME']);
    $pages=[
        'occupancy.php'=>[match($_GET['section'] ?? ''){'extras'=>'Extras & Stock','rooms','areas'=>'Property & Audits',default=>'Check-In & Occupancy'},'Guest details, bills and room readiness.'],'guest-id.php'=>['Guest identity','Private guest document.'],
        'handover.php'=>['Shift Handover','Open work, cash custody and recorded acknowledgements.'],
        'staff.php'=>['Staff & HR','Your people and their employment records.'],
        'cleaning-settings.php'=>['Settings','Configure future checks and lists; retain completed records.'],
        'hr-settings.php'=>['Settings','HR lists and payment providers.'],
        'hr-document.php'=>['Staff documents','Private HR attachments.'],
        'dashboard.php'=>['Today','Your guesthouse at a glance.'],
        'bookings.php'=>['Bookings & stays','Welcome guests and manage their stays.'],
        'checkin.php'=>['Check in','Prepare a warm welcome.'],
        'checkout.php'=>['Check out','Review the stay and complete departure.'],
        'rooms.php'=>['Rooms','Room details and availability.'],
        'extras.php'=>['Extras catalogue','Products and services for your guests.'],
        'extra_sales.php'=>['Extras & Stock','Review sales of products and services.'],
        'stock.php'=>['Stock','Keep supplies ready for the next stay.'],
        'reminders.php'=>['Reminders & Delivery','Your source-linked work and delivery preferences.'],
        'owner-exceptions.php'=>['Owner Exceptions','Linked open work for the designated owner.'],
        'weekly-audits.php'=>['Weekly Audits & Follow-Ups','Original findings, named responsibility and verified completion.'],
        'housekeeping.php'=>['Check-In & Occupancy','Named cleaning, independent inspection and recorded exceptions.'],
        'stock-issues.php'=>['Stock Non-Sale Issues','Reports, owner decisions and actual stock disposition.'],
        'stock-counts.php'=>['Stock Counts & Variance Review','Physical counts, owner decisions and retained history.'],
        'purchases.php'=>['Purchase & Expense Requests','Approval, delivery, cost and settlement.'],
        'payroll.php'=>['Payroll & Staff Payments','Private payroll and published employee statements.'],
        'financial-corrections.php'=>['Finance & Cash','Financial Corrections'],
        'finance.php'=>['Finance & Cash Control','Receipts, deposits, costs and management reviews.'],
        'payments.php'=>['Payments','Guest payments and booking balances.'],
        'expenses.php'=>['Finance & Cash','Record spending and follow approvals.'],
        'reports.php'=>['Reports','Review guesthouse performance.'],
        'owner_reports.php'=>['Dashboard','Visual reports for rooms, receipts, extras and expenses.'],
        'users.php'=>['System Access','Individual accounts, roles and privileges.'],
        'audit.php'=>['Audit Trail','Who did what, and when. All times are UTC.'],
        'admin_recovery.php'=>['Historical Recovery','Restricted historical recording workspace.'],
        'change-password.php'=>['Change password','Keep your staff account secure.'],
        'logout.php'=>['Sign out','Finish your session on this device.'],
        'forgot-password.php'=>['Password recovery','Request a password-reset link.'],
        'reset-password.php'=>['Reset password','Choose a unique staff password.'],
    ];
    $locked=!empty($actor['must_change_password']);
    $permissions=[];$roles=[];$pending=null;
    try {
        $repo=access_service()->repo;
        $roles=$repo->run('SELECT r.name FROM security_user_roles ur JOIN security_roles r ON r.id=ur.role_id WHERE ur.user_id=? ORDER BY r.name',[(int)$actor['id']])->fetchAll(PDO::FETCH_COLUMN);
        if(!$locked) {
            $permissions=$repo->privileges((int)$actor['id']);
            if(array_intersect(['expenses.manage','expenses.submit','expenses.approve'],$permissions)) {
                $review=in_array('expenses.approve',$permissions,true) && $repo->isAdmin((int)$actor['id']);
                $pending=(int)$repo->run("SELECT COUNT(*) FROM expense_submissions WHERE status='pending'".($review?'':' AND submitted_by=?'),$review?[]:[(int)$actor['id']])->fetchColumn();
            }
        }
    } catch(Throwable $unavailable) { /* A badge outage must not break a usable page. */ }
    return ['actor'=>$actor,'page'=>$page,'title'=>$pages[$page][0] ?? 'Workspace','subtitle'=>$pages[$page][1] ?? '', 'permissions'=>$permissions,'roles'=>$roles,'pending'=>$pending,'locked'=>$locked];
}
function workspace_icon(string $name): string {
    $paths=[
      'today'=>'M3 10 12 3l9 7v11h-6v-7H9v7H3Z',
      'stays'=>'M3 21V5h18v16M7 9h3m4 0h3M7 13h3m4 0h3M10 21v-4h4v4',
      'rooms'=>'M3 21h18M6 21V3h12v18M14 12h1',
      'extras'=>'M3 8h18v13H3ZM2 3h20v5H2m10 0v13',
      'money'=>'M3 5h18v14H3ZM8 5c0 3-2 5-5 5m18 4c-3 0-5 2-5 5m-4-9v4',
      'report'=>'M4 3v18h17M8 17v-5m5 5V8m5 9V4',
      'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0m4-4a4 4 0 0 1 0 8',
      'audit'=>'M6 3h12v18H6ZM9 7h6m-6 5h6m-6 5h4',
      'menu'=>'M4 6h16M4 12h16M4 18h16',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name] ?? $paths['audit']).'"/></svg>';
}

// Content versions prevent cached page scripts from retaining retired handlers.
function workspace_asset_url(string $path): string {
    if (!preg_match('~\Aassets/(?:js|css)/[a-z0-9-]+\.(?:js|css)\z~D', $path)) {
        throw new InvalidArgumentException('Unsupported workspace asset.');
    }
    $file=dirname(__DIR__,2).'/public/'.$path;
    if (!is_file($file)) throw new RuntimeException('Workspace asset is unavailable.');
    return access_url($path).'?v='.substr(hash_file('sha256',$file),0,16);
}
