<?php
session_start();
require '../config/dbh.inc.php';

if (!isset($_SESSION['UserId'])) {
    $_SESSION['errors']['auth'] = "Unauthorized access.";
    header("Location: staff_login.php");
    exit();
}

// Get form values
$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$diagnosis_type = $_POST['diagnosis-type'] ?? null;
$prognosis = $_POST['prognosis'] ?? null;
$diagnosis = trim($_POST['diagnosis'] ?? '');
$treatment = trim($_POST['treatment'] ?? '');

if (!$appointment_id || !$pet_id || !$diagnosis_type || !$prognosis || empty($diagnosis) || empty($treatment)) {
    $_SESSION['errors']['missing_data'] = "All fields are required.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

try {
    $pdo->beginTransaction();

    // Fetch RecordId for the given Appointment and Pet
    $stmt = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id LIMIT 1");
    $stmt->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("No matching patient record found.");
    }

    $record_id = $record['RecordId'];

    // ✅ Check if a diagnosis already exists for this RecordId
    $stmt = $pdo->prepare("SELECT DiagnosisId FROM Diagnoses WHERE RecordId = :record_id LIMIT 1");
    $stmt->execute([':record_id' => $record_id]);
    $existingDiagnosis = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingDiagnosis) {
        // ✅ Update existing diagnosis
        $stmt = $pdo->prepare("
            UPDATE Diagnoses 
            SET DiagnosisType = :diagnosis_type, 
                Diagnosis = :diagnosis, 
                Treatment = :treatment, 
                Prognosis = :prognosis,
                Status = 'Under Treatment'
            WHERE RecordId = :record_id
        ");
    } else {
        // ✅ Insert new diagnosis
        $stmt = $pdo->prepare("
            INSERT INTO Diagnoses (RecordId, DiagnosisType, Diagnosis, Treatment, Prognosis, Status)
            VALUES (:record_id, :diagnosis_type, :diagnosis, :treatment, :prognosis, 'Pending')
        ");
    }

    $stmt->execute([
        ':record_id' => $record_id,
        ':diagnosis_type' => $diagnosis_type,
        ':diagnosis' => $diagnosis,
        ':treatment' => $treatment,
        ':prognosis' => $prognosis
    ]);

    $pdo->commit();

    // ✅ Fetch updated diagnosis data for display
    $stmt = $pdo->prepare("SELECT * FROM Diagnoses WHERE RecordId = :record_id");
    $stmt->execute([':record_id' => $record_id]);
    $_SESSION['diagnosis_data'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['success_diagnosis'] = "Diagnosis saved successfully.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['errors']['db'] = "Database Error: " . $e->getMessage();
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}
?>