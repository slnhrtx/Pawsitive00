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
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

if (!hasPermission($pdo, 'View Dashboard')) {
    exit("You do not have permission to access this page.");
}

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

try {
    $query = "SELECT COUNT(*) AS TotalRecords FROM Pets";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['TotalRecords'];
} catch (Exception $e) {
    error_log($e->getMessage());
    $totalRecords = 'Error fetching records';
}

$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM Pets) AS TotalRecords,
        (SELECT COUNT(*) FROM Pets INNER JOIN Species ON Pets.SpeciesId = Species.Id WHERE SpeciesName = 'Dog' AND IsArchived = 0) AS Dogs,
        (SELECT COUNT(*) FROM Pets INNER JOIN Species ON Pets.SpeciesId = Species.Id WHERE SpeciesName = 'Cat' AND IsArchived = 0) AS Cats,
        (SELECT COUNT(*) FROM Pets INNER JOIN Species ON Pets.SpeciesId = Species.Id WHERE SpeciesName NOT IN ('Dog', 'Cat') AND IsArchived = 0) AS Others
";
$stats = fetchData($pdo, $statsQuery)[0];

$dogsCount = isset($stats['Dogs']) ? (int) $stats['Dogs'] : 0;
$catsCount = isset($stats['Cats']) ? (int) $stats['Cats'] : 0;
$otherPetsCount = isset($stats['Others']) ? (int) $stats['Others'] : 0;
$totalRecords = isset($stats['TotalRecords']) ? (int) $stats['TotalRecords'] : 0;

$appointmentsPerDay = fetchData($pdo, "
    SELECT DATE(AppointmentDate) AS label, COUNT(*) AS count
    FROM Appointments
    WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY label
    ORDER BY label
");

$appointmentsPerWeek = fetchData($pdo, "
    SELECT CONCAT(YEAR(AppointmentDate), '-W', WEEK(AppointmentDate)) AS label, COUNT(*) AS count
    FROM Appointments
    WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY label
    ORDER BY label
");

$appointmentsPerMonth = fetchData($pdo, "
    SELECT DATE_FORMAT(AppointmentDate, '%Y-%m') AS label, COUNT(*) AS count
    FROM Appointments
    WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    GROUP BY label
    ORDER BY label
");

$appointmentsPerYear = fetchData($pdo, "
    SELECT YEAR(AppointmentDate) AS label, COUNT(*) AS count
    FROM Appointments
    WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    GROUP BY label
    ORDER BY label
");

$appointmentData = [
    'day' => ['labels' => array_column($appointmentsPerDay, 'label'), 'counts' => array_column($appointmentsPerDay, 'count')],
    'week' => ['labels' => array_column($appointmentsPerWeek, 'label'), 'counts' => array_column($appointmentsPerWeek, 'count')],
    'month' => ['labels' => array_column($appointmentsPerMonth, 'label'), 'counts' => array_column($appointmentsPerMonth, 'count')],
    'year' => ['labels' => array_column($appointmentsPerYear, 'label'), 'counts' => array_column($appointmentsPerYear, 'count')],
];

$upcomingAppointments = fetchData($pdo, "
    SELECT a.AppointmentId, a.AppointmentDate, a.AppointmentTime, p.PetId, p.Name AS PetName, a.Status, s.ServiceName
    FROM Appointments AS a
    JOIN Pets AS p ON a.PetId = p.PetId
    JOIN Services AS s ON a.ServiceId = s.ServiceId
    WHERE a.Status IN ('Pending', 'Confirmed', 'Done')
    ORDER BY a.AppointmentDate ASC
    LIMIT 10
");

$overdueAppointments = fetchData($pdo, "
    SELECT * FROM Appointments
    WHERE AppointmentDate < CURDATE() AND Status = 'Pending'
");

$recentActivities = fetchData($pdo, "
    SELECT UserName, Role, PageAccessed, ActionDetails, CreatedAt
    FROM ActivityLog
    ORDER BY CreatedAt DESC
    LIMIT 5
");

$speciesData = fetchData($pdo, "
    SELECT s.SpeciesName, COUNT(p.PetId) AS PetCount
    FROM Species s
    LEFT JOIN Pets p ON s.Id = p.SpeciesId
    GROUP BY s.SpeciesName
    ORDER BY PetCount DESC
");

// Prepare data for the pie chart
$speciesLabels = array_column($speciesData, 'SpeciesName');
$speciesCounts = array_column($speciesData, 'PetCount');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['appointment_id'], $data['new_date'], $data['new_time'])) {
        echo json_encode(['success' => false, 'message' => '⚠️ Missing required fields.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE Appointments
            SET AppointmentDate = :newDate, AppointmentTime = :newTime
            WHERE AppointmentId = :appointmentId
        ");
        $stmt->execute([
            ':newDate' => $data['new_date'],
            ':newTime' => $data['new_time'],
            ':appointmentId' => $data['appointment_id']
        ]);

        if ($stmt->rowCount()) {
            echo json_encode(['success' => true, 'message' => '✅ Appointment rescheduled successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => '⚠️ No changes made.']);
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '❌ Database error occurred.']);
    }
    exit;
}
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
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/update_appointment.js?v=1.0.1"></script>
    <style>
        .success-message {
            color: #155724;
            background-color: #d4edda;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
            transform: translateY(-20px);
        }

        .success-message.show {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }
    </style>
    <style>
        .status-button:not(:disabled):hover {
            background-color: #45a049;
        }

        .status-button:disabled,
        .decline-btn:disabled,
        .confirm-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            opacity: 0.6;
            border: 1px solid #bbbbbb;
        }
    </style>
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
                <li class="active">
                    <a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 3.png" alt="Chart Icon">Overview
                    </a>
                </li>
                <li>
                    <a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record
                    </a>
                </li>
                <li>
                    <a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff
                    </a>
                </li>
                <li>
                    <a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule
                    </a>
                </li>
                <li>
                    <a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Billing Icon">Invoice and Billing
                    </a>
                </li>
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
    <div class="content-wrapper">
        <div class="main-content">
            <h1>Overview</h1>
            <section class="overview-section">
                <div class="total-record">
                    <h2>Total Records of Registered Pets</h2>
                    <p style="font-size: 40px;"><?= htmlspecialchars($totalRecords); ?></p>
                </div>
                <section class="pet-records">
                    <div class="pet-item">
                        <img src="../assets/images/Icons/Dogs.png" alt="Dog Icon" class="pet-icon">
                        <p>Dogs</p>
                        <span><?= htmlspecialchars($dogsCount); ?></span>
                    </div>
                    <div class="pet-item">
                        <img src="../assets/images/Icons/Cats.png" alt="Cat Icon" class="pet-icon">
                        <p>Cats</p>
                        <span><?= htmlspecialchars($catsCount); ?></span>
                    </div>
                    <div class="pet-item">
                        <img src="../assets/images/Icons/Other Pet.png" alt="Other Pets Icon" class="pet-icon">
                        <p>Other Pets</p>
                        <span><?= htmlspecialchars($otherPetsCount); ?></span>
                    </div>
                </section>
            </section>

            <br><hr><br>

            <div class="chart-controls-container">
            <h2 class="text-xl font-semibold text-center text-gray-700">Appointment Analytics</h2>
                <label for="appointmentRange" class="text-gray-700 font-medium">Filter by Range:</label>
                <select id="appointmentRange" class="border rounded-md p-2 focus:ring-2 focus:ring-blue-500">
                    <option value="day">Per Day</option>
                    <option value="week">Per Week</option>
                    <option value="month" selected>Per Month</option>
                    <option value="year">Per Year</option>
                </select>
            </div>          
            <br>
            <canvas id="appointmentsChart" data-appointments='<?= json_encode($appointmentData); ?>' width="800"
                height="400"></canvas>

            <br><hr><br>

            <div class="species-filter-container">
                <h2 class="text-xl font-semibold text-center text-gray-700">Pets Per Species Analytics</h2>
                <label for="speciesFilter" class="text-gray-700 font-medium">Filter by Species:</label>
                <select id="speciesFilter" class="border rounded-md p-2 focus:ring-2 focus:ring-blue-500">
                    <option value="all">All Species</option>
                    <option value="Dog">Dog</option>
                    <option value="Cat">Cat</option>
                    <option value="Rabbit">Rabbit</option>
                    <option value="Bird">Bird</option>
                    <option value="Hamster">Hamster</option>
                    <option value="Guinea Pig">Guinea Pig</option>
                    <option value="Reptile">Reptile</option>
                    <option value="Ferret">Ferret</option>
                    <option value="Fish">Fish</option>
                </select>
            </div>
            <br>
            <div class="max-w-md mx-auto mt-8 bg-white shadow-lg rounded-lg p-4">
                <canvas id="speciesPieChart" data-labels='<?= json_encode($speciesLabels); ?>'
                    data-counts='<?= json_encode($speciesCounts); ?>'></canvas>
            </div>
            <div class="right-section">
                <h2>Quick Actions</h2>
                <?php if (hasPermission($pdo, 'Create Owner')): ?>
                    <button class="add-owner">
                        <a href="add_owner_pet.php">Add Owner and Pet</a>
                    </button>
                <?php endif; ?>
                <?php if (hasPermission($pdo, 'Create Appointment')): ?>
                    <button class="add-record">
                        <a href="appointment.php">Add Appointment</a>
                    </button>
                <?php endif; ?>
                <?php if (hasPermission($pdo, 'Export Data')): ?>
                    <button class="export-button btn btn-primary" onclick="exportAllDataPDF()">Export</button>
                <?php endif; ?>
                <div class="card">
                    <h2>Upcoming Appointments</h2>
                    <div class="schedule-items">
                        <?php if (!empty($upcomingAppointments)): ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div id="appointment-<?= htmlspecialchars($appointment['AppointmentId']) . '-' . htmlspecialchars($appointment['PetId']); ?>"
                                    class="schedule-item">
                                    <div class="appointment-header">
                                        <h3 style="font-size: 22px;"><?= htmlspecialchars($appointment['PetName']); ?></h3>
                                        <div class="dropdown">
                                            <button id="menu-btn-<?= $appointment['AppointmentId']; ?>" class="menu-btn"
                                                onclick="toggleMenu(<?= $appointment['AppointmentId']; ?>)">⋮</button>
                                            <div id="menu-<?= $appointment['AppointmentId']; ?>" class="dropdown-content"
                                                style="display: none;">
                                                <a href="#"
                                                    onclick="openRescheduleModal(event, <?= $appointment['AppointmentId']; ?>); return false;">Reschedule</a>
                                            </div>
                                        </div>
                                    </div>
                                    <p><strong>Service:</strong> <?= htmlspecialchars($appointment['ServiceName']); ?></p>
                                    <p><strong>Date:</strong> <?= htmlspecialchars($appointment['AppointmentDate']); ?></p>
                                    <p><strong>Time:</strong> <?= htmlspecialchars($appointment['AppointmentTime']); ?></p>
                                    <p><strong>Status:</strong> <span
                                            class="status"><?= htmlspecialchars($appointment['Status']); ?></span></p>
                                    <div class="buttons-container"
                                        id="buttons-<?= htmlspecialchars($appointment['AppointmentId']) . '-' . htmlspecialchars($appointment['PetId']); ?>">
                                        <?php if ($appointment['Status'] === 'Done'): ?>
                                            <?php if (hasPermission($pdo, 'Process Payments')): ?>
                                                <button class="status-button"
                                                    onclick="generateInvoice(<?= htmlspecialchars($appointment['AppointmentId']); ?>, <?= htmlspecialchars($appointment['PetId']); ?>)">
                                                    Invoice and Billing
                                                </button>
                                            <?php else: ?>
                                                <button class="status-button" disabled>Invoice and Billing</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (hasPermission($pdo, 'Modify Appointment Status')): ?>
                                                <button class="status-button"
                                                    onclick="updateAppointmentStatus(<?= htmlspecialchars($appointment['AppointmentId']); ?>, 'Done', <?= htmlspecialchars($appointment['PetId']); ?>)">
                                                    Mark as Done
                                                </button>
                                            <?php else: ?>
                                                <button class="status-button" disabled>Mark as Done</button>
                                            <?php endif; ?>

                                            <?php if ($appointment['Status'] === 'Confirmed'): ?>
                                                <?php if ($appointment['ServiceName'] === 'Pet Vaccination & Deworming'): ?>
                                                    <button
                                                        onclick="promptVitalsVaccine('<?= htmlspecialchars($appointment['AppointmentId']); ?>', '<?= htmlspecialchars($appointment['PetId']); ?>')">
                                                        Start Consultation
                                                    </button>
                                                <?php else: ?>
                                                    <button
                                                        onclick="promptVitalsUpdate('<?= htmlspecialchars($appointment['AppointmentId']); ?>', '<?= htmlspecialchars($appointment['PetId']); ?>')">
                                                        Start Consultation
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if (hasPermission($pdo, 'Modify Appointment Status')): ?>
                                                    <button class="confirm-btn"
                                                        onclick="updateAppointmentStatus(<?= htmlspecialchars($appointment['AppointmentId']); ?>, 'Confirmed', <?= htmlspecialchars($appointment['PetId']); ?>)">
                                                        Confirm
                                                    </button>
                                                <?php else: ?>
                                                    <button class="confirm-btn" disabled>Confirm</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No upcoming appointments.</p>
                        <?php endif; ?>
                        <button class="see-all-button" onclick="window.location.href='appointment_list.php';">See
                            All</button>
                    </div>
                </div>
                <div class="alerts">
                    <div class="card">
                        <h2>Recent Activities</h2>
                        <div class="activity-items">
                            <?php if (!empty($recentActivities)): ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <h3><?= htmlspecialchars($activity['UserName']); ?></h3>
                                        <p><strong>Role:</strong> <?= htmlspecialchars($activity['Role']); ?></p>
                                        <p><strong>Activity:</strong> <?= htmlspecialchars_decode($activity['ActionDetails']); ?></p>
                                        <p><strong>Timestamp:</strong> <?= htmlspecialchars($activity['CreatedAt']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No recent activities.</p>
                            <?php endif; ?>
                            <button class="see-all-button" onclick="window.location.href='all_activities.php';"
                                style="margin-top: 5px;">See All</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="toast" class="success-message" style="display: none;">
        <i class="fas fa-check-circle"></i> <!-- Optional Font Awesome icon -->
        <span id="toast-message"></span>
    </div>
    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

            toastMessage.textContent = message;
            toast.classList.add('show');

            // Hide after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartElement = document.getElementById('appointmentsChart');
        const appointmentData = JSON.parse(chartElement.dataset.appointments);
        let currentChart = null;

        function renderBarChart(range) {
            const data = appointmentData[range];

            if (currentChart) currentChart.destroy(); // Clear the existing chart

            currentChart = new Chart(chartElement, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: `Appointments Per ${range.charAt(0).toUpperCase() + range.slice(1)}`,
                        data: data.counts,
                        backgroundColor: '#a8ebf0',  // Bar color
                        borderColor: '#156f77',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return `Appointments: ${tooltipItem.raw}`;
                                }
                            }
                        },
                        legend: {
                            display: false // Hides the legend for cleaner look
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date Range'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Appointments'
                            }
                        }
                    }
                }
            });
        }

        // Default view: Month
        renderBarChart('month');

        // Event Listener for dropdown change
        document.getElementById('appointmentRange').addEventListener('change', (event) => {
            renderBarChart(event.target.value);
        });
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('speciesPieChart').getContext('2d');
        const allLabels = JSON.parse(ctx.canvas.dataset.labels);
        const allCounts = JSON.parse(ctx.canvas.dataset.counts);

        // Define consistent colors for each species
        const speciesColors = {
            "Dog": "#FF6384",
            "Cat": "#36A2EB",
            "Rabbit": "#FFCE56",
            "Bird": "#4CAF50",
            "Hamster": "#9C27B0",
            "Guinea Pig": "#FF9800",
            "Reptile": "#795548",
            "Ferret": "#03A9F4",
            "Fish": "#E91E63"
        };

        // Generate color mapping for existing labels
        const assignedColors = allLabels.map(species => speciesColors[species] || "#CCCCCC"); // Default gray if missing

        let speciesChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: allLabels,
                datasets: [{
                    data: allCounts,
                    backgroundColor: assignedColors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#4A4A4A',
                            font: { size: 14, family: 'Poppins' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (tooltipItem) {
                                return `${speciesChart.data.labels[tooltipItem.dataIndex]}: ${speciesChart.data.datasets[0].data[tooltipItem.dataIndex]} pets`;
                            }
                        }
                    }
                }
            }
        });

        // Event Listener for Species Dropdown
        document.getElementById('speciesFilter').addEventListener('change', function () {
            const selectedSpecies = this.value;

            if (selectedSpecies === 'all') {
                speciesChart.data.labels = allLabels;
                speciesChart.data.datasets[0].data = allCounts;
                speciesChart.data.datasets[0].backgroundColor = assignedColors;
            } else {
                const index = allLabels.indexOf(selectedSpecies);
                if (index !== -1) {
                    speciesChart.data.labels = [allLabels[index]];
                    speciesChart.data.datasets[0].data = [allCounts[index]];
                    speciesChart.data.datasets[0].backgroundColor = [speciesColors[selectedSpecies]];
                }
            }

            speciesChart.update(); // Refresh the chart
        });
    });
    </script>
<script>
function generateInvoice(appointmentId, petId) {
    if (!appointmentId || !petId) {
        console.error("❌ Missing appointment or pet ID.");
        return;
    }

    Swal.fire({
        title: "Generate Invoice?",
        text: "Are you sure you want to generate an invoice for this appointment?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, Generate",
        cancelButtonText: "Cancel"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../src/generate_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire("Success", "Invoice generated successfully!", "success")
                    .then(() => {
                        window.location.href = 'invoice_billing_form.php';
                    });
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            })
            .catch(error => console.error("❌ Error:", error));
        }
    });
}
</script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main_dashboard.js?v=1.0.6"></script>
</body>

</html>