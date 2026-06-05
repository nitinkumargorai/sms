<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Connection failed: " . mysqli_connect_error());
}

$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id > 0) {
    $query = mysqli_query($data, "SELECT file_path FROM assignments WHERE id = '$assignment_id'");
    
    if ($query && $row = mysqli_fetch_assoc($query)) {
        $file_path = "../" . $row['file_path'];
        
        if (!empty($row['file_path']) && file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        } else {
            echo "<h3>File not found!</h3><a href='assignments.php'>Go Back</a>";
        }
    } else {
        echo "<h3>Assignment not found!</h3><a href='assignments.php'>Go Back</a>";
    }
} else {
    echo "<h3>Invalid request!</h3><a href='assignments.php'>Go Back</a>";
}
?>