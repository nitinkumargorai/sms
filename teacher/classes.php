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

// Fetch teacher details
$teacher_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);
$teacher_branch = $teacher_data['branch'] ?? '';
$teacher_mobile = $teacher_data['mobile'] ?? '';
$teacher_id = $teacher_data['id'] ?? 0;
$teacher_qualification = $teacher_data['qualification'] ?? '';
$teacher_experience = $teacher_data['experience'] ?? 0;

/* ============================================
   REAL-TIME DATA FROM DATABASE
============================================ */

// 1️⃣ GET TEACHER'S SUBJECTS WITH REAL DATA
$subjects = [];
$total_students = 0;
$total_assignments = 0;
$total_materials = 0;
$total_completed_tasks = 0;

if ($teacher_id > 0) {
    $subject_query = mysqli_query($data, "SELECT s.* FROM subjects s 
                                          INNER JOIN teacher_subjects ts ON s.id = ts.subject_id 
                                          WHERE ts.teacher_id = " . intval($teacher_id) . "
                                          ORDER BY s.semester ASC, s.subject_name ASC");
    
    if ($subject_query && mysqli_num_rows($subject_query) > 0) {
        while ($row = mysqli_fetch_assoc($subject_query)) {
            // Get student count for this subject
            $student_count_query = mysqli_query($data, "SELECT COUNT(*) as total FROM admission WHERE Branch = '{$row['branch']}' AND Semester = '{$row['semester']}'");
            $student_count = 0;
            if ($student_count_query && $sc_row = mysqli_fetch_assoc($student_count_query)) {
                $student_count = $sc_row['total'];
                $total_students += $student_count;
            }
            
            // Get assignments count for this subject
            $assignment_count_query = mysqli_query($data, "SELECT COUNT(*) as total FROM assignments WHERE subject_id = '{$row['id']}' AND teacher_id = '$teacher_id'");
            $assignment_count = 0;
            if ($assignment_count_query && $ac_row = mysqli_fetch_assoc($assignment_count_query)) {
                $assignment_count = $ac_row['total'];
                $total_assignments += $assignment_count;
            }
            
            // Get materials count for this subject
            $material_count_query = mysqli_query($data, "SELECT COUNT(*) as total FROM materials WHERE subject_id = '{$row['id']}' AND teacher_id = '$teacher_id'");
            $material_count = 0;
            if ($material_count_query && $mc_row = mysqli_fetch_assoc($material_count_query)) {
                $material_count = $mc_row['total'];
                $total_materials += $material_count;
            }
            
            // Get attendance percentage for this subject (last 30 days)
            $attendance_query = mysqli_query($data, "SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(*) as total_count
                FROM attendance a
                JOIN admission ad ON a.student_id = ad.id
                WHERE a.subject_id = '{$row['id']}' 
                AND a.marked_by = '$teacher_id'
                AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            
            $attendance_percentage = 0;
            if ($attendance_query && $att_row = mysqli_fetch_assoc($attendance_query)) {
                if ($att_row['total_count'] > 0) {
                    $attendance_percentage = round(($att_row['present_count'] / $att_row['total_count']) * 100);
                }
            }
            
            $row['student_count'] = $student_count;
            $row['assignment_count'] = $assignment_count;
            $row['material_count'] = $material_count;
            $row['attendance_percentage'] = $attendance_percentage;
            $subjects[] = $row;
        }
    }
}

// 2️⃣ GET TOTAL COMPLETED TASKS (graded assignments)
$completed_query = mysqli_query($data, "SELECT COUNT(*) as total FROM submissions s 
                                        JOIN assignments a ON s.assignment_id = a.id 
                                        WHERE a.teacher_id = '$teacher_id' AND s.status = 'graded'");
if ($completed_query && $cq_row = mysqli_fetch_assoc($completed_query)) {
    $total_completed_tasks = $cq_row['total'];
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

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Classes | Teacher - StudyBuddyHub</title>

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
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .teacher-wrap {
            display: flex;
            min-height: 100vh;
            position: relative;
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

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #1e293b;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #475569;
        }

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
        .menu-toggle:hover {
            background: var(--light);
        }
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
            .welcome-banner h2 { font-size: 1.3rem; }
        }
        
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
        .stat-content h4 {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .stat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .stat-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
        }
        .stat-link:hover { gap: 0.5rem; color: var(--primary-dark); }

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

        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
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
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .subject-code {
            background: rgba(79,70,229,0.1);
            color: var(--primary);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .subject-credits {
            background: #f1f5f9;
            color: var(--gray);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        .subject-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(79,70,229,0.1), rgba(79,70,229,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .subject-icon i { font-size: 1.8rem; color: var(--card-color, var(--primary)); }
        .subject-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .subject-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--gray);
            font-size: 0.8rem;
            flex-wrap: wrap;
        }
        .subject-meta i { color: var(--primary); margin-right: 0.3rem; width: 16px; }
        .subject-description {
            color: var(--gray);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        .subject-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--gray);
            font-size: 0.8rem;
        }
        .stat-item i { color: var(--primary); font-size: 0.9rem; }
        .stat-item .number {
            font-weight: 700;
            color: var(--dark);
        }
        .subject-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem;
        }
        .btn-action {
            padding: 0.6rem 0.3rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            text-align: center;
        }
        .btn-materials { background: rgba(79,70,229,0.1); color: var(--primary); }
        .btn-materials:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        .btn-attendance { background: rgba(16,185,129,0.1); color: var(--success); }
        .btn-attendance:hover { background: var(--success); color: white; transform: translateY(-2px); }
        .btn-assignments { background: rgba(245,158,11,0.1); color: var(--warning); }
        .btn-assignments:hover { background: var(--warning); color: white; transform: translateY(-2px); }
        .btn-results { background: rgba(239,68,68,0.1); color: var(--danger); }
        .btn-results:hover { background: var(--danger); color: white; transform: translateY(-2px); }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h5 { font-size: 1.2rem; font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--gray); font-size: 0.9rem; }

        /* Profile Dropdown */
        .profile-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 1rem;
            min-width: 300px;
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

        /* Modal Styles */
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
            .subjects-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                gap: 1rem;
            }
            .profile-dropdown {
                right: 10px;
                left: 10px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<div class="teacher-wrap">

    <!-- SIDEBAR - UPDATED WITH SYLLABUS BUTTON -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📚 StudyBuddyHub</h3>
            <p>College Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">MAIN</div>
            <a href="dashboard.php"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

            <div class="menu-title">ACADEMICS</div>
            <a href="classes.php" class="active"> <i class="fas fa-book"></i> <span>My Classes</span> </a>
            <a href="materials.php"> <i class="fas fa-file-pdf"></i> <span>Study Materials</span> </a>
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>

            <div class="menu-title">STUDENT MANAGEMENT</div>
            <a href="attendance.php"> <i class="fas fa-calendar-check"></i> <span>Attendance</span> </a>
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
                <span class="page-title"><i class="fas fa-book me-2" style="color: var(--primary);"></i>My Classes</span>
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
            <!-- Welcome Banner -->
            <div class="welcome-banner" data-aos="fade-up">
                <div class="welcome-content">
                    <h2>
                        👩‍🏫 My Teaching Classes
                    </h2>
                    <p>Manage your subjects, track student progress, and access course materials.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-book"></i>
                            <span><?php echo count($subjects); ?> Active Classes</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $total_students; ?> Students</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-tasks"></i>
                            <span><?php echo $total_assignments; ?> Assignments</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                    <div class="stat-content">
                        <h4>Total Subjects</h4>
                        <div class="stat-number"><?php echo count($subjects); ?></div>
                        <div class="stat-footer">
                            <span class="stat-link">Teaching this semester <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h4>Total Students</h4>
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-footer">
                            <span class="stat-link">Across all subjects <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-content">
                        <h4>Study Materials</h4>
                        <div class="stat-number"><?php echo $total_materials; ?></div>
                        <div class="stat-footer">
                            <span class="stat-link">Resources uploaded <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h4>Completed Tasks</h4>
                        <div class="stat-number"><?php echo $total_completed_tasks; ?></div>
                        <div class="stat-footer">
                            <span class="stat-link">Assignments graded <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUBJECTS SECTION -->
            <div class="section-header" data-aos="fade-up" data-aos-delay="100">
                <h3>
                    <i class="fas fa-graduation-cap" style="color: var(--primary);"></i>
                    My Subjects
                </h3>
                <span class="badge bg-primary p-2 px-3 rounded-pill">📖 <?php echo count($subjects); ?> Classes</span>
            </div>

            <!-- Subjects Grid -->
            <?php if (!empty($subjects)): ?>
            <div class="subjects-grid" data-aos="fade-up" data-aos-delay="150">
                <?php 
                $colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'];
                $icons = ['fa-network-wired', 'fa-database', 'fa-windows', 'fa-python', 'fa-code', 'fa-cogs', 'fa-chart-line', 'fa-calculator', 'fa-atom', 'fa-flask'];
                
                foreach ($subjects as $index => $subject): 
                    $color = $colors[$index % count($colors)];
                    $icon = $icons[$index % count($icons)];
                ?>
                <div class="subject-card" style="--card-color: <?php echo $color; ?>;" data-aos="fade-up" data-aos-delay="<?php echo 200 + ($index * 50); ?>">
                    <div class="subject-header">
                        <span class="subject-code">📌 <?php echo htmlspecialchars($subject['subject_code']); ?></span>
                        <span class="subject-credits"><i class="fas fa-star"></i> <?php echo $subject['credits']; ?> Credits</span>
                    </div>

                    <div class="subject-icon">
                        <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                    </div>

                    <h3 class="subject-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    
                    <div class="subject-meta">
                        <span><i class="fas fa-layer-group"></i> Semester <?php echo $subject['semester']; ?></span>
                        <span><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($subject['branch']); ?></span>
                    </div>

                    <p class="subject-description">
                        <?php echo !empty($subject['description']) ? htmlspecialchars(substr($subject['description'], 0, 100)) : 'No description available for this subject.'; ?>
                    </p>

                    <div class="subject-stats">
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span><span class="number"><?php echo $subject['student_count']; ?></span> Students</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-tasks"></i>
                            <span><span class="number"><?php echo $subject['assignment_count']; ?></span> Assignments</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-file-pdf"></i>
                            <span><span class="number"><?php echo $subject['material_count']; ?></span> Materials</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar-check"></i>
                            <span><span class="number"><?php echo $subject['attendance_percentage']; ?>%</span> Attendance</span>
                        </div>
                    </div>

                    <div class="subject-actions">
                        <a href="materials.php?subject=<?php echo $subject['id']; ?>" class="btn-action btn-materials">
                            <i class="fas fa-file-pdf"></i> Materials
                        </a>
                        <a href="attendance.php?subject=<?php echo $subject['id']; ?>" class="btn-action btn-attendance">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                        <a href="assignments.php?subject=<?php echo $subject['id']; ?>" class="btn-action btn-assignments">
                            <i class="fas fa-tasks"></i> Assignments
                        </a>
                        <a href="results.php?subject=<?php echo $subject['id']; ?>" class="btn-action btn-results">
                            <i class="fas fa-chart-line"></i> Results
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" data-aos="fade-up">
                <i class="fas fa-book-open"></i>
                <h5>No Classes Assigned Yet</h5>
                <p>You haven't been assigned any classes. Please contact the administrator to assign subjects to you.</p>
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

    // Close sidebar on outside click for mobile
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

    document.addEventListener('keydown', function(e) { 
        if (e.key === 'Escape') {
            document.getElementById('profileDropdown').style.display = 'none';
        }
    });

    // Quick Add Dropdown
    const quickAddBtn = document.getElementById('quickAddBtn');
    const quickMenu = document.getElementById('quickMenu');
    
    function toggleQuickMenu(e) { 
        e.stopPropagation(); 
        quickMenu.classList.toggle('active'); 
    }
    
    function closeQuickMenu() { 
        quickMenu.classList.remove('active'); 
    }
    
    if (quickAddBtn) quickAddBtn.addEventListener('click', toggleQuickMenu);
    
    document.addEventListener('click', function(e) {
        if (quickMenu && !quickMenu.contains(e.target) && !quickAddBtn.contains(e.target)) closeQuickMenu();
    });

    // Open quick add modal
    function openInQuickModal(pageUrl, title, icon = 'fa-plus-circle') {
        const modalElement = document.getElementById('quickAddModal');
        const modal = new bootstrap.Modal(modalElement);
        const modalTitle = document.getElementById('quickAddModalLabel');
        const modalBody = document.getElementById('quickAddModalBody');
        
        modalTitle.innerHTML = `<i class="fas ${icon}"></i> ${title}`;
        modalBody.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading ${title}...</p>
            </div>
        `;
        
        setTimeout(() => {
            modalBody.innerHTML = `<iframe src="${pageUrl}" style="width: 100%; height: 75vh; border: none;" title="${title}"></iframe>`;
        }, 100);
        
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
</script>

</body>
</html>