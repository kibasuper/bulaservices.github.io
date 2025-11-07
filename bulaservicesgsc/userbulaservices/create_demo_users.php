<?php
declare(strict_types=1);

/**
 * create_demo_users.php — diversified names + random email domains
 * - username = lowercase(first+last) (sanitized; unique with numeric suffix)
 * - password = lowercase(first_sanitized) + '1234'
 * - email    = lowercase(first+last+rand4)@<random domain from $EMAIL_DOMAINS>
 * - auto-verified & active users
 *
 * SAFETY: Start with $DRY_RUN = true. Delete this file after use.
 */

date_default_timezone_set('Asia/Manila');

// --------------------- CONFIG ---------------------
$DRY_RUN   = false;         // preview first!
$NUM_USERS = 50;           // how many to create
$EMAIL_DOMAINS = [         // rotate through these (put your own here)
  'demo.bulaservicesgsc.com',
  'testmail.ph',
  'bulamock.local',
  'samplemail.ph',
  'devmail.local'
];

$USE_APP_CONFIG = true;    // use your app's getDBConnection()
// --------------------------------------------------

// ---- DB connection via your app config ----
if ($USE_APP_CONFIG) {
    // your script sits in .../userbulaservices/
    require_once __DIR__ . '/server/config.php';
    if (!function_exists('getDBConnection')) {
        fwrite(STDERR, "FATAL: getDBConnection() not found in server/config.php\n");
        exit(1);
    }
    try { $pdo = getDBConnection(); }
    catch (Throwable $e) { fwrite(STDERR, "DB connect failed via app config: ".$e->getMessage()."\n"); exit(1); }
} else {
    // Fallback manual DSN (only if you decide to use it)
    $dsn = 'mysql:host=127.0.0.1;dbname=bulaservicesfiles;charset=utf8mb4';
    $dbUser = 'YOURUSER';
    $dbPass = 'YOURPASS';
    try { $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
    catch (Throwable $e) { fwrite(STDERR, "DB connect failed: ".$e->getMessage()."\n"); exit(1); }
}

// ------------------ Helpers ------------------
function slugify(string $s): string {
    $s = trim($s);
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($t !== false) $s = $t;
    return preg_replace('/[^A-Za-z0-9]/', '', $s);
}
function make_username_base(string $first, string $last): string {
    $base = strtolower(slugify($first.$last));
    return $base === '' ? 'user' : $base;
}
function make_plain_password(string $first): string {
    $p = strtolower(slugify($first));
    if ($p === '') $p = 'user';
    return $p.'1234';
}
function ensure_unique_username(PDO $pdo, string $base): string {
    $maxLen = 50;
    $check = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $u = substr($base, 0, $maxLen);
    $check->execute([':u'=>$u]);
    if (!$check->fetch()) return $u;
    for ($n=2; $n<=9999; $n++) {
        $cand = substr($base, 0, $maxLen - strlen((string)$n)) . $n;
        $check->execute([':u'=>$cand]);
        if (!$check->fetch()) return $cand;
    }
    return substr($base,0,$maxLen-6) . random_int(100000,999999);
}
function rand_choice(array $arr) { return $arr[array_rand($arr)]; }

// Random email via rotating domains (ensures uniqueness in DB)
function make_random_email(PDO $pdo, string $first, string $last, array $domains, int $idx): string {
    $base = strtolower(slugify($first.$last));
    if ($base === '') $base = 'user';
    $sel = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $tries = 0;
    do {
        $domain = rand_choice($domains);
        $rand4  = str_pad((string)rand(0,9999), 4, '0', STR_PAD_LEFT);
        $candidate = $base.$rand4.'@'.$domain;
        $sel->execute([':email'=>$candidate]);
        $exists = (bool)$sel->fetch();
        $tries++;
        if ($tries > 40) {
            $candidate = $base.$idx.uniqid().'@'.$domain;
            break;
        }
    } while ($exists);
    return $candidate;
}

// ------------------ Name Pools (expanded) ------------------
// Common Filipino given names + some double-first-name patterns and middle initials
$firstMale = [
  'Juan','Carlos','Mark','Jose','Ernesto','Ramon','Edu','Miguel','Daniel','Rodel','Renato','Alvin','Benjie','Dante',
  'Frederick','Hector','Isidro','Joey','Karlo','Leandro','Mario','Nestor','Oscar','Peter','Quincy','Adrian','Bryan',
  'Christian','Dominic','Edward','Francis','Gabriel','Harold','Ian','Jasper','Kevin','Lawrence','Matthew','Noel',
  'Oliver','Patrick','Rafael','Samuel','Timothy','Ulysses','Victor','Warren','Xavier','Yancy','Zedrick'
];
$firstFemale = [
  'Maria','Kristine','Sheila','Analyn','Grace','Lara','Carmela','Roselyn','Jocelyn','Liza','May','Nina','Patricia',
  'Rona','Sofia','Teresita','Vanessa','Xenia','Yvonne','Zara','Carla','Estela','Faye','Gina','Hannah','Irene','Jenny',
  'Katrina','Lourdes','Mika','Noemi','Olive','Pauline','Queenie','Rhea','Shaira','Trisha','Ursula','Veronica',
  'Winnie','Ximena','Yvette','Zelda'
];
// More diverse surnames, plus hyphenation candidates
$lastNamesBase = [
  'Dela Cruz','Santos','Reyes','Garcia','Villanueva','Lopez','Martinez','Ramos','Navarro','Mendoza','Cruz','Torres',
  'Delos Santos','Aguilar','Alcantara','Bautista','Castillo','Espinosa','Gonzales','Ilagan','Jimenez','Karim','Luna',
  'Morales','Nava','Ortega','Pascua','Quiambao','Rivera','Salazar','Tamayo','Umali','Valdez','Webb','Yap','Zamora'
];
$lastNamesExtra = ['Manalo','Roque','Serrano','Tiongson','Uy','Villar','Abad','Beltran','Cortez','De Guzman','Enriquez','Flores','Gadia','Hermosa'];
$lastNamesForHyphen = array_merge($lastNamesBase, $lastNamesExtra);

// Address pools
$puroks = array_map(fn($n) => "Purok $n", range(1, 22));
$streets = [
  'Damicog Street','Bulaong Avenue','Royeca Boulevard','Gonzales St','Rajah Muda',
  'Asai Village','Gensan Ville','Market Road','San Miguel St','Palanan St',
  'Fernandez St','Liberty Lane','Santo Niño St','Mabini St','Roxas St'
];
$occupations = [
  'Student','Farmer','Fisherman','Vendor','Barangay Worker','Driver','Teacher',
  'Unemployed','Security Guard','Clerk','Nurse Assistant','Small Business Owner',
  'Construction Worker','Call Center Agent','IT Support','Housekeeper','Sales Associate'
];

// ------------------ Generate unique people ------------------
$seenNameCombos = []; // to ensure unique (first,last) within this run
$people = [];
for ($i=0; $i<$NUM_USERS; $i++) {
    $isMale = ($i % 2 === 0);
    $first = $isMale ? rand_choice($firstMale) : rand_choice($firstFemale);

    // Occasionally double first names or add middle initial for variety
    if (rand(1,6) === 1) {
        $addon = $isMale ? rand_choice($firstMale) : rand_choice($firstFemale);
        if ($addon !== $first) $first = $first . ' ' . $addon;          // double first name
    } elseif (rand(1,6) === 1) {
        $first .= ' ' . chr(rand(65, 90)) . '.';                         // middle initial
    }

    // Surnames: sometimes hyphenate two different surnames
    if (rand(1,7) === 1) {
        $l1 = rand_choice($lastNamesForHyphen);
        $l2 = rand_choice($lastNamesForHyphen);
        if ($l1 !== $l2) $last = $l1 . '-' . $l2;
        else $last = rand_choice($lastNamesBase);
    } else {
        $last = rand_choice($lastNamesBase);
    }

    // Ensure unique full-name combo in this batch
    $key = strtolower(trim($first.'|'.$last));
    $tries = 0;
    while (isset($seenNameCombos[$key]) && $tries < 20) {
        // tweak last name or add another initial until unique
        if (rand(0,1)) {
            $last = rand_choice($lastNamesBase);
        } else {
            $first .= ' ' . chr(rand(65, 90)) . '.';
        }
        $key = strtolower(trim($first.'|'.$last));
        $tries++;
    }
    $seenNameCombos[$key] = true;

    // Age & birthdate
    $age = rand(18, 65);
    $year  = (int)date('Y') - $age;
    $month = rand(1, 12);
    $day   = rand(1, 28);
    $birth_date = sprintf('%04d-%02d-%02d', $year, $month, $day);

    $gender = $isMale ? 'male' : 'female';
    $purok  = rand_choice($puroks);
    $street = rand_choice($streets);
    $houseNo = rand(1, 180);
    $address = "{$houseNo} {$street}, Barangay Bula, General Santos City";
    $contact = sprintf('09%02d%03d%04d', rand(10,99), rand(100,999), rand(1000,9999));
    $occupation = rand_choice($occupations);
    $people[] = [
        'first_name' => $first,
        'middle_name'=> null,
        'last_name'  => $last,
        'birth_date' => $birth_date,
        'age'        => $age,
        'civil_status' => 'single',
        'gender'     => $gender,
        'purok'      => $purok,
        'year_started_staying' => rand(2000, (int)date('Y')-1),
        'contact_number' => $contact,
        'occupation' => $occupation,
        'address'   => $address,
    ];
}

// ------------------ DB statements ------------------
$insertUser = $pdo->prepare("
    INSERT INTO users
    (user_type, first_name, middle_name, last_name, suffix, birth_place, birth_date, age, civil_status, gender, purok,
     year_started_staying, contact_number, occupation, address, email, username, password, profile_picture,
     is_active, is_verified, verification_token, reset_token, reset_token_expiry, last_login, failed_login_attempts,
     account_locked_until, created_at, updated_at, email_verified, verified_at, terms_accepted_at, status,
     reset_token_hash, reset_token_expires, reset_token_used)
    VALUES
    (:user_type, :first_name, :middle_name, :last_name, :suffix, :birth_place, :birth_date, :age, :civil_status, :gender, :purok,
     :year_started_staying, :contact_number, :occupation, :address, :email, :username, :password, :profile_picture,
     :is_active, :is_verified, :verification_token, :reset_token, :reset_token_expiry, :last_login, :failed_login_attempts,
     :account_locked_until, :created_at, :updated_at, :email_verified, :verified_at, :terms_accepted_at, :status,
     :reset_token_hash, :reset_token_expires, :reset_token_used)
");

$consumeEV = $pdo->prepare("UPDATE email_verifications SET consumed_at = :consumed_at WHERE user_id = :uid AND consumed_at IS NULL");

// ------------------ Insert loop ------------------
$now = (new DateTime())->format('Y-m-d H:i:s');
$created = [];

foreach ($people as $idx => $u) {
    $first = $u['first_name'];
    $last  = $u['last_name'];

    $usernameBase = make_username_base($first, $last);
    $username     = ensure_unique_username($pdo, $usernameBase);

    $email = make_random_email($pdo, $first, $last, $EMAIL_DOMAINS, $idx+1);

    $plain = make_plain_password($first);
    $hash  = password_hash($plain, PASSWORD_DEFAULT);

    $params = [
        ':user_type' => 'resident',
        ':first_name' => $first,
        ':middle_name' => $u['middle_name'],
        ':last_name' => $last,
        ':suffix' => null,
        ':birth_place' => 'General Santos',
        ':birth_date' => $u['birth_date'],
        ':age' => $u['age'],
        ':civil_status' => $u['civil_status'],
        ':gender' => $u['gender'],
        ':purok' => $u['purok'],
        ':year_started_staying' => $u['year_started_staying'],
        ':contact_number' => $u['contact_number'],
        ':occupation' => $u['occupation'],
        ':address' => $u['address'],
        ':email' => $email,
        ':username' => $username,
        ':password' => $hash,
        ':profile_picture' => null,
        ':is_active' => 1,
        ':is_verified' => 1,
        ':verification_token' => null,
        ':reset_token' => null,
        ':reset_token_expiry' => null,
        ':last_login' => null,
        ':failed_login_attempts' => 0,
        ':account_locked_until' => null,
        ':created_at' => $now,
        ':updated_at' => $now,
        ':email_verified' => 1,
        ':verified_at' => $now,
        ':terms_accepted_at' => $now,
        ':status' => 'active',
        ':reset_token_hash' => null,
        ':reset_token_expires' => null,
        ':reset_token_used' => 0,
    ];

    if ($DRY_RUN) {
        $created[] = [
            'first_name' => $first,
            'last_name'  => $last,
            'username'   => $username,
            'password'   => $plain,
            'email'      => $email,
            'address'    => $u['address'],
        ];
        continue;
    }

    try {
        $pdo->beginTransaction();
        $insertUser->execute($params);
        $newId = (int)$pdo->lastInsertId();
        $consumeEV->execute([':consumed_at' => $now, ':uid' => $newId]);
        $pdo->commit();
        $created[] = [
            'id' => $newId,
            'first_name' => $first,
            'last_name'  => $last,
            'username'   => $username,
            'password'   => $plain,
            'email'      => $email,
            'address'    => $u['address'],
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Insert failed for {$first} {$last}: ".$e->getMessage()."\n");
    }
}

// ------------------ Output ------------------
if ($DRY_RUN) {
    echo "DRY RUN: Preview of {$NUM_USERS} users (no DB changes):\n";
    echo "first_name,last_name,username,plain_password,email,address\n";
    foreach ($created as $c) {
        echo sprintf("%s,%s,%s,%s,%s,%s\n",
            $c['first_name'],$c['last_name'],$c['username'],$c['password'],$c['email'],$c['address']
        );
    }
    echo "End preview. Set \$DRY_RUN = false to apply.\n";
} else {
    echo "Created ".count($created)." demo users:\n";
    echo "id,first_name,last_name,username,plain_password,email,address\n";
    foreach ($created as $c) {
        echo sprintf("%s,%s,%s,%s,%s,%s\n",
            $c['id'],$c['first_name'],$c['last_name'],$c['username'],$c['password'],$c['email'],$c['address']
        );
    }
    echo "Done. Delete this script after verifying.\n";
}
