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

/* GET FILTERS */
$selected_branch = isset($_GET['branch']) ? mysqli_real_escape_string($data, $_GET['branch']) : '';
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

/* PROMOTE STUDENTS */
if (isset($_POST['promote_selected']) && isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
    $student_ids = $_POST['student_ids'];
    $success_count = 0;
    $error_count = 0;
    
    mysqli_begin_transaction($data);
    
    foreach ($student_ids as $student_id) {
        $student_id = intval($student_id);
        
        // Get current semester
        $get_current = mysqli_query($data, "SELECT Semester FROM admission WHERE id = $student_id");
        if ($current = mysqli_fetch_assoc($get_current)) {
            $current_semester = $current['Semester'];
            $new_semester = $current_semester + 1;
            
            // Max semester is 6 for Diploma/Polytechnic
            if ($new_semester <= 6) {
                $update = mysqli_query($data, "UPDATE admission SET Semester = $new_semester WHERE id = $student_id");
                if ($update) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }
    
    if ($error_count == 0) {
        mysqli_commit($data);
        $message = "✅ $success_count student(s) promoted successfully!";
        $message_type = "success";
    } else {
        mysqli_rollback($data);
        $message = "⚠️ $success_count promoted, $error_count failed. Please try again.";
        $message_type = "error";
    }
    
    header("Location: promote_semester.php?branch=" . urlencode($selected_branch) . "&semester=" . $selected_semester . "&msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* DEMOTE STUDENTS */
if (isset($_POST['demote_selected']) && isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
    $student_ids = $_POST['student_ids'];
    $success_count = 0;
    $error_count = 0;
    
    mysqli_begin_transaction($data);
    
    foreach ($student_ids as $student_id) {
        $student_id = intval($student_id);
        
        // Get current semester
        $get_current = mysqli_query($data, "SELECT Semester FROM admission WHERE id = $student_id");
        if ($current = mysqli_fetch_assoc($get_current)) {
            $current_semester = $current['Semester'];
            $new_semester = $current_semester - 1;
            
            // Min semester is 1
            if ($new_semester >= 1) {
                $update = mysqli_query($data, "UPDATE admission SET Semester = $new_semester WHERE id = $student_id");
                if ($update) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }
    
    if ($error_count == 0) {
        mysqli_commit($data);
        $message = "✅ $success_count student(s) demoted successfully!";
        $message_type = "success";
    } else {
        mysqli_rollback($data);
        $message = "⚠️ $success_count demoted, $error_count failed. Please try again.";
        $message_type = "error";
    }
    
    header("Location: promote_semester.php?branch=" . urlencode($selected_branch) . "&semester=" . $selected_semester . "&msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* PROMOTE ALL IN SEMESTER */
if (isset($_POST['promote_all'])) {
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = intval($_POST['semester']);
    
    $get_students = mysqli_query($data, "SELECT id, Semester FROM admission WHERE Branch = '$branch' AND Semester = $semester");
    $success_count = 0;
    $error_count = 0;
    
    mysqli_begin_transaction($data);
    
    while ($student = mysqli_fetch_assoc($get_students)) {
        $current_semester = $student['Semester'];
        $new_semester = $current_semester + 1;
        
        if ($new_semester <= 6) {
            $update = mysqli_query($data, "UPDATE admission SET Semester = $new_semester WHERE id = {$student['id']}");
            if ($update) {
                $success_count++;
            } else {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }
    
    if ($error_count == 0) {
        mysqli_commit($data);
        $message = "✅ All $success_count student(s) in $branch - Semester $semester promoted successfully!";
        $message_type = "success";
    } else {
        mysqli_rollback($data);
        $message = "⚠️ $success_count promoted, $error_count failed.";
        $message_type = "error";
    }
    
    header("Location: promote_semester.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH BRANCHES FOR FILTER */
$branches_query = mysqli_query($data, "SELECT DISTINCT Branch FROM admission ORDER BY Branch");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row['Branch'];
}

/* FETCH SEMESTERS FOR FILTER - FIXED to 1-6 */
$semesters = [1, 2, 3, 4, 5, 6];

/* FETCH STUDENTS BASED ON FILTERS */
$students = [];
$total_students = 0;

if ($selected_branch && $selected_semester > 0) {
    $students_query = mysqli_query($data, "SELECT * FROM admission WHERE Branch = '$selected_branch' AND Semester = $selected_semester ORDER BY id DESC");
    if ($students_query) {
        while ($row = mysqli_fetch_assoc($students_query)) {
            $students[] = $row;
        }
        $total_students = count($students);
    }
}

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

// Helper function for ordinal suffix
function getOrdinalSuffix($number) {
    if ($number == 1) return 'st';
    if ($number == 2) return 'nd';
    if ($number == 3) return 'rd';
    return 'th';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Promote Semester | Admin Panel - StudyBuddyHub</title>

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
            --info: #4cc9f0;
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

        /* ===== FILTER CARD ===== */
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        .filter-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .filter-card { padding: 1.25rem; } }

        /* Form Controls */
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
        }
        .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }
        
        /* Filter Button */
        .btn-load {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition);
            width: 100%;
        }
        .btn-load:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
        }

        /* ===== ACTION CARD ===== */
        .action-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
        }
        .action-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .action-card { padding: 1rem 1.5rem; } }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        /* Unified Button Styles */
        .btn-promote-all, .btn-promote-selected, .btn-demote-selected {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-promote-all {
            background: linear-gradient(135deg, var(--success), #05b888);
            color: white;
        }
        .btn-promote-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(6,214,160,0.3);
        }
        .btn-promote-selected {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .btn-promote-selected:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
        }
        .btn-demote-selected {
            background: linear-gradient(135deg, var(--warning), #e6b800);
            color: var(--dark);
        }
        .btn-demote-selected:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255,209,102,0.3);
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
        .total-count {
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
            min-width: 700px;
        }
        thead th {
            font-size: 0.75rem;
            padding: 0.75rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        tbody td {
            font-size: 0.8rem;
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }

        .branch-badge {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
        }
        .semester-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-block;
            font-weight: 500;
        }

        .student-avatar {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.7rem;
            margin-right: 0.5rem;
        }

        .checkbox-col {
            width: 40px;
            text-align: center;
        }
        .student-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
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

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-top: 1.5rem;
            transition: var(--transition);
        }
        .info-card:hover {
            box-shadow: var(--shadow-lg);
        }
        @media (min-width: 768px) { .info-card { padding: 1.25rem; } }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(67,97,238,0.1), rgba(58,12,163,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon i {
            font-size: 1.2rem;
            color: var(--primary);
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
        
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        @media (min-width: 576px) {
            .modal-dialog {
                min-height: calc(100% - 3.5rem);
            }
        }

        [data-aos] {
            opacity: 0;
            transition-property: opacity, transform;
        }
        [data-aos].aos-animate { opacity: 1; }
        
        .badge-can-promote {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .badge-can-demote {
            background: rgba(255,209,102,0.1);
            color: #d4a000;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .badge-disabled {
            background: rgba(108,117,125,0.1);
            color: var(--gray);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
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
            <a href="promote_semester.php" class="active"> <i class="fas fa-arrow-up"></i> <span>Promote Semester</span> </a>

            <div class="menu-title">TEACHER MANAGEMENT</div>
            <a href="view_teacher.php"> <i class="fas fa-user-tie"></i> <span>View Teachers</span> </a>
            <a href="teacher_subjects.php"> <i class="fas fa-chalkboard"></i> <span>Teacher Subjects</span> </a>

            <div class="menu-title">ACADEMIC MANAGEMENT</div>
            <a href="subjects.php"> <i class="fas fa-book"></i> <span>View Subjects</span> </a>
            <a href="timetable.php"> <i class="fas fa-calendar-alt"></i> <span>Timetable</span> </a>
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
                    <i class="fas fa-arrow-up"></i> Promote Semester
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
                <h2> <i class="fas fa-arrow-up" style="color: var(--success);"></i> Promote / Demote Semester </h2>
                <p>Select students and move them to the next or previous semester (Diploma/Polytechnic - 6 Semesters)</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- FILTER SECTION -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="50">
                <form method="get" action="promote_semester.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label"><i class="fas fa-code-branch"></i> Select Branch</label>
                            <select name="branch" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo ($selected_branch == $branch) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-layer-group"></i> Current Semester</label>
                            <select name="semester" class="form-select" required>
                                <option value="0">-- Select Semester --</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($selected_semester == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i . getOrdinalSuffix($i); ?> Semester
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn-load"><i class="fas fa-search me-1"></i> Load Students</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selected_branch && $selected_semester > 0): ?>
                
                <!-- ACTION BUTTONS -->
                <div class="action-card" data-aos="fade-up" data-aos-delay="100">
                    <div>
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="small text-muted ms-1">Select students to promote or demote</span>
                    </div>
                    <div class="action-buttons">
                        <form method="post" action="promote_semester.php" id="promoteAllForm" style="display: inline;">
                            <input type="hidden" name="branch" value="<?php echo htmlspecialchars($selected_branch); ?>">
                            <input type="hidden" name="semester" value="<?php echo $selected_semester; ?>">
                            <button type="submit" name="promote_all" class="btn-promote-all" id="promoteAllBtn">
                                <i class="fas fa-fast-forward"></i> Promote All
                            </button>
                        </form>
                        <button class="btn-promote-selected" id="promoteSelectedBtn">
                            <i class="fas fa-arrow-up"></i> Promote Selected
                        </button>
                        <button class="btn-demote-selected" id="demoteSelectedBtn">
                            <i class="fas fa-arrow-down"></i> Demote Selected
                        </button>
                    </div>
                </div>

                <!-- STUDENTS TABLE -->
                <div class="table-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="table-header">
                        <h4>
                            <i class="fas fa-users"></i>
                            Students in <?php echo htmlspecialchars($selected_branch); ?> - <?php echo $selected_semester . getOrdinalSuffix($selected_semester); ?> Semester
                        </h4>
                        <div class="total-count">
                            <i class="fas fa-user-graduate"></i> Total: <?php echo $total_students; ?> Students
                        </div>
                    </div>

                    <div class="table-responsive">
                        <?php if ($total_students > 0): ?>
                            <form method="post" action="promote_semester.php" id="studentForm">
                                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($selected_branch); ?>">
                                <input type="hidden" name="semester" value="<?php echo $selected_semester; ?>">
                                <table id="studentsTable" class="display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th class="checkbox-col">
                                                <input type="checkbox" id="selectAll" class="student-checkbox">
                                            </th>
                                            <th>ID</th>
                                            <th>Student Name</th>
                                            <th>Registration No</th>
                                            <th>Email</th>
                                            <th>Current Semester</th>
                                            <th>Action Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): 
                                            $can_promote = $student['Semester'] < 6;
                                            $can_demote = $student['Semester'] > 1;
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox student-checkbox-item">
                                            </td>
                                            <td><span class="branch-badge">#<?php echo $student['id']; ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar"><?php echo strtoupper(substr($student['Name'], 0, 1)); ?></div>
                                                    <strong><?php echo htmlspecialchars($student['Name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['registration_no']); ?></td>
                                            <td><?php echo htmlspecialchars($student['Email']); ?></td>
                                            <td><span class="semester-badge">Semester <?php echo $student['Semester']; ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <?php if ($can_promote): ?>
                                                        <span class="badge-can-promote">
                                                            <i class="fas fa-arrow-up"></i> Can Promote
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge-disabled">
                                                            <i class="fas fa-ban"></i> Max Semester (6th)
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($can_demote): ?>
                                                        <span class="badge-can-demote">
                                                            <i class="fas fa-arrow-down"></i> Can Demote
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge-disabled">
                                                            <i class="fas fa-ban"></i> Min Semester (1st)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h5>No Students Found</h5>
                                <p>No students found in <?php echo htmlspecialchars($selected_branch); ?> - <?php echo $selected_semester . getOrdinalSuffix($selected_semester); ?> Semester.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INFO CARD -->
                <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div>
                            <h6 style="font-weight: 600; margin-bottom: 0.25rem;">How Semester Promotion Works</h6>
                            <p style="color: var(--gray); font-size: 0.75rem; margin: 0;">
                                <i class="fas fa-arrow-up text-success me-1"></i> <strong>Promote:</strong> Moves students to the next semester (max 6th semester)<br>
                                <i class="fas fa-arrow-down text-warning me-1"></i> <strong>Demote:</strong> Moves students to the previous semester (min 1st semester)<br>
                                <i class="fas fa-fast-forward text-info me-1"></i> <strong>Promote All:</strong> Promotes all students in the selected branch and semester at once
                            </p>
                        </div>
                    </div>
                </div>

            <?php elseif ($selected_branch || $selected_semester > 0): ?>
                <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h5>No Results Found</h5>
                        <p>No students found matching your selection. Please try different filters.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <h5>Select Branch & Semester</h5>
                        <p>Please select a branch and semester from the filters above to view students.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
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
        }
    });

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

    <?php if ($total_students > 0): ?>
    // DataTable
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#studentsTable')) {
            $('#studentsTable').DataTable().destroy();
        }
        
        $('#studentsTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc']],
            columnDefs: [
                { targets: 0, orderable: false, width: '40px' },
                { targets: 1, width: '60px' },
                { targets: 2, width: '180px' },
                { targets: 3, width: '150px' },
                { targets: 4, width: '200px' },
                { targets: 5, width: '120px' },
                { targets: 6, orderable: false, width: '180px' }
            ],
            language: { 
                search: "Search:", 
                lengthMenu: "Show _MENU_ entries", 
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: { first: "First", last: "Last", next: "Next", previous: "Prev" }
            }
        });
    });

    // Select All functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox-item');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            studentCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
        
        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(studentCheckboxes).length > 0 && Array.from(studentCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            });
        });
    }

    // Promote Selected with SweetAlert
    const promoteSelectedBtn = document.getElementById('promoteSelectedBtn');
    if (promoteSelectedBtn) {
        promoteSelectedBtn.addEventListener('click', function() {
            const selectedIds = Array.from(studentCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
            if (selectedIds.length === 0) {
                Swal.fire({
                    title: 'No Students Selected',
                    text: 'Please select at least one student to promote.',
                    icon: 'warning',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            Swal.fire({
                title: 'Promote Students?',
                html: `Are you sure you want to promote <strong>${selectedIds.length}</strong> selected student(s) to the next semester?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#06d6a0',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Promote',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('studentForm');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'promote_selected';
                    hiddenInput.value = '1';
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        });
    }

    // Demote Selected with SweetAlert
    const demoteSelectedBtn = document.getElementById('demoteSelectedBtn');
    if (demoteSelectedBtn) {
        demoteSelectedBtn.addEventListener('click', function() {
            const selectedIds = Array.from(studentCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
            if (selectedIds.length === 0) {
                Swal.fire({
                    title: 'No Students Selected',
                    text: 'Please select at least one student to demote.',
                    icon: 'warning',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            Swal.fire({
                title: 'Demote Students?',
                html: `Are you sure you want to demote <strong>${selectedIds.length}</strong> selected student(s) to the previous semester?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffd166',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Demote',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('studentForm');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'demote_selected';
                    hiddenInput.value = '1';
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        });
    }

    // Promote All with SweetAlert
    const promoteAllBtn = document.getElementById('promoteAllBtn');
    if (promoteAllBtn) {
        promoteAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Promote All Students?',
                html: `Are you sure you want to promote <strong>ALL <?php echo $total_students; ?></strong> students in<br><strong><?php echo htmlspecialchars($selected_branch); ?> - <?php echo $selected_semester . getOrdinalSuffix($selected_semester); ?> Semester</strong><br>to the next semester?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#06d6a0',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Promote All',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('promoteAllForm').submit();
                }
            });
        });
    }
    <?php endif; ?>

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