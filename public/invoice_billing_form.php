<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
session_start();

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ .'/../src/helpers/validation_helpers.php';
require __DIR__ .'/../src/helpers/log.inc.php';
require __DIR__ .'/../src/helpers/permission.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: staff_login.php');
    exit();
}

if (!hasPermission('Process Payments')){
    die("Error: You do not have permission to access this page.");
}

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

// Fetch total number of completed appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Appointments WHERE Status = 'Done'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalRecords = $result['total'] ?? 0;

// Fetch Unpaid Vaccinations
$stmt = $pdo->prepare("
    SELECT pv.VaccineId, pv.VaccinationName, pv.VaccinationDate, pv.Status,
           p.Name AS PetName, CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName
    FROM PetVaccinations pv
    INNER JOIN Pets p ON pv.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    WHERE pv.Status = 'Unpaid'
    ORDER BY pv.VaccinationDate DESC
");

$stmt->execute();
$unpaidVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define pagination variables
$recordsPerPage = 10;  // Adjust as needed
$totalPages = ceil($totalRecords / $recordsPerPage);
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$startIndex = ($currentPage - 1) * $recordsPerPage;

// Fetch paginated results
$stmt = $pdo->prepare("
    SELECT a.AppointmentId, a.AppointmentDate, a.AppointmentTime, 
           p.Name AS PetName, o.FirstName AS OwnerFirstName, o.LastName AS OwnerLastName,
           s.ServiceName, a.Status
    FROM Appointments a
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE a.Status = 'Done'
    ORDER BY a.AppointmentDate DESC
    LIMIT :startIndex, :recordsPerPage
");

$stmt->bindValue(':startIndex', $startIndex, PDO::PARAM_INT);
$stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$doneAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/invoice_billing_form.css">
</head>

<body>
<div class="sidebar">
        <div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
            <ul class="nav-links">
                <li><a href="main_dashboard.php">
                    <img src="../assets/images/Icons/Chart 1.png" alt="Chart Icon">Overview</a></li>
                <li><a href="record.php">
                    <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
                    <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></>
                <li class="active"><a href="invoice_billing_form.php">
                    <img src="../assets/images/Icons/Billing 3.png" alt="Schedule Icon">Invoice and Billing</a></>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='../logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>

    <div class="main-content">
    <div class="header">
        <h1>Invoice & Billing</h1>
            <div class="actions">
            <form method="GET" action="record.php" class="filter-container">
                <input type="text" id="searchInput" placeholder="Search..." onkeyup="realTimeSearch()">
            </form>

                <div class="button-group">
                    <button class="add-btn" onclick="location.href='generate_iandb.php'">View Paid</button>
                    <button class="add-btn" onclick="location.href='generate_iandb.php'">+ Generate Invoice & Billing</button>
                </div>
            </div>

        <br>

        <h2>Vaccination Billing</h2>
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Vaccination ID</th>
                    <th>Pet Name</th>
                    <th>Owner Name</th>
                    <th>Vaccine</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($unpaidVaccinations)): ?>
                    <?php foreach ($unpaidVaccinations as $vaccine): ?>
                        <tr>
                            <td><?= htmlspecialchars($vaccine['VaccineId']) ?></td>
                            <td><?= htmlspecialchars($vaccine['PetName']) ?></td>
                            <td><?= htmlspecialchars($vaccine['OwnerName']) ?></td>
                            <td><?= htmlspecialchars($vaccine['VaccinationName']) ?></td>
                            <td><?= htmlspecialchars($vaccine['VaccinationDate']) ?></td>
                            <td><?= htmlspecialchars($vaccine['Status']) ?></td>
                            <td>
                                <button class="mark-paid-btn" data-id="<?= htmlspecialchars($vaccine['VaccineId']) ?>">Mark As Paid</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No unpaid vaccinations found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <br>
        <hr>
        <br>
        <?php if (!empty($doneAppointments)): ?>
            <h2>Appointment Billing</h2>
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Pet Name</th>
                        <th>Owner Name</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doneAppointments as $appointment): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $appointment['AppointmentId']) ?></td>
                            <td><?= htmlspecialchars((string) $appointment['PetName']) ?></td>
                            <td><?= htmlspecialchars((string) ($appointment['OwnerFirstName'] . ' ' . $appointment['OwnerLastName'])) ?></td>
                            <td><?= htmlspecialchars((string) $appointment['ServiceName']) ?></td>
                            <td><?= htmlspecialchars((string) $appointment['Status']) ?></td>
                            <td>
                                <?php if (($appointment['PaymentStatus'] ?? 'Unpaid') === 'Unpaid'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($appointment['AppointmentId']) ?>">
                                        <button class="mark-paid-btn" data-id="<?= htmlspecialchars($appointment['AppointmentId']) ?>">Mark As Paid</button>
                                    </form>
                                <?php else: ?>
                                    <span class="paid-label">Paid</span> <!-- Display Paid Label -->
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <tr>
                <td colspan="7">No completed appointments found.</td> <!-- Adjusted colspan for the new column -->
            </tr>
        <?php endif; ?>
            <div class="pagination">
                <a href="?page=<?= max(1, $currentPage - 1) ?>">&laquo; Previous</a>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= min($totalPages, $currentPage + 1) ?>">Next &raquo;</a>
            </div>
    </div>
    <script>
        // Search Functionality
        function filterInvoices() {
            let input = document.getElementById("searchInvoice").value.toLowerCase();
            let tableRows = document.querySelectorAll(".appointments-table tbody tr");

            tableRows.forEach(row => {
                let rowText = row.innerText.toLowerCase();
                row.style.display = rowText.includes(input) ? "" : "none";
            });
        }

        // Action for Generate Invoice button
        function generateNewInvoice() {
            alert("Redirecting to generate a new invoice...");
            // window.location.href = 'generate_invoice.php'; // Optional redirect
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const markPaidButtons = document.querySelectorAll(".mark-paid-btn");

            markPaidButtons.forEach(button => {
                button.addEventListener("click", function () {
                    const id = this.getAttribute("data-id");

                    // SweetAlert confirmation dialog
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "Do you want to mark this as Paid?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, mark as Paid!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Send request to mark as paid
                            fetch('mark_as_paid.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: id })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'Status updated to Paid.',
                                        icon: 'success',
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload(); // Refresh UI after success
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: data.message,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Something went wrong.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            });
                        }
                    });
                });
            });
        });
    </script>
    </script>
</body>
</html>

