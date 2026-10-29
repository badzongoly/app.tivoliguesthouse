<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

$filter_start = trim($_GET['start'] ?? date('Y-m-d', strtotime('-30 days')));
$filter_end   = trim($_GET['end'] ?? date('Y-m-d'));
$page         = max(1, (int)($_GET['page'] ?? 1));
$page_size    = 25;
$offset       = ($page - 1) * $page_size;

$start_dt = date('Y-m-d 00:00:00', strtotime($filter_start));
$end_dt   = date('Y-m-d 23:59:59', strtotime($filter_end));

$countSql = 'SELECT COUNT(*) c FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE p.created_at BETWEEN ? AND ?';
$listSql  = 'SELECT p.id, p.booking_id, p.method, p.amount, p.note, p.created_at, b.guest_name, b.status, r.name AS room_name
             FROM payments p
             JOIN bookings b ON b.id=p.booking_id
             JOIN rooms r ON r.id=b.room_id
             WHERE p.created_at BETWEEN ? AND ?
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ? OFFSET ?';

$countStmt = $mysqli->prepare($countSql);
$countStmt->bind_param('ss', $start_dt, $end_dt);
$countStmt->execute();
$total_rows = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $page_size));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $page_size; }

$listStmt = $mysqli->prepare($listSql);
$listStmt->bind_param('ssii', $start_dt, $end_dt, $page_size, $offset);
$listStmt->execute();
$payments = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$qs = $_GET;
unset($qs['page']);
$qs_str = http_build_query($qs);

function h($value) { return htmlspecialchars((string)$value); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Payments</title>
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
    @media (max-width: 991.98px){ .sidebar { transform:translateX(-100%); box-shadow: 0 8px 30px rgba(0,0,0,.08); } body.sidebar-open .sidebar { transform:translateX(0); } .content { margin-left:0; } body.sidebar-open::before{ content:""; position:fixed; inset:0; background:rgba(15,23,42,.25); z-index:1030; } }
    .navtop { background:#ffffff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:1000; }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 1px rgba(0,0,0,.02),0 3px 6px rgba(0,0,0,.04); }
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }
    @media (max-width: 575.98px){ .tbl-mobile-hide{ display:none; } .pagination .page-link{ padding:.25rem .5rem; } }
  </style>
</head>
<body>
  <?php include __DIR__.'/partials/sidebar.php'; ?>
  <button class="btn btn-outline-primary sidebar-toggle" id="btnSidebar" aria-label="Toggle navigation">☰</button>
  <div class="content">
    <?php include __DIR__.'/partials/navtop.php'; ?>
    <main class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h3 class="m-0">Payment Logs</h3>
          <div class="text-secondary">Paginated app payments filtered by date range</div>
        </div>
        <a class="btn btn-outline-light" href="reports.php">Go to Reports</a>
      </div>

      <form method="get" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Start date</label>
            <input type="date" class="form-control" name="start" value="<?php echo h($filter_start); ?>">
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">End date</label>
            <input type="date" class="form-control" name="end" value="<?php echo h($filter_end); ?>">
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h5 class="mb-0">Payments</h5>
            <small class="text-secondary">Showing <?php echo $total_rows ? ($offset + 1) : 0; ?>–<?php echo min($total_rows, $offset + count($payments)); ?> of <?php echo $total_rows; ?></small>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th class="tbl-mobile-hide">Room</th>
                <th>Guest</th>
                <th class="tbl-mobile-hide">Status</th>
                <th>Note</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$payments): ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No payments found for this date range.</td></tr>
              <?php else: foreach ($payments as $p): ?>
                <tr>
                  <td><?php echo h($p['created_at']); ?></td>
                  <td>₵<?php echo number_format((float)$p['amount'], 2); ?></td>
                  <td><?php echo strtoupper(h($p['method'])); ?></td>
                  <td class="tbl-mobile-hide"><?php echo h($p['room_name']); ?></td>
                  <td><?php echo h($p['guest_name']); ?></td>
                  <td class="tbl-mobile-hide"><?php echo h($p['status']); ?></td>
                  <td><?php echo h($p['note']); ?></td>
                  <td><a class="btn btn-sm btn-outline-light" href="checkout.php?booking_id=<?php echo (int)$p['booking_id']; ?>">Booking</a></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($total_pages > 1): ?>
      <nav aria-label="Payments pagination" class="mb-3">
        <ul class="pagination pagination-sm">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
              <a class="page-link" href="?<?php echo h($qs_str . ($qs_str ? '&' : '') . 'page=' . $i); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </main>
  </div>
  <script>
    document.getElementById('btnSidebar')?.addEventListener('click', function(){ document.body.classList.toggle('sidebar-open'); });
  </script>
</body>
</html>
