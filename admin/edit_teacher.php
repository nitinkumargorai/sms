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
$info = null;

// Get teacher ID from URL
$id = isset($_GET['id']) ? mysqli_real_escape_string($data, $_GET['id']) : 0;

// Fetch teacher data
$sql = "SELECT * FROM teacher WHERE id='$id'";
$result = mysqli_query($data, $sql);
$info = mysqli_fetch_assoc($result);
$old_email = $info['email'] ?? '';

if (!$info) {
    header("Location: view_teacher.php?msg=" . urlencode("Teacher not found!") . "&type=error");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($data, trim($_POST['name']));
    $email = mysqli_real_escape_string($data, trim($_POST['email']));
    $mobile = mysqli_real_escape_string($data, trim($_POST['mobile']));
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($mobile)) {
        $errors[] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Mobile number must be 10 digits";
    }
    
    if (empty($branch)) {
        $errors[] = "Please select a branch";
    }
    
    // Check if email already exists for another teacher
    if (empty($errors)) {
        $check_email = "SELECT id FROM teacher WHERE email='$email' AND id != '$id'";
        $email_result = mysqli_query($data, $check_email);
        
        if (mysqli_num_rows($email_result) > 0) {
            $errors[] = "Email already exists for another teacher";
        }
    }

    if (empty($errors) && $email !== $old_email) {
        $user_email_check = mysqli_query($data, "SELECT id FROM user WHERE email='$email'");
        if ($user_email_check && mysqli_num_rows($user_email_check) > 0) {
            $errors[] = "Email already exists in login system";
        }
    }
    
    // Check if mobile already exists for another teacher
    if (empty($errors)) {
        $check_mobile = "SELECT id FROM teacher WHERE mobile='$mobile' AND id != '$id'";
        $mobile_result = mysqli_query($data, $check_mobile);
        
        if (mysqli_num_rows($mobile_result) > 0) {
            $errors[] = "Mobile number already exists for another teacher";
        }
    }
    
    // Update if no errors
    if (empty($errors)) {
        mysqli_begin_transaction($data);

        $update_query = "UPDATE teacher SET 
                         name='$name', 
                         email='$email', 
                         mobile='$mobile', 
                         branch='$branch' 
                         WHERE id='$id'";

        $teacher_ok = mysqli_query($data, $update_query);

        if ($teacher_ok) {
            $user_exists_query = mysqli_query($data, "SELECT id FROM user WHERE email='$old_email' AND usertype='teacher'");
            if ($user_exists_query && mysqli_num_rows($user_exists_query) > 0) {
                $user_ok = mysqli_query($data, "UPDATE user SET username='$name', email='$email' WHERE email='$old_email' AND usertype='teacher'");
            } else {
                $user_ok = mysqli_query($data, "INSERT INTO user (username, email, password, usertype) VALUES ('$name', '$email', '1234', 'teacher')");
            }
        } else {
            $user_ok = false;
        }

        if ($teacher_ok && $user_ok) {
            mysqli_commit($data);
            $message = "✅ Teacher updated successfully!";
            $message_type = "success";
            
            // Refresh teacher data
            $result = mysqli_query($data, "SELECT * FROM teacher WHERE id='$id'");
            $info = mysqli_fetch_assoc($result);
            $old_email = $info['email'] ?? $email;
        } else {
            mysqli_rollback($data);
            $message = "❌ Error updating teacher: " . mysqli_error($data);
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests");
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
    <title>Edit Teacher | Admin Panel - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --primary-light: #eef2ff;
            --secondary: #64748b;
            --dark: #1e1e2f;
            --light: #f8fafc;
            --gray: #94a3b8;
            --gray-light: #e2e8f0;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef476f;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
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
            width: 280px;
            background: linear-gradient(135deg, #1e1e2f, #2a2a40);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            left: -100%;
            transition: left 0.3s ease;
        }

        .sidebar.active {
            left: 0;
        }

        @media (min-width: 769px) {
            .sidebar {
                left: 0;
            }
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            font-size: 0.9rem;
        }

        .sidebar a i {
            width: 20px;
            font-size: 1rem;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }

        .sidebar a.active {
            background: linear-gradient(90deg, rgba(67,97,238,0.2), transparent);
            color: white;
            border-left-color: var(--primary);
            font-weight: 500;
        }

        .badge-count {
            background: var(--danger);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-left: auto;
        }

        /* ===== MAIN CONTENT ===== */
        .main {
            flex: 1;
            width: 100%;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 769px) {
            .main {
                margin-left: 280px;
            }
        }

        /* ===== NEW MODERN TOPBAR STYLES ===== */
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

        @media (min-width: 768px) {
            .content {
                padding: 1.5rem;
            }
        }

        /* ===== EDIT CARD ===== */
        .edit-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            max-width: 800px;
            margin: 0 auto;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .card-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .card-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary);
        }

        .input-group {
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            transition: var(--transition);
            background: white;
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: var(--gray);
            padding-left: 1rem;
        }

        .form-control {
            border: none;
            padding: 0.75rem 1rem 0.75rem 0;
            font-size: 1rem;
            background: transparent;
        }

        .form-control:focus {
            box-shadow: none;
            outline: none;
        }

        .form-select {
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        /* Button */
        .btn-update {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .btn-update::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-update:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            filter: brightness(1.05);
        }

        .btn-cancel {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }

        /* Alert */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
        }

        .alert-success {
            background: rgba(6,214,160,0.1);
            color: #06d6a0;
            border-left: 4px solid #06d6a0;
        }

        .alert-error {
            background: rgba(239,71,111,0.1);
            color: #ef476f;
            border-left: 4px solid #ef476f;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .card-header {
                padding: 1.5rem;
            }
            
            .card-header h2 {
                font-size: 1.5rem;
            }
            
            .card-header i {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrap">

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>StudyBuddyHub</h3>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">MAIN</div>
            <a href="home.php" class="<?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>

            <div class="menu-title">STUDENTS</div>
            <a href="add_student.php" class="<?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> <span>Add Student</span>
            </a>
            <a href="pending_requests.php" class="<?php echo ($current_page == 'pending_requests.php') ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>Pending Students</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_student.php" class="<?php echo ($current_page == 'view_student.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> <span>View Students</span>
            </a>

            <div class="menu-title">TEACHERS</div>
            <a href="add_teacher.php" class="<?php echo ($current_page == 'add_teacher.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> <span>Add Teacher</span>
            </a>
            <a href="view_teacher.php" class="<?php echo ($current_page == 'view_teacher.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> <span>View Teachers</span>
            </a>
        
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
                    <i class="fas fa-user-edit"></i> Edit Teacher
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
            
            <!-- EDIT CARD -->
            <div class="edit-card" data-aos="fade-up">
                <div class="card-header">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h2>Edit Teacher Information</h2>
                    <p>Update the teacher's details below</p>
                </div>
                
                <div class="card-body">
                    <?php if ($message != ""): ?>
                        <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                            <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <div><?php echo $message; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" id="editForm">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       name="name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($info['name']); ?>"
                                       placeholder="Enter teacher's full name"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($info['email']); ?>"
                                       placeholder="Enter teacher's email"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-phone-alt"></i> Mobile Number
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone-alt"></i>
                                </span>
                                <input type="tel" 
                                       name="mobile" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($info['mobile']); ?>"
                                       placeholder="Enter 10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       title="Please enter a valid 10-digit mobile number"
                                       required>
                            </div>
                            <small class="text-muted">Enter 10-digit mobile number (numbers only)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-code-branch"></i> Branch
                            </label>
                            <select name="branch" class="form-select" required>
                                <option value="">Select Branch</option>
                                <option value="CSE" <?php echo ($info['branch'] == 'CSE') ? 'selected' : ''; ?>>Computer Science Engineering (CSE)</option>
                                <option value="EEE" <?php echo ($info['branch'] == 'EEE') ? 'selected' : ''; ?>>Electrical & Electronics Engineering (EEE)</option>
                                <option value="ECE" <?php echo ($info['branch'] == 'ECE') ? 'selected' : ''; ?>>Electronics & Communication (ECE)</option>
                                <option value="EE" <?php echo ($info['branch'] == 'EE') ? 'selected' : ''; ?>>Electrical Engineering (EE)</option>
                                <option value="ME" <?php echo ($info['branch'] == 'ME') ? 'selected' : ''; ?>>Mechanical Engineering (ME)</option>
                                <option value="CE" <?php echo ($info['branch'] == 'CE') ? 'selected' : ''; ?>>Civil Engineering (CE)</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn-update" id="updateBtn">
                                <i class="fas fa-save"></i>
                                <span id="btnText">Update Teacher</span>
                                <i class="fas fa-arrow-right"></i>
                                <span class="spinner" id="spinner" style="display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite;"></span>
                            </button>
                            
                            <a href="view_teacher.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overlay for mobile sidebar -->


<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 600,
        once: true,
        offset: 20
    });

    // Toggle sidebar on mobile
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
            const toggleBtn = document.querySelector('.menu-toggle');
            const overlay = document.getElementById('overlay');
            
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    });

    // Mobile number validation - only numbers, max 10 digits
    const mobileInput = document.querySelector('input[name="mobile"]');
    if (mobileInput) {
        mobileInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }

    // Form submission loading state
    document.getElementById('editForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('updateBtn');
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        const arrow = btn.querySelector('.fa-arrow-right');
        
        btnText.style.opacity = '0.7';
        if (arrow) arrow.style.opacity = '0';
        spinner.style.display = 'inline-block';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.8';
    });

    // Auto-hide alert after 5 seconds
    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);

    // Quick Add Dropdown toggling & backdrop dismissal

    // Standardized Quick Add & Modal Helpers (Self-contained scope)
    (function() {
        
    })();

    if (typeof window.openInQuickModal === 'undefined') {
        window.openInQuickModal = function(pageUrl, title, icon = 'fa-plus-circle') {
            const modalElement = document.getElementById('quickAddModal');
            if (!modalElement) return;
            const modal = new bootstrap.Modal(modalElement);
            const modalTitle = document.getElementById('quickAddModalLabel');
            const modalBody = document.getElementById('quickAddModalBody');
            
            if (modalTitle) modalTitle.innerHTML = `<i class="fas ${icon}
    }

    if (typeof window.openPageInModal === 'undefined') {
        window.openPageInModal = function(pageUrl, title, icon = 'fa-plus-circle') {
            window.openInQuickModal(pageUrl, title, icon);
        }
    }
</script>
<!-- QUICK ADD MODAL -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-iframe">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="quickAddModalLabel">
                    <i class="fas fa-plus-circle"></i> Loading...
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
</body>
</html>
