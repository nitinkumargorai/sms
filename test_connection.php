<?php
session_start();
echo "<h2>Database Connection Test</h2>";

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "<p style='color:green'>✓ Database connected successfully</p>";

// Check session
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check assignments table
$result = mysqli_query($data, "SELECT COUNT(*) as count FROM assignments");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>✓ Assignments table has " . $row['count'] . " records</p>";
} else {
    echo "<p style='color:red'>✗ Error reading assignments table: " . mysqli_error($data) . "</p>";
}

// Check submissions table
$result = mysqli_query($data, "SELECT COUNT(*) as count FROM submissions");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>✓ Submissions table has " . $row['count'] . " records</p>";
} else {
    echo "<p style='color:orange'>⚠ Submissions table may not exist: " . mysqli_error($data) . "</p>";
}

// Get student ID from session
$student_id = $_SESSION['student_id'] ?? 0;
echo "<p>Student ID from session: " . $student_id . "</p>";

// Get a sample assignment
$result = mysqli_query($data, "SELECT a.id, a.title, s.subject_name FROM assignments a JOIN subjects s ON s.id = a.subject_id LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>✓ Sample assignment found: ID=" . $row['id'] . ", Title=" . $row['title'] . "</p>";
} else {
    echo "<p style='color:red'>✗ No assignments found in database!</p>";
}
?>