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

// Fetch branches for dropdown
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $subject_code = strtoupper(trim(mysqli_real_escape_string($data, $_POST['subject_code'])));
    $subject_name = trim(mysqli_real_escape_string($data, $_POST['subject_name']));
    $short_name = strtoupper(trim(mysqli_real_escape_string($data, $_POST['short_name'])));
    $credits = intval($_POST['credits']);
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = intval($_POST['semester']);
    $description = trim(mysqli_real_escape_string($data, $_POST['description']));

    // Validation
    $errors = [];
    
    if (empty($subject_code)) {
        $errors[] = "Subject code is required!";
    }
    
    if (empty($subject_name)) {
        $errors[] = "Subject name is required!";
    }
    
    if ($credits < 1 || $credits > 6) {
        $errors[] = "Credits must be between 1 and 6!";
    }
    
    if (empty($branch)) {
        $errors[] = "Please select a branch!";
    }
    
    if ($semester < 1 || $semester > 6) {
        $errors[] = "Semester must be between 1 and 6!";
    }
    
    // Check if branch exists
    $check_branch = mysqli_query($data, "SELECT branch_code FROM branches WHERE branch_code = '$branch'");
    if (mysqli_num_rows($check_branch) == 0) {
        $errors[] = "Selected branch does not exist!";
    }
    
    // Check if subject code already exists
    $check_subject = mysqli_query($data, "SELECT id FROM subjects WHERE subject_code = '$subject_code'");
    if (mysqli_num_rows($check_subject) > 0) {
        $errors[] = "Subject code '$subject_code' already exists!";
    }

    if (empty($errors)) {
        // Insert new subject
        $sql = "INSERT INTO subjects (subject_code, subject_name, short_name, credits, branch, semester, description, created_at) 
                VALUES ('$subject_code', '$subject_name', '$short_name', $credits, '$branch', $semester, '$description', NOW())";
        
        if (mysqli_query($data, $sql)) {
            $message = "Subject added successfully!";
            $message_type = "success";
            
            // Clear form data on success
            echo "<script>setTimeout(function() { window.location.href = window.location.pathname + '?success=1'; }, 1500);</script>";
        } else {
            $message = "Failed to add subject: " . mysqli_error($data);
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .container-custom { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.3rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; }
        .form-title i { color: #4361ee; }
        .form-label { font-weight: 500; color: #1e1e2f; margin-bottom: 0.3rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.9rem; transition: all 0.3s ease; width: 100%; }
        .form-control:focus, .form-select:focus { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.1); outline: none; }
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
        .col { flex: 1; padding: 0 0.5rem; min-width: 200px; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .btn-secondary { background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .alert-custom { border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: none; display: flex; align-items: center; gap: 0.75rem; animation: slideDown 0.5s ease; }
        .alert-success { background: rgba(6,214,160,0.1); color: #06d6a0; border-left: 4px solid #06d6a0; }
        .alert-error { background: rgba(239,71,111,0.1); color: #ef476f; border-left: 4px solid #ef476f; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .code-hint { font-size: 0.7rem; color: #6c757d; margin-top: 0.25rem; }
        textarea { resize: vertical; min-height: 80px; }
        .full-width { grid-column: span 2; }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-book"></i>
            Add New Subject
        </div>

        <?php if (isset($message) && $message != "" && strpos($message, "success") === false): ?>
            <div class="alert-custom alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <div>Subject added successfully! You can now assign this subject to teachers.</div>
            </div>
        <?php endif; ?>

        <form method="post" id="addSubjectForm">
            <div class="row">
                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Subject Code <span class="required">*</span>
                    </label>
                    <input type="text" name="subject_code" class="form-control" placeholder="e.g., CS501, EC401" required id="subject_code">
                    <div class="code-hint"><i class="fas fa-info-circle"></i> Unique code, will be auto-uppercased</div>
                </div>

                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-shortcode"></i> Short Name
                    </label>
                    <input type="text" name="short_name" class="form-control" placeholder="e.g., CN, DBMS" id="short_name">
                    <div class="code-hint"><i class="fas fa-info-circle"></i> Short display name (optional)</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-heading"></i> Subject Name <span class="required">*</span>
                </label>
                <input type="text" name="subject_name" class="form-control" placeholder="e.g., Computer Networks, Database Management System" required id="subject_name">
            </div>

            <div class="row">
                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-star"></i> Credits <span class="required">*</span>
                    </label>
                    <select name="credits" class="form-select" required id="credits">
                        <option value="">Select Credits</option>
                        <option value="1">1 Credit</option>
                        <option value="2">2 Credits</option>
                        <option value="3">3 Credits</option>
                        <option value="4">4 Credits</option>
                        <option value="5">5 Credits</option>
                        <option value="6">6 Credits</option>
                    </select>
                </div>

                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-code-branch"></i> Branch <span class="required">*</span>
                    </label>
                    <select name="branch" class="form-select" required id="branch">
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($branches)): ?>
                        <div class="code-hint text-danger">
                            <i class="fas fa-exclamation-triangle"></i> No branches found. Please <a href="add_branch.php">add a branch</a> first.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col">
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i> Semester <span class="required">*</span>
                    </label>
                    <select name="semester" class="form-select" required id="semester">
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

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-align-left"></i> Description
                </label>
                <textarea name="description" class="form-control" rows="3" placeholder="Enter subject description (optional)" id="description"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Add Subject
                </button>
                <a href="subjects.php" class="btn-secondary">
                    <i class="fas fa-list"></i> View All Subjects
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Auto uppercase for subject code
    const subjectCodeInput = document.getElementById('subject_code');
    if (subjectCodeInput) {
        subjectCodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Auto uppercase for short name
    const shortNameInput = document.getElementById('short_name');
    if (shortNameInput) {
        shortNameInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Auto-hide alert after 3 seconds
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