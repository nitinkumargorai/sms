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
$teacher_subjects = [];
$subjects_query = mysqli_query($data, "
    SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.semester, s.branch, s.credits
    FROM teacher_subjects ts
    JOIN subjects s ON s.id = ts.subject_id
    WHERE ts.teacher_id = '$teacher_id'
    ORDER BY s.semester ASC, s.subject_name ASC
");

while ($subj = mysqli_fetch_assoc($subjects_query)) {
    $teacher_subjects[] = $subj;
}

// If no subjects found, fallback to branch subjects
if (empty($teacher_subjects) && !empty($teacher_branch)) {
    $branch_subjects = mysqli_query($data, "SELECT * FROM subjects WHERE branch='$teacher_branch' ORDER BY semester ASC, subject_name ASC");
    while ($subj = mysqli_fetch_assoc($branch_subjects)) {
        $teacher_subjects[] = $subj;
    }
}

// ============================================
// FETCH TIMETABLE FROM DATABASE
// ============================================
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$timetable = [];

foreach ($days as $day) {
    $timetable[$day] = [];
    
    $timetable_query = mysqli_query($data, "
        SELECT 
            tt.id,
            tt.start_time,
            tt.end_time,
            tt.room_no,
            tt.day,
            tt.branch,
            tt.semester,
            tt.academic_year,
            s.id as subject_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            t.name as teacher_name
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN teacher t ON t.id = tt.teacher_id
        WHERE tt.teacher_id = '$teacher_id' 
        AND tt.day = '$day'
        ORDER BY tt.start_time ASC
    ");
    
    if ($timetable_query && mysqli_num_rows($timetable_query) > 0) {
        while ($row = mysqli_fetch_assoc($timetable_query)) {
            $start = date('h:i A', strtotime($row['start_time']));
            $end = date('h:i A', strtotime($row['end_time']));
            
            $timetable[$day][] = [
                'id' => $row['id'],
                'time' => $start . ' - ' . $end,
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'subject' => $row['subject_name'],
                'code' => $row['subject_code'],
                'semester' => $row['semester'],
                'branch' => $row['branch'],
                'room' => $row['room_no'],
                'type' => 'theory',
                'teacher' => $row['teacher_name']
            ];
        }
    }
    
    // Add break for lunch if no entry exists around 12-1
    $has_break = false;
    foreach ($timetable[$day] as $class) {
        if (strpos($class['time'], '12:00') !== false || strpos($class['time'], '13:00') !== false) {
            $has_break = true;
            break;
        }
    }
    
    if (!$has_break && !empty($timetable[$day])) {
        // Insert break at appropriate position
        $break_added = false;
        for ($i = 0; $i < count($timetable[$day]); $i++) {
            if (strtotime($timetable[$day][$i]['start_time']) >= strtotime('12:00:00')) {
                array_splice($timetable[$day], $i, 0, [[
                    'time' => '12:00 - 13:00',
                    'subject' => 'Lunch Break',
                    'code' => '',
                    'semester' => '',
                    'branch' => '',
                    'room' => '',
                    'type' => 'break'
                ]]);
                $break_added = true;
                break;
            }
        }
        if (!$break_added) {
            $timetable[$day][] = [
                'time' => '12:00 - 13:00',
                'subject' => 'Lunch Break',
                'code' => '',
                'semester' => '',
                'branch' => '',
                'room' => '',
                'type' => 'break'
            ];
        }
    }
    
    // Sort by time
    usort($timetable[$day], function($a, $b) {
        if (!isset($a['start_time']) && !isset($b['start_time'])) return 0;
        if (!isset($a['start_time'])) return 1;
        if (!isset($b['start_time'])) return -1;
        return strtotime($a['start_time']) - strtotime($b['start_time']);
    });
}

// If no timetable in database, use sample data
$has_timetable = false;
foreach ($days as $day) {
    if (!empty($timetable[$day]) && count($timetable[$day]) > 0) {
        $has_timetable = true;
        break;
    }
}

if (!$has_timetable && !empty($teacher_subjects)) {
    // Generate sample timetable from assigned subjects
    $subject_index = 0;
    foreach ($days as $day) {
        if ($day == 'Saturday') {
            $timetable[$day] = [
                ['time' => '09:00 - 11:00', 'subject' => 'Lab Practice', 'code' => '', 'semester' => '', 'branch' => $teacher_branch, 'room' => 'Computer Lab', 'type' => 'lab'],
                ['time' => '11:00 - 12:00', 'subject' => 'Mentoring Session', 'code' => '', 'semester' => '', 'branch' => '', 'room' => 'Room 101', 'type' => 'mentoring'],
                ['time' => '12:00 - 13:00', 'subject' => 'Lunch Break', 'code' => '', 'semester' => '', 'branch' => '', 'room' => '', 'type' => 'break'],
                ['time' => '13:00 - 15:00', 'subject' => 'Research Work', 'code' => '', 'semester' => '', 'branch' => '', 'room' => 'Staff Room', 'type' => 'work'],
            ];
            continue;
        }
        
        $day_classes = [];
        $time_slots = ['09:00', '10:00', '11:00', '13:00', '14:00'];
        
        foreach ($time_slots as $idx => $start) {
            $end = date('H:i', strtotime($start . ' +1 hour'));
            $subject = $teacher_subjects[$subject_index % count($teacher_subjects)];
            
            if ($start == '13:00') {
                $day_classes[] = [
                    'time' => '12:00 - 13:00',
                    'subject' => 'Lunch Break',
                    'code' => '',
                    'semester' => '',
                    'branch' => '',
                    'room' => '',
                    'type' => 'break'
                ];
            }
            
            $day_classes[] = [
                'time' => date('h:i A', strtotime($start)) . ' - ' . date('h:i A', strtotime($end)),
                'start_time' => $start . ':00',
                'end_time' => $end . ':00',
                'subject' => $subject['subject_name'],
                'code' => $subject['subject_code'],
                'semester' => $subject['semester'],
                'branch' => $subject['branch'],
                'room' => ($idx % 2 == 0) ? 'Lab ' . (100 + $idx) : 'Room ' . (200 + $idx),
                'type' => ($idx % 2 == 0) ? 'practical' : 'theory'
            ];
            $subject_index++;
        }
        $timetable[$day] = $day_classes;
    }
}

// Calculate statistics
$total_subjects = count($teacher_subjects);
$total_hours = 0;
$theory_hours = 0;
$practical_hours = 0;

foreach ($days as $day) {
    foreach ($timetable[$day] as $class) {
        if (isset($class['type']) && $class['type'] != 'break') {
            $total_hours++;
            if ($class['type'] == 'theory') $theory_hours++;
            if ($class['type'] == 'practical') $practical_hours++;
        }
    }
}

$total_hours = $total_hours * 1; // Each class is 1 hour
$current_day = date('l');
$current_time = date('H:i:s');

// Find current class
$current_class = null;
if (isset($timetable[$current_day])) {
    foreach ($timetable[$current_day] as $class) {
        if (isset($class['start_time']) && isset($class['end_time'])) {
            if ($current_time >= $class['start_time'] && $current_time <= $class['end_time']) {
                $current_class = $class;
                break;
            }
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Timetable | Teacher - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
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

        /* Current Class Card */
        .current-class-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .current-class-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        
        .current-class-label {
            font-size: 0.75rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .current-class-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .current-class-time {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .current-class-time span {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        /* Day Tabs */
        .day-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .day-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            color: var(--gray);
            border: 1px solid var(--border);
            font-size: 0.9rem;
        }
        .day-tab:hover {
            background: var(--light);
            color: var(--dark);
            border-color: var(--primary-light);
        }
        .day-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }
        .day-tab.today {
            border: 2px solid var(--primary);
        }

        /* Timetable Card */
        .timetable-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            display: none;
        }
        .timetable-card.active { display: block; }

        .day-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        .day-header h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .today-badge {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .timetable-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .class-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 16px;
            transition: var(--transition);
            border-left: 4px solid var(--row-color, var(--primary));
        }
        
        @media (max-width: 768px) {
            .class-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
        
        .class-row:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }
        
        .break-row {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        
        .class-time {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .class-time i { color: var(--primary); }
        
        .class-details {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .class-details {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
        
        .class-subject {
            font-weight: 700;
            color: var(--dark);
        }
        .class-code {
            font-size: 0.7rem;
            color: var(--gray);
            margin-left: 0.5rem;
        }
        .class-semester, .class-room {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
            font-size: 0.8rem;
        }
        .class-semester i, .class-room i { color: var(--primary); width: 16px; }
        
        .class-type-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .type-theory { background: rgba(79,70,229,0.1); color: var(--primary); }
        .type-practical { background: rgba(16,185,129,0.1); color: var(--success); }
        .type-lab { background: rgba(59,130,246,0.1); color: var(--info); }
        .type-break { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .type-mentoring { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .type-work { background: rgba(107,114,128,0.1); color: var(--gray); }

        /* Legend Card */
        .legend-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .legend-title {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
        }
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        .color-primary { background: var(--primary); }
        .color-success { background: var(--success); }
        .color-info { background: var(--info); }
        .color-warning { background: #f59e0b; }
        .color-secondary { background: #8b5cf6; }
        .color-gray { background: var(--gray); }

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
            .day-tab { padding: 0.5rem 1rem; font-size: 0.8rem; }
            .current-class-name { font-size: 1.2rem; }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h5 { font-size: 1.2rem; color: var(--dark); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--gray); }
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
                <a href="students.php"> <i class="fas fa-user-graduate"></i> <span>My Students</span> </a>

                <div class="menu-title">RESOURCES</div>
                <a href="syllabus.php"> <i class="fas fa-list-alt"></i> <span>Syllabus</span> </a>
                <a href="timetable.php" class="active"> <i class="fas fa-clock"></i> <span>Time Table</span> </a>
                <a href="profile.php"> <i class="fas fa-user"></i> <span>Profile</span> </a>
                <a href="settings.php"> <i class="fas fa-cog"></i> <span>Settings</span> </a>
            </div>
        </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="menu-toggle" onclick="toggleSidebar()"> <i class="fas fa-bars"></i> </button>
                <span class="page-title"><i class="fas fa-clock me-2" style="color: var(--primary);"></i>Time Table</span>
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
                    <h2>📅 Weekly Timetable</h2>
                    <p>Your teaching schedule for the current semester.</p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <i class="fas fa-book"></i>
                            <span><?php echo $total_subjects; ?> Subjects</span>
                        </div>
                        <div class="welcome-stat">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $total_hours; ?> Hours/Week</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $total_hours; ?></div>
                    <div class="stat-label">Hours/Week</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
                    <div class="stat-number"><?php echo $theory_hours; ?></div>
                    <div class="stat-label">Theory Hours</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-flask"></i></div>
                    <div class="stat-number"><?php echo $practical_hours; ?></div>
                    <div class="stat-label">Practical Hours</div>
                </div>
            </div>

            <!-- Current Class -->
            <?php if ($current_class && isset($current_class['subject']) && $current_class['subject'] != 'Lunch Break'): ?>
            <div class="current-class-card" data-aos="fade-up" data-aos-delay="100">
                <div class="current-class-label"><i class="fas fa-play-circle"></i> Currently Ongoing</div>
                <div class="current-class-name"><?php echo htmlspecialchars($current_class['subject']); ?></div>
                <div class="current-class-time">
                    <span><i class="far fa-clock"></i> <?php echo $current_class['time']; ?></span>
                    <?php if (isset($current_class['room'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $current_class['room']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Day Tabs -->
            <div class="day-tabs" data-aos="fade-up" data-aos-delay="150">
                <?php foreach ($days as $index => $day): ?>
                <button class="day-tab <?php echo ($day == $current_day) ? 'today' : ''; ?> <?php echo $index == 0 ? 'active' : ''; ?>" onclick="showDay('<?php echo $day; ?>', this)">
                    <?php echo substr($day, 0, 3); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Timetable Cards -->
            <?php foreach ($days as $day_index => $day): ?>
            <div class="timetable-card" id="day-<?php echo $day; ?>" data-aos="fade-up" data-aos-delay="200">
                <div class="day-header">
                    <h4><i class="fas fa-calendar-day"></i> <?php echo $day; ?></h4>
                    <?php if ($day == $current_day): ?>
                        <span class="today-badge"><i class="fas fa-sun"></i> Today</span>
                    <?php endif; ?>
                </div>

                <div class="timetable-grid">
                    <?php if (empty($timetable[$day])): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <h5>No Classes Scheduled</h5>
                            <p>No classes have been scheduled for <?php echo $day; ?>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($timetable[$day] as $class): 
                            $type_class = 'type-' . ($class['type'] ?? 'theory');
                            $break_class = (isset($class['type']) && $class['type'] == 'break') ? 'break-row' : '';
                            $row_color = '#4f46e5';
                            if (isset($class['type'])) {
                                if ($class['type'] == 'theory') $row_color = '#4f46e5';
                                elseif ($class['type'] == 'practical') $row_color = '#10b981';
                                elseif ($class['type'] == 'lab') $row_color = '#3b82f6';
                                elseif ($class['type'] == 'break') $row_color = '#f59e0b';
                            }
                        ?>
                        <div class="class-row <?php echo $break_class; ?>" style="--row-color: <?php echo $row_color; ?>;">
                            <div class="class-time">
                                <i class="far fa-clock"></i>
                                <?php echo $class['time']; ?>
                            </div>
                            <div class="class-details">
                                <div>
                                    <span class="class-subject"><?php echo htmlspecialchars($class['subject']); ?></span>
                                    <?php if (!empty($class['code'])): ?>
                                        <span class="class-code"><?php echo $class['code']; ?></span>
                                    <?php endif; ?>
                                    <?php if (isset($class['type']) && $class['type'] != 'break'): ?>
                                        <span class="class-type-badge <?php echo $type_class; ?>">
                                            <?php echo ucfirst($class['type']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($class['semester'])): ?>
                                <div class="class-semester">
                                    <i class="fas fa-layer-group"></i>
                                    Sem <?php echo $class['semester']; ?> | <?php echo $class['branch']; ?>
                                </div>
                                <?php else: ?>
                                <div></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($class['room'])): ?>
                                <div class="class-room">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo $class['room']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Legend -->
            <div class="legend-card" data-aos="fade-up" data-aos-delay="250">
                <div class="legend-title">
                    <i class="fas fa-info-circle me-2" style="color: var(--primary);"></i>
                    Class Type Legend
                </div>
                <div class="legend-items">
                    <div class="legend-item"><div class="legend-color color-primary"></div><span>Theory</span></div>
                    <div class="legend-item"><div class="legend-color color-success"></div><span>Practical</span></div>
                    <div class="legend-item"><div class="legend-color color-info"></div><span>Lab</span></div>
                    <div class="legend-item"><div class="legend-color color-warning"></div><span>Break</span></div>
                    <div class="legend-item"><div class="legend-color color-secondary"></div><span>Mentoring</span></div>
                    <div class="legend-item"><div class="legend-color color-gray"></div><span>Work</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Profile Dropdown -->
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
                <a href="syllabus.php"><i class="fas fa-list-alt"></i> Syllabus</a>
                <a href="students.php"><i class="fas fa-user-graduate"></i> My Students</a>
                <a href="timetable.php"><i class="fas fa-clock"></i> Time Table</a>
                <hr>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

<!-- Quick Add Modal -->
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

    // Show selected day
    function showDay(day, element) {
        // Hide all timetable cards
        document.querySelectorAll('.timetable-card').forEach(card => {
            card.style.display = 'none';
        });
        
        // Show selected day's card
        const selectedCard = document.getElementById('day-' + day);
        if (selectedCard) {
            selectedCard.style.display = 'block';
        }
        
        // Update active tab
        document.querySelectorAll('.day-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        element.classList.add('active');
    }

    // Activate today's day on load
    const today = '<?php echo $current_day; ?>';
    const todayTab = document.querySelector('.day-tab.today');
    if (todayTab) {
        todayTab.click();
    } else {
        document.querySelector('.day-tab')?.click();
    }
</script>

</body>
</html>