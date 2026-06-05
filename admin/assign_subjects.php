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

// Handle Add Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $academic_year = mysqli_real_escape_string($data, trim($_POST['academic_year'] ?? date('Y') . '-' . (date('Y')+1)));

    // Check if assignment already exists for this teacher, subject, AND academic year
    $check_sql = "SELECT id FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id AND academic_year = '$academic_year'";
    $check_result = mysqli_query($data, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $message = "This subject is already assigned to this teacher for the academic year $academic_year!";
        $message_type = "error";
    } else {
        $stmt = mysqli_prepare($data, "INSERT INTO teacher_subjects (teacher_id, subject_id, academic_year) VALUES (?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $subject_id, $academic_year);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Subject assigned successfully!";
                $message_type = "success";
                
                // Redirect to refresh the page and show success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $message = "Failed to assign subject: " . mysqli_error($data);
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Remove Assignment
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $delete_sql = "DELETE FROM teacher_subjects WHERE id = $id";
    if (mysqli_query($data, $delete_sql)) {
        $message = "Assignment removed successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to remove assignment: " . mysqli_error($data);
        $message_type = "error";
    }
}

// Get success message from URL parameter
if (isset($_GET['success']) && $_GET['success'] == 1 && empty($message)) {
    $message = "Subject assigned successfully!";
    $message_type = "success";
}

// Get all branches
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

// Get current academic year
$current_year = date('Y');
$next_year = $current_year + 1;
$default_academic_year = $current_year . '-' . $next_year;

// Get all assignments
$assignments_query = mysqli_query($data, "SELECT ts.id, ts.academic_year,
                                          t.id as teacher_id, t.name as teacher_name,
                                          s.id as subject_id, s.subject_code, s.subject_name, s.branch as subject_branch, s.semester
                                          FROM teacher_subjects ts
                                          JOIN teacher t ON t.id = ts.teacher_id
                                          JOIN subjects s ON s.id = ts.subject_id
                                          ORDER BY ts.academic_year DESC");
$assignments = [];
while ($row = mysqli_fetch_assoc($assignments_query)) {
    $assignments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h2 { font-size: 1.5rem; font-weight: 700; color: #1e1e2f; }
        .page-header p { color: #6c757d; margin-top: 0.25rem; }
        .card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border: none; }
        .card-title { font-size: 1rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e9ecef; }
        .card-title i { color: #4361ee; margin-right: 0.5rem; }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #1e1e2f; margin-bottom: 0.3rem; }
        .form-control, .form-select { border: 1px solid #ddd; border-radius: 8px; padding: 0.6rem 0.8rem; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: #4361ee; box-shadow: 0 0 0 2px rgba(67,97,238,0.1); outline: none; }
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
        .col { flex: 1; padding: 0 0.5rem; min-width: 180px; }
        .btn-primary { background: #4361ee; border: none; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 500; font-size: 0.9rem; }
        .btn-primary:hover { background: #3a0ca3; }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-danger-sm { background: #ef476f; color: white; border: none; padding: 0.3rem 0.7rem; border-radius: 6px; font-size: 0.75rem; }
        .btn-danger-sm:hover { background: #d63e62; }
        .alert { border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; }
        .alert-success { background: #d4edda; color: #155724; border: none; }
        .alert-danger { background: #f8d7da; color: #721c24; border: none; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 0.75rem 0.5rem; background: #f8f9fa; font-weight: 600; font-size: 0.75rem; border-bottom: 1px solid #dee2e6; }
        .table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #dee2e6; font-size: 0.85rem; vertical-align: middle; }
        .badge { padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; }
        .badge-branch { background: #e3f2fd; color: #1976d2; }
        .badge-semester { background: #e8f5e9; color: #388e3c; }
        .loading { display: none; text-align: center; padding: 0.5rem; color: #4361ee; font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 2rem; color: #6c757d; }
        @media (max-width: 768px) { .container { padding: 1rem; } .col { min-width: 100%; margin-bottom: 0.75rem; } }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-random"></i> Assign Subjects to Teachers</h2>
        <p>Select branch, semester, subject, and teacher</p>
    </div>

    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo ($message_type == 'success') ? 'success' : 'danger'; ?>">
            <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Assignment Form -->
    <div class="card">
        <div class="card-title"><i class="fas fa-plus-circle"></i> New Assignment</div>
        
        <form method="post" id="assignForm">
            <input type="hidden" name="action" value="assign">
            <div class="row">
                <div class="col">
                    <label class="form-label">Branch</label>
                    <select name="branch" class="form-select" id="branchSelect" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select" id="semesterSelect" disabled required>
                        <option value="">Select Branch First</option>
                        <option value="1">1st Semester</option>
                        <option value="2">2nd Semester</option>
                        <option value="3">3rd Semester</option>
                        <option value="4">4th Semester</option>
                        <option value="5">5th Semester</option>
                        <option value="6">6th Semester</option>
                    </select>
                </div>

                <div class="col">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" id="subjectSelect" disabled required>
                        <option value="">Select Semester First</option>
                    </select>
                    <div class="loading" id="subjectLoading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                </div>

                <div class="col">
                    <label class="form-label">Teacher</label>
                    <select name="teacher_id" class="form-select" id="teacherSelect" disabled required>
                        <option value="">Select Branch First</option>
                    </select>
                    <div class="loading" id="teacherLoading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                </div>

                <div class="col">
                    <label class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" value="<?php echo $default_academic_year; ?>" placeholder="2025-2026" id="academicYear" required>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-save"></i> Assign Subject
                </button>
            </div>
        </form>
    </div>

    <!-- Current Assignments -->
    <div class="card">
        <div class="card-title"><i class="fas fa-list"></i> Current Assignments (<?php echo count($assignments); ?>)</div>
        
        <?php if (!empty($assignments)): ?>
        <div style="overflow-x: auto;">
            <table class="table" id="assignmentsTable">
                <thead>
                    <tr><th>Teacher</th><th>Subject</th><th>Branch</th><th>Sem</th><th>Year</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['teacher_name']); ?></td>
                        <td><?php echo htmlspecialchars($a['subject_code']); ?><br><small><?php echo htmlspecialchars($a['subject_name']); ?></small></td>
                        <td><span class="badge badge-branch"><?php echo htmlspecialchars($a['subject_branch']); ?></span></td>
                        <td><span class="badge badge-semester"><?php echo $a['semester']; ?></span></td>
                        <td><?php echo htmlspecialchars($a['academic_year']); ?></td>
                        <td><a href="javascript:void(0)" onclick="removeAssign(<?php echo $a['id']; ?>)" class="btn-danger-sm"><i class="fas fa-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>No assignments yet</p></div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
// Initialize DataTable
<?php if (!empty($assignments)): ?>
$(document).ready(function() {
    $('#assignmentsTable').DataTable({ pageLength: 10, order: [[4, 'desc']], searching: true });
});
<?php endif; ?>

const branchSelect = document.getElementById('branchSelect');
const semesterSelect = document.getElementById('semesterSelect');
const subjectSelect = document.getElementById('subjectSelect');
const teacherSelect = document.getElementById('teacherSelect');
const submitBtn = document.getElementById('submitBtn');

// When branch changes
branchSelect.addEventListener('change', function() {
    const branch = this.value;
    if (branch) {
        semesterSelect.disabled = false;
        loadAllTeachers();
        subjectSelect.disabled = true;
        subjectSelect.innerHTML = '<option value="">Select Semester First</option>';
        semesterSelect.selectedIndex = 0;
        submitBtn.disabled = true;
    } else {
        semesterSelect.disabled = true;
        subjectSelect.disabled = true;
        teacherSelect.disabled = true;
        submitBtn.disabled = true;
    }
});

// When semester changes
semesterSelect.addEventListener('change', function() {
    const branch = branchSelect.value;
    const semester = this.value;
    if (branch && semester) {
        loadSubjects(branch, semester);
    }
});

function checkSubmit() {
    submitBtn.disabled = !(subjectSelect.value && teacherSelect.value && semesterSelect.value && branchSelect.value);
}

subjectSelect.addEventListener('change', checkSubmit);
teacherSelect.addEventListener('change', checkSubmit);

// Load subjects
function loadSubjects(branch, semester) {
    subjectSelect.disabled = true;
    subjectSelect.innerHTML = '<option value="">Loading...</option>';
    document.getElementById('subjectLoading').style.display = 'block';
    
    fetch(`../ajax/get_subjects.php?branch=${encodeURIComponent(branch)}&semester=${semester}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('subjectLoading').style.display = 'none';
            if (data.success && data.subjects.length > 0) {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                data.subjects.forEach(s => {
                    subjectSelect.innerHTML += `<option value="${s.id}">${s.subject_code} - ${s.subject_name} (${s.credits} credits)</option>`;
                });
                subjectSelect.disabled = false;
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects found</option>';
                subjectSelect.disabled = true;
            }
            checkSubmit();
        })
        .catch(() => {
            document.getElementById('subjectLoading').style.display = 'none';
            subjectSelect.innerHTML = '<option value="">Error loading</option>';
        });
}

// Load all teachers
function loadAllTeachers() {
    teacherSelect.disabled = true;
    teacherSelect.innerHTML = '<option value="">Loading...</option>';
    document.getElementById('teacherLoading').style.display = 'block';
    
    fetch('../ajax/get_teachers.php')
        .then(res => res.json())
        .then(data => {
            document.getElementById('teacherLoading').style.display = 'none';
            if (data.success && data.teachers.length > 0) {
                teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
                data.teachers.forEach(t => {
                    teacherSelect.innerHTML += `<option value="${t.id}">${t.name} (${t.email})</option>`;
                });
                teacherSelect.disabled = false;
            } else {
                teacherSelect.innerHTML = '<option value="">No teachers found</option>';
                teacherSelect.disabled = true;
            }
            checkSubmit();
        })
        .catch(() => {
            document.getElementById('teacherLoading').style.display = 'none';
            teacherSelect.innerHTML = '<option value="">Error loading</option>';
        });
}

// Remove assignment
function removeAssign(id) {
    Swal.fire({
        title: 'Remove assignment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef476f',
        confirmButtonText: 'Yes, remove'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = window.location.pathname + '?delete=' + id;
        }
    });
}

// Academic year format
document.getElementById('academicYear').addEventListener('input', function() {
    let v = this.value.replace(/[^0-9-]/g, '');
    if (v.length === 4 && !v.includes('-')) v = v + '-';
    if (v.length > 9) v = v.slice(0, 9);
    this.value = v;
});

// Auto-hide alert
setTimeout(() => {
    const alert = document.querySelector('.alert');
    if (alert) alert.style.display = 'none';
}, 3000);
</script>

</body>
</html>