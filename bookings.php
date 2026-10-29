<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

function compute_nights_noon_cutoff(DateTimeInterface $checkin, DateTimeInterface $checkout): int
{
    // Guard: never less than 1 night
    if ($checkout <= $checkin) {
        return 1;
    }

    // Night 1 ends at *following day* 12:00 pm (noon), regardless of check-in time.
    // Important: always move to next day noon, not "same day if before noon".
    $firstNoon = (new DateTime($checkin->format('Y-m-d H:i:s'), $checkin->getTimezone()))
        ->setTime(12, 0, 0)
        ->modify('+1 day');

    // If the guest leaves on/before the first noon boundary → exactly 1 night
    if ($checkout <= $firstNoon) {
        return 1;
    }

    // Each noon boundary (24h from firstNoon) crossed adds a night.
    // Example: 12:00:00 exact returns ceil(0) = 0 → still 1 night (already handled above).
    $seconds = $checkout->getTimestamp() - $firstNoon->getTimestamp();
    $extraNights = (int)ceil($seconds / 86400); // 86400 sec = 24h
    return 1 + max(0, $extraNights);
}

$rs = $mysqli->query("SELECT * FROM rooms ORDER BY name");
$rooms = $rs->fetch_all(MYSQLI_ASSOC);

// Helper to get active booking id per room
$activeStmt = $mysqli->prepare("SELECT id FROM bookings WHERE room_id=? AND status='active' ORDER BY id DESC LIMIT 1");

// Filters for recent bookings
$filter_room   = (int)($_GET['room_id'] ?? 0);
$filter_status = $_GET['status'] ?? 'all'; // 'all' | 'checked_out' | 'cancelled'
$filter_start  = trim($_GET['start'] ?? ''); // YYYY-MM-DD
$filter_end    = trim($_GET['end'] ?? '');   // YYYY-MM-DD

/*$sql = "SELECT b.*, r.name AS room_name, r.rate
        FROM bookings b
        JOIN rooms r ON r.id=b.room_id
        WHERE b.status!='active'";*/
        
$sql ="SELECT b.*, r.name AS room_name, COALESCE(b.rate_per_night, r.rate) AS booking_rate
        FROM bookings b
        JOIN rooms r ON r.id=b.room_id
        WHERE b.status!='active'";
        
$types = '';
$params = [];

if ($filter_room > 0) { $sql .= " AND b.room_id=?"; $types.='i'; $params[]=$filter_room; }
if (in_array($filter_status, ['checked_out','cancelled'], true)) { $sql .= " AND b.status=?"; $types.='s'; $params[]=$filter_status; }
if ($filter_start) { $sql .= " AND COALESCE(b.checkout_at,b.checkin_at) >= ?"; $types.='s'; $params[] = date('Y-m-d 00:00:00', strtotime($filter_start)); }
if ($filter_end)   { $sql .= " AND COALESCE(b.checkout_at,b.checkin_at) <= ?"; $types.='s'; $params[] = date('Y-m-d 23:59:59', strtotime($filter_end)); }

$sql .= " ORDER BY COALESCE(b.checkout_at, b.checkin_at) DESC LIMIT 100";
$st = $mysqli->prepare($sql);
if ($types) { $st->bind_param($types, ...$params); }
$st->execute();
$recent = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// CSV export
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="bookings_export.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Check-In','Check-Out','Room','Guest','Nights','Rate/Night','Room Total','Extras','Grand Total','Paid','Status']);
  foreach ($recent as $b) {
    $start = new DateTime($b['checkin_at']);
    $end = new DateTime($b['checkout_at'] ?: 'now');
    $nights = max(1, (int)$start->diff($end)->days);
    $extras_total = (float)($mysqli->query("SELECT COALESCE(SUM(qty*unit_price),0) s FROM booking_extras WHERE booking_id=".(int)$b['id'])->fetch_assoc()['s']);
    //$room_total = $nights * (float)$b['rate'];
    $room_total = $nights * (float)$b['booking_rate'];
    $grand_total = $room_total + $extras_total;
    $paid_total = (float)($mysqli->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE booking_id=".(int)$b['id'])->fetch_assoc()['s']);
    fputcsv($out, [
      $b['checkin_at'],
      $b['checkout_at'],
      $b['room_name'],
      $b['guest_name'],
      $nights,
      number_format((float)$b['booking_rate'],2,'.',''),
      number_format($room_total,2,'.',''),
      number_format($extras_total,2,'.',''),
      number_format($grand_total,2,'.',''),
      number_format($paid_total,2,'.',''),
      $b['status']
    ]);
  }
  fclose($out);
  exit;
}

// Build querystring for CSV link (preserve filters)
$qs = $_GET; unset($qs['export']); $qs_str = http_build_query($qs);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Bookings</title>
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
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="m-0">Rooms</h3>
        <a class="btn btn-outline-light" href="dashboard.php">Dashboard</a>
      </div>

      <div class="row g-3">
        <?php foreach ($rooms as $r): ?>
          <?php
            $activeStmt->bind_param('i', $r['id']);
            $activeStmt->execute();
            $bk = $activeStmt->get_result()->fetch_assoc();
            $booking_id = $bk['id'] ?? null;
          ?>
          <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="card p-3 h-100 room-card">
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="min-w-0">
                  <div class="h5 mb-0 text-truncate" title="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['name']); ?></div>
                  <div class="text-secondary small text-truncate" title="<?php echo htmlspecialchars($r['type']); ?> — ₵<?php echo number_format($r['rate'],2); ?>"><?php echo htmlspecialchars($r['type']); ?> — ₵<?php echo number_format($r['rate'],2); ?></div>
                </div>
                <span class="badge <?php echo $r['status']==='occupied'?'bg-danger':'bg-success'; ?> flex-shrink-0"><?php echo $r['status']; ?></span>
              </div>
              <div class="mt-3 d-flex gap-2">
                <?php if ($r['status']==='vacant'): ?>
                  <a class="btn btn-primary w-50" href="checkin.php?room_id=<?php echo $r['id']; ?>">Check-In</a>
                <?php else: ?>
                  <a class="btn btn-outline-light w-50" href="checkin.php?room_id=<?php echo $r['id']; ?>">View</a>
                  <?php if ($booking_id): ?>
                    <a class="btn btn-danger w-50" href="checkout.php?booking_id=<?php echo $booking_id; ?>">Check-Out</a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <hr class="my-4">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h4 class="m-0">Recent Bookings</h4>
        <span class="text-secondary small">Last 100 non-active bookings</span>
      </div>

      <!-- Filters -->
      <form method="get" class="card p-3 mb-3 filter-form">
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
              <?php foreach ($rooms as $rr): ?>
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
        <div class="mt-2 d-flex justify-content-end">
          <a class="btn btn-outline-light" href="?<?php echo $qs_str ? $qs_str.'&' : ''; ?>export=csv">Export CSV</a>
        </div>
      </form>

      <div class="card p-3">
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Check-In</th>
                <th class="tbl-mobile-hide">Check-Out</th>
                <th>Room</th>
                <th class="tbl-mobile-hide">Guest</th>
                <th class="text-end">Nights</th>
                 <th class="text-end">Room Only</th>
                <th class="text-end tbl-mobile-hide">Room + Extra ₵</th>
                <th class="text-end">Paid ₵</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $b): ?>
                <?php
                  $start = new DateTime($b['checkin_at']);
                  $end = new DateTime($b['checkout_at'] ?: 'now');
                  $nights = compute_nights_noon_cutoff($start,$end);
                  $extras_total = (float)($mysqli->query("SELECT COALESCE(SUM(qty*unit_price),0) s FROM booking_extras WHERE booking_id=".(int)$b['id'])->fetch_assoc()['s']);
                  $room_total = $nights * (float)$b['booking_rate'];
                  $grand_total = $room_total + $extras_total;
                  $paid_total = (float)($mysqli->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE booking_id=".(int)$b['id'])->fetch_assoc()['s']);
                ?>
                <tr>
                  <td><?php $__dt = DateTime::createFromFormat('Y-m-d H:i:s', $b['checkin_at']); 
echo $__dt ? $__dt->format('m/d H:i') : htmlspecialchars($b['checkin_at']); ?>
                      
                      <?php //echo htmlspecialchars($b['checkin_at']); ?></td>
                  <td class="tbl-mobile-hide">
                      <?php //echo htmlspecialchars($b['checkout_at'] ?? '-'); ?>
                      <?php $__dt = DateTime::createFromFormat('Y-m-d H:i:s', $b['checkout_at']); 
echo $__dt ? $__dt->format('m/d H:i') : htmlspecialchars($b['checkout_at']); ?></td>
                  <td><?php echo htmlspecialchars($b['room_name']); ?></td>
                  <td class="tbl-mobile-hide"><?php echo htmlspecialchars($b['guest_name']); ?></td>
                  <td class="text-end"><?php echo $nights; ?></td>
                  <td class="text-end"><?php echo $room_total; ?></td>
                  <td class="text-end tbl-mobile-hide">₵<?php echo number_format($grand_total,2); ?></td>
                  <td class="text-end">₵<?php echo number_format($paid_total,2); ?></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#bookingModal"
                      data-bid="<?php echo (int)$b['id']; ?>"
                      data-guest="<?php echo htmlspecialchars($b['guest_name'], ENT_QUOTES); ?>"
                      data-room="<?php echo htmlspecialchars($b['room_name'], ENT_QUOTES); ?>"
                      data-checkin="<?php echo htmlspecialchars($b['checkin_at'], ENT_QUOTES); ?>"
                      data-checkout="<?php echo htmlspecialchars($b['checkout_at'] ?? '-', ENT_QUOTES); ?>"
                      data-nights="<?php echo (int)$nights; ?>"
                      data-rate="<?php echo number_format((float)$b['booking_rate'],2,'.',''); ?>"
                      data-roomtotal="<?php echo number_format($room_total,2,'.',''); ?>"
                      data-extras="<?php echo number_format($extras_total,2,'.',''); ?>"
                      data-paid="<?php echo number_format($paid_total,2,'.',''); ?>"
                      data-total="<?php echo number_format($grand_total,2,'.',''); ?>">
                      View
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Booking Detail Modal -->
      <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Booking Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <div class="card p-3 h-100">
                    <div><strong>Guest:</strong> <span id="v-guest"></span></div>
                    <div><strong>Room:</strong> <span id="v-room"></span></div>
                    <div><strong>Check-In:</strong> <span id="v-checkin"></span></div>
                    <div><strong>Check-Out:</strong> <span id="v-checkout"></span></div>
                    <div><strong>Nights:</strong> <span id="v-nights"></span></div>
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <div class="card p-3 h-100">
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Rate / Night</span><strong>₵<span id="v-rate"></span></strong></li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Room Total</span><strong>₵<span id="v-roomtotal"></span></strong></li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Extras</span><strong>₵<span id="v-extras"></span></strong></li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Grand Total</span><strong>₵<span id="v-total"></span></strong></li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Paid</span><strong>₵<span id="v-paid"></span></strong></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

      <?php include __DIR__.'/partials/footer.php'; ?>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

      // Modal population
      const modalEl = document.getElementById('bookingModal');
      if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function (event) {
          const btn = event.relatedTarget;
          const set = (id, val) => { const el = modalEl.querySelector('#'+id); if (el) el.textContent = val; };
          set('v-guest', btn.getAttribute('data-guest'));
          set('v-room', btn.getAttribute('data-room'));
          set('v-checkin', btn.getAttribute('data-checkin'));
          set('v-checkout', btn.getAttribute('data-checkout'));
          set('v-nights', btn.getAttribute('data-nights'));
          set('v-rate', btn.getAttribute('data-rate'));
          set('v-roomtotal', btn.getAttribute('data-roomtotal'));
          set('v-extras', btn.getAttribute('data-extras'));
          set('v-total', btn.getAttribute('data-total'));
          set('v-paid', btn.getAttribute('data-paid'));
        });
      }
    })();
  </script>
</body>
</html>
