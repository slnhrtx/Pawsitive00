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

        $stmt = $pdo->prepare("UPDATE Appointments SET Status = 'Done' WHERE AppointmentId = ?");
        $stmt->execute([$appointment_id]);

        $stmt = $pdo->prepare("
            SELECT a.AppointmentDate, a.ServiceId, s.Price AS ServicePrice, p.OwnerId
            FROM Appointments a
            INNER JOIN Services s ON a.ServiceId = s.ServiceId
            INNER JOIN Pets p ON a.PetId = p.PetId
            WHERE a.AppointmentId = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            throw new Exception("Invalid appointment data.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO Invoices (AppointmentId, InvoiceNumber, InvoiceDate, TotalAmount, Status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$appointment_id, $invoice_number, $invoice_date, $total_amount]);

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