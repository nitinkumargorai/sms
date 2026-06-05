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

$message = "";
$message_type = "";

/* HANDLE CREATE ASSIGNMENT */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_assignment'])) {
    $subject_id = mysqli_real_escape_string($data, $_POST['subject_id']);
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $description = mysqli_real_escape_string($data, trim($_POST['description']));
    $due_date = mysqli_real_escape_string($data, $_POST['due_date']);
    $due_time = mysqli_real_escape_string($data, $_POST['due_time']);
    $total_marks = intval($_POST['total_marks']);
    
    $errors = [];
    
    if (empty($subject_id)) $errors[] = "Please select a subject!";
    if (empty($title)) $errors[] = "Please enter an assignment title!";
    if (empty($due_date)) $errors[] = "Please select a due date!";
    if ($total_marks < 1 || $total_marks > 100) $errors[] = "Total marks must be between 1 and 100!";
    
    // Handle file attachment
    $attachment_path = "";
    if (!empty($_FILES["attachment"]["name"])) {
        $target_dir = "../uploads/assignments/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
        $new_filename = time() . "_" . $teacher_id . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($_FILES["attachment"]["name"], PATHINFO_FILENAME)) . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ["pdf", "doc", "docx", "txt", "jpg", "jpeg", "png", "zip", "rar"];
        
        if (in_array($file_extension, $allowed_types)) {
            if ($_FILES["attachment"]["size"] <= 10485760) {
                if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                    $attachment_path = "uploads/assignments/" . $new_filename;
                } else {
                    $errors[] = "Failed to upload file!";
                }
            } else {
                $errors[] = "File size must be less than 10MB!";
            }
        } else {
            $errors[] = "Only PDF, DOC, DOCX, TXT, JPG, PNG, ZIP files are allowed!";
        }
    }
    
    if (empty($errors)) {
        $due_time_sql = !empty($due_time) ? "'$due_time'" : "NULL";
        $file_path_sql = !empty($attachment_path) ? "'$attachment_path'" : "NULL";
        
        $sql = "INSERT INTO assignments (subject_id, teacher_id, title, description, due_date, due_time, total_marks, file_path) 
                VALUES ('$subject_id', '$teacher_id', '$title', '$description', '$due_date', $due_time_sql, '$total_marks', $file_path_sql)";
        
        if (mysqli_query($data, $sql)) {
            $message = "success";
            $message_type = "success";
        } else {
            $message = "Database error: " . mysqli_error($data);
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_type == "success" && $message == "success") {
            echo json_encode(['status' => 'success', 'message' => 'Assignment created successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
        exit();
    }
    
    header("Location: assignments.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .form-card { background: white; padding: 1.5rem; max-width: 100%; margin: 0; }
        @media (min-width: 768px) { .form-card { padding: 2rem; } }
        .form-title { font-size: 1.1rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; }
        @media (min-width: 768px) { .form-title { font-size: 1.3rem; } }
        .form-title i { color: #4361ee; }
        .form-label { font-weight: 500; color: #1e1e2f; margin-bottom: 0.3rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select, textarea { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.7rem 0.8rem; font-size: 0.9rem; width: 100%; }
        .form-control:focus, .form-select:focus, textarea:focus { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.1); outline: none; }
        textarea { resize: vertical; min-height: 80px; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .form-grid { grid-template-columns: repeat(2, 1fr); } .full-width { grid-column: span 2; } }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; width: 100%; margin-bottom: 0.5rem; }
        @media (min-width: 576px) { .btn-submit { width: auto; margin-bottom: 0; } }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); color: white; }
        .btn-light { background: white; border-radius: 10px; padding: 0.75rem 1.5rem; font-size: 0.9rem; font-weight: 500; border: 2px solid #e9ecef; width: 100%; }
        @media (min-width: 576px) { .btn-light { width: auto; } }
        .btn-light:hover { background: #f8f9fa; border-color: #4361ee; }
        .file-upload-area { border: 2px dashed #e9ecef; border-radius: 12px; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #fafbfc; }
        .file-upload-area:hover { border-color: #4361ee; background: rgba(67,97,238,0.02); }
        .file-upload-area i { font-size: 1.5rem; color: #4361ee; margin-bottom: 0.3rem; }
        .file-upload-area p { font-size: 0.8rem; margin-bottom: 0.2rem; color: #1e1e2f; }
        .file-upload-area small { font-size: 0.65rem; color: #6c757d; }
        .file-info { margin-top: 0.5rem; font-size: 0.75rem; }
        .info-card { background: #f8f9fa; border-radius: 15px; padding: 1rem; margin-top: 1rem; }
        .info-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(67,97,238,0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .info-icon i { color: #4361ee; }
        .info-content h6 { font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #1e1e2f; }
        .info-content p { color: #666; font-size: 0.8rem; margin: 0; }
        .d-flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 0.5rem; }
        .mt-4 { margin-top: 1.5rem; }
        .align-items-center { align-items: center; }
        .justify-content-end { justify-content: flex-end; }
        .text-muted { color: #6c757d; font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="form-card">
    <div class="form-title">
        <i class="fas fa-plus-circle"></i>
        Create New Assignment
    </div>

    <form method="post" id="createAssignmentForm" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="full-width">
                <label class="form-label"><i class="fas fa-book"></i> Select Subject <span class="required">*</span></label>
                <select name="subject_id" class="form-select" required id="subject_id">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($teacher_subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (Sem ' . $subject['semester'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full-width">
                <label class="form-label"><i class="fas fa-heading"></i> Assignment Title <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Assignment 1: SQL Queries" required id="title">
            </div>

            <div class="full-width">
                <label class="form-label"><i class="fas fa-align-left"></i> Description <span class="required">*</span></label>
                <textarea name="description" class="form-control" rows="3" placeholder="Provide instructions for students..." required id="description"></textarea>
            </div>

            <div>
                <label class="form-label"><i class="fas fa-calendar-alt"></i> Due Date <span class="required">*</span></label>
                <input type="date" name="due_date" class="form-control" required id="due_date" min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div>
                <label class="form-label"><i class="fas fa-clock"></i> Due Time</label>
                <input type="time" name="due_time" class="form-control" value="23:59" id="due_time">
                <div class="text-muted mt-1">Default: 11:59 PM</div>
            </div>

            <div>
                <label class="form-label"><i class="fas fa-star"></i> Total Marks <span class="required">*</span></label>
                <input type="number" name="total_marks" class="form-control" value="20" min="1" max="100" required id="total_marks">
            </div>

            <div>
                <label class="form-label"><i class="fas fa-paperclip"></i> Attachment (Optional)</label>
                <div class="file-upload-area" onclick="document.getElementById('attachment_file').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to browse or drag and drop</p>
                    <small>PDF, DOC, DOCX, TXT, JPG, PNG, ZIP (Max 10MB)</small>
                </div>
                <input type="file" class="d-none" name="attachment" id="attachment_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" onchange="updateFileName(this)">
                <div class="file-info" id="file-info"></div>
            </div>
        </div>

        <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn-light" id="closeModalBtn"><i class="fas fa-times me-2"></i>Cancel</button>
            <button type="submit" class="btn-submit" id="submitBtn" <?php echo empty($teacher_subjects) ? 'disabled' : ''; ?>>
                <i class="fas fa-save"></i> <span>Create Assignment</span>
            </button>
        </div>
    </form>

    <div class="info-card">
        <div class="d-flex align-items-center gap-3">
            <div class="info-icon"><i class="fas fa-info-circle"></i></div>
            <div class="info-content">
                <h6>Important Notes</h6>
                <p><i class="fas fa-check-circle text-success me-2"></i>Assignment will be visible to students immediately<br>
                <i class="fas fa-check-circle text-success me-2"></i>Students can submit until the due date/time<br>
                <i class="fas fa-check-circle text-success me-2"></i>Late submissions will be marked as "Late"<br>
                <i class="fas fa-check-circle text-success me-2"></i>You can attach reference materials (optional)</p>
            </div>
        </div>
    </div>
</div>

<script>
    function updateFileName(input) {
        const fileInfo = document.getElementById('file-info');
        if (input.files.length > 0) {
            const file = input.files[0];
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            fileInfo.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Selected: ${file.name} (${sizeMB} MB)`;
        } else {
            fileInfo.innerHTML = '';
        }
    }

    const dueDateInput = document.getElementById('due_date');
    if (dueDateInput) {
        const today = new Date().toISOString().split('T')[0];
        dueDateInput.setAttribute('min', today);
    }

    function closeModal() {
        const modal = document.querySelector('.modal.show');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        }
    }

    document.getElementById('createAssignmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const subjectId = document.getElementById('subject_id').value;
        const title = document.getElementById('title').value.trim();
        const description = document.getElementById('description').value.trim();
        const dueDate = document.getElementById('due_date').value;
        const totalMarks = document.getElementById('total_marks').value;
        
        if (!subjectId) { Swal.fire({icon:'error',title:'Validation Error',text:'Please select a subject!',confirmButtonColor:'#4361ee'}); return; }
        if (!title) { Swal.fire({icon:'error',title:'Validation Error',text:'Please enter an assignment title!',confirmButtonColor:'#4361ee'}); return; }
        if (!description) { Swal.fire({icon:'error',title:'Validation Error',text:'Please enter a description!',confirmButtonColor:'#4361ee'}); return; }
        if (!dueDate) { Swal.fire({icon:'error',title:'Validation Error',text:'Please select a due date!',confirmButtonColor:'#4361ee'}); return; }
        const marks = parseInt(totalMarks);
        if (isNaN(marks) || marks < 1 || marks > 100) { Swal.fire({icon:'error',title:'Validation Error',text:'Total marks must be between 1 and 100!',confirmButtonColor:'#4361ee'}); return; }
        
        Swal.fire({ title: 'Creating Assignment...', text: 'Please wait...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        const formData = new FormData(this);
        formData.append('create_assignment', '1');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Success!', text: response.message, confirmButtonColor: '#4361ee' }).then(() => {
                        closeModal();
                        if (window.parent && window.parent.location) window.parent.location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: response.message, confirmButtonColor: '#4361ee' });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred. Please try again.', confirmButtonColor: '#4361ee' });
            }
        });
    });
    
    document.getElementById('closeModalBtn').addEventListener('click', function() { closeModal(); });
</script>
</body>
</html>