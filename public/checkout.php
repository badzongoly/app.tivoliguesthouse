<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/app.php';

/**
 * Compute nights stayed using a noon cutoff.
 *
 * Rules:
 * 1) Any minute after check-in up to 12:00 pm the following day counts as 1 night.
 * 2) If checkout is 12:01 pm the following day (or later past any noon), add another night.
 *
 * @param DateTimeInterface $checkin  Check-in timestamp (UTC recommended)
 * @param DateTimeInterface $checkout Checkout timestamp (UTC recommended)
 * @return int Nights (minimum 1)
 */
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


$booking_id = (int)($_GET['booking_id'] ?? 0);
$bk = $mysqli->query("SELECT b.*, r.name room_name, COALESCE(b.rate_per_night, r.rate) AS rate FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id={$booking_id}")->fetch_assoc();
if (!$bk) { http_response_code(404); echo 'Booking not found'; exit; }

// Compute bill
$start = new DateTime($bk['checkin_at']);
$end = new DateTime();

//$nights =  max(1, (int)$start->diff($end)->days);
$nights = compute_nights_noon_cutoff($start, $end);

$room_total = $nights * (float)$bk['rate'];
$extras_total = (float)($mysqli->query("SELECT COALESCE(SUM(qty*unit_price),0) s FROM booking_extras WHERE booking_id={$booking_id}")->fetch_assoc()['s']);
$paid_total = (float)($mysqli->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE booking_id={$booking_id}")->fetch_assoc()['s']);
$grand_total = $room_total + $extras_total;
$balance = max(0, $grand_total - $paid_total);

$notice=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='pay') {
  $amount = (float)($_POST['amount'] ?? 0);
  $method = $_POST['method'] ?? 'cash';
  
  if ($amount>0) {

// Set timezone to UTC
$date = new DateTime('now', new DateTimeZone('UTC'));
$created_at = $date->format('Y-m-d H:i:s');

$note = 'Checkout payment';

// Always escape input to prevent SQL injection
$booking_id = (int)$booking_id; // integer cast
$method = $mysqli->real_escape_string($method);
$amount = (float)$amount;
$note = $mysqli->real_escape_string($note);
$created_at = $mysqli->real_escape_string($created_at);

// Build SQL string
 $sql = "
    INSERT INTO payments (booking_id, method, amount, note, created_at)
    VALUES ($booking_id, '$method', $amount, '$note', '$created_at')
";

// Execute query
if ($mysqli->query($sql)) {
   header("Location: checkout.php?booking_id={$booking_id}"); exit;
} else {
    echo "Error: " . $mysqli->error;
}
    
  } else { $error='Enter a valid amount'; }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='confirm_checkout') {
  if ($balance>0) { $error='Please settle outstanding balance before checkout.'; }
  else {
    $mysqli->query("UPDATE bookings SET status='checked_out', checkout_at=NOW() WHERE id={$booking_id}");
    $mysqli->query("UPDATE rooms SET status='vacant' WHERE id=".(int)$bk['room_id']);
    $notice='Checkout completed. Room freed.';
  }
}

$date = new DateTime('now', new DateTimeZone('UTC'));
$created_at = $date->format('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Check-Out</title>
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
    .list-group-item { background:transparent; border-color:var(--line); }
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }

    /* Mobile hamburger */
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }

    /* Compact spacing for form rows on small screens */
    .form-label{ font-size:.95rem; }
  </style>
<?php require dirname(__DIR__).'/templates/layouts/head.php'; ?>
</head>
<body class="app-page">
  <?php include dirname(__DIR__).'/templates/partials/sidebar.php'; ?>

  <!-- Mobile sidebar toggle -->
  

  <div class="content">
    <?php include dirname(__DIR__).'/templates/partials/navtop.php'; ?>
    <main id="workspace-main" tabindex="-1" class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="m-0">Check-Out — Room <?php echo htmlspecialchars($bk['room_name']); ?></h3>
        <a class="btn btn-outline-light" href="bookings.php">Back to Rooms</a>
      </div>

      <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if (!empty($notice)): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

      <div class="row g-3 align-items-stretch">
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <h5 class="mb-2">Bill Summary</h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center"><span>Nights (<?php echo (int)$nights; ?>) × ₵<?php echo number_format($bk['rate'],2); ?></span><strong>₵<?php echo number_format($room_total,2); ?></strong></li>
              <li class="list-group-item d-flex justify-content-between align-items-center"><span>Extras Total</span><strong>₵<?php echo number_format($extras_total,2); ?></strong></li>
              <li class="list-group-item d-flex justify-content-between align-items-center"><span>Grand Total</span><strong>₵<?php echo number_format($grand_total,2); ?></strong></li>
              <li class="list-group-item d-flex justify-content-between align-items-center"><span>Paid</span><strong>₵<?php echo number_format($paid_total,2); ?></strong></li>
              <li class="list-group-item d-flex justify-content-between align-items-center"><span>Balance</span><strong class="<?php echo $balance>0?'text-danger':'text-success'; ?>">₵<?php echo number_format($balance,2); ?></strong></li>
            </ul>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card p-3 h-100">
            <h5 class="mb-2">Take Payment</h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="pay">
              <div class="col-12 col-sm-6">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" name="amount" value="<?php echo number_format($balance,2,'.',''); ?>">
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label">Method</label>
                <select class="form-select" name="method">
                  <option value="cash">Cash</option>
                  <option value="momo">MoMo</option>
                  <option value="card">Card</option>
                </select>
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-outline-light">Record Payment</button>
              </div>
            </form>
          </div>
          <div class="card p-3 mt-3">
            <h5 class="mb-2">Finalize Checkout</h5>
            <form method="post" class="d-flex flex-wrap gap-2">
              <input type="hidden" name="action" value="confirm_checkout">
              <button class="btn btn-danger flex-grow-1" <?php echo $balance>0?'disabled':''; ?>>Confirm & Free Room</button>
            </form>
            <?php if ($balance>0): ?>
              <div class="small text-secondary mt-2">Settle outstanding balance to enable checkout.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
    <?php include dirname(__DIR__).'/templates/partials/footer.php'; ?>
  </div>

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
    })();
  </script>
</body>
</html>