<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || ($_SESSION['usertype'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$material_id = isset($_POST['material_id']) ? (int) $_POST['material_id'] : 0;

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit();
}

// Get student branch and semester for verification
$student_email = $_SESSION['email'] ?? '';
$student_query = mysqli_query($data, "SELECT Branch, Semester FROM admission WHERE Email='$student_email'");
$student = mysqli_fetch_assoc($student_query);

// Verify material belongs to student's branch and semester
$verify_query = mysqli_query($data, "
    SELECT m.id 
    FROM materials m
    JOIN subjects s ON s.id = m.subject_id
    WHERE m.id = $material_id AND s.branch = '{$student['Branch']}' AND s.semester = '{$student['Semester']}'
");

if (mysqli_num_rows($verify_query) > 0) {
    $update_query = mysqli_query($data, "UPDATE materials SET downloads = downloads + 1 WHERE id = $material_id");
    
    if ($update_query) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update download count']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
}
?>