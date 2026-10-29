<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

// ----------------------
// Filters (same bar style as Bookings)
// ----------------------
$filter_room   = (int)($_GET['room_id'] ?? 0);
$filter_status = $_GET['status'] ?? 'all'; // 'all' | 'checked_out' | 'cancelled'
$filter_start  = trim($_GET['start'] ?? date('Y-m-d', strtotime('-30 days')));
$filter_end    = trim($_GET['end'] ?? date('Y-m-d'));

$start_dt = date('Y-m-d 00:00:00', strtotime($filter_start));
$end_dt   = date('Y-m-d 23:59:59', strtotime($filter_end));

// Room list for the filter dropdown
$rooms_all = $mysqli->query('SELECT id,name FROM rooms ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$rooms_total = (int)$mysqli->query('SELECT COUNT(*) c FROM rooms')->fetch_assoc()['c'];

// ----------------------
// Helper to bind dynamic conditions for payments
// ----------------------
$cond = ' WHERE p.created_at BETWEEN ? AND ? ';
$types = 'ss';
$params = [$start_dt, $end_dt];
$join = ' JOIN bookings b ON b.id=p.booking_id ';

if ($filter_room > 0) { $cond .= ' AND b.room_id=? '; $types.='i'; $params[]=$filter_room; }
if (in_array($filter_status, ['checked_out','cancelled'], true)) { $cond .= ' AND b.status=? '; $types.='s'; $params[]=$filter_status; }

// ----------------------
// Revenue — Daily
// ----------------------
$sql_daily = 'SELECT DATE(p.created_at) d, SUM(p.amount) s FROM payments p'.$join.$cond.' GROUP BY DATE(p.created_at) ORDER BY d DESC';
$st = $mysqli->prepare($sql_daily); $st->bind_param($types, ...$params); $st->execute();
$rev_daily = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Total revenue in range
$rev_total = 0.0; foreach ($rev_daily as $row) { $rev_total += (float)$row['s']; }

// Pagination for Revenue — Daily (table view only)
$daily_size = max(5, min(50, (int)($_GET['dsize'] ?? 14)));
$daily_page = max(1, (int)($_GET['dpage'] ?? 1));
$daily_total_rows = count($rev_daily);
$daily_pages = max(1, (int)ceil($daily_total_rows / $daily_size));
if ($daily_page > $daily_pages) { $daily_page = $daily_pages; }
$daily_offset = ($daily_page - 1) * $daily_size;
$rev_daily_page = array_slice($rev_daily, $daily_offset, $daily_size);
$daily_qs = $_GET; $daily_qs['dsize'] = $daily_size; unset($daily_qs['dpage'],$daily_qs['export'],$daily_qs['which']);
$daily_qs_str = http_build_query($daily_qs);

// ----------------------
// Revenue — Weekly (ISO week)
// ----------------------
$sql_weekly = 'SELECT YEARWEEK(p.created_at,1) wk, MIN(DATE(p.created_at)) start_d, MAX(DATE(p.created_at)) end_d, SUM(p.amount) s
               FROM payments p'.$join.$cond.' GROUP BY YEARWEEK(p.created_at,1) ORDER BY start_d DESC';
$st = $mysqli->prepare($sql_weekly); $st->bind_param($types, ...$params); $st->execute();
$rev_weekly = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// ----------------------
// Pagination for Revenue — Weekly (table view only)
// ----------------------
$weekly_size = max(5, min(50, (int)($_GET['wsize'] ?? 14)));
$weekly_page = max(1, (int)($_GET['wpage'] ?? 1));
$weekly_total_rows = count($rev_weekly);
$weekly_pages = max(1, (int)ceil($weekly_total_rows / $weekly_size));
if ($weekly_page > $weekly_pages) { $weekly_page = $weekly_pages; }
$weekly_offset = ($weekly_page - 1) * $weekly_size;
$rev_weekly_page = array_slice($rev_weekly, $weekly_offset, $weekly_size);
$weekly_qs = $_GET; $weekly_qs['wsize'] = $weekly_size; unset($weekly_qs['wpage'],$weekly_qs['export'],$weekly_qs['which']);
$weekly_qs_str = http_build_query($weekly_qs);

// ----------------------
// Revenue — Monthly
// ----------------------
$sql_monthly = "SELECT DATE_FORMAT(p.created_at,'%Y-%m') ym, SUM(p.amount) s FROM payments p".$join.$cond." GROUP BY ym ORDER BY ym DESC";
$st = $mysqli->prepare($sql_monthly); $st->bind_param($types, ...$params); $st->execute();
$rev_monthly = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// ----------------------
// Payments by Method
// ----------------------
$sql_pm = 'SELECT p.method, SUM(p.amount) s FROM payments p'.$join.$cond.' GROUP BY p.method';
$st = $mysqli->prepare($sql_pm); $st->bind_param($types, ...$params); $st->execute();
$pay_by_method = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// ----------------------
// Occupancy — calculate each room's occupied nights for the selected period
// Formula per room: occupied nights / number of days in period * 100
// Overall Avg Occupancy: average of the individual room occupancy percentages
// ----------------------

// Reporting period is inclusive of both selected dates.
$period_start = (new DateTime($filter_start))->setTime(0, 0, 0);
$period_end   = (new DateTime($filter_end))->setTime(0, 0, 0);
$period_end_exclusive = (clone $period_end)->modify('+1 day');
$period_days = ($period_end >= $period_start)
  ? $period_start->diff($period_end)->days + 1
  : 0;

// Build the set of rentable rooms used for occupancy calculations.
// Exclude the non-rentable room named "Service". If a room filter is selected,
// occupancy is calculated for that room only.
$occ_rooms = [];
foreach ($rooms_all as $room) {
  $room_id   = (int)$room['id'];
  $room_name = trim((string)$room['name']);

  if (strcasecmp($room_name, 'Service') === 0) { continue; }
  if ($filter_room > 0 && $room_id !== $filter_room) { continue; }

  $occ_rooms[$room_id] = [
    'id' => $room_id,
    'name' => $room_name,
    'occupied_dates' => [],
    'occupied_nights' => 0,
    'pct' => 0.0,
  ];
}
$occ_total = count($occ_rooms);
$occ_room_ids = array_keys($occ_rooms);

// Fetch all non-cancelled bookings that overlap the selected reporting period.
$stb = $mysqli->prepare(
  "SELECT b.room_id, b.checkin_at, b.checkout_at
   FROM bookings b
   WHERE b.status <> 'cancelled'
     AND b.checkin_at < ?
     AND (b.checkout_at IS NULL OR b.checkout_at > ?)"
);
$period_end_exclusive_dt = $period_end_exclusive->format('Y-m-d 00:00:00');
$period_start_dt = $period_start->format('Y-m-d 00:00:00');
$stb->bind_param('ss', $period_end_exclusive_dt, $period_start_dt);
$stb->execute();
$bookings_range = $stb->get_result()->fetch_all(MYSQLI_ASSOC);

// For every booking, mark each occupied NIGHT against its room.
// Check-in night counts; checkout date does not count as an occupied night.
// Using the date as an array key also prevents duplicate/overlapping bookings
// from counting the same room-night more than once.
if ($period_days > 0 && $occ_total > 0) {
  foreach ($bookings_range as $booking) {
    $room_id = (int)$booking['room_id'];
    if (!isset($occ_rooms[$room_id])) { continue; }

    $booking_start = (new DateTime($booking['checkin_at']))->setTime(0, 0, 0);
    $booking_end_exclusive = $booking['checkout_at']
      ? (new DateTime($booking['checkout_at']))->setTime(0, 0, 0)
      : clone $period_end_exclusive;

    // Clip the booking to the selected report period.
    $night = $booking_start > $period_start ? clone $booking_start : clone $period_start;
    $booking_end_exclusive = $booking_end_exclusive < $period_end_exclusive
      ? $booking_end_exclusive
      : clone $period_end_exclusive;

    while ($night < $booking_end_exclusive) {
      $occ_rooms[$room_id]['occupied_dates'][$night->format('Y-m-d')] = true;
      $night->modify('+1 day');
    }
  }
}

// Apply the requested formula to EACH room:
// occupancy % = occupied nights / number of days in selected period * 100
$occupied_room_nights = 0;
$room_occupancy_pct_total = 0.0;
foreach ($occ_rooms as &$room_occ) {
  $room_occ['occupied_nights'] = count($room_occ['occupied_dates']);
  $room_occ['pct'] = $period_days > 0
    ? round(($room_occ['occupied_nights'] / $period_days) * 100, 2)
    : 0.0;

  $occupied_room_nights += $room_occ['occupied_nights'];
  $room_occupancy_pct_total += $room_occ['pct'];
}
unset($room_occ);

// The summary card shows the average of the individual room percentages.
$avg_occupancy_pct = $occ_total > 0
  ? round($room_occupancy_pct_total / $occ_total, 2)
  : 0.0;

// Preserve the existing daily occupancy dataset used by the CSV export.
$occ_daily = [];
if ($period_days > 0) {
  $cursor = clone $period_start;
  for ($i = 0; $i < $period_days; $i++) {
    $date_key = $cursor->format('Y-m-d');
    $occupied_count = 0;

    foreach ($occ_rooms as $room_occ) {
      if (isset($room_occ['occupied_dates'][$date_key])) {
        $occupied_count++;
      }
    }

    $occ_daily[] = [
      'date' => $date_key,
      'occupied' => $occupied_count,
      'pct' => $occ_total > 0 ? round(($occupied_count / $occ_total) * 100, 2) : 0,
    ];

    $cursor->modify('+1 day');
  }
}

$available_room_nights = $occ_total * $period_days;

// ----------------------
// Expenses — by Category (for pie chart)
// ----------------------
$stx = $mysqli->prepare('SELECT c.name cat, SUM(e.amount) s
                         FROM expenses e JOIN expense_categories c ON c.id = e.category_id
                         WHERE e.expense_date BETWEEN ? AND ?
                         GROUP BY c.id
                         ORDER BY s DESC');
$stx->bind_param('ss', $filter_start, $filter_end); $stx->execute();
$exp_by_cat = $stx->get_result()->fetch_all(MYSQLI_ASSOC);
$exp_labels = array_map(fn($r)=>$r['cat'], $exp_by_cat);
$exp_values = array_map(fn($r)=>(float)$r['s'], $exp_by_cat);
$exp_total  = array_sum($exp_values);

// ----------------------
// Expenses — Monthly time-series (grouped by YEAR-MONTH)
// ----------------------
$sql_exp_monthly = "SELECT DATE_FORMAT(e.expense_date,'%Y-%m') ym, SUM(e.amount) s FROM expenses e WHERE e.expense_date BETWEEN ? AND ? GROUP BY ym ORDER BY ym DESC";
$stxm = $mysqli->prepare($sql_exp_monthly); $stxm->bind_param('ss', $filter_start, $filter_end); $stxm->execute();
$exp_monthly = $stxm->get_result()->fetch_all(MYSQLI_ASSOC);

// ----------------------
// CSV Export switch
// ----------------------
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $which = $_GET['which'] ?? 'revenue_daily';
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="reports_export_'.preg_replace('/[^a-z_]/','',$which).'.csv"');
  $out = fopen('php://output','w');

  switch ($which) {
    case 'revenue_daily':
      fputcsv($out, ['Date','Revenue']);
      foreach ($rev_daily as $r) fputcsv($out, [$r['d'], number_format((float)$r['s'],2,'.','')]);
      break;
    case 'revenue_weekly':
      fputcsv($out, ['ISO Week','Start','End','Revenue']);
      foreach ($rev_weekly as $r) fputcsv($out, [$r['wk'], $r['start_d'], $r['end_d'], number_format((float)$r['s'],2,'.','')]);
      break;
    case 'revenue_monthly':
      fputcsv($out, ['Year-Month','Revenue']);
      foreach ($rev_monthly as $r) fputcsv($out, [$r['ym'], number_format((float)$r['s'],2,'.','')]);
      break;
    case 'expenses_monthly':
      fputcsv($out, ['Year-Month','Amount']);
      foreach ($exp_monthly as $r) fputcsv($out, [$r['ym'], number_format((float)$r['s'],2,'.','')]);
      break;
    case 'occupancy_daily':
      fputcsv($out, ['Date','Occupied Rooms','Occupancy %']);
      foreach ($occ_daily as $o) fputcsv($out, [$o['date'], $o['occupied'], $o['pct']]);
      break;
    case 'expenses_by_category':
      fputcsv($out, ['Category','Amount']);
      foreach ($exp_by_cat as $x) fputcsv($out, [$x['cat'], number_format((float)$x['s'],2,'.','')]);
      break;
  }
  fclose($out);
  exit;
}

// Build base querystring to preserve filters in export links
$qs = $_GET; unset($qs['export'],$qs['which']); $qs_str = http_build_query($qs);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Reports</title>
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
    .table { --bs-table-bg: transparent; color:var(--text); }
    .table thead th { color:#334155; white-space:nowrap; }

    /* Sidebar links on light theme */
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }

    /* Outline light buttons are hard to read on light UI; restyle them gently */
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }

    /* Mobile hamburger */
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }

    /* Chart containers scale with viewport */
    .chart-box{ height:340px; }
    @media (max-width: 1199.98px){ .chart-box{ height:300px; } }
    @media (max-width: 991.98px){ .chart-box{ height:260px; } }
    @media (max-width: 575.98px){ .chart-box{ height:220px; } }

    /* Tighten pagination on phones */
    @media (max-width: 575.98px){
      .pagination .page-link{ padding:.25rem .5rem; }
      .table-responsive{ scrollbar-width: thin; }
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
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h3 class="m-0">Reports</h3>
          <div class="text-secondary">Revenue, occupancy, and payment breakdowns</div>
        </div>
      </div>

      <!-- Filter Bar (same pattern) -->
      <form method="get" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Start date</label>
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($filter_start); ?>">
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">End date</label>
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($filter_end); ?>">
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id">
              <option value="0">All rooms</option>
              <?php foreach ($rooms_all as $rr): ?>
                <option value="<?php echo (int)$rr['id']; ?>" <?php echo ($filter_room==(int)$rr['id'])?'selected':''; ?>><?php echo htmlspecialchars($rr['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-md-2">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php $statuses = ['all'=>'All','checked_out'=>'Checked out','cancelled'=>'Cancelled']; foreach($statuses as $k=>$v): ?>
                <option value="<?php echo $k; ?>" <?php echo ($filter_status===$k)?'selected':''; ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-1 d-grid">
            <button class="btn btn-primary">Apply</button>
          </div>
        </div>
      </form>

      <!-- Top Summary Cards -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Total Revenue</div><div class="h4 mb-0">₵<?php echo number_format($rev_total,2); ?></div></div></div>
        <div class="col-12 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Avg Daily Revenue</div><div class="h4 mb-0">₵<?php echo number_format($period_days>0?$rev_total/$period_days:0,2); ?></div></div></div>
        <div class="col-12 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Avg Occupancy</div><div class="h4 mb-0"><?php echo number_format($avg_occupancy_pct,2); ?>%</div></div></div>
        <div class="col-12 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Total Expenses</div><div class="h4 mb-0">₵<?php echo number_format($exp_total,2); ?></div></div></div>
      </div>

      <!-- Revenue Tables (Daily + Weekly side by side) -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="m-0">Revenue — Daily</h5>
              <div class="d-flex gap-2">
                <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv&which=revenue_daily">Export CSV</a>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped align-middle mb-2">
                <thead><tr><th>Date</th><th class="text-end">Revenue (₵)</th></tr></thead>
                <tbody>
                  <?php foreach ($rev_daily_page as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['d']); ?></td><td class="text-end">₵<?php echo number_format($r['s'],2); ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <small class="text-secondary">Showing <?php echo $daily_total_rows?($daily_offset+1):0; ?>–<?php echo min($daily_total_rows, $daily_offset+$daily_size); ?> of <?php echo $daily_total_rows; ?></small>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $daily_page<=1?'disabled':''; ?>">
                    <a class="page-link" href="?<?php echo $daily_qs_str; ?>&dpage=<?php echo max(1,$daily_page-1); ?>">Prev</a>
                  </li>
                  <?php $sp=max(1,$daily_page-2); $ep=min($daily_pages,$daily_page+2); for($p=$sp;$p<=$ep;$p++): ?>
                    <li class="page-item <?php echo $p===$daily_page?'active':''; ?>">
                      <a class="page-link" href="?<?php echo $daily_qs_str; ?>&dpage=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $daily_page>=$daily_pages?'disabled':''; ?>">
                    <a class="page-link" href="?<?php echo $daily_qs_str; ?>&dpage=<?php echo min($daily_pages,$daily_page+1); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="m-0">Revenue — Weekly (ISO)</h5>
              <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv&which=revenue_weekly">Export CSV</a>
            </div>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Week</th><th>Start</th><th>End</th><th class="text-end">₵</th></tr></thead>
                <tbody>
                  <?php foreach ($rev_weekly_page as $r): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['wk']); ?></td>
                      <td><?php echo htmlspecialchars($r['start_d']); ?></td>
                      <td><?php echo htmlspecialchars($r['end_d']); ?></td>
                      <td class="text-end">₵<?php echo number_format($r['s'],2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
              <small class="text-secondary">Showing <?php echo $weekly_total_rows?($weekly_offset+1):0; ?>–<?php echo min($weekly_total_rows, $weekly_offset+$weekly_size); ?> of <?php echo $weekly_total_rows; ?></small>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $weekly_page<=1?'disabled':''; ?>">
                    <a class="page-link" href="?<?php echo $weekly_qs_str; ?>&wpage=<?php echo max(1,$weekly_page-1); ?>">Prev</a>
                  </li>
                  <?php $wsp=max(1,$weekly_page-2); $wep=min($weekly_pages,$weekly_page+2); for($p=$wsp;$p<=$wep;$p++): ?>
                    <li class="page-item <?php echo $p===$weekly_page?'active':''; ?>">
                      <a class="page-link" href="?<?php echo $weekly_qs_str; ?>&wpage=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $weekly_page>=$weekly_pages?'disabled':''; ?>">
                    <a class="page-link" href="?<?php echo $weekly_qs_str; ?>&wpage=<?php echo min($weekly_pages,$weekly_page+1); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly + Payments by Method side by side -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="m-0">Revenue — Monthly</h5>
              <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv&which=revenue_monthly">Export CSV</a>
            </div>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Year-Month</th><th class="text-end">₵</th></tr></thead>
                <tbody>
                  <?php foreach ($rev_monthly as $r): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['ym']); ?></td>
                      <td class="text-end">₵<?php echo number_format($r['s'],2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="m-0">Expenses — Monthly</h5>
              <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv&which=expenses_monthly">Export CSV</a>
            </div>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Year-Month</th><th class="text-end">₵</th></tr></thead>
                <tbody>
                  <?php foreach ($exp_monthly as $m): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($m['ym']); ?></td>
                      <td class="text-end">₵<?php echo number_format($m['s'],2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Expenses by Category (replacing Occupancy Daily table) -->
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h5 class="m-0">Expenses by Category</h5>
          <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv&which=expenses_by_category">Export CSV</a>
        </div>
        <div class="row g-3">
          <div class="col-12 col-xl-8">
            <div class="chart-box">
              <canvas id="expPie" aria-label="Expenses pie chart" role="img"></canvas>
            </div>
          </div>
          <div class="col-12 col-xl-4">
            <div class="mt-xl-0 mt-2">
              <div class="text-secondary">Total Expenses in Period</div>
              <div class="h4 mb-2">₵<?php echo number_format($exp_total,2); ?></div>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <tbody>
                    <?php foreach ($exp_by_cat as $x): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($x['cat']); ?></td>
                        <td class="text-end">₵<?php echo number_format((float)$x['s'],2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes
      <div class="text-secondary small">
        <em>Note:</em> Revenue reports are based on <strong>payment dates</strong> (cash basis). Occupancy excludes cancelled bookings and counts a room as occupied for any day on which a booking overlaps. Expenses are summarized by category using <strong>expense_date</strong>.
      </div>  -->

    </main>
    <?php include __DIR__.'/partials/footer.php'; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      // Sidebar toggle (mobile)
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

      // Expenses pie chart
      const labels = <?php echo json_encode($exp_labels); ?>;
      const data    = <?php echo json_encode($exp_values, JSON_NUMERIC_CHECK); ?>;
      const pieEl = document.getElementById('expPie');
      let pieChart = null;
      function renderPie(){
        if (!pieEl || !labels || !labels.length) return;
        const ctx = pieEl.getContext('2d');
        if (pieChart) { pieChart.destroy(); }
        pieChart = new Chart(ctx, {
          type: 'pie',
          data: { labels, datasets: [{ data }] },
          options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{
              legend:{ position: (window.matchMedia('(min-width: 1200px)').matches? 'right':'bottom') },
              tooltip:{ callbacks:{ label:(c)=> `${c.label}: ₵${Number(c.parsed).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}` } }
            }
          }
        });
      }
      renderPie();
      // Re-render on resize to adapt legend position
      let to; window.addEventListener('resize', ()=>{ clearTimeout(to); to=setTimeout(renderPie, 150); });
    })();
  </script>
</body>
</html>
