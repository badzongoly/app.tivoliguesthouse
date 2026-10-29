<?php
session_start();
require_once __DIR__ . '/../db.php';

// If already logged in
if (!empty($_SESSION['user'])) { header('Location: ../dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  if ($email && $pass) {
    $st = $mysqli->prepare('SELECT id,email,password_hash,name,role FROM users WHERE email=?');
    $st->bind_param('s',$email); $st->execute(); $u = $st->get_result()->fetch_assoc();
    if ($u && password_verify($pass, $u['password_hash'])) {
      $_SESSION['user'] = [ 'id'=>$u['id'], 'email'=>$u['email'], 'name'=>$u['name'], 'role'=>$u['role'] ];
      header('Location: ../dashboard.php'); exit;
    } else {
      $err = 'Invalid email or password';
    }
  } else { $err = 'Please enter email and password'; }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HotelPOS — Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{
      --bg-page:#f7f8fb;          /* soft light background */
      --bg-surface:#ffffff;       /* card/nav surfaces */
      --line:#e5e7eb;             /* gentle borders */
      --text:#0f172a;             /* slate-900 */
      --muted:#475569;            /* slate-600 */
      --brand:#0ea5e9;            /* accent */
    }
    body { background:var(--bg-page); color:var(--text); }
    .card { background:var(--bg-surface); border-radius:16px; border:1px solid var(--line); box-shadow: 0 2px 8px rgba(15,23,42,.06); }
    .brand { font-weight:700; letter-spacing:.4px; color:var(--text); }
    .text-secondary { color: var(--muted)!important; }
    .form-control { border-color:#dbe2ea; }
    .form-control:focus { border-color:#93c5fd; box-shadow: 0 0 0 .25rem rgba(59,130,246,.15); }
    .btn-primary { background: linear-gradient(180deg, #38bdf8, #0ea5e9); border: 1px solid #0ea5e9; }
    .btn-primary:hover { filter: brightness(0.95); }
    .link-muted { color:#64748b; }
  </style>
</head>
<body class="d-flex align-items-center" style="min-height:100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="text-center mb-4">
          <h1 class="brand">Tivoli Guesthouse</h1>
          <p class="text-secondary">Sign in to continue</p>
        </div>
        <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
        <div class="card p-4 shadow-sm">
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="Your password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePwd" aria-label="Show password">Show</button>
              </div>
            </div>
            <button class="btn btn-primary w-100">Login</button>
          </form>
        </div>
        <p class="mt-3 text-center text-secondary small">Use admin@example.com / your password</p>
      </div>
    </div>
  </div>
  <script>
    (function(){
      const btn = document.getElementById('togglePwd');
      const pw  = document.getElementById('password');
      if (btn && pw) {
        btn.addEventListener('click', function(){
          const showing = pw.getAttribute('type') === 'text';
          pw.setAttribute('type', showing ? 'password' : 'text');
          btn.textContent = showing ? 'Show' : 'Hide';
          btn.setAttribute('aria-pressed', (!showing).toString());
        });
      }
      const form = document.querySelector('form[method="post"]');
      if (form) {
        form.addEventListener('submit', function(){
          const submit = form.querySelector('button[type="submit"], button:not([type])');
          if (submit) { submit.disabled = true; submit.textContent = 'Logging in…'; }
        });
      }
    })();
  </script>
</body>
</html>
