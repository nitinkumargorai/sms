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

// Fetch student details from database
if (!empty($student_email)) {
    $query = mysqli_query($data, "SELECT * FROM admission WHERE Email='$student_email'");
    if ($query && $row = mysqli_fetch_assoc($query)) {
        $student_id = $row['id'];
        $student_name = $row['Name'];
        $student_branch = $row['Branch'] ?? '';
        $student_semester = $row['Semester'] ?? 1;
        $student_reg_no = $row['registration_no'] ?? '';
        
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

/* FETCH RESULTS FROM DATABASE - LIVE DATA ONLY */
$results_by_semester = [];
$results_query = mysqli_query($data, "
    SELECT 
        r.id,
        r.marks,
        r.max_marks,
        r.grade,
        r.exam_type,
        r.exam_date,
        s.id as subject_id,
        s.subject_code,
        s.subject_name,
        s.credits,
        s.semester
    FROM results r
    JOIN subjects s ON s.id = r.subject_id
    WHERE r.student_id = '$student_id'
    ORDER BY s.semester ASC, s.subject_code ASC
");

if ($results_query && mysqli_num_rows($results_query) > 0) {
    while ($row = mysqli_fetch_assoc($results_query)) {
        $semester_key = (int) ($row['semester'] ?? 0);
        if (!isset($results_by_semester[$semester_key])) {
            $results_by_semester[$semester_key] = [];
        }
        $results_by_semester[$semester_key][] = [
            'id' => $row['id'],
            'code' => $row['subject_code'],
            'name' => $row['subject_name'],
            'marks' => (int) $row['marks'],
            'max_marks' => (int) $row['max_marks'],
            'grade' => $row['grade'],
            'credit' => (int) ($row['credits'] ?? 0),
            'exam_type' => $row['exam_type'],
            'exam_date' => $row['exam_date']
        ];
    }
}

// Build semesters array from available results
$semesters = [];
if (!empty($results_by_semester)) {
    $max_semester = max(array_keys($results_by_semester));
    for ($i = 1; $i <= $max_semester; $i++) {
        $subjects = $results_by_semester[$i] ?? [];
        $status = $i < $student_semester ? 'completed' : ($i == $student_semester ? 'ongoing' : 'upcoming');
        
        // Calculate SGPA only if subjects exist
        $semester_total_points = 0;
        $semester_total_credits = 0;
        foreach ($subjects as $subject) {
            if ($subject['marks'] !== null) {
                $semester_total_points += ($subject['credit'] * getGradePoint($subject['marks']));
                $semester_total_credits += $subject['credit'];
            }
        }
        
        $semesters[] = [
            'name' => 'Semester ' . $i,
            'sgpa' => $semester_total_credits > 0 ? round($semester_total_points / $semester_total_credits, 2) : null,
            'status' => $status,
            'subjects' => $subjects
        ];
    }
}

// Calculate overall statistics - LIVE from actual results
$completed_semesters = array_filter($semesters, function($s) { return $s['status'] == 'completed'; });
$total_subjects = 0;
$total_marks = 0;
$total_max_marks = 0;
$total_credits = 0;
$grade_points = 0;

foreach ($completed_semesters as $semester) {
    foreach ($semester['subjects'] as $subject) {
        if ($subject['marks'] !== null) {
            $total_subjects++;
            $total_marks += $subject['marks'];
            $total_max_marks += $subject['max_marks'];
            $total_credits += $subject['credit'];
            $grade_points += $subject['credit'] * getGradePoint($subject['marks']);
        }
    }
}

$overall_percentage = $total_max_marks > 0 ? round(($total_marks / $total_max_marks) * 100) : 0;
$cgpa = $total_credits > 0 ? round($grade_points / $total_credits, 2) : 0;

// Calculate grade distribution from actual data
$grade_distribution = [
    'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
];
foreach ($completed_semesters as $semester) {
    foreach ($semester['subjects'] as $subject) {
        if ($subject['grade'] && isset($grade_distribution[$subject['grade']])) {
            $grade_distribution[$subject['grade']]++;
        }
    }
}
// Remove empty grades
$grade_distribution = array_filter($grade_distribution, function($v) { return $v > 0; });

function getGradePoint($marks) {
    if ($marks >= 90) return 10;
    if ($marks >= 80) return 9;
    if ($marks >= 70) return 8;
    if ($marks >= 60) return 7;
    if ($marks >= 50) return 6;
    if ($marks >= 40) return 5;
    return 4;
}

function getGradeColor($grade) {
    if ($grade == 'A+' || $grade == 'A') return 'success';
    if ($grade == 'B+' || $grade == 'B') return 'primary';
    if ($grade == 'C') return 'warning';
    if ($grade == 'D') return 'danger';
    return 'secondary';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Results | Student - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Semester Tabs */
        .semester-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .semester-tab {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            color: var(--gray);
            border: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .semester-tab:hover {
            background: var(--light);
            color: var(--dark);
            border-color: var(--primary-light);
        }
        .semester-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }
        .semester-tab.ongoing {
            border-left: 4px solid var(--warning);
        }

        /* Result Card */
        .result-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .semester-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .semester-header h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sgpa-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .sgpa-good { background: rgba(16,185,129,0.1); color: var(--success); }
        .sgpa-average { background: rgba(245,158,11,0.1); color: var(--warning); }
        .sgpa-low { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-completed { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-ongoing { background: rgba(245,158,11,0.1); color: var(--warning); }

        /* Results Table */
        .table-responsive { overflow-x: auto; }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        .results-table th {
            background: var(--light);
            padding: 1rem 0.75rem;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            font-size: 0.8rem;
        }
        .results-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
            color: var(--gray);
        }
        .grade-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .grade-success { background: rgba(16,185,129,0.1); color: var(--success); }
        .grade-primary { background: rgba(79,70,229,0.1); color: var(--primary); }
        .grade-warning { background: rgba(245,158,11,0.1); color: var(--warning); }
        .grade-danger { background: rgba(239,68,68,0.1); color: var(--danger); }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .chart-header {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border);
        }
        .chart-header h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .chart-container {
            height: 250px;
            position: relative;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .metric-label {
            font-size: 0.7rem;
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
            .semester-tab { padding: 0.4rem 1rem; font-size: 0.75rem; }
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
            <a href="results.php" class="active"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>

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
                <span class="page-title"><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>Results</span>
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
                    <h2>📊 Academic Results</h2>
                    <p>Track your academic performance across all semesters.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-chart-line"></i>
                            <span>CGPA: <?php echo $cgpa; ?></span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-percent"></i>
                            <span><?php echo $overall_percentage; ?>% Overall</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?php echo $cgpa; ?></div>
                    <div class="stat-label">CGPA</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-number"><?php echo $overall_percentage; ?>%</div>
                    <div class="stat-label">Overall</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="stat-number"><?php echo count($completed_semesters); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <?php if (empty($semesters)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-chart-line"></i>
                    <h5>No Results Available</h5>
                    <p>Your results will appear here once they are published by the faculty.</p>
                </div>
            <?php else: ?>
                <!-- Semester Tabs -->
                <div class="semester-tabs" data-aos="fade-up" data-aos-delay="100">
                    <?php foreach ($semesters as $index => $semester): ?>
                    <button class="semester-tab <?php echo $semester['status']; ?> <?php echo $index == 0 ? 'active' : ''; ?>" onclick="showSemester(<?php echo $index; ?>)">
                        <?php echo $semester['name']; ?>
                        <?php if ($semester['status'] == 'ongoing'): ?>
                            <i class="fas fa-spinner fa-pulse ms-1"></i>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Semester Results -->
                <?php foreach ($semesters as $index => $semester): ?>
                <div class="result-card" id="semester-<?php echo $index; ?>" style="display: <?php echo $index == 0 ? 'block' : 'none'; ?>;" data-aos="fade-up" data-aos-delay="150">
                    <div class="semester-header">
                        <h4><i class="fas fa-calendar-alt"></i> <?php echo $semester['name']; ?> Results</h4>
                        <div>
                            <?php if ($semester['status'] == 'ongoing'): ?>
                                <span class="status-badge status-ongoing"><i class="fas fa-spinner fa-pulse"></i> In Progress</span>
                            <?php else: ?>
                                <span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php endif; ?>
                            <?php if ($semester['sgpa']): 
                                $sgpa_class = 'sgpa-good';
                                if ($semester['sgpa'] < 6) $sgpa_class = 'sgpa-low';
                                elseif ($semester['sgpa'] < 8) $sgpa_class = 'sgpa-average';
                            ?>
                                <span class="sgpa-badge <?php echo $sgpa_class; ?>">
                                    <i class="fas fa-star"></i> SGPA: <?php echo $semester['sgpa']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Credits</th>
                                    <th>Marks</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($semester['subjects'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No results available for this semester</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($semester['subjects'] as $subject): 
                                        $grade_class = 'grade-secondary';
                                        if ($subject['grade'] == 'A+' || $subject['grade'] == 'A') $grade_class = 'grade-success';
                                        else if ($subject['grade'] == 'B+' || $subject['grade'] == 'B') $grade_class = 'grade-primary';
                                        else if ($subject['grade'] == 'C') $grade_class = 'grade-warning';
                                        else if ($subject['grade'] == 'D') $grade_class = 'grade-danger';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td><?php echo $subject['credit']; ?></td>
                                        <td>
                                            <strong><?php echo $subject['marks']; ?>/<?php echo $subject['max_marks']; ?></strong>
                                            <br><small><?php echo round(($subject['marks'] / $subject['max_marks']) * 100); ?>%</small>
                                        </td>
                                        <td>
                                            <span class="grade-badge <?php echo $grade_class; ?>">
                                                <?php echo $subject['grade']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Charts Section -->
                <div class="charts-row" data-aos="fade-up" data-aos-delay="200">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5><i class="fas fa-chart-line"></i> SGPA Trend</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="sgpaChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h5><i class="fas fa-chart-pie"></i> Grade Distribution</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="gradeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="metrics-grid" data-aos="fade-up" data-aos-delay="250">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $cgpa; ?></div>
                        <div class="metric-label">Current CGPA</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $total_subjects; ?></div>
                        <div class="metric-label">Subjects Completed</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">
                            <?php 
                                $best_semester = 0;
                                foreach ($semesters as $s) {
                                    if ($s['sgpa'] && $s['sgpa'] > $best_semester) {
                                        $best_semester = $s['sgpa'];
                                    }
                                }
                                echo $best_semester;
                            ?>
                        </div>
                        <div class="metric-label">Best SGPA</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $overall_percentage; ?>%</div>
                        <div class="metric-label">Overall %</div>
                    </div>
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

    function showSemester(index) {
        <?php foreach ($semesters as $i => $s): ?>
        document.getElementById('semester-<?php echo $i; ?>').style.display = 'none';
        <?php endforeach; ?>
        
        document.getElementById('semester-' + index).style.display = 'block';
        
        const tabs = document.querySelectorAll('.semester-tab');
        tabs.forEach((tab, i) => {
            if (i === index) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
    }

    // SGPA Trend Chart - Live data
    const sgpaData = <?php echo json_encode(array_map(function($s) { 
        return $s['sgpa'] ?? null; 
    }, $semesters)); ?>;
    const sgpaLabels = <?php echo json_encode(array_map(function($s) { 
        return $s['name']; 
    }, $semesters)); ?>;

    if (sgpaData.length > 0) {
        const sgpaCtx = document.getElementById('sgpaChart').getContext('2d');
        new Chart(sgpaCtx, {
            type: 'line',
            data: {
                labels: sgpaLabels,
                datasets: [{
                    label: 'SGPA',
                    data: sgpaData,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'SGPA: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { stepSize: 2 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Grade Distribution Chart - Live data
    const gradeLabels = <?php echo json_encode(array_keys($grade_distribution)); ?>;
    const gradeData = <?php echo json_encode(array_values($grade_distribution)); ?>;
    const gradeColors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#6b7280'];

    if (gradeLabels.length > 0) {
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: gradeLabels,
                datasets: [{
                    data: gradeData,
                    backgroundColor: gradeColors.slice(0, gradeLabels.length),
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '65%'
            }
        });
    }
</script>

</body>
</html>