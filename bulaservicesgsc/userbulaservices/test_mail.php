<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
try {
  // SMTP
  $mail->isSMTP();
  $mail->Host = 'bulaservicesgsc.com';            // your mail host
  $mail->SMTPAuth = true;
  $mail->Username = 'no-reply@bulaservicesgsc.com';    // full mailbox
  $mail->Password = '0sD4xBFIgtC32OOp';      // <-- change this
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // STARTTLS
  $mail->Port = 587;

  // Headers
  $mail->setFrom('no-reply@bulaservicesgsc.com', 'Barangay Bula');
  $mail->addAddress('espinosa.jericho@gmail.com');            // <-- change this

  // Content
  $mail->Subject = 'PHPMailer SMTP test';
  $mail->Body    = 'If you see this, SMTP works.';
  $mail->AltBody = 'SMTP test';

  $mail->send();
  echo "OK";
} catch (Throwable $e) {
  echo "Mailer Error: " . $e->getMessage();
}
