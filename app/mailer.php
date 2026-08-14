<?php
declare(strict_types=1);

function notify_management(string $subject, string $body): void
{
    global $config;
    $smtp = $config['smtp'];
    if (empty($smtp['host']) || empty($smtp['from_email'])) return;
    $recipients = db()->query("SELECT u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='management' AND u.is_active=1 AND u.email IS NOT NULL AND u.email<>''")->fetchAll(PDO::FETCH_COLUMN);
    if (!$recipients) return;
    require_once ROOT_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once ROOT_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once ROOT_PATH . '/vendor/PHPMailer/src/SMTP.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); $mail->isSMTP(); $mail->Host=$smtp['host']; $mail->Port=(int)$smtp['port']; $mail->SMTPAuth=true; $mail->Username=$smtp['username']; $mail->Password=$smtp['password'];
        if ($smtp['encryption']==='tls') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        if ($smtp['encryption']==='ssl') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->setFrom($smtp['from_email'],$smtp['from_name']); foreach($recipients as $email) $mail->addAddress($email); $mail->Subject=$subject; $mail->Body=$body; $mail->send();
    } catch (Throwable $e) { system_log('error','email_failed','Management email failed',['error'=>$e->getMessage(),'subject'=>$subject]); }
}

function send_mail_to_addresses(array $recipients, string $subject, string $body): void
{
    global $config;
    $smtp = $config['smtp'];
    $recipients = array_values(array_unique(array_filter($recipients, static fn($email) => is_string($email) && trim($email) !== '')));
    if (!$recipients || empty($smtp['host']) || empty($smtp['from_email'])) return;
    require_once ROOT_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once ROOT_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once ROOT_PATH . '/vendor/PHPMailer/src/SMTP.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); $mail->isSMTP(); $mail->Host=$smtp['host']; $mail->Port=(int)$smtp['port']; $mail->SMTPAuth=true; $mail->Username=$smtp['username']; $mail->Password=$smtp['password'];
        if ($smtp['encryption']==='tls') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        if ($smtp['encryption']==='ssl') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->setFrom($smtp['from_email'],$smtp['from_name']); foreach($recipients as $email) $mail->addAddress($email); $mail->Subject=$subject; $mail->Body=$body; $mail->send();
    } catch (Throwable $e) { system_log('error','email_failed','Direct email failed',['error'=>$e->getMessage(),'subject'=>$subject,'recipient_count'=>count($recipients)]); }
}
