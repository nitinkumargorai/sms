<?php
session_start();

/* AUTH CHECK */
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
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
    die("Database connection failed: " . mysqli_connect_error());
}

$message = "";
$message_type = "";

/* HANDLE DELETE ASSIGNMENT */
if (isset($_GET['delete_id'])) {
    $assignment_id = intval($_GET['delete_id']);
    
    mysqli_query($data, "DELETE FROM submissions WHERE assignment_id = $assignment_id");
    $delete_sql = "DELETE FROM assignments WHERE id = $assignment_id";
    if (mysqli_query($data, $delete_sql)) {
        $message = "Assignment deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: assignments.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* HANDLE UPDATE ASSIGNMENT */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $description = mysqli_real_escape_string($data, trim($_POST['description']));
    $due_date = mysqli_real_escape_string($data, $_POST['due_date']);
    $due_time = mysqli_real_escape_string($data, $_POST['due_time']);
    $total_marks = intval($_POST['total_marks']);
    
    $update_sql = "UPDATE assignments SET 
                   title = '$title',
                   description = '$description',
                   due_date = '$due_date',
                   due_time = '$due_time',
                   total_marks = $total_marks
                   WHERE id = $assignment_id";
    
    if (mysqli_query($data, $update_sql)) {
        $message = "Assignment updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: assignments.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* HANDLE GRADE SUBMISSION */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $marks = intval($_POST['marks']);
    $feedback = mysqli_real_escape_string($data, trim($_POST['feedback']));
    
    $update_sql = "UPDATE submissions SET marks = $marks, feedback = '$feedback', status = 'graded' WHERE id = $submission_id";
    if (mysqli_query($data, $update_sql)) {
        $message = "Submission graded successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: assignments.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH ALL ASSIGNMENTS WITH DETAILS */
$assignments_query = mysqli_query($data, "
    SELECT 
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.due_time,
        a.total_marks,
        a.created_at,
        s.subject_name,
        s.subject_code,
        s.branch,
        s.semester,
        t.name as teacher_name,
        (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as total_submissions,
        (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND status = 'graded') as graded_count,
        (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND status = 'late') as late_count
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    JOIN teacher t ON t.id = a.teacher_id
    ORDER BY a.created_at DESC
");

$assignments = [];
while ($row = mysqli_fetch_assoc($assignments_query)) {
    $assignments[] = $row;
}

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Assignments | Admin Panel - StudyBuddyHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef476f;
            --dark: #1e1e2f;
            --gray: #6c757d;
            --light: #f8f9fa;
            --border: #e9ecef;
            --sidebar-width: 280px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        body { background: #f0f2f5; overflow-x: hidden; }
        .admin-wrap { display: flex; min-height: 100vh; position: relative; }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e1e2f 0%, #2a2a40 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            left: -100%;
            transition: left 0.3s ease;
        }
        .sidebar.active { left: 0; }
        @media (min-width: 769px) { .sidebar { left: 0; } }
        
        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-header h3 {
            font-size: 1.35rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        
        .sidebar-menu { padding: 0.5rem 0 1rem; }
        .menu-title {
            padding: 0.75rem 1.25rem 0.5rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            font-size: 0.85rem;
        }
        .sidebar a i { width: 22px; font-size: 1rem; }
        .sidebar a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary);
        }
        .sidebar a.active {
            background: linear-gradient(90deg, rgba(67,97,238,0.15), transparent);
            color: white;
            border-left-color: var(--primary);
            font-weight: 500;
        }
        .badge-count {
            background: var(--danger);
            color: white;
            padding: 0.15rem 0.5rem;
            border-radius: 50px;
            font-size: 0.65rem;
            margin-left: auto;
        }
        
        /* Main Content */
        .main {
            flex: 1;
            width: 100%;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }
        @media (min-width: 769px) { .main { margin-left: var(--sidebar-width); } }
        
        /* ===== NEW MODERN TOPBAR STYLES ===== */
        .modern-topbar {
            background: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 99;
            border-bottom: 1px solid var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 10px;
            transition: all 0.2s;
            display: none;
        }

        .menu-toggle-btn:hover {
            background: var(--light);
        }

        @media (max-width: 768px) {
            .menu-toggle-btn {
                display: block;
            }
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            letter-spacing: -0.3px;
        }

        .page-title i {
            color: var(--primary);
            margin-right: 0.5rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Quick Add Button */
        .quick-add-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .quick-add-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        /* Dropdown Menu */
        .quick-dropdown {
            position: relative;
        }

        .quick-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            min-width: 260px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1000;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .quick-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .quick-menu-header {
            padding: 0.75rem 1rem;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .quick-menu-header span {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .quick-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .quick-menu-item:hover {
            background: var(--light);
            padding-left: 1.25rem;
        }

        .quick-menu-item i {
            width: 22px;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .quick-menu-divider {
            height: 1px;
            background: var(--border);
            margin: 0.25rem 0;
        }

        /* Admin Profile */
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 1rem;
            background: var(--light);
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .admin-profile:hover {
            background: #e2e8f0;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            overflow: hidden;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .admin-info {
            display: block;
        }

        .admin-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark);
        }

        .admin-role {
            font-size: 0.7rem;
            color: var(--primary);
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .admin-info {
                display: none;
            }
            .quick-add-btn span {
                display: none;
            }
            .quick-add-btn {
                padding: 0.5rem;
            }
            .logout-btn span {
                display: none;
            }
        }

        /* Logout Button */
        .logout-btn {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }
        /* Content Area */
        .content { padding: 1rem; }
        @media (min-width: 768px) { .content { padding: 1.5rem; } }
        
        .page-header { margin-bottom: 1.5rem; }
        .page-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        @media (min-width: 768px) { .page-header h2 { font-size: 1.8rem; } }
        .page-header p { color: var(--gray); font-size: 0.85rem; }
        
        .alert-custom {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
            font-size: 0.85rem;
        }
        .alert-success {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        .alert-error {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .table-card:hover { box-shadow: var(--shadow-lg); }
        @media (min-width: 768px) { .table-card { padding: 1.5rem; } }
        
        .table-header {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        @media (min-width: 576px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        .table-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .total-assignments {
            background: rgba(67,97,238,0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--primary);
            display: inline-block;
            width: fit-content;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            min-width: 1000px;
        }
        thead th {
            font-size: 0.75rem;
            padding: 0.75rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
            text-align: left;
        }
        tbody td {
            font-size: 0.8rem;
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }
        
        .badge-subject {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        .badge-submissions {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        .badge-late {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        
        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-view, .btn-edit, .btn-delete {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        .btn-view {
            background: rgba(6,214,160,0.1);
            color: var(--success);
        }
        .btn-view:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        .btn-edit {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
        }
        .btn-edit:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .btn-delete {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
        }
        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }
        .empty-state h5 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        /* Modal Styles */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-custom.active { display: flex; }
        .modal-content-custom {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header-custom {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 24px 24px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header-custom h5 { margin: 0; font-weight: 600; font-size: 1.1rem; }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: var(--transition);
        }
        .modal-close:hover { opacity: 1; transform: scale(1.1); }
        .modal-body-custom { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            font-weight: 500;
            margin-bottom: 0.3rem;
            display: block;
            font-size: 0.85rem;
            color: var(--dark);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        textarea { resize: vertical; min-height: 80px; }
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .btn-cancel {
            background: var(--gray);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-cancel:hover { background: #5a6268; }
        
        /* Submissions Table */
        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .submissions-table th {
            text-align: left;
            padding: 0.5rem;
            background: var(--light);
            font-size: 0.7rem;
            font-weight: 600;
        }
        .submissions-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.75rem;
        }
        .btn-download {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-size: 0.65rem;
            text-decoration: none;
        }
        .btn-download:hover { background: var(--primary); color: white; }
        .btn-grade {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-size: 0.65rem;
            border: none;
            cursor: pointer;
        }
        .btn-grade:hover { background: var(--success); color: white; }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .modal-xl-custom { max-width: 90%; width: 1000px; }
        @media (min-width: 1200px) { .modal-xl-custom { max-width: 90%; width: 1200px; } }
        .modal-content-iframe { border-radius: 20px; overflow: hidden; }
        .modal-header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }
        .modal-header-gradient .btn-close { filter: brightness(0) invert(1); }
        .modal-body-iframe { padding: 0; max-height: 80vh; }
        .modal-body-iframe iframe { width: 100%; height: 75vh; border: none; }
        
        [data-aos] { opacity: 0; transition-property: opacity, transform; }
        [data-aos].aos-animate { opacity: 1; }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .btn-close-white {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>

<div class="admin-wrap">

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>StudyBuddyHub</h3>
            <p>College Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">MAIN</div>
            <a href="home.php"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

            <div class="menu-title">BRANCH MANAGEMENT</div>
            <a href="branches.php"> <i class="fas fa-code-branch"></i> <span>View Branches</span> </a>

            <div class="menu-title">STUDENT MANAGEMENT</div>
            <a href="pending_requests.php"> <i class="fas fa-clock"></i> <span>Pending Students</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_student.php"> <i class="fas fa-users"></i> <span>View Students</span> </a>
            <a href="promote_semester.php"> <i class="fas fa-arrow-up"></i> <span>Promote Semester</span> </a>

            <div class="menu-title">TEACHER MANAGEMENT</div>
            <a href="view_teacher.php"> <i class="fas fa-user-tie"></i> <span>View Teachers</span> </a>
            <a href="teacher_subjects.php"> <i class="fas fa-chalkboard"></i> <span>Teacher Subjects</span> </a>

            <div class="menu-title">ACADEMIC MANAGEMENT</div>
            <a href="subjects.php"> <i class="fas fa-book"></i> <span>View Subjects</span> </a>
            <a href="timetable.php"> <i class="fas fa-calendar-alt"></i> <span>Timetable</span> </a>
            <a href="assignments.php" class="active"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
            <a href="results.php"> <i class="fas fa-chart-bar"></i> <span>Results</span> </a>

            <div class="menu-title">NOTIFICATIONS</div>
            <a href="send_notification.php"> <i class="fas fa-bell"></i> <span>Send Notification</span> </a>
            <a href="notification_history.php"> <i class="fas fa-history"></i> <span>Notification History</span> </a>
        
            <div class="menu-title">ACCOUNT</div>
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> <span>My Profile</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- NEW MODERN TOPBAR -->
        <div class="modern-topbar">
            <div class="topbar-left">
                <button class="menu-toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <i class="fas fa-tasks"></i> Assignments
                </div>
            </div>
            <div class="topbar-right">
                <div class="quick-dropdown">
                    <button class="quick-add-btn" id="quickAddBtn">
                        <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down" style="font-size: 0.65rem;"></i>
                    </button>
                    <div class="quick-menu" id="quickMenu">
                        <div class="quick-menu-header">
                            <span><i class="fas fa-plus-circle"></i> Quick Actions</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_student.php" data-title="Add New Student" data-icon="fa-user-graduate">
                            <i class="fas fa-user-graduate"></i><span>Add Student</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_teacher.php" data-title="Add New Teacher" data-icon="fa-chalkboard-teacher">
                            <i class="fas fa-chalkboard-teacher"></i><span>Add Teacher</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_branch.php" data-title="Add New Branch" data-icon="fa-code-branch">
                            <i class="fas fa-code-branch"></i><span>Add Branch</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_subject.php" data-title="Add New Subject" data-icon="fa-book">
                            <i class="fas fa-book"></i><span>Add Subject</span>
                        </div>
                        <div class="quick-menu-divider"></div>
                        <div class="quick-menu-item" data-page="assign_subjects.php" data-title="Assign Subject to Teacher" data-icon="fa-random">
                            <i class="fas fa-random"></i><span>Assign Subject</span>
                        </div>
                        <div class="quick-menu-item" data-page="timetable.php" data-title="Create Timetable" data-icon="fa-calendar-plus">
                            <i class="fas fa-calendar-plus"></i><span>Create Timetable</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_notification.php" data-title="Send Notification" data-icon="fa-bell">
                            <i class="fas fa-bell"></i><span>Send Notification</span>
                        </div>
                    </div>
                </div>

                <a href="profile.php" class="admin-profile">
                    <div class="admin-avatar">
                        <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                            <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                        <div class="admin-role">Administrator</div>
                    </div>
                </a>

                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="page-header" data-aos="fade-up">
                <h2> <i class="fas fa-tasks" style="color: var(--primary);"></i> Assignments Oversight </h2>
                <p>View, manage, and grade all assignments across all branches</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- ASSIGNMENTS TABLE -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list"></i>
                        All Assignments
                    </h4>
                    <div class="total-assignments">
                        <i class="fas fa-tasks"></i> Total: <?php echo count($assignments); ?> Assignments
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if (!empty($assignments)): ?>
                        <table id="assignmentsTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Assignment</th>
                                    <th>Subject</th>
                                    <th>Branch/Sem</th>
                                    <th>Teacher</th>
                                    <th>Due Date</th>
                                    <th>Submissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                <tr id="assignment-row-<?php echo $assignment['id']; ?>">
                                    <td><span class="badge-subject">#<?php echo $assignment['id']; ?></span></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['title']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($assignment['description'], 0, 50)); ?>...</small>
                                     </td
                                    <td>
                                        <span class="badge-subject"><?php echo htmlspecialchars($assignment['subject_code']); ?></span><br>
                                        <small><?php echo htmlspecialchars($assignment['subject_name']); ?></small>
                                     </td
                                    <td>
                                        <span class="badge-subject"><?php echo htmlspecialchars($assignment['branch']); ?></span>
                                        <span class="badge-subject">Sem <?php echo $assignment['semester']; ?></span>
                                     </td
                                    <td><?php echo htmlspecialchars($assignment['teacher_name']); ?> </td
                                    <td>
                                        <div class="small">
                                            <strong>Due:</strong> <?php echo date('d M Y', strtotime($assignment['due_date'])); ?><br>
                                            <small class="text-muted">at <?php echo date('h:i A', strtotime($assignment['due_time'])); ?></small>
                                        </div>
                                     </td
                                    <td>
                                        <div>
                                            <span class="badge-submissions"><i class="fas fa-cloud-upload-alt"></i> <?php echo $assignment['total_submissions']; ?> submitted</span><br>
                                            <span class="badge-submissions"><i class="fas fa-check-circle"></i> <?php echo $assignment['graded_count']; ?> graded</span><br>
                                            <?php if ($assignment['late_count'] > 0): ?>
                                                <span class="badge-late"><i class="fas fa-clock"></i> <?php echo $assignment['late_count']; ?> late</span>
                                            <?php endif; ?>
                                        </div>
                                     </td
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-view" onclick="viewSubmissions(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')">
                                                <i class="fas fa-eye"></i> Submissions
                                            </button>
                                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($assignment); ?>)'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-delete" onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </div>
                                     </td
                                 </tr
                                <?php endforeach; ?>
                            </tbody>
                         </table
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h5>No Assignments Found</h5>
                            <p>No assignments have been created yet. Teachers can create assignments from their panel.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VIEW SUBMISSIONS MODAL -->
<div class="modal-custom" id="submissionsModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-users"></i> Submissions for: <span id="assignmentTitle"></span></h5>
            <button class="modal-close" onclick="closeSubmissionsModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="submissionsModalBody">
            <div class="text-center p-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading submissions...</p>
            </div>
        </div>
    </div>
</div>

<!-- EDIT ASSIGNMENT MODAL -->
<div class="modal-custom" id="editModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-edit"></i> Edit Assignment</h5>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editAssignmentForm">
            <input type="hidden" name="assignment_id" id="edit_assignment_id">
            <input type="hidden" name="update_assignment" value="1">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Title</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Due Date</label>
                            <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Due Time</label>
                            <input type="time" name="due_time" id="edit_due_time" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-star"></i> Total Marks</label>
                    <input type="number" name="total_marks" id="edit_total_marks" class="form-control" min="1" max="100" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- GRADE SUBMISSION MODAL -->
<div class="modal-custom" id="gradeModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-star"></i> Grade Submission</h5>
            <button class="modal-close" onclick="closeGradeModal()">&times;</button>
        </div>
        <form method="post" id="gradeForm">
            <input type="hidden" name="submission_id" id="grade_submission_id">
            <input type="hidden" name="grade_submission" value="1">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-chart-line"></i> Student</label>
                    <p id="grade_student_name" class="fw-bold mb-2"></p>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-star"></i> Marks</label>
                    <input type="number" name="marks" id="grade_marks" class="form-control" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Feedback</label>
                    <textarea name="feedback" id="grade_feedback" class="form-control" rows="3" placeholder="Enter feedback for the student..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeGradeModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Submit Grade</button>
            </div>
        </form>
    </div>
</div>

<!-- QUICK ADD MODAL (Bootstrap Modal) -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-iframe">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="quickAddModalLabel">
                    <i class="fas fa-plus-circle"></i> Loading...
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-iframe" id="quickAddModalBody">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading content...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    AOS.init({ duration: 600, once: true, offset: 20, disable: window.innerWidth < 768 });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('active');
        if (sidebar.classList.contains('active')) {
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.menu-toggle-btn');
            const overlay = document.getElementById('overlay');
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    });

    // Quick Add Dropdown
    const quickAddBtn = document.getElementById('quickAddBtn');
    const quickMenu = document.getElementById('quickMenu');
    
    function toggleQuickMenu(e) { 
        e.stopPropagation(); 
        if (quickMenu) quickMenu.classList.toggle('active'); 
    }
    
    function closeQuickMenu() { 
        if (quickMenu) quickMenu.classList.remove('active'); 
    }
    
    if (quickAddBtn) quickAddBtn.addEventListener('click', toggleQuickMenu);
    
    document.addEventListener('click', function(e) {
        if (quickMenu && !quickMenu.contains(e.target) && quickAddBtn && !quickAddBtn.contains(e.target)) {
            closeQuickMenu();
        }
    });

    // Function to open any page in Quick Add Modal
    function openInQuickModal(pageUrl, title, icon = 'fa-plus-circle') {
        const modalElement = document.getElementById('quickAddModal');
        if (!modalElement) return;
        const modal = new bootstrap.Modal(modalElement);
        const modalTitle = document.getElementById('quickAddModalLabel');
        const modalBody = document.getElementById('quickAddModalBody');
        
        if (modalTitle) {
            modalTitle.innerHTML = `<i class="fas ${icon}"></i> ${title}`;
        }
        if (modalBody) {
            modalBody.innerHTML = `<iframe src="${pageUrl}" style="width: 100%; height: 75vh; border: none;" title="${title}"></iframe>`;
        }
        modal.show();
    }

    // Open page in modal (alias)
    function openPageInModal(pageUrl, title, icon = 'fa-plus-circle') {
        openInQuickModal(pageUrl, title, icon);
    }

    document.querySelectorAll('.quick-menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const pageUrl = this.getAttribute('data-page');
            const title = this.getAttribute('data-title');
            const icon = this.getAttribute('data-icon') || 'fa-plus-circle';
            if (pageUrl) {
                openInQuickModal(pageUrl, title, icon);
                closeQuickMenu();
            }
        });
    });

    // DataTable
    <?php if (!empty($assignments)): ?>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#assignmentsTable')) {
            $('#assignmentsTable').DataTable().destroy();
        }
        
        var $table = $('#assignmentsTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 8) {
            $('#assignmentsTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, width: '60px' },
                    { targets: 1, width: '200px' },
                    { targets: 2, width: '150px' },
                    { targets: 3, width: '100px' },
                    { targets: 4, width: '120px' },
                    { targets: 5, width: '100px' },
                    { targets: 6, width: '120px' },
                    { targets: 7, width: '120px', orderable: false }
                ],
                language: { 
                    search: "Search:", 
                    lengthMenu: "Show _MENU_ entries", 
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    paginate: { 
                        first: "First", 
                        last: "Last", 
                        next: "Next", 
                        previous: "Prev" 
                    }
                }
            });
        } else {
            console.warn('Column count mismatch. Header:', headerCols, 'Body:', bodyCols);
            $('#assignmentsTable').addClass('table table-striped');
        }
    });
    <?php endif; ?>

    // View Submissions
    function viewSubmissions(assignmentId, assignmentTitle) {
        document.getElementById('assignmentTitle').innerHTML = assignmentTitle;
        document.getElementById('submissionsModalBody').innerHTML = `
            <div class="text-center p-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading submissions...</p>
            </div>
        `;
        document.getElementById('submissionsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        
        $.ajax({
            url: '../ajax/get_submissions.php',
            type: 'GET',
            data: { assignment_id: assignmentId },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.submissions.length > 0) {
                    let html = `
                        <div class="mb-3">
                            <div class="alert alert-info">
                                <strong>Total Submissions:</strong> ${data.submissions.length} students have submitted this assignment.
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="submissions-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Registration No</th>
                                        <th>Submitted On</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    data.submissions.forEach(sub => {
                        let statusBadge = '';
                        let statusText = '';
                        if (sub.status === 'graded') {
                            statusBadge = 'badge-submissions';
                            statusText = 'Graded';
                        } else if (sub.status === 'late') {
                            statusBadge = 'badge-late';
                            statusText = 'Late';
                        } else {
                            statusBadge = 'badge-subject';
                            statusText = 'Submitted';
                        }
                        
                        let marksDisplay = '';
                        if (sub.marks) {
                            let percentage = Math.round((sub.marks / sub.max_marks) * 100);
                            marksDisplay = `<div><strong>${sub.marks}/${sub.max_marks}</strong><br><small>${percentage}%</small></div>`;
                        } else {
                            marksDisplay = '<span class="text-muted">Not graded</span>';
                        }
                        
                        // Fix file path for download
                        let downloadUrl = sub.file_path;
                        if (downloadUrl && !downloadUrl.startsWith('http')) {
                            downloadUrl = '../' + downloadUrl;
                        }
                        
                        html += `<tr>
                            <td><strong>${escapeHtml(sub.student_name)}</strong><br><small>${escapeHtml(sub.registration_no)}</small></td>
                            <td>${sub.submission_date}<br><small>${sub.submission_time}</small></td>
                            <td><span class="${statusBadge}">${statusText}</span></td>
                            <td>${marksDisplay}</td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="${downloadUrl}" class="btn-download" download><i class="fas fa-download"></i> File</a>
                                    ${sub.status !== 'graded' ? `<button class="btn-grade" onclick="openGradeModal(${sub.id}, '${escapeHtml(sub.student_name)}', ${sub.max_marks})"><i class="fas fa-star"></i> Grade</button>` : ''}
                                </div>
                             </td
                         </tr`;
                    });
                    html += `</tbody>
                             </table
                        </div>`;
                    document.getElementById('submissionsModalBody').innerHTML = html;
                } else {
                    document.getElementById('submissionsModalBody').innerHTML = `<div class="text-center p-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h6>No submissions yet</h6><p class="text-muted">No students have submitted this assignment.</p></div>`;
                }
            },
            error: function(xhr, status, error) {
                console.log("AJAX Error:", error);
                document.getElementById('submissionsModalBody').innerHTML = `<div class="text-center p-5"><i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i><h6>Error loading submissions</h6><p class="text-muted">Please try again. Error: ${error}</p></div>`;
            }
        });
    }

    function closeSubmissionsModal() {
        document.getElementById('submissionsModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openEditModal(assignment) {
        document.getElementById('edit_assignment_id').value = assignment.id;
        document.getElementById('edit_title').value = assignment.title;
        document.getElementById('edit_description').value = assignment.description;
        document.getElementById('edit_due_date').value = assignment.due_date;
        document.getElementById('edit_due_time').value = assignment.due_time;
        document.getElementById('edit_total_marks').value = assignment.total_marks;
        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openGradeModal(submissionId, studentName, maxMarks) {
        document.getElementById('grade_submission_id').value = submissionId;
        document.getElementById('grade_student_name').innerHTML = studentName;
        document.getElementById('grade_marks').max = maxMarks;
        document.getElementById('grade_marks').value = '';
        document.getElementById('grade_feedback').value = '';
        document.getElementById('gradeModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeGradeModal() {
        document.getElementById('gradeModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function deleteAssignment(id, title) {
        Swal.fire({
            title: 'Delete Assignment?',
            html: `Are you sure you want to delete <strong>${escapeHtml(title)}</strong>?<br><br>This will also delete all student submissions.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `assignments.php?delete_id=${id}`;
            }
        });
    }

    // Close modals on outside click
    document.getElementById('submissionsModal').addEventListener('click', function(e) {
        if (e.target === this) closeSubmissionsModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('gradeModal').addEventListener('click', function(e) {
        if (e.target === this) closeGradeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSubmissionsModal();
            closeEditModal();
            closeGradeModal();
            closeQuickMenu();
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);
</script>

</body>
</html>