<?php
session_start();

/* AUTH CHECK - Allow both teacher and modal access */
if (!isset($_SESSION['username']) || ($_SESSION['usertype'] !== 'teacher' && !isset($_GET['modal']))) {
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

// Get teacher details
$teacher_name = $_SESSION['username'];
$teacher_email = $_SESSION['email'] ?? '';

$teacher_query = mysqli_query($data, "SELECT * FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);
$teacher_branch = $teacher_data['branch'] ?? '';
$teacher_id = $teacher_data['id'] ?? 0;

// Get teacher's subjects
$teacher_subjects = [];
$subject_query = mysqli_query($data, "SELECT s.* FROM subjects s 
                                      JOIN teacher_subjects ts ON s.id = ts.subject_id 
                                      WHERE ts.teacher_id = '$teacher_id' 
                                      ORDER BY s.semester ASC, s.subject_name ASC");
if ($subject_query) {
    while ($row = mysqli_fetch_assoc($subject_query)) {
        $teacher_subjects[] = $row;
    }
}

// Get students for branch
$students = [];
$student_query = mysqli_query($data, "SELECT id, Name, registration_no, Semester FROM admission WHERE Branch = '$teacher_branch' ORDER BY Name");
if ($student_query) {
    while ($row = mysqli_fetch_assoc($student_query)) {
        $students[] = $row;
    }
}

// If teacher has no specific branch, get all students
if (empty($teacher_branch)) {
    $student_query = mysqli_query($data, "SELECT id, Name, registration_no, Semester FROM admission ORDER BY Name");
    if ($student_query) {
        $students = [];
        while ($row = mysqli_fetch_assoc($student_query)) {
            $students[] = $row;
        }
    }
}

// Handle attendance submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_attendance'])) {
    $subject_id = intval($_POST['subject_id']);
    $attendance_date = mysqli_real_escape_string($data, $_POST['attendance_date']);
    $attendance_map = $_POST['attendance'] ?? [];
    
    if (empty($subject_id) || empty($attendance_date) || empty($attendance_map)) {
        $message = "Please select subject/date and mark attendance first.";
        $message_type = "error";
    } else {
        // Delete existing attendance for this subject and date
        mysqli_query($data, "DELETE FROM attendance WHERE subject_id = $subject_id AND date = '$attendance_date' AND marked_by = $teacher_id");
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($attendance_map as $student_id => $status) {
            $student_id = intval($student_id);
            $status = mysqli_real_escape_string($data, $status);
            
            if (!in_array($status, ['present', 'absent', 'late'])) {
                continue;
            }
            
            $insert_query = "INSERT INTO attendance (student_id, subject_id, date, status, marked_by) 
                            VALUES ($student_id, $subject_id, '$attendance_date', '$status', $teacher_id)";
            
            if (mysqli_query($data, $insert_query)) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $message = "success";
            $message_type = "success";
        } else {
            $message = "$success_count records saved, $error_count failed.";
            $message_type = "error";
        }
    }
    
    // If AJAX request, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_type == "success" && $message == "success") {
            echo json_encode(['status' => 'success', 'message' => 'Attendance marked successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
        exit();
    }
    
    header("Location: attendance.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            padding: 0;
            margin: 0;
        }

        .modal-container {
            max-width: 100%;
            padding: 0;
        }

        .form-card {
            background: white;
            border-radius: 0;
            padding: 1.5rem;
            box-shadow: none;
            max-width: 100%;
            margin: 0;
            border: none;
        }

        @media (min-width: 768px) {
            .form-card {
                padding: 2rem;
                border-radius: 0;
            }
        }

        .form-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e1e2f;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (min-width: 768px) {
            .form-title {
                font-size: 1.3rem;
            }
        }

        .form-title i {
            color: #4361ee;
        }

        .form-label {
            font-weight: 500;
            color: #1e1e2f;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .form-label i {
            color: #4361ee;
            font-size: 0.9rem;
        }

        .form-label .required {
            color: #ef476f;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.7rem 0.8rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        @media (min-width: 768px) {
            .form-control, .form-select {
                padding: 0.75rem 1rem;
            }
        }

        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.5rem;
        }

        .col {
            flex: 1;
            padding: 0 0.5rem;
            min-width: 180px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-bottom: 0.5rem;
        }

        @media (min-width: 576px) {
            .btn-submit {
                width: auto;
                margin-bottom: 0;
            }
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            color: white;
        }

        .btn-light {
            background: white;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            border: 2px solid #e9ecef;
            width: 100%;
            transition: all 0.3s ease;
        }

        @media (min-width: 576px) {
            .btn-light {
                width: auto;
            }
        }

        .btn-light:hover {
            background: #f8f9fa;
            border-color: #4361ee;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.5s ease;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(6,214,160,0.1);
            color: #06d6a0;
            border-left: 4px solid #06d6a0;
        }

        .alert-error {
            background: rgba(239,71,111,0.1);
            color: #ef476f;
            border-left: 4px solid #ef476f;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(67,97,238,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-icon i {
            color: #4361ee;
        }

        .info-content h6 {
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            color: #1e1e2f;
        }

        .info-content p {
            color: #666;
            font-size: 0.8rem;
            margin: 0;
        }

        /* Attendance Table Styles */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .attendance-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            border-bottom: 2px solid #e9ecef;
            color: #1e1e2f;
        }

        .attendance-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.8rem;
            vertical-align: middle;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .student-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.7rem;
        }

        .attendance-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-attend {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: none;
        }

        .btn-present {
            background: rgba(6,214,160,0.1);
            color: #06d6a0;
        }
        .btn-present:hover, .btn-present.active {
            background: #06d6a0;
            color: white;
        }

        .btn-absent {
            background: rgba(239,71,111,0.1);
            color: #ef476f;
        }
        .btn-absent:hover, .btn-absent.active {
            background: #ef476f;
            color: white;
        }

        .btn-late {
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }
        .btn-late:hover, .btn-late.active {
            background: #ffc107;
            color: white;
        }

        .btn-mark-all {
            background: rgba(67,97,238,0.1);
            color: #4361ee;
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-mark-all:hover {
            background: #4361ee;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.75rem;
        }

        .d-flex {
            display: flex;
        }

        .flex-wrap {
            flex-wrap: wrap;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .gap-3 {
            gap: 1rem;
        }

        .mt-4 {
            margin-top: 1.5rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .me-2 {
            margin-right: 0.5rem;
        }

        .align-items-center {
            align-items: center;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .justify-content-end {
            justify-content: flex-end;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
    </style>
</head>
<body>

<div class="modal-container">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-calendar-check"></i>
            Mark Attendance
        </div>

        <form method="post" id="attendanceForm" action="">
            <input type="hidden" name="mark_attendance" value="1">
            <input type="hidden" name="subject_id" id="subject_id">
            <input type="hidden" name="attendance_date" id="attendance_date">
            <div id="attendance_data"></div>

            <div class="row">
                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-book"></i> Select Subject <span class="required">*</span>
                    </label>
                    <select class="form-select" id="subjectSelect" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (Sem ' . $subject['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teacher_subjects)): ?>
                        <div class="text-muted mt-1 text-danger">
                            <i class="fas fa-exclamation-triangle"></i> No subjects assigned. Please contact administrator.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Date <span class="required">*</span>
                    </label>
                    <input type="date" class="form-control" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col d-flex align-items-end">
                    <button type="button" class="btn-submit" id="loadStudentsBtn" style="width: 100%;" <?php echo empty($teacher_subjects) ? 'disabled' : ''; ?>>
                        <i class="fas fa-search"></i> Load Students
                    </button>
                </div>
            </div>
        </form>

        <!-- Students Table (Hidden initially) -->
        <div id="studentsSection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                <h6 style="font-weight: 600; margin: 0;">
                    <i class="fas fa-users"></i> Student List
                </h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-mark-all" onclick="markAll('present')">
                        <i class="fas fa-check-circle"></i> All Present
                    </button>
                    <button type="button" class="btn-mark-all" onclick="markAll('absent')">
                        <i class="fas fa-times-circle"></i> All Absent
                    </button>
                    <button type="button" class="btn-mark-all" onclick="markAll('late')">
                        <i class="fas fa-clock"></i> All Late
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="attendance-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Registration No</th>
                            <th>Semester</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" id="closeModalBtn">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn-submit" id="saveAttendanceBtn">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h6>Important Notes</h6>
                    <p>
                        <i class="fas fa-check-circle text-success me-2"></i>Select subject and date to load students<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Click on Present/Absent/Late to mark attendance<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Use "All Present" for quick marking<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Previous attendance records will be overwritten
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Store attendance data
    let attendanceData = {};

    // Load students when subject is selected
    document.getElementById('loadStudentsBtn').addEventListener('click', function() {
        const subjectId = document.getElementById('subjectSelect').value;
        const attendanceDate = document.getElementById('attendanceDate').value;
        
        if (!subjectId) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a subject!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (!attendanceDate) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a date!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Set hidden fields
        document.getElementById('subject_id').value = subjectId;
        document.getElementById('attendance_date').value = attendanceDate;
        
        // Show loading
        Swal.fire({
            title: 'Loading Students...',
            text: 'Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Fetch students via AJAX (using PHP to get students)
        const students = <?php echo json_encode($students); ?>;
        
        Swal.close();
        
        // Display students table
        displayStudents(students);
        document.getElementById('studentsSection').style.display = 'block';
    });
    
    // Display students in table
    function displayStudents(students) {
        const tbody = document.getElementById('studentsTableBody');
        tbody.innerHTML = '';
        
        if (students.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No students found</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        students.forEach((student, index) => {
            const row = tbody.insertRow();
            row.setAttribute('data-student-id', student.id);
            
            // Serial number
            const cell1 = row.insertCell(0);
            cell1.textContent = index + 1;
            
            // Student name with avatar
            const cell2 = row.insertCell(1);
            cell2.innerHTML = `
                <div class="student-info">
                    <div class="student-avatar">${student.Name.charAt(0).toUpperCase()}</div>
                    <div>
                        <strong>${escapeHtml(student.Name)}</strong>
                    </div>
                </div>
            `;
            
            // Registration number
            const cell3 = row.insertCell(2);
            cell3.textContent = student.registration_no || 'N/A';
            
            // Semester
            const cell4 = row.insertCell(3);
            cell4.textContent = student.Semester || 'N/A';
            
            // Attendance buttons
            const cell5 = row.insertCell(4);
            cell5.innerHTML = `
                <div class="attendance-buttons">
                    <button type="button" class="btn-attend btn-present" onclick="setAttendance(this, 'present', ${student.id})">
                        <i class="fas fa-check-circle"></i> Present
                    </button>
                    <button type="button" class="btn-attend btn-absent" onclick="setAttendance(this, 'absent', ${student.id})">
                        <i class="fas fa-times-circle"></i> Absent
                    </button>
                    <button type="button" class="btn-attend btn-late" onclick="setAttendance(this, 'late', ${student.id})">
                        <i class="fas fa-clock"></i> Late
                    </button>
                </div>
            `;
        });
    }
    
    // Set attendance for a student
    function setAttendance(button, status, studentId) {
        const row = button.closest('tr');
        const presentBtn = row.querySelector('.btn-present');
        const absentBtn = row.querySelector('.btn-absent');
        const lateBtn = row.querySelector('.btn-late');
        
        // Remove active class from all buttons in this row
        presentBtn.classList.remove('active');
        absentBtn.classList.remove('active');
        lateBtn.classList.remove('active');
        
        // Add active class to clicked button
        button.classList.add('active');
        
        // Store attendance data
        attendanceData[studentId] = status;
    }
    
    // Mark all students with a specific status
    function markAll(status) {
        const rows = document.querySelectorAll('#studentsTable tbody tr');
        rows.forEach(row => {
            const studentId = row.getAttribute('data-student-id');
            if (studentId) {
                const presentBtn = row.querySelector('.btn-present');
                const absentBtn = row.querySelector('.btn-absent');
                const lateBtn = row.querySelector('.btn-late');
                
                presentBtn.classList.remove('active');
                absentBtn.classList.remove('active');
                lateBtn.classList.remove('active');
                
                if (status === 'present') {
                    presentBtn.classList.add('active');
                } else if (status === 'absent') {
                    absentBtn.classList.add('active');
                } else if (status === 'late') {
                    lateBtn.classList.add('active');
                }
                
                attendanceData[studentId] = status;
            }
        });
    }
    
    // Save attendance
    function saveAttendance() {
        const subjectId = document.getElementById('subjectSelect').value;
        const attendanceDate = document.getElementById('attendanceDate').value;
        
        if (!subjectId) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a subject!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (Object.keys(attendanceData).length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please mark attendance for at least one student!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Build form data
        const formData = new FormData();
        formData.append('mark_attendance', '1');
        formData.append('subject_id', subjectId);
        formData.append('attendance_date', attendanceDate);
        
        for (const [studentId, status] of Object.entries(attendanceData)) {
            formData.append(`attendance[${studentId}]`, status);
        }
        
        // Show loading
        Swal.fire({
            title: 'Saving Attendance...',
            text: 'Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit via AJAX
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        confirmButtonColor: '#4361ee',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            closeModal();
                            if (window.parent && window.parent.location) {
                                window.parent.location.reload();
                            }
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message,
                        confirmButtonColor: '#4361ee'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) errorMsg = response.message;
                } catch(e) {}
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMsg,
                    confirmButtonColor: '#4361ee'
                });
            }
        });
    }
    
    // Close modal function
    function closeModal() {
        const modal = document.querySelector('#quickAddModal');
        if (modal) {
            const bootstrapModal = bootstrap.Modal.getInstance(modal);
            if (bootstrapModal) {
                bootstrapModal.hide();
            }
        }
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            const bsModal = bootstrap.Modal.getInstance(openModal);
            if (bsModal) bsModal.hide();
        }
    }
    
    // Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    // Event listeners
    document.getElementById('saveAttendanceBtn').addEventListener('click', saveAttendance);
    document.getElementById('closeModalBtn').addEventListener('click', closeModal);
    
    // Set default date to today's date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('attendanceDate').value = today;
</script>

</body>
</html>