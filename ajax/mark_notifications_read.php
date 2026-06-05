<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);

/* DB CONNECTION */
$host = "localhost";
$db_user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $db_user, $password, $db);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0";
if (mysqli_query($data, $update_sql)) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($data)]);
}
?>
