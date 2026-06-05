<?php
session_start();

/* AUTH CHECK */
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
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

$message = "";
$message_type = "";

/* HANDLE DELETE SLOT */
if (isset($_GET['delete_id'])) {
    $slot_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM timetable WHERE id = $slot_id";
    if (mysqli_query($data, $delete_sql)) {
        $message = "Timetable slot removed successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: timetable.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* HANDLE EDIT SLOT */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_slot'])) {
    $slot_id = intval($_POST['slot_id']);
    $subject_id = intval($_POST['subject_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $day = mysqli_real_escape_string($data, $_POST['day']);
    $start_time = mysqli_real_escape_string($data, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($data, $_POST['end_time']);
    $room_no = mysqli_real_escape_string($data, $_POST['room_no']);
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = intval($_POST['semester']);
    $academic_year = mysqli_real_escape_string($data, $_POST['academic_year']);
    
    // Check for teacher time clash (excluding current slot)
    $check_clash = mysqli_query($data, "SELECT id FROM timetable WHERE teacher_id = $teacher_id AND day = '$day' AND id != $slot_id AND (
        (start_time <= '$start_time' AND end_time > '$start_time') OR 
        (start_time < '$end_time' AND end_time >= '$end_time') OR
        ('$start_time' <= start_time AND '$end_time' >= end_time)
    )");
    
    if (mysqli_num_rows($check_clash) > 0) {
        $message = "Teacher has a time clash on this day!";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE timetable SET 
                       subject_id = $subject_id,
                       teacher_id = $teacher_id,
                       day = '$day',
                       start_time = '$start_time',
                       end_time = '$end_time',
                       room_no = '$room_no',
                       branch = '$branch',
                       semester = $semester,
                       academic_year = '$academic_year'
                       WHERE id = $slot_id";
        
        if (mysqli_query($data, $update_sql)) {
            $message = "Timetable slot updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_error($data);
            $message_type = "error";
        }
    }
    
    header("Location: timetable.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH TIMETABLE ENTRIES */
$timetable_query = mysqli_query($data, "SELECT tt.*, s.subject_name, s.subject_code, s.credits, t.name AS teacher_name, t.email AS teacher_email
                                        FROM timetable tt
                                        JOIN subjects s ON s.id = tt.subject_id
                                        JOIN teacher t ON t.id = tt.teacher_id
                                        ORDER BY FIELD(tt.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), tt.start_time");
$timetable_entries = [];
while ($row = mysqli_fetch_assoc($timetable_query)) {
    $timetable_entries[] = $row;
}

/* FETCH BRANCHES FOR EDIT MODAL */
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

/* FETCH TEACHERS FOR EDIT MODAL */
$teachers_query = mysqli_query($data, "SELECT id, name, email FROM teacher WHERE is_active = 1 ORDER BY name");
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_query)) {
    $teachers[] = $row;
}

/* FETCH SUBJECTS FOR EDIT MODAL */
$subjects_query = mysqli_query($data, "SELECT id, subject_code, subject_name, branch, semester, credits FROM subjects ORDER BY branch, semester, subject_code");
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_query)) {
    $subjects[] = $row;
}

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Timetable | Admin Panel - StudyBuddyHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
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
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef476f;
            --dark: #1e1e2f;
            --gray: #6c757d;
            --light: #f8f9fa;
            --border: #e9ecef;
            --sidebar-width: 280px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: #f0f2f5;
            overflow-x: hidden;
        }

        .admin-wrap {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e1e2f 0%, #2a2a40 100%);
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
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            border-left-color: var(--primary);
        }
        .sidebar a.active {
            background: linear-gradient(90deg, rgba(67,97,238,0.15), transparent);
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

        /* ===== NEW MODERN TOPBAR STYLES ===== */
        .modern-topbar {
            background: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 99;
            border-bottom: 1px solid var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 10px;
            transition: all 0.2s;
            display: none;
        }

        .menu-toggle-btn:hover {
            background: var(--light);
        }

        @media (max-width: 768px) {
            .menu-toggle-btn {
                display: block;
            }
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            letter-spacing: -0.3px;
        }

        .page-title i {
            color: var(--primary);
            margin-right: 0.5rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Quick Add Button */
        .quick-add-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .quick-add-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        /* Dropdown Menu */
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
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1000;
            border: 1px solid var(--border);
            overflow: hidden;
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
        }

        .quick-menu-header span {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .quick-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .quick-menu-item:hover {
            background: var(--light);
            padding-left: 1.25rem;
        }

        .quick-menu-item i {
            width: 22px;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .quick-menu-divider {
            height: 1px;
            background: var(--border);
            margin: 0.25rem 0;
        }

        /* Admin Profile */
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 1rem;
            background: var(--light);
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .admin-profile:hover {
            background: #e2e8f0;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
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

        .admin-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .admin-info {
            display: block;
        }

        .admin-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark);
        }

        .admin-role {
            font-size: 0.7rem;
            color: var(--primary);
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .admin-info {
                display: none;
            }
            .quick-add-btn span {
                display: none;
            }
            .quick-add-btn {
                padding: 0.5rem;
            }
            .logout-btn span {
                display: none;
            }
        }

        /* Logout Button */
        .logout-btn {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-weight: 500;
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }
        /* ===== CONTENT AREA ===== */
        .content { padding: 1rem; }
        @media (min-width: 768px) { .content { padding: 1.5rem; } }

        .page-header { margin-bottom: 1.5rem; }
        .page-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        @media (min-width: 768px) { .page-header h2 { font-size: 1.8rem; } }
        .page-header p { color: var(--gray); font-size: 0.85rem; }

        /* ===== ALERTS ===== */
        .alert-custom {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
            font-size: 0.85rem;
        }
        .alert-success {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        .alert-error {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== TABLE CARD ===== */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .table-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .table-card { padding: 1.5rem; } }

        .table-header {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        @media (min-width: 576px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        .table-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .total-slots {
            background: rgba(67,97,238,0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--primary);
            display: inline-block;
            width: fit-content;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            min-width: 1000px;
        }
        thead th {
            font-size: 0.75rem;
            padding: 0.75rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
            text-align: left;
        }
        tbody td {
            font-size: 0.8rem;
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }

        .badge-day {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
        }
        .badge-time {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
        }
        .badge-room {
            background: rgba(255,193,7,0.1);
            color: #d4a000;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-view, .btn-edit, .btn-delete {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        .btn-view {
            background: rgba(6,214,160,0.1);
            color: var(--success);
        }
        .btn-view:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        .btn-edit {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
        }
        .btn-edit:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .btn-delete {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
        }
        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .btn-create {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }
        .empty-state h5 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Modal Styles */
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
            max-width: 700px;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
        }
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
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
            transition: var(--transition);
        }
        .btn-cancel:hover {
            background: #5a6268;
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

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Quick Add Modal */
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

        [data-aos] {
            opacity: 0;
            transition-property: opacity, transform;
        }
        [data-aos].aos-animate { opacity: 1; }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
    </style>
</head>
<body>

<div class="admin-wrap">

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>StudyBuddyHub</h3>
            <p>College Management System</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">MAIN</div>
            <a href="home.php"> <i class="fas fa-home"></i> <span>Dashboard</span> </a>

            <div class="menu-title">BRANCH MANAGEMENT</div>
            <a href="branches.php"> <i class="fas fa-code-branch"></i> <span>View Branches</span> </a>

            <div class="menu-title">STUDENT MANAGEMENT</div>
            <a href="pending_requests.php"> <i class="fas fa-clock"></i> <span>Pending Students</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_student.php"> <i class="fas fa-users"></i> <span>View Students</span> </a>
            <a href="promote_semester.php"> <i class="fas fa-arrow-up"></i> <span>Promote Semester</span> </a>

            <div class="menu-title">TEACHER MANAGEMENT</div>
            <a href="view_teacher.php"> <i class="fas fa-user-tie"></i> <span>View Teachers</span> </a>
            <a href="teacher_subjects.php"> <i class="fas fa-chalkboard"></i> <span>Teacher Subjects</span> </a>

            <div class="menu-title">ACADEMIC MANAGEMENT</div>
            <a href="subjects.php"> <i class="fas fa-book"></i> <span>View Subjects</span> </a>
            <a href="timetable.php" class="active"> <i class="fas fa-calendar-alt"></i> <span>Timetable</span> </a>
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
            <a href="results.php"> <i class="fas fa-chart-bar"></i> <span>Results</span> </a>

            <div class="menu-title">NOTIFICATIONS</div>
            <a href="send_notification.php"> <i class="fas fa-bell"></i> <span>Send Notification</span> </a>
            <a href="notification_history.php"> <i class="fas fa-history"></i> <span>Notification History</span> </a>
        
            <div class="menu-title">ACCOUNT</div>
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> <span>My Profile</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- NEW MODERN TOPBAR -->
        <div class="modern-topbar">
            <div class="topbar-left">
                <button class="menu-toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <i class="fas fa-calendar-alt"></i> Timetable
                </div>
            </div>
            <div class="topbar-right">
                <div class="quick-dropdown">
                    <button class="quick-add-btn" id="quickAddBtn">
                        <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down" style="font-size: 0.65rem;"></i>
                    </button>
                    <div class="quick-menu" id="quickMenu">
                        <div class="quick-menu-header">
                            <span><i class="fas fa-plus-circle"></i> Quick Actions</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_student.php" data-title="Add New Student" data-icon="fa-user-graduate">
                            <i class="fas fa-user-graduate"></i><span>Add Student</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_teacher.php" data-title="Add New Teacher" data-icon="fa-chalkboard-teacher">
                            <i class="fas fa-chalkboard-teacher"></i><span>Add Teacher</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_branch.php" data-title="Add New Branch" data-icon="fa-code-branch">
                            <i class="fas fa-code-branch"></i><span>Add Branch</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_subject.php" data-title="Add New Subject" data-icon="fa-book">
                            <i class="fas fa-book"></i><span>Add Subject</span>
                        </div>
                        <div class="quick-menu-divider"></div>
                        <div class="quick-menu-item" data-page="assign_subjects.php" data-title="Assign Subject to Teacher" data-icon="fa-random">
                            <i class="fas fa-random"></i><span>Assign Subject</span>
                        </div>
                        <div class="quick-menu-item" data-page="timetable.php" data-title="Create Timetable" data-icon="fa-calendar-plus">
                            <i class="fas fa-calendar-plus"></i><span>Create Timetable</span>
                        </div>
                        <div class="quick-menu-item" data-page="add_notification.php" data-title="Send Notification" data-icon="fa-bell">
                            <i class="fas fa-bell"></i><span>Send Notification</span>
                        </div>
                    </div>
                </div>

                <a href="profile.php" class="admin-profile">
                    <div class="admin-avatar">
                        <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $_SESSION['profile_pic'])): ?>
                            <img src="../uploads/profile_pics/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                        <div class="admin-role">Administrator</div>
                    </div>
                </a>

                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="page-header" data-aos="fade-up">
                <h2> <i class="fas fa-calendar-alt" style="color: var(--primary);"></i> Timetable Management </h2>
                <p>View, edit, and manage class schedules for all branches and semesters</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- TIMETABLE TABLE -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list"></i>
                        Current Timetable
                    </h4>
                    <div class="d-flex gap-2">
                        <div class="total-slots">
                            <i class="fas fa-calendar-week"></i> Total: <?php echo count($timetable_entries); ?> Slots
                        </div>
                        <button class="btn-create" onclick="openInQuickModal('create_timetable.php', 'Create Timetable', 'fa-calendar-plus')">
                            <i class="fas fa-plus-circle"></i> Create Timetable
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if (!empty($timetable_entries)): ?>
                        <table id="timetableTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Branch</th>
                                    <th>Semester</th>
                                    <th>Room</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timetable_entries as $slot): ?>
                                <tr id="slot-row-<?php echo $slot['id']; ?>">
                                    <td><span class="badge-day">#<?php echo $slot['id']; ?></span></td>
                                    <td><span class="badge-day"><?php echo $slot['day']; ?></span></td>
                                    <td><span class="badge-time"><?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($slot['subject_code']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($slot['subject_name']); ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($slot['teacher_name']); ?></strong></td>
                                    <td><span class="badge-day"><?php echo htmlspecialchars($slot['branch']); ?></span></td>
                                    <td><span class="badge-time">Sem <?php echo $slot['semester']; ?></span></td>
                                    <td><span class="badge-room"><?php echo htmlspecialchars($slot['room_no'] ?: 'Not assigned'); ?></span></td>
                                    <td><?php echo htmlspecialchars($slot['academic_year']); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-view" onclick='viewSlot(<?php echo json_encode($slot); ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($slot); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete" onclick="deleteSlot(<?php echo $slot['id']; ?>, '<?php echo htmlspecialchars($slot['subject_code']); ?>', '<?php echo htmlspecialchars($slot['day']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Timetable Slots Found</h5>
                            <p>Click the "Create Timetable" button to add your first timetable slot.</p>
                            <button class="btn-create" style="margin-top: 1rem;" onclick="openInQuickModal('create_timetable.php', 'Create Timetable', 'fa-calendar-plus')">
                                <i class="fas fa-plus-circle"></i> Create Timetable
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="modal-custom" id="viewModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-calendar-alt"></i> Timetable Slot Details</h5>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body-custom" id="viewModalBody">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

<!-- EDIT TIMETABLE SLOT MODAL -->
<div class="modal-custom" id="editModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-edit"></i> Edit Timetable Slot</h5>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editSlotForm">
            <input type="hidden" name="slot_id" id="edit_slot_id">
            <input type="hidden" name="update_slot" value="1">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Subject</label>
                    <select name="subject_id" id="edit_subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['branch'] . ' Sem ' . $subject['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-chalkboard-teacher"></i> Teacher</label>
                    <select name="teacher_id" id="edit_teacher_id" class="form-control" required>
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['name'] . ' (' . $teacher['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-day"></i> Day</label>
                    <select name="day" id="edit_day" class="form-control" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Start Time</label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> End Time</label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-door-open"></i> Room No</label>
                    <input type="text" name="room_no" id="edit_room_no" class="form-control" placeholder="e.g., Room 101, Lab A">
                </div>
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-code-branch"></i> Branch</label>
                            <select name="branch" id="edit_branch" class="form-control" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                        <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Semester</label>
                            <select name="semester" id="edit_semester" class="form-control" required>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                                <option value="3">3rd Semester</option>
                                <option value="4">4th Semester</option>
                                <option value="5">5th Semester</option>
                                <option value="6">6th Semester</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Academic Year</label>
                    <input type="text" name="academic_year" id="edit_academic_year" class="form-control" placeholder="2025-2026" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Slot</button>
            </div>
        </form>
    </div>
</div>

<!-- QUICK ADD MODAL (Bootstrap Modal) -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-iframe">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="quickAddModalLabel">
                    <i class="fas fa-plus-circle"></i> Loading...
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
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
        if (sidebar.classList.contains('active')) {
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.menu-toggle-btn');
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
    
    document.addEventListener('keydown', function(e) { 
        if (e.key === 'Escape') {
            closeQuickMenu();
            closeViewModal();
            closeEditModal();
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

    // Open page in modal (alias)
    function openPageInModal(pageUrl, title, icon = 'fa-plus-circle') {
        openInQuickModal(pageUrl, title, icon);
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

    // DataTable - Now with correct 10 columns
    <?php if (!empty($timetable_entries)): ?>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#timetableTable')) {
            $('#timetableTable').DataTable().destroy();
        }
        
        var $table = $('#timetableTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 10) {
            $('#timetableTable').DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    { targets: 0, width: '60px' },
                    { targets: 1, width: '100px' },
                    { targets: 2, width: '120px' },
                    { targets: 3, width: '180px' },
                    { targets: 4, width: '150px' },
                    { targets: 5, width: '100px' },
                    { targets: 6, width: '80px' },
                    { targets: 7, width: '100px' },
                    { targets: 8, width: '100px' },
                    { targets: 9, width: '100px', orderable: false }
                ],
                language: { 
                    search: "Search:", 
                    lengthMenu: "Show _MENU_ entries", 
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: { first: "First", last: "Last", next: "Next", previous: "Prev" }
                }
            });
        } else {
            console.warn('Column count mismatch. Header:', headerCols, 'Body:', bodyCols);
            $('#timetableTable').addClass('table table-striped');
        }
    });
    <?php endif; ?>

    // View Slot Details
    function viewSlot(slot) {
        let modalContent = `
            <div class="text-center mb-3">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-calendar-alt fa-2x" style="color: white;"></i>
                </div>
                <h5 style="font-weight: 700; color: var(--dark);">Timetable Slot #${slot.id}</h5>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-calendar-day"></i> Day</div>
                <div class="detail-value">${slot.day}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-clock"></i> Time</div>
                <div class="detail-value">${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-book"></i> Subject</div>
                <div class="detail-value"><strong>${escapeHtml(slot.subject_code)}</strong> - ${escapeHtml(slot.subject_name)} (${slot.credits} credits)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-chalkboard-teacher"></i> Teacher</div>
                <div class="detail-value"><strong>${escapeHtml(slot.teacher_name)}</strong><br><small>${escapeHtml(slot.teacher_email)}</small></div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-code-branch"></i> Branch</div>
                <div class="detail-value">${escapeHtml(slot.branch)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-layer-group"></i> Semester</div>
                <div class="detail-value">${getSemesterName(slot.semester)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-door-open"></i> Room</div>
                <div class="detail-value">${escapeHtml(slot.room_no) || 'Not assigned'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label"><i class="fas fa-calendar-alt"></i> Academic Year</div>
                <div class="detail-value">${escapeHtml(slot.academic_year)}</div>
            </div>
        `;
        
        document.getElementById('viewModalBody').innerHTML = modalContent;
        document.getElementById('viewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Edit Modal Functions
    function openEditModal(slot) {
        document.getElementById('edit_slot_id').value = slot.id;
        document.getElementById('edit_subject_id').value = slot.subject_id;
        document.getElementById('edit_teacher_id').value = slot.teacher_id;
        document.getElementById('edit_day').value = slot.day;
        document.getElementById('edit_start_time').value = slot.start_time;
        document.getElementById('edit_end_time').value = slot.end_time;
        document.getElementById('edit_room_no').value = slot.room_no || '';
        document.getElementById('edit_branch').value = slot.branch;
        document.getElementById('edit_semester').value = slot.semester;
        document.getElementById('edit_academic_year').value = slot.academic_year;
        
        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modals on outside click
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    // Delete Slot with SweetAlert
    function deleteSlot(id, subjectCode, day) {
        Swal.fire({
            title: 'Delete Timetable Slot?',
            html: `Are you sure you want to delete slot for <strong>${escapeHtml(subjectCode)}</strong> on <strong>${day}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `timetable.php?delete_id=${id}`;
            }
        });
    }

    function formatTime(time) {
        if (!time) return '';
        let [hours, minutes] = time.split(':');
        let period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours}:${minutes} ${period}`;
    }

    function getSemesterName(semester) {
        const suffixes = ['st', 'nd', 'rd', 'th', 'th', 'th'];
        return `${semester}${suffixes[semester-1]} Semester`;
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

    // Auto-hide alert after 5 seconds
    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);
</script>

</body>
</html>