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

// Get teacher details
$teacher_name = $_SESSION['username'];
$teacher_email = $_SESSION['email'] ?? '';

$teacher_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);
$teacher_branch = $teacher_data['branch'] ?? '';
$teacher_mobile = $teacher_data['mobile'] ?? '';
$teacher_id = $teacher_data['id'] ?? 0;

// Get teacher's subjects
$teacher_subjects = [];
$subject_query = mysqli_query($data, "
    SELECT s.id, s.subject_name, s.subject_code, s.semester, s.branch
    FROM subjects s
    JOIN teacher_subjects ts ON s.id = ts.subject_id
    WHERE ts.teacher_id = '$teacher_id'
    ORDER BY s.semester ASC, s.subject_name ASC
");

if ($subject_query) {
    while ($row = mysqli_fetch_assoc($subject_query)) {
        $teacher_subjects[] = $row;
    }
}

// Get pending count for sidebar
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

$message = "";
$message_type = "";
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_date = isset($_GET['date']) ? mysqli_real_escape_string($data, $_GET['date']) : date('Y-m-d');
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'daily';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get students for selected subject
$students = [];
$subject_name = "";
$subject_code = "";
$subject_semester = 0;

if ($selected_subject_id > 0) {
    // Get subject details
    $subj_query = mysqli_query($data, "SELECT subject_name, subject_code, semester, branch FROM subjects WHERE id = $selected_subject_id");
    if ($subj_query && $subj_row = mysqli_fetch_assoc($subj_query)) {
        $subject_name = $subj_row['subject_name'];
        $subject_code = $subj_row['subject_code'];
        $subject_semester = $subj_row['semester'];
        $subject_branch = $subj_row['branch'];
        
        // Get students from this branch and semester
        $students_query = mysqli_query($data, "
            SELECT id, Name, registration_no, Email, mobile 
            FROM admission 
            WHERE Branch = '$subject_branch' AND Semester = $subject_semester
            ORDER BY Name ASC
        ");
        
        if ($students_query) {
            while ($row = mysqli_fetch_assoc($students_query)) {
                $students[] = $row;
            }
        }
    }
}

// Get attendance records
$attendance_records = [];

if ($selected_subject_id > 0 && $view_mode == 'daily') {
    $daily_query = mysqli_query($data, "
        SELECT student_id, status, remarks 
        FROM attendance 
        WHERE subject_id = $selected_subject_id AND date = '$selected_date'
    ");
    if ($daily_query) {
        while ($row = mysqli_fetch_assoc($daily_query)) {
            $attendance_records[$row['student_id']] = $row;
        }
    }
}

// Get monthly attendance data
$monthly_attendance = [];
if ($selected_subject_id > 0 && $view_mode == 'monthly') {
    $month_start = date('Y-m-01', strtotime($selected_month));
    $month_end = date('Y-m-t', strtotime($selected_month));
    
    $monthly_query = mysqli_query($data, "
        SELECT 
            student_id,
            date,
            status
        FROM attendance 
        WHERE subject_id = $selected_subject_id 
        AND date BETWEEN '$month_start' AND '$month_end'
    ");
    
    if ($monthly_query) {
        while ($row = mysqli_fetch_assoc($monthly_query)) {
            $monthly_attendance[$row['student_id']][$row['date']] = $row['status'];
        }
    }
}

// Calculate daily totals
$total_present_today = 0;
$total_absent_today = 0;
$total_late_today = 0;

if ($selected_subject_id > 0 && !empty($students)) {
    foreach ($students as $student) {
        $status = $attendance_records[$student['id']]['status'] ?? 'absent';
        if ($status == 'present') $total_present_today++;
        elseif ($status == 'absent') $total_absent_today++;
        elseif ($status == 'late') $total_late_today++;
    }
}

// Get dates for month view
$days_in_month = date('t', strtotime($selected_month));
$month_dates = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $date = date('Y-m-d', strtotime("$selected_month-$i"));
    $month_dates[] = $date;
}

// Handle attendance submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_attendance'])) {
    $subject_id = intval($_POST['subject_id']);
    $attendance_date = mysqli_real_escape_string($data, $_POST['attendance_date']);
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    mysqli_begin_transaction($data);
    
    try {
        mysqli_query($data, "DELETE FROM attendance WHERE subject_id = $subject_id AND date = '$attendance_date'");
        
        $insert_count = 0;
        foreach ($statuses as $student_id => $status) {
            $student_id = intval($student_id);
            $status = mysqli_real_escape_string($data, $status);
            $remark = mysqli_real_escape_string($data, $remarks[$student_id] ?? '');
            
            $insert_sql = "INSERT INTO attendance (student_id, subject_id, date, status, marked_by, remarks) 
                           VALUES ($student_id, $subject_id, '$attendance_date', '$status', $teacher_id, '$remark')";
            
            if (mysqli_query($data, $insert_sql)) {
                $insert_count++;
            }
        }
        
        mysqli_commit($data);
        $message = "Attendance saved successfully! $insert_count records updated.";
        $message_type = "success";
        
    } catch (Exception $e) {
        mysqli_rollback($data);
        $message = "Error saving attendance: " . $e->getMessage();
        $message_type = "error";
    }
    
    header("Location: attendance.php?subject_id=$subject_id&date=$attendance_date&view=daily&msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Get message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Attendance Management | Teacher - StudyBuddyHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray: #6b7280;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 280px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body { background: #f1f5f9; overflow-x: hidden; }
        .teacher-wrap { display: flex; min-height: 100vh; position: relative; }
        
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
        .sidebar-header p { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 0.25rem; }
        
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
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: var(--primary-light); }
        .sidebar a.active {
            background: linear-gradient(90deg, rgba(79,70,229,0.15), transparent);
            color: white;
            border-left-color: var(--primary);
            font-weight: 500;
        }
        
        .main {
            flex: 1;
            width: 100%;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }
        @media (min-width: 769px) { .main { margin-left: var(--sidebar-width); } }
        
        .topbar {
            background: white;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 99;
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
        }
        .menu-toggle:hover { background: var(--light); }
        @media (min-width: 769px) { .menu-toggle { display: none; } }
        .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-left: 0.5rem;
        }
        
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
        }
        .quick-add-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79,70,229,0.4); }
        
        .quick-dropdown { position: relative; }
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
        .quick-menu.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .quick-menu-header {
            padding: 0.75rem 1rem;
            background: var(--light);
            border-bottom: 1px solid var(--border);
            border-radius: 16px 16px 0 0;
        }
        .quick-menu-header span { font-size: 0.7rem; font-weight: 600; color: var(--gray); text-transform: uppercase; }
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
        .quick-menu-item i { width: 22px; color: var(--primary); font-size: 1rem; }
        
        .teacher-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.8rem;
            background: var(--light);
            border-radius: 50px;
            cursor: pointer;
        }
        .teacher-profile:hover { background: #e2e8f0; }
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
        }
        .teacher-info { display: none; }
        @media (min-width: 576px) {
            .teacher-info { display: block; }
            .teacher-name { font-weight: 600; font-size: 0.9rem; color: var(--dark); }
            .teacher-role { font-size: 0.7rem; color: var(--primary); }
        }
        .logout-btn {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn:hover { background: var(--danger); color: white; transform: translateY(-2px); box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
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
        .topbar-actions { display: flex; align-items: center; gap: 0.75rem; }
        
        .content { padding: 1.5rem; }
        @media (max-width: 768px) { .content { padding: 1rem; } }
        
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
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(1.05); opacity: 0.6; } }
        .welcome-content { position: relative; z-index: 1; }
        .welcome-banner h2 { font-size: 1.8rem; font-weight: 700; color: white; margin-bottom: 0.5rem; }
        @media (max-width: 768px) { .welcome-banner h2 { font-size: 1.3rem; } }
        .welcome-banner p { color: rgba(255,255,255,0.9); font-size: 0.95rem; margin-bottom: 1.5rem; }
        .welcome-stats { display: flex; flex-wrap: wrap; gap: 1rem; }
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
        
        .subject-selector {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .subject-selector h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .view-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            background: white;
            color: var(--gray);
            border: 1px solid var(--border);
        }
        .view-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }
        .view-btn:hover:not(.active) {
            background: var(--light);
            border-color: var(--primary-light);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .stat-card .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
        }
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--gray);
        }
        
        .attendance-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .attendance-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .bulk-actions {
            background: var(--light);
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .bulk-btn {
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }
        .bulk-present { background: #10b981; color: white; }
        .bulk-absent { background: #ef4444; color: white; }
        .bulk-late { background: #f59e0b; color: white; }
        .bulk-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        
        .attendance-table { width: 100%; font-size: 0.85rem; border-collapse: collapse; }
        .attendance-table th {
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            padding: 1rem 0.75rem;
            white-space: nowrap;
            background: var(--light);
        }
        .attendance-table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .attendance-table tr:hover {
            background: rgba(79,70,229,0.02);
        }
        
        .student-name-cell {
            font-weight: 600;
            color: var(--dark);
        }
        .reg-no-cell {
            font-family: monospace;
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        .status-present { background: rgba(16,185,129,0.1); color: #10b981; }
        .status-absent { background: rgba(239,68,68,0.1); color: #ef4444; }
        .status-late { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .status-badge:hover { transform: scale(1.05); }
        
        .status-option {
            display: inline-block;
            margin-right: 0.5rem;
            cursor: pointer;
        }
        .status-option input[type="radio"] {
            display: none;
        }
        .status-option input[type="radio"]:checked + .status-badge {
            box-shadow: 0 0 0 2px white, 0 0 0 4px currentColor;
            transform: scale(1.05);
        }
        
        .remarks-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.75rem;
            transition: var(--transition);
        }
        .remarks-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(79,70,229,0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        
        .month-view { overflow-x: auto; }
        .month-table {
            min-width: 800px;
            font-size: 0.75rem;
            border-collapse: collapse;
        }
        .month-table th, .month-table td {
            text-align: center;
            padding: 0.5rem;
            border: 1px solid var(--border);
        }
        .month-table th {
            background: var(--light);
            font-weight: 600;
        }
        .month-table .present-cell { background: rgba(16,185,129,0.15); color: #10b981; }
        .month-table .absent-cell { background: rgba(239,68,68,0.15); color: #ef4444; }
        .month-table .late-cell { background: rgba(245,158,11,0.15); color: #f59e0b; }
        
        .attendance-percent {
            font-weight: 700;
            font-size: 0.85rem;
        }
        .percent-high { color: #10b981; }
        .percent-medium { color: #f59e0b; }
        .percent-low { color: #ef4444; }
        
        .alert-custom {
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
        }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); border-left: 4px solid var(--success); }
        .alert-error { background: rgba(239,68,68,0.1); color: var(--danger); border-left: 4px solid var(--danger); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h5 { font-size: 1.2rem; font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--gray); font-size: 0.9rem; }
        
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
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
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
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        
        .date-input, .month-input {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .attendance-header { flex-direction: column; align-items: flex-start; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .attendance-table th, .attendance-table td { font-size: 0.7rem; padding: 0.5rem; }
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
            <a href="attendance.php" class="active"> <i class="fas fa-calendar-check"></i> <span>Attendance</span> </a>
            <a href="results.php"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>
            <a href="students.php"> <i class="fas fa-user-graduate"></i> <span>My Students</span> </a>
            <div class="menu-title">RESOURCES</div>
            <a href="syllabus.php"> <i class="fas fa-list-alt"></i> <span>Syllabus</span> </a>
            <a href="timetable.php"> <i class="fas fa-clock"></i> <span>Time Table</span> </a>
            <a href="profile.php"> <i class="fas fa-user"></i> <span>Profile</span> </a>
            <a href="settings.php"> <i class="fas fa-cog"></i> <span>Settings</span> </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="menu-toggle" onclick="toggleSidebar()"> <i class="fas fa-bars"></i> </button>
                <span class="page-title"><i class="fas fa-calendar-check me-2" style="color: var(--primary);"></i>Attendance Management</span>
            </div>
            <div class="topbar-actions">
                <div class="quick-dropdown">
                    <button class="quick-add-btn" id="quickAddBtn">
                        <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
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
                    <div class="teacher-avatar" style="overflow: hidden;">
                        <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                            <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($teacher_name ?? $_SESSION['username'] ?? 'T', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="teacher-info">
                        <div class="teacher-name"><?php echo htmlspecialchars($teacher_name); ?></div>
                        <div class="teacher-role">Teacher</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #666;"></i>
                </div>
                <a href="../logout.php" class="logout-btn"> <i class="fas fa-sign-out-alt"></i> <span>Logout</span> </a>
            </div>
        </div>

        <div class="content">
            <div class="welcome-banner" data-aos="fade-up">
                <div class="welcome-content">
                    <h2>📋 Attendance Management</h2>
                    <p>Mark and manage student attendance for your subjects.</p>
                </div>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Subject Selection -->
            <div class="subject-selector" data-aos="fade-up">
                <h4><i class="fas fa-book"></i> Select Subject</h4>
                <form method="get" action="attendance.php">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Select a subject --</option>
                                <?php foreach ($teacher_subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo ($selected_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (Sem ' . $subject['semester'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="date" class="form-control date-input" value="<?php echo $selected_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn-save w-100">
                                <i class="fas fa-arrow-right"></i> Load Class
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selected_subject_id > 0 && !empty($students)): ?>
                <!-- View Toggle -->
                <div class="view-toggle" data-aos="fade-up" data-aos-delay="50">
                    <a href="?subject_id=<?php echo $selected_subject_id; ?>&date=<?php echo $selected_date; ?>&view=daily" class="view-btn <?php echo ($view_mode == 'daily') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day"></i> Daily View
                    </a>
                    <a href="?subject_id=<?php echo $selected_subject_id; ?>&month=<?php echo $selected_month; ?>&view=monthly" class="view-btn <?php echo ($view_mode == 'monthly') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Monthly View
                    </a>
                </div>

                <?php if ($view_mode == 'daily'): ?>
                    <!-- Stats Cards -->
                    <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(79,70,229,0.1);"><i class="fas fa-users" style="color: var(--primary);"></i></div>
                            <div class="stat-number"><?php echo count($students); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(16,185,129,0.1);"><i class="fas fa-check-circle" style="color: #10b981;"></i></div>
                            <div class="stat-number"><?php echo $total_present_today; ?></div>
                            <div class="stat-label">Present</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(239,68,68,0.1);"><i class="fas fa-times-circle" style="color: #ef4444;"></i></div>
                            <div class="stat-number"><?php echo $total_absent_today; ?></div>
                            <div class="stat-label">Absent</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(245,158,11,0.1);"><i class="fas fa-clock" style="color: #f59e0b;"></i></div>
                            <div class="stat-number"><?php echo $total_late_today; ?></div>
                            <div class="stat-label">Late</div>
                        </div>
                    </div>

                    <!-- Attendance Form - FIXED TABLE COLUMNS -->
                    <div class="attendance-card" data-aos="fade-up" data-aos-delay="150">
                        <div class="attendance-header">
                            <h3>
                                <i class="fas fa-users" style="color: var(--primary);"></i>
                                <?php echo htmlspecialchars($subject_name); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($subject_code); ?>)</small>
                            </h3>
                            <div>
                                <span class="badge bg-info"><?php echo date('l, d M Y', strtotime($selected_date)); ?></span>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="bulk-actions">
                            <span class="text-muted small"><i class="fas fa-bolt"></i> Bulk Actions:</span>
                            <button type="button" class="bulk-btn bulk-present" onclick="setAllStatus('present')"><i class="fas fa-check"></i> All Present</button>
                            <button type="button" class="bulk-btn bulk-absent" onclick="setAllStatus('absent')"><i class="fas fa-times"></i> All Absent</button>
                            <button type="button" class="bulk-btn bulk-late" onclick="setAllStatus('late')"><i class="fas fa-clock"></i> All Late</button>
                        </div>

                        <form method="post" action="attendance.php" id="attendanceForm">
                            <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                            <input type="hidden" name="save_attendance" value="1">
                            
                            <div class="table-responsive">
                                <table class="attendance-table" id="attendanceTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%">#</th>
                                            <th style="width: 15%">Registration No</th>
                                            <th style="width: 25%">Student Name</th>
                                            <th style="width: 25%">Status</th>
                                            <th style="width: 30%">Remarks</th>
                                        </tr
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($students as $student): 
                                            $current_status = $attendance_records[$student['id']]['status'] ?? 'absent';
                                            $current_remark = $attendance_records[$student['id']]['remarks'] ?? '';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td class="reg-no-cell"><?php echo htmlspecialchars($student['registration_no']); ?></td>
                                            <td class="student-name-cell"><?php echo htmlspecialchars($student['Name']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <label class="status-option">
                                                        <input type="radio" name="status[<?php echo $student['id']; ?>]" value="present" <?php echo ($current_status == 'present') ? 'checked' : ''; ?>>
                                                        <span class="status-badge status-present"><i class="fas fa-check-circle"></i> Present</span>
                                                    </label>
                                                    <label class="status-option">
                                                        <input type="radio" name="status[<?php echo $student['id']; ?>]" value="absent" <?php echo ($current_status == 'absent') ? 'checked' : ''; ?>>
                                                        <span class="status-badge status-absent"><i class="fas fa-times-circle"></i> Absent</span>
                                                    </label>
                                                    <label class="status-option">
                                                        <input type="radio" name="status[<?php echo $student['id']; ?>]" value="late" <?php echo ($current_status == 'late') ? 'checked' : ''; ?>>
                                                        <span class="status-badge status-late"><i class="fas fa-clock"></i> Late</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="remarks-input" placeholder="Add remarks..." value="<?php echo htmlspecialchars($current_remark); ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Attendance
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Monthly View -->
                    <div class="attendance-card" data-aos="fade-up" data-aos-delay="150">
                        <div class="attendance-header">
                            <h3>
                                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                                Monthly Attendance Report - <?php echo date('F Y', strtotime($selected_month)); ?>
                            </h3>
                            <div>
                                <form method="get" action="attendance.php" class="d-flex gap-2">
                                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                                    <input type="hidden" name="view" value="monthly">
                                    <input type="month" name="month" class="month-input" value="<?php echo $selected_month; ?>" onchange="this.form.submit()">
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive month-view">
                            <table class="month-table">
                                <thead>
                                    <tr>
                                        <th style="min-width: 150px;">Student Name</th>
                                        <th style="min-width: 100px;">Reg No</th>
                                        <?php foreach ($month_dates as $date): ?>
                                            <th><?php echo date('d', strtotime($date)); ?></th>
                                        <?php endforeach; ?>
                                        <th>P</th><th>A</th><th>L</th>
                                        <th>%</th>
                                    </tr
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        $present = 0;
                                        $absent = 0;
                                        $late = 0;
                                        foreach ($month_dates as $date) {
                                            $status = $monthly_attendance[$student['id']][$date] ?? '';
                                            if ($status == 'present') $present++;
                                            elseif ($status == 'absent') $absent++;
                                            elseif ($status == 'late') $late++;
                                        }
                                        $total = $present + $absent + $late;
                                        $percent = $total > 0 ? round(($present / $total) * 100) : 0;
                                        $percent_class = $percent >= 75 ? 'percent-high' : ($percent >= 50 ? 'percent-medium' : 'percent-low');
                                    ?>
                                    <tr>
                                        <td class="text-start"><strong><?php echo htmlspecialchars($student['Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['registration_no']); ?></td>
                                        <?php foreach ($month_dates as $date):
                                            $status = $monthly_attendance[$student['id']][$date] ?? '';
                                            $cell_class = '';
                                            if ($status == 'present') $cell_class = 'present-cell';
                                            elseif ($status == 'absent') $cell_class = 'absent-cell';
                                            elseif ($status == 'late') $cell_class = 'late-cell';
                                        ?>
                                            <td class="<?php echo $cell_class; ?>">
                                                <?php if ($status == 'present'): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php elseif ($status == 'absent'): ?>
                                                    <i class="fas fa-times-circle"></i>
                                                <?php elseif ($status == 'late'): ?>
                                                    <i class="fas fa-clock"></i>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><strong><?php echo $present; ?></strong></td>
                                        <td><?php echo $absent; ?></td>
                                        <td><?php echo $late; ?></td>
                                        <td><span class="attendance-percent <?php echo $percent_class; ?>"><?php echo $percent; ?>%</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($selected_subject_id > 0): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-users-slash"></i>
                    <h5>No Students Found</h5>
                    <p>No students are enrolled in this subject for the selected semester.</p>
                </div>
            <?php elseif (empty($teacher_subjects)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-book"></i>
                    <h5>No Subjects Assigned</h5>
                    <p>You haven't been assigned any subjects yet. Please contact the administrator.</p>
                </div>
            <?php else: ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-arrow-left"></i>
                    <h5>Select a Subject</h5>
                    <p>Please select a subject from the dropdown above to mark attendance.</p>
                </div>
            <?php endif; ?>
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
                <?php echo strtoupper(substr($teacher_name ?? $_SESSION['username'] ?? 'T', 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="profile-dropdown-info">
            <h4><?php echo htmlspecialchars($teacher_name); ?></h4>
            <p><?php echo $teacher_email ?: 'teacher@studybuddyhub.com'; ?></p>
            <?php if ($teacher_branch): ?><p class="mt-1"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($teacher_branch); ?></p><?php endif; ?>
        </div>
    </div>
    <div class="profile-dropdown-menu">
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="classes.php"><i class="fas fa-book"></i> My Classes</a>
        <a href="students.php"><i class="fas fa-user-graduate"></i> My Students</a>
        <a href="timetable.php"><i class="fas fa-clock"></i> Time Table</a>
        <hr>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- QUICK ADD MODAL -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-iframe">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="quickAddModalLabel"><i class="fas fa-plus-circle"></i> Loading...</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-iframe" id="quickAddModalBody">
                <div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading content...</p></div>
            </div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

    function toggleProfileMenu() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('profileDropdown');
        const profile = document.querySelector('.teacher-profile');
        if (profile && !profile.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    const quickAddBtn = document.getElementById('quickAddBtn');
    const quickMenu = document.getElementById('quickMenu');
    
    function toggleQuickMenu(e) { e.stopPropagation(); quickMenu.classList.toggle('active'); }
    function closeQuickMenu() { quickMenu.classList.remove('active'); }
    if (quickAddBtn) quickAddBtn.addEventListener('click', toggleQuickMenu);
    document.addEventListener('click', function(e) {
        if (quickMenu && !quickMenu.contains(e.target) && !quickAddBtn.contains(e.target)) closeQuickMenu();
    });

    function openInQuickModal(pageUrl, title, icon = 'fa-plus-circle') {
        const modal = new bootstrap.Modal(document.getElementById('quickAddModal'));
        document.getElementById('quickAddModalLabel').innerHTML = `<i class="fas ${icon}"></i> ${title}`;
        document.getElementById('quickAddModalBody').innerHTML = `<iframe src="${pageUrl}" style="width: 100%; height: 75vh; border: none;" title="${title}"></iframe>`;
        modal.show();
        closeQuickMenu();
    }

    document.querySelectorAll('.quick-menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const pageUrl = this.getAttribute('data-page');
            const title = this.getAttribute('data-title');
            const icon = this.getAttribute('data-icon') || 'fa-plus-circle';
            if (pageUrl) {
                openInQuickModal(pageUrl, title, icon);
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('profileDropdown').style.display = 'none';
        }
    });

    function setAllStatus(status) {
        const radios = document.querySelectorAll('input[type="radio"][name^="status["]');
        radios.forEach(radio => {
            if (radio.value === status) {
                radio.checked = true;
            }
        });
        
        Swal.fire({
            icon: 'success',
            title: 'Bulk Action Applied',
            text: `All students marked as ${status.toUpperCase()}`,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
    }

    <?php if ($view_mode == 'daily' && !empty($students)): ?>
    $(document).ready(function() {
        // Destroy existing DataTable if any
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }
        
        // Initialize DataTable
        $('#attendanceTable').DataTable({
            pageLength: 20,
            order: [[2, 'asc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ students",
                infoEmpty: "Showing 0 to 0 of 0 students",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Prev"
                }
            },
            columnDefs: [
                { orderable: false, targets: [0, 3, 4] }
            ]
        });
    });
    <?php endif; ?>

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