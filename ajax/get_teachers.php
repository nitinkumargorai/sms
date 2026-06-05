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

// Get ALL teachers - no branch filter
$query = "SELECT id, name, email FROM teacher WHERE is_active = 1 OR is_active IS NULL ORDER BY name";
$result = mysqli_query($data, $query);

$teachers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $teachers[] = $row;
}

echo json_encode(['success' => true, 'teachers' => $teachers]);
?>