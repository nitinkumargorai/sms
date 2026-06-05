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
$teacher_email = $_SESSION['email'] ?? '';
$teacher_name = $_SESSION['username'];

// Fetch teacher data
$teacher_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);
$teacher_id = $teacher_data['id'] ?? 0;
$teacher_branch = $teacher_data['branch'] ?? '';
$teacher_mobile = $teacher_data['mobile'] ?? '';

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

// Get teacher's assigned subjects
$subjects_query = mysqli_query($data, "
    SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.semester, s.branch
    FROM teacher_subjects ts
    JOIN subjects s ON s.id = ts.subject_id
    WHERE ts.teacher_id = '$teacher_id'
    ORDER BY s.semester ASC, s.subject_name ASC
");

$assigned_subjects = [];
while ($subj = mysqli_fetch_assoc($subjects_query)) {
    $assigned_subjects[] = $subj;
}

// Get filter parameters
$selected_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($data, $_GET['search']) : '';

// Fetch students based on filters
$students = [];
$filter_subject_name = '';
$filter_semester = 0;
$total_students = 0;
$avg_attendance = 0;
$avg_performance = 0;
$pending_submissions = 0;

if ($selected_subject > 0) {
    // Get subject details
    $subject_detail_query = mysqli_query($data, "SELECT subject_name, semester, branch FROM subjects WHERE id = '$selected_subject'");
    $subject_detail = mysqli_fetch_assoc($subject_detail_query);
    if ($subject_detail) {
        $filter_subject_name = $subject_detail['subject_name'];
        $filter_semester = $subject_detail['semester'];
        
        // Build student query
        $student_sql = "SELECT a.*, u.last_login, u.profile_pic 
                        FROM admission a
                        LEFT JOIN user u ON a.user_id = u.id
                        WHERE a.Branch = '{$subject_detail['branch']}' 
                        AND a.Semester = '{$subject_detail['semester']}'";
        
        if (!empty($search_query)) {
            $student_sql .= " AND (a.Name LIKE '%$search_query%' 
                                 OR a.Email LIKE '%$search_query%')";
        }
        
        $student_sql .= " ORDER BY a.Name ASC";
        $student_result = mysqli_query($data, $student_sql);
        
        while ($student = mysqli_fetch_assoc($student_result)) {
            // Get attendance percentage for this student in this subject
            $att_sql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
                        FROM attendance 
                        WHERE student_id = '{$student['id']}' 
                        AND subject_id = '$selected_subject'";
            $att_result = mysqli_query($data, $att_sql);
            $att_data = mysqli_fetch_assoc($att_result);
            $attendance_percent = $att_data['total'] > 0 ? round(($att_data['present_count'] / $att_data['total']) * 100) : 0;
            
            // Get average marks for this student in this subject
            $marks_sql = "SELECT AVG(marks) as avg_marks, COUNT(*) as exam_count 
                          FROM results 
                          WHERE student_id = '{$student['id']}' 
                          AND subject_id = '$selected_subject'";
            $marks_result = mysqli_query($data, $marks_sql);
            $marks_data = mysqli_fetch_assoc($marks_result);
            $avg_marks = $marks_data['avg_marks'] ? round($marks_data['avg_marks'], 1) : 0;
            $exam_count = $marks_data['exam_count'] ?? 0;
            
            // Get pending assignments count
            $pending_sql = "SELECT COUNT(*) as pending 
                            FROM submissions s
                            JOIN assignments a ON s.assignment_id = a.id
                            WHERE s.student_id = '{$student['id']}' 
                            AND a.subject_id = '$selected_subject'
                            AND (s.status = 'submitted' OR s.status = 'late')";
            $pending_result = mysqli_query($data, $pending_sql);
            $pending_data = mysqli_fetch_assoc($pending_result);
            $pending_count_student = $pending_data['pending'] ?? 0;
            
            $student['attendance_percent'] = $attendance_percent;
            $student['avg_marks'] = $avg_marks;
            $student['exam_count'] = $exam_count;
            $student['pending_assignments'] = $pending_count_student;
            $students[] = $student;
        }
    }
}

// Get summary statistics
$total_students = count($students);
foreach ($students as $s) {
    $avg_attendance += $s['attendance_percent'];
    $avg_performance += $s['avg_marks'];
    $pending_submissions += $s['pending_assignments'];
}
$avg_attendance = $total_students > 0 ? round($avg_attendance / $total_students) : 0;
$avg_performance = $total_students > 0 ? round($avg_performance / $total_students, 1) : 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Students | Teacher - StudyBuddyHub</title>

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
            cursor: pointer;
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
            box-shadow: 0 4px 12px rgba(79,70,229,0.4);
        }
        
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
            overflow: hidden;
        }
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(79,70,229,0.1), rgba(79,70,229,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .stat-icon i { font-size: 1.5rem; color: var(--primary); }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        
        .subject-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        
        .subject-chip {
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            background: var(--light);
            color: var(--dark);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border);
        }
        
        .subject-chip:hover {
            background: rgba(79,70,229,0.1);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .subject-chip.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }
        
        .search-box {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .search-box input {
            flex: 1;
            padding: 0.7rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
            outline: none;
            transition: var(--transition);
            min-width: 200px;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        
        .btn-search {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        
        .btn-clear {
            padding: 0.7rem 1.5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: white;
            color: var(--gray);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-clear:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(239,68,68,0.05);
        }

        /* Students Grid - Card Layout */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .student-card {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            transition: var(--transition);
            border: 1px solid var(--border);
            position: relative;
            cursor: pointer;
        }
        
        .student-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .student-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .student-card-avatar {
            width: 65px;
            height: 65px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .student-card-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-card-info {
            flex: 1;
        }
        
        .student-card-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .student-card-reg {
            font-size: 0.7rem;
            color: var(--gray);
        }
        
        .student-card-stats {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            padding: 0.75rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        
        .student-stat {
            flex: 1;
            text-align: center;
        }
        
        .student-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .student-stat-label {
            font-size: 0.65rem;
            color: var(--gray);
            margin-top: 0.2rem;
        }
        
        .student-card-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .student-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        
        .student-action-btn-view {
            background: rgba(79,70,229,0.1);
            color: var(--primary);
        }
        .student-action-btn-view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .student-action-btn-marks {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }
        .student-action-btn-marks:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        
        .student-action-btn-attend {
            background: rgba(245,158,11,0.1);
            color: var(--warning);
        }
        .student-action-btn-attend:hover {
            background: var(--warning);
            color: white;
            transform: translateY(-2px);
        }
        
        .attendance-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .attendance-high { background: rgba(16,185,129,0.1); color: var(--success); }
        .attendance-medium { background: rgba(245,158,11,0.1); color: var(--warning); }
        .attendance-low { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .marks-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .marks-excellent { background: rgba(16,185,129,0.1); color: var(--success); }
        .marks-good { background: rgba(79,70,229,0.1); color: var(--primary); }
        .marks-average { background: rgba(245,158,11,0.1); color: var(--warning); }
        .marks-poor { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        .empty-state h5 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
            font-size: 0.9rem;
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
            overflow: hidden;
        }
        .profile-dropdown-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
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

        /* Modal */
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
        .message-content {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Quick Add Modal */
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
            .stats-grid { gap: 1rem; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .subject-chip { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
            .search-box input, .btn-search, .btn-clear { width: 100%; }
            .students-grid { grid-template-columns: 1fr; }
        }
        
        .btn-close-white { filter: brightness(0) invert(1); }
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
            <a href="students.php" class="active"> <i class="fas fa-users"></i> <span>My Students</span> </a>

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
                <span class="page-title"><i class="fas fa-users me-2" style="color: var(--primary);"></i>My Students</span>
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
                    <div class="teacher-avatar">
                        <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                            <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($teacher_name ?? 'T', 0, 1)); ?>
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
            <!-- Welcome Banner -->
            <div class="welcome-banner" data-aos="fade-up">
                <div class="welcome-content">
                    <h2>👨‍🎓 My Students</h2>
                    <p>View and manage students enrolled in your subjects.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-book"></i>
                            <span><?php echo count($assigned_subjects); ?> Subjects</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $total_students; ?> Students</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo $avg_performance; ?>% Avg Marks</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
                    <div class="stat-number"><?php echo count($assigned_subjects); ?></div>
                    <div class="stat-label">My Subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo $avg_performance; ?>%</div>
                    <div class="stat-label">Avg Performance</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $avg_attendance; ?>%</div>
                    <div class="stat-label">Avg Attendance</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <div class="subject-selector">
                    <?php foreach ($assigned_subjects as $subject): ?>
                    <a href="?subject_id=<?php echo $subject['id']; ?>" class="subject-chip <?php echo $selected_subject == $subject['id'] ? 'active' : ''; ?>">
                        <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($subject['subject_name']); ?> (Sem <?php echo $subject['semester']; ?>)
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($selected_subject > 0): ?>
                <form method="GET" action="">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                        <?php if (!empty($search_query)): ?>
                        <a href="?subject_id=<?php echo $selected_subject; ?>" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- Students Grid - Card Layout (No Table) -->
            <div class="students-grid-container" data-aos="fade-up" data-aos-delay="150">
                <?php if ($selected_subject == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h5>Select a Subject to View Students</h5>
                        <p>Please select a subject from above to see the list of students enrolled in that class.</p>
                    </div>
                <?php elseif (empty($students)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <h5>No Students Found</h5>
                        <p>No students are enrolled in <?php echo htmlspecialchars($filter_subject_name); ?> for Semester <?php echo $filter_semester; ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="students-grid">
                        <?php foreach ($students as $student): 
                            $attendance_class = $student['attendance_percent'] >= 75 ? 'attendance-high' : ($student['attendance_percent'] >= 60 ? 'attendance-medium' : 'attendance-low');
                            $marks_class = $student['avg_marks'] >= 80 ? 'marks-excellent' : ($student['avg_marks'] >= 60 ? 'marks-good' : ($student['avg_marks'] >= 40 ? 'marks-average' : 'marks-poor'));
                            $initials = strtoupper(substr($student['Name'], 0, 2));
                        ?>
                        <div class="student-card" data-aos="fade-up" data-aos-delay="50">
                            <div class="student-card-header">
                                <div class="student-card-avatar">
                                    <?php if (!empty($student['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $student['profile_pic'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($student['profile_pic']); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="student-card-info">
                                    <div class="student-card-name"><?php echo htmlspecialchars($student['Name']); ?></div>
                                    <div class="student-card-reg"><?php echo htmlspecialchars($student['registration_no']); ?></div>
                                </div>
                            </div>
                            
                            <div class="student-card-stats">
                                <div class="student-stat">
                                    <div class="student-stat-value"><?php echo $student['Semester']; ?></div>
                                    <div class="student-stat-label">Semester</div>
                                </div>
                                <div class="student-stat">
                                    <div class="student-stat-value">
                                        <span class="attendance-badge <?php echo $attendance_class; ?>">
                                            <?php echo $student['attendance_percent']; ?>%
                                        </span>
                                    </div>
                                    <div class="student-stat-label">Attendance</div>
                                </div>
                                <div class="student-stat">
                                    <div class="student-stat-value">
                                        <span class="marks-badge <?php echo $marks_class; ?>">
                                            <?php echo $student['avg_marks']; ?>%
                                        </span>
                                    </div>
                                    <div class="student-stat-label">Avg Marks</div>
                                </div>
                            </div>
                            
                            <div class="student-card-actions">
                                <button class="student-action-btn student-action-btn-view" onclick='viewStudentDetails(<?php echo json_encode($student); ?>, <?php echo $selected_subject; ?>)' title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="add_result.php?student_id=<?php echo $student['id']; ?>&subject_id=<?php echo $selected_subject; ?>" class="student-action-btn student-action-btn-marks" title="Add Results" onclick="openInQuickModal(this.href, 'Add Results', 'fa-chart-line'); return false;">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                                <a href="mark_attendance.php?student_id=<?php echo $student['id']; ?>&subject_id=<?php echo $selected_subject; ?>" class="student-action-btn student-action-btn-attend" title="Mark Attendance" onclick="openInQuickModal(this.href, 'Mark Attendance', 'fa-calendar-check'); return false;">
                                    <i class="fas fa-calendar-check"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Profile Dropdown Menu -->
<div class="profile-dropdown" id="profileDropdown">
    <div class="profile-dropdown-header">
        <div class="profile-dropdown-avatar">
            <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar">
            <?php else: ?>
                <?php echo strtoupper(substr($teacher_name ?? 'T', 0, 1)); ?>
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
        <a href="syllabus.php"><i class="fas fa-list-alt"></i> Syllabus</a>
        <a href="students.php"><i class="fas fa-users"></i> My Students</a>
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

    // Profile Dropdown
    function toggleProfileMenu() {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
        }
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('profileDropdown');
        const profile = document.querySelector('.teacher-profile');
        if (profile && !profile.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function(e) {
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

    // View Student Details in Modal
    function viewStudentDetails(student, subjectId) {
        let attendanceClass = '';
        let attendanceText = '';
        let marksClass = '';
        let marksText = '';
        
        if (student.attendance_percent >= 75) {
            attendanceClass = 'attendance-high';
            attendanceText = 'Good';
        } else if (student.attendance_percent >= 60) {
            attendanceClass = 'attendance-medium';
            attendanceText = 'Average';
        } else {
            attendanceClass = 'attendance-low';
            attendanceText = 'Poor';
        }
        
        if (student.avg_marks >= 80) {
            marksClass = 'marks-excellent';
            marksText = 'Excellent';
        } else if (student.avg_marks >= 60) {
            marksClass = 'marks-good';
            marksText = 'Good';
        } else if (student.avg_marks >= 40) {
            marksClass = 'marks-average';
            marksText = 'Average';
        } else {
            marksClass = 'marks-poor';
            marksText = 'Poor';
        }
        
        let semesterSuffix = '';
        if (student.Semester == 1) semesterSuffix = 'st';
        else if (student.Semester == 2) semesterSuffix = 'nd';
        else if (student.Semester == 3) semesterSuffix = 'rd';
        else semesterSuffix = 'th';
        
        let modalContent = `
            <div class="text-center mb-3">
                <div style="width: 80px; height: 80px; border-radius: 40px; background: linear-gradient(135deg, #4f46e5, #4338ca); margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    ${student.profile_pic ? 
                        `<img src="../uploads/profile_pics/${student.profile_pic}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">` :
                        `<span style="font-size: 2rem; color: white;">${student.Name.charAt(0)}</span>`
                    }
                </div>
                <h5 style="font-weight: 700; color: #1e293b;">${escapeHtml(student.Name)}</h5>
                <p style="color: #6b7280; font-size: 0.85rem;">${escapeHtml(student.Email)}</p>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-id-card"></i> Registration Number</div>
                <div class="detail-value">${escapeHtml(student.registration_no)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-code-branch"></i> Branch & Semester</div>
                <div class="detail-value">${escapeHtml(student.Branch)} - ${student.Semester}${semesterSuffix} Semester</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-calendar-check"></i> Attendance</div>
                <div class="detail-value">
                    <span class="attendance-badge ${attendanceClass}">${student.attendance_percent}% (${attendanceText})</span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-chart-line"></i> Average Marks</div>
                <div class="detail-value">
                    <span class="marks-badge ${marksClass}">${student.avg_marks}% (${marksText})</span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-tasks"></i> Pending Assignments</div>
                <div class="detail-value">${student.pending_assignments}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-star"></i> Grade Summary</div>
                <div class="detail-value">
                    ${student.avg_marks >= 75 ? '<span class="badge bg-success">Pass with Distinction</span>' : 
                      student.avg_marks >= 60 ? '<span class="badge bg-info">First Class</span>' :
                      student.avg_marks >= 50 ? '<span class="badge bg-warning text-dark">Second Class</span>' :
                      student.avg_marks >= 40 ? '<span class="badge bg-warning text-dark">Pass Class</span>' :
                      '<span class="badge bg-danger">Needs Improvement</span>'}
                </div>
            </div>
        `;
        
        Swal.fire({
            title: 'Student Details',
            html: modalContent,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Close',
            width: '550px',
            customClass: {
                popup: 'rounded-4'
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