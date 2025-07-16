<?php
if (isset($_POST['signup-submit'])) {
    require 'dbh.inc.php';

    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $passwordRepeat = $_POST['password-repeat'];

    // Validate inputs
    if (empty($name) || empty($email) || empty($role) || empty($password) || empty($passwordRepeat)) {
        header("Location: register.php?error=emptyfields&name=".$name."&email=".$email."&phone=".$phone."&role=".$role);
        exit();
    }
    
    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 100) {
        header("Location: register.php?error=invalidname&email=".$email."&phone=".$phone."&role=".$role);
        exit();
    }
    
    // Validate email format and length
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        header("Location: register.php?error=invalidemail&name=".$name."&phone=".$phone."&role=".$role);
        exit();
    }
    
    // Validate phone number (numbers only, 10-15 digits)
    if (!empty($phone) && (!preg_match('/^[0-9]{10,15}$/', $phone) || strlen($phone) > 20)) {
        header("Location: register.php?error=invalidphone&name=".$name."&email=".$email."&role=".$role);
        exit();
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        header("Location: register.php?error=passwordweak&name=".$name."&email=".$email."&phone=".$phone."&role=".$role);
        exit();
    }
    
    if ($password !== $passwordRepeat) {
        header("Location: register.php?error=passwordmismatch&name=".$name."&email=".$email."&phone=".$phone."&role=".$role);
        exit();
    }

    // Check if email exists
    $sql = "SELECT email FROM Person WHERE email=?";
    $stmt = mysqli_stmt_init($connection);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("Location: register.php?error=sqlerror");
        exit();
    }
    else {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $resultCheck = mysqli_stmt_num_rows($stmt);
        if ($resultCheck > 0) {
            header("Location: register.php?error=emailtaken&name=".$name."&phone=".$phone."&role=".$role);
            exit();
        }
    }

    // Hash password
    $hashedPwd = password_hash($password, PASSWORD_DEFAULT);

    // Insert into Person table
    $sql = "INSERT INTO Person (name, email, phone_number, password) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_stmt_init($connection);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("Location: register.php?error=sqlerror");
        exit();
    }
    else {
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $hashedPwd);
        mysqli_stmt_execute($stmt);
        $personId = mysqli_insert_id($connection);

        // Insert into User table
        $sql = "INSERT INTO User (UserID, role) VALUES (?, ?)";
        $stmt = mysqli_stmt_init($connection);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header("Location: register.php?error=sqlerror");
            exit();
        }
        else {
            mysqli_stmt_bind_param($stmt, "is", $personId, $role);
            mysqli_stmt_execute($stmt);

            // Start session and redirect
            session_start();
            $_SESSION['userId'] = $personId;
            $_SESSION['userEmail'] = $email;
            $_SESSION['userRole'] = $role;
            $_SESSION['userName'] = $name;

            header("Location: register.php?signup=success");
            mysqli_stmt_close($stmt);
            mysqli_close($connection);
            exit();
        }
    }
    mysqli_stmt_close($stmt);
    mysqli_close($connection);
}
else {
    header("Location: register.php");
    exit();
}