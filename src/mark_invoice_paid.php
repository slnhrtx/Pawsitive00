<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['invoice_id'])) {
    echo json_encode(["success" => false, "message" => "⚠️ Missing invoice ID."]);
    exit;
}

$invoiceId = intval($data['invoice_id']);

// ✅ Check if the invoice exists and is unpaid
$checkInvoice = $pdo->prepare("SELECT AppointmentId, Status FROM Invoices WHERE InvoiceId = ?");
$checkInvoice->execute([$invoiceId]);
$invoice = $checkInvoice->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo json_encode(["success" => false, "message" => "⚠️ Invoice not found."]);
    exit;
}

if ($invoice['Status'] === 'Paid') {
    echo json_encode(["success" => false, "message" => "⚠️ This invoice is already marked as paid."]);
    exit;
}

$appointmentId = $invoice['AppointmentId'];

try {
    $pdo->beginTransaction();

    // ✅ Update the invoice status to "Paid"
    $stmt = $pdo->prepare("UPDATE Invoices SET Status = 'Paid', PaidAt = NOW() WHERE InvoiceId = ?");
    $stmt->execute([$invoiceId]);

    // ✅ Update the appointment status to "Paid"
    $updateAppointment = $pdo->prepare("UPDATE Appointments SET Status = 'Paid' WHERE AppointmentId = ?");
    $updateAppointment->execute([$appointmentId]);

    $pdo->commit();

    echo json_encode(["success" => true, "message" => "✅ Invoice and appointment updated to Paid successfully!"]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "❌ Database error: " . $e->getMessage()]);
}
?>