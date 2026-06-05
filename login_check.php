<?php
session_start();
require_once 'config.php';

function verify_and_upgrade_password(array $userRow, string $plainPassword, $conn): bool
{
    // Prefer hashed verification if present
    if (!empty($userRow['password_hash']) && password_verify($plainPassword, $userRow['password_hash'])) {
        return true;
    }

    // Fallback to legacy plain-text match
    if ($plainPassword === $userRow['password']) {
        // Upgrade to hashed password for next login
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE user SET password_hash=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $newHash, $userRow['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return true;
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    $stmt = mysqli_prepare($data, "SELECT id, username, email, password, password_hash, usertype, profile_pic FROM user WHERE email=? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    } else {
        $row = null;
    }

    if ($row && verify_and_upgrade_password($row, $pass, $data)) {

        $_SESSION['username'] = $row['username'];
        $_SESSION['usertype'] = $row['usertype'];
        $_SESSION['email']    = $row['email'];
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['profile_pic'] = $row['profile_pic'];

        mysqli_query($data, "UPDATE user SET last_login=NOW() WHERE id=" . intval($row['id']));

        if ($row['usertype'] === 'student') {
            // Fetch student details using user_id first, then email as fallback
            $stmt = mysqli_prepare($data, "SELECT id, Branch, Semester FROM admission WHERE user_id=? OR Email=? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "is", $row['id'], $row['email']);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($res && ($sr = mysqli_fetch_assoc($res))) {
                    $_SESSION['student_id'] = $sr['id'];
                    $_SESSION['branch']     = $sr['Branch'];
                    $_SESSION['semester']   = $sr['Semester'];
                }
                mysqli_stmt_close($stmt);
            }
            header("Location: student/home.php");
            exit();
        } elseif ($row['usertype'] === 'admin') {
            header("Location: admin/home.php");
            exit();
        } elseif ($row['usertype'] === 'teacher') {
            header("Location: teacher/dashboard.php");
            exit();
        }

    } else {
        $_SESSION['loginMessage'] = "Invalid email or password";
        header("Location: login.php");
        exit();
    }
}
?>
