<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Attendance Overview</h4>
        <div>
            <a href="home.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted mb-2">Planned: view daily/subject-wise attendance, override entries, and export CSV.</p>
            <p class="mb-0">Navigation link is active; implementation is queued.</p>
        </div>
    </div>
</div>
</body>
</html>
