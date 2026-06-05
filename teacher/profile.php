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
    die("Database connection failed: " . mysqli_connect_error());
}

$message = "";
$message_type = "";

// Get teacher details from session
$teacher_name = $_SESSION['username'];
$teacher_email = $_SESSION['email'] ?? '';

// Get teacher ID from session
$teacher_user_id = $_SESSION['user_id'];

// Get pending count for sidebar
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = '$teacher_user_id' AND is_read = 0");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

$notifications_list = [];
$notif_query = mysqli_query($data, "SELECT * FROM notifications WHERE user_id = '$teacher_user_id' AND is_read = 0 ORDER BY id DESC LIMIT 5");
if ($notif_query) {
    while ($notif_row = mysqli_fetch_assoc($notif_query)) {
        $notifications_list[] = $notif_row;
    }
}

// ============================================
// FETCH TEACHER DETAILS FROM teacher TABLE
// ============================================
$teacher_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);

$teacher_id = $teacher_data['id'] ?? 0;
$teacher_mobile = $teacher_data['mobile'] ?? '';
$teacher_branch = $teacher_data['branch'] ?? '';
$teacher_qualification = $teacher_data['qualification'] ?? '';
$teacher_experience = $teacher_data['experience'] ?? 0;
$teacher_joining_date = $teacher_data['joining_date'] ?? '';
$teacher_address = $teacher_data['address'] ?? '';
$teacher_dob = $teacher_data['dob'] ?? '';
$teacher_gender = $teacher_data['gender'] ?? '';

// Get teacher's subjects count
$subject_count = 0;
$subject_query = mysqli_query($data, "SELECT COUNT(*) as total FROM teacher_subjects WHERE teacher_id='$teacher_id'");
if ($subject_query && $row = mysqli_fetch_assoc($subject_query)) {
    $subject_count = $row['total'];
}

// ============================================
// HANDLE PROFILE UPDATE
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {

    $name = mysqli_real_escape_string($data, $_POST['name']);
    $mobile = mysqli_real_escape_string($data, $_POST['mobile']);
    $qualification = mysqli_real_escape_string($data, $_POST['qualification']);
    $experience = mysqli_real_escape_string($data, $_POST['experience']);
    $address = mysqli_real_escape_string($data, $_POST['address']);
    $dob = mysqli_real_escape_string($data, $_POST['dob']);
    $gender = mysqli_real_escape_string($data, $_POST['gender']);

    // Check if teacher record exists
    $check_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");

    if (mysqli_num_rows($check_query) > 0) {
        // Update existing record
        $update_query = "UPDATE teacher SET 
                         name='$name',
                         mobile='$mobile',
                         qualification='$qualification',
                         experience='$experience',
                         address='$address',
                         dob='$dob',
                         gender='$gender'
                         WHERE email='$teacher_email'";
    } else {
        // Insert new record
        $update_query = "INSERT INTO teacher (name, email, mobile, branch, qualification, experience, address, dob, gender) 
                         VALUES ('$name', '$teacher_email', '$mobile', '$teacher_branch', '$qualification', '$experience', '$address', '$dob', '$gender')";
    }

    if (mysqli_query($data, $update_query)) {
        // Update username in user table
        mysqli_query($data, "UPDATE user SET username='$name' WHERE email='$teacher_email'");

        $_SESSION['username'] = $name;
        $teacher_name = $name;
        $teacher_mobile = $mobile;
        $teacher_qualification = $qualification;
        $teacher_experience = $experience;
        $teacher_address = $address;
        $teacher_dob = $dob;
        $teacher_gender = $gender;

        $message = "✅ Profile updated successfully!";
        $message_type = "success";
    } else {
        $message = "❌ Error updating profile: " . mysqli_error($data);
        $message_type = "error";
    }
}

$join_year = $teacher_joining_date ? date('Y', strtotime($teacher_joining_date)) : '2025';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Profile | Teacher - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

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

        .teacher-wrap {
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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
            color: rgba(255, 255, 255, 0.4);
            margin-top: 0.25rem;
        }

        .sidebar-menu {
            padding: 0.5rem 0 1rem;
        }

        .menu-title {
            padding: 0.75rem 1.25rem 0.5rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            color: rgba(255, 255, 255, 0.7);
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
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary-light);
        }

        .sidebar a.active {
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.15), transparent);
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

        @media (min-width: 769px) {
            .main {
                margin-left: var(--sidebar-width);
            }
        }

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
            background: rgba(255, 255, 255, 0.95);
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.5rem;
            display: block;
            border-radius: 8px;
            transition: var(--transition);
        }

        .menu-toggle:hover {
            background: var(--light);
        }

        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

        .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-left: 0.5rem;
        }

        /* Quick Add Button */
        .quick-add-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .quick-add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }

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
            box-shadow: var(--shadow-lg);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
            border: 1px solid var(--border);
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
            border-radius: 16px 16px 0 0;
        }

        .quick-menu-header span {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
        }

        .quick-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .quick-menu-item:hover {
            background: var(--light);
            border-left-color: var(--primary);
            padding-left: 1.25rem;
        }

        .quick-menu-item i {
            width: 22px;
            color: var(--primary);
            font-size: 1rem;
        }

        /* Top Right Profile */
        .teacher-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.8rem;
            background: var(--light);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
        }

        .teacher-profile:hover {
            background: #e2e8f0;
        }

        .teacher-avatar {
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
            overflow: hidden;
        }

        .teacher-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .teacher-info {
            display: none;
        }

        @media (min-width: 576px) {
            .teacher-info {
                display: block;
            }

            .teacher-name {
                font-weight: 600;
                font-size: 0.9rem;
                color: var(--dark);
            }

            .teacher-role {
                font-size: 0.7rem;
                color: var(--primary);
            }
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
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
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

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
        .notification-badge i {
            color: var(--secondary);
            font-size: 1.1rem;
        }
        .notification-badge .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 50px;
            color: white;
            line-height: 1;
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* ===== CONTENT AREA ===== */
        .content {
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
        }

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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 6s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.3;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.6;
            }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
        }

        .welcome-banner p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .welcome-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-stat {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .welcome-stat i {
            font-size: 1rem;
        }

        .welcome-stat span {
            font-size: 0.85rem;
            font-weight: 500;
        }

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
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
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

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .profile-grid {
                grid-template-columns: 320px 1fr;
            }
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border: 1px solid var(--border);
            position: relative;
        }

        .profile-cover {
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .profile-avatar-container {
            position: relative;
            margin-top: -50px;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: 4px solid white;
            box-shadow: var(--shadow);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-avatar-wrapper {
            position: relative;
            display: inline-block;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
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

        .profile-info {
            text-align: center;
            padding: 1.5rem;
        }

        .profile-info h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .profile-badge {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            margin-top: 0.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--gray);
        }

        /* Details Card */
        .details-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .details-header h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-item {
            background: var(--light);
            padding: 0.75rem 1rem;
            border-radius: 12px;
        }

        .detail-label {
            font-size: 0.7rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .detail-label i {
            color: var(--primary);
            width: 14px;
            font-size: 0.7rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        /* Edit Form */
        .edit-form {
            display: none;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border);
        }

        .edit-form.show {
            display: block;
        }

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

        .full-width {
            grid-column: span 2;
        }

        @media (max-width: 768px) {
            .full-width {
                grid-column: span 1;
            }
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

        .form-control,
        .form-select {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-size: 0.85rem;
            transition: var(--transition);
            width: 100%;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn-save {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-cancel {
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--border);
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: var(--border);
        }

        /* Account Card */
        .account-card {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .account-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .account-icon i {
            font-size: 1.5rem;
            color: var(--success);
        }

        .account-content p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray);
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
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            overflow: hidden;
        }

        .profile-dropdown-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-dropdown-info h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .profile-dropdown-info p {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 0;
        }

        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }

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

        .profile-dropdown-menu a:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        .profile-dropdown-menu a i {
            width: 22px;
            color: var(--primary);
            font-size: 1rem;
        }

        .profile-dropdown-menu hr {
            margin: 0.5rem 0;
            border-color: var(--border);
        }

        /* Modal */
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

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        @media (max-width: 768px) {
            .details-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .edit-btn {
                width: 100%;
                text-align: center;
            }

            .profile-dropdown {
                right: 10px;
                left: 10px;
                min-width: auto;
            }

            .btn-save,
            .btn-cancel {
                width: 100%;
            }

            .mt-4.d-flex {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="teacher-wrap">

        <!-- SIDEBAR -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>📚 StudyBuddyHub</h3>
                <p>College Management System</p>
            </div>

            <div class="sidebar-menu">
                <div class="menu-title">MAIN</div>
                <a href="dashboard.php"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

                <div class="menu-title">ACADEMICS</div>
                <a href="classes.php"> <i class="fas fa-book"></i> <span>My Classes</span> </a>
                <a href="materials.php"> <i class="fas fa-file-pdf"></i> <span>Study Materials</span> </a>
                <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>

                <div class="menu-title">STUDENT MANAGEMENT</div>
                <a href="attendance.php"> <i class="fas fa-calendar-check"></i> <span>Attendance</span> </a>
                <a href="results.php"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>
                <a href="students.php"> <i class="fas fa-user-graduate"></i> <span>My Students</span> </a>

                <div class="menu-title">RESOURCES</div>
                <a href="syllabus.php"> <i class="fas fa-list-alt"></i> <span>Syllabus</span> </a>
                <a href="timetable.php"> <i class="fas fa-clock"></i> <span>Time Table</span> </a>
                <a href="profile.php" class="active"> <i class="fas fa-user"></i> <span>Profile</span> </a>
                <a href="settings.php"> <i class="fas fa-cog"></i> <span>Settings</span> </a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main">
            <div class="topbar">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle" onclick="toggleSidebar()"> <i class="fas fa-bars"></i> </button>
                    <span class="page-title"><i class="fas fa-user me-2" style="color: var(--primary);"></i>My
                        Profile</span>
                </div>
                <div class="topbar-actions">
                    <div class="quick-dropdown">
                        <button class="quick-add-btn" id="quickAddBtn">
                            <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down"
                                style="font-size: 0.7rem;"></i>
                        </button>
                        <div class="quick-menu" id="quickMenu">
                            <div class="quick-menu-header"><span><i class="fas fa-plus-circle"></i> Quick Actions</span></div>
                            <div class="quick-menu-item" data-page="upload_syllabus.php" data-title="Upload Syllabus" data-icon="fa-list-alt">
                                <i class="fas fa-list-alt"></i><span>Upload Syllabus</span>
                            </div>
                            <div class="quick-menu-item" data-page="add_material.php" data-title="Upload Study Material" data-icon="fa-file-pdf">
                                <i class="fas fa-file-pdf"></i><span>Upload Material</span>
                            </div>
                            <div class="quick-menu-item" data-page="add_assignment.php" data-title="Create Assignment" data-icon="fa-plus-circle">
                                <i class="fas fa-plus-circle"></i><span>Create Assignment</span>
                            </div>
                            <div class="quick-menu-item" data-page="mark_attendance.php" data-title="Mark Attendance" data-icon="fa-calendar-check">
                                <i class="fas fa-calendar-check"></i><span>Mark Attendance</span>
                            </div>
                            <div class="quick-menu-item" data-page="add_result.php" data-title="Add Results" data-icon="fa-chart-line">
                                <i class="fas fa-chart-line"></i><span>Add Results</span>
                            </div>
                        </div>
                    </div>

                    <div class="notification-badge me-2" onclick="viewNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($pending_count > 0): ?>
                            <span class="badge-count"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="teacher-profile" onclick="toggleProfileMenu()">
                        <div class="teacher-avatar">
                            <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                                <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="teacher-info">
                            <div class="teacher-name"><?php echo htmlspecialchars($teacher_name); ?></div>
                            <div class="teacher-role">Teacher</div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #666;"></i>
                    </div>
                    <a href="../logout.php" class="logout-btn"> <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- Welcome Banner -->
                <div class="welcome-banner" data-aos="fade-up">
                    <div class="welcome-content">
                        <h2>👤 My Profile</h2>
                        <p>View and manage your personal information.</p>
                        <div class="welcome-stats">
                            <div class="welcome-stat">
                                <i class="fas fa-book"></i>
                                <span><?php echo $subject_count; ?> Subjects</span>
                            </div>
                            <div class="welcome-stat">
                                <i class="fas fa-briefcase"></i>
                                <span><?php echo $teacher_experience ?: '0'; ?> Years Exp</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message != ""): ?>
                    <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>"
                        data-aos="fade-up">
                        <i
                            class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Profile Grid -->
                <div class="profile-grid" data-aos="fade-up" data-aos-delay="100">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-cover"></div>
                        <div class="profile-avatar-container">
                            <div class="profile-avatar-wrapper">
                                <div class="profile-avatar">
                                    <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Profile Picture">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-edit-btn" onclick="changeAvatar()" title="Change Profile Picture">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($teacher_name); ?></h3>
                            <div class="profile-badge">Teacher</div>
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $subject_count; ?></div>
                                    <div class="stat-label">Subjects</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $teacher_experience ?: '0'; ?></div>
                                    <div class="stat-label">Experience</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $join_year; ?></div>
                                    <div class="stat-label">Joined</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Card -->
                    <div class="details-card">
                        <div class="details-header">
                            <h4><i class="fas fa-info-circle"></i> Personal Information</h4>
                            <button class="edit-btn" onclick="toggleEditForm()"><i class="fas fa-edit"></i> Edit
                                Profile</button>
                        </div>

                        <!-- View Details -->
                        <div id="viewDetails">
                            <div class="details-grid">
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-user"></i> Full Name</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($teacher_name); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-envelope"></i> Email Address</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($teacher_email); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-phone"></i> Mobile Number</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($teacher_mobile) ?: 'Not provided'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-code-branch"></i> Branch</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($teacher_branch) ?: 'Not assigned'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($teacher_qualification) ?: 'Not provided'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-briefcase"></i> Experience</div>
                                    <div class="detail-value">
                                        <?php echo $teacher_experience ? $teacher_experience . ' Years' : 'Not provided'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-calendar-alt"></i> Joining Date</div>
                                    <div class="detail-value">
                                        <?php echo $teacher_joining_date ? date('d M Y', strtotime($teacher_joining_date)) : 'Not provided'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($teacher_address) ?: 'Not provided'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-birthday-cake"></i> Date of Birth</div>
                                    <div class="detail-value">
                                        <?php echo $teacher_dob ? date('d M Y', strtotime($teacher_dob)) : 'Not provided'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-venus-mars"></i> Gender</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($teacher_gender) ?: 'Not provided'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <div class="edit-form" id="editForm">
                            <form method="post">
                                <div class="form-grid">
                                    <div class="full-width">
                                        <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                        <input type="text" name="name" class="form-control"
                                            value="<?php echo htmlspecialchars($teacher_name); ?>" required>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-phone"></i> Mobile Number</label>
                                        <input type="tel" name="mobile" class="form-control"
                                            value="<?php echo htmlspecialchars($teacher_mobile); ?>" maxlength="10"
                                            pattern="[0-9]{10}">
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-venus-mars"></i> Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($teacher_gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($teacher_gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($teacher_gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-birthday-cake"></i> Date of
                                            Birth</label>
                                        <input type="date" name="dob" class="form-control"
                                            value="<?php echo $teacher_dob; ?>">
                                    </div>
                                    <div class="full-width">
                                        <label class="form-label"><i class="fas fa-graduation-cap"></i>
                                            Qualification</label>
                                        <input type="text" name="qualification" class="form-control"
                                            value="<?php echo htmlspecialchars($teacher_qualification); ?>">
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-briefcase"></i> Experience
                                            (Years)</label>
                                        <input type="number" name="experience" class="form-control"
                                            value="<?php echo $teacher_experience; ?>" min="0" max="50">
                                    </div>
                                    <div class="full-width">
                                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                        <textarea name="address" class="form-control"
                                            rows="3"><?php echo htmlspecialchars($teacher_address); ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" name="update_profile" class="btn-save"><i
                                            class="fas fa-save"></i> Save Changes</button>
                                    <button type="button" class="btn-cancel" onclick="toggleEditForm()"><i
                                            class="fas fa-times"></i> Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Card -->
                <div class="account-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="account-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="account-content">
                        <p><i class="fas fa-check-circle text-success me-1"></i> Account Type: <strong>Teacher</strong>
                            | Member Since: <strong><?php echo $join_year; ?></strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Dropdown -->
    <div class="profile-dropdown" id="profileDropdown">
        <div class="profile-dropdown-header">
            <div class="profile-dropdown-avatar">
                <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                    <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-dropdown-info">
                <h4><?php echo htmlspecialchars($teacher_name); ?></h4>
                <p><?php echo $teacher_email; ?></p>
                <?php if ($teacher_branch): ?>
                    <p class="mt-1"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($teacher_branch); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-dropdown-menu">
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="classes.php"><i class="fas fa-book"></i> My Classes</a>
            <a href="students.php"><i class="fas fa-user-graduate"></i> My Students</a>
            <a href="syllabus.php"><i class="fas fa-list-alt"></i> Syllabus</a>
            <a href="timetable.php"><i class="fas fa-clock"></i> Time Table</a>
            <hr>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Quick Add Modal -->
    <div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modal-content-iframe">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="quickAddModalLabel">
                        <i class="fas fa-plus-circle"></i> Loading...
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
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

        document.addEventListener('click', function (e) {
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

        window.addEventListener('resize', function () {
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

        document.addEventListener('click', function (e) {
            if (quickMenu && !quickMenu.contains(e.target) && quickAddBtn && !quickAddBtn.contains(e.target)) {
                closeQuickMenu();
            }
        });

        function openInQuickModal(pageUrl, title, icon = 'fa-plus-circle') {
            const modalElement = document.getElementById('quickAddModal');
            if (!modalElement) return;
            const modal = new bootstrap.Modal(modalElement);
            const modalTitle = document.getElementById('quickAddModalLabel');
            const modalBody = document.getElementById('quickAddModalBody');

            if (modalTitle) modalTitle.innerHTML = `<i class="fas ${icon}"></i> ${title}`;
            if (modalBody) modalBody.innerHTML = `<iframe src="${pageUrl}" style="width: 100%; height: 75vh; border: none;" title="${title}"></iframe>`;
            modal.show();
        }

        document.querySelectorAll('.quick-menu-item').forEach(item => {
            item.addEventListener('click', function (e) {
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

        // Profile Dropdown
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
            }
        }

        document.addEventListener('click', function (e) {
            const dropdown = document.getElementById('profileDropdown');
            const profile = document.querySelector('.teacher-profile');
            if (profile && !profile.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) dropdown.style.display = 'none';
            }
        });

        // View Notifications
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

        // Edit Form Toggle
        function toggleEditForm() {
            const editForm = document.getElementById('editForm');
            const viewDetails = document.getElementById('viewDetails');
            if (editForm && viewDetails) {
                editForm.classList.toggle('show');
                viewDetails.style.display = editForm.classList.contains('show') ? 'none' : 'block';
            }
        }

        // Auto-hide alert after 5 seconds
        setTimeout(function () {
            const alert = document.querySelector('.alert-custom');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 4000);

        // Change Avatar Function - FIXED
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
                confirmButtonColor: '#4f46e5',
                denyButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280'
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
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#6b7280',
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
                                Swal.fire('Error', 'An error occurred.', 'error');
                            });
                        }
                    });
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>

</body>

</html>