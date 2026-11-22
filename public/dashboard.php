<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/app.php';
require_once dirname(__DIR__).'/bootstrap/stay-reporting.php';

// Derive quick stats (assumes schema already exists)
$rooms_total = (int)$mysqli->query("SELECT COUNT(*) c FROM rooms WHERE rooms.type <> 'Service'")->fetch_assoc()['c'];
$rooms_occ   = (int)$mysqli->query("SELECT COUNT(*) c FROM rooms WHERE status='occupied'")->fetch_assoc()['c'];
$rooms_vac   = max(0, $rooms_total - $rooms_occ);
$bookingSource=\Tivoli\Repository\DepartureFacts::source((new \Tivoli\Service\FinanceService(access_service()))->installed());
$active_bookings=(int)access_service()->repo->run("SELECT COUNT(*) FROM ($bookingSource) b WHERE b.operational_status IN ('active','checked_in')")->fetchColumn();

// Today's revenue
$receiptSource=\Tivoli\Repository\ReceiptAccounting::paymentsSql((new \Tivoli\Service\FinanceService(access_service()))->installed());
$receiptCorrections=\Tivoli\Repository\ReceiptAccounting::period(access_service()->repo,gmdate('Y-m-d'),gmdate('Y-m-d'));
$today_rev = (float)access_service()->repo->run("SELECT COALESCE(SUM(amount),0) s FROM ($receiptSource) p WHERE DATE(created_at)=UTC_DATE()")->fetchColumn();

// ---- Charts data (last 90 days for headroom) ----
$st = access_service()->repo->run("SELECT DATE(created_at) d, SUM(amount) s FROM ($receiptSource) p GROUP BY DATE(created_at) ORDER BY d ASC");
$labels = [];$rev_series=[];
while ($r=$st->fetch()) { $labels[]=$r['d']; $rev_series[]=(float)$r['s']; }

// Rooms for histogram (booked nights by room)
$rooms_list = $mysqli->query("SELECT id,name FROM rooms WHERE rooms.type <> 'Service' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$room_labels = array_map(function($r){ return $r['name']; }, $rooms_list);

/**
 * Calculate booked room-nights for each room inside the requested rolling period.
 *
 * A night is based on calendar dates, not elapsed hours. Example:
 * check-in  2026-10-01 13:01
 * check-out 2026-10-05 11:01
 * = 4 nights (Oct 1, 2, 3 and 4).
 *
 * Bookings that overlap the reporting period are included and clipped to that
 * period. Cancelled bookings are ignored. If checkout_at is NULL, the booking
 * is treated as continuing through the end of the reporting period.
 */
function _countBookedNightsByRoom($mysqli, $rooms_list, $days){
  $period_end = new DateTimeImmutable(gmdate('Y-m-d'));
  $period_start = $period_end->modify('-'.($days - 1).' days');
  $period_end_exclusive = $period_end->modify('+1 day');

  $start_date = $period_start->format('Y-m-d');
  $end_date   = $period_end->format('Y-m-d');

  $map = [];
  foreach ($rooms_list as $r) {
    $map[(int)$r['id']] = 0;
  }

  // Select every non-cancelled booking that overlaps the reporting period.
  // Using overlap logic matters because a booking may start before the period
  // but still contribute nights inside it.
  $stayExclusion=stay_reporting_exclusion('b');
  $bookingSource=\Tivoli\Repository\DepartureFacts::source((new \Tivoli\Service\FinanceService(access_service()))->installed());
  $res=access_service()->repo->run("
    SELECT b.room_id,b.checkin_at,b.actual_checkout checkout_at
    FROM ($bookingSource) b
    WHERE b.operational_status <> 'cancelled' {$stayExclusion}
      AND DATE(b.checkin_at) <= ?
      AND (DATE(b.actual_checkout) > ? OR (b.actual_checkout IS NULL AND b.operational_status='active'))
  ",[$end_date,$start_date]);

  while ($row = $res->fetch()) {
    $room_id = (int)$row['room_id'];
    if (!array_key_exists($room_id, $map)) {
      continue; // Ignore Service/other rooms that are not in the chart.
    }

    // Work with dates only: the time of day does not change the number of nights.
    $booking_start = new DateTimeImmutable(substr($row['checkin_at'], 0, 10));
    $booking_end_exclusive = !empty($row['checkout_at'])
      ? new DateTimeImmutable(substr($row['checkout_at'], 0, 10))
      : $period_end_exclusive;

    // Clip the booking to the selected 7/14/30-day period.
    $clipped_start = ($booking_start > $period_start) ? $booking_start : $period_start;
    $clipped_end   = ($booking_end_exclusive < $period_end_exclusive)
      ? $booking_end_exclusive
      : $period_end_exclusive;

    if ($clipped_end > $clipped_start) {
      $nights = (int)$clipped_start->diff($clipped_end)->days;
      $map[$room_id] += $nights;
    }
  }

  $out = [];
  foreach ($rooms_list as $r) {
    $out[] = $map[(int)$r['id']];
  }
  return $out;
}

$room_nights7  = _countBookedNightsByRoom($mysqli, $rooms_list, 7);
$room_nights14 = _countBookedNightsByRoom($mysqli, $rooms_list, 14);
$room_nights30 = _countBookedNightsByRoom($mysqli, $rooms_list, 30);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tivoli Guesthouse — Dashboard</title>
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

    /* Sidebar */
    .sidebar { width:var(--sidebar-w); min-height:100vh; background:#f8fafc; position:fixed; left:0; top:0; padding:1rem; border-right:1px solid var(--line); color:var(--text); transition:transform .25s ease; will-change:transform; }
    .content { margin-left:var(--sidebar-w); transition:margin-left .25s ease; }

    /* Mobile / tablet off-canvas */
    @media (max-width: 991.98px){
      .sidebar { transform:translateX(-100%); box-shadow: 0 8px 28px rgba(0,0,0,.12); z-index:1040; }
      body.sidebar-open .sidebar { transform:translateX(0); }
      .content { margin-left:0; }
      body.sidebar-open::before { content:""; position:fixed; inset:0; background:rgba(15,23,42,.3); z-index:1030; }
    }

    /* Top nav */
    .navtop { background:#ffffff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:1000; }
    .navtop.navbar {
      --bs-navbar-color:#334155;                 /* link text */
      --bs-navbar-hover-color:#0f172a;           /* hover text */
      --bs-navbar-brand-color:#0f172a;           /* brand */
      --bs-navbar-brand-hover-color:#0f172a;     /* brand hover */
    }

    /* Cards */
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    a { text-decoration:none; }

    /* Sidebar links readable on light theme */
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }

    /* Outline light buttons visible on light UI */
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }

    /* Mobile hamburger */
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1050; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; align-items:center; gap:.5rem; } }

    /* Charts scale with viewport */
    .chart-box{ height:380px; }
    @media (max-width: 1199.98px){ .chart-box{ height:320px; } }
    @media (max-width: 991.98px){ .chart-box{ height:280px; } }
    @media (max-width: 575.98px){ .chart-box{ height:240px; } }

    /* Tighten headings on small screens */
    h5.m-0 { line-height:1.2; }
  </style>
<?php require dirname(__DIR__).'/templates/layouts/head.php'; ?>
</head>
<body class="app-page">
<?php require_once dirname(__DIR__).'/src/Security/require_login.php'; ?>
<?php require_once dirname(__DIR__).'/bootstrap/app.php'; ?>
<?php include dirname(__DIR__).'/templates/partials/sidebar.php'; ?>

<!-- Mobile toggle button -->


<div class="content">
  <?php include dirname(__DIR__).'/templates/partials/navtop.php'; ?>
  <main id="workspace-main" tabindex="-1" class="container py-4">
    <div class="app-page-heading-row"><div><p class="app-eyebrow">YOUR DAY AT TIVOLI</p><h1>Today</h1><p>A clear view of your guesthouse, ready for the day.</p><?php if($receiptCorrections['count']):?><p>Today's recorded receipt totals include recording corrections. These move no money and do not change earned room revenue.</p><?php endif?></div><?php if(in_array('bookings.manage',$workspace['permissions'],true)):?><a class="btn btn-primary" href="bookings.php">New check-in <span aria-hidden="true">↗</span></a><?php endif?></div>

    <!-- Top stats become 2x2 on phones -->
    <div class="row g-3">
      <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Rooms</div><div class="h2 mb-0"><?php echo $rooms_total ?? 0; ?></div></div></div>
      <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Occupied</div><div class="h2 mb-0"><?php echo $rooms_occ ?? 0; ?></div></div></div>
      <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Vacant</div><div class="h2 mb-0"><?php echo $rooms_vac ?? 0; ?></div></div></div>
      <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-secondary">Active Bookings</div><div class="h2 mb-0"><?php echo $active_bookings ?? 0; ?></div></div></div>
    </div>

    <!-- KPI + Quick actions stack on mobile -->
    <div class="row g-3 mt-1 align-items-stretch">
      <div class="col-12 col-lg-6">
        <div class="card p-3 h-100">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <div class="text-secondary">Today's Revenue</div>
              <div class="h3 mb-0">₵<?php echo number_format($today_rev ?? 0,2); ?></div>
            </div>
            <a class="btn btn-outline-light" href="bookings.php">Go to Bookings</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card p-3 h-100">
          <div class="text-secondary mb-2">Quick Actions</div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="bookings.php">Check-In</a>
            <a class="btn btn-outline-primary" href="bookings.php">Check-Out</a>
            <a class="btn btn-outline-light" href="rooms.php">Manage Rooms</a>
            <a class="btn btn-outline-light" href="extras.php">Manage Extras</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts with 7/14/30 toggle; stack on mobile -->
    <div class="row g-3 mt-1">
      <div class="col-12 d-flex justify-content-end">
        <div class="btn-group flex-wrap" role="group" aria-label="Range">
          <button type="button" class="btn btn-outline-primary" id="range7">7d</button>
          <button type="button" class="btn btn-outline-primary" id="range14">14d</button>
          <button type="button" class="btn btn-outline-primary active" id="range30">30d</button>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <div class="card p-3 chart-box">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Daily Revenue (30 days)</h5>
          </div>
          <canvas id="revChart" aria-label="Daily revenue chart" role="img"></canvas>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <div class="card p-3 chart-box">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0" id="occTitle">Booked Nights by Room (last 30 days)</h5>
          </div>
          <canvas id="occChart" aria-label="Booked nights by room chart" role="img"></canvas>
          <p class="text-secondary">Reported departures with unknown times are excluded until owner review.</p>
        </div>
      </div>
    </div>

    
  </main>
  <?php include dirname(__DIR__).'/templates/partials/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function(){
    // Sidebar: toggle + backdrop close
    const btn = document.getElementById('btnSidebar');
    btn?.addEventListener('click', ()=>{ document.body.classList.toggle('sidebar-open'); });
    document.addEventListener('click', (e)=>{
      if (document.body.classList.contains('sidebar-open')){
        const s = document.querySelector('.sidebar');
        if (s && !s.contains(e.target) && !btn.contains(e.target)){
          document.body.classList.remove('sidebar-open');
        }
      }
    });

    // Charts: expect these globals to be echoed by your PHP controller
    const allLabels = window.allLabels || <?php echo json_encode(array_map('display_date',$labels ?? [])); ?>;
    const allRev    = window.allRev    || <?php echo json_encode($rev_series ?? [], JSON_NUMERIC_CHECK); ?>;
    const roomLabels= window.roomLabels|| <?php echo json_encode($room_labels ?? []); ?>;
    const nights7   = window.nights7   || <?php echo json_encode($room_nights7 ?? [], JSON_NUMERIC_CHECK); ?>;
    const nights14  = window.nights14  || <?php echo json_encode($room_nights14 ?? [], JSON_NUMERIC_CHECK); ?>;
    const nights30  = window.nights30  || <?php echo json_encode($room_nights30 ?? [], JSON_NUMERIC_CHECK); ?>;

    let revChart, occChart;
    const sliceLast = (arr, n) => arr.slice(-n);

    function makeRevConfig(labels, data){
      return {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Daily Revenue', data, borderWidth: 0 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, ticks: { callback: v => '₵' + Number(v).toLocaleString() } } },
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => '₵' + Number(ctx.parsed.y).toLocaleString() } } }
        }
      };
    }

    function makeOccConfig(labels, data){
      return {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Booked Nights', data, borderWidth: 0 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, ticks: { precision:0 } } },
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.parsed.y} night${ctx.parsed.y === 1 ? '' : 's'}` } } }
        }
      };
    }

    function render(n){
      const labels = sliceLast(allLabels, n);
      const rev = sliceLast(allRev, n);
      const occCounts = n===7 ? nights7 : (n===14 ? nights14 : nights30);

      if (revChart) {
        revChart.data.labels = labels; revChart.data.datasets[0].data = rev; revChart.update();
        occChart.data.labels = roomLabels; occChart.data.datasets[0].data = occCounts; occChart.update();
      } else {
        const revCtx = document.getElementById('revChart').getContext('2d');
        revChart = new Chart(revCtx, makeRevConfig(labels, rev));
        const occCtx = document.getElementById('occChart').getContext('2d');
        occChart = new Chart(occCtx, makeOccConfig(roomLabels, occCounts));
      }

      const t = document.getElementById('occTitle'); if (t) t.textContent = `Booked Nights by Room (last ${n} days)`;
      ['range7','range14','range30'].forEach(id => document.getElementById(id)?.classList.remove('active'));
      document.getElementById(n===7?'range7':(n===14?'range14':'range30'))?.classList.add('active');
    }

    document.getElementById('range7')?.addEventListener('click', ()=>render(7));
    document.getElementById('range14')?.addEventListener('click', ()=>render(14));
    document.getElementById('range30')?.addEventListener('click', ()=>render(30));

    render(30);

    // Resize charts on orientation / layout changes
    let resizeTO; const onResize=()=>{ clearTimeout(resizeTO); resizeTO=setTimeout(()=>{ revChart?.resize(); occChart?.resize(); }, 120); };
    window.addEventListener('resize', onResize);
    document.getElementById('btnSidebar')?.addEventListener('click', ()=> setTimeout(onResize, 260));
  })();
</script>
</body>
</html>
