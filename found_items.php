<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Found Items";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Found Items</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>

    <div class="content">
        <h2>Search Found Items</h2>
        <form method="get" action="found_items.php" style="margin-bottom: 20px;">
            <input type="text" name="search" placeholder="Search by item name or location" 
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="submit">Search</button>
        </form>

        <h2>List of Found Items</h2>
        <table border="1" cellpadding="8" cellspacing="0" style="width:100%;background:#fff;">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Date Found</th>
                    <th>Location Found</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            require_once 'dbh.inc.php';

            $search = $_GET['search'] ?? '';
            
            // Corrected SQL query without PHP-style comments
            $sql = "SELECT 
                        r.item_name, 
                        r.description, 
                        r.incident_date, 
                        r.image_path,
                        IFNULL(f.location_found, 'N/A') as location_found,
                        IFNULL(fp.post_status, 'Active') as post_status
                    FROM Report r
                    LEFT JOIN Found f ON r.ReportID = f.ReportID
                    LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
                    WHERE r.report_type = 'Found'
                    AND r.ApprovalStatusID = 2";

            if (!empty($search)) {
                $sql .= " AND r.item_name LIKE ?";
            }

            $stmt = mysqli_prepare($connection, $sql);
            if (!empty($search)) {
                $like = '%' . $search . '%';
                mysqli_stmt_bind_param($stmt, "s", $like);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                    echo "<td>" . htmlspecialchars(date('Y-m-d', strtotime($row['incident_date']))) . "</td>";
                    echo "<td>" . htmlspecialchars($row['location_found']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['post_status']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center;'>No found items found.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>
</body>
</html>