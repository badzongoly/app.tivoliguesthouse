<?php
namespace Tivoli\Infrastructure;
use RuntimeException;
final class ResetMailer
{
    public function __construct(private readonly array $config, private readonly string $root) {}
    public function deliver(array $delivery): void
    {
        $base=rtrim($this->config['base_url'],'/');
        $host=parse_url($base,PHP_URL_HOST);$local=in_array($host,['localhost','127.0.0.1','::1'],true);
        if (!$local && parse_url($base,PHP_URL_SCHEME)!=='https') { throw new RuntimeException('Recovery requires HTTPS.'); }
        $url=$base.'/auth/reset-password.php#token='.rawurlencode($delivery['token']);
        if (($this->config['reset_delivery'] ?? '')==='local_preview' && $local) {
            $folder=$this->root.(getenv('TIVOLI_TEST_MODE')==='1'?'/storage/mail-test':'/storage/mail');
            if (!is_dir($folder) && !mkdir($folder,0700,true)) { throw new RuntimeException('Recovery delivery failed.'); }
            $path=$folder.'/'.bin2hex(random_bytes(16)).'.json';
            $payload=json_encode(['to'=>$delivery['email'],'subject'=>'Reset your Tivoli password','url'=>$url,'expires_in_minutes'=>30],JSON_THROW_ON_ERROR);
            if (file_put_contents($path,$payload,LOCK_EX)===false) { throw new RuntimeException('Recovery delivery failed.'); }
            chmod($path,0600);return;
        }
        $smtp=$this->config['smtp'] ?? [];
        if (($this->config['reset_delivery'] ?? '')!=='smtp' || !class_exists(\PHPMailer\PHPMailer\PHPMailer::class) || empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password']) || !filter_var($smtp['from_email'] ?? '',FILTER_VALIDATE_EMAIL) || !in_array($smtp['encryption'] ?? '',['tls','smtps'],true)) {
            throw new RuntimeException('SMTP recovery delivery is not configured.');
        }
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();$mail->Host=$smtp['host'];$mail->Port=(int)($smtp['port'] ?? 587);
        $mail->SMTPAuth=true;$mail->Username=$smtp['username'];$mail->Password=$smtp['password'];
        $mail->SMTPSecure=$smtp['encryption'];$mail->SMTPDebug=0;$mail->Timeout=10;$mail->Timelimit=15;
        $mail->CharSet='UTF-8';$mail->setFrom($smtp['from_email'],$smtp['from_name'] ?? 'Tivoli Guesthouse');
        $mail->addAddress($delivery['email']);$mail->Subject='Reset your Tivoli password';
        $mail->Body="A password reset was requested for your staff account.\n\nOpen this link within 30 minutes:\n".$url."\n\nIf you did not request this, ignore this email. Your password has not changed.";
        try {$mail->send();}catch(\Throwable $error){throw new RuntimeException('Recovery delivery failed.');}
    }
}