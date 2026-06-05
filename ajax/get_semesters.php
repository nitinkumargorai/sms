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

// Get unique semesters for subjects in this branch
$query = "SELECT DISTINCT semester FROM subjects WHERE branch = '$branch' ORDER BY semester";
$result = mysqli_query($data, $query);

$semesters = [];
while ($row = mysqli_fetch_assoc($result)) {
    $semesters[] = $row['semester'];
}

echo json_encode(['success' => true, 'semesters' => $semesters]);
?>