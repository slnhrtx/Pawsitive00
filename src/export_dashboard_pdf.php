<?php
require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php'; // Include TCPDF

use TCPDF;

// =============================
// ✅ Initialize PDF
// =============================
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Pawsitive Dashboard');
$pdf->SetTitle('Dashboard Report');
$pdf->SetHeaderData('', 0, 'Pawsitive Dashboard Report', 'Generated on: ' . date('Y-m-d'));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();

// =============================
// 📊 Total Appointments Data
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Total Appointments per Month', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

$query = "SELECT DATE_FORMAT(AppointmentDate, '%Y-%m') AS Month, COUNT(*) AS Count 
          FROM Appointments 
          WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
          GROUP BY Month
          ORDER BY Month";
$stmt = $pdo->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Table Header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 8, 'Month', 1, 0, 'C');
$pdf->Cell(60, 8, 'Total Appointments', 1, 1, 'C');

// Table Data
$pdf->SetFont('helvetica', '', 10);
foreach ($appointments as $data) {
    $pdf->Cell(60, 8, $data['Month'], 1, 0, 'C');
    $pdf->Cell(60, 8, $data['Count'], 1, 1, 'C');
}

$pdf->Ln(10);

// =============================
// 🐾 Registered Pets Data
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Total Registered Pets', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

$query = "SELECT SpeciesName, COUNT(*) AS Count 
          FROM Pets 
          INNER JOIN Species ON Pets.SpeciesId = Species.Id
          WHERE Pets.IsArchived = 0
          GROUP BY SpeciesName";
$stmt = $pdo->prepare($query);
$stmt->execute();
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Table Header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 8, 'Pet Type', 1, 0, 'C');
$pdf->Cell(60, 8, 'Total Registered', 1, 1, 'C');

// Table Data
$pdf->SetFont('helvetica', '', 10);
foreach ($pets as $data) {
    $pdf->Cell(60, 8, $data['SpeciesName'], 1, 0, 'C');
    $pdf->Cell(60, 8, $data['Count'], 1, 1, 'C');
}

$pdf->Ln(10);

// =============================
// 💉 Vaccination Records
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Vaccination Records', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

$query = "SELECT p.Name AS PetName, v.VaccinationName, v.VaccinationDate, v.Manufacturer, v.LotNumber, v.Notes 
          FROM PetVaccinations v
          INNER JOIN Pets p ON v.PetId = p.PetId
          ORDER BY v.VaccinationDate DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Table Header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'Pet Name', 1, 0, 'C');
$pdf->Cell(50, 8, 'Vaccine', 1, 0, 'C');
$pdf->Cell(30, 8, 'Date', 1, 0, 'C');
$pdf->Cell(30, 8, 'Manufacturer', 1, 1, 'C');

// Table Data
$pdf->SetFont('helvetica', '', 10);
foreach ($vaccinations as $data) {
    $pdf->Cell(40, 8, $data['PetName'], 1, 0, 'C');
    $pdf->Cell(50, 8, $data['VaccinationName'], 1, 0, 'C');
    $pdf->Cell(30, 8, $data['VaccinationDate'], 1, 0, 'C');
    $pdf->Cell(30, 8, $data['Manufacturer'], 1, 1, 'C');
}

// =============================
// ✅ Save and Export PDF
// =============================
$pdf->Output('dashboard_report_' . date('Y-m-d') . '.pdf', 'D');
exit;
?>