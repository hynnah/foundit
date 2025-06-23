<?php
if (isset($_POST['login-submit'])) {
    require 'dbh.inc.php';

    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=emptyfields");
        exit();
    }
    else {
        $sql = "SELECT p.*, u.role FROM Person p JOIN Users u ON p.PersonID = u.PersonID WHERE p.email=?";
        $stmt = mysqli_stmt_init($connection);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header("Location: login.php?error=sqlerror");
            exit();
        }
        else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $pwdCheck = password_verify($password, $row['password']);
                if ($pwdCheck === false) {
                    header("Location: login.php?error=wrongcredentials");
                    exit();
                }
                else if ($pwdCheck === true) {
                    session_start();
                    $_SESSION['userId'] = $row['PersonID'];
                    $_SESSION['userEmail'] = $row['email'];
                    $_SESSION['userRole'] = $row['role'];
                    $_SESSION['userName'] = $row['name'];

                    // Redirect based on role
                    if ($row['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                }
            }
            else {
                header("Location: login.php?error=wrongcredentials");
                exit();
            }
        }
    }
}
else {
    header("Location: login.php");
    exit();
}