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

if (empty($branch)) {
    echo json_encode(['success' => false, 'message' => 'Branch is required']);
    exit();
}

// Get ALL teachers for this branch
$query = "SELECT id, name, email FROM teacher WHERE branch = '$branch'";
$result = mysqli_query($data, $query);

$teachers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $teachers[] = $row;
}

echo json_encode(['success' => true, 'teachers' => $teachers]);
?>