<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
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

$student_id = $_SESSION['student_id'] ?? 0;
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
$remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($data, trim($_POST['remarks'])) : '';

if ($assignment_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit();
}

// Check if already submitted
$check_query = mysqli_query($data, "SELECT COUNT(*) as cnt FROM submissions WHERE assignment_id = $assignment_id AND student_id = $student_id");
if ($check_query && mysqli_fetch_assoc($check_query)['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted this assignment']);
    exit();
}

// Get assignment due date
$assignment_query = mysqli_query($data, "SELECT due_date, due_time FROM assignments WHERE id = $assignment_id");
$assignment = mysqli_fetch_assoc($assignment_query);
$due_datetime = strtotime($assignment['due_date'] . ' ' . $assignment['due_time']);
$is_overdue = (time() > $due_datetime);

// Handle file upload
if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] != 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload']);
    exit();
}

$target_dir = "../uploads/submissions/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$file_extension = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: PDF, DOC, DOCX, TXT, ZIP, RAR, JPG, PNG']);
    exit();
}

// Check file size (max 10MB)
if ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB']);
    exit();
}

$new_filename = time() . '_' . $student_id . '_' . $assignment_id . '.' . $file_extension;
$target_file = $target_dir . $new_filename;

if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file)) {
    $file_path = "uploads/submissions/" . $new_filename;
    $submission_date = date('Y-m-d');
    $submission_time = date('H:i:s');
    $status = $is_overdue ? 'late' : 'submitted';
    
    $insert_sql = "INSERT INTO submissions (assignment_id, student_id, submission_date, submission_time, file_path, remarks, status) 
                   VALUES ('$assignment_id', '$student_id', '$submission_date', '$submission_time', '$file_path', '$remarks', '$status')";
    
    if (mysqli_query($data, $insert_sql)) {
        // Get teacher ID for notification
        $teacher_query = mysqli_query($data, "SELECT teacher_id FROM assignments WHERE id = $assignment_id");
        if ($teacher_query && $teacher_row = mysqli_fetch_assoc($teacher_query)) {
            $teacher_id = $teacher_row['teacher_id'];
            
            // Insert notification for teacher
            $student_name = $_SESSION['username'];
            $notification_msg = "Student $student_name has submitted assignment #$assignment_id";
            mysqli_query($data, "INSERT INTO notifications (user_id, title, message, usertype, created_at) 
                                VALUES ((SELECT user_id FROM teacher WHERE id = $teacher_id), 'New Submission', '$notification_msg', 'teacher', NOW())");
        }
        
        $message = $is_overdue ? 
            "Your assignment has been submitted but is marked as LATE due to deadline passed. The teacher will review it with applicable penalties." :
            "Your assignment has been submitted successfully. The teacher will review it shortly.";
        
        echo json_encode([
            'success' => true,
            'title' => $is_overdue ? 'Assignment Submitted (Late)' : 'Assignment Submitted!',
            'message' => $message
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($data)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file. Please try again.']);
}
?>