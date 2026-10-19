<?php
/**
 * PrimeCare — Seed script (command line only)
 * ---------------------------------------------------------
 *   php database/seed-staff.php          doctors + staff logins only
 *   php database/seed-staff.php --demo   ...plus demo data so EVERY table
 *                                        has at least 10 records
 * Safe to run more than once (it skips anything that already exists).
 *
 * Run AFTER database/schema.sql and database/staff_migration.sql.
 * CHANGE THESE PASSWORDS before any real deployment.
 *
 * Patient demo logins on the website:  <first>.<last>@example.com / Patient123!
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require_once __DIR__ . '/../api/config/db.php';
date_default_timezone_set('Africa/Johannesburg');
$demo = in_array('--demo', $argv ?? [], true);

function one(PDO $pdo, string $sql, array $p = []) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchColumn();
}
function count_rows(PDO $pdo, string $table): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
}

// ---------------------------------------------------------------- departments (>= 10)
$departments = [
    'General Practice', 'Cardiology', 'Paediatrics', 'Preventive Care', 'Dermatology',
    'Orthopaedics', 'Ophthalmology', 'Gynaecology', 'Psychiatry', 'Dentistry',
    'Radiology', 'Physiotherapy',
];
foreach ($departments as $name) {
    if (!one($pdo, 'SELECT 1 FROM departments WHERE department_name = :n', ['n' => $name])) {
        $pdo->prepare('INSERT INTO departments (department_name, description) VALUES (:n, :d)')
            ->execute(['n' => $name, 'd' => "$name services at PrimeCare"]);
    }
}
function dept(PDO $pdo, string $name): ?int {
    $id = one($pdo, 'SELECT department_id FROM departments WHERE department_name = :n', ['n' => $name]);
    return $id === false ? null : (int) $id;
}

// ---------------------------------------------------------------- doctors (10)
// [first, last, specialty/department, status, login email or null]
$doctors = [
    ['Tendai', 'Moyo',     'General Practice', 'Active',   'doctor@primecare.com'],
    ['Lerato', 'Molefe',   'Paediatrics',      'Active',   'lerato@primecare.com'],
    ['Sipho',  'Nkosi',    'Cardiology',       'Active',   'sipho@primecare.com'],
    ['Zanele', 'Khumalo',  'Preventive Care',  'Inactive', null],
    ['Ayesha', 'Patel',    'Dermatology',      'Active',   'ayesha@primecare.com'],
    ['Pieter', 'van Wyk',  'Orthopaedics',     'Active',   'pieter@primecare.com'],
    ['Nomsa',  'Dlamini',  'Gynaecology',      'Active',   'nomsa@primecare.com'],
    ['Kabelo', 'Mokoena',  'Psychiatry',       'Active',   'kabelo@primecare.com'],
    ['Emma',   'Fourie',   'Dentistry',        'Active',   'emma@primecare.com'],
    ['Themba', 'Zulu',     'Ophthalmology',    'Inactive', null],
];
$doctorIds = [];
foreach ($doctors as [$first, $last, $spec, $status, $login]) {
    $email = strtolower("$first.$last") . '@primecare.com';
    $email = str_replace(' ', '', $email);
    $id = one($pdo, 'SELECT doctor_id FROM doctors WHERE email = :e', ['e' => $email]);
    if (!$id) {
        $pdo->prepare('INSERT INTO doctors (first_name,last_name,specialty,department_id,email,status)
                       VALUES (:f,:l,:s,:d,:e,:st)')
            ->execute(['f' => $first, 'l' => $last, 's' => $spec, 'd' => dept($pdo, $spec), 'e' => $email, 'st' => $status]);
        $id = $pdo->lastInsertId();
        echo "doctor created: Dr $first $last\n";
    }
    $doctorIds[$first] = (int) $id;
}

// ---------------------------------------------------------------- staff logins (10)
$staff = [
    ['admin@primecare.com',     'admin123', 'ADMIN',  'Admin',    'User',    null],
    ['reception@primecare.com', 'admin123', 'ADMIN',  'Reception','Desk',    null],
];
foreach ($doctors as [$first, $last, , , $login]) {
    if ($login !== null) { $staff[] = [$login, 'doc123', 'DOCTOR', $first, $last, $doctorIds[$first]]; }
}
foreach ($staff as [$email, $pw, $role, $first, $last, $docId]) {
    if (one($pdo, 'SELECT staff_id FROM staff WHERE email = :e', ['e' => $email])) { continue; }
    $pdo->prepare('INSERT INTO staff (email,password_hash,role,first_name,last_name,doctor_id) VALUES (:e,:h,:r,:f,:l,:d)')
        ->execute(['e' => $email, 'h' => password_hash($pw, PASSWORD_DEFAULT), 'r' => $role, 'f' => $first, 'l' => $last, 'd' => $docId]);
    echo "staff login created: $email / $pw ($role)\n";
}

if (!$demo) { echo "Done. (Add --demo for patients, appointments, records and prescriptions.)\n"; exit; }

// ---------------------------------------------------------------- patients (12)
$patients = [
    ['Naledi','Dube'], ['Johan','Kruger'], ['Sarah','Jansen'], ['Precious','Zulu'],
    ['Thabo','Mahlangu'], ['Lindiwe','Sithole'], ['Maria','Garcia'], ['David','Wilson'],
    ['Fatima','Hendricks'], ['Bongani','Ndlovu'], ['Chloe','Botha'], ['Ravi','Naidoo'],
];
$patientIds = [];
foreach ($patients as $i => [$first, $last]) {
    $username = strtolower($first . '.' . $last);
    $email = $username . '@example.com';
    $id = one($pdo, 'SELECT patient_id FROM patients WHERE email = :e', ['e' => $email]);
    if (!$id) {
        $pdo->prepare('INSERT INTO patients (first_name,last_name,email,phone,username,password_hash) VALUES (:f,:l,:e,:p,:u,:h)')
            ->execute(['f' => $first, 'l' => $last, 'e' => $email, 'p' => '082 555 ' . sprintf('%04d', 1000 + $i),
                       'u' => $username, 'h' => password_hash('Patient123!', PASSWORD_DEFAULT)]);
        $id = $pdo->lastInsertId();
    }
    $patientIds[] = (int) $id;
}
echo "patients ready (" . count_rows($pdo, 'patients') . ")\n";

// ---------------------------------------------------------------- today's appointments (10, all Booked)
$gp = 'General Practice';
$today = [
    // [patient index, time, reason, department, doctor first name or null]
    [0, '08:30:00', 'Regular check-up',      $gp,           'Tendai'],
    [1, '09:00:00', 'Blood pressure review', 'Cardiology',  'Sipho'],
    [2, '09:30:00', 'Flu symptoms',          'Paediatrics', 'Lerato'],
    [3, '10:00:00', 'Diabetes follow-up',    $gp,           'Tendai'],
    [4, '10:30:00', 'General consultation',  $gp,           null],
    [5, '11:00:00', 'Skin rash',             'Dermatology', 'Ayesha'],
    [6, '11:30:00', 'Chest tightness',       'Cardiology',  'Sipho'],
    [7, '13:00:00', 'Headache',              $gp,           'Tendai'],
    [8, '14:00:00', 'Knee pain',             'Orthopaedics','Pieter'],
    [9, '15:00:00', 'Medication review',     $gp,           null],
];
foreach ($today as [$pi, $time, $reason, $dep, $docFirst]) {
    if (one($pdo, 'SELECT 1 FROM appointments WHERE patient_id=:p AND appointment_date=:d AND appointment_time=:t',
            ['p' => $patientIds[$pi], 'd' => date('Y-m-d'), 't' => $time])) { continue; }
    $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,department_id,appointment_date,appointment_time,reason,status)
                   VALUES (:p,:doc,:dep,:d,:t,:r,'Booked')")
        ->execute(['p' => $patientIds[$pi], 'doc' => $docFirst ? $doctorIds[$docFirst] : null,
                   'dep' => dept($pdo, $dep), 'd' => date('Y-m-d'), 't' => $time, 'r' => $reason]);
}
echo "today's appointments ready\n";

// ---------------------------------------------------------------- history: 12 completed visits
// Each creates: appointment + medical record + consultation + prescription.
// The first 10 prescriptions also get a pending repeat request.
if (count_rows($pdo, 'medical_records') < 10) {
    $history = [
        ['Metformin 500mg',    '1 tablet · twice daily',  'Type 2 diabetes',        'Tendai'],
        ['Amlodipine 5mg',     '1 tablet · once daily',   'Hypertension',           'Sipho'],
        ['Salbutamol inhaler', '2 puffs · when needed',   'Asthma',                 'Lerato'],
        ['Atorvastatin 20mg',  '1 tablet · at night',     'High cholesterol',       'Tendai'],
        ['Levothyroxine 50mcg','1 tablet · each morning', 'Hypothyroidism',         'Tendai'],
        ['Hydrocortisone 1%',  'Apply thinly · twice daily','Eczema',               'Ayesha'],
        ['Ibuprofen 400mg',    '1 tablet · three times daily','Knee osteoarthritis','Pieter'],
        ['Sertraline 50mg',    '1 tablet · once daily',   'Anxiety disorder',       'Kabelo'],
        ['Folic acid 5mg',     '1 tablet · once daily',   'Antenatal care',         'Nomsa'],
        ['Omeprazole 20mg',    '1 capsule · before breakfast','Acid reflux',        'Tendai'],
        ['Cetirizine 10mg',    '1 tablet · once daily',   'Allergic rhinitis',      'Tendai'],
        ['Paracetamol 500mg',  '2 tablets · every 6 hours','Viral fever',           'Lerato'],
    ];
    foreach ($history as $i => [$med, $dose, $diagnosis, $docFirst]) {
        $pid = $patientIds[$i % count($patientIds)];
        $did = $doctorIds[$docFirst];
        $date = date('Y-m-d', strtotime('-' . (14 + $i * 3) . ' days'));
        $time = sprintf('%02d:00:00', 8 + ($i % 8));

        $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,department_id,appointment_date,appointment_time,reason,status)
                       VALUES (:p,:d,:dep,:date,:t,:r,'Completed')")
            ->execute(['p' => $pid, 'd' => $did, 'dep' => dept($pdo, 'General Practice'), 'date' => $date, 't' => $time, 'r' => $diagnosis . ' review']);
        $apptId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO medical_records (patient_id,doctor_id,appointment_id,consultation_date,diagnosis,treatment,notes,is_draft)
                       VALUES (:p,:d,:a,:date,:dx,:tx,:n,0)')
            ->execute(['p' => $pid, 'd' => $did, 'a' => $apptId, 'date' => $date, 'dx' => $diagnosis,
                       'tx' => 'Continue prescribed medication and review in 3 months.', 'n' => 'Patient stable. Advised on lifestyle changes.']);
        $recordId = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO consultations
                (appointment_id,patient_id,doctor_id,record_id,symptoms,blood_pressure,temperature,pulse,spo2,
                 medical_history,allergies,diagnosis,treatment,investigations,notes,status)
               VALUES (:a,:p,:d,:r,'Routine follow-up','120/80','36.6','72','98','None significant','None',:dx,:tx,'Bloods ordered','Patient stable.','Final')")
            ->execute(['a' => $apptId, 'p' => $pid, 'd' => $did, 'r' => $recordId, 'dx' => $diagnosis, 'tx' => 'Continue prescribed medication.']);

        $pdo->prepare("INSERT INTO prescriptions (record_id,patient_id,doctor_id,medication_name,dosage,instructions,date_issued,status)
                       VALUES (:r,:p,:d,:m,:dos,'Take as directed.',:date,'Active')")
            ->execute(['r' => $recordId, 'p' => $pid, 'd' => $did, 'm' => $med, 'dos' => $dose, 'date' => $date]);
        $presId = (int) $pdo->lastInsertId();

        if ($i < 10) {
            $pdo->prepare("INSERT INTO prescription_requests (prescription_id,patient_id,request_date,status)
                           VALUES (:pr,:p,CURDATE(),'Requested')")
                ->execute(['pr' => $presId, 'p' => $pid]);
        }
    }
    echo "history, consultations, prescriptions and repeat requests ready\n";
}

// ---------------------------------------------------------------- notifications (>= 10)
if (count_rows($pdo, 'notifications') < 10) {
    foreach ($patientIds as $pid) {
        $pdo->prepare("INSERT INTO notifications (patient_id,title,message,type)
                       VALUES (:p,'Welcome to PrimeCare','Your account is ready. You can book appointments online.','General')")
            ->execute(['p' => $pid]);
    }
}

echo "\nRow counts:\n";
foreach (['departments','doctors','staff','patients','appointments','medical_records','consultations',
          'prescriptions','prescription_requests','notifications'] as $t) {
    printf("  %-22s %d\n", $t, count_rows($pdo, $t));
}
echo "Done.\n";
