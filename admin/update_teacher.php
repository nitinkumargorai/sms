<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = mysqli_real_escape_string($data, $_POST['teacher_id']);
    $name = mysqli_real_escape_string($data, $_POST['name']);
    $email = mysqli_real_escape_string($data, $_POST['email']);
    $mobile = mysqli_real_escape_string($data, $_POST['mobile']);
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $qualification = mysqli_real_escape_string($data, $_POST['qualification']);
    $experience = mysqli_real_escape_string($data, $_POST['experience']);
    $address = mysqli_real_escape_string($data, $_POST['address']);
    $is_active = mysqli_real_escape_string($data, $_POST['is_active']);
    
    $update_query = "UPDATE teacher SET 
                     name='$name',
                     email='$email',
                     mobile='$mobile',
                     branch='$branch',
                     qualification='$qualification',
                     experience='$experience',
                     address='$address',
                     is_active='$is_active'
                     WHERE id='$teacher_id'";
    
    if (mysqli_query($data, $update_query)) {
        // Also update user table username
        mysqli_query($data, "UPDATE user SET username='$name' WHERE email='$email' AND usertype='teacher'");
        header("Location: view_teacher.php?msg=" . urlencode("Teacher updated successfully!") . "&type=success");
    } else {
        header("Location: view_teacher.php?msg=" . urlencode("Error updating teacher: " . mysqli_error($data)) . "&type=error");
    }
}
?>