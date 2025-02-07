<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);
session_start();
require '../config/dbh.inc.php';

$appointment_id = $_POST['appointment_id'] ?? null;
$pet_id = $_POST['pet_id'] ?? null;

if ($appointment_id && $pet_id) {
    try {
        $pdo->beginTransaction();

        // Update appointment status to Completed
        $stmt = $pdo->prepare("UPDATE Appointments SET Status = 'Done' WHERE AppointmentId = ?");
        $stmt->execute([$appointment_id]);

        // Clear session data related to this appointment
        unset($_SESSION['chief_complaint_data']);
        unset($_SESSION['medical_history_data']);
        unset($_SESSION['physical_exam_data']);
        unset($_SESSION['lab_exam_data']);
        unset($_SESSION['diagnosis_data']);
        unset($_SESSION['medication_data']);
        unset($_SESSION['follow_up_data']);
        unset($_SESSION['appointment_id']);

        $pdo->commit();

        $_SESSION['success_message'] = "Consultation successfully finished!";
        header("Location: ../public/pet_profile.php?pet_id=$pet_id");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error finishing consultation: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to complete the consultation.";
        header("Location: ../public/pet_profile.php?pet_id=$pet_id");
        exit();
    }
}
?>