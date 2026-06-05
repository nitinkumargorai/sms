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

/* HANDLE SEND NOTIFICATION - FIXED QUERIES */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_notification'])) {
    $usertype = mysqli_real_escape_string($data, $_POST['usertype']);
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = intval($_POST['semester']);
    $specific_user = isset($_POST['specific_user']) ? intval($_POST['specific_user']) : 0;
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $message_text = mysqli_real_escape_string($data, trim($_POST['message']));
    $link = mysqli_real_escape_string($data, $_POST['link'] ?? '');
    
    // Determine target users - FIXED QUERIES
    $target_users = [];
    
    if ($specific_user > 0) {
        // Send to specific user
        if ($usertype == 'student') {
            // Get user_id from admission table directly
            $user_query = mysqli_query($data, "SELECT user_id FROM admission WHERE id = $specific_user");
            if ($user_query && $row = mysqli_fetch_assoc($user_query)) {
                if ($row['user_id']) {
                    $target_users[] = $row['user_id'];
                } else {
                    // Fallback: get user from user table by email
                    $student_query = mysqli_query($data, "SELECT Email FROM admission WHERE id = $specific_user");
                    if ($student_query && $student_row = mysqli_fetch_assoc($student_query)) {
                        $user_fallback = mysqli_query($data, "SELECT id FROM user WHERE email = '{$student_row['Email']}' AND usertype = 'student'");
                        if ($user_fallback && $uf_row = mysqli_fetch_assoc($user_fallback)) {
                            $target_users[] = $uf_row['id'];
                        }
                    }
                }
            }
        } elseif ($usertype == 'teacher') {
            // Get user_id from teacher table
            $user_query = mysqli_query($data, "SELECT user_id FROM teacher WHERE id = $specific_user");
            if ($user_query && $row = mysqli_fetch_assoc($user_query)) {
                if ($row['user_id']) {
                    $target_users[] = $row['user_id'];
                } else {
                    // Fallback: get user from user table by email
                    $teacher_query = mysqli_query($data, "SELECT email FROM teacher WHERE id = $specific_user");
                    if ($teacher_query && $teacher_row = mysqli_fetch_assoc($teacher_query)) {
                        $user_fallback = mysqli_query($data, "SELECT id FROM user WHERE email = '{$teacher_row['email']}' AND usertype = 'teacher'");
                        if ($user_fallback && $uf_row = mysqli_fetch_assoc($user_fallback)) {
                            $target_users[] = $uf_row['id'];
                        }
                    }
                }
            }
        }
    } else {
        // Send to all users matching criteria
        if ($usertype == 'student') {
            $student_query = "SELECT u.id FROM user u 
                              INNER JOIN admission a ON a.user_id = u.id 
                              WHERE u.usertype = 'student'";
            if (!empty($branch)) {
                $student_query .= " AND a.Branch = '$branch'";
            }
            if ($semester > 0) {
                $student_query .= " AND a.Semester = $semester";
            }
            $result = mysqli_query($data, $student_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
            
            // If no users found with user_id link, try direct user table
            if (empty($target_users)) {
                $student_query2 = "SELECT u.id FROM user u WHERE u.usertype = 'student'";
                $result2 = mysqli_query($data, $student_query2);
                if ($result2) {
                    while ($row = mysqli_fetch_assoc($result2)) {
                        $target_users[] = $row['id'];
                    }
                }
            }
        } elseif ($usertype == 'teacher') {
            $teacher_query = "SELECT u.id FROM user u 
                              INNER JOIN teacher t ON t.user_id = u.id 
                              WHERE u.usertype = 'teacher'";
            if (!empty($branch)) {
                $teacher_query .= " AND t.branch = '$branch'";
            }
            $result = mysqli_query($data, $teacher_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
            
            // If no users found with user_id link, try direct user table
            if (empty($target_users)) {
                $teacher_query2 = "SELECT u.id FROM user u WHERE u.usertype = 'teacher'";
                $result2 = mysqli_query($data, $teacher_query2);
                if ($result2) {
                    while ($row = mysqli_fetch_assoc($result2)) {
                        $target_users[] = $row['id'];
                    }
                }
            }
        } elseif ($usertype == 'all') {
            $all_query = "SELECT id FROM user WHERE usertype IN ('student', 'teacher')";
            $result = mysqli_query($data, $all_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
        }
    }
    
    if (empty($target_users)) {
        $message = "No users found matching the selected criteria!";
        $message_type = "error";
    } else {
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($target_users as $user_id) {
            $insert_sql = "INSERT INTO notifications (user_id, usertype, title, message, link, is_read, created_at) 
                           VALUES ($user_id, '$usertype', '$title', '$message_text', '$link', 0, NOW())";
            if (mysqli_query($data, $insert_sql)) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }
        
        $message = "Notification sent to $sent_count user(s)";
        if ($failed_count > 0) {
            $message .= " ($failed_count failed)";
        }
        $message_type = "success";
    }
    
    header("Location: send_notification.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* HANDLE DELETE NOTIFICATION */
if (isset($_GET['delete_id'])) {
    $notification_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM notifications WHERE id = $notification_id";
    if (mysqli_query($data, $delete_sql)) {
        $message = "Notification deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: send_notification.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH BRANCHES FOR FILTER */
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

/* FETCH STUDENTS FOR DROPDOWN - FIXED */
$students_query = mysqli_query($data, "SELECT a.id, a.Name, a.registration_no, a.Branch, a.Semester, a.Email 
                                       FROM admission a 
                                       ORDER BY a.Name");
$students = [];
while ($row = mysqli_fetch_assoc($students_query)) {
    $students[] = $row;
}

/* FETCH TEACHERS FOR DROPDOWN - FIXED */
$teachers_query = mysqli_query($data, "SELECT t.id, t.name, t.email, t.branch 
                                       FROM teacher t 
                                       ORDER BY t.name");
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_query)) {
    $teachers[] = $row;
}

/* FETCH RECENT NOTIFICATIONS */
$notifications_query = mysqli_query($data, "
    SELECT n.*, 
           CASE 
               WHEN n.usertype = 'student' THEN (SELECT Name FROM admission WHERE user_id = n.user_id LIMIT 1)
               WHEN n.usertype = 'teacher' THEN (SELECT name FROM teacher WHERE user_id = n.user_id LIMIT 1)
               ELSE 'All Users'
           END as recipient_name
    FROM notifications n
    ORDER BY n.created_at DESC
    LIMIT 50
");

$notifications = [];
while ($row = mysqli_fetch_assoc($notifications_query)) {
    $notifications[] = $row;
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
    <title>Send Notification | Admin Panel - StudyBuddyHub</title>

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

        /* ===== SELECTION CARD ===== */
        .selection-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        .selection-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .selection-card { padding: 1.5rem; } }

        .selection-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .selection-title i {
            color: var(--primary);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
        }
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

        .btn-send {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
            color: white;
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
        .total-notifications {
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
            min-width: 900px;
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

        .badge-usertype {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-student { background: rgba(6,214,160,0.1); color: var(--success); }
        .badge-teacher { background: rgba(67,97,238,0.1); color: var(--primary); }
        .badge-all { background: rgba(255,193,7,0.1); color: #d4a000; }

        .badge-read {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.65rem;
        }
        .badge-unread {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.65rem;
        }

        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-view, .btn-delete {
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
            max-width: 600px;
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            font-weight: 500;
            margin-bottom: 0.3rem;
            display: block;
            font-size: 0.85rem;
            color: var(--dark);
        }
        .form-group label i {
            margin-right: 0.3rem;
            color: var(--primary);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-send-modal {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-send-modal:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        .btn-cancel {
            background: var(--gray);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-cancel:hover {
            background: #5a6268;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* View Modal */
        .view-modal .modal-content-custom {
            max-width: 500px;
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
        .message-content {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            line-height: 1.5;
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
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
            <a href="results.php"> <i class="fas fa-chart-bar"></i> <span>Results</span> </a>

            <div class="menu-title">NOTIFICATIONS</div>
            <a href="send_notification.php" class="active"> <i class="fas fa-bell"></i> <span>Send Notification</span> </a>
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
                    <i class="fas fa-bell"></i> Send Notification
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
                <h2> <i class="fas fa-bell" style="color: var(--primary);"></i> Send Notifications </h2>
                <p>Create and send notifications to students and teachers</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- SELECTION CARD -->
            <div class="selection-card" data-aos="fade-up" data-aos-delay="50">
                <div class="selection-title">
                    <i class="fas fa-users"></i>
                    Select Recipients
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-user-tag"></i> User Type</label>
                        <select name="usertype" class="form-select" id="usertypeSelect" required>
                            <option value="">-- Select User Type --</option>
                            <option value="student">Students</option>
                            <option value="teacher">Teachers</option>
                            <option value="all">All Users</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="branchDiv" style="display: none;">
                        <label class="form-label"><i class="fas fa-code-branch"></i> Branch</label>
                        <select name="branch" class="form-select" id="branchSelect">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                    <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2" id="semesterDiv" style="display: none;">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Semester</label>
                        <select name="semester" class="form-select" id="semesterSelect">
                            <option value="0">All Semesters</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4" id="specificUserDiv" style="display: none;">
                        <label class="form-label"><i class="fas fa-user-check"></i> Specific User</label>
                        <select name="specific_user" class="form-select" id="specificUserSelect">
                            <option value="0">-- Send to All --</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn-send w-100" id="openSendModalBtn">
                            <i class="fas fa-paper-plane"></i> Send Notification
                        </button>
                    </div>
                </div>
            </div>

            <!-- RECENT NOTIFICATIONS TABLE - FIXED WITH PROPER TABLE STRUCTURE -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-history"></i>
                        Sent Notifications
                    </h4>
                    <div class="total-notifications">
                        <i class="fas fa-bell"></i> Total: <?php echo count($notifications); ?> Notifications
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if (!empty($notifications)): ?>
                        <table id="notificationsTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Recipient</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Sent Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notif): ?>
                                <tr id="notif-row-<?php echo $notif['id']; ?>">
                                    <td><span class="badge-usertype" style="background: #e9ecef;">#<?php echo $notif['id']; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($notif['title']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars(substr($notif['message'], 0, 40)); ?>...</small></td>
                                    <td>
                                        <?php if ($notif['usertype'] == 'all'): ?>
                                            <span class="badge-usertype badge-all">All Users</span>
                                        <?php elseif ($notif['usertype'] == 'student'): ?>
                                            <span class="badge-usertype badge-student">Student</span>
                                        <?php else: ?>
                                            <span class="badge-usertype badge-teacher">Teacher</span>
                                        <?php endif; ?>
                                        <?php if ($notif['recipient_name'] && $notif['usertype'] != 'all'): ?>
                                            <br><small class="text-muted">To: <?php echo htmlspecialchars($notif['recipient_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notif['usertype'] == 'student'): ?>
                                            <span class="badge-usertype badge-student"><i class="fas fa-user-graduate"></i> Student</span>
                                        <?php elseif ($notif['usertype'] == 'teacher'): ?>
                                            <span class="badge-usertype badge-teacher"><i class="fas fa-chalkboard-teacher"></i> Teacher</span>
                                        <?php else: ?>
                                            <span class="badge-usertype badge-all"><i class="fas fa-users"></i> All</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notif['is_read'] == 1): ?>
                                            <span class="badge-read"><i class="fas fa-check-circle"></i> Read</span>
                                        <?php else: ?>
                                            <span class="badge-unread"><i class="fas fa-clock"></i> Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-view" onclick='viewNotification(<?php echo json_encode($notif); ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-delete" onclick="deleteNotification(<?php echo $notif['id']; ?>, '<?php echo htmlspecialchars($notif['title']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h5>No Notifications Sent</h5>
                            <p>You haven't sent any notifications yet. Use the form above to send your first notification.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEND NOTIFICATION MODAL -->
<div class="modal-custom" id="sendModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-paper-plane"></i> Send Notification</h5>
            <button class="modal-close" onclick="closeSendModal()">&times;</button>
        </div>
        <form method="post" id="sendNotificationForm">
            <input type="hidden" name="send_notification" value="1">
            <input type="hidden" name="usertype" id="modal_usertype">
            <input type="hidden" name="branch" id="modal_branch">
            <input type="hidden" name="semester" id="modal_semester">
            <input type="hidden" name="specific_user" id="modal_specific_user">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Exam Schedule, Holiday Notice" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="5" placeholder="Type your notification message here..." required></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Link (Optional)</label>
                    <input type="text" name="link" class="form-control" placeholder="https://example.com">
                    <small class="text-muted">Add a link for more information</small>
                </div>
                <div class="alert alert-info mt-2" id="recipientInfo" style="font-size: 0.8rem; background: rgba(67,97,238,0.05); border: 1px solid var(--border);">
                    <i class="fas fa-info-circle"></i> <span id="recipientInfoText">Select recipients from the dropdown</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeSendModal()">Cancel</button>
                <button type="submit" class="btn-send-modal"><i class="fas fa-paper-plane"></i> Send Notification</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW NOTIFICATION MODAL -->
<div class="modal-custom view-modal" id="viewModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-bell"></i> Notification Details</h5>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="viewModalBody">
            <!-- Dynamic content -->
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
            closeSendModal();
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
    <?php if (!empty($notifications)): ?>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#notificationsTable')) {
            $('#notificationsTable').DataTable().destroy();
        }
        
        var $table = $('#notificationsTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 7) {
            $('#notificationsTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, width: '60px' },
                    { targets: 1, width: '200px' },
                    { targets: 2, width: '150px' },
                    { targets: 3, width: '100px' },
                    { targets: 4, width: '80px' },
                    { targets: 5, width: '120px' },
                    { targets: 6, width: '100px', orderable: false }
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
            $('#notificationsTable').addClass('table table-striped');
        }
    });
    <?php endif; ?>

    // Dynamic form handling
    const usertypeSelect = document.getElementById('usertypeSelect');
    const branchDiv = document.getElementById('branchDiv');
    const semesterDiv = document.getElementById('semesterDiv');
    const specificUserDiv = document.getElementById('specificUserDiv');
    const specificUserSelect = document.getElementById('specificUserSelect');
    const openSendModalBtn = document.getElementById('openSendModalBtn');

    // Student and teacher data for dropdown
    const students = <?php echo json_encode($students); ?>;
    const teachers = <?php echo json_encode($teachers); ?>;

    if (usertypeSelect) {
        usertypeSelect.addEventListener('change', function() {
            const usertype = this.value;
            
            if (usertype === 'student') {
                branchDiv.style.display = 'block';
                semesterDiv.style.display = 'block';
                specificUserDiv.style.display = 'block';
                // Populate specific user dropdown with students
                specificUserSelect.innerHTML = '<option value="0">-- Send to All Students --</option>';
                students.forEach(student => {
                    specificUserSelect.innerHTML += `<option value="${student.id}">${student.Name} (${student.registration_no}) - ${student.Branch} Sem ${student.Semester}</option>`;
                });
            } else if (usertype === 'teacher') {
                branchDiv.style.display = 'block';
                semesterDiv.style.display = 'none';
                specificUserDiv.style.display = 'block';
                // Populate specific user dropdown with teachers
                specificUserSelect.innerHTML = '<option value="0">-- Send to All Teachers --</option>';
                teachers.forEach(teacher => {
                    specificUserSelect.innerHTML += `<option value="${teacher.id}">${teacher.name} (${teacher.email}) - ${teacher.branch || 'All Branches'}</option>`;
                });
            } else if (usertype === 'all') {
                branchDiv.style.display = 'none';
                semesterDiv.style.display = 'none';
                specificUserDiv.style.display = 'none';
            } else {
                branchDiv.style.display = 'none';
                semesterDiv.style.display = 'none';
                specificUserDiv.style.display = 'none';
            }
        });
    }

    // Open send modal and set hidden fields
    if (openSendModalBtn) {
        openSendModalBtn.addEventListener('click', function() {
            const usertype = usertypeSelect.value;
            const branch = document.getElementById('branchSelect') ? document.getElementById('branchSelect').value : '';
            const semester = document.getElementById('semesterSelect') ? document.getElementById('semesterSelect').value : 0;
            const specificUser = specificUserSelect ? specificUserSelect.value : 0;
            
            if (!usertype) {
                Swal.fire({
                    icon: 'error',
                    title: 'Selection Required',
                    text: 'Please select a user type first!',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            // Set hidden fields
            document.getElementById('modal_usertype').value = usertype;
            document.getElementById('modal_branch').value = branch;
            document.getElementById('modal_semester').value = semester;
            document.getElementById('modal_specific_user').value = specificUser;
            
            // Update recipient info text
            let recipientText = '';
            if (specificUser != 0) {
                if (usertype === 'student') {
                    const student = students.find(s => s.id == specificUser);
                    recipientText = `Sending to: ${student ? student.Name : 'Selected Student'} (Student)`;
                } else if (usertype === 'teacher') {
                    const teacher = teachers.find(t => t.id == specificUser);
                    recipientText = `Sending to: ${teacher ? teacher.name : 'Selected Teacher'} (Teacher)`;
                }
            } else {
                if (usertype === 'student') {
                    let text = `Sending to: All Students`;
                    if (branch) text += ` in ${branch}`;
                    if (semester > 0) text += ` - Semester ${semester}`;
                    recipientText = text;
                } else if (usertype === 'teacher') {
                    let text = `Sending to: All Teachers`;
                    if (branch) text += ` in ${branch}`;
                    recipientText = text;
                } else if (usertype === 'all') {
                    recipientText = 'Sending to: All Users (Students & Teachers)';
                }
            }
            
            document.getElementById('recipientInfoText').innerHTML = recipientText;
            
            // Open modal
            document.getElementById('sendModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    function closeSendModal() {
        document.getElementById('sendModal').classList.remove('active');
        document.body.style.overflow = '';
        // Reset form
        if (document.getElementById('sendNotificationForm')) {
            document.getElementById('sendNotificationForm').reset();
        }
    }

    // View Notification
    function viewNotification(notif) {
        let modalContent = `
            <div class="text-center mb-3">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-bell fa-2x" style="color: white;"></i>
                </div>
                <h5 style="font-weight: 700; color: var(--dark);">${escapeHtml(notif.title)}</h5>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-users"></i> Recipient</div>
                <div class="detail-value">
                    ${notif.usertype === 'student' ? '<span class="badge-usertype badge-student">Student</span>' : 
                      notif.usertype === 'teacher' ? '<span class="badge-usertype badge-teacher">Teacher</span>' : 
                      '<span class="badge-usertype badge-all">All Users</span>'}
                    ${notif.recipient_name ? `<br><small>To: ${escapeHtml(notif.recipient_name)}</small>` : ''}
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-envelope"></i> Message</div>
                <div class="message-content">${escapeHtml(notif.message)}</div>
            </div>
            ${notif.link ? `<div class="detail-row">
                <div class="detail-label"><i class="fas fa-link"></i> Link</div>
                <div class="detail-value"><a href="${escapeHtml(notif.link)}" target="_blank">${escapeHtml(notif.link)}</a></div>
            </div>` : ''}
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-calendar-alt"></i> Sent Date</div>
                <div class="detail-value">${new Date(notif.created_at).toLocaleString()}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-eye"></i> Status</div>
                <div class="detail-value">
                    ${notif.is_read == 1 ? '<span class="badge-read"><i class="fas fa-check-circle"></i> Read</span>' : '<span class="badge-unread"><i class="fas fa-clock"></i> Unread</span>'}
                </div>
            </div>
        `;
        
        document.getElementById('viewModalBody').innerHTML = modalContent;
        document.getElementById('viewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Delete Notification
    function deleteNotification(id, title) {
        Swal.fire({
            title: 'Delete Notification?',
            html: `Are you sure you want to delete notification "<strong>${escapeHtml(title)}</strong>"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `send_notification.php?delete_id=${id}`;
            }
        });
    }

    // Close modals on outside click
    const sendModal = document.getElementById('sendModal');
    const viewModal = document.getElementById('viewModal');
    
    if (sendModal) {
        sendModal.addEventListener('click', function(e) {
            if (e.target === this) closeSendModal();
        });
    }
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });
    }

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