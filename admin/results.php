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

// Filters
$branch_filter = isset($_GET['branch']) ? mysqli_real_escape_string($data, $_GET['branch']) : '';
$semester_filter = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$student_filter = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$exam_type_filter = isset($_GET['exam_type']) ? mysqli_real_escape_string($data, $_GET['exam_type']) : '';

/* HANDLE DELETE RESULT */
if (isset($_GET['delete_id'])) {
    $result_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM results WHERE id = $result_id";
    if (mysqli_query($data, $delete_sql)) {
        $message = "Result deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: results.php?msg=" . urlencode($message) . "&type=" . $message_type . "&branch=" . urlencode($branch_filter) . "&semester=" . $semester_filter);
    exit();
}

/* HANDLE UPDATE RESULT */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_result'])) {
    $result_id = intval($_POST['result_id']);
    $marks = intval($_POST['marks']);
    $exam_type = mysqli_real_escape_string($data, $_POST['exam_type']);
    $remarks = mysqli_real_escape_string($data, trim($_POST['remarks']));
    
    // Calculate grade based on marks
    $grade = '';
    if ($marks >= 90) $grade = 'A+';
    elseif ($marks >= 80) $grade = 'A';
    elseif ($marks >= 70) $grade = 'B+';
    elseif ($marks >= 60) $grade = 'B';
    elseif ($marks >= 50) $grade = 'C';
    elseif ($marks >= 40) $grade = 'D';
    else $grade = 'F';
    
    $update_sql = "UPDATE results SET 
                   marks = $marks,
                   exam_type = '$exam_type',
                   grade = '$grade',
                   remarks = '$remarks'
                   WHERE id = $result_id";
    
    if (mysqli_query($data, $update_sql)) {
        $message = "Result updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    header("Location: results.php?msg=" . urlencode($message) . "&type=" . $message_type . "&branch=" . urlencode($branch_filter) . "&semester=" . $semester_filter);
    exit();
}

/* HANDLE ADD RESULT */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_result'])) {
    $student_id = intval($_POST['student_id']);
    $subject_id = intval($_POST['subject_id']);
    $marks = intval($_POST['marks']);
    $exam_type = mysqli_real_escape_string($data, $_POST['exam_type']);
    $exam_date = mysqli_real_escape_string($data, $_POST['exam_date']);
    $remarks = mysqli_real_escape_string($data, trim($_POST['remarks']));
    
    // Calculate grade based on marks
    $grade = '';
    if ($marks >= 90) $grade = 'A+';
    elseif ($marks >= 80) $grade = 'A';
    elseif ($marks >= 70) $grade = 'B+';
    elseif ($marks >= 60) $grade = 'B';
    elseif ($marks >= 50) $grade = 'C';
    elseif ($marks >= 40) $grade = 'D';
    else $grade = 'F';
    
    // Check if result already exists
    $check_sql = "SELECT id FROM results WHERE student_id = $student_id AND subject_id = $subject_id";
    $check_result = mysqli_query($data, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $message = "Result already exists for this student and subject!";
        $message_type = "error";
    } else {
        $insert_sql = "INSERT INTO results (student_id, subject_id, marks, max_marks, grade, exam_type, exam_date, remarks) 
                       VALUES ($student_id, $subject_id, $marks, 100, '$grade', '$exam_type', '$exam_date', '$remarks')";
        
        if (mysqli_query($data, $insert_sql)) {
            $message = "Result added successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_error($data);
            $message_type = "error";
        }
    }
    
    header("Location: results.php?msg=" . urlencode($message) . "&type=" . $message_type . "&branch=" . urlencode($branch_filter) . "&semester=" . $semester_filter);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* FETCH BRANCHES FOR FILTER */
$branches_query = mysqli_query($data, "SELECT DISTINCT Branch FROM admission WHERE Branch IS NOT NULL AND Branch != '' ORDER BY Branch");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row['Branch'];
}

/* FETCH STUDENTS FOR FILTER */
$students_query = mysqli_query($data, "SELECT id, Name, registration_no, Branch, Semester FROM admission ORDER BY Name");
$students = [];
while ($row = mysqli_fetch_assoc($students_query)) {
    $students[] = $row;
}

/* FETCH SUBJECTS FOR ADD MODAL */
$subjects_query = mysqli_query($data, "SELECT id, subject_code, subject_name, branch, semester FROM subjects ORDER BY branch, semester, subject_code");
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_query)) {
    $subjects[] = $row;
}

/* BUILD FILTER QUERY FOR RESULTS */
$where = [];
if ($branch_filter) {
    $where[] = "a.Branch = '$branch_filter'";
}
if ($semester_filter > 0) {
    $where[] = "a.Semester = $semester_filter";
}
if ($student_filter > 0) {
    $where[] = "r.student_id = $student_filter";
}
if ($exam_type_filter) {
    $where[] = "r.exam_type = '$exam_type_filter'";
}

$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

/* FETCH RESULTS */
$results_query = mysqli_query($data, "
    SELECT 
        r.id,
        r.student_id,
        r.subject_id,
        r.marks,
        r.max_marks,
        r.grade,
        r.exam_type,
        r.exam_date,
        r.remarks,
        r.created_at,
        a.Name as student_name,
        a.registration_no,
        a.Branch as student_branch,
        a.Semester as student_semester,
        s.subject_code,
        s.subject_name,
        s.credits
    FROM results r
    JOIN admission a ON a.id = r.student_id
    JOIN subjects s ON s.id = r.subject_id
    $where_sql
    ORDER BY r.exam_date DESC, a.Name
");

$results = [];
while ($row = mysqli_fetch_assoc($results_query)) {
    $results[] = $row;
}

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

/* CALCULATE STATS */
$total_results = count($results);
$total_students_with_results = count(array_unique(array_column($results, 'student_id')));
$average_marks = 0;
if ($total_results > 0) {
    $sum_marks = array_sum(array_column($results, 'marks'));
    $average_marks = round($sum_marks / $total_results, 2);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Results | Admin Panel - StudyBuddyHub</title>

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
    
    <!-- jsPDF for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
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

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(67,97,238,0.1), rgba(58,12,163,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon i { font-size: 1.2rem; color: var(--primary); }
        .stat-info p {
            font-size: 0.7rem;
            color: var(--gray);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            font-weight: 500;
        }
        .stat-info h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

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

        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
        }
        .form-select, .form-control {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }
        
        .btn-filter {
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
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67,97,238,0.3);
        }
        .btn-reset {
            background: var(--gray);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            justify-content: center;
        }
        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }
        .btn-pdf {
            background: #dc3545;
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
        }
        .btn-pdf:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220,53,69,0.3);
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
        .total-results {
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

        .badge-grade {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .grade-A-plus { background: #d4edda; color: #155724; }
        .grade-A { background: #d1ecf1; color: #0c5460; }
        .grade-B-plus { background: #d4edda; color: #155724; }
        .grade-B { background: #fff3cd; color: #856404; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f8d7da; color: #721c24; }

        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-edit, .btn-delete {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
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

        .btn-add {
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
        .btn-add:hover {
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
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
            <a href="timetable.php"> <i class="fas fa-calendar-alt"></i> <span>Timetable</span> </a>
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
            <a href="results.php" class="active"> <i class="fas fa-chart-bar"></i> <span>Results</span> </a>

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
                    <i class="fas fa-chart-bar"></i> Results
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
                <h2> <i class="fas fa-chart-bar" style="color: var(--primary);"></i> Results Management </h2>
                <p>View, manage, and download student results across all branches</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- STATS CARDS -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="50">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-info">
                        <p>Total Results</p>
                        <h3><?php echo $total_results; ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <p>Students</p>
                        <h3><?php echo $total_students_with_results; ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="stat-info">
                        <p>Average Marks</p>
                        <h3><?php echo $average_marks; ?>%</h3>
                    </div>
                </div>
            </div>

            <!-- FILTER SECTION -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <form method="get" action="results.php" id="filterForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-code-branch"></i> Branch</label>
                            <select name="branch" class="form-select">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo ($branch_filter == $branch) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-layer-group"></i> Semester</label>
                            <select name="semester" class="form-select">
                                <option value="0">All Semesters</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($semester_filter == $i) ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-user-graduate"></i> Student</label>
                            <select name="student_id" class="form-select">
                                <option value="0">All Students</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo ($student_filter == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['Name'] . ' (' . $student['registration_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-file-alt"></i> Exam Type</label>
                            <select name="exam_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="internal" <?php echo ($exam_type_filter == 'internal') ? 'selected' : ''; ?>>Internal</option>
                                <option value="external" <?php echo ($exam_type_filter == 'external') ? 'selected' : ''; ?>>External</option>
                                <option value="practical" <?php echo ($exam_type_filter == 'practical') ? 'selected' : ''; ?>>Practical</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn-filter w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                                <a href="results.php" class="btn-reset"><i class="fas fa-undo-alt"></i></a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- RESULTS TABLE -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="150">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list"></i>
                        Student Results
                    </h4>
                    <div class="d-flex gap-2">
                        <div class="total-results">
                            <i class="fas fa-chart-bar"></i> Total: <?php echo $total_results; ?> Records
                        </div>
                        <?php if ($total_results > 0): ?>
                            <button class="btn-pdf" onclick="downloadPDF()">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        <?php endif; ?>
                        <button class="btn-add" onclick="openAddModal()">
                            <i class="fas fa-plus-circle"></i> Add Result
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if ($total_results > 0): ?>
                        <table id="resultsTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Branch</th>
                                    <th>Sem</th>
                                    <th>Exam Type</th>
                                    <th>Marks</th>
                                    <th>Grade</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): 
                                    $gradeClass = '';
                                    if ($result['grade'] == 'A+') $gradeClass = 'grade-A-plus';
                                    elseif ($result['grade'] == 'A') $gradeClass = 'grade-A';
                                    elseif ($result['grade'] == 'B+') $gradeClass = 'grade-B-plus';
                                    elseif ($result['grade'] == 'B') $gradeClass = 'grade-B';
                                    elseif ($result['grade'] == 'C') $gradeClass = 'grade-C';
                                    elseif ($result['grade'] == 'D') $gradeClass = 'grade-D';
                                    else $gradeClass = 'grade-F';
                                ?>
                                <tr id="result-row-<?php echo $result['id']; ?>">
                                    <td><span class="badge-grade" style="background: #e9ecef;">#<?php echo $result['id']; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($result['student_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($result['registration_no']); ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($result['subject_code']); ?></strong><br><small><?php echo htmlspecialchars($result['subject_name']); ?></small></td>
                                    <td><?php echo htmlspecialchars($result['student_branch']); ?></td>
                                    <td><?php echo $result['student_semester']; ?></td>
                                    <td><span class="badge-grade" style="background: rgba(67,97,238,0.1); color: var(--primary);"><?php echo ucfirst($result['exam_type']); ?></span></td>
                                    <td><strong><?php echo $result['marks']; ?></strong> / <?php echo $result['max_marks']; ?></td>
                                    <td><span class="badge-grade <?php echo $gradeClass; ?>"><?php echo $result['grade']; ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($result['exam_date'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($result); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete" onclick="deleteResult(<?php echo $result['id']; ?>, '<?php echo htmlspecialchars($result['student_name']); ?>', '<?php echo htmlspecialchars($result['subject_code']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                     </td
                                 </tr
                                <?php endforeach; ?>
                            </tbody>
                         </table
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h5>No Results Found</h5>
                            <p>No results match your filter criteria.</p>
                            <button class="btn-add" style="margin-top: 1rem;" onclick="openAddModal()">
                                <i class="fas fa-plus-circle"></i> Add First Result
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD RESULT MODAL -->
<div class="modal-custom" id="addModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-plus-circle"></i> Add New Result</h5>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="post" id="addResultForm">
            <input type="hidden" name="add_result" value="1">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['Name'] . ' (' . $student['registration_no'] . ') - ' . $student['Branch'] . ' Sem ' . $student['Semester']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['branch'] . ' Sem ' . $subject['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-star"></i> Marks (out of 100)</label>
                    <input type="number" name="marks" class="form-control" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-alt"></i> Exam Type</label>
                    <select name="exam_type" class="form-control" required>
                        <option value="internal">Internal Exam</option>
                        <option value="external">External Exam</option>
                        <option value="practical">Practical Exam</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Exam Date</label>
                    <input type="date" name="exam_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Add Result</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT RESULT MODAL -->
<div class="modal-custom" id="editModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5><i class="fas fa-edit"></i> Edit Result</h5>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editResultForm">
            <input type="hidden" name="result_id" id="edit_result_id">
            <input type="hidden" name="update_result" value="1">
            <div class="modal-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-star"></i> Marks (out of 100)</label>
                    <input type="number" name="marks" id="edit_marks" class="form-control" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-alt"></i> Exam Type</label>
                    <select name="exam_type" id="edit_exam_type" class="form-control" required>
                        <option value="internal">Internal Exam</option>
                        <option value="external">External Exam</option>
                        <option value="practical">Practical Exam</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Remarks</label>
                    <textarea name="remarks" id="edit_remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Result</button>
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
            closeAddModal();
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

    // DataTable
    <?php if ($total_results > 0): ?>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#resultsTable')) {
            $('#resultsTable').DataTable().destroy();
        }
        
        var $table = $('#resultsTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 10) {
            $('#resultsTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, width: '60px' },
                    { targets: 1, width: '150px' },
                    { targets: 2, width: '150px' },
                    { targets: 3, width: '80px' },
                    { targets: 4, width: '50px' },
                    { targets: 5, width: '100px' },
                    { targets: 6, width: '80px' },
                    { targets: 7, width: '60px' },
                    { targets: 8, width: '80px' },
                    { targets: 9, width: '80px', orderable: false }
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
            $('#resultsTable').addClass('table table-striped');
        }
    });
    <?php endif; ?>

    // Add Modal Functions
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Edit Modal Functions
    function openEditModal(result) {
        document.getElementById('edit_result_id').value = result.id;
        document.getElementById('edit_marks').value = result.marks;
        document.getElementById('edit_exam_type').value = result.exam_type;
        document.getElementById('edit_remarks').value = result.remarks || '';
        
        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Delete Result
    function deleteResult(id, studentName, subjectCode) {
        Swal.fire({
            title: 'Delete Result?',
            html: `Are you sure you want to delete result for <strong>${escapeHtml(studentName)}</strong> in <strong>${escapeHtml(subjectCode)}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const urlParams = new URLSearchParams(window.location.search);
                const branch = urlParams.get('branch') || '';
                const semester = urlParams.get('semester') || 0;
                window.location.href = `results.php?delete_id=${id}&branch=${encodeURIComponent(branch)}&semester=${semester}`;
            }
        });
    }

    // Close modals on outside click
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // PDF Download Function
    function downloadPDF() {
        const { jsPDF } = window.jspdf;
        
        Swal.fire({
            title: 'Generating PDF...',
            text: 'Please wait while we prepare the results report.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Get the table element
        const table = document.querySelector('#resultsTable');
        
        if (!table) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not generate PDF. Table not found.',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Clone the table for PDF
        const cloneTable = table.cloneNode(true);
        
        // Remove DataTable classes and add styling
        cloneTable.classList.remove('display');
        cloneTable.style.width = '100%';
        cloneTable.style.borderCollapse = 'collapse';
        cloneTable.style.fontSize = '10px';
        
        // Style the cloned table
        const style = document.createElement('style');
        style.textContent = `
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #4361ee; color: white; }
            .badge-grade { display: inline-block; padding: 2px 6px; border-radius: 4px; }
            .grade-A-plus { background: #d4edda; color: #155724; }
            .grade-A { background: #d1ecf1; color: #0c5460; }
            .grade-B-plus { background: #d4edda; color: #155724; }
            .grade-B { background: #fff3cd; color: #856404; }
            .grade-C { background: #fff3cd; color: #856404; }
            .grade-D { background: #f8d7da; color: #721c24; }
            .grade-F { background: #f8d7da; color: #721c24; }
        `;
        cloneTable.prepend(style);
        
        // Create a wrapper div for the PDF content
        const wrapper = document.createElement('div');
        
        // Add header
        const header = document.createElement('div');
        header.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="color: #4361ee; margin-bottom: 5px;">StudyBuddyHub</h1>
                <h3>Student Results Report</h3>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                <hr style="border: 1px solid #ddd;">
            </div>
        `;
        wrapper.appendChild(header);
        wrapper.appendChild(cloneTable);
        
        // Add footer
        const footer = document.createElement('div');
        footer.innerHTML = `
            <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #666;">
                <hr style="border: 1px solid #ddd;">
                <p>This is a system-generated report. For any discrepancies, please contact the administration.</p>
            </div>
        `;
        wrapper.appendChild(footer);
        
        // Use html2canvas to render the content
        html2canvas(wrapper, {
            scale: 2,
            logging: false,
            useCORS: true
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });
            
            const imgWidth = 297; // A4 landscape width in mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            pdf.save('student_results_report.pdf');
            
            Swal.fire({
                icon: 'success',
                title: 'PDF Generated!',
                text: 'The results report has been downloaded successfully.',
                confirmButtonColor: '#4361ee'
            });
        }).catch(error => {
            console.error('PDF generation error:', error);
            Swal.fire({
                icon: 'error',
                title: 'PDF Generation Failed',
                text: 'There was an error generating the PDF. Please try again.',
                confirmButtonColor: '#4361ee'
            });
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