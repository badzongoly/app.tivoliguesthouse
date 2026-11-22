<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/app.php';
require_once dirname(__DIR__).'/src/Infrastructure/Database/legacy_helpers.php';

try {
  require_db_table($mysqli, 'extras');
  require_db_table($mysqli, 'stock_movements');
} catch (Throwable $e) {
  http_response_code(500);
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database setup required</title>';
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
  echo '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger"><h3 class="mb-3">Database setup required</h3><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p>This page cannot run until the MySQL schema has been imported and the app user has the required table permissions.</p></div></div></body></html>';
  exit;
}

$notice = '';
$error = '';

// ----------------------
// Stock in action
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stock_in') {
  $extra_id   = (int)($_POST['extra_id'] ?? 0);
  $qty        = (float)($_POST['qty'] ?? 0);
  $unit_cost  = ($_POST['unit_cost'] ?? '') === '' ? null : (float)$_POST['unit_cost'];
  $note       = trim($_POST['note'] ?? '');
  $created_by = (int)($user['id'] ?? 0);

  if ($extra_id <= 0) {
    $error = 'Please select an extra.';
  } elseif ($qty <= 0) {
    $error = 'Quantity must be greater than zero.';
  } else {
    $mysqli->begin_transaction();
    try {
      $up = $mysqli->prepare("UPDATE extras SET stock_qty = stock_qty + ? WHERE id = ?");
      $up->bind_param('di', $qty, $extra_id);
      $up->execute();

      $mv = $mysqli->prepare("INSERT INTO stock_movements(extra_id,movement_type,qty,unit_cost,note,ref_type,ref_id,created_by) VALUES(?,?,?,?,?,?,?,?)");
      $movement_type = 'in';
      $ref_type = 'stock_in';
      $ref_id = null;
      $mv->bind_param('isddssii', $extra_id, $movement_type, $qty, $unit_cost, $note, $ref_type, $ref_id, $created_by);
      $mv->execute();

      $mysqli->commit();
      header('Location: stock.php?ok=1');
      exit;
    } catch (Throwable $t) {
      $mysqli->rollback();
      $error = 'Unable to save stock entry.';
    }
  }
}

if (isset($_GET['ok'])) {
  $notice = 'Stock added successfully.';
}

// ----------------------
// Filters + pagination
// ----------------------
$filter_extra = (int)($_GET['extra_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$page_size = 15;
$offset = ($page - 1) * $page_size;

$extras = $mysqli->query("SELECT id, name, price, stock_qty, active FROM extras ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Summary values
$selected_stock = null;
if ($filter_extra > 0) {
  $st = $mysqli->prepare("SELECT id, name, stock_qty FROM extras WHERE id = ? LIMIT 1");
  $st->bind_param('i', $filter_extra);
  $st->execute();
  $selected_stock = $st->get_result()->fetch_assoc();
}
$total_stock_all = (float)($mysqli->query("SELECT COALESCE(SUM(stock_qty),0) s FROM extras")->fetch_assoc()['s'] ?? 0);

// Paginated movements list (filtered to a specific extra if selected)
$countSql = "SELECT COUNT(*) c FROM stock_movements sm WHERE 1=1";
$listSql = "SELECT sm.*, e.name AS extra_name, e.stock_qty
            FROM stock_movements sm
            JOIN extras e ON e.id = sm.extra_id
            WHERE 1=1";
$types = '';
$params = [];
if ($filter_extra > 0) {
  $countSql .= " AND sm.extra_id = ?";
  $listSql  .= " AND sm.extra_id = ?";
  $types .= 'i';
  $params[] = $filter_extra;
}
$listSql .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT ? OFFSET ?";

$countStmt = $mysqli->prepare($countSql);
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total_rows = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $page_size));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $page_size; }

$listStmt = $mysqli->prepare($listSql);
$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $page_size;
$listParams[] = $offset;
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$qs = $_GET;
unset($qs['page']);
$qs_str = http_build_query($qs);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Stock In</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{--bg-page:#f7f8fb;--bg-surface:#ffffff;--line:#e5e7eb;--text:#0f172a;--muted:#475569;--sidebar-w:260px;}
    body { background:var(--bg-page); color:var(--text); }
    .sidebar { width:var(--sidebar-w); min-height:100vh; background:#f8fafc; position:fixed; left:0; top:0; padding:1rem; border-right:1px solid var(--line); color:var(--text); transition:transform .25s ease; will-change:transform; z-index:1040; }
    .content { margin-left:var(--sidebar-w); transition:margin-left .25s ease; }
    @media (max-width: 991.98px){ .sidebar { transform:translateX(-100%); box-shadow:0 8px 30px rgba(0,0,0,.08);} body.sidebar-open .sidebar{transform:translateX(0);} .content{margin-left:0;} body.sidebar-open::before{content:"";position:fixed;inset:0;background:rgba(15,23,42,.25);z-index:1030;} }
    .navtop { background:#ffffff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:1000; }
    .navtop.navbar { --bs-navbar-color:#334155; --bs-navbar-hover-color:#0f172a; --bs-navbar-brand-color:#0f172a; --bs-navbar-brand-hover-color:#0f172a; }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow:0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    .table { --bs-table-bg: transparent; color:var(--text); }
    .table thead th { color:#334155; white-space:nowrap; }
    .sidebar .list-group-item { background:transparent; color:var(--text)!important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }
    @media (max-width: 575.98px){ .tbl-mobile-hide{ display:none; } }
  </style>
<?php require dirname(__DIR__).'/templates/layouts/head.php'; ?>
</head>
<body class="app-page">
  <?php include dirname(__DIR__).'/templates/partials/sidebar.php'; ?>
  
  <div class="content">
    <?php include dirname(__DIR__).'/templates/partials/navtop.php'; ?>
    <main id="workspace-main" tabindex="-1" class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h3 class="m-0">Stock In</h3>
          <div class="text-secondary">Add stock for extras and review remaining quantity</div>
        </div>
        <a class="btn btn-outline-light" href="extras.php">Back to Extras</a>
      </div>

      <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-5">
          <div class="card p-3 h-100">
            <h5 class="mb-3">Add Stock</h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="stock_in">
              <div class="col-12">
                <label class="form-label">Extra</label>
                <select class="form-select" name="extra_id" required>
                  <option value="">Select extra…</option>
                  <?php foreach ($extras as $e): ?>
                    <option value="<?php echo (int)$e['id']; ?>">
                      <?php echo htmlspecialchars($e['name']); ?> (In stock: <?php echo rtrim(rtrim(number_format((float)$e['stock_qty'],2), '0'), '.'); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Quantity In</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="qty" required>
              </div>
              <div class="col-6">
                <label class="form-label">Unit Cost</label>
                <input type="number" step="0.01" min="0" class="form-control" name="unit_cost" placeholder="Optional">
              </div>
              <div class="col-12">
                <label class="form-label">Note</label>
                <input class="form-control" name="note" placeholder="Supplier, batch, invoice ref...">
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary">Save Stock In</button>
              </div>
            </form>
          </div>
        </div>

        <div class="col-12 col-lg-7">
          <div class="row g-3 h-100">
            <div class="col-12 col-md-4">
              <div class="card p-3 h-100">
                <div class="text-secondary">Selected Extra</div>
                <div class="h6 mb-1"><?php echo $selected_stock ? htmlspecialchars($selected_stock['name']) : 'All extras'; ?></div>
                <div class="small text-secondary">Filter target</div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card p-3 h-100">
                <div class="text-secondary">Remaining Stock</div>
                <div class="h4 mb-0"><?php echo $selected_stock ? rtrim(rtrim(number_format((float)$selected_stock['stock_qty'],2), '0'), '.') : rtrim(rtrim(number_format($total_stock_all,2), '0'), '.'); ?></div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="card p-3 h-100">
                <div class="text-secondary">Rows</div>
                <div class="h4 mb-0"><?php echo $total_rows; ?></div>
              </div>
            </div>

            <div class="col-12">
              <div class="card p-3 h-100">
                <form method="get" class="row g-2 align-items-end">
                  <div class="col-12 col-md-8">
                    <label class="form-label">Filter specific extra</label>
                    <select class="form-select" name="extra_id">
                      <option value="0">All extras</option>
                      <?php foreach ($extras as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>" <?php echo $filter_extra === (int)$e['id'] ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-4 d-grid">
                    <button class="btn btn-outline-light">Apply Filter</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h5 class="m-0">Stock Movements</h5>
          <span class="text-secondary small">Newest first</span>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Date</th>
                <th>Extra</th>
                <th class="tbl-mobile-hide">Type</th>
                <th class="text-end">Qty</th>
                <th class="text-end tbl-mobile-hide">Unit Cost</th>
                <th class="tbl-mobile-hide">Note</th>
                <th class="text-end">Current Stock</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No stock records found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(display_date($r['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($r['extra_name']); ?></td>
                    <td class="tbl-mobile-hide"><?php echo htmlspecialchars($r['movement_type']); ?></td>
                    <td class="text-end"><?php echo rtrim(rtrim(number_format((float)$r['qty'],2), '0'), '.'); ?></td>
                    <td class="text-end tbl-mobile-hide"><?php echo $r['unit_cost'] !== null ? '₵'.number_format((float)$r['unit_cost'],2) : '-'; ?></td>
                    <td class="tbl-mobile-hide"><?php echo htmlspecialchars($r['note'] ?: '-'); ?></td>
                    <td class="text-end"><?php echo rtrim(rtrim(number_format((float)$r['stock_qty'],2), '0'), '.'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($total_pages > 1): ?>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <small class="text-secondary">Page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
            <nav>
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                  <a class="page-link" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>page=<?php echo max(1, $page - 1); ?>">Prev</a>
                </li>
                <?php $sp = max(1, $page - 2); $ep = min($total_pages, $page + 2); for ($p = $sp; $p <= $ep; $p++): ?>
                  <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>page=<?php echo $p; ?>"><?php echo $p; ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                  <a class="page-link" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>page=<?php echo min($total_pages, $page + 1); ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </main>
    <?php include dirname(__DIR__).'/templates/partials/footer.php'; ?>
  </div>

  <script>
    (function(){
      const btn = document.getElementById('btnSidebar');
      btn?.addEventListener('click', ()=> document.body.classList.toggle('sidebar-open'));
      document.addEventListener('click', (e)=>{
        if (document.body.classList.contains('sidebar-open')){
          const s = document.querySelector('.sidebar');
          if (s && !s.contains(e.target) && !btn.contains(e.target)){
            document.body.classList.remove('sidebar-open');
          }
        }
      });
    })();
  </script>
</body>
</html>
