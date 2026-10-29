<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

// Create / Update extra
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $active = isset($_POST['active']) ? 1 : 0;
  if ($name !== '') {
    if ($id > 0) {
      $st = $mysqli->prepare('UPDATE extras SET name=?, price=?, active=? WHERE id=?');
      $st->bind_param('sdii', $name, $price, $active, $id);
      $st->execute();
    } else {
      $st = $mysqli->prepare('INSERT INTO extras(name, price, active, stock_qty)
VALUES (?, ?, ?, 0)');
      $st->bind_param('sdi', $name, $price, $active);
      $st->execute();
    }
  }
  header('Location: extras.php'); exit;
}

// Delete extra
if (isset($_GET['delete'])) {
  $rid = (int)$_GET['delete'];
  $mysqli->query('DELETE FROM extras WHERE id='.$rid);
  header('Location: extras.php'); exit;
}

// Edit state
$edit = null; if (isset($_GET['edit'])) { $id=(int)$_GET['edit']; $edit=$mysqli->query('SELECT * FROM extras WHERE id='.$id)->fetch_assoc(); }

// Fetch list
$extras = $mysqli->query('SELECT * FROM extras ORDER BY active DESC, name ASC')->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Extras</title>
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
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 class="m-0">Extras</h3>
          <div class="text-secondary">Manage catalog items for food, drinks, and services</div>
        </div>
        <a class="btn btn-outline-light" href="bookings.php">Back to Bookings</a>
      </div>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card p-3">
            <h5 class="mb-3"><?php echo $edit ? 'Edit Extra' : 'Add Extra'; ?></h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
              <div class="col-12">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label">Price (₵)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="price" value="<?php echo htmlspecialchars(isset($edit['price']) ? number_format((float)$edit['price'],2,'.','') : '0.00'); ?>">
              </div>
              <div class="col-6 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="active" id="active" <?php echo ((int)($edit['active'] ?? 1)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="active">Active</label>
                </div>
              </div>
              <div class="col-12 d-grid">
                <button type="submit" class="btn btn-primary"><?php echo $edit ? 'Save Changes' : 'Add Extra'; ?></button>
                <?php if ($edit): ?>
                  <a class="btn btn-outline-secondary mt-2" href="extras.php">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="m-0">All Extras</h5>
            </div>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Name</th><th class="text-end">Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($extras as $x): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($x['name']); ?></td>
                      <td class="text-end">₵<?php echo number_format($x['price'],2); ?></td>
                      <td>
                        <span class="badge <?php echo $x['active']? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $x['active']? 'active' : 'inactive'; ?></span>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-light" href="?edit=<?php echo $x['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo $x['id']; ?>" onclick="return confirm('Delete this item?');">Delete</a>
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
    <?php include __DIR__.'/partials/footer.php'; ?>
  </div>
  <script>
    (function(){
      const btn = document.getElementById('btnSidebar');
      btn?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
      document.addEventListener('click', (e) => {
        if (!document.body.classList.contains('sidebar-open')) return;
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && !sidebar.contains(e.target) && btn && !btn.contains(e.target)) {
          document.body.classList.remove('sidebar-open');
        }
      });
    })();
  </script>
</body>
</html>