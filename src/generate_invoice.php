<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';

checkAuthentication($pdo);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['appointment_id']) || !isset($data['pet_id'])) {
    echo json_encode(["success" => false, "message" => "⚠️ Missing required fields."]);
    exit;
}

$appointmentId = intval($data['appointment_id']);
$petId = intval($data['pet_id']);

// ✅ Step 1: Check if an invoice already exists for this appointment & pet
$checkInvoice = $pdo->prepare("SELECT COUNT(*) FROM Invoices WHERE AppointmentId = ? AND PetId = ?");
$checkInvoice->execute([$appointmentId, $petId]);
$invoiceExists = $checkInvoice->fetchColumn();

if ($invoiceExists > 0) {
    // ❌ Prevent duplicate invoice generation
    echo json_encode(["success" => false, "message" => "⚠️ An invoice has already been generated for this appointment."]);
    exit;
}

// ✅ Step 2: Generate a new invoice
$invoiceNumber = 'INV-' . time(); // Unique invoice number
$vaccineCost = 50.00; // Adjust cost as needed

try {
    $stmt = $pdo->prepare("
        INSERT INTO Invoices (AppointmentId, PetId, InvoiceNumber, InvoiceDate, TotalAmount, Status) 
        VALUES (?, ?, ?, NOW(), ?, 'Pending')
    ");
    $stmt->execute([$appointmentId, $petId, $invoiceNumber, $vaccineCost]);

    echo json_encode(["success" => true, "message" => "✅ Invoice generated successfully!"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "❌ Database error: " . $e->getMessage()]);
}
?>