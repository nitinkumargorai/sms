<?php
session_start();

/* AUTH CHECK */
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
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

$student_id = $_SESSION['student_id'] ?? 0;

// Verify submission belongs to this student
$query = mysqli_query($data, "
    SELECT s.file_path, ad.Name as student_name
    FROM submissions s
    JOIN admission ad ON ad.id = s.student_id
    WHERE s.id = $submission_id AND s.student_id = $student_id
");

if (!$query || mysqli_num_rows($query) == 0) {
    header("Location: assignments.php?msg=Unauthorized&type=error");
    exit();
}

$row = mysqli_fetch_assoc($query);
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
    $file_name = basename($found_path);
    $file_size = filesize($found_path);
    
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
