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

/* =========================
   APPROVE STUDENT (FINAL)
========================= */
if (isset($_GET['approve'])) {
    $id = mysqli_real_escape_string($data, $_GET['approve']);

    $req = mysqli_query($data, "SELECT * FROM admission_requests WHERE id=$id");

    if ($row = mysqli_fetch_assoc($req)) {

        $name     = mysqli_real_escape_string($data, $row['name']);
        $email    = mysqli_real_escape_string($data, $row['email']);
        $pass     = mysqli_real_escape_string($data, $row['password']);
        $reg_no   = mysqli_real_escape_string($data, $row['registration_no']);
        $branch   = mysqli_real_escape_string($data, $row['branch']);
        $semester = mysqli_real_escape_string($data, $row['semester']);

        $exists_user = mysqli_query($data, "SELECT id FROM user WHERE email='$email'");
        $exists_student = mysqli_query($data, "SELECT id FROM admission WHERE Email='$email' OR registration_no='$reg_no'");

        if (($exists_user && mysqli_num_rows($exists_user) > 0) || ($exists_student && mysqli_num_rows($exists_student) > 0)) {
            $message = "❌ Student already exists with this email or registration number";
            $message_type = "error";
        } else {
            mysqli_begin_transaction($data);

            $admission_sql = "INSERT INTO admission (Name, Email, password, registration_no, Branch, Semester)
                             VALUES ('$name', '$email', '$pass', '$reg_no', '$branch', '$semester')";
            
            $user_sql = "INSERT INTO user (username, email, password, usertype)
                        VALUES ('$name', '$email', '$pass', 'student')";

            $admission_ok = mysqli_query($data, $admission_sql);
            $admission_id = mysqli_insert_id($data);
            
            $user_ok = $admission_ok ? mysqli_query($data, $user_sql) : false;
            $user_id = mysqli_insert_id($data);
            
            $link_ok = $user_ok ? mysqli_query($data, "UPDATE admission SET user_id = $user_id WHERE id = $admission_id") : false;
            $delete_ok = $link_ok ? mysqli_query($data, "DELETE FROM admission_requests WHERE id=$id") : false;

            if ($admission_ok && $user_ok && $link_ok && $delete_ok) {
                mysqli_commit($data);
                $message = "✅ Student approved successfully and login enabled";
                $message_type = "success";
            } else {
                mysqli_rollback($data);
                $message = "❌ Error approving student: " . mysqli_error($data);
                $message_type = "error";
            }
        }
    }

    header("Location: pending_requests.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* =========================
   REJECT STUDENT
========================= */
if (isset($_GET['reject'])) {
    $id = mysqli_real_escape_string($data, $_GET['reject']);
    
    if (mysqli_query($data, "DELETE FROM admission_requests WHERE id=$id")) {
        $message = "✅ Request rejected successfully";
        $message_type = "success";
    } else {
        $message = "❌ Error rejecting request";
        $message_type = "error";
    }
    
    header("Location: pending_requests.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH PENDING REQUESTS */
$result = mysqli_query($data, "SELECT * FROM admission_requests ORDER BY id DESC");

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

$admin_name = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Requests | Admin Panel - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

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

        body {
            background: #f0f2f5;
            overflow-x: hidden;
        }

        .admin-wrap {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ===== SIDEBAR ===== */
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
        .sidebar-header p {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            margin-top: 0.25rem;
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

        /* ===== MAIN CONTENT ===== */
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

        /* ===== CONTENT AREA ===== */
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

        /* ===== ALERTS ===== */
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

        /* ===== TABLE CARD ===== */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .table-card:hover {
            box-shadow: var(--shadow-lg);
        }
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
        .total-count {
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
            min-width: 800px;
        }
        thead th {
            font-size: 0.75rem;
            padding: 0.75rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        tbody td {
            font-size: 0.8rem;
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }

        .status-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 500;
            background: rgba(255,193,7,0.1);
            color: #ffc107;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-approve {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: rgba(6,214,160,0.1);
            color: var(--success);
        }
        .btn-approve:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: rgba(239,71,111,0.1);
            color: var(--danger);
        }
        .btn-reject:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: rgba(67,97,238,0.1);
            color: var(--primary);
        }
        .btn-view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .branch-badge {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
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

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-top: 1.5rem;
            transition: var(--transition);
        }
        .info-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .info-card { padding: 1.25rem; } }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(67,97,238,0.1), rgba(58,12,163,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon i {
            font-size: 1.2rem;
            color: var(--primary);
        }

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
            max-width: 500px;
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
        .modal-header-custom h5 { 
            margin: 0; 
            font-weight: 600; 
            font-size: 1.1rem;
        }
        .modal-header-custom h5 i {
            margin-right: 0.5rem;
        }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: var(--transition);
        }
        .modal-close:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        .modal-body-custom { 
            padding: 1.5rem; 
        }
        .detail-row {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-size: 0.7rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        .detail-label i {
            margin-right: 0.3rem;
        }
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }

        .modal-content-iframe {
            border-radius: 20px;
            overflow: hidden;
        }
        .modal-header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }
        .modal-header-gradient .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-body-iframe {
            padding: 0;
            max-height: 80vh;
        }
        .modal-body-iframe iframe {
            width: 100%;
            height: 75vh;
            border: none;
        }
        
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        @media (min-width: 576px) {
            .modal-dialog {
                min-height: calc(100% - 3.5rem);
            }
        }

        [data-aos] {
            opacity: 0;
            transition-property: opacity, transform;
        }
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
            <a href="pending_requests.php" class="active"> <i class="fas fa-clock"></i> <span>Pending Students</span>
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
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
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
                    <i class="fas fa-clock"></i> Pending Students
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
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
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
                <h2> <i class="fas fa-clock" style="color: var(--warning);"></i> Pending Student Requests </h2>
                <p>Review and manage student registration requests before approving them.</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- TABLE CARD -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list"></i>
                        All Pending Registrations
                    </h4>
                    <div class="total-count">
                        <i class="fas fa-clock"></i> Total: <?php echo mysqli_num_rows($result); ?> Pending
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <table id="pendingTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Reg. No</th>
                                    <th>Branch</th>
                                    <th>Semester</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr id="request-row-<?php echo $row['id']; ?>">
                                    <td><span class="branch-badge">#<?php echo $row['id']; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['registration_no']); ?></td>
                                    <td><span class="branch-badge"><?php echo htmlspecialchars($row['branch']); ?></span></td>
                                    <td>Semester <?php echo $row['semester']; ?></td>
                                    <td><span class="status-badge"><i class="fas fa-clock"></i> Pending</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-approve" onclick="confirmApprove(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn-reject" onclick="confirmReject(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            <button class="btn-view" onclick='openViewModal(<?php echo json_encode($row); ?>)'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                     </td
                                 </tr
                                <?php endwhile; ?>
                            </tbody>
                         </table
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Pending Requests</h5>
                            <p>All student registration requests have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- INFO CARD -->
            <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h6 style="font-weight: 600; margin-bottom: 0.25rem;">Quick Guide</h6>
                        <p style="color: var(--gray); font-size: 0.75rem; margin: 0;">
                            <i class="fas fa-check-circle text-success me-1"></i> <strong>Approve:</strong> Student added to admission & user tables with login access<br>
                            <i class="fas fa-times-circle text-danger me-1"></i> <strong>Reject:</strong> Request deleted permanently<br>
                            <i class="fas fa-eye text-primary me-1"></i> <strong>View:</strong> See complete student details
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal-custom" id="viewModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-user-circle"></i> Student Details</h5>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="modalBodyContent">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

<!-- QUICK ADD MODAL -->
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeQuickMenu();
            closeViewModal();
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

    // DataTable initialization
    <?php if (mysqli_num_rows($result) > 0): ?>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#pendingTable')) {
            $('#pendingTable').DataTable().destroy();
        }
        
        var $table = $('#pendingTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 8) {
            $('#pendingTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, width: '60px' },
                    { targets: 1, width: '150px' },
                    { targets: 2, width: '180px' },
                    { targets: 3, width: '120px' },
                    { targets: 4, width: '100px' },
                    { targets: 5, width: '80px' },
                    { targets: 6, width: '80px' },
                    { targets: 7, width: '160px', orderable: false }
                ],
                language: { 
                    search: "Search:", 
                    lengthMenu: "Show _MENU_ entries", 
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: { first: "First", last: "Last", next: "Next", previous: "Prev" }
                }
            });
        } else {
            console.warn('Column count mismatch. Header:', headerCols, 'Body:', bodyCols);
            $('#pendingTable').addClass('table table-striped');
        }
    });
    <?php endif; ?>

    // Confirm Approve with SweetAlert
    function confirmApprove(id) {
        Swal.fire({
            title: 'Approve Student?',
            html: `This will:<br>
                   ✓ Add student to admission table<br>
                   ✓ Create login credentials<br>
                   ✓ Enable student access to the system`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#06d6a0',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Approve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `pending_requests.php?approve=${id}`;
            }
        });
    }

    // Confirm Reject with SweetAlert
    function confirmReject(id) {
        Swal.fire({
            title: 'Reject Request?',
            text: 'This will permanently delete the request. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Reject',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `pending_requests.php?reject=${id}`;
            }
        });
    }

    // View Modal Functions
    function openViewModal(student) {
        let semesterSuffix = '';
        if (student.semester == 1) semesterSuffix = 'st';
        else if (student.semester == 2) semesterSuffix = 'nd';
        else if (student.semester == 3) semesterSuffix = 'rd';
        else semesterSuffix = 'th';
        
        let modalContent = `
            <div class="text-center mb-3">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-graduate fa-2x" style="color: white;"></i>
                </div>
                <h5 style="font-weight: 700; color: var(--dark);">${escapeHtml(student.name)}</h5>
                <p style="color: var(--gray); font-size: 0.8rem;">${escapeHtml(student.email)}</p>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-id-card"></i> Registration Number</div>
                <div class="detail-value">${escapeHtml(student.registration_no)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-code-branch"></i> Branch</div>
                <div class="detail-value">${escapeHtml(student.branch)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-layer-group"></i> Semester</div>
                <div class="detail-value">${student.semester}${semesterSuffix}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-clock"></i> Status</div>
                <div class="detail-value"><span class="status-badge"><i class="fas fa-clock"></i> Pending</span></div>
            </div>
        `;
        
        document.getElementById('modalBodyContent').innerHTML = modalContent;
        document.getElementById('viewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modal on outside click
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
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

    // Auto-hide alert after 5 seconds
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