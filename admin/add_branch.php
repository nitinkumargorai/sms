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

    $branch_code = strtoupper(trim(mysqli_real_escape_string($data, $_POST['branch_code'])));
    $branch_name = trim(mysqli_real_escape_string($data, $_POST['branch_name']));
    $short_name = strtoupper(trim(mysqli_real_escape_string($data, $_POST['short_name'])));
    $description = trim(mysqli_real_escape_string($data, $_POST['description']));
    $established_year = intval($_POST['established_year']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($branch_code) || empty($branch_name)) {
        $message = "Branch code and name are required!";
        $message_type = "error";
    } else {
        // Check if branch code already exists
        $check_branch = mysqli_query($data, "SELECT id FROM branches WHERE branch_code = '$branch_code'");
        
        if (mysqli_num_rows($check_branch) > 0) {
            $message = "Branch code '$branch_code' already exists! Please use a different code.";
            $message_type = "error";
        } else {
            // Insert new branch
            $sql = "INSERT INTO branches (branch_code, branch_name, short_name, description, established_year, is_active, created_at) 
                    VALUES ('$branch_code', '$branch_name', '$short_name', '$description', $established_year, $is_active, NOW())";
            
            if (mysqli_query($data, $sql)) {
                $message = "Branch added successfully!";
                $message_type = "success";
                
                // Clear form data on success
                echo "<script>setTimeout(function() { window.location.href = window.location.pathname + '?success=1'; }, 1500);</script>";
            } else {
                $message = "Failed to add branch: " . mysqli_error($data);
                $message_type = "error";
            }
        }
    }
}

// Get all branches for display
$branches_query = mysqli_query($data, "SELECT * FROM branches ORDER BY branch_code");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Branch - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .container-custom { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.3rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; }
        .form-title i { color: #4361ee; }
        .form-label { font-weight: 500; color: #1e1e2f; margin-bottom: 0.3rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.9rem; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.1); outline: none; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .btn-secondary { background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .alert-custom { border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: none; display: flex; align-items: center; gap: 0.75rem; animation: slideDown 0.5s ease; }
        .alert-success { background: rgba(6,214,160,0.1); color: #06d6a0; border-left: 4px solid #06d6a0; }
        .alert-error { background: rgba(239,71,111,0.1); color: #ef476f; border-left: 4px solid #ef476f; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: 0.4s; border-radius: 50%; }
        input:checked + .slider { background-color: #06d6a0; }
        input:checked + .slider:before { transform: translateX(26px); }
        .code-hint { font-size: 0.7rem; color: #6c757d; margin-top: 0.25rem; }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-code-branch"></i>
            Add New Branch
        </div>

        <?php if (isset($message) && $message != ""): ?>
            <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <div>Branch added successfully! You can now add subjects to this branch.</div>
            </div>
        <?php endif; ?>

        <form method="post" id="addBranchForm">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-tag"></i> Branch Code <span class="required">*</span>
                </label>
                <input type="text" name="branch_code" class="form-control" placeholder="e.g., CSE, ECE, ME" required maxlength="20" id="branch_code">
                <div class="code-hint"><i class="fas fa-info-circle"></i> Unique code, will be auto-uppercased (max 20 characters)</div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-building"></i> Full Branch Name <span class="required">*</span>
                </label>
                <input type="text" name="branch_name" class="form-control" placeholder="e.g., Computer Science Engineering" required id="branch_name">
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-shortcode"></i> Short Name
                </label>
                <input type="text" name="short_name" class="form-control" placeholder="e.g., CSE" maxlength="10" id="short_name">
                <div class="code-hint"><i class="fas fa-info-circle"></i> Short display name (optional)</div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-calendar-alt"></i> Established Year
                </label>
                <select name="established_year" class="form-select" id="established_year">
                    <option value="0">Select Year</option>
                    <?php
                    $current_year = date('Y');
                    for ($year = $current_year; $year >= 1950; $year--) {
                        echo "<option value='$year'>$year</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-align-left"></i> Description
                </label>
                <textarea name="description" class="form-control" rows="3" placeholder="Enter branch description (optional)" id="description"></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-power-off"></i> Status
                </label>
                <div class="d-flex align-items-center gap-3">
                    <label class="switch">
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        <span class="slider"></span>
                    </label>
                    <span id="statusLabel">Active</span>
                </div>
                <div class="code-hint"><i class="fas fa-info-circle"></i> Inactive branches won't appear in dropdowns</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Add Branch
                </button>
                <a href="branches.php" class="btn-secondary">
                    <i class="fas fa-list"></i> View All Branches
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Auto uppercase for branch code
    const branchCodeInput = document.getElementById('branch_code');
    if (branchCodeInput) {
        branchCodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }

    // Auto uppercase for short name
    const shortNameInput = document.getElementById('short_name');
    if (shortNameInput) {
        shortNameInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Status label update
    const statusToggle = document.getElementById('is_active');
    const statusLabel = document.getElementById('statusLabel');
    
    if (statusToggle && statusLabel) {
        statusToggle.addEventListener('change', function() {
            statusLabel.innerHTML = this.checked ? 'Active' : 'Inactive';
            statusLabel.style.color = this.checked ? '#06d6a0' : '#ef476f';
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