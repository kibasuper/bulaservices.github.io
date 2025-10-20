<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../server/config.php';   // your DB/auth helpers, sessions, etc.
require __DIR__ . '/../vendor/autoload.php';      // PHPMailer

/**
 * ============================
 * SMTP2GO CONFIG (edit below)
 * ============================
 *
 * Get these from your SMTP2GO dashboard:
 *  - SMTP username & password (Sending → SMTP Users)
 *  - Verified sender address (Reports box shows “Add a verified sender” ✔)
 *
 * Recommended transport: STARTTLS on port 587 or 2525
 * SSL is available on 465, 8465, 443 if your host blocks 587/2525.
 */
const SMTP2GO_HOST   = 'mail.smtp2go.com';
const SMTP2GO_PORT   = 587;                     // or 2525 / 8025 / 80 / 25
const SMTP2GO_SECURE = 'tls';                   // 'tls' for STARTTLS, or 'ssl' if using 465
const SMTP2GO_USER   = 'bulaservices'; // <-- change this
const SMTP2GO_PASS   = '0sD4xBFIgtC32OOp'; // <-- change this

// Use an address you verified in SMTP2GO (can be your Gmail or a domain mailbox)
const MAIL_FROM_EMAIL    = 'no-reply@bulaservicesgsc.com'; // <-- change this
const MAIL_FROM_NAME     = 'Barangay Bula Online Services';
const MAIL_REPLYTO_EMAIL = 'no-reply@bulaservicesgsc.com'; // optional, usually same as FROM
const MAIL_REPLYTO_NAME  = 'Barangay Bula Support';

/**
 * Send the verification link.
 *
 * @param string $toEmail      Recipient email
 * @param string $displayName  Recipient display name (optional)
 * @param string $token        Raw verification token (NOT hashed)
 * @return array               ['ok'=>bool, 'error'?:string]
 */
function sendVerificationLink(string $toEmail, string $displayName, string $token): array
{
    $verifyUrl = 'https://bulaservicesgsc.com/php/verify.php?token=' . urlencode($token);


    $mail = new PHPMailer(true);
    try {
        // Transport
        $mail->isSMTP();
        $mail->Host       = SMTP2GO_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP2GO_USER;
        $mail->Password   = SMTP2GO_PASS;

        if (strtolower(SMTP2GO_SECURE) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;     // port 465, 8465, 443
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // port 587, 2525, 8025, 80, 25
        }
        $mail->Port = (int) SMTP2GO_PORT;

        // From / Reply-To / To
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        if (MAIL_REPLYTO_EMAIL) {
            $mail->addReplyTo(MAIL_REPLYTO_EMAIL, MAIL_REPLYTO_NAME ?: MAIL_FROM_NAME);
        }
        $mail->addAddress($toEmail, $displayName ?: '');

        // Optional headers (nice to have)
        $mail->MessageID = sprintf('<%s@bulaservicesgsc.com>', bin2hex(random_bytes(8)));
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

        // Content
        $safeName = htmlspecialchars($displayName ?: 'there', ENT_QUOTES, 'UTF-8');
        $mail->isHTML(true);
        $mail->Subject = 'Verify your email';
        $mail->Body = "
          <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.55'>
            <p>Hi {$safeName},</p>
            <p>Welcome to Barangay Bula Online Services. Please verify your email address:</p>
            <p>
              <a href='{$verifyUrl}' style='display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none'>
                Verify Email
              </a>
            </p>
            <p>If the button doesn’t work, copy this link into your browser:<br>
              <span style='word-break:break-all'>{$verifyUrl}</span>
            </p>
            <p style='color:#6b7280'>This link expires in 30 minutes. If you didn’t create an account, please ignore this email.</p>
          </div>";
        $mail->AltBody = "Hi {$safeName},\n\nVerify your email:\n{$verifyUrl}\n\nThis link expires in 30 minutes.";

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        // $mail->SMTPDebug = 2; // uncomment for verbose SMTP logs (dev only)
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log('MAIL ERROR: ' . $err);
        return ['ok' => false, 'error' => $err];
    }
}
