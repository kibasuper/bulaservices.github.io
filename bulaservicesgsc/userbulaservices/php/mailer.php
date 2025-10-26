<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/* ---------- SMTP CONFIG ---------- */
const SMTP2GO_HOST   = 'mail.smtp2go.com';
const SMTP2GO_PORT   = 2525;  // safer port, less likely blocked
const SMTP2GO_SECURE = 'tls';
const SMTP2GO_USER   = 'bulaservices';
const SMTP2GO_PASS   = '0sD4xBFIgtC32OOp';

const MAIL_FROM_EMAIL    = 'no-reply@bulaservicesgsc.com';
const MAIL_FROM_NAME     = 'Barangay Bula Online Services';
const MAIL_REPLYTO_EMAIL = 'no-reply@bulaservicesgsc.com';
const MAIL_REPLYTO_NAME  = 'Barangay Bula Support';

/* ---------- GENERIC EMAIL ---------- */
function sendEmailGeneric(string $toEmail, string $displayName, string $subject, string $html, string $alt=''): array {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP2GO_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP2GO_USER;
        $mail->Password   = SMTP2GO_PASS;
        $mail->SMTPSecure = (strtolower(SMTP2GO_SECURE) === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)SMTP2GO_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        if (MAIL_REPLYTO_EMAIL) $mail->addReplyTo(MAIL_REPLYTO_EMAIL, MAIL_REPLYTO_NAME ?: MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $displayName ?: '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt ?: strip_tags($html);

        $mail->send();
        return ['ok'=>true];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log('MAIL ERROR: ' . $err);
        return ['ok'=>false,'error'=>$err];
    }
}

/* ---------- VERIFICATION EMAIL ---------- */
function sendVerificationLink(string $toEmail, string $displayName, string $token): array {
    $verifyUrl = 'https://bulaservicesgsc.com/php/verify.php?token=' . urlencode($token);
    $safeName = htmlspecialchars($displayName ?: 'there', ENT_QUOTES, 'UTF-8');

    $html = "
      <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.55'>
        <p>Hi {$safeName},</p>
        <p>Welcome to Barangay Bula Online Services. Please verify your email address:</p>
        <p>
          <a href='{$verifyUrl}' style='display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none'>
            Verify Email
          </a>
        </p>
        <p>If the button doesn’t work, copy this link:<br>
          <span style='word-break:break-all'>{$verifyUrl}</span>
        </p>
        <p style='color:#6b7280'>This link expires in 30 minutes.</p>
      </div>";
    $alt = "Hi {$safeName},\n\nVerify your email:\n{$verifyUrl}\n\nThis link expires in 30 minutes.";

    return sendEmailGeneric($toEmail, $displayName, 'Verify your email', $html, $alt);
}

/* ---------- RESET PASSWORD EMAIL ---------- */
function sendPasswordResetLink(string $toEmail, string $displayName, string $token): array {
    $resetUrl = 'https://bulaservicesgsc.com/php/reset_password.php?token=' . urlencode($token);
    $safeName = htmlspecialchars($displayName ?: 'there', ENT_QUOTES, 'UTF-8');

    $html = "
      <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.55'>
        <p>Hi {$safeName},</p>
        <p>We received a request to reset your password. Click below to choose a new one:</p>
        <p>
          <a href='{$resetUrl}' style='display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none'>
            Reset Password
          </a>
        </p>
        <p>If the button doesn’t work, copy this link:<br>
          <span style='word-break:break-all'>{$resetUrl}</span>
        </p>
        <p style='color:#6b7280'>This link expires in 30 minutes.</p>
      </div>";
    $alt = "Hi {$safeName},\n\nReset your password:\n{$resetUrl}\n\nThis link expires in 30 minutes.";

    return sendEmailGeneric($toEmail, $displayName, 'Reset your password', $html, $alt);
}
