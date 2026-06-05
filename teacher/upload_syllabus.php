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
$teacher_id = $teacher_data['id'] ?? 0;

// Get teacher's subjects
$teacher_subjects = [];
$subject_query = mysqli_query($data, "
    SELECT s.* FROM subjects s 
    JOIN teacher_subjects ts ON s.id = ts.subject_id 
    WHERE ts.teacher_id = '$teacher_id' 
    ORDER BY s.semester ASC, s.subject_name ASC
");
if ($subject_query) {
    while ($row = mysqli_fetch_assoc($subject_query)) {
        $teacher_subjects[] = $row;
    }
}

/* HANDLE UPLOAD SYLLABUS */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_syllabus'])) {
    $subject_id = mysqli_real_escape_string($data, $_POST['subject_id']);
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $description = mysqli_real_escape_string($data, trim($_POST['description']));
    $semester = intval($_POST['semester']);
    
    // Get subject branch
    $subject_branch_query = mysqli_query($data, "SELECT branch FROM subjects WHERE id = $subject_id");
    $subject_branch = mysqli_fetch_assoc($subject_branch_query);
    $branch = $subject_branch['branch'];
    
    // Create directory if not exists
    $target_dir = "../uploads/syllabus/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Get file extension and determine file type
    $file_extension = strtolower(pathinfo($_FILES["syllabus_file"]["name"], PATHINFO_EXTENSION));
    
    // Determine file type based on extension
    $file_type = "PDF";
    if (in_array($file_extension, ['pdf'])) $file_type = "PDF";
    elseif (in_array($file_extension, ['ppt', 'pptx'])) $file_type = "PPT";
    elseif (in_array($file_extension, ['doc', 'docx'])) $file_type = "DOC";
    elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) $file_type = "Image";
    elseif (in_array($file_extension, ['zip', 'rar'])) $file_type = "Archive";
    
    // Generate unique filename
    $new_filename = time() . '_' . $teacher_id . '_' . uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    $allowed_types = array("pdf", "doc", "docx", "ppt", "pptx", "jpg", "jpeg", "png", "zip", "rar");
    
    if (empty($subject_id) || empty($title)) {
        $message = "Please select a subject and enter a title!";
        $message_type = "error";
    } elseif (!in_array($file_extension, $allowed_types)) {
        $message = "File type not allowed. Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG, ZIP, RAR";
        $message_type = "error";
    } elseif ($_FILES["syllabus_file"]["size"] > 10485760) { // 10MB limit
        $message = "File size must be less than 10MB.";
        $message_type = "error";
    } else {
        if (move_uploaded_file($_FILES["syllabus_file"]["tmp_name"], $target_file)) {
            $file_size = filesize($target_file);
            if ($file_size < 1048576) {
                $file_size_display = round($file_size / 1024, 2) . " KB";
            } else {
                $file_size_display = round($file_size / (1024 * 1024), 2) . " MB";
            }
            $file_path = "uploads/syllabus/" . $new_filename;
            
            $insert_query = "INSERT INTO syllabus_files (subject_id, teacher_id, title, description, file_path, file_type, file_size, semester, branch, upload_date, downloads, created_at) 
                            VALUES ('$subject_id', '$teacher_id', '$title', '$description', '$file_path', '$file_type', '$file_size_display', '$semester', '$branch', CURDATE(), 0, NOW())";
            
            if (mysqli_query($data, $insert_query)) {
                $message = "success";
                $message_type = "success";
            } else {
                $message = "Database error: " . mysqli_error($data);
                $message_type = "error";
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
        } else {
            $message = "Error uploading file. Please check folder permissions.";
            $message_type = "error";
        }
    }
    
    // If AJAX request, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_type == "success" && $message == "success") {
            echo json_encode(['status' => 'success', 'message' => 'Syllabus uploaded successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
        exit();
    }
    
    header("Location: syllabus.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Syllabus | Teacher - StudyBuddyHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f8f9fa; }
        .modal-container { max-width: 100%; padding: 0; }
        .form-card { background: white; border-radius: 0; padding: 1.5rem; max-width: 100%; margin: 0; border: none; }
        @media (min-width: 768px) { .form-card { padding: 2rem; } }
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
        .form-title i { color: #4361ee; }
        .form-label {
            font-weight: 500;
            color: #1e1e2f;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select, textarea {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.7rem 0.8rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        .form-control:focus, .form-select:focus, textarea:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }
        textarea { resize: vertical; min-height: 80px; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .form-grid { grid-template-columns: repeat(2, 1fr); } .full-width { grid-column: span 2; } }
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
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); color: white; }
        .btn-light {
            background: white;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .btn-light:hover { background: #f8f9fa; border-color: #4361ee; }
        .file-upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        .file-upload-area:hover { border-color: #4361ee; background: rgba(67,97,238,0.02); }
        .file-upload-area i { font-size: 2rem; color: #4361ee; margin-bottom: 0.5rem; }
        .file-upload-area p { font-size: 0.85rem; margin-bottom: 0.3rem; color: #1e1e2f; }
        .file-upload-area small { font-size: 0.7rem; color: #6c757d; }
        .file-info { margin-top: 0.5rem; font-size: 0.8rem; }
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
        .info-icon i { color: #4361ee; }
        .info-content h6 { font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #1e1e2f; }
        .info-content p { color: #666; font-size: 0.8rem; margin: 0; }
        .d-flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 1rem; }
        .mt-4 { margin-top: 1.5rem; }
        .align-items-center { align-items: center; }
        .justify-content-end { justify-content: flex-end; }
    </style>
</head>
<body>

<div class="modal-container">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-cloud-upload-alt"></i>
            Upload Syllabus
        </div>

        <form method="post" id="uploadSyllabusForm" enctype="multipart/form-data" action="">
            <div class="form-grid">
                <div class="full-width">
                    <label class="form-label"><i class="fas fa-book"></i> Select Subject <span class="required">*</span></label>
                    <select name="subject_id" class="form-select" required id="subject_id">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" data-semester="<?php echo $subject['semester']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (Sem ' . $subject['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teacher_subjects)): ?>
                        <div class="text-muted mt-1 text-danger">No subjects assigned. Please contact administrator.</div>
                    <?php endif; ?>
                </div>

                <div class="full-width">
                    <label class="form-label"><i class="fas fa-heading"></i> Syllabus Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Complete Syllabus 2025-26" required id="title">
                </div>

                <div class="full-width">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the syllabus (optional)" id="description"></textarea>
                </div>

                <div>
                    <label class="form-label"><i class="fas fa-layer-group"></i> Semester <span class="required">*</span></label>
                    <input type="number" name="semester" class="form-control" id="semester" min="1" max="8" required readonly placeholder="Auto-detected from subject">
                </div>

                <div>
                    <label class="form-label"><i class="fas fa-calendar"></i> Academic Year</label>
                    <input type="text" class="form-control" value="2025-2026" readonly>
                </div>

                <div class="full-width">
                    <label class="form-label"><i class="fas fa-file"></i> Upload Syllabus File <span class="required">*</span></label>
                    <div class="file-upload-area" onclick="document.getElementById('syllabus_file').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to browse or drag and drop</p>
                        <small>Supported: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG, ZIP (Max 10MB)</small>
                    </div>
                    <input type="file" class="d-none" name="syllabus_file" id="syllabus_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar" required onchange="updateFileName(this)">
                    <div class="file-info" id="file-info"></div>
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" id="closeModalBtn"><i class="fas fa-times me-2"></i>Cancel</button>
                <button type="submit" class="btn-submit" id="submitBtn" <?php echo empty($teacher_subjects) ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> <span>Upload Syllabus</span>
                </button>
            </div>
        </form>

        <div class="info-card">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon"><i class="fas fa-info-circle"></i></div>
                <div class="info-content">
                    <h6>Important Notes</h6>
                    <p><i class="fas fa-check-circle text-success me-2"></i>Maximum file size: 10MB<br>
                    <i class="fas fa-check-circle text-success me-2"></i>Supported formats: PDF, DOC, PPT, Images, ZIP<br>
                    <i class="fas fa-check-circle text-success me-2"></i>Syllabus will be visible to students instantly<br>
                    <i class="fas fa-check-circle text-success me-2"></i>You can track download counts for each syllabus</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    // Auto-detect semester when subject is selected
    document.getElementById('subject_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const semester = selectedOption.getAttribute('data-semester');
        const semesterField = document.getElementById('semester');
        if (semester) {
            semesterField.value = semester;
        } else {
            semesterField.value = '';
        }
    });

    function updateFileName(input) {
        const fileInfo = document.getElementById('file-info');
        if (input.files.length > 0) {
            const file = input.files[0];
            const fileSizeKB = (file.size / 1024).toFixed(2);
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const sizeText = fileSizeMB >= 1 ? fileSizeMB + " MB" : fileSizeKB + " KB";
            const fileName = file.name;
            let icon = '<i class="fas fa-file"></i>';
            const fileExt = fileName.split('.').pop().toLowerCase();
            if (fileExt === 'pdf') icon = '<i class="fas fa-file-pdf text-danger"></i>';
            else if (fileExt === 'doc' || fileExt === 'docx') icon = '<i class="fas fa-file-word text-primary"></i>';
            else if (fileExt === 'ppt' || fileExt === 'pptx') icon = '<i class="fas fa-file-powerpoint text-warning"></i>';
            else if (fileExt === 'jpg' || fileExt === 'jpeg' || fileExt === 'png' || fileExt === 'gif') icon = '<i class="fas fa-file-image text-success"></i>';
            else if (fileExt === 'zip' || fileExt === 'rar') icon = '<i class="fas fa-file-archive text-secondary"></i>';
            fileInfo.innerHTML = `${icon} <strong>${fileName}</strong> (${sizeText})`;
            fileInfo.style.color = '#06d6a0';
        } else {
            fileInfo.innerHTML = '';
        }
    }

    function validateForm() {
        const subjectId = document.getElementById('subject_id').value;
        const title = document.getElementById('title').value.trim();
        const semester = document.getElementById('semester').value;
        const fileInput = document.getElementById('syllabus_file');
        
        if (!subjectId) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please select a subject!', confirmButtonColor: '#4361ee' });
            return false;
        }
        if (!title) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter a syllabus title!', confirmButtonColor: '#4361ee' });
            return false;
        }
        if (title.length < 3) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Title must be at least 3 characters!', confirmButtonColor: '#4361ee' });
            return false;
        }
        if (!semester) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please select a valid subject to auto-detect semester!', confirmButtonColor: '#4361ee' });
            return false;
        }
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please select a file to upload!', confirmButtonColor: '#4361ee' });
            return false;
        }
        const file = fileInput.files[0];
        if (file.size > 10485760) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'File size must be less than 10MB!', confirmButtonColor: '#4361ee' });
            return false;
        }
        return true;
    }

    function closeModal() {
        const modalElement = document.querySelector('#quickAddModal');
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
        }
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
        if (window.parent && window.parent.location) {
            window.parent.location.reload();
        }
    }

    document.getElementById('uploadSyllabusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!validateForm()) return;
        
        Swal.fire({
            title: 'Uploading Syllabus...',
            text: 'Please wait while we upload your file.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        const formData = new FormData(this);
        formData.append('upload_syllabus', '1');
        
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
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        closeModal();
                        if (window.parent && window.parent.location) {
                            window.parent.location.reload();
                        } else {
                            window.location.href = 'syllabus.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
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
    });
    
    document.getElementById('closeModalBtn').addEventListener('click', function() {
        closeModal();
    });
</script>

</body>
</html>