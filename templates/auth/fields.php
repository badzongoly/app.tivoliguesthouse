      <?php if($error):?><p class="error" id="auth-error" role="alert"><?=access_h($error)?></p><?php endif?>
      <?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>
      <?php if($page==='login.php' && isset($_GET['changed'])):?><p class="notice" role="status">Password updated. Sign in with your new password.</p><?php endif?>
      <?php if($current && $current['must_change_password'] && $page==='change-password.php'):?><p class="notice" role="status">Change your temporary password before continuing.</p><?php endif?>
      <form method="post" action="<?=access_h(access_url('auth/'.$page))?>" class="auth-form" <?=$error?'aria-describedby="auth-error"':''?>>
        <input type="hidden" name="csrf" value="<?=access_h(access_csrf())?>">
        <?php if(in_array($page,['login.php','forgot-password.php'],true)):?>
        <div class="auth-field"><label for="email">Email address</label><div class="auth-input-wrap"><svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><input id="email" name="email" type="email" maxlength="190" autocomplete="username" placeholder="Your staff email address" value="<?=access_h($emailValue)?>" autocapitalize="none" spellcheck="false" required></div></div>
        <?php endif?>
        <?php if($page==='change-password.php'):?>
        <div class="auth-field"><label for="current_password">Current password</label><div class="password"><input id="current_password" name="current_password" type="password" autocomplete="current-password" required><button type="button" class="secondary" data-password-toggle="current_password" aria-pressed="false" aria-label="Show password">Show</button></div></div>
        <?php endif?>
        <?php if(in_array($page,['login.php','change-password.php','reset-password.php'],true)):?>
        <div class="auth-field"><div class="auth-label-row"><label for="password"><?=$page==='login.php'?'Password':'New password'?></label><?php if($page==='login.php'):?><a href="<?=access_h(access_url('auth/forgot-password.php'))?>">Forgot password?</a><?php endif?></div><div class="password auth-password"><svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input id="password" name="password" type="password" autocomplete="<?=$page==='login.php'?'current-password':'new-password'?>" placeholder="<?=$page==='login.php'?'Enter your password':'Create a unique passphrase'?>" <?=$page==='login.php'?'':'minlength="12" maxlength="72" aria-describedby="password-guidance"'?> required><button type="button" class="secondary" data-password-toggle="password" aria-pressed="false" aria-label="Show password">Show</button></div>
        <?php if($page!=='login.php'):?><small id="password-guidance">At least 12 characters. Maximum 72 bytes.</small><?php endif?></div>
        <?php if($page!=='login.php'):?><div class="auth-field"><label for="confirm_password">Confirm new password</label><div class="password"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required><button type="button" class="secondary" data-password-toggle="confirm_password" aria-pressed="false" aria-label="Show password">Show</button></div></div><?php endif?>
        <?php endif?>
        <?php if($page==='reset-password.php'):?><input type="hidden" id="reset-token" name="token" value="<?=access_h(is_string($_POST['token'] ?? null)?$_POST['token']:'')?>"><?php endif?>
        <?php if($page==='logout.php'):?><p class="auth-description">You can sign in again whenever you’re ready.</p><?php endif?>
        <button type="submit" class="auth-submit"><span><?=access_h($title)?></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
      </form>
