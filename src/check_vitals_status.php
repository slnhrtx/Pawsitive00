<?php
require '../config/dbh.inc.php';
header('Content-Type: application/json');

$appointmentId = $_GET['appointment_id'] ?? null;
$petId = $_GET['pet_id'] ?? null;

if (!$appointmentId || !$petId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM PetWeights WHERE PetId = ? AND DATE(RecordedAt) = CURDATE() LIMIT 1");
$stmt->execute([$petId]);
$alreadyRecorded = $stmt->fetchColumn();

echo json_encode(['success' => true, 'alreadyRecorded' => (bool) $alreadyRecorded]);
?>