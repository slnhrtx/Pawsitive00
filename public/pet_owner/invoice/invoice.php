<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../config/dbh.inc.php';
require '../../../src/helpers/session_helpers.php';
require '../../../src/helpers/auth_helpers.php';

checkAuthentication($pdo);

$userId = $_SESSION['UserId'];
$ownerEmail = $_SESSION['Email'];
$ownerName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];

// ✅ Fetch invoices linked to the owner's pets
$stmt = $pdo->prepare("
    SELECT i.InvoiceId, i.InvoiceNumber, i.InvoiceDate, i.TotalAmount, i.Status, i.PaidAt,
           a.AppointmentDate, p.Name AS PetName, s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE o.Email = ?
    ORDER BY i.InvoiceDate DESC
");
$stmt->execute([$ownerEmail]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/owner_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="main-content">
        <h2>Invoice History</h2>
        <p>View all your past invoices related to pet services.</p>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Invoice Date</th>
                    <th>Appointment Date</th>
                    <th>Pet Name</th>
                    <th>Service</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Paid At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= htmlspecialchars($invoice['InvoiceNumber']) ?></td>
                            <td><?= htmlspecialchars($invoice['InvoiceDate']) ?></td>
                            <td><?= htmlspecialchars($invoice['AppointmentDate']) ?></td>
                            <td><?= htmlspecialchars($invoice['PetName']) ?></td>
                            <td><?= htmlspecialchars($invoice['ServiceName']) ?></td>
                            <td>$<?= number_format($invoice['TotalAmount'], 2) ?></td>
                            <td>
                                <?php if ($invoice['Status'] === 'Paid'): ?>
                                    <span class="status paid">Paid</span>
                                <?php elseif ($invoice['Status'] === 'Overdue'): ?>
                                    <span class="status overdue">Overdue</span>
                                <?php else: ?>
                                    <span class="status pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $invoice['PaidAt'] ? htmlspecialchars($invoice['PaidAt']) : '-' ?></td>
                            <td>
                                <button class="download-btn" data-id="<?= $invoice['InvoiceId'] ?>">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9">No invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".download-btn").forEach(button => {
            button.addEventListener("click", function () {
                const invoiceId = this.dataset.id;
                window.location.href = `download_invoice.php?invoice_id=${invoiceId}`;
            });
        });
    });
    </script>

    <style>
        body { font-family: 'Arial', sans-serif; }
        .main-content { max-width: 90%; margin: auto; padding: 20px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .invoice-table th, .invoice-table td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        .invoice-table th { background-color: #f4f4f4; }
        .status { padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .status.paid { background-color: #4CAF50; color: white; }
        .status.pending { background-color: #FFC107; color: white; }
        .status.overdue { background-color: #E74C3C; color: white; }
        .download-btn { background: #3498db; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 5px; }
        .download-btn:hover { background: #2980b9; }
    </style>
</body>
</html>