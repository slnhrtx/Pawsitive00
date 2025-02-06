<?php
require __DIR__ . '/../config/dbh.inc.php';
header('Content-Type: application/json');

$pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;

if ($pet_id <= 0) {
    echo json_encode(['error' => 'Invalid Pet ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT Weight, RecordedAt 
        FROM PetWeights 
        WHERE PetId = :pet_id 
        ORDER BY RecordedAt ASC
    ");
    $stmt->execute([':pet_id' => $pet_id]);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $weights]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>