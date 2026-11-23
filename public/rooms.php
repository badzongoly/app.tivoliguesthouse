<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/app.php';

// Create / Update
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $type = trim($_POST['type'] ?? 'Standard');
  $rate = (float)($_POST['rate'] ?? 0);
  if ($name) {
    if ($id>0) {
      $st=$mysqli->prepare('UPDATE rooms SET name=?, type=?, rate=? WHERE id=?');
      $st->bind_param('ssdi',$name,$type,$rate,$id); $st->execute();
    } else {
      $st=$mysqli->prepare('INSERT INTO rooms(name,type,rate) VALUES(?,?,?)');
      $st->bind_param('ssd',$name,$type,$rate); $st->execute();
    }
  }
  header('Location: rooms.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
  $rid = (int)$_GET['delete'];
  $mysqli->query('DELETE FROM rooms WHERE id='.$rid);
  header('Location: rooms.php'); exit;
}

$rooms = $mysqli->query('SELECT * FROM rooms ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$edit=null; if (isset($_GET['edit'])) { $id=(int)$_GET['edit']; $edit=$mysqli->query('SELECT * FROM rooms WHERE id='.$id)->fetch_assoc(); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Rooms</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{
      --bg-page:#f7f8fb;          /* soft light background */
      --bg-surface:#ffffff;       /* card/nav surfaces */
      --line:#e5e7eb;             /* gentle borders */
      --text:#0f172a;             /* slate-900 */
      --muted:#475569;            /* slate-600 */
    }
    body { background:var(--bg-page); color:var(--text); }
    .sidebar { width:260px; min-height:100vh; background:#f8fafc; position:fixed; left:0; top:0; padding:1rem; border-right:1px solid var(--line); color:var(--text); }
    .content { margin-left:260px; }
    .navtop { background:#ffffff; border-bottom:1px solid var(--line); }
    .navtop.navbar {
      --bs-navbar-color:#334155;                 /* link text */
      --bs-navbar-hover-color:#0f172a;           /* hover text */
      --bs-navbar-brand-color:#0f172a;           /* brand */
      --bs-navbar-brand-hover-color:#0f172a;     /* brand hover */
    }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    a { text-decoration:none; }

    /* Make sidebar links readable on light theme, overriding any text-white classes */
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }

    /* Outline light buttons are hard to read on light UI; restyle them gently */
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }
  </style>
<?php require dirname(__DIR__).'/templates/layouts/head.php'; ?>
</head>
<body class="app-page">
  <?php include dirname(__DIR__).'/templates/partials/sidebar.php'; ?>
  <div class="content">
    <?php include dirname(__DIR__).'/templates/partials/navtop.php'; ?>
    <main id="workspace-main" tabindex="-1" class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Rooms</h3>
        <a class="btn btn-outline-light" href="bookings.php">Back to Bookings</a>
      </div>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card p-3">
            <h5 class="mb-3"><?php echo $edit?'Edit Room':'Add Room'; ?></h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
              <div class="col-6">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" required>
              </div>
              <div class="col-3">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                  <?php $types=['Standard','Double','Queen','King','Suite']; foreach($types as $t): ?>
                    <option <?php echo (($edit['type'] ?? '')===$t)?'selected':''; ?>><?php echo $t; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3">
                <label class="form-label">Rate (₵)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="rate" value="<?php echo htmlspecialchars($edit['rate'] ?? '0'); ?>">
              </div>
              <div class="col-12 d-grid"><button class="btn btn-primary"><?php echo $edit?'Save Changes':'Add Room'; ?></button></div>
            </form>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card p-3">
            <h5 class="mb-3">All Rooms</h5>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Name</th><th>Type</th><th class="text-end">Rate</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($rooms as $r): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['name']); ?></td>
                      <td><?php echo htmlspecialchars($r['type']); ?></td>
                      <td class="text-end">₵<?php echo number_format($r['rate'],2); ?></td>
                      <td><span class="badge <?php echo $r['status']==='occupied'?'bg-danger':'bg-success'; ?>"><?php echo $r['status']; ?></span></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-light" href="?edit=<?php echo $r['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo $r['id']; ?>" data-confirm="Delete this room?">Delete</a>
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
    <?php include dirname(__DIR__).'/templates/partials/footer.php'; ?>
  </div>
</body>
</html>
