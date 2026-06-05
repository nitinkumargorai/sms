<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
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

$branch = mysqli_real_escape_string($data, $_GET['branch'] ?? '');

if (empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Branch is required']);
    exit();
}

// Get ALL subjects for this branch (regardless of semester)
$query = "SELECT id, subject_code, subject_name, credits, semester FROM subjects 
          WHERE branch = '$branch' 
          ORDER BY semester, subject_code";
$result = mysqli_query($data, $query);

$subjects = [];
while ($row = mysqli_fetch_assoc($result)) {
    $subjects[] = $row;
}

echo json_encode(['success' => true, 'subjects' => $subjects]);
?>