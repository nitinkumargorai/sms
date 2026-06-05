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

// Get student details from session
$student_name = $_SESSION['username'];
$student_email = $_SESSION['email'] ?? '';
$student_id = $_SESSION['student_id'] ?? 0;

// Get pending count for sidebar notifications
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

// Fetch student details from admission table
if (!empty($student_email)) {
    $student_query = mysqli_query($data, "SELECT * FROM admission WHERE Email='$student_email'");
    if ($student_query && $student_row = mysqli_fetch_assoc($student_query)) {
        $student_id = $student_row['id'];
        $student_name = $student_row['Name'];
        $student_reg_no = $student_row['registration_no'] ?? '';
        $student_branch = $student_row['Branch'] ?? '';
        $student_semester = $student_row['Semester'] ?? 1;
        $student_mobile = $student_row['mobile'] ?? '';
        $student_email = $student_row['Email'];
        
        // Update session - THIS IS PHP CODE, NOT HTML!
        $_SESSION['student_id'] = $student_id;
        $_SESSION['student_branch'] = $student_branch;
        $_SESSION['student_semester'] = $student_semester;
    }
}

// ============================================
// DYNAMIC STATS FROM DATABASE
// ============================================

// 1. Total Subjects for this student's branch + semester
$total_subjects = 0;
$completed_subjects = 0;
$ongoing_subjects = 0;
$subject_progress = 0;

if (!empty($student_branch) && $student_semester > 0) {
    $subject_query = mysqli_query($data, "SELECT * FROM subjects WHERE branch='$student_branch' AND semester='$student_semester'");
    if ($subject_query) {
        $total_subjects = mysqli_num_rows($subject_query);
        
        // Get completed subjects (those with results)
        if ($student_id > 0) {
            $completed_query = mysqli_query($data, "SELECT COUNT(DISTINCT subject_id) as cnt FROM results WHERE student_id='$student_id'");
            if ($completed_query && $comp_row = mysqli_fetch_assoc($completed_query)) {
                $completed_subjects = (int)$comp_row['cnt'];
            }
        }
        $ongoing_subjects = $total_subjects - $completed_subjects;
        $subject_progress = $total_subjects > 0 ? round(($completed_subjects / $total_subjects) * 100) : 0;
    }
}

// 2. Pending Assignments count
$pending_assignments = 0;
if ($student_id > 0) {
    $pending_query = mysqli_query($data, "
        SELECT COUNT(*) as cnt FROM submissions 
        WHERE student_id='$student_id' AND (status='submitted' OR status='late')
    ");
    if ($pending_query && $pending_row = mysqli_fetch_assoc($pending_query)) {
        $pending_assignments = (int)$pending_row['cnt'];
    }
}

// Also count assignments not yet submitted
$total_assignments = 0;
$assignments_query = mysqli_query($data, "
    SELECT COUNT(*) as cnt FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    WHERE s.branch='$student_branch' AND s.semester='$student_semester'
");
if ($assignments_query && $ass_row = mysqli_fetch_assoc($assignments_query)) {
    $total_assignments = (int)$ass_row['cnt'];
}

// Get submissions count
$submitted_count = 0;
$submitted_query = mysqli_query($data, "
    SELECT COUNT(*) as cnt FROM submissions 
    WHERE student_id='$student_id' AND (status='submitted' OR status='graded')
");
if ($submitted_query && $sub_row = mysqli_fetch_assoc($submitted_query)) {
    $submitted_count = (int)$sub_row['cnt'];
}

$pending_to_submit = max(0, $total_assignments - $submitted_count);

// 3. Attendance stats
$attendance_pct = 0;
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$total_classes = 0;

if ($student_id > 0) {
    $attendance_query = mysqli_query($data, "
        SELECT 
            SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_cnt,
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_cnt,
            SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_cnt,
            COUNT(*) as total
        FROM attendance WHERE student_id='$student_id'
    ");
    if ($attendance_query && $att_row = mysqli_fetch_assoc($attendance_query)) {
        $present_count = (int)($att_row['present_cnt'] ?? 0);
        $absent_count = (int)($att_row['absent_cnt'] ?? 0);
        $late_count = (int)($att_row['late_cnt'] ?? 0);
        $total_classes = (int)($att_row['total'] ?? 0);
        
        // Present + Late are considered present for percentage
        $effective_present = $present_count + $late_count;
        $attendance_pct = $total_classes > 0 ? round(($effective_present / $total_classes) * 100) : 0;
    }
}

// 4. Results/Average Marks
$average_marks = 0;
$total_exams = 0;
if ($student_id > 0) {
    $marks_query = mysqli_query($data, "
        SELECT AVG((marks/max_marks)*100) as avg_marks, COUNT(*) as total
        FROM results WHERE student_id='$student_id'
    ");
    if ($marks_query && $marks_row = mysqli_fetch_assoc($marks_query)) {
        $average_marks = round($marks_row['avg_marks'] ?? 0);
        $total_exams = (int)($marks_row['total'] ?? 0);
    }
}

// 5. Recent Activity from DB
$recent_activities = [];

// Recent materials
$materials_query = mysqli_query($data, "
    SELECT m.title, s.subject_name, m.upload_date, 'material' as type, m.created_at
    FROM materials m 
    JOIN subjects s ON s.id = m.subject_id
    WHERE s.branch='$student_branch' AND s.semester='$student_semester'
    ORDER BY m.id DESC LIMIT 3
");
if ($materials_query) {
    while ($row = mysqli_fetch_assoc($materials_query)) {
        $row['description'] = 'New study material uploaded';
        $recent_activities[] = $row;
    }
}

// Recent assignments
$assignments_recent_query = mysqli_query($data, "
    SELECT a.title, s.subject_name, a.created_at, 'assignment' as type, a.due_date
    FROM assignments a 
    JOIN subjects s ON s.id = a.subject_id
    WHERE s.branch='$student_branch' AND s.semester='$student_semester'
    ORDER BY a.id DESC LIMIT 3
");
if ($assignments_recent_query) {
    while ($row = mysqli_fetch_assoc($assignments_recent_query)) {
        $row['upload_date'] = $row['created_at'];
        $row['description'] = 'New assignment created';
        $recent_activities[] = $row;
    }
}

// Recent attendance
$attendance_recent_query = mysqli_query($data, "
    SELECT s.subject_name, a.date as upload_date, a.status, 'attendance' as type
    FROM attendance a 
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id='$student_id'
    ORDER BY a.date DESC LIMIT 2
");
if ($attendance_recent_query) {
    while ($row = mysqli_fetch_assoc($attendance_recent_query)) {
        $status_text = ucfirst($row['status']);
        $row['title'] = $status_text;
        $row['description'] = 'Attendance marked as ' . $status_text;
        $recent_activities[] = $row;
    }
}

// Sort by date desc and limit to 5
usort($recent_activities, function($a, $b) {
    $date_a = $a['upload_date'] ?? ($a['created_at'] ?? '2000-01-01');
    $date_b = $b['upload_date'] ?? ($b['created_at'] ?? '2000-01-01');
    return strtotime($date_b) - strtotime($date_a);
});
$recent_activities = array_slice($recent_activities, 0, 5);

// 6. Get upcoming deadlines
$upcoming_deadlines = [];
$deadlines_query = mysqli_query($data, "
    SELECT a.id, a.title, a.due_date, a.due_time, s.subject_name, a.total_marks
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    WHERE s.branch='$student_branch' AND s.semester='$student_semester'
    AND a.due_date >= CURDATE()
    AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id='$student_id')
    ORDER BY a.due_date ASC LIMIT 5
");
if ($deadlines_query) {
    while ($row = mysqli_fetch_assoc($deadlines_query)) {
        $upcoming_deadlines[] = $row;
    }
}

// Notification count (pending assignments + other notifications)
$notification_count = $pending_to_submit + $pending_count;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Student Dashboard | StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Custom Scrollbar */
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
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
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

        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .analytics-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .analytics-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border);
        }
        .analytics-header h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .analytics-header i {
            font-size: 1.2rem;
            color: var(--primary);
        }
        .canvas-container {
            position: relative;
            height: 220px;
        }

        /* Progress Cards */
        .progress-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .progress-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .progress-header h5 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        .progress-percent {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
        }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
        }
        .progress-details {
            display: flex;
            justify-content: space-between;
            color: var(--gray);
            font-size: 0.75rem;
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .section-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .activity-item:hover {
            background: var(--light);
            border-radius: 12px;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(79,70,229,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .activity-icon i { color: var(--primary); font-size: 1.1rem; }
        .activity-content { flex: 1; }
        .activity-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }
        .activity-time {
            font-size: 0.7rem;
            color: var(--gray);
        }

        /* Deadline List */
        .deadline-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .deadline-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .deadline-item:last-child { border-bottom: none; }
        .deadline-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark);
        }
        .deadline-subject {
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 0.2rem;
        }
        .deadline-date {
            font-size: 0.75rem;
            color: var(--warning);
        }
        .deadline-badge {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
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
            .stats-grid { gap: 1rem; }
            .analytics-grid { gap: 1rem; }
            .progress-section { gap: 1rem; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
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
            <a href="home.php" class="active"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

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
            <a href="settings.php"> <i class="fas fa-cog"></i> <span>Settings</span> </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="menu-toggle" onclick="toggleSidebar()"> <i class="fas fa-bars"></i> </button>
                <span class="page-title"><i class="fas fa-graduation-cap me-2" style="color: var(--primary);"></i>Dashboard</span>
            </div>
            <div class="topbar-actions">
                <div class="notification-badge" onclick="viewNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="badge-count"><?php echo $notification_count; ?></span>
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
                    <h2>
                        👋 Welcome back, <?php echo htmlspecialchars($student_name); ?>!
                    </h2>
                    <p>Track your academic progress and stay updated with your courses.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-layer-group"></i>
                            <span>Semester <?php echo $student_semester; ?></span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-code-branch"></i>
                            <span><?php echo htmlspecialchars($student_branch ?: 'Not Assigned'); ?></span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo $subject_progress; ?>% Complete</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <a href="subjects.php" class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Total Subjects</div>
                </a>

                <a href="assignments.php" class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-number"><?php echo $pending_to_submit; ?></div>
                    <div class="stat-label">Pending Assignments</div>
                </a>

                <a href="attendance.php" class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $attendance_pct; ?>%</div>
                    <div class="stat-label">Attendance</div>
                </a>

                <a href="results.php" class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo $average_marks; ?>%</div>
                    <div class="stat-label">Average Marks</div>
                </a>
            </div>

            <!-- ANALYTICS SECTION -->
            <div class="analytics-grid" data-aos="fade-up" data-aos-delay="100">
                <!-- Attendance Chart -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h5><i class="fas fa-chart-pie"></i> Attendance Overview</h5>
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="canvas-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <div class="small text-muted">Present</div>
                            <div class="fw-bold text-success"><?php echo $present_count; ?></div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Absent</div>
                            <div class="fw-bold text-danger"><?php echo $absent_count; ?></div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Late</div>
                            <div class="fw-bold text-warning"><?php echo $late_count; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h5><i class="fas fa-chart-line"></i> Academic Performance</h5>
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="canvas-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <span class="badge bg-primary"><?php echo $total_exams; ?> Exams Taken</span>
                        <span class="badge bg-success ms-2">Avg: <?php echo $average_marks; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- PROGRESS SECTION -->
            <div class="progress-section" data-aos="fade-up" data-aos-delay="150">
                <div class="progress-card">
                    <div class="progress-header">
                        <h5><i class="fas fa-chart-pie me-2" style="color: var(--primary);"></i>Subject Progress</h5>
                        <span class="progress-percent"><?php echo $subject_progress; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $subject_progress; ?>%;"></div>
                    </div>
                    <div class="progress-details">
                        <span><?php echo $completed_subjects; ?>/<?php echo $total_subjects; ?> completed</span>
                        <span><?php echo $ongoing_subjects; ?> ongoing</span>
                    </div>
                </div>

                <div class="progress-card">
                    <div class="progress-header">
                        <h5><i class="fas fa-tasks me-2" style="color: var(--warning);"></i>Assignment Completion</h5>
                        <span class="progress-percent"><?php echo $total_assignments > 0 ? round(($submitted_count / $total_assignments) * 100) : 0; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $total_assignments > 0 ? round(($submitted_count / $total_assignments) * 100) : 0; ?>%; background: linear-gradient(90deg, var(--warning), #f97316);"></div>
                    </div>
                    <div class="progress-details">
                        <span><?php echo $submitted_count; ?> submitted</span>
                        <span><?php echo $pending_to_submit; ?> pending</span>
                    </div>
                </div>
            </div>

            <!-- RECENT ACTIVITY & UPCOMING DEADLINES -->
            <div class="row g-4" data-aos="fade-up" data-aos-delay="200">
                <div class="col-lg-7">
                    <div class="analytics-card">
                        <div class="section-header" style="margin-bottom: 1rem;">
                            <h3><i class="fas fa-history" style="color: var(--primary);"></i> Recent Activity</h3>
                        </div>
                        <ul class="activity-list">
                            <?php if (empty($recent_activities)): ?>
                                <li class="activity-item">
                                    <div class="activity-icon"><i class="fas fa-info-circle"></i></div>
                                    <div class="activity-content">
                                        <div class="activity-title">No recent activity</div>
                                        <div class="activity-time">Activities will appear here as they happen</div>
                                    </div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] == 'material'): ?>
                                            <i class="fas fa-file-pdf"></i>
                                        <?php elseif ($activity['type'] == 'assignment'): ?>
                                            <i class="fas fa-tasks"></i>
                                        <?php else: ?>
                                            <i class="fas fa-calendar-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php if ($activity['type'] == 'material'): ?>
                                                New Material: <?php echo htmlspecialchars($activity['title']); ?>
                                            <?php elseif ($activity['type'] == 'assignment'): ?>
                                                Assignment: <?php echo htmlspecialchars($activity['title']); ?>
                                            <?php else: ?>
                                                Attendance: <?php echo htmlspecialchars($activity['title'] ?? $activity['status'] ?? 'Marked'); ?>
                                            <?php endif; ?>
                                            - <?php echo htmlspecialchars($activity['subject_name']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo date('d M Y', strtotime($activity['upload_date'] ?? ($activity['created_at'] ?? 'now'))); ?>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="analytics-card">
                        <div class="section-header" style="margin-bottom: 1rem;">
                            <h3><i class="fas fa-calendar-alt" style="color: var(--warning);"></i> Upcoming Deadlines</h3>
                        </div>
                        <ul class="deadline-list">
                            <?php if (empty($upcoming_deadlines)): ?>
                                <li class="activity-item">
                                    <div class="activity-icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="activity-content">
                                        <div class="activity-title">No upcoming deadlines</div>
                                        <div class="activity-time">All caught up! 🎉</div>
                                    </div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($upcoming_deadlines as $deadline): 
                                    $days_left = ceil((strtotime($deadline['due_date']) - time()) / (60 * 60 * 24));
                                ?>
                                <li class="deadline-item">
                                    <div>
                                        <div class="deadline-title">📋 <?php echo htmlspecialchars($deadline['title']); ?></div>
                                        <div class="deadline-subject"><?php echo htmlspecialchars($deadline['subject_name']); ?></div>
                                        <div class="deadline-date">📅 Due: <?php echo date('d M Y', strtotime($deadline['due_date'])); ?></div>
                                    </div>
                                    <div class="deadline-badge">
                                        <?php if ($days_left == 0): ?>
                                            Today!
                                        <?php elseif ($days_left == 1): ?>
                                            Tomorrow
                                        <?php else: ?>
                                            <?php echo $days_left; ?> days left
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
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

    // Initialize Charts
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [<?php echo $present_count; ?>, <?php echo $absent_count; ?>, <?php echo $late_count; ?>],
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 11 } }
                }
            }
        }
    });

    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(performanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Average Score', 'Remaining'],
            datasets: [{
                data: [<?php echo $average_marks; ?>, <?php echo 100 - $average_marks; ?>],
                backgroundColor: ['#4f46e5', '#e2e8f0'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 11 } }
                }
            }
        }
    });
</script>

</body>
</html>