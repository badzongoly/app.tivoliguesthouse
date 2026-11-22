<?php
require_once dirname(__DIR__,2).'/bootstrap/security.php';
try { access_session(); } catch (Throwable $error) { http_response_code(503); exit('Authentication is temporarily unavailable.'); }
$page=basename($_SERVER['SCRIPT_NAME']); $error=''; $notice=''; $current=null;
try {
    $current=access_current();
    if ($page==='login.php' && $current) { header('Location: '.access_url(access_home_path($current))); exit; }
    if (in_array($page,['logout.php','change-password.php'],true) && !$current) { header('Location: '.access_url('auth/login.php')); exit; }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        access_check_csrf();
        $scalar=static function(string $key): string { if (!is_string($_POST[$key] ?? '')) { throw new RuntimeException('Invalid form input.'); } return $_POST[$key] ?? ''; };
        if ($page==='login.php') {
            $email=$scalar('email'); $password=$scalar('password');
            if (strlen($email)>190 || strlen($password)>4096) { throw new RuntimeException('Invalid email or password.'); }
            $identity=access_service()->login($email,$password);
            if (!$identity) { $error='Invalid email or password, or sign-in is temporarily limited. Try again later.'; }
            else {
                $home = access_home_path($identity);
                session_regenerate_id(true); $_SESSION=[]; $_SESSION['user']=$identity;
                $_SESSION['authenticated_at']=time(); $_SESSION['last_seen']=time();
                header('Location: '.access_url($home)); exit;
            }
        } elseif ($page==='logout.php') {
            access_service()->transaction(fn()=>access_service()->audit((int)$current['id'],'auth.logout','user',(string)$current['id'],'succeeded'));
            access_destroy_session(); header('Location: '.access_url('auth/login.php')); exit;
        } elseif ($page==='change-password.php') {
            if ($scalar('password')!==$scalar('confirm_password')) { throw new RuntimeException('Passwords do not match.'); }
            access_service()->changePassword((int)$current['id'],$scalar('current_password'),$scalar('password'));
            access_destroy_session(); header('Location: '.access_url('auth/login.php?changed=1')); exit;
        } elseif ($page==='forgot-password.php') {
            $email=$scalar('email'); if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
            access_service()->requestReset($email);
            $notice='If an eligible account matches, a password-reset link will be delivered. It expires in 30 minutes.';
        } elseif ($page==='reset-password.php') {
            if ($scalar('password')!==$scalar('confirm_password')) { throw new RuntimeException('Passwords do not match.'); }
            if (!access_service()->resetPassword($scalar('token'),$scalar('password'))) { throw new RuntimeException('This reset link is invalid or expired. Request a new link.'); }
            header('Location: '.access_url('auth/login.php?changed=1')); exit;
        }
    }
} catch (Throwable $exception) {
    $error=access_error($exception);
    if ($_SERVER['REQUEST_METHOD']==='POST' && !($exception instanceof PDOException)) {
        try { access_service()->audit($current?(int)$current['id']:null,'auth.form_rejected','form',$page,'denied',['reason'=>'validation_or_csrf']); }
        catch(Throwable $auditError) { http_response_code(503); $error='Authentication is temporarily unavailable.'; }
    }
    if ($exception instanceof PDOException) { http_response_code(503); }
}
$title=match($page){'login.php'=>'Sign in','logout.php'=>'Sign out','change-password.php'=>'Change password','forgot-password.php'=>'Forgot password','reset-password.php'=>'Reset password'};
require dirname(__DIR__,2).'/templates/auth/form.php';