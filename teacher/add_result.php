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

// Fetch existing results for selected subject
$existing_results = [];
if (isset($_GET['subject_id']) && isset($_GET['exam_type'])) {
    $subject_id = intval($_GET['subject_id']);
    $exam_type = mysqli_real_escape_string($data, $_GET['exam_type']);
    
    $results_query = mysqli_query($data, "SELECT * FROM results WHERE subject_id = $subject_id AND exam_type = '$exam_type'");
    while ($row = mysqli_fetch_assoc($results_query)) {
        $existing_results[$row['student_id']] = $row;
    }
}

// Handle result submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_results'])) {
    $subject_id = intval($_POST['subject_id']);
    $exam_type = mysqli_real_escape_string($data, $_POST['exam_type']);
    $exam_date = mysqli_real_escape_string($data, $_POST['exam_date']);
    $results = $_POST['results'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    if (empty($subject_id) || empty($exam_type) || empty($results)) {
        $message = "Please select subject, exam type and enter marks!";
        $message_type = "error";
    } else {
        $success_count = 0;
        $error_count = 0;
        
        // Get max marks (default 100)
        $max_marks = 100;
        
        foreach ($results as $student_id => $marks) {
            $student_id = intval($student_id);
            $marks = intval($marks);
            $remark = mysqli_real_escape_string($data, trim($remarks[$student_id] ?? ''));
            
            // Skip if marks is empty
            if ($marks === '' || $marks < 0) {
                continue;
            }
            
            // Validate marks
            if ($marks > $max_marks) {
                $marks = $max_marks;
            }
            
            // Calculate grade
            $percentage = ($marks / $max_marks) * 100;
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 80) $grade = 'A';
            elseif ($percentage >= 70) $grade = 'B+';
            elseif ($percentage >= 60) $grade = 'B';
            elseif ($percentage >= 50) $grade = 'C';
            elseif ($percentage >= 40) $grade = 'D';
            else $grade = 'F';
            
            // Check if result already exists
            $check_query = mysqli_query($data, "SELECT id FROM results WHERE student_id = $student_id AND subject_id = $subject_id AND exam_type = '$exam_type'");
            
            if (mysqli_num_rows($check_query) > 0) {
                // Update existing result
                $update_query = "UPDATE results SET 
                                marks = $marks,
                                grade = '$grade',
                                exam_date = '$exam_date',
                                remarks = '$remark'
                                WHERE student_id = $student_id AND subject_id = $subject_id AND exam_type = '$exam_type'";
                
                if (mysqli_query($data, $update_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new result
                $insert_query = "INSERT INTO results (student_id, subject_id, exam_type, marks, max_marks, grade, exam_date, remarks, created_at) 
                                VALUES ($student_id, $subject_id, '$exam_type', $marks, $max_marks, '$grade', '$exam_date', '$remark', NOW())";
                
                if (mysqli_query($data, $insert_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($error_count == 0 && $success_count > 0) {
            $message = "success";
            $message_type = "success";
        } elseif ($success_count > 0) {
            $message = "$success_count results saved, $error_count failed.";
            $message_type = "error";
        } else {
            $message = "No results were saved. Please enter marks for at least one student.";
            $message_type = "error";
        }
    }
    
    // If AJAX request, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_type == "success" && $message == "success") {
            echo json_encode(['status' => 'success', 'message' => 'Results saved successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
        exit();
    }
    
    header("Location: results.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Results</title>

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

        .form-control, .form-select, textarea {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.7rem 0.8rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        @media (min-width: 768px) {
            .form-control, .form-select, textarea {
                padding: 0.75rem 1rem;
            }
        }

        .form-control:focus, .form-select:focus, textarea:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
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

        /* Results Table Styles */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .results-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            border-bottom: 2px solid #e9ecef;
            color: #1e1e2f;
        }

        .results-table td {
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

        .marks-input {
            width: 80px;
            padding: 0.4rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
        }
        .marks-input:focus {
            border-color: #4361ee;
            outline: none;
        }

        .grade-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .grade-a-plus { background: #d4edda; color: #155724; }
        .grade-a { background: #d1ecf1; color: #0c5460; }
        .grade-b-plus { background: #d4edda; color: #155724; }
        .grade-b { background: #fff3cd; color: #856404; }
        .grade-c { background: #fff3cd; color: #856404; }
        .grade-d { background: #f8d7da; color: #721c24; }
        .grade-f { background: #f8d7da; color: #721c24; }

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
            max-height: 60vh;
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
            <i class="fas fa-chart-line"></i>
            Add Student Results
        </div>

        <form method="post" id="resultsForm" action="">
            <input type="hidden" name="submit_results" value="1">
            <div id="results_data"></div>

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
                        <i class="fas fa-file-alt"></i> Exam Type <span class="required">*</span>
                    </label>
                    <select class="form-select" id="examTypeSelect" required>
                        <option value="">-- Select Exam Type --</option>
                        <option value="internal">Internal Exam</option>
                        <option value="external">External Exam</option>
                        <option value="practical">Practical Exam</option>
                        <option value="quiz">Quiz</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>

                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Exam Date <span class="required">*</span>
                    </label>
                    <input type="date" class="form-control" id="examDate" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col d-flex align-items-end">
                    <button type="button" class="btn-submit" id="loadStudentsBtn" style="width: 100%;" <?php echo empty($teacher_subjects) ? 'disabled' : ''; ?>>
                        <i class="fas fa-search"></i> Load Students
                    </button>
                </div>
            </div>
        </form>

        <!-- Results Table (Hidden initially) -->
        <div id="resultsSection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                <h6 style="font-weight: 600; margin: 0;">
                    <i class="fas fa-users"></i> Student Results
                </h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-light" style="padding: 0.3rem 0.8rem;" onclick="fillAllMarks(100)">
                        <i class="fas fa-star"></i> Set All 100
                    </button>
                    <button type="button" class="btn-light" style="padding: 0.3rem 0.8rem;" onclick="fillAllMarks(85)">
                        <i class="fas fa-star"></i> Set All 85
                    </button>
                    <button type="button" class="btn-light" style="padding: 0.3rem 0.8rem;" onclick="fillAllMarks(75)">
                        <i class="fas fa-star"></i> Set All 75
                    </button>
                    <button type="button" class="btn-light" style="padding: 0.3rem 0.8rem;" onclick="fillAllMarks(60)">
                        <i class="fas fa-star"></i> Set All 60
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Registration No</th>
                            <th>Semester</th>
                            <th>Marks (out of 100)</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" id="closeModalBtn">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn-submit" id="saveResultsBtn">
                    <i class="fas fa-save"></i> Save Results
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
                        <i class="fas fa-check-circle text-success me-2"></i>Marks are out of 100. Grade will be auto-calculated<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Use the buttons above to quickly fill all marks<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Previous results will be updated if they exist<br>
                        <i class="fas fa-check-circle text-success me-2"></i>You can add remarks for each student
                    </p>
                </div>
            </div>
        </div>

        <!-- Grade Scale Info -->
        <div class="info-card" style="margin-top: 0.75rem;">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon" style="background: rgba(255,193,7,0.1);">
                    <i class="fas fa-chart-simple" style="color: #ffc107;"></i>
                </div>
                <div class="info-content">
                    <h6>Grade Scale</h6>
                    <p>
                        <span class="grade-badge grade-a-plus">A+</span> 90-100% |
                        <span class="grade-badge grade-a">A</span> 80-89% |
                        <span class="grade-badge grade-b-plus">B+</span> 70-79% |
                        <span class="grade-badge grade-b">B</span> 60-69% |
                        <span class="grade-badge grade-c">C</span> 50-59% |
                        <span class="grade-badge grade-d">D</span> 40-49% |
                        <span class="grade-badge grade-f">F</span> Below 40%
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let studentsData = [];
    let existingResults = <?php echo json_encode($existing_results); ?>;
    
    // Load students when subject is selected
    document.getElementById('loadStudentsBtn').addEventListener('click', function() {
        const subjectId = document.getElementById('subjectSelect').value;
        const examType = document.getElementById('examTypeSelect').value;
        const examDate = document.getElementById('examDate').value;
        
        if (!subjectId) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a subject!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (!examType) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select an exam type!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (!examDate) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select an exam date!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Loading Students...',
            text: 'Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Get students from PHP
        studentsData = <?php echo json_encode($students); ?>;
        
        Swal.close();
        
        // Display students table
        displayStudents(studentsData);
        document.getElementById('resultsSection').style.display = 'block';
    });
    
    // Calculate grade based on marks
    function calculateGrade(marks) {
        if (marks >= 90) return { grade: 'A+', class: 'grade-a-plus' };
        if (marks >= 80) return { grade: 'A', class: 'grade-a' };
        if (marks >= 70) return { grade: 'B+', class: 'grade-b-plus' };
        if (marks >= 60) return { grade: 'B', class: 'grade-b' };
        if (marks >= 50) return { grade: 'C', class: 'grade-c' };
        if (marks >= 40) return { grade: 'D', class: 'grade-d' };
        return { grade: 'F', class: 'grade-f' };
    }
    
    // Update grade display when marks change
    function updateGrade(inputElement, studentId) {
        const marks = parseInt(inputElement.value) || 0;
        const gradeInfo = calculateGrade(marks);
        
        const row = inputElement.closest('tr');
        const gradeCell = row.querySelector('.grade-cell');
        const remarksInput = row.querySelector('.remarks-input');
        
        gradeCell.innerHTML = `<span class="grade-badge ${gradeInfo.class}">${gradeInfo.grade}</span>`;
        
        // Auto-fill remark based on grade
        if (remarksInput && !remarksInput.value) {
            if (gradeInfo.grade === 'A+' || gradeInfo.grade === 'A') {
                remarksInput.value = 'Excellent performance!';
            } else if (gradeInfo.grade === 'B+' || gradeInfo.grade === 'B') {
                remarksInput.value = 'Good work. Keep it up!';
            } else if (gradeInfo.grade === 'C') {
                remarksInput.value = 'Satisfactory. Can improve further.';
            } else if (gradeInfo.grade === 'D') {
                remarksInput.value = 'Needs improvement. Please work harder.';
            } else if (gradeInfo.grade === 'F') {
                remarksInput.value = 'Failed. Need to retake the exam.';
            }
        }
    }
    
    // Display students in table
    function displayStudents(students) {
        const tbody = document.getElementById('resultsTableBody');
        tbody.innerHTML = '';
        
        if (students.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No students found</p>
                     </td
                 </tr
            `;
            return;
        }
        
        students.forEach((student, index) => {
            const row = tbody.insertRow();
            const existingResult = existingResults[student.id];
            
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
            
            // Marks input
            const cell5 = row.insertCell(4);
            const marksValue = existingResult ? existingResult.marks : '';
            cell5.innerHTML = `
                <input type="number" class="marks-input" 
                       name="marks_${student.id}" 
                       data-student-id="${student.id}"
                       value="${marksValue}"
                       min="0" max="100" 
                       placeholder="0-100"
                       oninput="updateGrade(this, ${student.id})">
            `;
            
            // Grade display
            const cell6 = row.insertCell(5);
            const gradeInfo = existingResult ? calculateGrade(existingResult.marks) : { grade: '-', class: '' };
            cell6.innerHTML = `<span class="grade-badge ${gradeInfo.class}" id="grade_${student.id}">${gradeInfo.grade}</span>`;
            cell6.className = 'grade-cell';
            
            // Remarks input
            const cell7 = row.insertCell(6);
            const remarksValue = existingResult ? (existingResult.remarks || '') : '';
            cell7.innerHTML = `
                <input type="text" class="form-control" 
                       name="remarks_${student.id}"
                       data-student-id="${student.id}"
                       value="${escapeHtml(remarksValue)}"
                       placeholder="Add remarks..."
                       style="min-width: 150px; padding: 0.3rem 0.5rem; font-size: 0.75rem;">
            `;
        });
    }
    
    // Fill all marks with a specific value
    function fillAllMarks(value) {
        const marksInputs = document.querySelectorAll('.marks-input');
        marksInputs.forEach(input => {
            input.value = value;
            const studentId = input.getAttribute('data-student-id');
            updateGrade(input, studentId);
        });
    }
    
    // Save results
    function saveResults() {
        const subjectId = document.getElementById('subjectSelect').value;
        const examType = document.getElementById('examTypeSelect').value;
        const examDate = document.getElementById('examDate').value;
        
        if (!subjectId) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a subject!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (!examType) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select an exam type!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Collect results data
        const results = {};
        const remarks = {};
        let hasData = false;
        
        const marksInputs = document.querySelectorAll('.marks-input');
        marksInputs.forEach(input => {
            const studentId = input.getAttribute('data-student-id');
            const marks = input.value;
            if (marks !== '' && marks !== null) {
                results[studentId] = marks;
                hasData = true;
            }
        });
        
        const remarksInputs = document.querySelectorAll('input[name^="remarks_"]');
        remarksInputs.forEach(input => {
            const studentId = input.getAttribute('data-student-id');
            remarks[studentId] = input.value;
        });
        
        if (!hasData) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter marks for at least one student!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Build form data
        const formData = new FormData();
        formData.append('submit_results', '1');
        formData.append('subject_id', subjectId);
        formData.append('exam_type', examType);
        formData.append('exam_date', examDate);
        
        for (const [studentId, marks] of Object.entries(results)) {
            formData.append(`results[${studentId}]`, marks);
        }
        for (const [studentId, remark] of Object.entries(remarks)) {
            formData.append(`remarks[${studentId}]`, remark);
        }
        
        // Show loading
        Swal.fire({
            title: 'Saving Results...',
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
    document.getElementById('saveResultsBtn').addEventListener('click', saveResults);
    document.getElementById('closeModalBtn').addEventListener('click', closeModal);
    
    // Set default date to today's date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('examDate').value = today;
</script>

</body>
</html>