<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit();
}

$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
$student_id = $_SESSION['student_id'] ?? 0;

if ($assignment_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit();
}

// Check if already submitted
$check_query = mysqli_query($data, "SELECT COUNT(*) as cnt FROM submissions WHERE assignment_id = $assignment_id AND student_id = $student_id");
if ($check_query) {
    $check_row = mysqli_fetch_assoc($check_query);
    if ($check_row['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted this assignment']);
        exit();
    }
}

$query = mysqli_query($data, "
    SELECT 
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.due_time,
        a.total_marks,
        s.subject_name
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.id = $assignment_id
");

if ($query && mysqli_num_rows($query) > 0) {
    $row = mysqli_fetch_assoc($query);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'due_date' => $row['due_date'],
            'due_time' => $row['due_time'],
            'due_date_formatted' => date('d M Y', strtotime($row['due_date'])),
            'due_time_formatted' => date('h:i A', strtotime($row['due_time'])),
            'total_marks' => $row['total_marks'],
            'subject_name' => $row['subject_name']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Assignment not found in database']);
}
?>
