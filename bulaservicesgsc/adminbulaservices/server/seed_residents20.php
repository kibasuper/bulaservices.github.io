<?php
// seed_residents20.php
declare(strict_types=1);

/**
 * Creates 20 believable users (mostly residents of Barangay Bula, GSC)
 * and corresponding email_verifications records.
 *
 * SAFETY:
 * - Requires superadmin session.
 * - Idempotent-ish: ensures unique usernames/emails inside this run,
 *   but won’t check your existing DB for collisions — run on a test DB first.
 */

require_once __DIR__ . '/server/config.php';
session_start();

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  echo "Forbidden: superadmin session required.";
  exit;
}

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -------- helpers
function filipino_mobile(): string {
  // 09 + 9 digits
  return '09' . str_pad((string)random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
}
function slugify($s) {
  $s = strtolower(trim($s));
  $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
  $s = preg_replace('/[^a-z0-9]+/i', '', $s);
  return $s;
}
function random_purok(): string {
  $p = [ 'Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6','Purok 7','Purok 8','Purok 9','Purok 10','Purok 11','Purok 12','Purok 13','Purok 14','Purok 15','Purok 16','Purok 17','Purok 18' ];
  return $p[array_rand($p)];
}
function random_address(string $purok): string {
  $streets = [
    'Mateo Road','Rajah Muda Ave','Paitan St','Purok Riverside','Barangay Hall Road',
    'Pendatun St','Santo Niño St','San Isidro St','San Miguel St','Maharlika Road'
  ];
  $s = $streets[array_rand($streets)];
  return "$purok, $s, Barangay Bula, General Santos City";
}
function random_birthdate_gender(): array {
  // Ages 18..60
  $year = (int)date('Y') - random_int(18, 60);
  $month = random_int(1, 12);
  $day = random_int(1, 28);
  $birth = sprintf('%04d-%02d-%02d', $year, $month, $day);

  $genders = ['male','female'];
  $gender = $genders[array_rand($genders)];
  return [$birth, $gender];
}
function calc_age(string $birth): int {
  $bd = new DateTimeImmutable($birth);
  $now = new DateTimeImmutable('now');
  return (int)$bd->diff($now)->y;
}
function random_civil_status(int $age): ?string {
  $choices = $age < 25 ? ['single','single','single','married'] : ['single','married','married','widowed','separated'];
  return $choices[array_rand($choices)];
}
function random_stay_year(int $age): ?int {
  $current = (int)date('Y');
  $minYear = max($current - $age + 1, 1990);
  return random_int($minYear, $current);
}
function token64(): string {
  return bin2hex(random_bytes(32)); // 64 hex chars
}
function ensure_unique(&$set, string $base): string {
  $u = $base; $i = 1;
  while (isset($set[$u])) {
    $u = $base . $i;
    $i++;
  }
  $set[$u] = true;
  return $u;
}

// -------- curated Filipino name list (first, middle initial or name, last)
$pool = [
  ['Judith','R.','Santos'],
  ['Marvin','D.','Reyes'],
  ['Aileen','M.','Cruz'],
  ['Jerome','P.','Dela Cruz'],
  ['Kimberly','T.','Garcia'],
  ['Rogelio','B.','Lopez'],
  ['Shiela','G.','Mendoza'],
  ['Alvin','S.','Torres'],
  ['Jonalyn','L.','Flores'],
  ['Christian','V.','Bautista'],
  ['Rhea','C.','Navarro'],
  ['Jasper','N.','Aquino'],
  ['April','F.','Domingo'],
  ['Mark','E.','Salazar'],
  ['Eunice','H.','Diaz'],
  ['Lorenzo','Q.','Gonzales'],
  ['Joyce','K.','Villanueva'],
  ['Neil','J.','Castillo'],
  ['Patricia','Y.','Ramos'],
  ['Francis','O.','Morales'],
];

$domainChoices = ['@gmail.com','@yahoo.com','@outlook.com'];
$usernameSet = [];
$emailSet = [];

$pdo->beginTransaction();

try {
  // prepared statements
  $userSql = "
    INSERT INTO users (
      user_type, first_name, middle_name, last_name, suffix, birth_place, birth_date, age,
      civil_status, gender, purok, year_started_staying, contact_number, occupation, address,
      email, username, password, profile_picture, is_active, is_verified, verification_token,
      reset_token, reset_token_expiry, last_login, failed_login_attempts, account_locked_until,
      created_at, updated_at, email_verified, verified_at, terms_accepted_at, status
    ) VALUES (
      :user_type, :first_name, :middle_name, :last_name, NULL, :birth_place, :birth_date, :age,
      :civil_status, :gender, :purok, :stay_year, :contact, :occupation, :address,
      :email, :username, :password, NULL, 1, 1, NULL,
      NULL, NULL, NULL, 0, NULL,
      NOW(), NOW(), 1, :verified_at, :terms_at, 'active'
    )
  ";
  $userStmt = $pdo->prepare($userSql);

  $evSql = "
    INSERT INTO email_verifications (user_id, token, sent_to, created_at, expires_at, consumed_at)
    VALUES (:user_id, :token, :sent_to, :created_at, :expires_at, :consumed_at)
  ";
  $evStmt = $pdo->prepare($evSql);

  $createdUsers = [];

  foreach ($pool as $idx => [$first, $mid, $last]) {
    [$birth_date, $gender] = random_birthdate_gender();
    $age = calc_age($birth_date);
    $civil = random_civil_status($age);
    $purok = random_purok();
    $address = random_address($purok);
    $contact = filipino_mobile();
    $occupation = (random_int(0,1) ? 'Employee' : 'Self-employed');

    // Mostly residents; sprinkle in a couple outsiders
    $user_type = ($idx % 9 === 0) ? 'outsider' : 'resident';

    // usernames/emails
    $unameBase = slugify($first . $last);
    $username  = ensure_unique($usernameSet, $unameBase);

    $emailBase = slugify($first) . '.' . slugify($last);
    $domain    = $domainChoices[array_rand($domainChoices)];
    $email     = ensure_unique($emailSet, $emailBase) . $domain;

    // password rule
    $plain = strtolower($first) . '1234';
    $hash  = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);

    $verifiedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $termsAt    = (new DateTimeImmutable('now -5 minutes'))->format('Y-m-d H:i:s');

    $userStmt->execute([
      ':user_type'   => $user_type,
      ':first_name'  => $first,
      ':middle_name' => $mid,
      ':last_name'   => $last,
      ':birth_place' => 'General Santos City',
      ':birth_date'  => $birth_date,
      ':age'         => $age,
      ':civil_status'=> $civil,
      ':gender'      => $gender,
      ':purok'       => preg_replace('/^Purok\s+/i','',$purok), // your schema stores purok number often
      ':stay_year'   => random_stay_year($age),
      ':contact'     => $contact,
      ':occupation'  => $occupation,
      ':address'     => $address,
      ':email'       => $email,
      ':username'    => $username,
      ':password'    => $hash,
      ':verified_at' => $verifiedAt,
      ':terms_at'    => $termsAt,
    ]);

    $uid = (int)$pdo->lastInsertId();
    $createdUsers[] = [
      'id'=>$uid, 'first'=>$first, 'last'=>$last, 'email'=>$email, 'username'=>$username, 'password'=>$plain
    ];

    // email_verifications simulated (already consumed)
    $created = (new DateTimeImmutable('now -10 minutes'));
    $expires = $created->modify('+30 minutes');

    $evStmt->execute([
      ':user_id'    => $uid,
      ':token'      => token64(),
      ':sent_to'    => $email,
      ':created_at' => $created->format('Y-m-d H:i:s'),
      ':expires_at' => $expires->format('Y-m-d H:i:s'),
      ':consumed_at'=> $verifiedAt,
    ]);
  }

  $pdo->commit();

  header('Content-Type: text/plain; charset=utf-8');
  echo "OK — seeded 20 accounts.\n\n";
  echo str_pad('ID',6)."  ".str_pad('Username',16)."  ".str_pad('Password (plain)',18)."  Email\n";
  echo str_repeat('-',70)."\n";
  foreach ($createdUsers as $u) {
    echo str_pad((string)$u['id'],6)."  ".
         str_pad($u['username'],16)."  ".
         str_pad($u['password'],18)."  ".
         $u['email']."\n";
  }
  echo "\nIMPORTANT: Remove this file after use.\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Seeder failed: ".$e->getMessage();
}
