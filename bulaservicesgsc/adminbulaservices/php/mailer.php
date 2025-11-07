<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/* ---------- SMTP CONFIG (Same as user side) ---------- */
const SMTP2GO_HOST   = 'mail.smtp2go.com';
const SMTP2GO_PORT   = 2525;
const SMTP2GO_SECURE = 'tls';
const SMTP2GO_USER   = 'bulaservices';
const SMTP2GO_PASS   = '0sD4xBFIgtC32OOp';

const MAIL_FROM_EMAIL    = 'no-reply@bulaservicesgsc.com';
const MAIL_FROM_NAME     = 'Barangay Bula Admin System';
const MAIL_REPLYTO_EMAIL = 'no-reply@bulaservicesgsc.com';
const MAIL_REPLYTO_NAME  = 'Barangay Bula Admin Support';

/* ---------- GENERIC EMAIL FUNCTION ---------- */
function sendAdminEmail(string $toEmail, string $displayName, string $subject, string $html, string $alt = ''): array {
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
        $mail->addReplyTo(MAIL_REPLYTO_EMAIL, MAIL_REPLYTO_NAME);
        $mail->addAddress($toEmail, $displayName ?: '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt ?: strip_tags($html);

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log('ADMIN MAIL ERROR: ' . $err);
        return ['ok' => false, 'error' => $err];
    }
}

/* ---------- ADMIN PASSWORD RESET EMAIL ---------- */
function sendAdminPasswordResetLink(string $toEmail, string $displayName, string $token): array {
    $resetUrl = 'https://admin.bulaservicesgsc.com/reset_password.php?token=' . urlencode($token);
    $safeName = htmlspecialchars($displayName ?: 'Administrator', ENT_QUOTES, 'UTF-8');

    $html = "
      <div style='font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;'>
        <h2 style='color: #2563eb;'>Barangay Bula - Admin Password Reset</h2>
        
        <p>Hello <strong>{$safeName}</strong>,</p>
        
        <p>We received a request to reset your administrator password for the Barangay Bula system.</p>
        
        <p style='margin: 25px 0;'>
          <a href='{$resetUrl}' 
             style='display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; 
                    border-radius: 6px; text-decoration: none; font-weight: bold;'>
            Reset Admin Password
          </a>
        </p>
        
        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
        <p style='background: #f8f9fa; padding: 12px; border-radius: 4px; word-break: break-all; 
                  font-family: monospace; font-size: 12px;'>
          {$resetUrl}
        </p>
        
        <p style='color: #6b7280; font-size: 13px; margin-top: 25px;'>
          <strong>Important:</strong> This reset link will expire in 30 minutes for security reasons.
        </p>
        
        <p style='color: #6b7280; font-size: 13px;'>
          If you didn't request this reset, please ignore this email and contact the system administrator immediately.
        </p>
        
        <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 25px 0;'>
        
        <p style='color: #9ca3af; font-size: 12px;'>
          Barangay Bula Admin System<br>
          General Santos City
        </p>
      </div>";

    $alt = "Barangay Bula - Admin Password Reset\n\n" .
           "Hello {$safeName},\n\n" .
           "We received a request to reset your administrator password.\n\n" .
           "Reset your password here: {$resetUrl}\n\n" .
           "This link expires in 30 minutes.\n\n" .
           "If you didn't request this, please contact the system administrator.";

    return sendAdminEmail($toEmail, $displayName, 'Admin Password Reset Request', $html, $alt);
}
?>