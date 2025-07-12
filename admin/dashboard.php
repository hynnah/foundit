<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Pending Reports";

require_once '../dbh.inc.php';

// Updated query to include image_path
$sql = "SELECT r.ReportID, r.item_name, r.description, r.report_type, r.submission_date, r.image_path, p.name AS submitter_name
        FROM Report r
        JOIN User u ON r.UserID_submitter = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        WHERE r.ApprovalStatusID = 1
        ORDER BY r.submission_date DESC";

$result = mysqli_query($connection, $sql);
$pending_reports = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pending_reports[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Admin Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <style>/* ayaw hilabti*/
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #f1f1f1;
        }
        .action-btn {
            margin-right: 10px;
        }
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>

    <h2><?= htmlspecialchars($content_header) ?></h2>

    <?php if (empty($pending_reports)) : ?>
        <p>No pending reports at the moment.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Submitted By</th>
                    <th>Date Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_reports as $report) : ?>
                    <tr>
                        <td>
                            <?php if (!empty($report['image_path'])): ?>
                                <img src="../<?= htmlspecialchars($report['image_path']) ?>" alt="Item Image" class="item-image">
                            <?php else: ?>
                                <img src="../images/default-item.jpg" alt="Default Image" class="item-image">
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($report['item_name']) ?></td>
                        <td><?= htmlspecialchars($report['description']) ?></td>
                        <td><?= ucfirst($report['report_type']) ?></td>
                        <td><?= htmlspecialchars($report['submitter_name']) ?></td>
                        <td><?= htmlspecialchars($report['submission_date']) ?></td>
                        <td>
                            <form action="process_approval.php" method="POST" style="display:inline;">
                                <input type="hidden" name="report_id" value="<?= $report['ReportID'] ?>">
                                <button class="action-btn" name="action" value="approve">Approve</button>
                                <button class="action-btn" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    $page_content = ob_get_clean();
    include_once "../includes/admin_layout.php";
    ?>
</body>
</html>
