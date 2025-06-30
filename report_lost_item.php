<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Report Lost Item";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Report Lost Item</title>
    <link rel="stylesheet" href="style.css">
    <style> /* ayaw hilabti*/
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <!-- Please dont remove, kay maguba -->  
    <?php
    ob_start();
    ?> 

    <!-- Just add your main content here, basta! just delete below-->  
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px;">
        <img src="resources/hannah-cute.gif" alt="report if lost icon, cheerful mood" style="display: block; margin: 0 auto; width: 320px; height: auto; object-fit: cover;">
        <div style="margin-top: 18px;">
            <h1 style="text-align: center;">Hi, I am report lost items page, I am waiting for Johnfranz</h1>
        </div>
    </div>

    <!-- Please dont remove, kay maguba(2) -->  
    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>

</html>