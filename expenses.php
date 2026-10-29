<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/db_helpers.php';

try {
  require_db_table($mysqli, 'expense_categories');
  require_db_table($mysqli, 'expenses');
} catch (Throwable $e) {
  http_response_code(500);
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database setup required</title>';
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
  echo '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger"><h3 class="mb-3">Database setup required</h3><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p>This page cannot run until the MySQL schema has been imported and the app user has the required table permissions.</p></div></div></body></html>';
  exit;
}

// ----------------------
// Handle create/update expense
// ----------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save_expense') {
  $id = (int)($_POST['id'] ?? 0);
  $expense_date = $_POST['expense_date'] ?: date('Y-m-d');
  $category_id = (int)($_POST['category_id'] ?? 0);
  $method = $_POST['method'] ?? 'cash';
  $amount = (float)($_POST['amount'] ?? 0);
  $vendor = trim($_POST['vendor'] ?? '');
  $reference_no = trim($_POST['reference_no'] ?? '');
  $description = trim($_POST['description'] ?? '');
  if ($category_id>0 && $amount>0) {
    if ($id>0) {
      $st = $mysqli->prepare('UPDATE expenses SET expense_date=?, category_id=?, method=?, amount=?, vendor=?, reference_no=?, description=? WHERE id=?');
      $st->bind_param('sisdsssi', $expense_date,$category_id,$method,$amount,$vendor,$reference_no,$description,$id);
      $st->execute();
    } else {
      $st = $mysqli->prepare('INSERT INTO expenses(expense_date,category_id,method,amount,vendor,reference_no,description,user_id) VALUES(?,?,?,?,?,?,?,?)');
      $uid = (int)($user['id'] ?? 0);
      $st->bind_param('sisdsssi', $expense_date,$category_id,$method,$amount,$vendor,$reference_no,$description,$uid);
      $st->execute();
    }
  }
  header('Location: expenses.php'); exit;
}

// ----------------------
// Handle add category (inline)
// ----------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add_category') {
  $name = trim($_POST['cat_name'] ?? '');
  if ($name!=='') {
    $st = $mysqli->prepare('INSERT INTO expense_categories(name,active) VALUES(?,1)');
    $st->bind_param('s',$name); $st->execute();
  }
  header('Location: expenses.php'); exit;
}

// ----------------------
// Delete expense
// ----------------------
if (isset($_GET['delete'])) {
  $rid = (int)$_GET['delete'];
  $mysqli->query('DELETE FROM expenses WHERE id='.$rid);
  header('Location: expenses.php'); exit;
}

// ----------------------
// Filters
// ----------------------
$filter_start  = trim($_GET['start'] ?? date('Y-m-01'));
$filter_end    = trim($_GET['end'] ?? date('Y-m-t'));
$filter_cat    = (int)($_GET['category_id'] ?? 0);
$filter_method = $_GET['method'] ?? 'all';
$q             = trim($_GET['q'] ?? '');

$start_dt = date('Y-m-d 00:00:00', strtotime($filter_start));
$end_dt   = date('Y-m-d 23:59:59', strtotime($filter_end));

// ----------------------
// Fetch categories for selects
// ----------------------
$cats = $mysqli->query('SELECT id,name FROM expense_categories WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);

// ----------------------
// Query expenses with dynamic filters
// ----------------------
$sql = "SELECT e.*, c.name AS category_name, u.name AS user_name
        FROM expenses e
        JOIN expense_categories c ON c.id=e.category_id
        LEFT JOIN users u ON u.id=e.user_id
        WHERE e.expense_date BETWEEN ? AND ?";
$types = 'ss'; $params = [$filter_start, $filter_end];
if ($filter_cat>0)   { $sql .= ' AND e.category_id=?'; $types.='i'; $params[]=$filter_cat; }
if (in_array($filter_method,['cash','momo','card','bank','other'], true)) { $sql .= ' AND e.method=?'; $types.='s'; $params[]=$filter_method; }
if ($q!=='') { $like='%'.$q.'%'; $sql .= ' AND (e.vendor LIKE ? OR e.reference_no LIKE ? OR e.description LIKE ?)'; $types.='sss'; $params[]=$like; $params[]=$like; $params[]=$like; }
$sql .= ' ORDER BY e.expense_date DESC, e.id DESC LIMIT 300';
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$params); $st->execute();
$expenses = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Totals
$total_amount = 0.0; foreach ($expenses as $row) { $total_amount += (float)$row['amount']; }

// CSV export
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="expenses_export.csv"');
  $out=fopen('php://output','w');
  fputcsv($out, ['Date','Category','Method','Amount','Vendor','Reference','Description','Recorded By']);
  foreach ($expenses as $e) {
    fputcsv($out, [ $e['expense_date'], $e['category_name'], strtoupper($e['method']), number_format((float)$e['amount'],2,'.',''), $e['vendor'], $e['reference_no'], $e['description'], $e['user_name'] ]);
  }
  fclose($out); exit;
}

// Editing state
$edit = null; if (isset($_GET['edit'])) { $id=(int)$_GET['edit']; $edit=$mysqli->query('SELECT * FROM expenses WHERE id='.$id)->fetch_assoc(); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Expenses</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{
      --bg-page:#f7f8fb;          /* soft light background */
      --bg-surface:#ffffff;       /* card/nav surfaces */
      --line:#e5e7eb;             /* gentle borders */
      --text:#0f172a;             /* slate-900 */
      --muted:#475569;            /* slate-600 */
      --sidebar-w:260px;
    }
    body { background:var(--bg-page); color:var(--text); }

    /* Sidebar: desktop vs. mobile off-canvas */
    .sidebar { width:var(--sidebar-w); min-height:100vh; background:#f8fafc; position:fixed; left:0; top:0; padding:1rem; border-right:1px solid var(--line); color:var(--text); transition:transform .25s ease; will-change:transform; z-index:1040; }
    .content { margin-left:var(--sidebar-w); transition:margin-left .25s ease; }
    @media (max-width: 991.98px){
      .sidebar { transform:translateX(-100%); box-shadow: 0 8px 30px rgba(0,0,0,.08); }
      body.sidebar-open .sidebar { transform:translateX(0); }
      .content { margin-left:0; }
      body.sidebar-open::before{ content:""; position:fixed; inset:0; background:rgba(15,23,42,.25); z-index:1030; }
    }

    .navtop { background:#ffffff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:1000; }
    .navtop.navbar {
      --bs-navbar-color:#334155;                 /* link text */
      --bs-navbar-hover-color:#0f172a;           /* hover text */
      --bs-navbar-brand-color:#0f172a;           /* brand */
      --bs-navbar-brand-hover-color:#0f172a;     /* brand hover */
    }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    a { text-decoration:none; }

    /* Sidebar links readable on light theme */
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }

    /* Outline light buttons: softer contrast */
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }

    /* Mobile hamburger */
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }

    /* Room cards layout responsiveness */
    .room-card .badge{ text-transform:capitalize; }
    .room-card .btn{ min-width:0; }

    /* Filter form: tighter spacing on small screens */
    .filter-form .form-label{ font-size:.9rem; }

    /* Table responsiveness: keep scroll, hide less critical columns on xs */
    @media (max-width: 575.98px){
      .tbl-mobile-hide{ display:none; }
      .table thead th{ white-space:nowrap; }
    }
  </style>
</head>
<body>
  <?php include __DIR__.'/partials/sidebar.php'; ?>
    <!-- Mobile sidebar toggle -->
  <button class="btn btn-outline-primary sidebar-toggle" id="btnSidebar" aria-label="Toggle navigation">☰</button>
  <div class="content">
    <?php include __DIR__.'/partials/navtop.php'; ?>
    <main class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 class="m-0">Expenses</h3>
          <div class="text-secondary">Track operating expenses with categories and payment methods</div>
        </div>
        <a class="btn btn-outline-light" href="reports.php">Go to Reports</a>
      </div>

      <!-- Filter Bar & CSV -->
      <form method="get" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Start date</label>
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($filter_start); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">End date</label>
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($filter_end); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Category</label>
            <select class="form-select" name="category_id">
              <option value="0">All categories</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo ($filter_cat==(int)$c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Method</label>
            <select class="form-select" name="method">
              <?php $methods = ['all'=>'All','cash'=>'Cash','momo'=>'MoMo','card'=>'Card','bank'=>'Bank','other'=>'Other']; foreach($methods as $k=>$v): ?>
                <option value="<?php echo $k; ?>" <?php echo ($filter_method===$k)?'selected':''; ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label">Search</label>
            <input class="form-control" name="q" placeholder="Vendor/ref" value="<?php echo htmlspecialchars($q); ?>">
          </div>
          <div class="col-md-12 d-flex justify-content-end gap-2">
            <button class="btn btn-primary">Apply</button>
            <a class="btn btn-outline-light" href="?<?php $qs=$_GET; unset($qs['export']); echo http_build_query($qs); ?>&export=csv">Export CSV</a>
          </div>
        </div>
      </form>

      <!-- Summary & New Category -->
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary">Total in period</div>
                <div class="h4 mb-0">₵<?php echo number_format($total_amount,2); ?></div>
              </div>
              <div class="text-secondary">Records: <?php echo count($expenses); ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card p-3 h-100">
            <div class="text-secondary mb-2">Quick Add Category</div>
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="action" value="add_category">
              <input class="form-control" name="cat_name" placeholder="e.g., Fuel" required>
              <button class="btn btn-outline-light">Add</button>
            </form>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Create / Edit Expense -->
        <div class="col-lg-5">
          <div class="card p-3">
            <h5 class="mb-3"><?php echo $edit?'Edit Expense':'Add Expense'; ?></h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="save_expense">
              <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
              <div class="col-6">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="expense_date" value="<?php echo htmlspecialchars($edit['expense_date'] ?? date('Y-m-d')); ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" required>
                  <option value="">Select…</option>
                  <?php foreach ($cats as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (($edit['category_id'] ?? 0)==(int)$c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Method</label>
                <select class="form-select" name="method" required>
                  <?php foreach(['cash','momo','card','bank','other'] as $m): ?>
                    <option <?php echo (($edit['method'] ?? '')===$m)?'selected':''; ?>><?php echo $m; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Amount (₵)</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" value="<?php echo htmlspecialchars($edit['amount'] ?? ''); ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label">Vendor</label>
                <input class="form-control" name="vendor" value="<?php echo htmlspecialchars($edit['vendor'] ?? ''); ?>">
              </div>
              <div class="col-6">
                <label class="form-label">Reference #</label>
                <input class="form-control" name="reference_no" value="<?php echo htmlspecialchars($edit['reference_no'] ?? ''); ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <input class="form-control" name="description" value="<?php echo htmlspecialchars($edit['description'] ?? ''); ?>">
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary"><?php echo $edit?'Save Changes':'Add Expense'; ?></button>
              </div>
            </form>
          </div>
        </div>

        <!-- List -->
        <div class="col-lg-7">
          <div class="card p-3">
            <h5 class="mb-3">Expenses</h5>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($expenses as $e): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($e['expense_date']); ?></td>
                      <td><?php echo htmlspecialchars($e['category_name']); ?></td>
                      <td class="text-end">₵<?php echo number_format($e['amount'],2); ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-light" href="?edit=<?php echo (int)$e['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int)$e['id']; ?>" onclick="return confirm('Delete this expense?');">Delete</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </main>
    <?php include __DIR__.'/partials/footer.php'; ?>
  </div>
</body>
</html>
