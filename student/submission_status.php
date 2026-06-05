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
$student_branch = $_SESSION['student_branch'] ?? '';
$student_semester = $_SESSION['student_semester'] ?? 1;

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id == 0) {
    header("Location: assignments.php?msg=Invalid assignment&type=error");
    exit();
}

// Fetch submission details
$submission_query = mysqli_query($data, "
    SELECT 
        s.id as submission_id,
        s.submission_date,
        s.submission_time,
        s.file_path,
        s.remarks,
        s.marks,
        s.feedback,
        s.status,
        s.created_at,
        a.id as assignment_id,
        a.title as assignment_title,
        a.description,
        a.due_date,
        a.due_time,
        a.total_marks,
        sub.subject_name,
        sub.subject_code,
        t.name as teacher_name,
        t.email as teacher_email
    FROM submissions s
    JOIN assignments a ON a.id = s.assignment_id
    JOIN subjects sub ON sub.id = a.subject_id
    LEFT JOIN teacher t ON t.id = a.teacher_id
    WHERE s.assignment_id = $assignment_id AND s.student_id = $student_id
");

if (!$submission_query || mysqli_num_rows($submission_query) == 0) {
    header("Location: assignments.php?msg=Submission not found&type=error");
    exit();
}

$submission = mysqli_fetch_assoc($submission_query);

// Format dates
$submission_date_formatted = date('d M Y', strtotime($submission['submission_date']));
$submission_time_formatted = date('h:i A', strtotime($submission['submission_time']));
$due_date_formatted = date('d M Y', strtotime($submission['due_date']));
$due_time_formatted = date('h:i A', strtotime($submission['due_time']));

// Check if submission was late
$due_datetime = strtotime($submission['due_date'] . ' ' . $submission['due_time']);
$submission_datetime = strtotime($submission['submission_date'] . ' ' . $submission['submission_time']);
$is_late = ($submission_datetime > $due_datetime);

// Get status display
$status_badge = '';
$status_text = '';
$status_color = '';

switch($submission['status']) {
    case 'submitted':
        $status_text = 'Submitted - Awaiting Grading';
        $status_color = 'warning';
        $status_badge = '<span class="badge bg-warning"><i class="fas fa-clock"></i> Pending Review</span>';
        break;
    case 'late':
        $status_text = 'Late Submission - Awaiting Grading';
        $status_color = 'danger';
        $status_badge = '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Late Submission</span>';
        break;
    case 'graded':
        $status_text = 'Graded';
        $status_color = 'success';
        $status_badge = '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Graded</span>';
        break;
    default:
        $status_text = 'Unknown';
        $status_color = 'secondary';
        $status_badge = '<span class="badge bg-secondary">Unknown</span>';
}

// Get file extension for icon
$file_extension = strtolower(pathinfo($submission['file_path'], PATHINFO_EXTENSION));
$file_icon = 'fa-file';
if ($file_extension == 'pdf') $file_icon = 'fa-file-pdf';
elseif (in_array($file_extension, ['doc', 'docx'])) $file_icon = 'fa-file-word';
elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) $file_icon = 'fa-file-image';
elseif ($file_extension == 'zip') $file_icon = 'fa-file-archive';
elseif ($file_extension == 'txt') $file_icon = 'fa-file-alt';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Submission Status | Student - StudyBuddyHub</title>

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

        /* ===== CONTENT AREA ===== */
        .content { padding: 1.5rem; }
        @media (max-width: 768px) { .content { padding: 1rem; } }

        /* Status Card */
        .status-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .status-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }
        
        .status-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .status-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .status-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .status-body {
            padding: 2rem;
        }
        
        .status-badge-large {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: var(--light);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .marks-section {
            background: linear-gradient(135deg, rgba(79,70,229,0.05), rgba(79,70,229,0.02));
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .marks-score {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .feedback-section {
            background: var(--light);
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .feedback-text {
            color: var(--dark);
            line-height: 1.6;
            margin-top: 0.5rem;
        }
        
        .btn-back {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            color: white;
        }
        
        .btn-download {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid var(--success);
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .btn-download:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }
        
        .late-badge {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
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
            .profile-dropdown { right: 10px; left: 10px; min-width: auto; }
            .status-body { padding: 1.5rem; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
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
                <span class="page-title"><i class="fas fa-tasks me-2" style="color: var(--primary);"></i>Submission Status</span>
            </div>
            <div class="topbar-actions">
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
            <div class="status-container">
                <!-- Status Card -->
                <div class="status-card" data-aos="fade-up">
                    <div class="status-header">
                        <i class="fas fa-file-alt"></i>
                        <h2><?php echo htmlspecialchars($submission['assignment_title']); ?></h2>
                        <p><?php echo htmlspecialchars($submission['subject_name']); ?> (<?php echo htmlspecialchars($submission['subject_code']); ?>)</p>
                    </div>
                    
                    <div class="status-body">
                        <!-- Status Badge -->
                        <div style="text-align: center;">
                            <?php echo $status_badge; ?>
                            <?php if ($is_late && $submission['status'] != 'graded'): ?>
                                <span class="late-badge"><i class="fas fa-hourglass-end"></i> Late Submission</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Assignment Details -->
                        <div class="info-section">
                            <h5 style="margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-info-circle" style="color: var(--primary);"></i> Assignment Details</h5>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-heading"></i> Title</div>
                                <div class="info-value"><?php echo htmlspecialchars($submission['assignment_title']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-align-left"></i> Description</div>
                                <div class="info-value"><?php echo htmlspecialchars($submission['description'] ?: 'No description provided.'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-calendar-alt"></i> Due Date & Time</div>
                                <div class="info-value"><?php echo $due_date_formatted . ' at ' . $due_time_formatted; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-star"></i> Total Marks</div>
                                <div class="info-value"><?php echo $submission['total_marks']; ?> marks</div>
                            </div>
                        </div>
                        
                        <!-- Submission Details -->
                        <div class="info-section">
                            <h5 style="margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-upload" style="color: var(--primary);"></i> Submission Details</h5>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-calendar-check"></i> Submitted On</div>
                                <div class="info-value"><?php echo $submission_date_formatted . ' at ' . $submission_time_formatted; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-file"></i> Submitted File</div>
                                <div class="info-value">
                                    <a href="download_submission.php?id=<?php echo $submission['submission_id']; ?>" class="btn-download" style="padding: 0.3rem 0.8rem; font-size: 0.75rem;">
                                        <i class="fas <?php echo $file_icon; ?>"></i> Download My Submission
                                    </a>
                                </div>
                            </div>
                            <?php if (!empty($submission['remarks'])): ?>
                            <div class="info-row">
                                <div class="info-label"><i class="fas fa-comment"></i> Your Remarks</div>
                                <div class="info-value"><?php echo htmlspecialchars($submission['remarks']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Marks & Feedback Section (if graded) -->
                        <?php if ($submission['status'] == 'graded'): ?>
                            <div class="marks-section">
                                <h5 style="margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-chart-line"></i> Your Score</h5>
                                <div class="marks-score"><?php echo $submission['marks']; ?> / <?php echo $submission['total_marks']; ?></div>
                                <div class="progress" style="height: 8px; margin-top: 1rem;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($submission['marks'] / $submission['total_marks']) * 100; ?>%"></div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-primary">Percentage: <?php echo round(($submission['marks'] / $submission['total_marks']) * 100); ?>%</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($submission['feedback'])): ?>
                            <div class="feedback-section">
                                <h5 style="margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-comment-dots" style="color: var(--primary);"></i> Teacher's Feedback</h5>
                                <div class="feedback-text">
                                    <i class="fas fa-quote-left" style="color: var(--primary-light); margin-right: 0.5rem;"></i>
                                    <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-chalkboard-user"></i> Reviewed by: <?php echo htmlspecialchars($submission['teacher_name'] ?: 'Teacher'); ?>
                                        <?php if (!empty($submission['teacher_email'])): ?>
                                            (<?php echo htmlspecialchars($submission['teacher_email']); ?>)
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="feedback-section" style="text-align: center; background: rgba(245,158,11,0.05);">
                                <i class="fas fa-hourglass-half" style="font-size: 2rem; color: var(--warning); margin-bottom: 0.5rem;"></i>
                                <h5>Awaiting Grading</h5>
                                <p class="text-muted">Your submission is waiting for teacher's review. You will be notified once it's graded.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
                            <a href="assignments.php" class="btn-back">
                                <i class="fas fa-arrow-left"></i> Back to Assignments
                            </a>
                            <?php if ($submission['status'] != 'graded'): ?>
                                <a href="submit_assignment.php?id=<?php echo $assignment_id; ?>" class="btn-back" style="background: linear-gradient(135deg, var(--warning), #ea580c);">
                                    <i class="fas fa-edit"></i> Resubmit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
</script>

</body>
</html>