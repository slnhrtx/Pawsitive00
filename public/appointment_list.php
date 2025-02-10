<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';
//logActivity($pdo, $userId, $userName, $role, 'record.php', 'Accessed Record page');

// Pagination setup
$currentPage = isset($_GET['page']) ? max(0, (int)$_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

$where_clause = [];
$params = [];

// Search filter
if (isset($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Pets.Name LIKE ? 
                        OR Appointments.AppointmentCode LIKE ? 
                        OR Services.ServiceName LIKE ? 
                        OR CONCAT(Owners.FirstName, ' ', Owners.LastName) LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Date filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];
    $where_clause[] = "appointments.AppointmentDate BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Status filter
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    if ($status == 'today') {
        $where_clause[] = "Appointments.AppointmentDate = CURDATE()";
    } elseif ($status == 'upcoming') {
        $where_clause[] = "Appointments.AppointmentDate > CURDATE() AND Appointments.Status = 'Pending'";
    } elseif ($status == 'done') {
        $where_clause[] = "Appointments.Status = 'Done'";
    } elseif ($status == 'declined') {
        $where_clause[] = "Appointments.Status = 'Declined'";
    }
}

$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT 
        Appointments.AppointmentId,
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName,
        Owners.Email,
        Owners.Phone,
        Pets.PetCode,
        Pets.PetId,
        Pets.Name AS PetName,
        Species.SpeciesName AS PetType,
        Services.ServiceName AS Service,
        Appointments.AppointmentDate,
        Appointments.AppointmentTime,
        Appointments.Status
    FROM Appointments
    INNER JOIN Pets ON Appointments.PetId = Pets.PetId
    INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
    $where_sql
    ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentTime DESC
    LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
SELECT COUNT(*) 
FROM Appointments
INNER JOIN Pets ON Appointments.PetId = Pets.PetId
INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
LEFT JOIN Species ON Pets.SpeciesId = Species.Id
LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
$where_sql";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute(array_slice($params, 0, -2));
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
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
    <link rel="stylesheet" href="../assets/css/appointment_list.css">
    <script src="../assets/js/record.js"></script>
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
                        <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                    <li><a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                    <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                    <li class="active"><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 3.png" alt="Schedule Icon">Schedule</a></li>
                    <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></>
                </ul>
            </nav>
            <div class="sidebar-bottom">
                <button onclick="window.location.href='settings.php';">
                    <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
                </button>
                <button onclick="window.location.href='logout.php';">
                    <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
                </button>
            </div>
        </div>
        <div class="main-content">
        <div class="header">
            <h1>Appointments</h1>
            <div class="actions">
                <div class="left">
                    <input type="text" id="searchInput" placeholder="Search appointments..."/>
                    <div class="dropdown">
                        <button class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <label>
                                <input type="radio" name="status" value="today" onclick="applyFilters()"> Today
                            </label>
                            <label>
                                <input type="radio" name="status" value="upcoming" onclick="applyFilters()"> Upcoming
                            </label>
                            <label>
                                <input type="radio" name="status" value="done" onclick="applyFilters()"> Done
                            </label>
                            <label>
                                <input type="radio" name="status" value="declined" onclick="applyFilters()"> Declined
                            </label>
                            <hr>
                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="window.location.href='appointment.php'">Clear Filter</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <table class="staff-table">
            <thead>
                <tr>
                    <th><a href="#" class="sortable" data-column="AppointmentCode">Appointment Id</a></th>
                    <th><a href="#" class="sortable" data-column="PetCode">Pet Code</a></th>
                    <th><a href="#" class="sortable" data-column="PetName">Pet Name</a></th>
                    <th><a href="#" class="sortable" data-column="Status">Status</a></th>
                    <th><a href="#" class="sortable" data-column="Service">Service</a></th>
                </tr>
            </thead>
            <tbody id="appointmentList">
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <div class="hover-container">
                                    <?= htmlspecialchars($appointment['AppointmentId'] ?? 'N/A') ?>
                                    <i class="fas fa-info-circle"></i>
                                    <div class="hover-card">
                                        <div class="profile-info">
                                            <img src="../assets/images/Icons/Profile User.png" alt="Profile Pic" class="profile-img" width="10px">
                                            <div>
                                                <strong><?= htmlspecialchars($appointment['OwnerName'] ?? 'Unknown') ?></strong><br>
                                                <?= htmlspecialchars($appointment['role'] ?? 'Authorized Representative') ?>
                                            </div>
                                        </div>
                                        <hr>
                                        <div>
                                            <strong>Email:</strong><br>
                                            <?= htmlspecialchars($appointment['Email'] ?? 'No email provided') ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Phone Number:</strong><br>
                                            <?= htmlspecialchars($appointment['Phone'] ?? 'No phone number provided') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($appointment['PetCode'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($appointment['PetName']) ?></td>
                            <td><?= htmlspecialchars($appointment['Status']) ?></td>
                            <td><?= htmlspecialchars($appointment['Service']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No appointments found for the given search criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
            <?php for ($i = 0; $i < $totalPages; $i++): ?>
                <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
                <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                    <span style="display: inline-block; margin-top: 15px;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("searchInput");
            const appointmentList = document.getElementById("appointmentList");

            function debounce(func, delay) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), delay);
                };
            }
    function fetchAppointments(query) {
        fetch(`fetch_appointments.php?search=${encodeURIComponent(query)}`)
            .then(response => response.text())
            .then(html => {
                appointmentList.innerHTML = html; // Update table body with new results
            })
            .catch(error => console.error("Error fetching appointments:", error));
    }

    searchInput.addEventListener("input", debounce(() => {
        const query = searchInput.value.trim();
        fetchAppointments(query);
    }, 300));  // 300ms delay before triggering search
});
        </script>
</body>
</html>