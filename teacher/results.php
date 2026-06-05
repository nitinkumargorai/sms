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

$selected_semester = isset($_GET['semester']) ? mysqli_real_escape_string($data, $_GET['semester']) : 'all';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'results';

// ============================================
// FETCH TEACHER'S SUBJECTS
// ============================================
$teacher_subjects = [];

$subject_query = mysqli_query($data, "SELECT s.* FROM subjects s 
                                      JOIN teacher_subjects ts ON s.id = ts.subject_id 
                                      WHERE ts.teacher_id = '$teacher_id' 
                                      ORDER BY s.semester ASC, s.subject_name ASC");

if ($subject_query && mysqli_num_rows($subject_query) > 0) {
    while ($row = mysqli_fetch_assoc($subject_query)) {
        $teacher_subjects[] = $row;
    }
}

// Get unique semesters
$semesters = [];
foreach ($teacher_subjects as $subject) {
    $semesters[$subject['semester']] = $subject['semester'];
}
ksort($semesters);

// Filter subjects by selected semester
$filtered_subjects = [];
if ($selected_semester === 'all') {
    $filtered_subjects = $teacher_subjects;
} else {
    foreach ($teacher_subjects as $subject) {
        if ($subject['semester'] == $selected_semester) {
            $filtered_subjects[] = $subject;
        }
    }
}

// ============================================
// FETCH STUDENTS
// ============================================
$all_students = [];
$student_query = mysqli_query($data, "SELECT id, Name, Email, Branch, Semester, mobile, created_at FROM admission WHERE Branch='$teacher_branch' ORDER BY Semester, Name");
if ($student_query && mysqli_num_rows($student_query) > 0) {
    while ($row = mysqli_fetch_assoc($student_query)) {
        $all_students[] = $row;
    }
}

// Filter students by selected semester
$filtered_students = [];
if ($selected_semester === 'all') {
    $filtered_students = $all_students;
} else {
    foreach ($all_students as $student) {
        if ($student['Semester'] == $selected_semester) {
            $filtered_students[] = $student;
        }
    }
}

// ============================================
// HANDLE RESULT ADD/UPDATE
// ============================================
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_result'])) {
    
    $student_id = mysqli_real_escape_string($data, $_POST['student_id']);
    $subject_id = mysqli_real_escape_string($data, $_POST['subject_id']);
    $exam_type = mysqli_real_escape_string($data, $_POST['exam_type']);
    $marks = (int) $_POST['marks'];
    $max_marks = (int) $_POST['max_marks'];
    $exam_date = mysqli_real_escape_string($data, $_POST['exam_date']);
    
    if ($max_marks <= 0 || $marks < 0 || $marks > $max_marks) {
        $message = "Please enter valid marks (0 to $max_marks).";
        $message_type = "error";
    } else {
        $percentage = ($marks / $max_marks) * 100;
        if ($percentage >= 90) $grade = 'A+';
        elseif ($percentage >= 80) $grade = 'A';
        elseif ($percentage >= 70) $grade = 'B+';
        elseif ($percentage >= 60) $grade = 'B';
        elseif ($percentage >= 50) $grade = 'C';
        elseif ($percentage >= 40) $grade = 'D';
        else $grade = 'F';
        
        $check_query = mysqli_query($data, "SELECT id FROM results WHERE student_id='$student_id' AND subject_id='$subject_id' AND exam_type='$exam_type'");
        
        if ($check_query && mysqli_num_rows($check_query) > 0) {
            $existing = mysqli_fetch_assoc($check_query);
            $result_id = $existing['id'];
            $save_query = "UPDATE results SET marks='$marks', max_marks='$max_marks', grade='$grade', exam_date='$exam_date' WHERE id='$result_id'";
        } else {
            $save_query = "INSERT INTO results (student_id, subject_id, exam_type, marks, max_marks, grade, exam_date)
                           VALUES ('$student_id', '$subject_id', '$exam_type', '$marks', '$max_marks', '$grade', '$exam_date')";
        }
        
        if (mysqli_query($data, $save_query)) {
            $message = "Result saved successfully! Grade: $grade";
            $message_type = "success";
            $active_tab = 'results';
        } else {
            $message = "Failed to save result: " . mysqli_error($data);
            $message_type = "error";
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $result_id = mysqli_real_escape_string($data, $_GET['delete']);
    $delete_query = mysqli_query($data, "DELETE FROM results WHERE id='$result_id'");
    if ($delete_query) {
        header("Location: results.php?semester=" . urlencode($selected_semester) . "&tab=results&msg=" . urlencode("Result deleted successfully.") . "&type=success");
    } else {
        header("Location: results.php?semester=" . urlencode($selected_semester) . "&tab=results&msg=" . urlencode("Failed to delete result.") . "&type=error");
    }
    exit();
}

// Handle message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

// ============================================
// FETCH RESULTS
// ============================================
$all_results = [];
$results_query = mysqli_query($data, "
    SELECT 
        r.id,
        r.student_id,
        r.subject_id,
        r.exam_type,
        r.marks,
        r.max_marks,
        r.grade,
        r.exam_date,
        a.Name AS student_name,
        a.id as student_id_num,
        a.Semester,
        s.subject_name,
        s.subject_code,
        s.branch,
        s.semester AS subject_semester
    FROM results r
    JOIN admission a ON a.id = r.student_id
    JOIN subjects s ON s.id = r.subject_id
    ORDER BY r.id DESC
");

if ($results_query && mysqli_num_rows($results_query) > 0) {
    while ($row = mysqli_fetch_assoc($results_query)) {
        // Generate registration number from student ID
        $row['reg_no'] = 'REG' . str_pad($row['student_id_num'], 6, '0', STR_PAD_LEFT);
        $all_results[] = $row;
    }
}

// Filter results by selected semester
$filtered_results = [];
if ($selected_semester === 'all') {
    $filtered_results = $all_results;
} else {
    foreach ($all_results as $result) {
        if ($result['subject_semester'] == $selected_semester) {
            $filtered_results[] = $result;
        }
    }
}

// Calculate stats
$total_results = count($filtered_results);
$total_students = count($filtered_students);
$avg_marks = 0;
$toppers_count = 0;

if (!empty($filtered_results)) {
    $total_marks_sum = array_sum(array_column($filtered_results, 'marks'));
    $total_max_marks = array_sum(array_column($filtered_results, 'max_marks'));
    $avg_marks = $total_max_marks > 0 ? round(($total_marks_sum / $total_max_marks) * 100) : 0;
    
    foreach ($filtered_results as $result) {
        $percentage = ($result['marks'] / $result['max_marks']) * 100;
        if ($percentage >= 90) $toppers_count++;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Results | Teacher - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
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

        /* Semester Tabs */
        .semester-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .semester-tab {
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            color: var(--gray);
            border: 1px solid var(--border);
            text-decoration: none;
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
        .semester-tab.all-tab.active {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        /* Action Tabs */
        .action-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .action-tab {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            background: transparent;
            color: var(--gray);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .action-tab:hover {
            background: var(--light);
            color: var(--dark);
        }
        .action-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        /* Add Result Card */
        .add-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            display: none;
        }
        .add-card.active { display: block; }

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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        .full-width { grid-column: span 2; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
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
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            outline: none;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79,70,229,0.3);
            color: white;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: none;
        }
        .table-card.active { display: block; }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .table-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            font-size: 0.85rem;
            border-collapse: collapse;
        }
        .table th {
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            padding: 1rem 0.75rem;
            text-align: left;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            color: var(--gray);
            border-bottom: 1px solid var(--border);
        }

        .grade-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .grade-Aplus { background: rgba(16,185,129,0.1); color: var(--success); }
        .grade-A { background: rgba(79,70,229,0.1); color: var(--primary); }
        .grade-Bplus { background: rgba(245,158,11,0.1); color: var(--warning); }
        .grade-B { background: rgba(239,68,68,0.1); color: var(--danger); }
        .grade-C { background: rgba(107,114,128,0.1); color: var(--gray); }
        .grade-D { background: rgba(107,114,128,0.1); color: var(--gray); }
        .grade-F { background: rgba(239,68,68,0.1); color: var(--danger); }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-delete { background: rgba(239,68,68,0.1); color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }

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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
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

        /* Modal */
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
            .action-tab { padding: 0.4rem 1rem; font-size: 0.8rem; }
            .semester-tab { padding: 0.4rem 0.8rem; font-size: 0.75rem; }
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
            <a href="results.php" class="active"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>
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
                <span class="page-title"><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>Results</span>
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
                    <h2>📊 Results Management</h2>
                    <p>View, add, and manage student results for your subjects.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo $total_results; ?> Total Results</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-trophy"></i>
                            <span><?php echo $toppers_count; ?> Toppers</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo $total_results; ?></div>
                    <div class="stat-label">Total Results</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star" style="color: var(--success);"></i></div>
                    <div class="stat-number"><?php echo $avg_marks; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-trophy" style="color: var(--warning);"></i></div>
                    <div class="stat-number"><?php echo $toppers_count; ?></div>
                    <div class="stat-label">Toppers (90%+)</div>
                </div>
            </div>

            <!-- SEMESTER TABS -->
            <div class="semester-tabs" data-aos="fade-up" data-aos-delay="100">
                <a href="?semester=all&tab=<?php echo $active_tab; ?>" class="semester-tab <?php echo ($selected_semester == 'all') ? 'active all-tab' : ''; ?>">
                    <i class="fas fa-globe me-1"></i> All
                </a>
                <?php for ($sem = 1; $sem <= 6; $sem++): ?>
                <a href="?semester=<?php echo $sem; ?>&tab=<?php echo $active_tab; ?>" class="semester-tab <?php echo ($selected_semester == $sem) ? 'active' : ''; ?>">
                    Sem <?php echo $sem; ?>
                </a>
                <?php endfor; ?>
            </div>

            <!-- ACTION TABS -->
            <div class="action-tabs" data-aos="fade-up" data-aos-delay="150">
                <a href="?tab=results&semester=<?php echo $selected_semester; ?>" class="action-tab <?php echo ($active_tab == 'results') ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Results
                </a>
                <a href="?tab=add&semester=<?php echo $selected_semester; ?>" class="action-tab <?php echo ($active_tab == 'add') ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> Add New Result
                </a>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- ADD RESULT CARD -->
            <div class="add-card <?php echo ($active_tab == 'add') ? 'active' : ''; ?>" data-aos="fade-up" data-aos-delay="200">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Add New Student Result</h4>
                </div>

                <form method="post">
                    <div class="form-grid">
                        <div class="full-width">
                            <label class="form-label"><i class="fas fa-user-graduate"></i> Select Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($filtered_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['Name']); ?> - Sem <?php echo $student['Semester']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="full-width">
                            <label class="form-label"><i class="fas fa-book"></i> Select Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($filtered_subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo htmlspecialchars($subject['subject_code']); ?>) - Sem <?php echo $subject['semester']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label"><i class="fas fa-clipboard-list"></i> Exam Type</label>
                            <select name="exam_type" class="form-select" required>
                                <option value="Internal Test 1">Internal Test 1</option>
                                <option value="Internal Test 2">Internal Test 2</option>
                                <option value="Mid Semester">Mid Semester</option>
                                <option value="End Semester">End Semester</option>
                                <option value="Practical">Practical</option>
                                <option value="Assignment">Assignment</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> Exam Date</label>
                            <input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div>
                            <label class="form-label"><i class="fas fa-star"></i> Marks Obtained</label>
                            <input type="number" name="marks" class="form-control" placeholder="e.g., 85" min="0" max="100" required>
                        </div>

                        <div>
                            <label class="form-label"><i class="fas fa-chart-line"></i> Maximum Marks</label>
                            <input type="number" name="max_marks" class="form-control" placeholder="e.g., 100" min="1" max="100" value="100" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="add_result" class="btn-add">
                            <i class="fas fa-save"></i> Save Result
                        </button>
                    </div>
                </form>
            </div>

            <!-- RESULTS TABLE -->
            <div class="table-card <?php echo ($active_tab == 'results') ? 'active' : ''; ?>" data-aos="fade-up" data-aos-delay="200">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list" style="color: var(--primary);"></i>
                        <?php echo ($selected_semester == 'all') ? 'All Results' : 'Semester ' . $selected_semester . ' Results'; ?>
                    </h4>
                    <span class="badge bg-primary p-2 px-3 rounded-pill">📊 <?php echo $total_results; ?> Results</span>
                </div>

                <?php if (empty($filtered_results)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h5>No Results Found</h5>
                        <p>Add results by clicking the "Add New Result" tab above.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table id="resultsTable" class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Registration No.</th>
                                <th>Subject</th>
                                <th>Exam Type</th>
                                <th>Marks</th>
                                <th>Grade</th>
                                <th>Percentage</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_results as $index => $result): 
                                $percentage = ($result['marks'] / $result['max_marks']) * 100;
                                $grade_class = '';
                                if ($result['grade'] == 'A+') $grade_class = 'grade-Aplus';
                                elseif ($result['grade'] == 'A') $grade_class = 'grade-A';
                                elseif ($result['grade'] == 'B+') $grade_class = 'grade-Bplus';
                                elseif ($result['grade'] == 'B') $grade_class = 'grade-B';
                                elseif ($result['grade'] == 'C') $grade_class = 'grade-C';
                                elseif ($result['grade'] == 'D') $grade_class = 'grade-D';
                                else $grade_class = 'grade-F';
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($result['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($result['reg_no']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($result['subject_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($result['subject_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($result['exam_type']); ?></td>
                                <td>
                                    <strong><?php echo $result['marks']; ?>/<?php echo $result['max_marks']; ?></strong>
                                  </td>
                                <td><span class="grade-badge <?php echo $grade_class; ?>"><?php echo $result['grade']; ?></span></td>
                                <td><span class="fw-bold"><?php echo round($percentage); ?>%</span></td>
                                <td><?php echo date('d M Y', strtotime($result['exam_date'])); ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="?delete=<?php echo $result['id']; ?>&semester=<?php echo urlencode($selected_semester); ?>&tab=results" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this result?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                  </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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

    // Initialize DataTable
    $(document).ready(function() {
        if ($('#resultsTable tbody tr').length > 0) {
            $('#resultsTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: "🔍 Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ results",
                    infoEmpty: "Showing 0 to 0 of 0 results",
                    infoFiltered: "(filtered from _MAX_ total results)"
                },
                responsive: true
            });
        }
    });

    // Auto-hide alert
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