<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Found Items";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Found Items</title>
    <link rel="stylesheet" href="style.css">
    <style> /* ayaw hilabti*/
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <!-- Please dont remove -->  
    <?php
    ob_start();
    ?> 

    <!-- Just add your main content here-->

    <!-- Please dont remove -->  
    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>

</html>