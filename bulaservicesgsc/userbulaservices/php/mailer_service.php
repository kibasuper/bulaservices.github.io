<?php
declare(strict_types=1);

// Try both likely paths for mailer.php
$paths = [
    __DIR__ . '/mailer.php',
    dirname(__DIR__) . '/php/mailer.php',           // safety
];
foreach ($paths as $p) { if (is_file($p)) { require_once $p; break; } }

/**
 * Generic status email
 */
function sendServiceStatusEmail(string $toEmail, string $displayName, string $subject, string $htmlBody, string $altBody=''): array {
    // Reuse PHPMailer setup from mailer.php but with custom subject/body
    try {
        $r = new \ReflectionFunction('sendVerificationLink');
        $file = $r->getFileName(); // ensures mailer.php was loaded
    } catch (\Throwable $e) {
        return ['ok'=>false,'error'=>'mailer.php not found/loaded'];
    }

    // Build a new PHPMailer like sendVerificationLink() does
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP2GO_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP2GO_USER;
        $mail->Password   = SMTP2GO_PASS;
        $mail->SMTPSecure = (strtolower(SMTP2GO_SECURE) === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) SMTP2GO_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        if (MAIL_REPLYTO_EMAIL) {
            $mail->addReplyTo(MAIL_REPLYTO_EMAIL, MAIL_REPLYTO_NAME ?: MAIL_FROM_NAME);
        }
        $mail->addAddress($toEmail, $displayName ?: '');

        $mail->MessageID = sprintf('<%s@bulaservicesgsc.com>', bin2hex(random_bytes(8)));
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        $mail->send();
        return ['ok'=>true];
    } catch (\Throwable $e) {
        error_log('MAIL ERROR: '.$e->getMessage());
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/** convenience */
function pretty_service_name(string $raw): string {
    $m = [
        'barangay_clearance'=>'Barangay Clearance',
        'indigency'=>'Certificate of Indigency',
        'residency'=>'Certificate of Residency',
        'business_permit'=>'Business Permit',
        'gym'=>'Gym Reservation',
    ];
    $k = strtolower(trim($raw));
    return $m[$k] ?? ucwords(str_replace(['_','-'],' ',$raw));
}
