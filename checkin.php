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

$room_id = (int)($_GET['room_id'] ?? 0);
$room = $mysqli->query("SELECT * FROM rooms WHERE id={$room_id}")->fetch_assoc();
if (!$room) { http_response_code(404); echo 'Room not found'; exit; }

// Find active booking if any
$bk = $mysqli->query("SELECT * FROM bookings WHERE room_id={$room_id} AND status='active' ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Load active extras catalog for dropdown (id, name, price)
$extras_catalog = $mysqli->query("SELECT id,name,price FROM extras WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$notice=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='checkin') {
    
  $guest_name = trim($_POST['guest_name'] ?? '');
  $gender = $_POST['gender'] ?? null;
  $nationality = trim($_POST['nationality'] ?? '');
  $contact = trim($_POST['contact'] ?? '');
  $paid = (float)($_POST['amount_paid'] ?? 0);
  $dt = $_POST['checkin_at'] ? date('Y-m-d H:i:s', strtotime($_POST['checkin_at'])) : date('Y-m-d H:i:s');
  if ($guest_name) {
      
    $rate_per_night = (float)$room['rate'];
    $stmt = $mysqli->prepare("INSERT INTO bookings(room_id,rate_per_night,guest_name,gender,nationality,contact,checkin_at) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param('idsssss',$room_id,$rate_per_night,$guest_name,$gender,$nationality,$contact,$dt);
    
    $stmt->execute();
    $booking_id = $stmt->insert_id;
    
    if ($paid>0) {
        
        $date = new DateTime('now', new DateTimeZone('UTC'));
        $created_at = $date->format('Y-m-d H:i:s');
        
      $p = $mysqli->prepare("INSERT INTO payments(booking_id,method,amount,note,created_at) VALUES(?,?,?,?,?)");
      $method = $_POST['method'] ?? 'cash'; $note='Check-in deposit';
      $p->bind_param('isdss',$booking_id,$method,$paid,$note,$created_at); $p->execute();
    }
    
    $mysqli->query("UPDATE rooms SET status='occupied' WHERE id={$room_id}");
    header("Location: checkin.php?room_id={$room_id}"); exit;
    
  } else { $error='Guest name is required'; }
}

// Add extra line from catalog
if ($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='add_extra' && $bk) {
  $extra_id = (int)($_POST['extra_id'] ?? 0);
  $qty = (float)($_POST['qty'] ?? 1);
  $price = (float)($_POST['unit_price'] ?? 0);
  if ($extra_id>0 && $qty>0) {
    // Resolve description from catalog
    $row = $mysqli->query("SELECT name FROM extras WHERE id={$extra_id}")->fetch_assoc();
    $desc = $row ? $row['name'] : 'Extra';
    $st=$mysqli->prepare("INSERT INTO booking_extras(booking_id,extra_id,description,qty,unit_price) VALUES(?,?,?,?,?)");
    $st->bind_param('iisdd',$bk['id'],$extra_id,$desc,$qty,$price);
    $st->execute();
    
    // Reduce stock when an extra is sold
$deduct = $mysqli->prepare("
  UPDATE extras
  SET stock_qty = GREATEST(0, stock_qty - ?)
  WHERE id = ?
");
$deduct->bind_param('di', $qty, $extra_id);
$deduct->execute();

// Optional: log stock movement
$mv = $mysqli->prepare("
  INSERT INTO stock_movements(extra_id, movement_type, qty, unit_cost, note, ref_type, ref_id, created_by)
  VALUES(?, 'out', ?, ?, ?, 'booking_extra', ?, ?)
");
$unit_cost_null = null; // if you don't track cost here
$note = 'Auto deduction from booking extra';
$booking_extra_ref_id = (int)$mysqli->insert_id; // call this immediately after booking_extras insert
$created_by = (int)($user['id'] ?? 0);
$mv->bind_param('iddsii', $extra_id, $qty, $unit_cost_null, $note, $booking_extra_ref_id, $created_by);
$mv->execute();
    
    $notice='Extra added';
  } else { $error='Please select an extra and enter a valid quantity'; }
}


// Get lines if occupied
$extras=[]; $paid_total=0.0; $nights=0; $room_total=0.0;
if ($bk) {
  $extras = $mysqli->query("SELECT * FROM booking_extras WHERE booking_id={$bk['id']} ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
  $paid_total = (float)($mysqli->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE booking_id={$bk['id']}")->fetch_assoc()['s']);
  // Nights so far
  $start = new DateTime($bk['checkin_at']);
  $end = new DateTime();
  $nights = compute_nights_noon_cutoff($start,$end); //max(1, (int)$start->diff($end)->days);
  $room_total = $nights * (float)($bk['rate_per_night'] ?? $room['rate']);
}
?>
<?php
// NOTE: This version focuses on responsive UI/UX only. It relies on your existing
// controller to provide: $room, $bk (active booking or null), $nights, $room_total,
// $paid_total, $extras_catalog (list), $extras (list), $error, $notice.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Check-In</title>
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

    /* Sidebar links on light UI */
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }

    /* Outline light buttons: softer contrast on light theme */
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }

    /* Mobile hamburger */
    .sidebar-toggle{ position:fixed; left:12px; top:12px; z-index:1051; display:none; }
    @media (max-width: 991.98px){ .sidebar-toggle{ display:inline-flex; } }

    /* Table responsiveness for extras log */
    @media (max-width: 575.98px){
      .tbl-hide-xs{ display:none; }
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
        <h3 class="m-0">Check-In — <?php echo htmlspecialchars($room['name']); ?> <span class="text-secondary fw-normal" style="font-size:.95rem;">(₵<?php echo number_format($room['rate'],2); ?>/night)</span></h3>
        <a class="btn btn-outline-light" href="bookings.php">Back to Rooms</a>
      </div>

      <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if (!empty($notice)): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

      <?php if (!$bk): ?>
        <!-- Check-in form (responsive grid) -->
        <div class="card p-3">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="checkin">

            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Guest Name</label>
              <input class="form-control" name="guest_name" required>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label">Gender</label>
              <select class="form-select" name="gender">
                <option>Male</option><option>Female</option><option>Other</option>
              </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label">Nationality</label>
              <input class="form-control" name="nationality">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label">Contact</label>
              <input class="form-control" name="contact">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Check-in Date/Time</label>
              <input type="datetime-local" class="form-control" name="checkin_at" value="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label">Amount Paid</label>
              <input type="number" step="0.01" min="0" class="form-control" name="amount_paid" value="0">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label">Method</label>
              <select class="form-select" name="method">
                <option value="cash">Cash</option>
                <option value="momo">MoMo</option>
                <option value="card">Card</option>
              </select>
            </div>
            <div class="col-12 col-lg-3 d-grid align-self-end">
              <button class="btn btn-primary">Confirm Check-In</button>
            </div>
          </form>
        </div>
      <?php else: ?>
        <!-- Occupied: summary + extras entry + log -->
        <div class="row g-3">
          <!-- Left: Booking Summary -->
          <div class="col-12 col-lg-6">
            <div class="card p-3 h-100">
              <h5 class="mb-3">Booking Summary</h5>
              <div class="row g-2 small">
                <div class="col-6"><strong>Guest:</strong> <?php echo htmlspecialchars($bk['guest_name']); ?></div>
                <div class="col-6"><strong>Checked In:</strong> <?php echo htmlspecialchars($bk['checkin_at']); ?></div>
                <div class="col-6"><strong>Nights:</strong> <?php echo (int)$nights; ?></div>
                <div class="col-6"><strong>Room Total:</strong> ₵<?php echo number_format($room_total,2); ?></div>
                <div class="col-6"><strong>Paid So Far:</strong> ₵<?php echo number_format($paid_total,2); ?></div>
              </div>
              <a class="btn btn-danger mt-3 w-100 w-sm-auto" href="checkout.php?booking_id=<?php echo (int)$bk['id']; ?>">Proceed to Check-Out</a>
            </div>
          </div>

          <!-- Right: Add Extras Form -->
          <div class="col-12 col-lg-6">
            <div class="card p-3 h-100">
              <h5 class="mb-3">Add Extra</h5>
              <?php if (!isset($extras_catalog) || empty($extras_catalog)): ?>
                <p class="text-secondary mb-2">No extras available yet.</p>
                <a class="btn btn-sm btn-primary" href="extras.php">Create Extras</a>
              <?php else: ?>
              <form method="post">
                <input type="hidden" name="action" value="add_extra">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-sm-6">
                    <label class="form-label">Extra</label>
                    <select class="form-select" name="extra_id" id="extra_id" required>
                      <option value="">Select extra…</option>
                      <?php foreach ($extras_catalog as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>" data-price="<?php echo htmlspecialchars(number_format((float)$e['price'],2,'.','')); ?>">
                          <?php echo htmlspecialchars($e['name']); ?> — ₵<?php echo number_format($e['price'],2); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-6 col-sm-2">
                    <label class="form-label">Qty</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="qty" id="qty" value="1">
                  </div>
                  <div class="col-6 col-sm-4">
                    <label class="form-label">Unit price</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="unit_price" id="unit_price" placeholder="Unit price">
                  </div>
                  <div class="col-12 d-grid">
                    <button class="btn btn-outline-light">Add</button>
                  </div>
                </div>
              </form>
              <script>
                (function(){
                  const sel = document.getElementById('extra_id');
                  const price = document.getElementById('unit_price');
                  if (sel && price) {
                    sel.addEventListener('change', function(){
                      const opt = sel.options[sel.selectedIndex];
                      if (opt && opt.dataset.price) { price.value = opt.dataset.price; }
                    });
                  }
                })();
              </script>
              <?php endif; ?>
            </div>
          </div>

          <!-- Full-width: Transaction Log (Extras) -->
          <div class="col-12 col-lg-12">
            <div class="card p-3">
              <h5 class="mb-3">Transaction Log (Extras)</h5>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Description</th>
                      <th class="text-end">Qty</th>
                      <th class="text-end tbl-hide-xs">Unit</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $extras_total = 0; foreach ($extras as $x): $line = $x['qty'] * $x['unit_price']; $extras_total += $line; ?>
                      <tr>
                        <td><?php echo htmlspecialchars($x['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($x['description'] ?: 'Extra'); ?></td>
                        <td class="text-end"><?php echo rtrim(rtrim(number_format($x['qty'],2), '0'),'.'); ?></td>
                        <td class="text-end tbl-hide-xs">₵<?php echo number_format($x['unit_price'],2); ?></td>
                        <td class="text-end">₵<?php echo number_format($line,2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="4" class="text-end">Extras Total</th>
                      <th class="text-end">₵<?php echo number_format($extras_total,2); ?></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>
    <?php include __DIR__.'/partials/footer.php'; ?>
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
