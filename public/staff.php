<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
ob_start();

require '../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/permissions.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: staff_login.php');
    exit;
}

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

$filterQuery = isset($_GET['filter']) ? $_GET['filter'] : '';
$orderQuery = isset($_GET['order']) ? strtoupper($_GET['order']) : '';  // No default order

$where_clause = [];
$params = [];

// ✅ Search Logic
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Users.FirstName LIKE ? 
                        OR Users.LastName LIKE ? 
                        OR Roles.RoleName LIKE ?)";
    $params = array_fill(0, 3, $search);
}

// ✅ Filter by Name or Role
$orderBy = 'Users.FirstName'; // Default to Name
if ($filterQuery === 'role') {
    $orderBy = 'Roles.RoleName'; // Sort by Role
}

$currentPage = isset($_GET['page']) ? max(0, (int)$_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

// ✅ SQL Query
$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$countQuery = "
    SELECT COUNT(*) AS total
    FROM Users
    LEFT JOIN UserRoles ON Users.UserId = UserRoles.UserId
    LEFT JOIN Roles ON UserRoles.RoleId = Roles.RoleId
    $where_sql
";

try {
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage); // ✅ Calculates total pages
} catch (PDOException $e) {
    echo "Error counting staff: " . $e->getMessage();
    $totalPages = 1; // ✅ Fallback to at least 1 page if an error occurs
}

$query = "
    SELECT Users.StaffCode,
           CONCAT(Users.FirstName, ' ', Users.LastName) AS name,
           Users.Email,
           Users.EmploymentType,
           Users.PhoneNumber,
           Roles.RoleName AS role,
           Users.Status,
           Users.UpdatedAt -- ✅ Add this line
    FROM Users
    LEFT JOIN UserRoles ON Users.UserId = UserRoles.UserId
    LEFT JOIN Roles ON UserRoles.RoleId = Roles.RoleId
    $where_sql
";

if ($orderQuery) {
    $query .= " ORDER BY $orderBy $orderQuery";
} else {
    $query .= " ORDER BY RAND()"; // Random order when no filter is applied
}
$query .= " LIMIT ?, ?";

// ✅ Pagination
$params[] = $offset;
$params[] = $recordsPerPage;

// Execute Query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error fetching staff: " . $e->getMessage();
    $staff = [];
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
    <link rel="stylesheet" href="../assets/css/staff_view.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src = "../assets/js/staff_view.js"></script>
    <style>
    .toast-container {
        position: fixed;
        bottom: 20px; /* Position at the bottom */
        right: 20px; /* Position on the right side */
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 10px; /* Space between multiple toasts */
    }

    /* Success Toast */
    .success-toast {
        color: #155724; /* Green text */
        background-color: #d4edda; /* Light green background */
        padding: 12px 20px;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        animation: slide-in 0.5s ease, fade-out 0.5s ease 3s;
        opacity: 1;
    }

    /* Error Toast */
    .error-toast {
        color: #721c24; /* Red text */
        background-color: #f8d7da; /* Light red background */
        padding: 12px 20px;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        animation: slide-in 0.5s ease, fade-out 0.5s ease 3s;
        opacity: 1;
    }

    @keyframes slide-in {
        from {
            transform: translateX(100%); /* Start off-screen */
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fade-out {
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    </style>
</head>
<body>
<div class="sidebar">
<div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <ul class="nav-links">
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
                <li><a href="main_dashboard.php">
                    <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                <li><a href="record.php">
                    <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li class="active"><a href="staff_view.php">
                    <img src="../assets/images/Icons/Staff 3.png" alt="Schedule Icon">Staff</a></li>
                <li><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                    <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></s>
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
            <h1>Staff</h1>
            <div id="toastContainer" class="toast-container">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="success-toast" id="successToast">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); // Clear message after displaying ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="error-toast" id="errorToast">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); // Clear message after displaying ?>
                <?php endif; ?>
            </div>

            <div class="actions">
                <div class="left">
                    <input type="text" id="searchInput" placeholder="Search staff...">
                    
                    <!-- Filter dropdown -->
                    <div class="dropdown">
                    <button class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                        <label>
                            <input type="radio" name="filter" value="name" <?= $filterQuery == 'name' ? 'checked' : '' ?>> Name
                        </label>
                        <label>
                            <input type="radio" name="filter" value="role" <?= $filterQuery == 'role' ? 'checked' : '' ?>> Role
                        </label>
                        <hr>
                        <label>
                            <input type="radio" name="order" value="ASC" <?= $orderQuery == 'ASC' ? 'checked' : '' ?>> Ascending
                        </label>
                        <label>
                            <input type="radio" name="order" value="DESC" <?= $orderQuery == 'DESC' ? 'checked' : '' ?>> Descending
                        </label>
                        <hr>
                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="window.location.href='staff_view.php'">Clear Filter</button>
                        </div>
                    </div>
                </div>
                <?php if (hasPermission($pdo, 'Create Staff')): ?>
                    <button class="add-btn" onclick="location.href='add_staff.php'">+ Add Staff</button>
                <?php endif; ?>
            </div>
        </div>
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Staff Code</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Employment Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="staffList">
                <?php if (!empty($staff)): ?>
                    <?php foreach ($staff as $staff_member): ?>
                        <tr class="staff-row">
                            <!-- ✅ Staff Code Column -->
                            <td><?= htmlspecialchars($staff_member['StaffCode'] ?? 'N/A') ?></td>

                            <!-- ✅ Name with Hover Details -->
                            <td>
                                <div class="hover-container">
                                    <?= htmlspecialchars($staff_member['name']) ?>
                                    <i class="fas fa-info-circle"></i> <!-- Info Icon -->
                                    <div class="hover-card">
                                        <div class="profile-info">
                                            <img src="../assets/images/Icons/Profile User.png" alt="Profile Pic" class="profile-img">
                                            <div>
                                                <strong><?= htmlspecialchars($staff_member['name']) ?></strong><br>
                                                Position: <?= htmlspecialchars($staff_member['role'] ?? 'No Role Assigned') ?>
                                            </div>
                                        </div>
                                        <hr>
                                        <div>
                                            <strong>Updated At:</strong><br>
                                            <?= htmlspecialchars($staff_member['UpdatedAt'] ?? 'No update yet') ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Email:</strong><br>
                                            <?= htmlspecialchars($staff_member['Email']) ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Phone Number:</strong><br>
                                            <?= htmlspecialchars($staff_member['PhoneNumber'] ?? 'No information found') ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Emergency Contact Name:</strong><br>
                                            <?= htmlspecialchars($staff_member['EmergencyContactName'] ?? 'No information found') ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Emergency Contact Number:</strong><br>
                                            <?= htmlspecialchars($staff_member['EmergencyContactNumber'] ?? 'No information found') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- ✅ Role -->
                            <td><?= htmlspecialchars($staff_member['role'] ?? 'No Role Assigned') ?></td>

                            <!-- ✅ Employment Type -->
                            <td><?= htmlspecialchars($staff_member['EmploymentType']) ?></td>

                            <?php if (hasPermission($pdo, 'Manage Staff')): ?>
                                <td>
                                    <button class="action-btn edit-btn" onclick="editStaff('<?= $staff_member['Email']; ?>')">Edit</button>
                                    <button class="action-btn delete-btn" onclick="deleteStaff('<?= $staff_member['Email']; ?>')">Delete</button>
                                    <button class="action-btn toggle-btn" onclick="toggleStatus('<?= $staff_member['Email']; ?>')">
                                        <?= $staff_member['Status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No staff members found.</td>
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
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const searchInput = document.getElementById("searchInput");
            const staffList = document.getElementById("staffList");

            searchInput.addEventListener("input", () => {
                const query = searchInput.value.trim();

                fetch(`staff_view.php?search=${encodeURIComponent(query)}`)
                    .then(response => response.text())
                    .then(html => {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;

                        const newTableBody = tempDiv.querySelector("#staffList");
                        if (newTableBody) {
                            staffList.innerHTML = newTableBody.innerHTML;
                        }
                    })
                    .catch(error => console.error("Error fetching search results:", error));
            });
        });

        // Open the Edit Modal and populate the form
        function editStaff(email) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_staff_details.php?email=' + encodeURIComponent(email), true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const staff = JSON.parse(xhr.responseText);

                    Swal.fire({
                        title: 'Edit Staff Information',
                        html: `
                        <div style="text-align: left;">
                            <div class="swal2-row">
                                <label>Email:</label>
                                <input type="text" id="swalEmail" class="swal2-input" value="${staff.Email}" readonly>
                            </div>
                            
                            <div class="swal2-row">
                                <label>First:</label>
                                <input type="text" id="swalFirstName" class="swal2-input" value="${staff.FirstName}" required>
                            </div>
                            
                            <div class="swal2-row">
                                <label>Last:</label>
                                <input type="text" id="swalLastName" class="swal2-input" value="${staff.LastName}" required>
                            </div>
                            
                            <div class="swal2-row">
                                <label>Phone:</label>
                                <input type="text" id="swalPhoneNumber" class="swal2-input" value="${staff.PhoneNumber}">
                            </div>
                            
                            <div class="swal2-row">
                                <label>Status:</label>
                                <select id="swalStatus" class="swal2-select">
                                    <option value="active" ${staff.Status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${staff.Status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </div>
                        `,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Save Changes',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            confirmButton: 'swal2-confirm-btn',
                            cancelButton: 'swal2-cancel-btn'
                        },
                        preConfirm: () => {
                            return {
                                firstName: document.getElementById('swalFirstName').value,
                                lastName: document.getElementById('swalLastName').value,
                                phoneNumber: document.getElementById('swalPhoneNumber').value,
                                status: document.getElementById('swalStatus').value
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const data = result.value;
                            updateStaffDetails(email, data);
                        }
                    });
                } else {
                    Swal.fire('Error', 'Error fetching staff details.', 'error');
                }
            };
            xhr.send();
        }

        function updateStaffDetails(email, data) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_staff_details.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    Swal.fire('Success', 'Staff information updated successfully!', 'success').then(() => {
                        location.reload(); // Reload the page to see the changes
                    });
                } else {
                    Swal.fire('Error', 'Error updating staff details.', 'error');
                }
            };

            const params = `email=${encodeURIComponent(email)}` +
                `&firstName=${encodeURIComponent(data.firstName)}` +
                `&lastName=${encodeURIComponent(data.lastName)}` +
                `&phoneNumber=${encodeURIComponent(data.phoneNumber)}` +
                `&status=${encodeURIComponent(data.status)}`;
            xhr.send(params);
        }

        // Close the Edit Modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        // Function to delete a staff member
        function deleteStaff(email) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'swal2-delete-btn',
                    cancelButton: 'swal2-cancel-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'delete_staff.php?email=' + encodeURIComponent(email), true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            Swal.fire('Deleted!', 'The staff member has been removed.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', 'Unable to delete the staff member.', 'error');
                        }
                    };
                    xhr.send();
                }
            });
        }

        // Function to toggle staff status (active/inactive)
        function toggleStatus(email, currentStatus) {
            let actionText = currentStatus === 'active' ? 'deactivate' : 'activate';
            let actionVerb = currentStatus === 'active' ? 'Deactivate' : 'Activate';

            Swal.fire({
                title: `Confirm ${actionVerb}`,
                text: `Are you sure you want to ${actionText} this staff member?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Yes, ${actionVerb}`,
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'swal2-toggle-btn',
                    cancelButton: 'swal2-cancel-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'toggle_status.php?email=' + encodeURIComponent(email), true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            Swal.fire('Updated!', `Staff member has been ${actionText}d.`, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', `Failed to ${actionText} staff member.`, 'error');
                        }
                    };
                    xhr.send();
                }
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
        const toast = document.getElementById("successToast");
        if (toast) {
            // Automatically remove toast after 3.5 seconds
            setTimeout(() => {
                toast.remove();
            }, 3500); // Wait for the fade-out animation to complete
        }
    });
    </script>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const infoIcons = document.querySelectorAll('.fas.fa-info-circle'); // All "i" icons
        const hoverCards = document.querySelectorAll('.hover-card'); // All hover cards

        // Track the open state
        let activeCard = null;

        // Function to show the hover card
        const showHoverCard = (card) => {
            card.style.display = 'block';
        };

        // Function to hide the hover card
        const hideHoverCard = (card) => {
            card.style.display = 'none';
        };

        // Add hover and click listeners
        infoIcons.forEach((icon, index) => {
            const hoverCard = hoverCards[index];

            // Show the hover card on hover
            icon.addEventListener('mouseenter', () => {
                showHoverCard(hoverCard);
            });

            // Keep the hover card visible on hover
            hoverCard.addEventListener('mouseenter', () => {
                showHoverCard(hoverCard);
            });

            // Hide the hover card when not hovering
            icon.addEventListener('mouseleave', () => {
                if (activeCard !== hoverCard) {
                    hideHoverCard(hoverCard);
                }
            });

            hoverCard.addEventListener('mouseleave', () => {
                if (activeCard !== hoverCard) {
                    hideHoverCard(hoverCard);
                }
            });

            // Toggle the hover card visibility on click
            icon.addEventListener('click', (event) => {
                event.stopPropagation(); // Prevent bubbling up
                if (activeCard === hoverCard) {
                    activeCard = null; // Deselect active card
                    hideHoverCard(hoverCard);
                } else {
                    if (activeCard) hideHoverCard(activeCard); // Close any open card
                    activeCard = hoverCard; // Set the new active card
                    showHoverCard(hoverCard);
                }
            });
        });

        // Close hover cards when clicking outside
        document.addEventListener('click', (event) => {
            if (activeCard && !activeCard.contains(event.target) && !event.target.classList.contains('fa-info-circle')) {
                hideHoverCard(activeCard);
                activeCard = null; // Reset active card
            }
        });
    });
    </script>
    
    <script>
            document.querySelector('.apply-btn').addEventListener('click', function () {
        const filter = document.querySelector('input[name="filter"]:checked')?.value || '';
        const order = document.querySelector('input[name="order"]:checked')?.value || '';
        const searchQuery = document.getElementById('searchInput').value.trim();

        let url = `staff_view.php?`;
        if (filter) url += `filter=${filter}&`;
        if (order) url += `order=${order}&`;
        if (searchQuery) url += `search=${encodeURIComponent(searchQuery)}`;

        window.location.href = url;
    });
    </script>
</body>
</html>