<?php
session_start();
require '../config/dbh.inc.php';

$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$medications = $_POST['medications'] ?? [];

if (!$appointment_id || !$pet_id) {
    $_SESSION['errors']['medication'] = "Missing required information.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

try {
    $pdo->beginTransaction();

    // Delete previous records to avoid duplicates
    $stmtDelete = $pdo->prepare("DELETE FROM MedicationsGiven WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
    $stmtDelete->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);

    // Insert the selected medications into the database
    $stmtInsert = $pdo->prepare("INSERT INTO MedicationsGiven (AppointmentId, PetId, MedicationName) VALUES (:appointment_id, :pet_id, :medication_name)");

    foreach ($medications as $medication) {
        $stmtInsert->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id,
            ':medication_name' => $medication
        ]);
    }

    // Store the selected medications in the session to retain values after submission
    $_SESSION['medication_data']['medications'] = $medications;

    $pdo->commit();
    $_SESSION['success_medication'] = "Medications saved successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['errors']['medication'] = "Database Error: " . $e->getMessage();
}

header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
exit();