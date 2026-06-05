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

// ============================================
// FETCH ASSIGNMENTS
// ============================================
$assignments = [];
$assignment_query = mysqli_query($data, "
    SELECT 
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.due_time,
        a.total_marks,
        a.file_path,
        a.created_at,
        s.subject_name,
        s.subject_code,
        s.semester,
        t.name AS teacher_name,
        t.email AS teacher_email,
        (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND student_id = '$student_id') as is_submitted,
        (SELECT marks FROM submissions WHERE assignment_id = a.id AND student_id = '$student_id') as obtained_marks,
        (SELECT status FROM submissions WHERE assignment_id = a.id AND student_id = '$student_id') as submission_status,
        (SELECT file_path FROM submissions WHERE assignment_id = a.id AND student_id = '$student_id') as submitted_file
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    LEFT JOIN teacher t ON t.id = a.teacher_id
    WHERE s.branch = '$student_branch' AND s.semester = '$student_semester'
    ORDER BY a.due_date ASC
");

$colors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
$index = 0;

if ($assignment_query && mysqli_num_rows($assignment_query) > 0) {
    while ($row = mysqli_fetch_assoc($assignment_query)) {
        $subject_name = $row['subject_name'];
        $color = $colors[$index % count($colors)];
        $icon = 'fa-tasks';
        
        // Assign color based on subject
        if (stripos($subject_name, 'Database') !== false) {
            $color = 'primary';
            $icon = 'fa-database';
        } elseif (stripos($subject_name, 'Network') !== false) {
            $color = 'success';
            $icon = 'fa-network-wired';
        } elseif (stripos($subject_name, 'Operating') !== false) {
            $color = 'warning';
            $icon = 'fa-windows';
        } elseif (stripos($subject_name, 'Python') !== false) {
            $color = 'danger';
            $icon = 'fa-python';
        } elseif (stripos($subject_name, 'Web') !== false) {
            $color = 'info';
            $icon = 'fa-code';
        }
        
        // Calculate days left
        $due_date = $row['due_date'];
        $today = date('Y-m-d');
        $days_left = round((strtotime($due_date) - strtotime($today)) / (60 * 60 * 24));
        
        // Set status
        $status = 'pending';
        $status_text = 'Not Submitted';
        $status_class = 'danger';
        
        if ($row['is_submitted'] > 0) {
            if ($row['submission_status'] == 'graded') {
                $status = 'graded';
                $status_text = 'Graded';
                $status_class = 'success';
            } else {
                $status = 'submitted';
                $status_text = 'Submitted';
                $status_class = 'warning';
            }
        } elseif ($days_left < 0) {
            $status = 'overdue';
            $status_text = 'Overdue';
            $status_class = 'danger';
        } elseif ($days_left <= 3) {
            $status = 'urgent';
            $status_text = 'Urgent';
            $status_class = 'danger';
        }
        
        $row['color'] = $color;
        $row['icon'] = $icon;
        $row['subject'] = $subject_name;
        $row['due_date_formatted'] = date('d M Y', strtotime($row['due_date']));
        $row['due_time_formatted'] = date('h:i A', strtotime($row['due_time']));
        $row['days_left'] = $days_left;
        $row['status'] = $status;
        $row['status_text'] = $status_text;
        $row['status_class'] = $status_class;
        $row['file_exists'] = !empty($row['file_path']);
        
        $assignments[] = $row;
        $index++;
    }
}

// Get unique subjects for filter
$unique_subjects = array_unique(array_column($assignments, 'subject'));

// Get statistics
$total_assignments = count($assignments);
$submitted_count = count(array_filter($assignments, function($a) { return $a['is_submitted'] > 0; }));
$pending_count_assignments = $total_assignments - $submitted_count;
$overdue_count = count(array_filter($assignments, function($a) { return $a['days_left'] < 0 && $a['is_submitted'] == 0; }));

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Assignments | Student - StudyBuddyHub</title>

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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
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

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            position: relative;
            min-width: 200px;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        .search-box input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .search-box input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        
        .filter-select {
            padding: 0.7rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
            background: white;
            cursor: pointer;
            min-width: 140px;
        }
        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn-reset {
            padding: 0.7rem 1.2rem;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 12px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-reset:hover {
            background: var(--border);
            transform: translateY(-2px);
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

        /* Assignments Grid */
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .assignment-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .assignment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
        }
        .assignment-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending, .status-urgent { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status-submitted { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-graded { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-overdue { background: rgba(239,68,68,0.15); color: var(--danger); }
        
        .due-date {
            font-size: 0.7rem;
            color: var(--gray);
        }
        
        .assignment-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(79,70,229,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .assignment-icon i { font-size: 1.8rem; color: var(--card-color, var(--primary)); }
        
        .assignment-subject {
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .assignment-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .assignment-description {
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .assignment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.75rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--gray);
            font-size: 0.75rem;
        }
        .meta-item i { color: var(--primary); width: 16px; }
        
        .marks-info {
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }
        
        .button-group {
            display: flex;
            gap: 0.75rem;
            margin-top: auto;
        }
        
        .btn-primary-custom, .btn-view-details, .btn-submit-custom, .btn-download-custom {
            flex: 1;
            padding: 0.6rem 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
        }
        
        .btn-view-details {
            background: rgba(79,70,229,0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        .btn-view-details:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-download-custom {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        .btn-download-custom:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-submit-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .btn-submit-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        
        .btn-success-custom {
            background: linear-gradient(135deg, var(--success), #059669);
        }
        .btn-outline-custom {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        /* Modal Styles */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        .modal-custom.active {
            display: flex;
        }
        .modal-content-custom {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
            position: relative;
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
            position: sticky;
            top: 0;
            z-index: 10;
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
        }
        .modal-close:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        .modal-body-custom {
            padding: 1.5rem;
        }
        .detail-section {
            margin-bottom: 1rem;
        }
        .detail-label {
            font-size: 0.7rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
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
            .assignments-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .search-box, .filter-select, .btn-reset { width: 100%; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .button-group { flex-direction: column; }
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
            <a href="assignments.php" class="active"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>

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
                <span class="page-title"><i class="fas fa-tasks me-2" style="color: var(--primary);"></i>Assignments</span>
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
                    <h2>
                        📋 My Assignments
                    </h2>
                    <p>View, download assignment files, and submit your work before the deadlines.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-tasks"></i>
                            <span><?php echo $total_assignments; ?> Total</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo $submitted_count; ?> Submitted</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $pending_count_assignments; ?> Pending</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-number"><?php echo $total_assignments; ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $submitted_count; ?></div>
                    <div class="stat-label">Submitted</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $pending_count_assignments; ?></div>
                    <div class="stat-label">Pending</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?php echo $overdue_count; ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar" data-aos="fade-up" data-aos-delay="100">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search assignments..." onkeyup="filterAssignments()">
                </div>
                <select class="filter-select" id="subjectFilter" onchange="filterAssignments()">
                    <option value="all">All Subjects</option>
                    <?php foreach ($unique_subjects as $subject): ?>
                        <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" id="statusFilter" onchange="filterAssignments()">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="submitted">Submitted</option>
                    <option value="graded">Graded</option>
                    <option value="urgent">Urgent</option>
                </select>
                <button class="btn-reset" onclick="resetFilters()"><i class="fas fa-times"></i> Reset</button>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-tasks"></i>
                    <h5>No Assignments Available</h5>
                    <p>Assignments for your subjects will appear here when created by teachers.</p>
                </div>
            <?php else: ?>
                <!-- Assignments Section -->
                <div class="section-header" data-aos="fade-up" data-aos-delay="150">
                    <h3><i class="fas fa-list" style="color: var(--primary);"></i> All Assignments</h3>
                    <span class="badge bg-primary p-2 px-3 rounded-pill">📋 <?php echo $total_assignments; ?> Assignments</span>
                </div>

                <div class="assignments-grid" id="assignmentsGrid">
                    <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card" 
                         data-subject="<?php echo htmlspecialchars($assignment['subject']); ?>" 
                         data-status="<?php echo $assignment['status']; ?>"
                         data-title="<?php echo strtolower(htmlspecialchars($assignment['title'])); ?>"
                         style="--card-color: var(--<?php echo $assignment['color']; ?>);">
                        
                        <div class="assignment-header">
                            <span class="status-badge status-<?php echo $assignment['status']; ?>">
                                <?php if ($assignment['status'] == 'submitted'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php elseif ($assignment['status'] == 'graded'): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($assignment['status'] == 'urgent'): ?>
                                    <i class="fas fa-exclamation-circle"></i>
                                <?php elseif ($assignment['status'] == 'overdue'): ?>
                                    <i class="fas fa-hourglass-end"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>
                                <?php endif; ?>
                                <?php echo $assignment['status_text']; ?>
                            </span>
                            <span class="due-date">
                                <i class="fas fa-calendar-alt"></i> Due: <?php echo $assignment['due_date_formatted']; ?>
                            </span>
                        </div>

                        <div class="assignment-icon">
                            <i class="fas <?php echo $assignment['icon']; ?>"></i>
                        </div>

                        <div class="assignment-subject">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($assignment['subject']); ?>
                        </div>
                        <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                        <div class="assignment-description">
                            <?php echo !empty($assignment['description']) ? htmlspecialchars(substr($assignment['description'], 0, 100)) : 'No description provided.'; ?>
                        </div>

                        <div class="assignment-meta">
                            <div class="meta-item">
                                <i class="fas fa-chalkboard-user"></i>
                                <span><?php echo htmlspecialchars($assignment['teacher_name'] ?: 'Teacher'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $assignment['due_time_formatted']; ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-star"></i>
                                <span>Max: <?php echo $assignment['total_marks']; ?> marks</span>
                            </div>
                        </div>

                        <?php if ($assignment['status'] == 'graded' && $assignment['obtained_marks'] !== null): ?>
                            <div class="marks-info">
                                <div class="alert alert-success" style="padding: 0.5rem; text-align: center;">
                                    <strong>Score:</strong> <?php echo $assignment['obtained_marks']; ?>/<?php echo $assignment['total_marks']; ?> marks
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="button-group">
                            <!-- View Details Button -->
                            <button class="btn-view-details" onclick='viewAssignmentDetails(<?php echo json_encode($assignment); ?>)'>
                                <i class="fas fa-eye"></i> View
                            </button>
                            
                            <!-- Download Teacher's File Button -->
                            <?php if ($assignment['file_exists']): ?>
                                <a href="download_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-download-custom">
                                    <i class="fas fa-download"></i> File
                                </a>
                            <?php endif; ?>
                            
                            <!-- Submit Button - Opens Modal -->
                            <?php if ($assignment['status'] == 'submitted'): ?>
                                <a href="submission_status.php?id=<?php echo $assignment['id']; ?>" class="btn-submit-custom btn-outline-custom">
                                    <i class="fas fa-eye"></i> Status
                                </a>
                            <?php elseif ($assignment['status'] == 'graded'): ?>
                                <a href="student_view_feedback.php?id=<?php echo $assignment['id']; ?>" class="btn-submit-custom btn-success-custom">
                                    <i class="fas fa-chart-line"></i> Feedback
                                </a>
                            <?php else: ?>
                                <button class="btn-submit-custom" onclick="openSubmitModal(<?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-upload"></i> Submit
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- No Results State -->
                <div id="noResults" class="empty-state" style="display: none;" data-aos="fade-up">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Assignments</h5>
                    <p>Try adjusting your search or filter criteria.</p>
                    <button class="btn-reset" onclick="resetFilters()"><i class="fas fa-times"></i> Clear Filters</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Assignment Modal -->
<div class="modal-custom" id="viewModal">
    <div class="modal-content-custom" style="max-width: 600px;">
        <div class="modal-header-custom">
            <h5><i class="fas fa-file-alt"></i> Assignment Details</h5>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="viewModalBody">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button class="btn-cancel" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- Submit Assignment Modal -->
<div class="modal-custom" id="submitModal">
    <div class="modal-content-custom" style="max-width: 600px;">
        <div class="modal-header-custom">
            <h5><i class="fas fa-upload"></i> Submit Assignment</h5>
            <button class="modal-close" onclick="closeSubmitModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="submitModalBody">
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading assignment details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal-custom" id="successModal">
    <div class="modal-content-custom" style="max-width: 450px; text-align: center;">
        <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
            <h5><i class="fas fa-check-circle"></i> Success!</h5>
            <button class="modal-close" onclick="closeSuccessModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="successModalBody">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;"></i>
            <h4>Assignment Submitted!</h4>
            <p>Your assignment has been submitted successfully.</p>
            <button class="btn-save" onclick="closeSuccessModalAndRedirect()">OK</button>
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
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    AOS.init({ duration: 600, once: true, offset: 20, disable: window.innerWidth < 768 });

    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('active');
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    // Close sidebar on click outside on mobile
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

    // Profile Dropdown
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
            closeViewModal();
            closeSubmitModal();
            closeSuccessModal();
        }
    });

    // Notifications
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

    // View Assignment Details
    function viewAssignmentDetails(assignment) {
        let fileHtml = '';
        if (assignment.file_path) {
            fileHtml = `
                <div class="detail-section">
                    <div class="detail-label"><i class="fas fa-paperclip"></i> Attached File</div>
                    <div class="detail-value">
                        <a href="download_assignment.php?id=${assignment.id}" class="btn-download-custom" style="display: inline-flex; padding: 0.5rem 1rem; text-decoration: none;">
                            <i class="fas fa-download"></i> Download Assignment File
                        </a>
                    </div>
                </div>
            `;
        } else {
            fileHtml = '<div class="detail-section"><div class="detail-label"><i class="fas fa-paperclip"></i> Attached File</div><div class="detail-value">No file attached</div></div>';
        }
        
        const statusIcon = assignment.status == 'submitted' ? '<i class="fas fa-check-circle text-warning"></i>' : 
                          (assignment.status == 'graded' ? '<i class="fas fa-star text-success"></i>' : 
                          (assignment.status == 'urgent' ? '<i class="fas fa-exclamation-circle text-danger"></i>' : 
                          '<i class="fas fa-clock text-danger"></i>'));
        
        const modalBody = document.getElementById('viewModalBody');
        modalBody.innerHTML = `
            <div class="text-center mb-3">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-alt fa-2x" style="color: white;"></i>
                </div>
                <h5 style="font-weight: 700; color: var(--dark);">${escapeHtml(assignment.title)}</h5>
                <span class="badge-subject">${escapeHtml(assignment.subject)}</span>
            </div>
            <div class="detail-section">
                <div class="detail-label"><i class="fas fa-align-left"></i> Description</div>
                <div class="detail-value">${escapeHtml(assignment.description) || 'No description provided.'}</div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-section">
                        <div class="detail-label"><i class="fas fa-calendar-alt"></i> Due Date</div>
                        <div class="detail-value">${assignment.due_date_formatted}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-section">
                        <div class="detail-label"><i class="fas fa-clock"></i> Due Time</div>
                        <div class="detail-value">${assignment.due_time_formatted}</div>
                    </div>
                </div>
            </div>
            <div class="detail-section">
                <div class="detail-label"><i class="fas fa-star"></i> Total Marks</div>
                <div class="detail-value">${assignment.total_marks} marks</div>
            </div>
            ${fileHtml}
            <div class="detail-section">
                <div class="detail-label"><i class="fas fa-chalkboard-user"></i> Teacher</div>
                <div class="detail-value">${escapeHtml(assignment.teacher_name) || 'Not assigned'}</div>
            </div>
            <div class="detail-section">
                <div class="detail-label"><i class="fas fa-info-circle"></i> Status</div>
                <div class="detail-value">${statusIcon} ${assignment.status_text}</div>
            </div>
            ${assignment.status == 'graded' && assignment.obtained_marks ? `
            <div class="detail-section">
                <div class="detail-label"><i class="fas fa-chart-line"></i> Obtained Marks</div>
                <div class="detail-value"><strong>${assignment.obtained_marks}/${assignment.total_marks}</strong></div>
            </div>
            ` : ''}
        `;
        document.getElementById('viewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Submit Modal Functions
    function openSubmitModal(assignmentId) {
        const modal = document.getElementById('submitModal');
        const modalBody = document.getElementById('submitModalBody');
        
        modalBody.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading assignment details...</p>
            </div>
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        $.ajax({
            url: 'get_assignment_details.php',
            type: 'POST',
            data: { assignment_id: assignmentId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const assignment = response.data;
                    const isOverdue = new Date(assignment.due_date + ' ' + assignment.due_time) < new Date();
                    
                    let overdueWarning = '';
                    if (isOverdue) {
                        overdueWarning = `
                            <div class="alert alert-danger mb-3" style="border-radius: 12px;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>Warning:</strong> This assignment is overdue! Late submissions may be penalized.
                            </div>
                        `;
                    }
                    
                    modalBody.innerHTML = `
                        <form id="submitAssignmentForm" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="${assignment.id}">
                            ${overdueWarning}
                            <div class="detail-section">
                                <div class="detail-label"><i class="fas fa-book"></i> Subject</div>
                                <div class="detail-value" style="font-size: 1rem; font-weight: 600;">${escapeHtml(assignment.subject_name)}</div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label"><i class="fas fa-heading"></i> Assignment Title</div>
                                <div class="detail-value" style="font-weight: 600;">${escapeHtml(assignment.title)}</div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label"><i class="fas fa-align-left"></i> Description</div>
                                <div class="detail-value">${escapeHtml(assignment.description) || 'No description provided.'}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-section">
                                        <div class="detail-label"><i class="fas fa-calendar-alt"></i> Due Date</div>
                                        <div class="detail-value">${assignment.due_date_formatted}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-section">
                                        <div class="detail-label"><i class="fas fa-clock"></i> Due Time</div>
                                        <div class="detail-value">${assignment.due_time_formatted}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label"><i class="fas fa-star"></i> Total Marks</div>
                                <div class="detail-value">${assignment.total_marks} marks</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-paperclip"></i> Your Submission File <span class="text-danger">*</span></label>
                                <input type="file" name="submission_file" class="form-control" accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png" required>
                                <small class="text-muted">Allowed: PDF, DOC, DOCX, TXT, ZIP, RAR, JPG, PNG (Max: 10MB)</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-comment"></i> Remarks (Optional)</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="Any comments for the teacher..."></textarea>
                            </div>
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="confirmSubmit" required>
                                    <label class="form-check-label" for="confirmSubmit">I confirm that this is my own work.</label>
                                </div>
                            </div>
                            <div class="mt-4" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                                <button type="button" class="btn-cancel" onclick="closeSubmitModal()">Cancel</button>
                                <button type="submit" class="btn-save" id="submitBtn"><i class="fas fa-paper-plane"></i> Submit Assignment</button>
                            </div>
                        </form>
                    `;
                    
                    $('#submitAssignmentForm').on('submit', function(e) {
                        e.preventDefault();
                        submitAssignment();
                    });
                } else {
                    modalBody.innerHTML = `
                        <div class="text-center p-5">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem;"></i>
                            <h5>Error Loading Assignment</h5>
                            <p>${response.message}</p>
                            <button class="btn-cancel" onclick="closeSubmitModal()">Close</button>
                        </div>
                    `;
                }
            },
            error: function() {
                modalBody.innerHTML = `
                    <div class="text-center p-5">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem;"></i>
                        <h5>Error Loading Assignment</h5>
                        <p>Could not load assignment details. Please try again.</p>
                        <button class="btn-cancel" onclick="closeSubmitModal()">Close</button>
                    </div>
                `;
            }
        });
    }

    function closeSubmitModal() {
        document.getElementById('submitModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeSuccessModalAndRedirect() {
        closeSuccessModal();
        window.location.href = 'assignments.php';
    }

    function submitAssignment() {
        const formData = new FormData(document.getElementById('submitAssignmentForm'));
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        $.ajax({
            url: 'submit_assignment.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    closeSubmitModal();
                    const successModal = document.getElementById('successModal');
                    const successBody = document.getElementById('successModalBody');
                    successBody.innerHTML = `
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;"></i>
                        <h4>${response.title || 'Assignment Submitted!'}</h4>
                        <p>${response.message}</p>
                        <button class="btn-save" onclick="closeSuccessModalAndRedirect()">OK</button>
                    `;
                    successModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#4f46e5'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#4f46e5'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Filter Functions
    function filterAssignments() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const subjectFilter = document.getElementById('subjectFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        const cards = document.querySelectorAll('.assignment-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const subject = card.getAttribute('data-subject');
            const status = card.getAttribute('data-status');
            const title = card.getAttribute('data-title');
            
            const matchesSearch = searchTerm === '' || subject.toLowerCase().includes(searchTerm) || title.includes(searchTerm);
            const matchesSubject = subjectFilter === 'all' || subject === subjectFilter;
            const matchesStatus = statusFilter === 'all' || status === statusFilter;
            
            if (matchesSearch && matchesSubject && matchesStatus) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        const noResults = document.getElementById('noResults');
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('subjectFilter').value = 'all';
        document.getElementById('statusFilter').value = 'all';
        filterAssignments();
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

    // Close modals on outside click
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });
    document.getElementById('submitModal').addEventListener('click', function(e) {
        if (e.target === this) closeSubmitModal();
    });
    document.getElementById('successModal').addEventListener('click', function(e) {
        if (e.target === this) closeSuccessModal();
    });
</script>

</body>
</html>