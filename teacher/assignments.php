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

// Get teacher's subjects for filtering
$teacher_subjects = [];
$subjects_query = mysqli_query($data, "
    SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.semester 
    FROM subjects s
    JOIN assignments a ON a.subject_id = s.id
    WHERE a.teacher_id = '$teacher_id'
    ORDER BY s.semester ASC, s.subject_name ASC
");

$unique_subjects = [];
if ($subjects_query) {
    while ($row = mysqli_fetch_assoc($subjects_query)) {
        $unique_subjects[] = $row;
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

$selected_semester = isset($_GET['semester']) ? mysqli_real_escape_string($data, $_GET['semester']) : 'all';
$selected_subject = isset($_GET['subject']) ? mysqli_real_escape_string($data, $_GET['subject']) : 'all';

$message = "";
$message_type = "";

// Handle delete
if (isset($_GET['delete'])) {
    $assignment_id = mysqli_real_escape_string($data, $_GET['delete']);

    $get_file = mysqli_query($data, "SELECT file_path FROM assignments WHERE id='$assignment_id' AND teacher_id='$teacher_id'");
    if ($row = mysqli_fetch_assoc($get_file)) {
        if (!empty($row['file_path']) && file_exists("../" . $row['file_path'])) {
            unlink("../" . $row['file_path']);
        }
    }

    mysqli_query($data, "DELETE FROM assignments WHERE id='$assignment_id' AND teacher_id='$teacher_id'");
    header("Location: assignments.php?msg=" . urlencode("Assignment deleted successfully.") . "&type=success&semester=" . $selected_semester . "&subject=" . $selected_subject);
    exit();
}

// Handle Edit Assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_assignment'])) {
    $assignment_id = mysqli_real_escape_string($data, $_POST['assignment_id']);
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $description = mysqli_real_escape_string($data, trim($_POST['description']));
    $due_date = mysqli_real_escape_string($data, $_POST['due_date']);
    $due_time = mysqli_real_escape_string($data, $_POST['due_time']);
    $total_marks = intval($_POST['total_marks']);

    $file_path = $_POST['existing_file_path'] ?? '';

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] == 0) {
        $target_dir = "../uploads/assignments/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['assignment_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $target_file)) {
                if (!empty($file_path) && file_exists("../" . $file_path)) {
                    unlink("../" . $file_path);
                }
                $file_path = "uploads/assignments/" . $new_filename;
            }
        }
    }

    $update_sql = "UPDATE assignments SET 
                   title = '$title',
                   description = '$description',
                   due_date = '$due_date',
                   due_time = '$due_time',
                   total_marks = $total_marks,
                   file_path = '$file_path'
                   WHERE id = $assignment_id AND teacher_id = '$teacher_id'";

    if (mysqli_query($data, $update_sql)) {
        $message = "Assignment updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: assignments.php?msg=" . urlencode($message) . "&type=" . $message_type . "&semester=" . $selected_semester . "&subject=" . $selected_subject);
    exit();
}

// Get assignments with filters
$all_assignments = [];
$where_clause = "WHERE a.teacher_id = '$teacher_id'";

if ($selected_semester !== 'all') {
    $where_clause .= " AND s.semester = '$selected_semester'";
}
if ($selected_subject !== 'all') {
    $where_clause .= " AND a.subject_id = '$selected_subject'";
}

$assignment_query = mysqli_query($data, "
    SELECT 
        a.id,
        a.subject_id,
        s.subject_name,
        s.semester,
        a.title,
        a.description,
        a.due_date,
        a.due_time,
        a.total_marks,
        a.file_path,
        a.created_at,
        COUNT(DISTINCT sub.id) AS submissions,
        (
            SELECT COUNT(*)
            FROM admission adm
            WHERE adm.Branch = s.branch AND adm.Semester = s.semester
        ) AS total_students
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    LEFT JOIN submissions sub ON sub.assignment_id = a.id
    $where_clause
    GROUP BY a.id
    ORDER BY a.created_at DESC
");

if ($assignment_query) {
    while ($row = mysqli_fetch_assoc($assignment_query)) {
        $row['submissions'] = (int) $row['submissions'];
        $row['total_students'] = (int) $row['total_students'];
        $row['pending'] = max(0, $row['total_students'] - $row['submissions']);
        $all_assignments[] = $row;
    }
}

$filtered_assignments = $all_assignments;

// Calculate stats
$total_assignments = count($filtered_assignments);
$total_submissions = array_sum(array_column($filtered_assignments, 'submissions'));
$total_pending = array_sum(array_column($filtered_assignments, 'pending'));
$avg_submission_rate = $total_assignments > 0 ? round(($total_submissions / ($total_assignments * 30)) * 100) : 0;

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
    <title>Assignments | Teacher - StudyBuddyHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray: #6b7280;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 280px;
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

        .sidebar.active {
            left: 0;
        }

        @media (min-width: 769px) {
            .sidebar {
                left: 0;
            }
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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
            color: rgba(255, 255, 255, 0.4);
            margin-top: 0.25rem;
        }

        .sidebar-menu {
            padding: 0.5rem 0 1rem;
        }

        .menu-title {
            padding: 0.75rem 1.25rem 0.5rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            font-size: 0.85rem;
        }

        .sidebar a i {
            width: 22px;
            font-size: 1rem;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary-light);
        }

        .sidebar a.active {
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.15), transparent);
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

        @media (min-width: 769px) {
            .main {
                margin-left: var(--sidebar-width);
            }
        }

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

        .menu-toggle:hover {
            background: var(--light);
        }

        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

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

        .quick-add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }

        .quick-dropdown {
            position: relative;
        }

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

        .quick-menu-header span {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
        }

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

        .quick-menu-item i {
            width: 22px;
            color: var(--primary);
            font-size: 1rem;
        }

        .teacher-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.8rem;
            background: var(--light);
            border-radius: 50px;
            cursor: pointer;
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

        .teacher-info {
            display: none;
        }

        @media (min-width: 576px) {
            .teacher-info {
                display: block;
            }

            .teacher-name {
                font-weight: 600;
                font-size: 0.9rem;
                color: var(--dark);
            }

            .teacher-role {
                font-size: 0.7rem;
                color: var(--primary);
            }
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
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

        .logout-btn:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
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
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .content {
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
        }

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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 6s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.3;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.6;
            }
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
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
        }

        .welcome-banner p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .welcome-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-stat {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .welcome-stat i {
            font-size: 1rem;
        }

        .welcome-stat span {
            font-size: 0.85rem;
            font-weight: 500;
        }

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
            box-shadow: var(--shadow);
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
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(79, 70, 229, 0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: var(--primary);
        }

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

        /* Enhanced Filter Section */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .filter-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn-subject {
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--border);
        }

        .filter-btn-subject:hover,
        .filter-btn-subject.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        .filter-btn-semester {
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--border);
        }

        .filter-btn-semester:hover,
        .filter-btn-semester.active {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        .filter-btn-all {
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--border);
        }

        .filter-btn-all:hover,
        .filter-btn-all.active {
            background: linear-gradient(135deg, var(--warning), #ea580c);
            color: white;
            border-color: transparent;
        }

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

        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

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

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            font-size: 0.85rem;
        }

        .table th {
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            color: var(--gray);
        }

        .badge-subject {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }

        .badge-semester {
            background: var(--light);
            color: var(--gray);
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
            white-space: nowrap;
        }

        .due-badge {
            padding: 0.25rem 0.7rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .due-soon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .due-today {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .due-future {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .progress-small {
            width: 60px;
            height: 4px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
        }

        .pending-count {
            background: var(--danger);
            color: white;
            padding: 0.1rem 0.4rem;
            border-radius: 50px;
            font-size: 0.65rem;
            display: inline-block;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .btn-view:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .btn-edit:hover {
            background: var(--warning);
            color: white;
            transform: translateY(-2px);
        }

        .btn-check {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .btn-check:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }

        .btn-download {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .btn-download:hover {
            background: #8b5cf6;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .btn-submissions {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        .btn-submissions:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

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
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-custom.active {
            display: flex;
        }

        .modal-content-custom {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header-custom {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 24px 24px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.3rem;
            display: block;
            font-size: 0.85rem;
            color: var(--dark);
        }

        .form-group label i {
            margin-right: 0.3rem;
            color: var(--primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-cancel {
            background: var(--gray);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* Submissions Table in Modal */
        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .submissions-table th {
            text-align: left;
            padding: 0.75rem;
            background: var(--light);
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--dark);
        }

        .submissions-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.75rem;
        }

        .submissions-table tr:hover {
            background: var(--light);
        }

        .status-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 500;
        }

        .status-submitted {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-late {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status-graded {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

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
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .profile-dropdown-info h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .profile-dropdown-info p {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 0;
        }

        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }

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

        .profile-dropdown-menu a:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        .profile-dropdown-menu a i {
            width: 22px;
            color: var(--primary);
            font-size: 1rem;
        }

        .profile-dropdown-menu hr {
            margin: 0.5rem 0;
            border-color: var(--border);
        }

        .modal-content-iframe {
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }

        .modal-header-gradient .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body-iframe {
            padding: 0;
            max-height: 80vh;
        }

        .modal-body-iframe iframe {
            width: 100%;
            height: 75vh;
            border: none;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .file-info {
            background: var(--light);
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                gap: 1rem;
            }

            .profile-dropdown {
                right: 10px;
                left: 10px;
                min-width: auto;
            }

            .semester-tab {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }

            .table th,
            .table td {
                white-space: nowrap;
            }

            .filter-buttons {
                gap: 0.3rem;
            }

            .filter-btn {
                padding: 0.3rem 0.7rem;
                font-size: 0.7rem;
            }
        }
    </style>
</head>

<body>

    <div class="teacher-wrap">

        <!-- SIDEBAR -->
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
                <a href="assignments.php" class="active"> <i class="fas fa-tasks"></i> <span>Assignments</span>
                </a>
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
                    <span class="page-title"><i class="fas fa-tasks me-2"
                            style="color: var(--primary);"></i>Assignments</span>
                </div>
                <div class="topbar-actions">
                    <div class="quick-dropdown">
                        <button class="quick-add-btn" id="quickAddBtn">
                            <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down"
                                style="font-size: 0.7rem;"></i>
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
                    <a href="../logout.php" class="logout-btn"> <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </div>
            </div>

            <div class="content">
                <div class="welcome-banner" data-aos="fade-up">
                    <div class="welcome-content">
                        <h2>📋 Assignments</h2>
                        <p>Manage, track, and grade your assignments.</p>
                        <div class="welcome-stats">
                            <div class="welcome-stat"><i
                                    class="fas fa-tasks"></i><span><?php echo $total_assignments; ?> Total
                                    Assignments</span></div>
                            <div class="welcome-stat"><i class="fas fa-clock"></i><span><?php echo $total_pending; ?>
                                    Pending Submissions</span></div>
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
                        <div class="stat-number"><?php echo $total_submissions; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-number"><?php echo $total_pending; ?></div>
                        <div class="stat-label">Pending Grading</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-number"><?php echo $avg_submission_rate; ?>%</div>
                        <div class="stat-label">Submission Rate</div>
                    </div>
                </div>

                <!-- ENHANCED FILTER SECTION -->
                <div class="filter-section" data-aos="fade-up" data-aos-delay="100">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> Filter Assignments
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted"><i class="fas fa-calendar-alt"></i>
                                Semester</label>
                            <div class="filter-buttons">
                                <a href="?semester=all&subject=<?php echo $selected_subject; ?>"
                                    class="filter-btn filter-btn-semester <?php echo ($selected_semester == 'all') ? 'active' : ''; ?>">
                                    <i class="fas fa-globe"></i> All Semesters
                                </a>
                                <?php for ($sem = 1; $sem <= 6; $sem++): ?>
                                    <a href="?semester=<?php echo $sem; ?>&subject=<?php echo $selected_subject; ?>"
                                        class="filter-btn filter-btn-semester <?php echo ($selected_semester == $sem) ? 'active' : ''; ?>">
                                        Semester <?php echo $sem; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted"><i class="fas fa-book"></i> Subject</label>
                            <div class="filter-buttons">
                                <a href="?semester=<?php echo $selected_semester; ?>&subject=all"
                                    class="filter-btn filter-btn-subject <?php echo ($selected_subject == 'all') ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i> All Subjects
                                </a>
                                <?php foreach ($unique_subjects as $subject): ?>
                                    <a href="?semester=<?php echo $selected_semester; ?>&subject=<?php echo $subject['id']; ?>"
                                        class="filter-btn filter-btn-subject <?php echo ($selected_subject == $subject['id']) ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message != ""): ?>
                    <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>"
                        data-aos="fade-up">
                        <i
                            class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                <?php endif; ?>

                <!-- ASSIGNMENTS LIST -->
                <div class="table-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="table-header">
                        <h4><i class="fas fa-list" style="color: var(--primary);"></i>
                            <?php
                            if ($selected_semester != 'all' && $selected_subject != 'all') {
                                echo 'Assignments - Semester ' . $selected_semester . ' / Selected Subject';
                            } elseif ($selected_semester != 'all') {
                                echo 'Semester ' . $selected_semester . ' Assignments';
                            } elseif ($selected_subject != 'all') {
                                echo 'Selected Subject Assignments';
                            } else {
                                echo 'All Assignments';
                            }
                            ?>
                        </h4>
                        <span class="badge bg-primary p-2 px-3 rounded-pill">📋 <?php echo $total_assignments; ?>
                            Assignments</span>
                    </div>

                    <?php if (empty($filtered_assignments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h5>No Assignments Found</h5>
                            <p>Use the Quick Add button above to create your first assignment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="assignmentsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 15%">Subject</th>
                                        <th style="width: 25%">Assignment Details</th>
                                        <th style="width: 15%">Due Date & Time</th>
                                        <th style="width: 8%">Marks</th>
                                        <th style="width: 12%">Submissions</th>
                                        <th style="width: 8%">Actions</th>
                                    <tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_assignments as $index => $assignment):
                                        $today = strtotime(date('Y-m-d'));
                                        $due = strtotime($assignment['due_date']);
                                        $days_diff = round(($due - $today) / (60 * 60 * 24));

                                        if ($days_diff < 0) {
                                            $due_class = 'due-today';
                                            $due_text = 'Overdue';
                                        } elseif ($days_diff == 0) {
                                            $due_class = 'due-today';
                                            $due_text = 'Today';
                                        } elseif ($days_diff <= 3) {
                                            $due_class = 'due-soon';
                                            $due_text = $days_diff . ' days left';
                                        } else {
                                            $due_class = 'due-future';
                                            $due_text = $days_diff . ' days left';
                                        }

                                        $submission_percent = $assignment['total_students'] > 0 ? round(($assignment['submissions'] / $assignment['total_students']) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['subject_name']); ?></strong>
                                                <br><span class="badge-semester">Sem
                                                    <?php echo $assignment['semester']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                                <?php if (!empty($assignment['description'])): ?>
                                                    <br><small
                                                        class="text-muted"><?php echo htmlspecialchars(substr($assignment['description'], 0, 60));
                                                        if (strlen($assignment['description']) > 60)
                                                            echo '...'; ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($assignment['file_path'])): ?>
                                                    <br><small><i class="fas fa-paperclip"></i> File attached</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="due-badge <?php echo $due_class; ?>">
                                                    <?php echo date('d M Y', strtotime($assignment['due_date'])); ?>
                                                </span>
                                                <br><small><i class="fas fa-clock"></i>
                                                    <?php echo date('h:i A', strtotime($assignment['due_time'])); ?></small>
                                                <br><small class="text-muted"><?php echo $due_text; ?></small>
                                            </td>
                                            <td><span class="fw-bold"><?php echo $assignment['total_marks']; ?></span> marks
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span><?php echo $assignment['submissions']; ?>/<?php echo $assignment['total_students']; ?></span>
                                                    <div class="progress-small">
                                                        <div class="progress-fill"
                                                            style="width: <?php echo $submission_percent; ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($assignment['pending'] > 0): ?>
                                                    <span class="pending-count"><?php echo $assignment['pending']; ?> pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">All Submitted</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button class="btn-action btn-view"
                                                        onclick='viewAssignmentDetails(<?php echo json_encode($assignment); ?>)'>
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn-action btn-edit"
                                                        onclick='openEditModal(<?php echo json_encode($assignment); ?>)'>
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn-action btn-submissions"
                                                        onclick="viewSubmissions(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')">
                                                        <i class="fas fa-users"></i> Submissions
                                                    </button>
                                                    <?php if (!empty($assignment['file_path'])): ?>
                                                        <a href="../<?php echo $assignment['file_path']; ?>"
                                                            class="btn-action btn-download" download>
                                                            <i class="fas fa-download"></i> File
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?delete=<?php echo $assignment['id']; ?>&semester=<?php echo urlencode($selected_semester); ?>&subject=<?php echo urlencode($selected_subject); ?>"
                                                        class="btn-action btn-delete"
                                                        onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown Menu -->
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
                        <p class="mt-1"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($teacher_branch); ?>
                        </p><?php endif; ?>
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
            </div>
        </div>

        <!-- VIEW ASSIGNMENT MODAL -->
        <div class="modal-custom" id="viewModal">
            <div class="modal-content-custom">
                <div class="modal-header-custom">
                    <h5><i class="fas fa-file-alt"></i> Assignment Details</h5>
                    <button class="modal-close" onclick="closeViewModal()">&times;</button>
                </div>
                <div class="modal-body-custom" id="viewModalBody"></div>
                <div class="modal-footer"
                    style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                    <button class="btn-cancel" onclick="closeViewModal()">Close</button>
                </div>
            </div>
        </div>

        <!-- EDIT ASSIGNMENT MODAL -->
        <div class="modal-custom" id="editModal">
            <div class="modal-content-custom">
                <div class="modal-header-custom">
                    <h5><i class="fas fa-edit"></i> Edit Assignment</h5>
                    <button class="modal-close" onclick="closeEditModal()">&times;</button>
                </div>
                <form method="post" id="editAssignmentForm" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    <input type="hidden" name="edit_assignment" value="1">
                    <input type="hidden" name="existing_file_path" id="edit_existing_file_path">
                    <div class="modal-body-custom">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"
                                required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-alt"></i> Due Date</label>
                                    <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> Due Time</label>
                                    <input type="time" name="due_time" id="edit_due_time" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Total Marks</label>
                            <input type="number" name="total_marks" id="edit_total_marks" class="form-control" min="1"
                                max="100" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-paperclip"></i> Assignment File (Optional)</label>
                            <input type="file" name="assignment_file" id="edit_assignment_file" class="form-control"
                                accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png">
                            <small class="text-muted">Leave empty to keep current file. Max size: 10MB</small>
                            <div id="current_file_info" class="file-info"></div>
                        </div>
                    </div>
                    <div class="modal-footer"
                        style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Assignment</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SUBMISSIONS MODAL -->
        <div class="modal-custom" id="submissionsModal">
            <div class="modal-content-custom" style="max-width: 900px;">
                <div class="modal-header-custom">
                    <h5><i class="fas fa-users"></i> Student Submissions: <span id="submissionsAssignmentTitle"></span>
                    </h5>
                    <button class="modal-close" onclick="closeSubmissionsModal()">&times;</button>
                </div>
                <div class="modal-body-custom" id="submissionsModalBody">
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading submissions...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ADD MODAL -->
        <div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content modal-content-iframe">
                    <div class="modal-header modal-header-gradient">
                        <h5 class="modal-title" id="quickAddModalLabel"><i class="fas fa-plus-circle"></i> Loading...
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-iframe" id="quickAddModalBody">
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status"><span
                                    class="visually-hidden">Loading...</span></div>
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

            document.addEventListener('click', function (e) {
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
            document.addEventListener('click', function (e) {
                if (quickMenu && !quickMenu.contains(e.target) && !quickAddBtn.contains(e.target)) closeQuickMenu();
            });

            function openInQuickModal(pageUrl, title, icon = 'fa-plus-circle') {
                const modal = new bootstrap.Modal(document.getElementById('quickAddModal'));
                document.getElementById('quickAddModalLabel').innerHTML = `<i class="fas ${icon}"></i> ${title}`;
                document.getElementById('quickAddModalBody').innerHTML = `<iframe src="${pageUrl}" style="width: 100%; height: 75vh; border: none;" title="${title}"></iframe>`;
                modal.show();
            }

            document.querySelectorAll('.quick-menu-item').forEach(item => {
                item.addEventListener('click', function (e) {
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

            function viewAssignmentDetails(assignment) {
                const fileHtml = assignment.file_path ? `
            <div class="detail-row" style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-paperclip"></i> Attached File</div>
                <div class="detail-value">
                    <a href="../${assignment.file_path}" class="btn-action btn-download" download style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                        <i class="fas fa-download"></i> Download Assignment File
                    </a>
                </div>
            </div>
        ` : '';

                const modalBody = document.getElementById('viewModalBody');
                modalBody.innerHTML = `
            <div class="text-center mb-3">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-alt fa-2x" style="color: white;"></i>
                </div>
                <h5 style="font-weight: 700; color: var(--dark);">${escapeHtml(assignment.title)}</h5>
                <span class="badge-subject">${escapeHtml(assignment.subject_name)} (Sem ${assignment.semester})</span>
            </div>
            <div class="detail-row" style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-align-left"></i> Description</div>
                <div class="detail-value" style="font-size: 0.9rem; font-weight: 500; color: var(--dark);">${escapeHtml(assignment.description) || 'No description provided.'}</div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-row" style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                        <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-calendar-alt"></i> Due Date & Time</div>
                        <div class="detail-value" style="font-size: 0.9rem; font-weight: 500; color: var(--dark);">
                            ${new Date(assignment.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}<br>
                            <small>${assignment.due_time}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-row" style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                        <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-star"></i> Total Marks</div>
                        <div class="detail-value" style="font-size: 0.9rem; font-weight: 500; color: var(--dark);">${assignment.total_marks} marks</div>
                    </div>
                </div>
            </div>
            ${fileHtml}
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-row" style="margin-bottom: 1rem;">
                        <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-users"></i> Submissions</div>
                        <div class="detail-value" style="font-size: 0.9rem; font-weight: 500; color: var(--dark);">
                            ${assignment.submissions} / ${assignment.total_students} students
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-row" style="margin-bottom: 1rem;">
                        <div class="detail-label" style="font-size: 0.7rem; color: var(--gray); text-transform: uppercase; margin-bottom: 0.25rem;"><i class="fas fa-hourglass-half"></i> Pending</div>
                        <div class="detail-value" style="font-size: 0.9rem; font-weight: 500; color: var(--dark);">
                            ${assignment.pending} students pending
                        </div>
                    </div>
                </div>
            </div>
        `;
                document.getElementById('viewModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeViewModal() {
                document.getElementById('viewModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            function openEditModal(assignment) {
                document.getElementById('edit_assignment_id').value = assignment.id;
                document.getElementById('edit_title').value = assignment.title;
                document.getElementById('edit_description').value = assignment.description || '';
                document.getElementById('edit_due_date').value = assignment.due_date;
                document.getElementById('edit_due_time').value = assignment.due_time;
                document.getElementById('edit_total_marks').value = assignment.total_marks;
                document.getElementById('edit_existing_file_path').value = assignment.file_path || '';

                const fileInfoDiv = document.getElementById('current_file_info');
                if (assignment.file_path) {
                    const fileName = assignment.file_path.split('/').pop();
                    fileInfoDiv.innerHTML = `<i class="fas fa-paperclip"></i> Current file: <strong>${fileName}</strong><br>
                                     <small class="text-muted">Upload a new file to replace it</small>`;
                    fileInfoDiv.style.display = 'block';
                } else {
                    fileInfoDiv.innerHTML = '<i class="fas fa-info-circle"></i> No file attached currently';
                    fileInfoDiv.style.display = 'block';
                }

                document.getElementById('editModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            function viewSubmissions(assignmentId, assignmentTitle) {
                document.getElementById('submissionsAssignmentTitle').innerHTML = assignmentTitle;
                document.getElementById('submissionsModalBody').innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading submissions...</p>
            </div>
        `;
                document.getElementById('submissionsModal').classList.add('active');
                document.body.style.overflow = 'hidden';

                $.ajax({
                    url: 'get_submissions.php',
                    type: 'GET',
                    data: { assignment_id: assignmentId },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success && data.submissions.length > 0) {
                            let html = `
                        <div class="mb-3">
                            <div class="alert alert-info">
                                <strong>Summary:</strong> ${data.submissions.length} students have submitted this assignment.
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="submissions-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Registration No</th>
                                        <th>Submitted On</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                            data.submissions.forEach(sub => {
                                let statusClass = '';
                                let statusText = '';
                                if (sub.status === 'submitted') {
                                    statusClass = 'status-submitted';
                                    statusText = 'Submitted';
                                } else if (sub.status === 'late') {
                                    statusClass = 'status-late';
                                    statusText = 'Late';
                                } else if (sub.status === 'graded') {
                                    statusClass = 'status-graded';
                                    statusText = 'Graded';
                                }

                                let marksDisplay = '';
                                if (sub.marks) {
                                    marksDisplay = `<span class="fw-bold">${sub.marks}/${sub.max_marks}</span>`;
                                } else {
                                    marksDisplay = '<span class="text-muted">Not graded</span>';
                                }

                                html += `<tr>
                            <td><strong>${escapeHtml(sub.student_name)}</strong></td>
                            <td>${escapeHtml(sub.registration_no)}</td
                            <td>${sub.submission_date}<br><small>${sub.submission_time}</small></td
                            <td><span class="status-badge ${statusClass}">${statusText}</span></td
                            <td>${marksDisplay}</td
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="${sub.file_path}" class="btn-action btn-download" download style="padding: 0.3rem 0.6rem;">
                                        <i class="fas fa-download"></i> File
                                    </a>
                                    <a href="teacher_grade_submission.php?id=${sub.id}&assignment=${assignmentId}" class="btn-action btn-check" style="padding: 0.3rem 0.6rem;">
                                        <i class="fas fa-star"></i> Grade
                                    </a>
                                </div>
                             </td
                         </tr`;
                            });

                            html += `</tbody>
                            </table>
                        </div>`;
                            document.getElementById('submissionsModalBody').innerHTML = html;
                        } else {
                            document.getElementById('submissionsModalBody').innerHTML = `
                        <div class="text-center p-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h6>No Submissions Yet</h6>
                            <p class="text-muted">No students have submitted this assignment.</p>
                        </div>
                    `;
                        }
                    },
                    error: function () {
                        document.getElementById('submissionsModalBody').innerHTML = `
                    <div class="text-center p-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h6>Error Loading Submissions</h6>
                        <p class="text-muted">Please try again.</p>
                    </div>
                `;
                    }
                });
            }

            function closeSubmissionsModal() {
                document.getElementById('submissionsModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            document.getElementById('viewModal').addEventListener('click', function (e) {
                if (e.target === this) closeViewModal();
            });
            document.getElementById('editModal').addEventListener('click', function (e) {
                if (e.target === this) closeEditModal();
            });
            document.getElementById('submissionsModal').addEventListener('click', function (e) {
                if (e.target === this) closeSubmissionsModal();
            });

            <?php if (!empty($filtered_assignments)): ?>
                $(document).ready(function () {
                    if ($('#assignmentsTable tbody tr').length > 0) {
                        $('#assignmentsTable').DataTable({
                            pageLength: 10,
                            order: [[0, 'desc']],
                            language: {
                                search: "🔍 Search:",
                                lengthMenu: "Show _MENU_ entries",
                                info: "Showing _START_ to _END_ of _TOTAL_ assignments",
                                infoEmpty: "Showing 0 to 0 of 0 assignments",
                                infoFiltered: "(filtered from _MAX_ total assignments)"
                            },
                            columnDefs: [
                                { orderable: false, targets: [7] }
                            ]
                        });
                    }
                });
            <?php endif; ?>

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeViewModal();
                    closeEditModal();
                    closeSubmissionsModal();
                }
            });

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function (m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            setTimeout(function () {
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