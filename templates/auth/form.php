<?php
$authCopy=match($page) {
    'login.php'=>['Welcome back.','Sign in to your Tivoli workspace.'],
    'forgot-password.php'=>['Let’s get you back in.','Enter your staff email to request a password-reset link.'],
    'reset-password.php'=>['A fresh start.','Choose a unique password for your staff account.'],
    'change-password.php'=>['Keep your account yours.','Update your password, then sign in again to continue.'],
    'logout.php'=>['Taking a break?','Sign out when you’re finished on this device.'],
};
$emailValue=is_string($_POST['email'] ?? null)?$_POST['email']:'';

if($current) {
    $user=$current;$layoutTitle=$title;$layoutPage='account';
    require dirname(__DIR__).'/layouts/start.php';
    ?><div class="app-page-heading"><p class="app-eyebrow">YOUR ACCOUNT</p><h1><?=access_h($title)?></h1><p><?=access_h($authCopy[1])?></p></div><section class="card app-account-form"><?php require __DIR__.'/fields.php';?></section><?php
    require dirname(__DIR__).'/layouts/end.php';
    return;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#183e34">
  <title><?=access_h($title)?> · Tivoli Guesthouse</title>
  <link rel="stylesheet" href="<?=access_h(access_url('assets/css/access.css'))?>">
  <link rel="stylesheet" href="<?=access_h(access_url('assets/css/auth.css'))?>">
  <script src="<?=access_h(access_url('assets/js/passwords.js'))?>" defer></script>
</head>
<body class="auth-page">
<main class="auth-shell">
  <aside class="auth-story" aria-label="Tivoli Guesthouse">
    <div class="auth-brand">
      <img src="<?=access_h(access_url('assets/images/Tivoli.png'))?>" alt="" width="64" height="64">
      <div><span class="auth-wordmark">Tivoli</span><span class="auth-brand-caption">GUESTHOUSE</span></div>
    </div>
    <div class="auth-story-copy">
      <p class="auth-eyebrow">THE ART OF A GREAT STAY</p>
      <h2>Thoughtful hospitality.<br>A little more personal.</h2>
      <p>Behind every warm welcome is a team<br class="desktop-break"> that makes it happen.</p>
    </div>
    <div class="auth-art auth-property-art">
      <img src="<?=access_h(access_url('assets/images/tivoli-front-view.png'))?>" alt="Brick-cladding concept for Tivoli Guesthouse, with a landscaped courtyard" width="1536" height="1024" fetchpriority="high">
    </div>
    <div class="auth-story-footer"><span class="auth-star" aria-hidden="true">✦</span><span>A great experience starts with you.</span><span class="auth-footer-line" aria-hidden="true"></span></div>
  </aside>
  <section class="auth-workspace" aria-labelledby="auth-heading">
    <header class="auth-workspace-header"><span class="auth-portal"><span aria-hidden="true"></span>STAFF PORTAL</span><span class="auth-access-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>Staff access</span></header>
    <div class="auth-form-area">
      <p class="auth-form-eyebrow">YOUR DAY AT TIVOLI</p>
      <h1 id="auth-heading"><?=access_h($authCopy[0])?></h1>
      <p class="auth-description"><?=access_h($authCopy[1])?></p>
      <?php require __DIR__.'/fields.php'; ?>
      <?php if($page==='login.php'):?><div class="auth-form-note"><span aria-hidden="true">✦</span><p>Your own account. Your Tivoli workspace.<br><span>Please use your individual staff credentials.</span></p></div><?php else:?><nav class="auth-back-links"><?php if($current):?><a href="<?=access_h(access_url('dashboard.php'))?>">← Back to dashboard</a><a href="<?=access_h(access_url('auth/logout.php'))?>">Sign out</a><?php else:?><a href="<?=access_h(access_url('auth/login.php'))?>">← Back to sign in</a><?php endif?></nav><?php endif?>
    </div>
    <footer class="auth-workspace-footer"><span>© <?=date('Y')?> Tivoli Guesthouse</span><span>Made for a warmer welcome.</span></footer>
  </section>
</main>
</body>
</html>