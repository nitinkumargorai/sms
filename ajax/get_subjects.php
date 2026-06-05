<?php
header('Content-Type: application/json');

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
$semester = intval($_GET['semester'] ?? 0);

if (empty($branch) || $semester <= 0) {
    echo json_encode(['success' => false, 'message' => 'Branch and semester are required']);
    exit();
}

$query = "SELECT id, subject_code, subject_name, credits FROM subjects 
          WHERE branch = '$branch' AND semester = $semester 
          ORDER BY subject_code";
$result = mysqli_query($data, $query);

$subjects = [];
while ($row = mysqli_fetch_assoc($result)) {
    $subjects[] = $row;
}

echo json_encode(['success' => true, 'subjects' => $subjects]);
?>