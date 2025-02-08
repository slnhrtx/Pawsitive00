<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';

// Set the Content-Type to JSON
header('Content-Type: application/json');

// Validate input
$species_id = filter_input(INPUT_GET, 'SpeciesId', FILTER_VALIDATE_INT);

if (!$species_id) {
    // Return an empty array if SpeciesId is invalid or missing
    echo json_encode([]);
    exit();
}

try {
    // Prepare query to fetch breeds based on SpeciesId
    $query = "SELECT BreedId, BreedName FROM Breeds WHERE SpeciesId = :SpeciesId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':SpeciesId', $species_id, PDO::PARAM_INT);
    $stmt->execute();

    // Fetch and format results
    $breeds = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $breeds[] = [
            'BreedId' => $row['BreedId'],      // Use the database column names for consistency
            'BreedName' => $row['BreedName']  // Corrected key name for clarity
        ];
    }

    // Return results as JSON
    echo json_encode($breeds);

} catch (PDOException $e) {
    // Log error for debugging purposes
    error_log("Error fetching breeds: " . $e->getMessage());
    
    // Return an empty array on failure
    echo json_encode([]);
}