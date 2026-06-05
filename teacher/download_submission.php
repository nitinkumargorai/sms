<?php
session_start();

/* AUTH CHECK */
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

/* DB CONNECTION */
$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Database connection failed");
}

$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($submission_id == 0) {
    header("Location: assignments.php?msg=Invalid request&type=error");
    exit();
}

// Get teacher ID
$teacher_email = $_SESSION['email'] ?? '';
$teacher_query = mysqli_query($data, "SELECT id FROM teacher WHERE email='$teacher_email'");
$teacher = mysqli_fetch_assoc($teacher_query);
$teacher_id = $teacher['id'];

// Verify submission belongs to teacher's assignment
$verify_query = mysqli_query($data, "
    SELECT s.file_path, s.student_id, ad.Name as student_name
    FROM submissions s
    JOIN assignments a ON a.id = s.assignment_id
    JOIN admission ad ON ad.id = s.student_id
    WHERE s.id = $submission_id AND a.teacher_id = $teacher_id
");

if (!$verify_query || mysqli_num_rows($verify_query) == 0) {
    header("Location: assignments.php?msg=Unauthorized&type=error");
    exit();
}

$row = mysqli_fetch_assoc($verify_query);
$file_path = $row['file_path'];
$student_name = $row['student_name'];

// Try multiple possible paths
$possible_paths = [
    "../" . $file_path,
    "../../" . $file_path,
    $file_path,
    "../uploads/submissions/" . basename($file_path),
    "../../uploads/submissions/" . basename($file_path),
    "uploads/submissions/" . basename($file_path)
];

$found_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $found_path = $path;
        break;
    }
}

if ($found_path && file_exists($found_path)) {
    $file_name = $student_name . '_' . basename($found_path);
    $file_size = filesize($found_path);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Set appropriate headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Transfer-Encoding: binary');
    header('Connection: Keep-Alive');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read the file and output it
    readfile($found_path);
    exit();
} else {
    echo "<script>
        alert('File not found. The submission file may have been deleted.');
        window.location.href = 'assignments.php';
    </script>";
    exit();
}
?>