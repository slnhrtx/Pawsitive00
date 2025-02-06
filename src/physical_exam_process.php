<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/dbh.inc.php'; // Database connection

// Ensure user is logged in
if (!isset($_SESSION['UserId'])) {
    $_SESSION['errors']['auth'] = "Unauthorized access.";
    header("Location: staff_login.php");
    exit();
}

// Get appointment and pet ID (ensure they are integers)
$appointment_id = isset($_POST['appointment_id']) && $_POST['appointment_id'] !== '' ? (int) $_POST['appointment_id'] : null;
$pet_id = isset($_POST['pet_id']) && $_POST['pet_id'] !== '' ? (int) $_POST['pet_id'] : null;

if (!$appointment_id || !$pet_id) {
    $_SESSION['errors']['missing_data'] = "Missing required information.";
    header("Location: ../public/patient_records.php");
    exit();
}

// Fetch RecordId from PatientRecords
$stmt = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
$stmt->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
$record_id = $record['RecordId'] ?? null;

if (!$record_id) {
    $_SESSION['errors']['db'] = "No matching record found.";
    header("Location: ../public/patient_records.php");
    exit();
}

// Convert empty strings to NULL for numeric values
function sanitize_input($value, $is_numeric = false) {
    return isset($value) && $value !== '' ? ($is_numeric ? (int) $value : htmlspecialchars($value, ENT_QUOTES)) : null;
}

// Process form inputs
$examData = [
    ':record_id' => $record_id,
    ':pulse' => sanitize_input($_POST['pulse'] ?? null, true),
    ':heart_rate' => sanitize_input($_POST['heart-rate'] ?? null, true),
    ':respiratory_rate' => sanitize_input($_POST['respiratory-rate'] ?? null, true),
    ':heart_sound' => sanitize_input($_POST['heart-sound'] ?? null),
    ':lung_sound' => sanitize_input($_POST['lung-sound'] ?? null),
    ':mucous_membrane' => sanitize_input($_POST['mucous-membrane'] ?? null),
    ':crt' => sanitize_input($_POST['capillary-refill-time'] ?? null),
    ':eyes' => sanitize_input($_POST['eyes'] ?? null),
    ':ears' => sanitize_input($_POST['ears'] ?? null),
    ':tracheal_pinch' => sanitize_input($_POST['tracheal-pinch'] ?? null),
    ':abdominal_palpation' => sanitize_input($_POST['abdominal-palpation'] ?? null),
    ':ln' => sanitize_input($_POST['ln'] ?? null),
    ':bcs' => sanitize_input($_POST['bcs'] ?? null, true)
];

try {
    $pdo->beginTransaction();

    // Check if a Physical Exam record already exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM PhysicalExams WHERE RecordId = :record_id");
    $stmtCheck->execute([':record_id' => $record_id]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists > 0) {
        // UPDATE existing record
        $stmt = $pdo->prepare("UPDATE PhysicalExams 
            SET Pulse = :pulse, 
                HeartRate = :heart_rate, 
                RespiratoryRate = :respiratory_rate, 
                HeartSound = :heart_sound, 
                LungSound = :lung_sound, 
                MucousMembrane = :mucous_membrane, 
                CapillaryRefillTime = :crt,
                Eyes = :eyes,
                Ears = :ears,
                TrachealPinch = :tracheal_pinch,
                AbdominalPalpation = :abdominal_palpation,
                LN = :ln,
                BCS = :bcs
            WHERE RecordId = :record_id");
    } else {
        // INSERT new record
        $stmt = $pdo->prepare("INSERT INTO PhysicalExams (
            RecordId, Pulse, HeartRate, RespiratoryRate, HeartSound, LungSound, 
            MucousMembrane, CapillaryRefillTime, Eyes, Ears, TrachealPinch, 
            AbdominalPalpation, LN, BCS) 
        VALUES (
            :record_id, :pulse, :heart_rate, :respiratory_rate, :heart_sound, :lung_sound, 
            :mucous_membrane, :crt, :eyes, :ears, :tracheal_pinch, 
            :abdominal_palpation, :ln, :bcs)");
    }

    $stmt->execute($examData);
    $pdo->commit();

    $_SESSION['success_message'] = "Physical Examination saved successfully.";

    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['errors']['db'] = "Database Error: " . $e->getMessage();
    
    // Debugging output
    echo "<pre>";
    print_r([
        'SQL Error' => $e->getMessage(),
        'Data' => $examData
    ]);
    echo "</pre>";
    exit();
}
?>