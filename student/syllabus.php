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

/* FETCH SUBJECTS WITH SYLLABUS FROM DATABASE */
$subjects = [];
$subjects_query = mysqli_query($data, "
    SELECT 
        s.id as subject_id,
        s.subject_code,
        s.subject_name,
        s.short_name,
        s.credits,
        s.description,
        COALESCE(t.name, 'To be assigned') as faculty_name,
        s.semester
    FROM subjects s
    LEFT JOIN teacher_subjects ts ON ts.subject_id = s.id
    LEFT JOIN teacher t ON t.id = ts.teacher_id
    WHERE s.branch = '$student_branch' AND s.semester = '$student_semester'
    ORDER BY s.subject_code ASC
");

$colors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
$icons = [
    'Computer' => 'fa-laptop-code',
    'Database' => 'fa-database',
    'Operating' => 'fa-windows',
    'Python' => 'fa-python',
    'Web' => 'fa-code',
    'Software' => 'fa-cogs',
    'Network' => 'fa-network-wired',
    'Math' => 'fa-calculator',
    'Physics' => 'fa-atom',
    'default' => 'fa-book'
];

if ($subjects_query && mysqli_num_rows($subjects_query) > 0) {
    $index = 0;
    while ($row = mysqli_fetch_assoc($subjects_query)) {
        // Determine color and icon
        $color = $colors[$index % count($colors)];
        $icon = $icons['default'];
        foreach ($icons as $key => $val) {
            if (stripos($row['subject_name'], $key) !== false) {
                $icon = $val;
                if ($key == 'Database') $color = 'success';
                elseif ($key == 'Operating') $color = 'warning';
                elseif ($key == 'Python') $color = 'danger';
                elseif ($key == 'Web') $color = 'info';
                elseif ($key == 'Software') $color = 'secondary';
                break;
            }
        }
        
        // Fetch syllabus units for this subject
        $syllabus_units = [];
        $syllabus_query = mysqli_query($data, "
            SELECT unit_no, unit_title, topics 
            FROM syllabus 
            WHERE subject_id = '{$row['subject_id']}'
            ORDER BY unit_no ASC
        ");
        
        if ($syllabus_query && mysqli_num_rows($syllabus_query) > 0) {
            while ($unit = mysqli_fetch_assoc($syllabus_query)) {
                $topics_array = explode(',', $unit['topics']);
                $syllabus_units[] = [
                    'unit_no' => $unit['unit_no'],
                    'title' => $unit['unit_title'],
                    'topics' => array_map('trim', $topics_array)
                ];
            }
        } else {
            // Default syllabus if not found
            $syllabus_units = [
                ['unit_no' => 1, 'title' => 'Introduction to ' . $row['subject_name'], 'topics' => ['Overview', 'Basic Concepts', 'Applications']],
                ['unit_no' => 2, 'title' => 'Core Concepts', 'topics' => ['Advanced Topics', 'Practical Implementation', 'Case Studies']],
                ['unit_no' => 3, 'title' => 'Advanced Topics', 'topics' => ['Current Trends', 'Research Areas', 'Future Directions']]
            ];
        }
        
        $subjects[] = [
            'id' => $row['subject_id'],
            'code' => $row['subject_code'],
            'name' => $row['subject_name'],
            'short_name' => $row['short_name'] ?: substr($row['subject_name'], 0, 15),
            'credits' => $row['credits'],
            'description' => $row['description'] ?: 'Course syllabus for ' . $row['subject_name'],
            'faculty' => $row['faculty_name'],
            'color' => $color,
            'icon' => $icon,
            'syllabus' => $syllabus_units,
            'textbooks' => ['Textbook 1', 'Textbook 2', 'Reference Book'],
            'references' => ['Online Resources', 'Research Papers']
        ];
        $index++;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Syllabus | Student - StudyBuddyHub</title>

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

        /* Search Bar */
        .search-bar {
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

        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
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
            height: 100%;
            display: flex;
            flex-direction: column;
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
            background: var(--light);
            color: var(--gray);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .subject-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .subject-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: rgba(79,70,229,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .subject-icon i { font-size: 1.5rem; color: var(--card-color, var(--primary)); }
        .subject-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
        }
        .subject-faculty {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--gray);
            font-size: 0.7rem;
            margin-top: 0.2rem;
        }
        .subject-faculty i { color: var(--primary); }

        /* Syllabus Accordion */
        .syllabus-section {
            margin: 1rem 0;
            flex: 1;
        }
        .unit-accordion {
            background: var(--light);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            overflow: hidden;
        }
        .unit-header {
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }
        .unit-header:hover {
            background: rgba(79,70,229,0.05);
        }
        .unit-title {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .unit-title i { color: var(--primary); }
        .unit-toggle { color: var(--gray); transition: transform 0.3s; }
        .unit-content { padding: 0 1rem 1rem; display: none; }
        .unit-content.show { display: block; }
        .topics-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .topic-item {
            padding: 0.4rem 0;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px dashed var(--border);
            font-size: 0.8rem;
        }
        .topic-item:last-child { border-bottom: none; }
        .topic-item i { color: var(--success); }

        /* Resources Section */
        .resources-section {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }
        .resources-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }
        .resources-list {
            list-style: none;
            padding: 0;
            margin: 0 0 0.75rem;
        }
        .resource-item {
            padding: 0.3rem 0;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
        }
        .resource-item i { color: var(--primary); width: 18px; }

        .btn-download {
            width: 100%;
            padding: 0.6rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-top: 1rem;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }

        /* Empty State */
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
            .subjects-grid { grid-template-columns: 1fr; }
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .search-bar { flex-direction: column; }
            .search-box, .btn-reset { width: 100%; }
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
            <a href="results.php"> <i class="fas fa-chart-line"></i> <span>Results</span> </a>

            <div class="menu-title">RESOURCES</div>
            <a href="timetable.php"> <i class="fas fa-clock"></i> <span>Time Table</span> </a>
            <a href="syllabus.php" class="active"> <i class="fas fa-list-alt"></i> <span>Syllabus</span> </a>
            
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
                <span class="page-title"><i class="fas fa-list-alt me-2" style="color: var(--primary);"></i>Syllabus</span>
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
                    <h2>📚 Course Syllabus</h2>
                    <p>Complete syllabus for Semester <?php echo $student_semester; ?> subjects.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-book"></i>
                            <span><?php echo count($subjects); ?> Subjects</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-list"></i>
                            <span>Course Outline</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo count($subjects); ?></div>
                    <div class="stat-label">Subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-number">Sem <?php echo $student_semester; ?></div>
                    <div class="stat-label">Current Semester</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number">2025-26</div>
                    <div class="stat-label">Academic Year</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-code-branch"></i></div>
                    <div class="stat-number"><?php echo htmlspecialchars($student_branch ?: 'CSE'); ?></div>
                    <div class="stat-label">Branch</div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-bar" data-aos="fade-up" data-aos-delay="100">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search subjects or topics..." onkeyup="filterSyllabus()">
                </div>
                <button class="btn-reset" onclick="resetFilters()"><i class="fas fa-times"></i> Reset</button>
            </div>

            <?php if (empty($subjects)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-book-open"></i>
                    <h5>No Syllabus Available</h5>
                    <p>Syllabus for your subjects will appear here when available.</p>
                </div>
            <?php else: ?>
                <!-- Subjects Grid -->
                <div class="subjects-grid" id="subjectsGrid" data-aos="fade-up" data-aos-delay="150">
                    <?php foreach ($subjects as $subject): 
                        $cardColor = 'var(--' . $subject['color'] . ')';
                    ?>
                    <div class="subject-card" data-subject-name="<?php echo strtolower($subject['name']); ?>" data-subject-code="<?php echo strtolower($subject['code']); ?>" style="--card-color: <?php echo $cardColor; ?>;">
                        <div class="subject-header">
                            <span class="subject-code">📖 <?php echo htmlspecialchars($subject['code']); ?></span>
                            <span class="subject-credits">⭐ <?php echo $subject['credits']; ?> Credits</span>
                        </div>

                        <div class="subject-title">
                            <div class="subject-icon">
                                <i class="fas <?php echo $subject['icon']; ?>"></i>
                            </div>
                            <div>
                                <div class="subject-name"><?php echo htmlspecialchars($subject['name']); ?></div>
                                <div class="subject-faculty">
                                    <i class="fas fa-chalkboard-user"></i>
                                    <?php echo htmlspecialchars($subject['faculty']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="syllabus-section">
                            <?php foreach ($subject['syllabus'] as $unit): ?>
                            <div class="unit-accordion">
                                <div class="unit-header" onclick="toggleUnit(this)">
                                    <span class="unit-title">
                                        <i class="fas fa-book-open"></i>
                                        Unit <?php echo $unit['unit_no']; ?>: <?php echo htmlspecialchars($unit['title']); ?>
                                    </span>
                                    <span class="unit-toggle">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                                <div class="unit-content">
                                    <ul class="topics-list">
                                        <?php foreach ($unit['topics'] as $topic): ?>
                                            <li class="topic-item">
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo htmlspecialchars($topic); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="resources-section">
                            <div class="resources-title"><i class="fas fa-book"></i> Textbooks</div>
                            <ul class="resources-list">
                                <?php foreach ($subject['textbooks'] as $book): ?>
                                    <li class="resource-item"><i class="fas fa-book"></i> <?php echo htmlspecialchars($book); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="resources-title"><i class="fas fa-link"></i> References</div>
                            <ul class="resources-list">
                                <?php foreach ($subject['references'] as $ref): ?>
                                    <li class="resource-item"><i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($ref); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <button class="btn-download" onclick="downloadSyllabus('<?php echo $subject['code']; ?>', '<?php echo htmlspecialchars($subject['name']); ?>')">
                                <i class="fas fa-download"></i> Download Syllabus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- No Results -->
                <div id="noResults" class="empty-state" style="display: none;" data-aos="fade-up">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Subjects</h5>
                    <p>Try adjusting your search criteria.</p>
                    <button class="btn-reset" onclick="resetFilters()"><i class="fas fa-times"></i> Clear Search</button>
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

    function toggleUnit(header) {
        const content = header.nextElementSibling;
        const icon = header.querySelector('.unit-toggle i');
        
        if (content.classList.contains('show')) {
            content.classList.remove('show');
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        } else {
            content.classList.add('show');
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    }

    function filterSyllabus() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.subject-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const subjectName = card.getAttribute('data-subject-name');
            const subjectCode = card.getAttribute('data-subject-code');
            const topics = card.innerText.toLowerCase();
            
            const matchesSearch = searchTerm === '' || 
                subjectName.includes(searchTerm) || 
                subjectCode.includes(searchTerm) ||
                topics.includes(searchTerm);
            
            if (matchesSearch) {
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
        const cards = document.querySelectorAll('.subject-card');
        cards.forEach(card => {
            card.style.display = 'flex';
        });
        document.getElementById('noResults').style.display = 'none';
    }

    function downloadSyllabus(code, name) {
        Swal.fire({
            title: 'Downloading...',
            text: 'Preparing syllabus for ' + name,
            icon: 'info',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
        
        // Actual download logic would go here
        setTimeout(() => {
            Swal.fire({
                title: 'Download Started',
                text: 'Your syllabus is being downloaded.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
        }, 1500);
    }
</script>

</body>
</html>