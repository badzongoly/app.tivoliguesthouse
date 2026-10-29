<nav class="navtop navbar navbar-dark">
<div class="container-fluid">
<span class="navbar-brand">Welcome, <?php echo htmlspecialchars($user['name'] ?? ''); ?></span>
<div class="d-flex gap-2">
<a href="auth/logout.php" class="btn btn-sm btn-outline-light">Logout</a>
</div>
</div>
</nav>