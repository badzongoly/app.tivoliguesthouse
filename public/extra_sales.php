<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/app.php';

// Filters
$filter_start = trim($_GET['start'] ?? date('Y-m-01'));
$filter_end   = trim($_GET['end'] ?? date('Y-m-d'));
$filter_extra = (int)($_GET['extra_id'] ?? 0);

$start_dt = date('Y-m-d 00:00:00', strtotime($filter_start));
$end_dt   = date('Y-m-d 23:59:59', strtotime($filter_end));

// Extras dropdown source
$extras_list = $mysqli->query("SELECT id, name, price, active FROM extras ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Query sold extras in date range (based on booking_extras.created_at)
$sql = "SELECT 
          be.id,
          be.created_at,
          be.booking_id,
          COALESCE(be.description, e.name, 'Extra') AS extra_name,
          be.qty,
          be.unit_price,
          (be.qty * be.unit_price) AS line_total,
          b.guest_name,
          r.name AS room_name
        FROM booking_extras be
        LEFT JOIN extras e ON e.id = be.extra_id
        LEFT JOIN bookings b ON b.id = be.booking_id
        LEFT JOIN rooms r ON r.id = b.room_id
        WHERE be.created_at BETWEEN ? AND ?";
$types = 'ss';
$params = [$start_dt, $end_dt];

if ($filter_extra > 0) {
  $sql .= " AND be.extra_id = ?";
  $types .= 'i';
  $params[] = $filter_extra;
}

$sql .= " ORDER BY be.created_at DESC LIMIT 500";
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$total_revenue = 0.0;
$total_qty = 0.0;
foreach ($rows as $row) {
  $total_revenue += (float)$row['line_total'];
  $total_qty += (float)$row['qty'];
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="extras_sales_export.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Date', 'Extra', 'Guest', 'Room', 'Qty', 'Unit Price', 'Line Total']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['created_at'],
      $r['extra_name'],
      $r['guest_name'],
      $r['room_name'],
      rtrim(rtrim(number_format((float)$r['qty'], 2, '.', ''), '0'), '.'),
      number_format((float)$r['unit_price'], 2, '.', ''),
      number_format((float)$r['line_total'], 2, '.', '')
    ]);
  }
  fclose($out);
  exit;
}

$qs = $_GET;
unset($qs['export']);
$qs_str = http_build_query($qs);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Extras Sold</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{
      --bg-page:#f7f8fb;
      --bg-surface:#ffffff;
      --line:#e5e7eb;
      --text:#0f172a;
      --muted:#475569;
      --sidebar-w:260px;
    }
    body { background:var(--bg-page); color:var(--text); }
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
      --bs-navbar-color:#334155;
      --bs-navbar-hover-color:#0f172a;
      --bs-navbar-brand-color:#0f172a;
      --bs-navbar-brand-hover-color:#0f172a;
    }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    .table { --bs-table-bg: transparent; color:var(--text); }
    .table thead th { color:#334155; white-space:nowrap; }
    a { text-decoration:none; }
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }
    @media (max-width: 575.98px){
      .tbl-mobile-hide{ display:none; }
    }
  </style>
<?php require dirname(__DIR__).'/templates/layouts/head.php'; ?>
</head>
<body class="app-page">
  <?php include dirname(__DIR__).'/templates/partials/sidebar.php'; ?>
  

  <div class="content">
    <?php include dirname(__DIR__).'/templates/partials/navtop.php'; ?>
    <main id="workspace-main" tabindex="-1" class="container py-4">
      <?php require dirname(__DIR__).'/templates/partials/extras-nav.php'; ?>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h3 class="m-0">Extras Sold</h3>
          <div class="text-secondary">Query extras sales by date range and specific extra</div>
        </div>
        <a class="btn btn-outline-light" href="bookings.php">Back to Bookings</a>
      </div>

      <form method="get" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label">Start date</label>
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($filter_start); ?>">
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label">End date</label>
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($filter_end); ?>">
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Extra</label>
            <select class="form-select" name="extra_id">
              <option value="0">All extras</option>
              <?php foreach ($extras_list as $e): ?>
                <option value="<?php echo (int)$e['id']; ?>" <?php echo $filter_extra === (int)$e['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($e['name']); ?><?php echo (int)$e['active'] === 0 ? ' (inactive)' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-2 d-grid">
            <button class="btn btn-primary">Apply Filter</button>
          </div>
        </div>
        <div class="mt-2 d-flex justify-content-end">
          <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv">Export CSV</a>
        </div>
      </form>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
          <div class="card p-3 h-100">
            <div class="text-secondary">Rows Returned</div>
            <div class="h4 mb-0"><?php echo count($rows); ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-3 h-100">
            <div class="text-secondary">Total Quantity Sold</div>
            <div class="h4 mb-0"><?php echo rtrim(rtrim(number_format($total_qty, 2), '0'), '.'); ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-3 h-100">
            <div class="text-secondary">Total Revenue</div>
            <div class="h4 mb-0">₵<?php echo number_format($total_revenue, 2); ?></div>
          </div>
        </div>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h5 class="m-0">Extras Sales Results</h5>
          <span class="text-secondary small">Based on booking_extras.created_at</span>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Date</th>
                <th>Extra</th>
                <th class="tbl-mobile-hide">Guest</th>
                <th class="tbl-mobile-hide">Room</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Unit ₵</th>
                <th class="text-end">Total ₵</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="7" class="text-center text-secondary py-4">No extras sold for the selected filter.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(display_date($r['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($r['extra_name']); ?></td>
                    <td class="tbl-mobile-hide"><?php echo htmlspecialchars($r['guest_name'] ?? '-'); ?></td>
                    <td class="tbl-mobile-hide"><?php echo htmlspecialchars($r['room_name'] ?? '-'); ?></td>
                    <td class="text-end"><?php echo rtrim(rtrim(number_format((float)$r['qty'], 2), '0'), '.'); ?></td>
                    <td class="text-end">₵<?php echo number_format((float)$r['unit_price'], 2); ?></td>
                    <td class="text-end">₵<?php echo number_format((float)$r['line_total'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
              <tfoot>
                <tr>
                  <th colspan="6" class="text-end">Total Revenue</th>
                  <th class="text-end">₵<?php echo number_format($total_revenue, 2); ?></th>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>
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