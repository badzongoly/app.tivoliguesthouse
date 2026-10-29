<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

if (($user['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo 'Access denied';
  exit;
}

function recovery_h($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function recovery_datetime(?string $value, bool $required = true): ?string
{
  $value = trim((string)$value);
  if ($value === '') {
    if ($required) {
      throw new InvalidArgumentException('A date and time is required.');
    }
    return null;
  }

  $date = DateTime::createFromFormat('Y-m-d\\TH:i', $value);
  $errors = DateTime::getLastErrors();
  if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
    throw new InvalidArgumentException('Enter a valid date and time.');
  }
  return $date->format('Y-m-d H:i:s');
}

function recovery_booking(mysqli $mysqli, int $booking_id): ?array
{
  $stmt = $mysqli->prepare('SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id=?');
  $stmt->bind_param('i', $booking_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc() ?: null;
}

function recovery_sync_room_status(mysqli $mysqli, int $room_id, int $booking_id, string $status): void
{
  $active_stmt = $mysqli->prepare("SELECT id FROM bookings WHERE room_id=? AND status='active' AND id<>? LIMIT 1");
  $active_stmt->bind_param('ii', $room_id, $booking_id);
  $active_stmt->execute();
  if ($active_stmt->get_result()->fetch_assoc()) {
    return;
  }

  $room_status = $status === 'active' ? 'occupied' : 'vacant';
  $room_update = $mysqli->prepare('UPDATE rooms SET status=? WHERE id=?');
  $room_update->bind_param('si', $room_status, $room_id);
  $room_update->execute();
}

$notice = '';
$error = '';
$selected_id = max(0, (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0));

try {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_booking') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $guest_name = trim($_POST['guest_name'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $nationality = trim($_POST['nationality'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $checkin_at = recovery_datetime($_POST['checkin_at'] ?? null);
    $checkout_at = recovery_datetime($_POST['checkout_at'] ?? null, false);

    if ($room_id <= 0 || $guest_name === '') {
      throw new InvalidArgumentException('Room and guest name are required.');
    }
    if ($checkout_at !== null && $checkout_at < $checkin_at) {
      throw new InvalidArgumentException('Check-out cannot be before check-in.');
    }
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
      $gender = null;
    }

    $room_stmt = $mysqli->prepare('SELECT rate FROM rooms WHERE id=?');
    $room_stmt->bind_param('i', $room_id);
    $room_stmt->execute();
    $room = $room_stmt->get_result()->fetch_assoc();
    if (!$room) {
      throw new InvalidArgumentException('Selected room was not found.');
    }

    $rate_per_night = (float)$room['rate'];
    $status = $checkout_at === null ? 'active' : 'checked_out';
    $stmt = $mysqli->prepare('INSERT INTO bookings(room_id,rate_per_night,guest_name,gender,nationality,contact,checkin_at,checkout_at,status) VALUES(?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('idsssssss', $room_id, $rate_per_night, $guest_name, $gender, $nationality, $contact, $checkin_at, $checkout_at, $status);
    $stmt->execute();
    $selected_id = $stmt->insert_id;

    recovery_sync_room_status($mysqli, $room_id, $selected_id, $status);
    $notice = 'Historical booking created.';
  }

  if ($action === 'update_stay') {
    $selected_id = (int)($_POST['booking_id'] ?? 0);
    $booking = recovery_booking($mysqli, $selected_id);
    if (!$booking) {
      throw new InvalidArgumentException('Booking was not found.');
    }

    $checkin_at = recovery_datetime($_POST['checkin_at'] ?? null);
    $checkout_at = recovery_datetime($_POST['checkout_at'] ?? null, false);
    if ($checkout_at !== null && $checkout_at < $checkin_at) {
      throw new InvalidArgumentException('Check-out cannot be before check-in.');
    }

    $status = $checkout_at === null ? 'active' : 'checked_out';
    $stmt = $mysqli->prepare('UPDATE bookings SET checkin_at=?, checkout_at=?, status=? WHERE id=?');
    $stmt->bind_param('sssi', $checkin_at, $checkout_at, $status, $selected_id);
    $stmt->execute();

    recovery_sync_room_status($mysqli, (int)$booking['room_id'], $selected_id, $status);
    $notice = 'Booking dates updated.';
  }

  if ($action === 'add_payment') {
    $selected_id = (int)($_POST['booking_id'] ?? 0);
    if (!recovery_booking($mysqli, $selected_id)) {
      throw new InvalidArgumentException('Booking was not found.');
    }
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'cash';
    $created_at = recovery_datetime($_POST['created_at'] ?? null);
    $note = trim($_POST['note'] ?? 'Historical payment');
    if ($amount <= 0 || !in_array($method, ['cash', 'momo', 'card'], true)) {
      throw new InvalidArgumentException('Enter a valid payment amount and method.');
    }

    $stmt = $mysqli->prepare('INSERT INTO payments(booking_id,method,amount,note,created_at) VALUES(?,?,?,?,?)');
    $stmt->bind_param('isdss', $selected_id, $method, $amount, $note, $created_at);
    $stmt->execute();
    $notice = 'Historical payment recorded.';
  }

  if ($action === 'add_extra') {
    $selected_id = (int)($_POST['booking_id'] ?? 0);
    if (!recovery_booking($mysqli, $selected_id)) {
      throw new InvalidArgumentException('Booking was not found.');
    }
    $extra_id = (int)($_POST['extra_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 0);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $created_at = recovery_datetime($_POST['created_at'] ?? null);
    if ($extra_id <= 0 || $qty <= 0 || $unit_price < 0) {
      throw new InvalidArgumentException('Enter a valid extra, quantity, and unit price.');
    }

    $extra_stmt = $mysqli->prepare('SELECT name FROM extras WHERE id=?');
    $extra_stmt->bind_param('i', $extra_id);
    $extra_stmt->execute();
    $extra = $extra_stmt->get_result()->fetch_assoc();
    if (!$extra) {
      throw new InvalidArgumentException('Extra was not found.');
    }

    $mysqli->begin_transaction();
    try {
      $description = $extra['name'];
      $stmt = $mysqli->prepare('INSERT INTO booking_extras(booking_id,extra_id,description,qty,unit_price,created_at) VALUES(?,?,?,?,?,?)');
      $stmt->bind_param('iisdds', $selected_id, $extra_id, $description, $qty, $unit_price, $created_at);
      $stmt->execute();
      $booking_extra_id = $stmt->insert_id;

      $deduct = $mysqli->prepare('UPDATE extras SET stock_qty=GREATEST(0, stock_qty-?) WHERE id=?');
      $deduct->bind_param('di', $qty, $extra_id);
      $deduct->execute();

      $movement_type = 'out';
      $note = 'Historical booking extra';
      $ref_type = 'booking_extra';
      $created_by = (int)($user['id'] ?? 0);
      $movement = $mysqli->prepare('INSERT INTO stock_movements(extra_id,movement_type,qty,unit_cost,note,ref_type,ref_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)');
      $unit_cost = null;
      $movement->bind_param('isddssiis', $extra_id, $movement_type, $qty, $unit_cost, $note, $ref_type, $booking_extra_id, $created_by, $created_at);
      $movement->execute();
      $mysqli->commit();
    } catch (Throwable $transaction_error) {
      $mysqli->rollback();
      throw $transaction_error;
    }
    $notice = 'Historical extra recorded.';
  }
} catch (Throwable $exception) {
  $error = $exception->getMessage();
}

$rooms = $mysqli->query('SELECT id,name,type,rate,status FROM rooms ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$extras = $mysqli->query('SELECT id,name,price FROM extras WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$bookings = $mysqli->query("SELECT b.id,b.guest_name,b.checkin_at,b.checkout_at,b.status,r.name AS room_name FROM bookings b JOIN rooms r ON r.id=b.room_id ORDER BY COALESCE(b.checkout_at,b.checkin_at) DESC, b.id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$booking = $selected_id > 0 ? recovery_booking($mysqli, $selected_id) : null;
$payment_total = 0.0;
$extras_total = 0.0;
if ($booking) {
  $payment_stmt = $mysqli->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE booking_id=?');
  $payment_stmt->bind_param('i', $selected_id);
  $payment_stmt->execute();
  $payment_total = (float)$payment_stmt->get_result()->fetch_assoc()['total'];
  $extra_stmt = $mysqli->prepare('SELECT COALESCE(SUM(qty*unit_price),0) AS total FROM booking_extras WHERE booking_id=?');
  $extra_stmt->bind_param('i', $selected_id);
  $extra_stmt->execute();
  $extras_total = (float)$extra_stmt->get_result()->fetch_assoc()['total'];
}
$now_local = date('Y-m-d\\TH:i');
$default_checkin = $booking ? date('Y-m-d\\TH:i', strtotime($booking['checkin_at'])) : $now_local;
$default_checkout = $booking && $booking['checkout_at'] ? date('Y-m-d\\TH:i', strtotime($booking['checkout_at'])) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Historical Recovery</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{--bg-page:#f7f8fb;--bg-surface:#fff;--line:#e5e7eb;--text:#0f172a;--sidebar-w:260px}
    body{background:var(--bg-page);color:var(--text)}
    .sidebar{width:var(--sidebar-w);min-height:100vh;background:#f8fafc;position:fixed;left:0;top:0;padding:1rem;border-right:1px solid var(--line);z-index:1040}
    .content{margin-left:var(--sidebar-w)}
    .navtop{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:1000}
    .card{background:var(--bg-surface);border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 1px rgba(0,0,0,.02),0 3px 6px rgba(0,0,0,.04)}
    .sidebar .list-group-item{background:transparent;color:var(--text)!important;border:0}.sidebar .list-group-item:hover{background:#eef2f7}
    .btn-outline-light{color:#334155;border-color:#cbd5e1}.btn-outline-light:hover{background:#f1f5f9;border-color:#94a3b8}
    .sidebar-toggle{position:fixed;left:12px;top:12px;z-index:1051;display:none}
    @media(max-width:991.98px){.sidebar{transform:translateX(-100%);transition:transform .25s ease}.sidebar-open .sidebar{transform:translateX(0)}.content{margin-left:0}.sidebar-toggle{display:inline-flex}}
  </style>
</head>
<body>
  <?php include __DIR__.'/partials/sidebar.php'; ?>
  <button class="btn btn-outline-primary sidebar-toggle" id="btnSidebar" aria-label="Toggle navigation">☰</button>
  <div class="content">
    <?php include __DIR__.'/partials/navtop.php'; ?>
    <main class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div><h3 class="m-0">Historical Recovery</h3><div class="text-secondary">Admin-only backdated booking, payment, and extra records</div></div>
        <a class="btn btn-outline-light" href="bookings.php">Back to Bookings</a>
      </div>
      <?php if ($error): ?><div class="alert alert-danger"><?php echo recovery_h($error); ?></div><?php endif; ?>
      <?php if ($notice): ?><div class="alert alert-success"><?php echo recovery_h($notice); ?></div><?php endif; ?>

      <div class="card p-3 mb-3">
        <label class="form-label fw-semibold" for="booking_id">Select an existing booking</label>
        <select class="form-select" id="booking_id" onchange="if(this.value) window.location='admin_recovery.php?booking_id='+this.value;">
          <option value="">Create a missing booking below</option>
          <?php foreach ($bookings as $item): ?><option value="<?php echo (int)$item['id']; ?>" <?php echo $selected_id === (int)$item['id'] ? 'selected' : ''; ?>>#<?php echo (int)$item['id']; ?> — <?php echo recovery_h($item['guest_name']); ?> — <?php echo recovery_h($item['room_name']); ?> — <?php echo recovery_h($item['checkin_at']); ?> (<?php echo recovery_h($item['status']); ?>)</option><?php endforeach; ?>
        </select>
      </div>

      <?php if (!$booking): ?>
      <div class="card p-3 mb-3">
        <h5 class="mb-3">Create Missing Historical Booking</h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="create_booking">
          <div class="col-12 col-md-6"><label class="form-label">Room</label><select class="form-select" name="room_id" required><option value="">Select room</option><?php foreach ($rooms as $room): ?><option value="<?php echo (int)$room['id']; ?>"><?php echo recovery_h($room['name']); ?> — <?php echo recovery_h($room['type']); ?> — ₵<?php echo number_format((float)$room['rate'],2); ?></option><?php endforeach; ?></select></div>
          <div class="col-12 col-md-6"><label class="form-label">Guest name</label><input class="form-control" name="guest_name" required></div>
          <div class="col-6 col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
          <div class="col-6 col-md-3"><label class="form-label">Nationality</label><input class="form-control" name="nationality"></div>
          <div class="col-12 col-md-6"><label class="form-label">Contact</label><input class="form-control" name="contact"></div>
          <div class="col-12 col-md-6"><label class="form-label">Check-in date/time</label><input type="datetime-local" class="form-control" name="checkin_at" required value="<?php echo recovery_h($now_local); ?>"></div>
          <div class="col-12 col-md-6"><label class="form-label">Check-out date/time <span class="text-secondary">(optional)</span></label><input type="datetime-local" class="form-control" name="checkout_at"></div>
          <div class="col-12"><button class="btn btn-primary">Create Historical Booking</button></div>
        </form>
      </div>
      <?php else: ?>
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h5 class="mb-1">Booking #<?php echo (int)$booking['id']; ?> — <?php echo recovery_h($booking['guest_name']); ?></h5><div class="text-secondary"><?php echo recovery_h($booking['room_name']); ?> · Rate snapshot ₵<?php echo number_format((float)$booking['rate_per_night'],2); ?>/night</div></div><span class="badge text-bg-secondary"><?php echo recovery_h($booking['status']); ?></span></div>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="update_stay"><input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
          <div class="col-12 col-md-6"><label class="form-label">Check-in date/time</label><input type="datetime-local" class="form-control" name="checkin_at" required value="<?php echo recovery_h($default_checkin); ?>"></div>
          <div class="col-12 col-md-6"><label class="form-label">Check-out date/time <span class="text-secondary">(blank keeps booking active)</span></label><input type="datetime-local" class="form-control" name="checkout_at" value="<?php echo recovery_h($default_checkout); ?>"></div>
          <div class="col-12"><button class="btn btn-primary">Save Check-In / Check-Out</button></div>
        </form>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6"><div class="card p-3 h-100"><h5 class="mb-3">Record Payment</h5><form method="post" class="row g-2"><input type="hidden" name="action" value="add_payment"><input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>"><div class="col-6"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" name="amount" required></div><div class="col-6"><label class="form-label">Method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="momo">MoMo</option><option value="card">Card</option></select></div><div class="col-12"><label class="form-label">Payment date/time</label><input type="datetime-local" class="form-control" name="created_at" required value="<?php echo recovery_h($now_local); ?>"></div><div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note" value="Historical payment"></div><div class="col-12 d-grid"><button class="btn btn-primary">Record Payment</button></div></form></div></div>
        <div class="col-12 col-lg-6"><div class="card p-3 h-100"><h5 class="mb-3">Record Extra</h5><form method="post" class="row g-2"><input type="hidden" name="action" value="add_extra"><input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>"><div class="col-12"><label class="form-label">Extra</label><select class="form-select" name="extra_id" id="extra_id" required><option value="">Select extra</option><?php foreach ($extras as $extra): ?><option value="<?php echo (int)$extra['id']; ?>" data-price="<?php echo recovery_h(number_format((float)$extra['price'],2,'.','')); ?>"><?php echo recovery_h($extra['name']); ?> — ₵<?php echo number_format((float)$extra['price'],2); ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0.01" class="form-control" name="qty" value="1" required></div><div class="col-6"><label class="form-label">Unit price</label><input type="number" step="0.01" min="0" class="form-control" name="unit_price" id="unit_price" required></div><div class="col-12"><label class="form-label">Extra date/time</label><input type="datetime-local" class="form-control" name="created_at" required value="<?php echo recovery_h($now_local); ?>"></div><div class="col-12 d-grid"><button class="btn btn-primary">Record Extra</button></div></form></div></div>
      </div>
      <div class="card p-3 mt-3"><div class="d-flex justify-content-between"><span>Recorded payments</span><strong>₵<?php echo number_format($payment_total,2); ?></strong><span>Recorded extras</span><strong>₵<?php echo number_format($extras_total,2); ?></strong></div></div>
      <?php endif; ?>
    </main>
    <?php include __DIR__.'/partials/footer.php'; ?>
  </div>
  <script>
    document.getElementById('btnSidebar')?.addEventListener('click', function(){ document.body.classList.toggle('sidebar-open'); });
    document.getElementById('extra_id')?.addEventListener('change', function(){ const option=this.options[this.selectedIndex]; document.getElementById('unit_price').value=option.dataset.price || ''; });
  </script>
</body>
</html>
