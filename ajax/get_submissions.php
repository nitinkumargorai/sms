<?php
session_start();
header('Content-Type: application/json');

/* AUTH CHECK */
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

/* DB CONNECTION */
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

// Get assignment details for max marks and branch/semester info
$assignment_query = mysqli_query($data, "
    SELECT 
        a.total_marks,
        a.title,
        s.branch,
        s.semester,
        s.subject_name
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.id = $assignment_id
");
$assignment = mysqli_fetch_assoc($assignment_query);
$max_marks = $assignment['total_marks'] ?? 20;
$assignment_branch = $assignment['branch'];
$assignment_semester = $assignment['semester'];

// Get all submissions for this assignment with student details
$submissions_query = mysqli_query($data, "
    SELECT 
        s.id,
        s.submission_date,
        s.submission_time,
        s.file_path,
        s.remarks,
        s.marks,
        s.feedback,
        s.status,
        a.id as student_id,
        a.Name as student_name,
        a.registration_no,
        a.Email as student_email,
        a.Branch,
        a.Semester,
        a.mobile
    FROM submissions s
    JOIN admission a ON a.id = s.student_id
    WHERE s.assignment_id = $assignment_id
    ORDER BY s.submission_date DESC, s.submission_time DESC
");

$submissions = [];
while ($row = mysqli_fetch_assoc($submissions_query)) {
    // Format file path for download
    $file_path = $row['file_path'];
    $download_path = "../" . $file_path;
    if (!file_exists($download_path)) {
        $download_path = $file_path;
    }
    
    $submissions[] = [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'student_name' => $row['student_name'],
        'registration_no' => $row['registration_no'],
        'student_email' => $row['student_email'],
        'branch' => $row['Branch'],
        'semester' => $row['Semester'],
        'mobile' => $row['mobile'],
        'submission_date' => date('d M Y', strtotime($row['submission_date'])),
        'submission_time' => date('h:i A', strtotime($row['submission_time'])),
        'file_path' => $download_path,
        'remarks' => $row['remarks'],
        'marks' => $row['marks'],
        'max_marks' => $max_marks,
        'feedback' => $row['feedback'],
        'status' => $row['status']
    ];
}

// Get students who haven't submitted yet
$not_submitted_query = mysqli_query($data, "
    SELECT 
        a.id,
        a.Name as student_name,
        a.registration_no,
        a.Email as student_email,
        a.Branch,
        a.Semester,
        a.mobile
    FROM admission a
    WHERE a.Branch = '$assignment_branch' 
    AND a.Semester = '$assignment_semester'
    AND a.id NOT IN (
        SELECT student_id FROM submissions WHERE assignment_id = $assignment_id
    )
    ORDER BY a.Name
");

$not_submitted = [];
while ($row = mysqli_fetch_assoc($not_submitted_query)) {
    $not_submitted[] = [
        'student_id' => $row['id'],
        'student_name' => $row['student_name'],
        'registration_no' => $row['registration_no'],
        'student_email' => $row['student_email'],
        'branch' => $row['Branch'],
        'semester' => $row['Semester'],
        'mobile' => $row['mobile']
    ];
}

echo json_encode([
    'success' => true,
    'submissions' => $submissions,
    'not_submitted' => $not_submitted,
    'max_marks' => $max_marks,
    'total_submitted' => count($submissions),
    'total_not_submitted' => count($not_submitted),
    'assignment_title' => $assignment['title'],
    'subject_name' => $assignment['subject_name'],
    'branch' => $assignment_branch,
    'semester' => $assignment_semester
]);
?>