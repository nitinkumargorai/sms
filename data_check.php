<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name     = mysqli_real_escape_string($data, $_POST['name']);
    $email    = mysqli_real_escape_string($data, $_POST['email']);
    $pass     = mysqli_real_escape_string($data, $_POST['password']);
    $reg_no   = mysqli_real_escape_string($data, $_POST['reg_no']);
    $branch   = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = mysqli_real_escape_string($data, $_POST['semester']);

    // Check if email already exists in user or admission_requests
    $check_email = mysqli_query($data, "SELECT id FROM user WHERE email='$email'");
    if ($check_email && mysqli_num_rows($check_email) > 0) {
        $_SESSION['status'] = "error";
        $_SESSION['message'] = "This email is already registered. Please login instead.";
        header("Location: signup.php");
        exit();
    }

    $check_pending = mysqli_query($data, "SELECT id FROM admission_requests WHERE email='$email'");
    if ($check_pending && mysqli_num_rows($check_pending) > 0) {
        $_SESSION['status'] = "error";
        $_SESSION['message'] = "A registration request with this email is already pending.";
        header("Location: signup.php");
        exit();
    }

    // Insert into admission_requests
    $sql = "INSERT INTO admission_requests 
            (name, email, password, registration_no, branch, semester, status)
            VALUES
            ('$name', '$email', '$pass', '$reg_no', '$branch', '$semester', 'pending')";

    if (mysqli_query($data, $sql)) {
        $_SESSION['status'] = "success";
        $_SESSION['message'] = "Registration request sent successfully! Wait for admin approval.";
    } else {
        $_SESSION['status'] = "error";
        $_SESSION['message'] = "Something went wrong: " . mysqli_error($data);
    }

    header("Location: signup.php");
    exit();
}
?>