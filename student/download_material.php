<?php
session_start();

/* AUTH CHECK */
if (!isset($_SESSION['username']) || ($_SESSION['usertype'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    exit("Database connection failed.");
}

// Get student branch and semester for verification
$student_email = $_SESSION['email'] ?? '';
$student_query = mysqli_query($data, "SELECT Branch, Semester FROM admission WHERE Email='$student_email'");
$student = mysqli_fetch_assoc($student_query);

$material_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($material_id <= 0) {
    exit("Invalid material.");
}

// Verify material belongs to student's branch and semester
$query = mysqli_query($data, "
    SELECT m.file_path, m.title, s.branch, s.semester 
    FROM materials m
    JOIN subjects s ON s.id = m.subject_id
    WHERE m.id='$material_id'
");

if (!$query || !($row = mysqli_fetch_assoc($query))) {
    exit("Material not found.");
}

// Check authorization
if ($row['branch'] != $student['Branch'] || $row['semester'] != $student['Semester']) {
    exit("Unauthorized access.");
}

$file_path = $row['file_path'] ?? '';

// IMPORTANT: DO NOT increment download count here - it's handled by AJAX
// Remove or comment out the line below:
// mysqli_query($data, "UPDATE materials SET downloads = downloads + 1 WHERE id='$material_id'");

// Try original path first, then fallback to other paths
$absolute_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file_path);

if (empty($file_path) || !file_exists($absolute_path)) {
    // Try alternative path
    $alternative_path = "../" . $file_path;
    if (!file_exists($alternative_path)) {
        exit("File not found on server.");
    }
    $absolute_path = $alternative_path;
}

// Send file for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($absolute_path) . '"');
header('Content-Length: ' . filesize($absolute_path));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

ob_clean();
flush();
readfile($absolute_path);
exit();
?>  