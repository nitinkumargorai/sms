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

/* DEFAULT VALUES */
$student_count = 0;
$teacher_count = 0;
$pending_count = 0;
$recent_students = [];
$recent_teachers = [];
$pending_students = [];

/* STUDENT COUNT */
$q1 = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission");
if ($q1 && $row = mysqli_fetch_assoc($q1)) {
    $student_count = (int)$row['total'];
}

/* TEACHER COUNT */
$q2 = mysqli_query($data, "SELECT COUNT(*) AS total FROM teacher");
if ($q2 && $row = mysqli_fetch_assoc($q2)) {
    $teacher_count = (int)$row['total'];
}

/* PENDING COUNT - FROM admission_requests TABLE */
$q3 = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($q3 && $row = mysqli_fetch_assoc($q3)) {
    $pending_count = (int)$row['total'];
}

/* PENDING STUDENTS DETAILS */
$pending_query = mysqli_query($data, "SELECT * FROM admission_requests WHERE status = 'pending' ORDER BY id DESC LIMIT 5");
if ($pending_query && mysqli_num_rows($pending_query) > 0) {
    while ($row = mysqli_fetch_assoc($pending_query)) {
        $pending_students[] = $row;
    }
}

/* RECENT STUDENTS */
$rs = mysqli_query($data, "SELECT id, Name, Email, registration_no, Branch, Semester FROM admission ORDER BY id DESC LIMIT 5");
if ($rs && mysqli_num_rows($rs) > 0) {
    while ($r = mysqli_fetch_assoc($rs)) {
        $recent_students[] = $r;
    }
}

/* RECENT TEACHERS */
$rt = mysqli_query($data, "SELECT id, name, email, branch FROM teacher ORDER BY id DESC LIMIT 5");
if ($rt && mysqli_num_rows($rt) > 0) {
    while ($r = mysqli_fetch_assoc($rt)) {
        $recent_teachers[] = $r;
    }
}

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count_sidebar = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count_sidebar = (int)$row['total'];
}

$admin_name = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard | StudyBuddyHub</title>

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

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.03);
        }
        @media (min-width: 768px) { .stat-card { padding: 1.25rem; } }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(67,97,238,0.1), rgba(58,12,163,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
        @media (min-width: 768px) {
            .stat-icon { width: 48px; height: 48px; }
        }
        .stat-icon i { font-size: 1.2rem; color: var(--primary); }
        .stat-content h4 {
            font-size: 0.7rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        @media (min-width: 768px) { .stat-number { font-size: 2rem; } }
        .stat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .stat-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
        }
        .stat-link:hover { gap: 0.5rem; color: var(--primary-dark); }
        .stat-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 500;
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }

        /* ===== TABLE CARD ===== */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
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
        .table-header h4 i { color: var(--primary); }
        
        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .view-all:hover { text-decoration: underline; }

        .pending-count {
            background: var(--warning);
            color: var(--dark);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.7rem;
        }

        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.7rem;
            border-bottom: 2px solid var(--border);
        }
        .data-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
            color: var(--dark);
        }
        .data-table tr:last-child td { border-bottom: none; }

        .status-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 500;
            background: rgba(255,193,7,0.1);
            color: #ffc107;
            display: inline-block;
        }

        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-approve, .btn-reject {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
        }
        .btn-approve {
            background: rgba(6,214,160,0.1);
            color: var(--success);
        }
        .btn-approve:hover { background: var(--success); color: white; }
        .btn-reject {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
        }
        .btn-reject:hover { background: var(--danger); color: white; }

        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray);
        }
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }

        /* Quick Actions Grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .quick-action-item {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            cursor: pointer;
        }
        .quick-action-item:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        .quick-action-item i {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .quick-action-item h6 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.2rem;
            font-size: 0.8rem;
        }
        .quick-action-item p {
            color: var(--gray);
            font-size: 0.65rem;
            margin: 0;
        }

        /* Modal styles */
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
            <a href="home.php" class="active"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

            <div class="menu-title">BRANCH MANAGEMENT</div>
            <a href="branches.php"> <i class="fas fa-code-branch"></i> <span>View Branches</span> </a>

            <div class="menu-title">STUDENT MANAGEMENT</div>
            <a href="pending_requests.php"> <i class="fas fa-clock"></i> <span>Pending Students</span>
                <?php if ($pending_count_sidebar > 0): ?>
                    <span class="badge-count"><?php echo $pending_count_sidebar; ?></span>
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
                    <i class="fas fa-chart-line"></i> Dashboard
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
                <h2> <i class="fas fa-chart-line" style="color: var(--primary);"></i> Dashboard Overview </h2>
                <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here's what's happening with your institution today.</p>
            </div>

            <!-- STATISTICS CARDS -->
            <div class="stats-grid">
                <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-content">
                        <h4>Total Students</h4>
                        <div class="stat-number"><?php echo $student_count; ?></div>
                        <div class="stat-footer">
                            <a href="view_student.php" class="stat-link">View All <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-content">
                        <h4>Total Teachers</h4>
                        <div class="stat-number"><?php echo $teacher_count; ?></div>
                        <div class="stat-footer">
                            <a href="view_teacher.php" class="stat-link">View All <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h4>Pending Approvals</h4>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-footer">
                            <a href="pending_requests.php" class="stat-link">Review <i class="fas fa-arrow-right"></i></a>
                            <?php if ($pending_count > 0): ?>
                                <span class="stat-badge">Action Required</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h4>System Status</h4>
                        <div class="stat-number">Active</div>
                        <div class="stat-footer">
                            <span class="text-success"><i class="fas fa-circle"></i> All Systems Operational</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PENDING STUDENTS SECTION -->
            <?php if (!empty($pending_students)): ?>
            <div class="table-card" data-aos="fade-up" data-aos-delay="500">
                <div class="table-header">
                    <h4><i class="fas fa-clock" style="color: var(--warning);"></i> Pending Registration Requests</h4>
                    <span class="pending-count"><?php echo $pending_count; ?> Request(s) Pending</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="pendingTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>Reg. No.</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_students as $pending): ?>
                            <tr id="pending-row-<?php echo $pending['id']; ?>">
                                <td><strong><?php echo htmlspecialchars($pending['name'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($pending['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pending['branch'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pending['registration_no'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge"><i class="fas fa-clock"></i> Pending</span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="pending_requests.php?approve=<?php echo $pending['id']; ?>" class="btn-approve" onclick="return confirmApprove()">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="pending_requests.php?reject=<?php echo $pending['id']; ?>" class="btn-reject" onclick="return confirmReject()">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    </div>
                                 </td
                             </tr
                            <?php endforeach; ?>
                        </tbody>
                     </table
                </div>
            </div>
            <?php endif; ?>

            <!-- RECENT STUDENTS & TEACHERS -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="600">
                <div class="table-header">
                    <h4><i class="fas fa-user-graduate" style="color: var(--primary);"></i> Recently Enrolled Students</h4>
                    <a href="view_student.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Registration No.</th>
                                <th>Branch</th>
                                <th>Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_students)): ?>
                                <?php foreach ($recent_students as $student): ?>
                                <tr id="student-row-<?php echo $student['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($student['Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['registration_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['Branch']); ?></td>
                                    <td>Semester <?php echo htmlspecialchars($student['Semester']); ?></td>
                                 </tr
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-state-row">
                                    <td colspan="5" class="empty-state text-center py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <p>No students found</p>
                                     </td
                                 </tr
                            <?php endif; ?>
                        </tbody>
                     </table
                </div>
            </div>

            <div class="table-card" data-aos="fade-up" data-aos-delay="700">
                <div class="table-header">
                    <h4><i class="fas fa-chalkboard-teacher" style="color: var(--primary);"></i> Recently Added Teachers</h4>
                    <a href="view_teacher.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="teachersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_teachers)): ?>
                                <?php foreach ($recent_teachers as $teacher): ?>
                                <tr id="teacher-row-<?php echo $teacher['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($teacher['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['branch']); ?></td>
                                    <td><a href="view_teacher.php" class="stat-link"><i class="fas fa-eye"></i> View</a></td>
                                 </tr
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-state-row">
                                    <td colspan="4" class="empty-state text-center py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <p>No teachers found</p>
                                     </td
                                 </tr
                            <?php endif; ?>
                        </tbody>
                     </table
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="800">
                <div class="table-header">
                    <h4><i class="fas fa-bolt" style="color: var(--primary);"></i> Quick Navigation</h4>
                </div>
                <div class="quick-actions-grid">
                    <div class="quick-action-item" data-page="add_student.php" data-title="Add New Student" data-icon="fa-user-plus">
                        <i class="fas fa-user-plus"></i>
                        <h6>Add Student</h6>
                        <p>Register new student</p>
                    </div>
                    <div class="quick-action-item" data-page="add_teacher.php" data-title="Add New Teacher" data-icon="fa-chalkboard-teacher">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h6>Add Teacher</h6>
                        <p>Register new teacher</p>
                    </div>
                    <div class="quick-action-item" data-page="add_branch.php" data-title="Add New Branch" data-icon="fa-code-branch">
                        <i class="fas fa-code-branch"></i>
                        <h6>Add Branch</h6>
                        <p>Create new branch</p>
                    </div>
                    <div class="quick-action-item" data-page="add_subject.php" data-title="Add New Subject" data-icon="fa-book">
                        <i class="fas fa-book"></i>
                        <h6>Add Subject</h6>
                        <p>Create new subject</p>
                    </div>
                    <div class="quick-action-item" data-page="add_notification.php" data-title="Send Notification" data-icon="fa-bell">
                        <i class="fas fa-bell"></i>
                        <h6>Notifications</h6>
                        <p>Send notification</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- MODAL POPUP FOR OPENING SEPARATE PHP FILES -->
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
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    // Close sidebar when clicking outside on mobile
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

    // Attach click handlers to quick menu items
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

    // Attach click handlers to quick action grid items
    document.querySelectorAll('.quick-action-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const pageUrl = this.getAttribute('data-page');
            const title = this.getAttribute('data-title');
            const icon = this.getAttribute('data-icon') || 'fa-plus-circle';
            if (pageUrl) {
                openPageInModal(pageUrl, title, icon);
            }
        });
    });

    // Confirm functions for approve/reject
    function confirmApprove() {
        return confirm('Approve this student?');
    }

    function confirmReject() {
        return confirm('Reject this request?');
    }

    // Initialize DataTables with proper column count checks
    $(document).ready(function() {
        // Students Table
        if ($('#studentsTable tbody tr').length > 0 && $('#studentsTable tbody tr:first').hasClass('empty-state-row') === false) {
            var firstRow = $('#studentsTable tbody tr:first');
            var colCount = firstRow.find('td').length;
            if (colCount === 5) {
                if ($.fn.DataTable.isDataTable('#studentsTable')) {
                    $('#studentsTable').DataTable().destroy();
                }
                $('#studentsTable').DataTable({ 
                    pageLength: 5, 
                    order: [[0, 'asc']], 
                    searching: true,
                    language: {
                        emptyTable: "No students found",
                        search: "Search:",
                        info: "Showing _START_ to _END_ of _TOTAL_ students",
                        infoEmpty: "Showing 0 to 0 of 0 students"
                    }
                });
            }
        }
        
        // Teachers Table
        if ($('#teachersTable tbody tr').length > 0 && $('#teachersTable tbody tr:first').hasClass('empty-state-row') === false) {
            var firstRow = $('#teachersTable tbody tr:first');
            var colCount = firstRow.find('td').length;
            if (colCount === 4) {
                if ($.fn.DataTable.isDataTable('#teachersTable')) {
                    $('#teachersTable').DataTable().destroy();
                }
                $('#teachersTable').DataTable({ 
                    pageLength: 5, 
                    order: [[0, 'asc']], 
                    searching: true,
                    language: {
                        emptyTable: "No teachers found",
                        search: "Search:",
                        info: "Showing _START_ to _END_ of _TOTAL_ teachers",
                        infoEmpty: "Showing 0 to 0 of 0 teachers"
                    }
                });
            }
        }
        
        // Pending Table (if exists)
        <?php if (!empty($pending_students)): ?>
        if ($('#pendingTable tbody tr').length > 0) {
            var firstRow = $('#pendingTable tbody tr:first');
            var colCount = firstRow.find('td').length;
            if (colCount === 6) {
                if ($.fn.DataTable.isDataTable('#pendingTable')) {
                    $('#pendingTable').DataTable().destroy();
                }
                $('#pendingTable').DataTable({ 
                    pageLength: 5, 
                    order: [[0, 'asc']], 
                    searching: true,
                    language: {
                        emptyTable: "No pending requests",
                        search: "Search:",
                        info: "Showing _START_ to _END_ of _TOTAL_ requests",
                        infoEmpty: "Showing 0 to 0 of 0 requests"
                    }
                });
            }
        }
        <?php endif; ?>
    });
</script>

</body>
</html>