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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name    = trim(mysqli_real_escape_string($data, $_POST['name']));
    $email   = trim(mysqli_real_escape_string($data, $_POST['email']));
    $mobile  = trim(mysqli_real_escape_string($data, $_POST['mobile']));
    $branch  = !empty($_POST['branch']) ? mysqli_real_escape_string($data, $_POST['branch']) : NULL;
    $password = mysqli_real_escape_string($data, $_POST['password']);
    $qualification = trim(mysqli_real_escape_string($data, $_POST['qualification'] ?? ''));
    $experience = intval($_POST['experience'] ?? 0);
    $address = trim(mysqli_real_escape_string($data, $_POST['address'] ?? ''));

    // Check if teacher already exists
    $check_teacher = mysqli_query($data, "SELECT id FROM teacher WHERE email = '$email'");
    $check_user = mysqli_query($data, "SELECT id FROM user WHERE email = '$email'");
    
    if (mysqli_num_rows($check_teacher) > 0) {
        $message = "Teacher already exists with this email!";
        $message_type = "error";
    } elseif (mysqli_num_rows($check_user) > 0) {
        $message = "Email already registered in the system!";
        $message_type = "error";
    } else {
        mysqli_begin_transaction($data);
        
        try {
            // Insert into teacher table (branch is optional now)
            if ($branch) {
                $sql_teacher = "INSERT INTO teacher (name, email, mobile, branch, qualification, experience, address, is_active) 
                                VALUES ('$name', '$email', '$mobile', '$branch', '$qualification', $experience, '$address', 1)";
            } else {
                $sql_teacher = "INSERT INTO teacher (name, email, mobile, qualification, experience, address, is_active) 
                                VALUES ('$name', '$email', '$mobile', '$qualification', $experience, '$address', 1)";
            }
            
            if (!mysqli_query($data, $sql_teacher)) {
                throw new Exception("Error inserting into teacher table: " . mysqli_error($data));
            }
            $teacher_id = mysqli_insert_id($data);
            
            // Insert into user table for login
            $sql_user = "INSERT INTO user (username, email, password, usertype) 
                        VALUES ('$name', '$email', '$password', 'teacher')";
            
            if (!mysqli_query($data, $sql_user)) {
                throw new Exception("Error inserting into user table: " . mysqli_error($data));
            }
            $user_id = mysqli_insert_id($data);

            if (!mysqli_query($data, "UPDATE teacher SET user_id = $user_id WHERE id = $teacher_id")) {
                throw new Exception("Error linking teacher to user: " . mysqli_error($data));
            }
            
            mysqli_commit($data);
            $message = "success";
            $message_type = "success";
            
        } catch (Exception $e) {
            mysqli_rollback($data);
            $message = "Failed to add teacher: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch branches for dropdown (optional selection)
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

// If modal AJAX request, return JSON
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if ($message_type == "success" && $message == "success") {
        echo json_encode(['status' => 'success', 'message' => 'Teacher added successfully!']);
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
    <title>Add Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; padding: 0; margin: 0; }
        .modal-container { max-width: 100%; padding: 0; }
        .form-card { background: white; border-radius: 0; padding: 1.5rem; box-shadow: none; max-width: 100%; margin: 0; border: none; }
        @media (min-width: 768px) { .form-card { padding: 2rem; border-radius: 0; } }
        .form-title { font-size: 1.1rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; }
        @media (min-width: 768px) { .form-title { font-size: 1.3rem; } }
        .form-title i { color: #4361ee; }
        .form-label { font-weight: 500; color: #1e1e2f; margin-bottom: 0.3rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select, textarea { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.7rem 0.8rem; font-size: 0.9rem; transition: all 0.3s ease; width: 100%; }
        @media (min-width: 768px) { .form-control, .form-select, textarea { padding: 0.75rem 1rem; } }
        .form-control:focus, .form-select:focus, textarea:focus { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.1); outline: none; }
        textarea { resize: vertical; min-height: 80px; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .form-grid { grid-template-columns: repeat(2, 1fr); } .full-width { grid-column: span 2; } }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; margin-bottom: 0.5rem; }
        @media (min-width: 576px) { .btn-submit { width: auto; margin-bottom: 0; } }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); color: white; }
        .btn-light { background: white; border-radius: 10px; padding: 0.75rem 1.5rem; font-size: 0.9rem; font-weight: 500; border: 2px solid #e9ecef; width: 100%; transition: all 0.3s ease; }
        @media (min-width: 576px) { .btn-light { width: auto; } }
        .btn-light:hover { background: #f8f9fa; border-color: #4361ee; }
        .alert-custom { border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: none; display: flex; align-items: center; gap: 0.75rem; animation: slideDown 0.5s ease; font-size: 0.9rem; }
        .alert-success { background: rgba(6,214,160,0.1); color: #06d6a0; border-left: 4px solid #06d6a0; }
        .alert-error { background: rgba(239,71,111,0.1); color: #ef476f; border-left: 4px solid #ef476f; }
        .info-card { background: #f8f9fa; border-radius: 15px; padding: 1rem; margin-top: 1rem; }
        .info-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(67,97,238,0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .info-icon i { color: #4361ee; }
        .info-content h6 { font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #1e1e2f; }
        .info-content p { color: #666; font-size: 0.8rem; margin: 0; }
        .password-strength { margin-top: 0.3rem; font-size: 0.75rem; }
        .strength-weak { color: #ef476f; }
        .strength-medium { color: #ffd166; }
        .strength-strong { color: #06d6a0; }
        .text-muted { color: #6c757d; font-size: 0.75rem; }
        .d-flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 1rem; }
        .mt-4 { margin-top: 1.5rem; }
        .me-2 { margin-right: 0.5rem; }
        .align-items-center { align-items: center; }
        .justify-content-end { justify-content: flex-end; }
        .optional-badge { font-size: 0.65rem; color: #6c757d; margin-left: 0.5rem; font-weight: normal; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="modal-container">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-user-tie"></i>
            Add New Teacher
        </div>

        <form method="post" id="addTeacherForm" action="">
            <div class="form-grid">
                <!-- Full Name -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" placeholder="Enter teacher's full name" id="name" required>
                </div>

                <!-- Email -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="teacher@example.com" id="email" required>
                </div>

                <!-- Password -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password <span class="required">*</span>
                    </label>
                    <input type="password" name="password" class="form-control" placeholder="Create password" id="password" required>
                    <small class="text-muted">Minimum 6 characters</small>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password <span class="required">*</span>
                    </label>
                    <input type="password" class="form-control" placeholder="Confirm password" id="confirm_password" required>
                </div>

                <!-- Mobile Number -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone"></i> Mobile Number <span class="required">*</span>
                    </label>
                    <input type="tel" name="mobile" class="form-control" placeholder="9876543210" id="mobile" maxlength="10" required>
                    <small class="text-muted">10-digit number</small>
                </div>

                <!-- Branch (Optional Now) -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-code-branch"></i> Branch (Optional)
                        <span class="optional-badge">Can teach any branch</span>
                    </label>
                    <select name="branch" class="form-select" id="branch">
                        <option value="">-- No Specific Branch (Can teach all) --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted" style="margin-top: 0.25rem;">
                        <i class="fas fa-info-circle"></i> Leave empty if teacher can teach any branch
                    </div>
                </div>

                <!-- Qualification -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-graduation-cap"></i> Qualification
                    </label>
                    <input type="text" name="qualification" class="form-control" placeholder="e.g., M.Tech, Ph.D" id="qualification">
                </div>

                <!-- Experience -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-briefcase"></i> Experience (Years)
                    </label>
                    <input type="number" name="experience" class="form-control" placeholder="0" id="experience" min="0" max="50" value="0">
                </div>

                <!-- Address -->
                <div class="full-width">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt"></i> Address
                    </label>
                    <textarea name="address" class="form-control" placeholder="Enter teacher's address" id="address" rows="2"></textarea>
                </div>
            </div>

            <!-- Buttons -->
            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" id="closeModalBtn">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-save"></i>
                    <span>Add Teacher</span>
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
                        <i class="fas fa-check-circle text-success me-2"></i>Branch is optional - teachers can teach any branch<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Login credentials created automatically<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Email must be unique across system<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Teachers can be assigned to any subject in any branch
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Password strength checker
    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength += 1;
        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
        return strength;
    }

    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
            } else if (strength <= 3) {
                strengthDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-exclamation-circle"></i> Weak password</span>';
            } else if (strength <= 5) {
                strengthDiv.innerHTML = '<span class="strength-medium"><i class="fas fa-exclamation-triangle"></i> Medium password</span>';
            } else {
                strengthDiv.innerHTML = '<span class="strength-strong"><i class="fas fa-check-circle"></i> Strong password</span>';
            }
        });
    }

    // Password match validation
    if (confirmInput) {
        confirmInput.addEventListener('keyup', function() {
            if (passwordInput.value === this.value && passwordInput.value !== '') {
                this.style.borderColor = '#06d6a0';
            } else if (this.value !== '') {
                this.style.borderColor = '#ef476f';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
    }

    // Mobile number formatting
    const mobileInput = document.getElementById('mobile');
    if (mobileInput) {
        mobileInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }

    // Form validation
    function validateForm() {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const mobile = document.getElementById('mobile').value.trim();
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        
        if (name.length < 3) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Name must be at least 3 characters!', confirmButtonColor: '#4361ee' });
            return false;
        }
        
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter a valid email address!', confirmButtonColor: '#4361ee' });
            return false;
        }
        
        if (!/^\d{10}$/.test(mobile)) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Mobile number must be exactly 10 digits!', confirmButtonColor: '#4361ee' });
            return false;
        }
        
        if (password.length < 6) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Password must be at least 6 characters!', confirmButtonColor: '#4361ee' });
            return false;
        }
        
        if (password !== confirm) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Passwords do not match!', confirmButtonColor: '#4361ee' });
            return false;
        }
        
        return true;
    }

    // Close modal function
    function closeModal() {
        const modal = document.querySelector('#pageModal');
        if (modal) {
            const bootstrapModal = bootstrap.Modal.getInstance(modal);
            if (bootstrapModal) bootstrapModal.hide();
        }
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            const bsModal = bootstrap.Modal.getInstance(openModal);
            if (bsModal) bsModal.hide();
        }
    }

    // Handle form submission with AJAX
    document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm()) return;
        
        Swal.fire({ title: 'Adding Teacher...', text: 'Please wait.', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Success!', text: response.message, confirmButtonColor: '#4361ee', confirmButtonText: 'OK' })
                        .then((result) => {
                            if (result.isConfirmed) {
                                closeModal();
                                if (window.parent && window.parent.location) window.parent.location.reload();
                            }
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
    
    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) { alert.style.transition = 'opacity 0.5s ease'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }
    }, 3000);
</script>

</body>
</html>