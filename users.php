<?php
require_once __DIR__.'/auth/require_login.php';
require_once __DIR__.'/db.php';

// Admin-only gate
if (($user['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo 'Forbidden: Admins only.'; exit;
}

$notice = '';$error='';

// Create/Update user
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save_user') {
  $id    = (int)($_POST['id'] ?? 0);
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role  = ($_POST['role'] ?? 'clerk');
  $pwd   = $_POST['password'] ?? '';
  $pwd2  = $_POST['password2'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error='Enter a valid email address.'; }
  elseif (!$name) { $error='Name is required.'; }
  elseif (!in_array($role, ['admin','clerk'], true)) { $error='Invalid role.'; }
  elseif ($id===0 && strlen($pwd) < 6) { $error='Password must be at least 6 characters.'; }
  elseif ($pwd !== '' && $pwd !== $pwd2) { $error='Passwords do not match.'; }
  else {
    if ($id>0) {
      if ($pwd !== '') {
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $st = $mysqli->prepare('UPDATE users SET name=?, email=?, role=?, password_hash=? WHERE id=?');
        $st->bind_param('ssssi', $name, $email, $role, $hash, $id);
      } else {
        $st = $mysqli->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?');
        $st->bind_param('sssi', $name, $email, $role, $id);
      }
      if ($st->execute()) { $notice='User updated.'; header('Location: users.php'); exit; }
      else {
        if ($mysqli->errno==1062) { $error='Email already exists.'; }
        else { $error='Update failed: '.$mysqli->error; }
      }
    } else {
      $hash = password_hash($pwd, PASSWORD_BCRYPT);
      $st = $mysqli->prepare('INSERT INTO users(name,email,role,password_hash) VALUES(?,?,?,?)');
      $st->bind_param('ssss', $name, $email, $role, $hash);
      if ($st->execute()) { $notice='User created.'; header('Location: users.php'); exit; }
      else {
        if ($mysqli->errno==1062) { $error='Email already exists.'; }
        else { $error='Create failed: '.$mysqli->error; }
      }
    }
  }
}

// Delete user (cannot delete self)
if (isset($_GET['delete'])) {
  $uid = (int)$_GET['delete'];
  if ($uid === (int)$user['id']) { $error='You cannot delete your own account.'; }
  else {
    $mysqli->query('DELETE FROM users WHERE id='.$uid);
    header('Location: users.php'); exit;
  }
}

// Edit state
$edit=null; if (isset($_GET['edit'])) { $id=(int)$_GET['edit']; $edit=$mysqli->query('SELECT id,name,email,role FROM users WHERE id='.$id)->fetch_assoc(); }

// List users
$users = $mysqli->query("SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Users</title>
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
      --bs-navbar-color:#334155;
      --bs-navbar-hover-color:#0f172a;
      --bs-navbar-brand-color:#0f172a;
      --bs-navbar-brand-hover-color:#0f172a;
    }
    .card { background:var(--bg-surface); border:1px solid var(--line); border-radius:16px; color:var(--text); box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 3px 6px rgba(0,0,0,.04); }
    .table { --bs-table-bg: transparent; color:var(--text); }
    .table thead th { color:#334155; }
    .sidebar .list-group-item { background:transparent; color:var(--text) !important; border:0; }
    .sidebar .list-group-item:hover { background:#eef2f7; }
    .btn-outline-light { color:#334155; border-color:#cbd5e1; }
    .btn-outline-light:hover { background:#f1f5f9; border-color:#94a3b8; }
  </style>
</head>
<body>
  <?php include __DIR__.'/partials/sidebar.php'; ?>
  <div class="content">
    <?php include __DIR__.'/partials/navtop.php'; ?>
    <main class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 class="m-0">Users</h3>
          <div class="text-secondary">Create and manage staff accounts</div>
        </div>
        <a class="btn btn-outline-light" href="dashboard.php">Back to Dashboard</a>
      </div>

      <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

      <div class="row g-3">
        <!-- Form -->
        <div class="col-lg-5">
          <div class="card p-3">
            <h5 class="mb-3"><?php echo $edit?'Edit User':'Add User'; ?></h5>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="save_user">
              <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
              <div class="col-12">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="name" value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($edit['email'] ?? ''); ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Role</label>
                <select class="form-select" name="role" required>
                  <?php foreach(['clerk','admin'] as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo (($edit['role'] ?? 'clerk')===$r)?'selected':''; ?>><?php echo ucfirst($r); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Password<?php echo $edit? ' (leave blank to keep)':''; ?></label>
                <input type="password" class="form-control" name="password" <?php echo $edit? '':'required'; ?>>
              </div>
              <div class="col-12">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="password2" <?php echo $edit? '':'required'; ?>>
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary"><?php echo $edit?'Save Changes':'Add User'; ?></button>
              </div>
            </form>
          </div>
        </div>

        <!-- List -->
        <div class="col-lg-7">
          <div class="card p-3">
            <h5 class="mb-3">All Users</h5>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($u['name']); ?></td>
                      <td><?php echo htmlspecialchars($u['email']); ?></td>
                      <td><span class="badge <?php echo $u['role']==='admin'?'text-bg-warning':'text-bg-secondary'; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-light" href="?edit=<?php echo (int)$u['id']; ?>">Edit</a>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                          <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int)$u['id']; ?>" onclick="return confirm('Delete this user?');">Delete</a>
                        <?php endif; ?>
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
</body>
</html>
