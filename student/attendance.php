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

/* FETCH ATTENDANCE DATA FROM DATABASE */
$attendance_data = [];
$attendance_query = mysqli_query($data, "
    SELECT 
        s.id as subject_id,
        s.subject_name,
        s.subject_code,
        COALESCE(t.name, 'Not Assigned') AS faculty,
        COUNT(*) AS total_classes,
        SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) AS attended
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    LEFT JOIN teacher t ON t.id = a.marked_by
    WHERE a.student_id = '$student_id'
    GROUP BY a.subject_id, s.subject_name, s.subject_code, t.name
    ORDER BY s.subject_code ASC
");

$colors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark', 'primary'];
$icons = [
    'Database' => 'fa-database',
    'Network' => 'fa-network-wired',
    'Operating' => 'fa-windows',
    'Python' => 'fa-python',
    'Web' => 'fa-code',
    'Math' => 'fa-calculator',
    'Physics' => 'fa-atom',
    'Chemistry' => 'fa-flask'
];

$index = 0;
if ($attendance_query && mysqli_num_rows($attendance_query) > 0) {
    while ($row = mysqli_fetch_assoc($attendance_query)) {
        $percentage = (int) ($row['total_classes'] > 0 ? round(($row['attended'] / $row['total_classes']) * 100) : 0);
        
        // Determine color and icon
        $color = $colors[$index % count($colors)];
        $icon = 'fa-book';
        foreach ($icons as $key => $val) {
            if (stripos($row['subject_name'], $key) !== false) {
                $icon = $val;
                if ($key == 'Network') $color = 'primary';
                elseif ($key == 'Operating') $color = 'warning';
                elseif ($key == 'Python') $color = 'danger';
                elseif ($key == 'Web') $color = 'info';
                elseif ($key == 'Database') $color = 'success';
                break;
            }
        }
        
        $row['percentage'] = $percentage;
        $row['color'] = $color;
        $row['icon'] = $icon;
        $attendance_data[] = $row;
        $index++;
    }
}

// Calculate overall attendance
$total_classes = array_sum(array_column($attendance_data, 'total_classes'));
$total_attended = array_sum(array_column($attendance_data, 'attended'));
$overall_percentage = $total_classes > 0 ? round(($total_attended / $total_classes) * 100) : 0;

// Calculate monthly attendance trend
$monthly_attendance = [];
$monthly_query = mysqli_query($data, "
    SELECT 
        DATE_FORMAT(date, '%b') AS month_label,
        COUNT(*) AS total_count,
        SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) AS attended_count
    FROM attendance
    WHERE student_id = '$student_id'
    GROUP BY YEAR(date), MONTH(date), DATE_FORMAT(date, '%b')
    ORDER BY MIN(date) DESC
    LIMIT 6
");

if ($monthly_query && mysqli_num_rows($monthly_query) > 0) {
    $monthly_data = [];
    while ($row = mysqli_fetch_assoc($monthly_query)) {
        $monthly_data[] = [
            'month' => $row['month_label'],
            'percentage' => (int) ($row['total_count'] > 0 ? round(($row['attended_count'] / $row['total_count']) * 100) : 0)
        ];
    }
    $monthly_attendance = array_reverse($monthly_data);
} else {
    // Sample data if no attendance records
    $monthly_attendance = [
        ['month' => 'Jan', 'percentage' => 0],
        ['month' => 'Feb', 'percentage' => 0],
        ['month' => 'Mar', 'percentage' => 0],
        ['month' => 'Apr', 'percentage' => 0],
        ['month' => 'May', 'percentage' => 0],
        ['month' => 'Jun', 'percentage' => 0]
    ];
}

// Fetch recent attendance records
$recent_records = [];
$recent_query = mysqli_query($data, "
    SELECT 
        a.date, 
        s.subject_name, 
        a.status,
        TIME_FORMAT(a.created_at, '%h:%i %p') AS time_label
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = '$student_id'
    ORDER BY a.date DESC, a.id DESC
    LIMIT 10
");

if ($recent_query && mysqli_num_rows($recent_query) > 0) {
    while ($row = mysqli_fetch_assoc($recent_query)) {
        $recent_records[] = $row;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Attendance | Student - StudyBuddyHub</title>

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

        /* Overall Card */
        .overall-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .overall-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .overall-header h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .attendance-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-good { background: rgba(16,185,129,0.1); color: var(--success); }
        .badge-warning { background: rgba(245,158,11,0.1); color: var(--warning); }
        .badge-danger { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .progress-container {
            margin-bottom: 1rem;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        .progress-bar-container {
            width: 100%;
            height: 10px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            transition: width 1s ease;
        }
        .attendance-stats {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
        }
        .stat-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .dot-present { background: var(--success); }
        .dot-absent { background: var(--danger); }
        .dot-total { background: var(--primary); }

        /* Attendance Grid */
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .subject-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .subject-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
        }
        .subject-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .subject-code {
            background: rgba(79,70,229,0.1);
            color: var(--primary);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .attendance-percent {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .percent-good { color: var(--success); }
        .percent-warning { color: var(--warning); }
        .percent-danger { color: var(--danger); }
        
        .subject-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(79,70,229,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .subject-icon i { font-size: 1.8rem; color: var(--card-color, var(--primary)); }
        
        .subject-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }
        .subject-faculty {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }
        .subject-faculty i { color: var(--primary); }
        
        .subject-progress {
            margin-bottom: 0.75rem;
        }
        .progress-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray);
        }
        .progress-stats i { width: 18px; }

        /* Chart and Recent Cards */
        .chart-card, .recent-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            height: 100%;
        }
        .chart-header, .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .chart-header h5, .recent-header h5 {
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
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .attendance-table th {
            text-align: left;
            padding: 0.75rem 0;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.75rem;
            border-bottom: 2px solid var(--border);
        }
        .attendance-table td {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .status-present { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-absent { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status-late { background: rgba(245,158,11,0.1); color: var(--warning); }

        /* Tips Card */
        .tips-card {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .tips-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: rgba(16,185,129,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tips-icon i { font-size: 1.5rem; color: var(--success); }
        .tips-content h6 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .tips-content p { font-size: 0.8rem; color: var(--gray); margin: 0; }

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
            .attendance-grid { grid-template-columns: 1fr; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .overall-header { flex-direction: column; align-items: flex-start; }
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
            <a href="attendance.php" class="active"> <i class="fas fa-calendar-check"></i> <span>Attendance</span> </a>
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
                <span class="page-title"><i class="fas fa-calendar-check me-2" style="color: var(--primary);"></i>Attendance</span>
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
                    <h2>📊 Attendance Overview</h2>
                    <p>Track your subject-wise attendance and stay on top of your academic progress.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo $total_classes; ?> Total Classes</span>
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
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-number"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
                    <div class="stat-number"><?php echo $total_attended; ?></div>
                    <div class="stat-label">Attended</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--danger);"></i></div>
                    <div class="stat-number"><?php echo $total_classes - $total_attended; ?></div>
                    <div class="stat-label">Missed</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo $overall_percentage; ?>%</div>
                    <div class="stat-label">Attendance</div>
                </div>
            </div>

            <!-- OVERALL CARD -->
            <div class="overall-card" data-aos="fade-up" data-aos-delay="100">
                <div class="overall-header">
                    <h4><i class="fas fa-chart-pie"></i> Overall Attendance</h4>
                    <?php
                    $badge_class = 'badge-good';
                    $badge_text = 'Excellent';
                    if ($overall_percentage < 75) {
                        $badge_class = 'badge-danger';
                        $badge_text = 'Needs Improvement';
                    } elseif ($overall_percentage < 85) {
                        $badge_class = 'badge-warning';
                        $badge_text = 'Average';
                    } elseif ($overall_percentage >= 90) {
                        $badge_class = 'badge-good';
                        $badge_text = 'Excellent';
                    }
                    ?>
                    <span class="attendance-badge <?php echo $badge_class; ?>">
                        <i class="fas <?php echo $overall_percentage >= 75 ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <?php echo $badge_text; ?>
                    </span>
                </div>

                <div class="progress-container">
                    <div class="progress-label">
                        <span>Attendance Rate</span>
                        <span class="fw-bold"><?php echo $overall_percentage; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $overall_percentage; ?>%;"></div>
                    </div>
                </div>

                <div class="attendance-stats">
                    <div class="stat-item">
                        <span class="stat-dot dot-present"></span>
                        <span>Present: <?php echo $total_attended; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-dot dot-absent"></span>
                        <span>Absent: <?php echo $total_classes - $total_attended; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-dot dot-total"></span>
                        <span>Total Classes: <?php echo $total_classes; ?></span>
                    </div>
                </div>
            </div>

            <!-- Subject-wise Attendance -->
            <div class="section-header mb-3" data-aos="fade-up" data-aos-delay="150">
                <h3 style="font-size: 1.2rem; font-weight: 700;"><i class="fas fa-book-open me-2" style="color: var(--primary);"></i>Subject-wise Attendance</h3>
            </div>

            <?php if (empty($attendance_data)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-calendar-alt"></i>
                    <h5>No Attendance Records</h5>
                    <p>No attendance records have been marked for your subjects yet.</p>
                </div>
            <?php else: ?>
                <div class="attendance-grid" data-aos="fade-up" data-aos-delay="200">
                    <?php foreach ($attendance_data as $subject): 
                        $cardColor = 'var(--' . $subject['color'] . ')';
                        $percent_class = 'percent-good';
                        if ($subject['percentage'] < 75) {
                            $percent_class = 'percent-danger';
                        } elseif ($subject['percentage'] < 85) {
                            $percent_class = 'percent-warning';
                        }
                    ?>
                    <div class="subject-card" style="--card-color: <?php echo $cardColor; ?>;">
                        <div class="subject-header">
                            <span class="subject-code">📖 <?php echo htmlspecialchars($subject['subject_code']); ?></span>
                            <span class="attendance-percent <?php echo $percent_class; ?>"><?php echo $subject['percentage']; ?>%</span>
                        </div>

                        <div class="subject-icon">
                            <i class="fas <?php echo $subject['icon']; ?>"></i>
                        </div>

                        <h4 class="subject-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                        <div class="subject-faculty">
                            <i class="fas fa-chalkboard-user"></i>
                            <span><?php echo htmlspecialchars($subject['faculty']); ?></span>
                        </div>

                        <div class="subject-progress">
                            <div class="progress-label">
                                <span><?php echo $subject['attended']; ?>/<?php echo $subject['total_classes']; ?></span>
                                <span><?php echo $subject['percentage']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?php echo $subject['percentage']; ?>%;"></div>
                            </div>
                        </div>

                        <div class="progress-stats">
                            <div><i class="fas fa-check-circle text-success"></i> Present: <?php echo $subject['attended']; ?></div>
                            <div><i class="fas fa-times-circle text-danger"></i> Absent: <?php echo $subject['total_classes'] - $subject['attended']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Charts and Recent Activity -->
            <div class="row g-4" data-aos="fade-up" data-aos-delay="250">
                <div class="col-lg-7">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>Monthly Attendance Trend</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="recent-card">
                        <div class="recent-header">
                            <h5><i class="fas fa-history me-2" style="color: var(--primary);"></i>Recent Records</h5>
                        </div>

                        <?php if (empty($recent_records)): ?>
                            <div class="empty-state py-4">
                                <i class="fas fa-calendar-alt"></i>
                                <h5>No Recent Records</h5>
                                <p class="small">No attendance records available.</p>
                            </div>
                        <?php else: ?>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recent_records, 0, 5) as $record): ?>
                                    <tr>
                                        <td><?php echo date('d M', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $record['status'] == 'present' ? 'status-present' : ($record['status'] == 'late' ? 'status-late' : 'status-absent'); ?>">
                                                <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="tips-card" data-aos="fade-up" data-aos-delay="300">
                <div class="tips-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="tips-content">
                    <h6>Attendance Tips</h6>
                    <p>
                        <i class="fas fa-check-circle text-success me-1"></i> Minimum 75% attendance required for exams.
                        <?php if ($overall_percentage < 75 && $total_classes > 0): ?>
                            You need approximately <?php echo ceil((75 * ($total_classes + 20) - $total_attended * 100) / 100); ?> more classes to reach 75%.
                        <?php endif; ?>
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

    // Attendance Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthly_attendance, 'month')); ?>,
            datasets: [{
                label: 'Attendance %',
                data: <?php echo json_encode(array_column($monthly_attendance, 'percentage')); ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
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
                            return 'Attendance: ' + context.raw + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { stepSize: 20, callback: function(value) { return value + '%'; } }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>

</body>
</html>