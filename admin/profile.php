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

$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "";

/* FETCH CURRENT ADMIN DETAILS */
$admin_query = mysqli_query($data, "SELECT * FROM user WHERE id = $user_id");
$admin = mysqli_fetch_assoc($admin_query);

if (!$admin) {
    die("User not found.");
}

$admin_name = $admin['username'];
$admin_email = $admin['email'];

/* HANDLE INFO UPDATE */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($data, trim($_POST['username']));
    $email = mysqli_real_escape_string($data, trim($_POST['email']));
    
    if (empty($username) || empty($email)) {
        $message = "❌ Username and Email are required!";
        $message_type = "error";
    } else {
        // Check email duplication
        $check_email = mysqli_query($data, "SELECT id FROM user WHERE email='$email' AND id != $user_id");
        if (mysqli_num_rows($check_email) > 0) {
            $message = "❌ Email is already in use by another user!";
            $message_type = "error";
        } else {
            $update = mysqli_query($data, "UPDATE user SET username='$username', email='$email' WHERE id=$user_id");
            if ($update) {
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $admin_name = $username;
                $admin_email = $email;
                $message = "✅ Profile updated successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Database error updating profile details.";
                $message_type = "error";
            }
        }
    }
}

/* HANDLE PASSWORD CHANGE */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_pass = mysqli_real_escape_string($data, $_POST['current_password']);
    $new_pass = mysqli_real_escape_string($data, $_POST['new_password']);
    $confirm_pass = mysqli_real_escape_string($data, $_POST['confirm_password']);
    
    $verified = false;
    if (!empty($admin['password_hash'])) {
        if (password_verify($current_pass, $admin['password_hash'])) {
            $verified = true;
        }
    } else {
        if ($admin['password'] === $current_pass) {
            $verified = true;
        }
    }
    
    if ($verified) {
        if ($new_pass === $confirm_pass) {
            if (strlen($new_pass) >= 6) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $update = mysqli_query($data, "UPDATE user SET password_hash='$new_hash', password='$new_pass' WHERE id=$user_id");
                if ($update) {
                    // Update admin array
                    $admin['password_hash'] = $new_hash;
                    $admin['password'] = $new_pass;
                    $message = "✅ Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "❌ Database error updating password.";
                    $message_type = "error";
                }
            } else {
                $message = "❌ New password must be at least 6 characters!";
                $message_type = "error";
            }
        } else {
            $message = "❌ New passwords do not match!";
            $message_type = "error";
        }
    } else {
        $message = "❌ Current password is incorrect!";
        $message_type = "error";
    }
}

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count_sidebar = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count_sidebar = (int)$row['total'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Profile | StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
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
        
        .sidebar-menu {
            padding: 0.5rem 0 1rem;
        }
        
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
        
        .sidebar a i {
            width: 22px;
            font-size: 1rem;
        }
        
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
        .content {
            padding: 1rem;
        }
        @media (min-width: 768px) { .content { padding: 1.5rem; } }

        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        @media (min-width: 768px) { .page-header h2 { font-size: 1.8rem; } }
        .page-header p {
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 0.25rem;
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

        .table-card h4 {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-card h4 i {
            color: var(--primary);
        }

        /* ===== PROFILE PIC STYLE ===== */
        .profile-header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            height: 100px;
            border-radius: 12px;
            position: relative;
        }

        .profile-avatar-container {
            position: relative;
            margin-top: -45px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .profile-avatar-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: 4px solid white;
            box-shadow: var(--shadow);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.2rem;
            overflow: hidden;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
            z-index: 10;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
            background: var(--primary-dark);
        }

        .profile-info-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }

        .profile-info-role {
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0 0;
        }

        .info-list-item {
            display: flex;
            justify-content: space-between;
            padding: 0.65rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
        }

        .info-list-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray);
            font-weight: 500;
        }

        .info-value {
            color: var(--dark);
            font-weight: 600;
        }

        /* ===== INPUT FORM STYLING ===== */
        .form-select, .form-control {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
            color: white;
        }

        /* Custom alert styles to look like system dashboard warnings */
        .alert-custom {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .alert-success {
            background: rgba(6,214,160,0.1);
            color: #06d6a0;
            border: 1px solid rgba(6,214,160,0.2);
        }
        .alert-error {
            background: rgba(239,71,111,0.1);
            color: #ef476f;
            border: 1px solid rgba(239,71,111,0.2);
        }

        /* Quick Add Modal */
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
        .modal.show .modal-dialog {
            transform: none;
        }

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
            <a href="profile.php" class="active"> <i class="fas fa-user-cog"></i> <span>My Profile</span> </a>
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
                    <i class="fas fa-user-cog"></i> My Profile
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
                <h2> <i class="fas fa-user-cog" style="color: var(--primary);"></i> Account Settings </h2>
                <p>Manage your login credentials, email address, and profile picture avatar settings.</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- PROFILE CARD -->
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="table-card text-center">
                        <div class="profile-header-gradient"></div>
                        <div class="profile-avatar-container">
                            <div class="profile-avatar-wrapper">
                                <div class="profile-avatar">
                                    <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-edit-btn" onclick="changeAvatar()" title="Change Profile Picture">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-info-name"><?php echo htmlspecialchars($admin_name); ?></div>
                        <div class="profile-info-role">Institution Administrator</div>
                        
                        <ul class="info-list text-start">
                            <li class="info-list-item">
                                <span class="info-label">Account ID</span>
                                <span class="info-value">#<?php echo $user_id; ?></span>
                            </li>
                            <li class="info-list-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($admin_email); ?></span>
                            </li>
                            <li class="info-list-item">
                                <span class="info-label">Role Type</span>
                                <span class="info-value">Global Admin</span>
                            </li>
                            <li class="info-list-item">
                                <span class="info-label">Register Date</span>
                                <span class="info-value"><?php echo !empty($admin['created_at']) ? date('Y-m-d', strtotime($admin['created_at'])) : date('Y-m-d'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- FORM CARDS -->
                <div class="col-lg-8" data-aos="fade-up" data-aos-delay="200">
                    <!-- PROFILE DETAILS FORM -->
                    <div class="table-card">
                        <h4><i class="fas fa-user-edit"></i> Edit Profile Information</h4>
                        <form method="POST" action="profile.php">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-500">Username</label>
                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin_name); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-500">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin_email); ?>" required>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_profile" class="btn-primary-custom">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- PASSWORD CHANGE FORM -->
                    <div class="table-card">
                        <h4><i class="fas fa-key"></i> Security & Password</h4>
                        <form method="POST" action="profile.php" id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label font-weight-500">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-500">New Password</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-500">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <div>
                                <button type="submit" name="change_password" class="btn-primary-custom">
                                    <i class="fas fa-shield-alt"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

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

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    AOS.init({
        duration: 600,
        easing: 'ease-in-out',
        once: true
    });

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

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.menu-toggle-btn');
            const overlay = document.getElementById('overlay');
            if (sidebar && !sidebar.contains(e.target) && toggleBtn && !toggleBtn.contains(e.target) && sidebar.classList.contains('active')) {
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
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.style.display = 'none';
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

    // Profile picture changing logic
    function changeAvatar() {
        const hasPic = <?php echo (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])) ? 'true' : 'false'; ?>;
        
        Swal.fire({
            title: 'Profile Picture',
            text: 'Choose an action to update your profile picture:',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: hasPic,
            confirmButtonText: '<i class="fas fa-upload"></i> Upload Photo',
            denyButtonText: '<i class="fas fa-trash-alt"></i> Remove Photo',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#4361ee',
            denyButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.onchange = function() {
                    if (fileInput.files.length === 0) return;
                    const file = fileInput.files[0];
                    if (file.size > 5 * 1024 * 1024) {
                        Swal.fire('Error', 'File size exceeds 5MB limit.', 'error');
                        return;
                    }
                    const formData = new FormData();
                    formData.append('profile_pic', file);
                    
                    Swal.fire({
                        title: 'Uploading...',
                        text: 'Please wait while we upload your picture.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('../ajax/update_profile_pic.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Upload failed.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Upload error:', err);
                        Swal.fire('Error', 'An error occurred during upload.', 'error');
                    });
                };
                fileInput.click();
            } else if (result.isDenied) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to remove your profile picture?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef476f',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, remove it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'delete');
                        
                        Swal.fire({
                            title: 'Removing...',
                            text: 'Please wait while we remove your profile picture.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        fetch('../ajax/update_profile_pic.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message || 'Deletion failed.', 'error');
                            }
                        })
                        .catch(err => {
                            console.error('Deletion error:', err);
                            Swal.fire('Error', 'An error occurred during profile picture removal.', 'error');
                        });
                    }
                });
            }
        });
    }

    // Password validation client-side
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'New passwords do not match!',
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'New password must be at least 6 characters!',
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
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