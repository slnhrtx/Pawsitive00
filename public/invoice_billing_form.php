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

// Fetch unpaid invoices
$stmt = $pdo->prepare("
    SELECT i.InvoiceId, i.InvoiceNumber, i.InvoiceDate AS DateIssued, i.TotalAmount, i.Status,
           a.AppointmentDate AS DueDate,  -- Assuming AppointmentDate is used as the due date
           p.Name AS PetName, CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName,
           s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE i.Status IN ('Pending', 'Overdue', 'Partial Payment')
    ORDER BY i.InvoiceDate ASC
");
$stmt->execute();
$unpaidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/vet_record.css">
</head>
    <!--<div class="sidebar">
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
                <li class="active"><a href="record.php">
                        <img src="../assets/images/Icons/Record 3.png" alt="Record Icon">Record</a></li>
                <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
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
            <button onclick="window.location.href='logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>-->
<h2>Unpaid Invoices</h2>

<table class="staff-table">
    <thead>
        <tr>
            <th>Invoice ID</th>
            <th>Invoice Number</th>
            <th>Date Issued</th>
            <th>Due Date</th>
            <th>Pet Name</th>
            <th>Owner Name</th>
            <th>Service Provided</th>
            <th>Total Amount Due</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="staffList">
        <?php if (!empty($unpaidInvoices)): ?>
            <?php foreach ($unpaidInvoices as $invoice): ?>
                <tr>
                    <td><?= htmlspecialchars($invoice['InvoiceId']) ?></td>
                    <td><?= htmlspecialchars($invoice['InvoiceNumber']) ?></td>
                    <td><?= htmlspecialchars($invoice['DateIssued']) ?></td>
                    <td><?= htmlspecialchars($invoice['DueDate']) ?></td>
                    <td><?= htmlspecialchars($invoice['PetName']) ?></td>
                    <td><?= htmlspecialchars($invoice['OwnerName']) ?></td>
                    <td><?= htmlspecialchars($invoice['ServiceName']) ?></td>
                    <td>$<?= number_format($invoice['TotalAmount'], 2) ?></td>
                    <td><?= htmlspecialchars($invoice['Status']) ?></td>
                    <td>
                        <button class="mark-paid-btn" data-id="<?= $invoice['InvoiceId'] ?>">Mark as Paid</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10">No unpaid invoices found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".mark-paid-btn").forEach(button => {
        button.addEventListener("click", function () {
            const invoiceId = this.dataset.id;

            Swal.fire({
                title: 'Confirm Payment',
                text: "Are you sure you want to mark this invoice as paid?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, mark as Paid!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mark_invoice_paid.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ invoice_id: invoiceId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Invoice marked as Paid.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    });
                }
            });
        });
    });
});
</script>