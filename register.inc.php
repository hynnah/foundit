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
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=invalidemail&name=".$name."&phone=".$phone."&role=".$role);
        exit();
    }
    else if ($password !== $passwordRepeat) {
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

        // Insert into Users table
        $sql = "INSERT INTO Users (PersonID, role) VALUES (?, ?)";
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