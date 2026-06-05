<?php
session_start();

/* AUTH CHECK - Allow both admin and modal access */
if (!isset($_SESSION['username']) || ($_SESSION['usertype'] !== 'admin' && !isset($_GET['modal']))) {
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

// Fetch branches from database
$branches = [];
$branch_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
if ($branch_query && mysqli_num_rows($branch_query) > 0) {
    while ($row = mysqli_fetch_assoc($branch_query)) {
        $branches[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name     = mysqli_real_escape_string($data, $_POST['name']);
    $pass     = mysqli_real_escape_string($data, $_POST['password']);
    $email    = mysqli_real_escape_string($data, $_POST['email']);
    $reg_no   = mysqli_real_escape_string($data, $_POST['reg_no']);
    $branch   = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = mysqli_real_escape_string($data, $_POST['semester']);

    /* Check if student/login already exists */
    $check_user = mysqli_query($data, "SELECT id FROM user WHERE email='$email'");
    $check_student = mysqli_query($data, "SELECT id FROM admission WHERE Email='$email' OR registration_no='$reg_no'");
    
    if (($check_user && mysqli_num_rows($check_user) > 0) || ($check_student && mysqli_num_rows($check_student) > 0)) {
        $message = "Student already exists with this email or registration number";
        $message_type = "error";
    } else {
        mysqli_begin_transaction($data);

        $admission_sql = "INSERT INTO admission 
            (Name, Email, password, registration_no, Branch, Semester)
            VALUES 
            ('$name', '$email', '$pass', '$reg_no', '$branch', '$semester')";

        $user_sql = "INSERT INTO user 
            (username, email, password, usertype)
            VALUES 
            ('$name', '$email', '$pass', 'student')";

        $admission_ok = mysqli_query($data, $admission_sql);
        $admission_id = mysqli_insert_id($data);
        
        $user_ok = $admission_ok ? mysqli_query($data, $user_sql) : false;
        $user_id = mysqli_insert_id($data);
        
        $link_ok = $user_ok ? mysqli_query($data, "UPDATE admission SET user_id = $user_id WHERE id = $admission_id") : false;

        if ($admission_ok && $user_ok && $link_ok) {
            mysqli_commit($data);
            $message = "success";
            $message_type = "success";
        } else {
            mysqli_rollback($data);
            $message = "Failed to add student: " . mysqli_error($data);
            $message_type = "error";
        }
    }
}

// If modal AJAX request with success, return JSON
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if ($message_type == "success" && $message == "success") {
        echo json_encode(['status' => 'success', 'message' => 'Student added successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $message]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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

        /* Modal specific styles - no sidebar, no topbar */
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .full-width {
                grid-column: span 2;
            }
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

        .alert-custom i {
            font-size: 1.1rem;
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

        .info-content p i {
            font-size: 0.7rem;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.75rem;
        }

        .d-flex {
            display: flex;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .mt-4 {
            margin-top: 1.5rem;
        }

        .me-2 {
            margin-right: 0.5rem;
        }

        .align-items-center {
            align-items: center;
        }

        .gap-3 {
            gap: 1rem;
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
            <i class="fas fa-graduation-cap"></i>
            Add New Student
        </div>

        <!-- Message Display for non-AJAX -->
        <?php if ($message != "" && $message != "success"): ?>
            <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <form method="post" id="addStudentForm" action="">
            <div class="form-grid">
                <!-- Full Name -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <input type="text" 
                           name="name" 
                           class="form-control" 
                           placeholder="Enter student's full name"
                           required>
                </div>

                <!-- Password -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Create password"
                           id="password"
                           required>
                    <small class="text-muted">Min 6 characters</small>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password
                    </label>
                    <input type="password" 
                           class="form-control" 
                           placeholder="Confirm password"
                           id="confirm_password"
                           required>
                </div>

                <!-- Email -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="student@example.com"
                           required>
                </div>

                <!-- Registration Number -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i> Registration Number
                    </label>
                    <input type="text" 
                           name="reg_no" 
                           class="form-control" 
                           placeholder="e.g., REG2025001"
                           required>
                </div>

                <!-- Branch - Dynamically loaded from database -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-code-branch"></i> Branch
                    </label>
                    <select name="branch" class="form-select" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                <?php echo htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($branches)): ?>
                            <option value="" disabled>No branches available. Please add branches first.</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($branches)): ?>
                        <small class="text-muted text-danger">No branches found. Please add branches in branch management.</small>
                    <?php endif; ?>
                </div>

                <!-- Semester - Diploma course (1-6) -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i> Semester
                    </label>
                    <select name="semester" class="form-select" required>
                        <option value="">Select Semester</option>
                        <option value="1">1st Semester</option>
                        <option value="2">2nd Semester</option>
                        <option value="3">3rd Semester</option>
                        <option value="4">4th Semester</option>
                        <option value="5">5th Semester</option>
                        <option value="6">6th Semester</option>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" id="closeModalBtn">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn-submit" id="submitBtn" <?php echo empty($branches) ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i>
                    <span>Add Student</span>
                </button>
            </div>
        </form>

        <!-- Info Card -->
        <div class="info-card">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h6>Important Notes</h6>
                    <p>
                        <i class="fas fa-check-circle text-success me-2"></i>Student can login with email & password<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Saved in admission & user tables<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Email and Registration number must be unique
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Password confirmation validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    // Real-time password match indicator
    confirmPassword.addEventListener('keyup', function() {
        if (password.value === this.value && password.value !== '') {
            this.style.borderColor = '#06d6a0';
        } else if (this.value !== '') {
            this.style.borderColor = '#ef476f';
        } else {
            this.style.borderColor = '#e9ecef';
        }
    });
    
    password.addEventListener('keyup', function() {
        if (confirmPassword.value !== '') {
            if (this.value === confirmPassword.value) {
                confirmPassword.style.borderColor = '#06d6a0';
            } else {
                confirmPassword.style.borderColor = '#ef476f';
            }
        }
    });

    // Close modal function
    function closeModal() {
        // Find the modal instance and close it
        const modal = document.querySelector('#quickAddModal');
        if (modal) {
            const bootstrapModal = bootstrap.Modal.getInstance(modal);
            if (bootstrapModal) {
                bootstrapModal.hide();
            }
        }
        // Alternative: try to find and close any open modal
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            const bsModal = bootstrap.Modal.getInstance(openModal);
            if (bsModal) bsModal.hide();
        }
    }

    // Handle form submission with AJAX
    document.getElementById('addStudentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate passwords
        if (password.value !== confirmPassword.value) {
            Swal.fire({
                icon: 'error',
                title: 'Password Mismatch',
                text: 'Passwords do not match!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        if (password.value.length < 6) {
            Swal.fire({
                icon: 'error',
                title: 'Password Too Short',
                text: 'Password must be at least 6 characters long!',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Adding Student...',
            text: 'Please wait while we register the student.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit via AJAX
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: $(this).serialize(),
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
                            // Close modal and reload parent page
                            closeModal();
                            // Reload the parent dashboard
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
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#4361ee'
                });
            }
        });
    });
    
    // Close modal button
    document.getElementById('closeModalBtn').addEventListener('click', function() {
        closeModal();
    });
    
    // Auto-hide success alert after 3 seconds (for non-AJAX)
    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 3000);
</script>

</body>
</html>