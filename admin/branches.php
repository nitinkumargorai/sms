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

/* ========================
   UPDATE BRANCH
======================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_branch'])) {
    $branch_id = intval($_POST['branch_id']);
    $branch_name = mysqli_real_escape_string($data, trim($_POST['branch_name']));
    $short_name = strtoupper(mysqli_real_escape_string($data, trim($_POST['short_name'])));
    $description = mysqli_real_escape_string($data, trim($_POST['description']));
    $established_year = mysqli_real_escape_string($data, trim($_POST['established_year']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $sql = "UPDATE branches SET 
            branch_name = '$branch_name',
            short_name = '$short_name',
            description = '$description',
            established_year = '$established_year',
            is_active = '$is_active'
            WHERE id = '$branch_id'";
    
    if (mysqli_query($data, $sql)) {
        $message = "Branch updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    
    header("Location: branches.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* ========================
   CASCADING DELETE BRANCH
======================== */
if (isset($_GET['delete_id'])) {
    $branch_id = intval($_GET['delete_id']);
    
    $branch_info = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE id = '$branch_id'");
    if (!$branch_info || mysqli_num_rows($branch_info) == 0) {
        header("Location: branches.php?msg=Branch not found&type=error");
        exit();
    }
    $branch = mysqli_fetch_assoc($branch_info);
    $branch_code = $branch['branch_code'];
    $branch_name = $branch['branch_name'];
    
    mysqli_begin_transaction($data);
    
    try {
        $subject_ids = [];
        $subjects_query = mysqli_query($data, "SELECT id FROM subjects WHERE branch = '$branch_code'");
        while ($subject = mysqli_fetch_assoc($subjects_query)) {
            $subject_ids[] = $subject['id'];
        }
        $subject_ids_str = !empty($subject_ids) ? implode(',', $subject_ids) : '';
        
        if (!empty($subject_ids_str)) {
            $materials_query = mysqli_query($data, "SELECT file_path FROM materials WHERE subject_id IN ($subject_ids_str)");
            while ($material = mysqli_fetch_assoc($materials_query)) {
                $file_path = "../" . $material['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            mysqli_query($data, "DELETE FROM materials WHERE subject_id IN ($subject_ids_str)");
            
            $syllabus_query = mysqli_query($data, "SELECT file_path FROM syllabus_files WHERE subject_id IN ($subject_ids_str)");
            while ($syllabus = mysqli_fetch_assoc($syllabus_query)) {
                $file_path = "../" . $syllabus['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            mysqli_query($data, "DELETE FROM syllabus_files WHERE subject_id IN ($subject_ids_str)");
        }
        
        if (!empty($subject_ids_str)) {
            $assignments_query = mysqli_query($data, "SELECT id FROM assignments WHERE subject_id IN ($subject_ids_str)");
            $assignment_ids = [];
            while ($assign = mysqli_fetch_assoc($assignments_query)) {
                $assignment_ids[] = $assign['id'];
            }
            
            if (!empty($assignment_ids)) {
                $assignment_ids_str = implode(',', $assignment_ids);
                
                $submissions_query = mysqli_query($data, "SELECT file_path FROM submissions WHERE assignment_id IN ($assignment_ids_str)");
                while ($submission = mysqli_fetch_assoc($submissions_query)) {
                    if (!empty($submission['file_path'])) {
                        $file_path = "../" . $submission['file_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
                mysqli_query($data, "DELETE FROM submissions WHERE assignment_id IN ($assignment_ids_str)");
            }
            mysqli_query($data, "DELETE FROM assignments WHERE subject_id IN ($subject_ids_str)");
        }
        
        if (!empty($subject_ids_str)) {
            mysqli_query($data, "DELETE FROM timetable WHERE subject_id IN ($subject_ids_str)");
        }
        
        $students_query = mysqli_query($data, "SELECT id, Name, Email FROM admission WHERE Branch = '$branch_code'");
        $student_ids = [];
        $student_emails = [];
        
        while ($student = mysqli_fetch_assoc($students_query)) {
            $student_ids[] = $student['id'];
            $student_emails[] = $student['Email'];
            
            $log_query = "INSERT INTO deleted_students_log (student_id, student_name, student_email, deleted_by, deleted_at) 
                          VALUES ('{$student['id']}', '{$student['Name']}', '{$student['Email']}', '{$_SESSION['username']}', NOW())";
            mysqli_query($data, $log_query);
        }
        
        if (!empty($student_ids)) {
            $student_ids_str = implode(',', $student_ids);
            mysqli_query($data, "DELETE FROM attendance WHERE student_id IN ($student_ids_str)");
            mysqli_query($data, "DELETE FROM results WHERE student_id IN ($student_ids_str)");
            mysqli_query($data, "DELETE FROM student_settings WHERE student_id IN ($student_ids_str)");
            mysqli_query($data, "DELETE FROM admission WHERE Branch = '$branch_code'");
        }
        
        if (!empty($student_emails)) {
            foreach ($student_emails as $email) {
                mysqli_query($data, "DELETE FROM user WHERE email = '$email'");
            }
        }
        
        $teachers_query = mysqli_query($data, "SELECT id, email FROM teacher WHERE branch = '$branch_code'");
        $teacher_ids = [];
        $teacher_emails = [];
        
        while ($teacher = mysqli_fetch_assoc($teachers_query)) {
            $teacher_ids[] = $teacher['id'];
            $teacher_emails[] = $teacher['email'];
        }
        
        if (!empty($teacher_ids)) {
            $teacher_ids_str = implode(',', $teacher_ids);
            mysqli_query($data, "DELETE FROM teacher_subjects WHERE teacher_id IN ($teacher_ids_str)");
            mysqli_query($data, "DELETE FROM teacher_settings WHERE teacher_id IN ($teacher_ids_str)");
            mysqli_query($data, "DELETE FROM teacher WHERE branch = '$branch_code'");
        }
        
        if (!empty($teacher_emails)) {
            foreach ($teacher_emails as $email) {
                mysqli_query($data, "DELETE FROM user WHERE email = '$email'");
            }
        }
        
        mysqli_query($data, "DELETE FROM subjects WHERE branch = '$branch_code'");
        
        $sql = "DELETE FROM branches WHERE id = '$branch_id'";
        
        if (mysqli_query($data, $sql)) {
            mysqli_commit($data);
            $message = "✅ Branch '$branch_name' ($branch_code) deleted successfully!\n\n" .
                       "📚 Removed: " . count($student_ids) . " student(s) (including login accounts)\n" .
                       "👨‍🏫 Removed: " . count($teacher_ids) . " teacher(s) (including login accounts)\n" .
                       "📖 Removed: " . count($subject_ids) . " subject(s)\n" .
                       "📄 Removed: All materials, assignments, and syllabus files";
            $message_type = "success";
        } else {
            throw new Exception(mysqli_error($data));
        }
        
    } catch (Exception $e) {
        mysqli_rollback($data);
        $message = "❌ Error during branch deletion: " . $e->getMessage();
        $message_type = "error";
    }
    
    header("Location: branches.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* ========================
   TOGGLE BRANCH STATUS
======================== */
if (isset($_GET['toggle_id'])) {
    $branch_id = intval($_GET['toggle_id']);
    $branch_info = mysqli_query($data, "SELECT is_active FROM branches WHERE id = '$branch_id'");
    $branch = mysqli_fetch_assoc($branch_info);
    $new_status = $branch['is_active'] == 1 ? 0 : 1;
    
    $sql = "UPDATE branches SET is_active = '$new_status' WHERE id = '$branch_id'";
    if (mysqli_query($data, $sql)) {
        $status_text = $new_status == 1 ? "activated" : "deactivated";
        $message = "Branch $status_text successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($data);
        $message_type = "error";
    }
    
    header("Location: branches.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

/* GET MESSAGE FROM URL */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* ========================
   FETCH ALL BRANCHES WITH COUNTS
======================== */
$branches_query = mysqli_query($data, "
    SELECT 
        b.id,
        b.branch_code,
        b.branch_name,
        b.short_name,
        b.description,
        b.established_year,
        b.is_active,
        b.created_at,
        (SELECT COUNT(*) FROM admission WHERE Branch = b.branch_code) as student_count,
        (SELECT COUNT(*) FROM teacher WHERE branch = b.branch_code) as teacher_count,
        (SELECT COUNT(*) FROM subjects WHERE branch = b.branch_code) as subject_count
    FROM branches b
    ORDER BY b.branch_code
");

$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* GET PENDING COUNT FOR SIDEBAR */
$pending_count = 0;
$count_query = mysqli_query($data, "SELECT COUNT(*) AS total FROM admission_requests WHERE status = 'pending'");
if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
    $pending_count = $row['total'];
}

$admin_name = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>View Branches | Admin Panel - StudyBuddyHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .quick-dropdown { position: relative; }
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
        .quick-menu-divider { height: 1px; background: var(--border); margin: 0.25rem 0; }

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
        .admin-profile:hover { background: #e2e8f0; }
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
        .admin-info { display: block; }
        .admin-name { font-weight: 600; font-size: 0.85rem; color: var(--dark); }
        .admin-role { font-size: 0.7rem; color: var(--primary); font-weight: 500; }

        @media (max-width: 576px) {
            .admin-info { display: none; }
            .quick-add-btn span { display: none; }
            .quick-add-btn { padding: 0.5rem; }
            .logout-btn span { display: none; }
        }

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
        .logout-btn:hover { background: var(--danger); color: white; }

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

        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .table-card:hover { box-shadow: var(--shadow-lg); }
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
        .total-branches {
            background: rgba(67,97,238,0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--primary);
            display: inline-block;
            width: fit-content;
        }

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

        .branch-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, rgba(67,97,238,0.1), rgba(58,12,163,0.05));
            color: var(--primary);
        }
        .status-active {
            background: rgba(6,214,160,0.1);
            color: var(--success);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        .status-inactive {
            background: rgba(108,117,125,0.1);
            color: var(--gray);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-edit, .btn-toggle, .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        .btn-edit {
            background: rgba(67,97,238,0.1);
            color: var(--primary);
        }
        .btn-edit:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        .btn-toggle {
            background: rgba(255,209,102,0.1);
            color: #d4a000;
        }
        .btn-toggle:hover { background: var(--warning); color: var(--dark); transform: translateY(-2px); }
        .btn-delete {
            background: rgba(239,71,111,0.1);
            color: var(--danger);
        }
        .btn-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }
        
        .stat-numbers {
            display: flex;
            gap: 0.3rem;
            font-size: 0.7rem;
            flex-wrap: wrap;
        }
        .stat-numbers span {
            background: var(--light);
            padding: 0.2rem 0.4rem;
            border-radius: 50px;
            font-size: 0.65rem;
        }
        .stat-numbers i { margin-right: 0.2rem; font-size: 0.65rem; }

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
            padding: 0.75rem 0.5rem;
            color: var(--dark);
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        tbody tr:hover { background: var(--light); }

        .modal-edit-overlay {
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
        .modal-edit-overlay.active { display: flex; }
        .modal-edit-container {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-edit-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 24px 24px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-edit-header h5 { margin: 0; font-weight: 600; }
        .modal-edit-header h5 i { margin-right: 0.5rem; }
        .modal-edit-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: var(--transition);
        }
        .modal-edit-close:hover { opacity: 1; transform: scale(1.1); }
        .modal-edit-body { padding: 1.5rem; }
        .modal-edit-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
        }
        .form-label i { margin-right: 0.3rem; color: var(--primary); }
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
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
        .btn-save:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .btn-cancel {
            background: var(--gray);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); }
        .form-switch .form-check-input { width: 2.5rem; height: 1.2rem; cursor: pointer; }
        .branch-code-display {
            background: var(--light);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            color: var(--primary);
            display: inline-block;
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
        .modal-header-gradient .btn-close { filter: brightness(0) invert(1); }
        .modal-body-iframe { padding: 0; max-height: 80vh; }
        .modal-body-iframe iframe { width: 100%; height: 75vh; border: none; }
        
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        @media (min-width: 576px) { .modal-dialog { min-height: calc(100% - 3.5rem); } }

        [data-aos] { opacity: 0; transition-property: opacity, transform; }
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
        
        .btn-close-white { filter: brightness(0) invert(1); }
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
            <a href="branches.php" class="active"> <i class="fas fa-code-branch"></i> <span>View Branches</span> </a>

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
            <a href="assign_subjects.php"> <i class="fas fa-chalkboard"></i> <span>Assign Subjects</span> </a>

            <div class="menu-title">ACADEMIC MANAGEMENT</div>
            <a href="subjects.php"> <i class="fas fa-book"></i> <span>View Subjects</span> </a>
            <a href="timetable.php"> <i class="fas fa-calendar-alt"></i> <span>Timetable</span> </a>
            <a href="assignments.php"> <i class="fas fa-tasks"></i> <span>Assignments</span> </a>
            <a href="results.php"> <i class="fas fa-chart-bar"></i> <span>Results</span> </a>

            <div class="menu-title">NOTIFICATIONS</div>
            <a href="send_notification.php"> <i class="fas fa-bell"></i> <span>Send Notification</span> </a>
            <a href="notification_history.php"> <i class="fas fa-history"></i> <span>Notification History</span> </a>
        
            <div class="menu-title">ACCOUNT</div>
            <a href="profile.php">
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
                    <i class="fas fa-code-branch"></i> Branch Management
                </div>
            </div>
            <div class="topbar-right">
                <div class="quick-dropdown">
                    <button class="quick-add-btn" id="quickAddBtn">
                        <i class="fas fa-plus"></i> <span>Quick Add</span> <i class="fas fa-chevron-down" style="font-size: 0.65rem;"></i>
                    </button>
                    <div class="quick-menu" id="quickMenu">
                        <div class="quick-menu-header"><span><i class="fas fa-plus-circle"></i> Quick Actions</span></div>
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
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
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
                <h2> <i class="fas fa-code-branch" style="color: var(--primary);"></i> Branch Management </h2>
                <p>View and manage all academic branches in the system</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>" data-aos="fade-up">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo nl2br(htmlspecialchars($message)); ?></div>
                </div>
            <?php endif; ?>

            <!-- BRANCHES TABLE -->
            <div class="table-card" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header">
                    <h4>
                        <i class="fas fa-list"></i>
                        All Branches
                    </h4>
                    <div class="total-branches">
                        <i class="fas fa-code-branch"></i> Total: <?php echo count($branches); ?> Branches
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="branchesTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Branch Name</th>
                                <th>Students</th>
                                <th>Teachers</th>
                                <th>Subjects</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                            <tr id="branch-row-<?php echo $branch['id']; ?>">
                                <td><span class="branch-badge"><?php echo htmlspecialchars($branch['branch_code']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong>
                                    <?php if ($branch['short_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($branch['short_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="stat-numbers">
                                        <span><i class="fas fa-user-graduate"></i> <?php echo $branch['student_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-numbers">
                                        <span><i class="fas fa-chalkboard-teacher"></i> <?php echo $branch['teacher_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-numbers">
                                        <span><i class="fas fa-book"></i> <?php echo $branch['subject_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($branch['is_active'] == 1): ?>
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                        <span class="status-inactive"><i class="fas fa-ban"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($branch); ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-toggle" onclick="toggleBranch(<?php echo $branch['id']; ?>, <?php echo $branch['is_active']; ?>)">
                                            <i class="fas fa-power-off"></i> Toggle
                                        </button>
                                        <button class="btn-delete" onclick="deleteBranch(<?php echo $branch['id']; ?>, '<?php echo htmlspecialchars($branch['branch_code']); ?>', <?php echo $branch['student_count']; ?>, <?php echo $branch['teacher_count']; ?>, <?php echo $branch['subject_count']; ?>, '<?php echo addslashes($branch['branch_name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT BRANCH MODAL -->
<div class="modal-edit-overlay" id="editModal">
    <div class="modal-edit-container">
        <div class="modal-edit-header">
            <h5><i class="fas fa-edit"></i> Edit Branch</h5>
            <button class="modal-edit-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editBranchForm">
            <input type="hidden" name="branch_id" id="edit_branch_id">
            <input type="hidden" name="update_branch" value="1">
            <div class="modal-edit-body">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-code"></i> Branch Code</label>
                    <div class="branch-code-display" id="edit_branch_code"></div>
                    <small class="text-muted">Branch code cannot be changed</small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-building"></i> Branch Name *</label>
                    <input type="text" name="branch_name" id="edit_branch_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-tag"></i> Short Name</label>
                    <input type="text" name="short_name" id="edit_short_name" class="form-control" placeholder="e.g., CSE">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Enter branch description"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-calendar"></i> Established Year</label>
                    <input type="number" name="established_year" id="edit_established_year" class="form-control" min="1900" max="2030" placeholder="e.g., 2020">
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Branch Active</label>
                    </div>
                    <small class="text-muted">Inactive branches won't appear in dropdowns</small>
                </div>
            </div>
            <div class="modal-edit-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Branch</button>
            </div>
        </form>
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
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
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
            closeEditModal();
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

    // DataTable initialization
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#branchesTable')) {
            $('#branchesTable').DataTable().destroy();
        }
        
        var $table = $('#branchesTable');
        var headerCols = $table.find('thead th').length;
        var bodyCols = $table.find('tbody tr:first td').length;
        
        console.log('Header columns:', headerCols);
        console.log('Body columns:', bodyCols);
        
        if (headerCols === bodyCols && headerCols === 7) {
            $('#branchesTable').DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    { targets: 0, width: '80px' },
                    { targets: 1, width: '200px' },
                    { targets: 2, width: '80px' },
                    { targets: 3, width: '80px' },
                    { targets: 4, width: '80px' },
                    { targets: 5, width: '80px' },
                    { targets: 6, width: '200px', orderable: false }
                ],
                language: { 
                    search: "Search:", 
                    lengthMenu: "Show _MENU_ entries", 
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: { 
                        first: "First", 
                        last: "Last", 
                        next: "Next", 
                        previous: "Prev" 
                    }
                }
            });
        } else {
            console.warn('Column count mismatch. Header:', headerCols, 'Body:', bodyCols);
            $('#branchesTable').addClass('table table-striped');
        }
    });

    // Edit Modal Functions
    function openEditModal(branch) {
        document.getElementById('edit_branch_id').value = branch.id;
        document.getElementById('edit_branch_code').innerHTML = branch.branch_code;
        document.getElementById('edit_branch_name').value = branch.branch_name;
        document.getElementById('edit_short_name').value = branch.short_name || '';
        document.getElementById('edit_description').value = branch.description || '';
        document.getElementById('edit_established_year').value = branch.established_year || '';
        document.getElementById('edit_is_active').checked = branch.is_active == 1;
        
        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // Toggle Branch Status
    function toggleBranch(id, currentStatus) {
        const newStatus = currentStatus == 1 ? 'deactivate' : 'activate';
        Swal.fire({
            title: `${newStatus === 'activate' ? 'Activate' : 'Deactivate'} Branch?`,
            text: `Are you sure you want to ${newStatus} this branch?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 'activate' ? '#06d6a0' : '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${newStatus}`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `branches.php?toggle_id=${id}`;
            }
        });
    }

    // Cascading Delete Branch
    function deleteBranch(id, branchCode, studentCount, teacherCount, subjectCount, branchName) {
        Swal.fire({
            title: '⚠️ DANGER: Delete Entire Branch!',
            html: `<div style="text-align: left;">
                <p class="fw-bold mb-2">⚠️ <strong>CASCADING DELETE WARNING</strong></p>
                <p>Deleting branch "<strong>${escapeHtml(branchName)} (${escapeHtml(branchCode)})</strong>" will PERMANENTLY remove:</p>
                <ul>
                    <li>📚 <strong>${studentCount}</strong> Student(s) and their <strong class="text-danger">LOGIN ACCOUNTS</strong></li>
                    <li>👨‍🏫 <strong>${teacherCount}</strong> Teacher(s) and their <strong class="text-danger">LOGIN ACCOUNTS</strong></li>
                    <li>📖 <strong>${subjectCount}</strong> Subject(s)</li>
                    <li>📝 All assignments and submissions</li>
                    <li>📄 All study materials</li>
                    <li>📊 All attendance records</li>
                    <li>🎯 All result records</li>
                    <li>🗓️ All timetable entries</li>
                </ul>
                <p class="text-danger fw-bold mt-2">⚠️ This action CANNOT be undone!</p>
                <p class="text-danger mt-2">Students and teachers will be <strong>COMPLETELY REMOVED</strong> from the system and will NOT be able to log in again.</p>
            </div>`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete Everything!',
            cancelButtonText: 'Cancel',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting Branch...',
                    text: 'Please wait while all associated data is being removed.',
                    icon: 'info',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                window.location.href = `branches.php?delete_id=${id}`;
            }
        });
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