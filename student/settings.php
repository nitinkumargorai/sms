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
    die("Database connection failed: " . mysqli_connect_error());
}

/* GET STUDENT DETAILS */
$student_name = $_SESSION['username'];
$student_email = $_SESSION['email'] ?? '';
$student_id = $_SESSION['student_id'] ?? 0;
$student_branch = '';
$student_semester = 1;
$student_mobile = '';
$student_address = '';
$student_dob = '';
$student_gender = '';

// Fetch student details from database
if (!empty($student_email)) {
    $query = mysqli_query($data, "SELECT * FROM admission WHERE Email='$student_email'");
    if ($query && $row = mysqli_fetch_assoc($query)) {
        $student_id = $row['id'];
        $student_name = $row['Name'];
        $student_email = $row['Email'];
        $student_branch = $row['Branch'] ?? '';
        $student_semester = $row['Semester'] ?? 1;
        $student_mobile = $row['mobile'] ?? '';
        $student_address = $row['address'] ?? '';
        $student_dob = $row['dob'] ?? '';
        $student_gender = $row['gender'] ?? '';
        
        // Update session
        $_SESSION['student_id'] = $student_id;
        $_SESSION['student_branch'] = $student_branch;
        $_SESSION['student_semester'] = $student_semester;
    }
}

// Get pending notifications count
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = '" . $_SESSION['user_id'] . "' AND is_read = 0");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}
$notifications_list = [];
$notif_query = mysqli_query($data, "SELECT * FROM notifications WHERE user_id = '" . $_SESSION['user_id'] . "' AND is_read = 0 ORDER BY id DESC LIMIT 5");
if ($notif_query) {
    while ($notif_row = mysqli_fetch_assoc($notif_query)) {
        $notifications_list[] = $notif_row;
    }
}

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* HANDLE SETTINGS UPDATE */
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = mysqli_real_escape_string($data, $_POST['name']);
        $mobile = mysqli_real_escape_string($data, $_POST['mobile']);
        $address = mysqli_real_escape_string($data, $_POST['address']);
        $dob = mysqli_real_escape_string($data, $_POST['dob']);
        $gender = mysqli_real_escape_string($data, $_POST['gender']);
        
        $update_query = "UPDATE admission SET 
                         Name='$name',
                         mobile='$mobile',
                         address='$address',
                         dob='$dob',
                         gender='$gender'
                         WHERE Email='$student_email'";
        
        if (mysqli_query($data, $update_query)) {
            mysqli_query($data, "UPDATE user SET username='$name' WHERE email='$student_email'");
            $_SESSION['username'] = $name;
            $student_name = $name;
            $message = "✅ Profile updated successfully!";
            $message_type = "success";
        } else {
            $message = "❌ Error updating profile: " . mysqli_error($data);
            $message_type = "error";
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        // Change password
        $current_pass = mysqli_real_escape_string($data, $_POST['current_password']);
        $new_pass = mysqli_real_escape_string($data, $_POST['new_password']);
        $confirm_pass = mysqli_real_escape_string($data, $_POST['confirm_password']);
        
        $check_query = mysqli_query($data, "SELECT password FROM user WHERE email='$student_email'");
        if ($check_query && $row = mysqli_fetch_assoc($check_query)) {
            if ($row['password'] == $current_pass) {
                if ($new_pass == $confirm_pass) {
                    if (strlen($new_pass) >= 6) {
                        $update_user = mysqli_query($data, "UPDATE user SET password='$new_pass' WHERE email='$student_email'");
                        if ($update_user) {
                            $message = "✅ Password changed successfully!";
                            $message_type = "success";
                        } else {
                            $message = "❌ Error changing password";
                            $message_type = "error";
                        }
                    } else {
                        $message = "❌ Password must be at least 6 characters!";
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
    }
    
    elseif (isset($_POST['update_preferences'])) {
        // Update notification preferences
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $assignment_alerts = isset($_POST['assignment_alerts']) ? 1 : 0;
        $material_updates = isset($_POST['material_updates']) ? 1 : 0;
        $attendance_reminders = isset($_POST['attendance_reminders']) ? 1 : 0;
        
        $save_settings = false;
        if ($student_id > 0) {
            $settings_exists = mysqli_query($data, "SELECT id FROM student_settings WHERE student_id='$student_id'");
            if ($settings_exists && mysqli_num_rows($settings_exists) > 0) {
                $save_settings = mysqli_query($data, "UPDATE student_settings
                    SET email_notifications='$email_notifications',
                        assignment_alerts='$assignment_alerts',
                        material_updates='$material_updates',
                        attendance_reminders='$attendance_reminders'
                    WHERE student_id='$student_id'");
            } else {
                $save_settings = mysqli_query($data, "INSERT INTO student_settings
                    (student_id, email_notifications, assignment_alerts, material_updates, attendance_reminders)
                    VALUES ('$student_id', '$email_notifications', '$assignment_alerts', '$material_updates', '$attendance_reminders')");
            }
        }
        $message = "✅ Preferences updated successfully!";
        $message_type = $save_settings ? "success" : "error";
        if (!$save_settings) {
            $message = "Failed to save preferences.";
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Settings | Student - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
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
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1e293b;
            --gray: #6b7280;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 280px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: #f1f5f9;
            overflow-x: hidden;
        }

        .student-wrap {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
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
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            border-left-color: var(--primary-light);
        }
        .sidebar a.active {
            background: linear-gradient(90deg, rgba(79,70,229,0.15), transparent);
            color: white;
            border-left-color: var(--primary);
            font-weight: 500;
        }

        /* ===== MAIN CONTENT ===== */
        .main {
            flex: 1;
            width: 100%;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }
        @media (min-width: 769px) { .main { margin-left: var(--sidebar-width); } }

        /* ===== TOPBAR ===== */
        .topbar {
            background: white;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 99;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--dark);
            cursor:指针;
            padding: 0.5rem;
            display: block;
            border-radius: 8px;
            transition: var(--transition);
        }
        .menu-toggle:hover { background: var(--light); }
        @media (min-width: 769px) { .menu-toggle { display: none; } }

        .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-left: 0.5rem;
        }

        /* Top Right Profile */
        .student-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.8rem;
            background: var(--light);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
        }
        .student-profile:hover { background: #e2e8f0; }
        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .student-info { display: none; }
        @media (min-width: 576px) {
            .student-info { display: block; }
            .student-name { font-weight: 600; font-size: 0.9rem; color: var(--dark); }
            .student-role { font-size: 0.7rem; color: var(--primary); }
        }
        
        .logout-btn {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .logout-btn:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }
        .topbar-actions { display: flex; align-items: center; gap: 0.75rem; }
        
        .notification-badge {
            position: relative;
            background: var(--light);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .notification-badge .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 50px;
        }

        /* ===== CONTENT AREA ===== */
        .content { padding: 1.5rem; }
        @media (max-width: 768px) { .content { padding: 1rem; } }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -30%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 6s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.05); opacity: 0.6; }
        }
        
        .welcome-content { position: relative; z-index: 1; }
        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 768px) { .welcome-banner h2 { font-size: 1.3rem; } }
        .welcome-banner p {
            color: rgba(255,255,255,0.9);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        .welcome-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .welcome-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .welcome-stat i { font-size: 1rem; }
        .welcome-stat span { font-size: 0.85rem; font-weight: 500; }

        /* Alert */
        .alert-custom {
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
        }
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        .alert-error {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        .card-header i {
            font-size: 1.2rem;
            color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(79,70,229,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .full-width { grid-column: span 2; }
        @media (max-width: 768px) {
            .full-width { grid-column: span 1; }
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
        }
        .form-label i {
            color: var(--primary);
            margin-right: 0.3rem;
        }
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-size: 0.85rem;
            transition: var(--transition);
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        
        /* Toggle Switch */
        .toggle-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        @media (max-width: 768px) {
            .toggle-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        
        .toggle-info h6 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.2rem;
            font-size: 0.9rem;
        }
        .toggle-info p {
            color: var(--gray);
            font-size: 0.75rem;
            margin: 0;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: var(--border);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
        }
        .toggle-switch.active {
            background: var(--primary);
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .toggle-switch.active::after {
            left: 33px;
        }
        .toggle-input {
            display: none;
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 2px solid rgba(239,68,68,0.2);
            background: rgba(239,68,68,0.02);
        }
        .danger-zone .card-header i {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }
        
        .btn-danger-custom {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.3);
            padding: 0.7rem 1.2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-danger-custom:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
        }
        
        /* Profile Dropdown */
        .profile-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 1rem;
            min-width: 280px;
            z-index: 1000;
            border: 1px solid var(--border);
            animation: fadeInDown 0.3s ease;
            display: none;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-dropdown-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .profile-dropdown-avatar {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .profile-dropdown-info h4 { font-size: 1rem; font-weight: 700; color: var(--dark); margin-bottom: 0.25rem; }
        .profile-dropdown-info p { font-size: 0.75rem; color: var(--gray); margin: 0; }
        .profile-dropdown-menu { padding: 0.5rem 0; }
        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: var(--dark);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        .profile-dropdown-menu a:hover { background: rgba(79,70,229,0.05); }
        .profile-dropdown-menu a i { width: 22px; color: var(--primary); font-size: 1rem; }
        .profile-dropdown-menu hr { margin: 0.5rem 0; border-color: var(--border); }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .btn-save, .btn-danger-custom { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>

<div class="student-wrap">

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📚 StudyBuddyHub</h3>
            <p>College Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">MAIN</div>
            <a href="home.php"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

            <div class="menu-title">ACADEMICS</div>
            <a href="subjects.php"> <i class="fas fa-book"></i> <span>My Subjects</span> </a>
            <a href="materials.php"> <i class="fas fa-file-pdf"></i> <span>Study Materials</span> </a>
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>

            <div class="menu-title">PROGRESS</div>
            <a href="attendance.php"> <i class="fas fa-calendar-check"></i> <span>Attendance</span> </a>
            <a href="results.php"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>

            <div class="menu-title">RESOURCES</div>
            <a href="timetable.php"> <i class="fas fa-clock"></i> <span>Time Table</span> </a>
            <a href="syllabus.php"> <i class="fas fa-list-alt"></i> <span>Syllabus</span> </a>
            
            <div class="menu-title">PROFILE</div>
            <a href="profile.php"> <i class="fas fa-user"></i> <span>My Profile</span> </a>
            <a href="settings.php" class="active"> <i class="fas fa-cog"></i> <span>Settings</span> </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="menu-toggle" onclick="toggleSidebar()"> <i class="fas fa-bars"></i> </button>
                <span class="page-title"><i class="fas fa-cog me-2" style="color: var(--primary);"></i>Settings</span>
            </div>
            <div class="topbar-actions">
                <div class="notification-badge" onclick="viewNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($pending_count > 0): ?>
                        <span class="badge-count"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </div>

                <div class="student-profile" onclick="toggleProfileMenu()">
                    <div class="student-avatar" style="overflow: hidden;">
                        <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                            <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($student_name ?? $_SESSION['username'] ?? 'S', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="student-info">
                        <div class="student-name"><?php echo htmlspecialchars($student_name); ?></div>
                        <div class="student-role">Student</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #666;"></i>
                </div>
                <a href="../logout.php" class="logout-btn"> <i class="fas fa-sign-out-alt"></i> <span>Logout</span> </a>
            </div>
        </div>

        <div class="content">
            <!-- Welcome Banner -->
            <div class="welcome-banner" data-aos="fade-up">
                <div class="welcome-content">
                    <h2>⚙️ Account Settings</h2>
                    <p>Manage your profile, security, and notification preferences.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure Account</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-bell"></i>
                            <span>Manage Notifications</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Profile Settings Card -->
                <div class="settings-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h4>Profile Information</h4>
                    </div>

                    <form method="post">
                        <div class="form-grid">
                            <div class="full-width">
                                <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student_name); ?>" required>
                            </div>

                            <div class="full-width">
                                <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($student_email); ?>" readonly>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>

                            <div>
                                <label class="form-label"><i class="fas fa-phone"></i> Mobile Number</label>
                                <input type="tel" name="mobile" class="form-control" value="<?php echo htmlspecialchars($student_mobile); ?>" maxlength="10" pattern="[0-9]{10}">
                            </div>

                            <div>
                                <label class="form-label"><i class="fas fa-venus-mars"></i> Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($student_gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($student_gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($student_gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label"><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="<?php echo $student_dob; ?>">
                            </div>

                            <div class="full-width">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student_address); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Settings Card -->
                <div class="settings-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="card-header">
                        <i class="fas fa-lock"></i>
                        <h4>Security & Password</h4>
                    </div>

                    <form method="post">
                        <div class="form-grid">
                            <div class="full-width">
                                <label class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div>
                                <label class="form-label"><i class="fas fa-key"></i> New Password</label>
                                <input type="password" name="new_password" class="form-control" id="new_password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>

                            <div>
                                <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="change_password" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notification Preferences Card -->
                <div class="settings-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>
                        <h4>Notification Preferences</h4>
                    </div>

                    <form method="post">
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h6>Email Notifications</h6>
                                <p>Receive updates via email</p>
                            </div>
                            <label class="toggle-switch active">
                                <input type="checkbox" name="email_notifications" class="toggle-input" checked>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h6>Assignment Alerts</h6>
                                <p>Get notified about new assignments</p>
                            </div>
                            <label class="toggle-switch active">
                                <input type="checkbox" name="assignment_alerts" class="toggle-input" checked>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h6>Study Material Updates</h6>
                                <p>When new materials are uploaded</p>
                            </div>
                            <label class="toggle-switch active">
                                <input type="checkbox" name="material_updates" class="toggle-input" checked>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h6>Attendance Reminders</h6>
                                <p>Daily attendance notifications</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="attendance_reminders" class="toggle-input">
                            </label>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="update_preferences" class="btn-save">
                                <i class="fas fa-bell"></i> Update Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Appearance Settings Card -->
                <div class="settings-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="card-header">
                        <i class="fas fa-paint-brush"></i>
                        <h4>Appearance</h4>
                    </div>

                    <div class="form-grid">
                        <div class="full-width">
                            <label class="form-label"><i class="fas fa-moon"></i> Theme Mode</label>
                            <select class="form-select">
                                <option>Light Mode</option>
                                <option>Dark Mode</option>
                                <option>System Default</option>
                            </select>
                        </div>

                        <div class="full-width">
                            <label class="form-label"><i class="fas fa-language"></i> Language</label>
                            <select class="form-select">
                                <option>English (US)</option>
                                <option>Hindi (हिंदी)</option>
                                <option>Bengali (বাংলা)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button class="btn-save">
                            <i class="fas fa-save"></i> Save Appearance
                        </button>
                    </div>
                </div>

                <!-- Danger Zone Card -->
                <div class="settings-card danger-zone" data-aos="fade-up" data-aos-delay="300">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Danger Zone</h4>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn-danger-custom" onclick="deactivateAccount()">
                            <i class="fas fa-user-slash"></i> Deactivate Account
                        </button>
                        <button class="btn-danger-custom" onclick="deleteAccount()">
                            <i class="fas fa-trash-alt"></i> Delete Account
                        </button>
                    </div>
                    <p class="text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        These actions are irreversible. Please be certain.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Dropdown Menu -->
<div class="profile-dropdown" id="profileDropdown">
    <div class="profile-dropdown-header">
        <div class="profile-dropdown-avatar" style="overflow: hidden;">
            <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <?php echo strtoupper(substr($student_name ?? $_SESSION['username'] ?? 'S', 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="profile-dropdown-info">
            <h4><?php echo htmlspecialchars($student_name); ?></h4>
            <p><?php echo htmlspecialchars($student_email); ?></p>
            <?php if (!empty($student_branch)): ?>
                <p class="mt-1"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($student_branch); ?> - Sem <?php echo $student_semester; ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="profile-dropdown-menu">
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="subjects.php"><i class="fas fa-book"></i> My Subjects</a>
        <a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a>
        <a href="results.php"><i class="fas fa-chart-line"></i> Results</a>
        <hr>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
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

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    });

    function toggleProfileMenu() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('profileDropdown');
        const profile = document.querySelector('.student-profile');
        if (profile && !profile.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function(e) { 
        if (e.key === 'Escape') {
            document.getElementById('profileDropdown').style.display = 'none';
        }
    });

    function viewNotifications() {
        <?php if (empty($notifications_list)): ?>
            Swal.fire({
                title: 'Notifications',
                text: 'You have no new notifications.',
                icon: 'info',
                confirmButtonColor: '#4f46e5'
            });
        <?php else: ?>
            let htmlContent = '<div class="text-start" style="max-height: 300px; overflow-y: auto;">';
            <?php foreach ($notifications_list as $notif): ?>
                htmlContent += `
                    <div style="padding: 12px; border-bottom: 1px solid #e2e8f0; margin-bottom: 5px;">
                        <strong style="color: #4f46e5; font-size: 0.95rem; display: block; margin-bottom: 4px;">
                            ${escapeHtml(<?php echo json_encode($notif['title']); ?>)}
                        </strong>
                        <p style="font-size: 0.85rem; margin: 0 0 6px 0; color: #334155; line-height: 1.4;">
                            ${escapeHtml(<?php echo json_encode($notif['message']); ?>)}
                        </p>
                        ${<?php echo json_encode($notif['link']); ?> ? `<a href="${escapeHtml(<?php echo json_encode($notif['link']); ?>)}" target="_blank" style="font-size: 0.8rem; color: #6366f1; text-decoration: underline; font-weight: 500;"><i class="fas fa-external-link-alt"></i> View Link</a>` : ''}
                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 6px;">
                            <i class="far fa-clock"></i> ${new Date(<?php echo json_encode($notif['created_at']); ?>).toLocaleString()}
                        </div>
                    </div>
                `;
            <?php endforeach; ?>
            htmlContent += '</div>';

            Swal.fire({
                title: 'Unread Notifications',
                html: htmlContent,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Mark as Read & Close'
            }).then((result) => {
                fetch('../ajax/mark_notifications_read.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        }
                    });
            });
        <?php endif; ?>
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
    }

    // Toggle switch functionality
    document.querySelectorAll('.toggle-switch').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;
            const input = this.querySelector('.toggle-input');
            input.checked = !input.checked;
            this.classList.toggle('active');
        });
    });

    // Password validation
    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    
    if (confirmPass) {
        confirmPass.addEventListener('keyup', function() {
            if (newPass.value === this.value && newPass.value.length >= 6) {
                this.style.borderColor = '#10b981';
            } else {
                this.style.borderColor = '#ef4444';
            }
        });
        newPass.addEventListener('keyup', function() {
            if (confirmPass.value === this.value && this.value.length >= 6) {
                confirmPass.style.borderColor = '#10b981';
            } else if (confirmPass.value) {
                confirmPass.style.borderColor = '#ef4444';
            }
        });
    }

    function deactivateAccount() {
        Swal.fire({
            title: 'Deactivate Account?',
            text: 'Your account will be deactivated. You can reactivate later by contacting admin.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, deactivate'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Deactivated!', 'Your account has been deactivated.', 'success');
            }
        });
    }

    function deleteAccount() {
        Swal.fire({
            title: 'Permanently Delete Account?',
            text: 'This action cannot be undone! All your data will be lost.',
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete my account'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Deleted!', 'Your account deletion request has been sent to admin.', 'success');
            }
        });
    }

    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 4000);
</script>

</body>
</html>