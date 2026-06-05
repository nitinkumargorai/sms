<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'teacher') {
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

$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;

if ($assignment_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit();
}

// Get teacher ID to verify ownership
$teacher_email = $_SESSION['email'] ?? '';
$teacher_query = mysqli_query($data, "SELECT id FROM teacher WHERE email='$teacher_email'");
$teacher = mysqli_fetch_assoc($teacher_query);
$teacher_id = $teacher['id'];

// Verify assignment belongs to this teacher
$verify_query = mysqli_query($data, "SELECT id, total_marks, title FROM assignments WHERE id = $assignment_id AND teacher_id = $teacher_id");
if (!$verify_query || mysqli_num_rows($verify_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Assignment not found or unauthorized']);
    exit();
}
$assignment = mysqli_fetch_assoc($verify_query);
$max_marks = $assignment['total_marks'];
$assignment_title = $assignment['title'];

// Get submissions with student details
$submissions_query = mysqli_query($data, "
    SELECT 
        s.id,
        s.submission_date,
        s.submission_time,
        s.file_path,
        s.marks,
        s.feedback,
        s.status,
        s.remarks,
        a.id as student_id,
        a.Name as student_name,
        a.registration_no,
        a.Email as student_email,
        a.mobile
    FROM submissions s
    JOIN admission a ON a.id = s.student_id
    WHERE s.assignment_id = $assignment_id
    ORDER BY s.submission_date DESC, s.submission_time DESC
");

$submissions = [];
while ($row = mysqli_fetch_assoc($submissions_query)) {
    // Fix file path for download
    $file_path = $row['file_path'];
    
    // Try multiple possible paths
    $possible_paths = [
        "../" . $file_path,
        $file_path,
        "../uploads/submissions/" . basename($file_path),
        "uploads/submissions/" . basename($file_path)
    ];
    
    $found_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $found_path = $path;
            break;
        }
    }
    
    if ($found_path) {
        $download_url = "download_submission.php?id=" . $row['id'];
        $file_exists = true;
    } else {
        $download_url = "#";
        $file_exists = false;
    }
    
    $submissions[] = [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'student_name' => $row['student_name'],
        'registration_no' => $row['registration_no'],
        'student_email' => $row['student_email'],
        'mobile' => $row['mobile'],
        'submission_date' => date('d M Y', strtotime($row['submission_date'])),
        'submission_time' => date('h:i A', strtotime($row['submission_time'])),
        'full_datetime' => date('d M Y h:i A', strtotime($row['submission_date'] . ' ' . $row['submission_time'])),
        'file_path' => $row['file_path'],
        'download_url' => $download_url,
        'file_exists' => $file_exists,
        'remarks' => $row['remarks'],
        'marks' => $row['marks'],
        'max_marks' => $max_marks,
        'feedback' => $row['feedback'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'submissions' => $submissions,
    'total_submissions' => count($submissions),
    'assignment_title' => $assignment_title,
    'max_marks' => $max_marks
]);
?>