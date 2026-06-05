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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_notification'])) {
    $usertype = mysqli_real_escape_string($data, $_POST['usertype']);
    $branch = mysqli_real_escape_string($data, $_POST['branch']);
    $semester = intval($_POST['semester']);
    $specific_user = isset($_POST['specific_user']) ? intval($_POST['specific_user']) : 0;
    $title = mysqli_real_escape_string($data, trim($_POST['title']));
    $message_text = mysqli_real_escape_string($data, trim($_POST['message']));
    $link = mysqli_real_escape_string($data, $_POST['link'] ?? '');
    
    $target_users = [];
    
    if ($specific_user > 0) {
        // Send to specific user
        if ($usertype == 'student') {
            $user_query = mysqli_query($data, "SELECT user_id FROM admission WHERE id = $specific_user");
            if ($user_query && $row = mysqli_fetch_assoc($user_query)) {
                if ($row['user_id']) {
                    $target_users[] = $row['user_id'];
                } else {
                    $student_query = mysqli_query($data, "SELECT Email FROM admission WHERE id = $specific_user");
                    if ($student_query && $student_row = mysqli_fetch_assoc($student_query)) {
                        $user_fallback = mysqli_query($data, "SELECT id FROM user WHERE email = '{$student_row['Email']}' AND usertype = 'student'");
                        if ($user_fallback && $uf_row = mysqli_fetch_assoc($user_fallback)) {
                            $target_users[] = $uf_row['id'];
                        }
                    }
                }
            }
        } elseif ($usertype == 'teacher') {
            $user_query = mysqli_query($data, "SELECT user_id FROM teacher WHERE id = $specific_user");
            if ($user_query && $row = mysqli_fetch_assoc($user_query)) {
                if ($row['user_id']) {
                    $target_users[] = $row['user_id'];
                } else {
                    $teacher_query = mysqli_query($data, "SELECT email FROM teacher WHERE id = $specific_user");
                    if ($teacher_query && $teacher_row = mysqli_fetch_assoc($teacher_query)) {
                        $user_fallback = mysqli_query($data, "SELECT id FROM user WHERE email = '{$teacher_row['email']}' AND usertype = 'teacher'");
                        if ($user_fallback && $uf_row = mysqli_fetch_assoc($user_fallback)) {
                            $target_users[] = $uf_row['id'];
                        }
                    }
                }
            }
        }
    } else {
        // Send to all matching criteria
        if ($usertype == 'student') {
            $student_query = "SELECT u.id FROM user u 
                              INNER JOIN admission a ON a.user_id = u.id 
                              WHERE u.usertype = 'student'";
            if (!empty($branch)) {
                $student_query .= " AND a.Branch = '$branch'";
            }
            if ($semester > 0) {
                $student_query .= " AND a.Semester = $semester";
            }
            $result = mysqli_query($data, $student_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
        } elseif ($usertype == 'teacher') {
            $teacher_query = "SELECT u.id FROM user u 
                              INNER JOIN teacher t ON t.user_id = u.id 
                              WHERE u.usertype = 'teacher'";
            if (!empty($branch)) {
                $teacher_query .= " AND t.branch = '$branch'";
            }
            $result = mysqli_query($data, $teacher_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
        } elseif ($usertype == 'all') {
            $all_query = "SELECT id FROM user WHERE usertype IN ('student', 'teacher')";
            $result = mysqli_query($data, $all_query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_users[] = $row['id'];
                }
            }
        }
    }
    
    if (empty($target_users)) {
        $message = "No users found matching the selected criteria!";
        $message_type = "error";
    } else {
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($target_users as $user_id) {
            $insert_sql = "INSERT INTO notifications (user_id, usertype, title, message, link, is_read, created_at) 
                           VALUES ($user_id, '$usertype', '$title', '$message_text', '$link', 0, NOW())";
            if (mysqli_query($data, $insert_sql)) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }
        
        $message = "Notification sent successfully to $sent_count user(s).";
        if ($failed_count > 0) {
            $message .= " ($failed_count failed)";
        }
        $message_type = "success";
        
        echo "<script>
            setTimeout(function() {
                if (window.parent && window.parent.closeQuickMenu) {
                    window.parent.closeQuickMenu();
                }
                // If embedded inside quickAddModal, try to hide it
                if (window.parent && window.parent.document.getElementById('quickAddModal')) {
                    const modalEl = window.parent.document.getElementById('quickAddModal');
                    const modal = window.parent.bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                // If embedded inside pageModal, try to hide it
                if (window.parent && window.parent.document.getElementById('pageModal')) {
                    const modalEl = window.parent.document.getElementById('pageModal');
                    const modal = window.parent.bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                // Reload parent page to reflect changes
                window.parent.location.reload();
            }, 2000);
        </script>";
    }
}

/* FETCH BRANCHES */
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

/* FETCH STUDENTS */
$students_query = mysqli_query($data, "SELECT id, Name, registration_no, Branch, Semester FROM admission ORDER BY Name");
$students = [];
while ($row = mysqli_fetch_assoc($students_query)) {
    $students[] = $row;
}

/* FETCH TEACHERS */
$teachers_query = mysqli_query($data, "SELECT id, name, branch FROM teacher ORDER BY name");
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_query)) {
    $teachers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; padding: 0; margin: 0; }
        .container-custom { max-width: 100%; padding: 0; }
        .form-card { background: white; border-radius: 0; padding: 1.5rem; box-shadow: none; max-width: 100%; margin: 0; border: none; }
        @media (min-width: 768px) {
            .form-card {
                padding: 2rem;
            }
        }
        .form-title { font-size: 1.1rem; font-weight: 600; color: #1e1e2f; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; }
        .form-title i { color: #4361ee; }
        .form-label { font-weight: 500; color: #1e1e2f; margin-bottom: 0.3rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
        .form-label i { color: #4361ee; }
        .form-label .required { color: #ef476f; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.9rem; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,0.1); outline: none; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .alert-custom { border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: none; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: rgba(6,214,160,0.1); color: #06d6a0; border-left: 4px solid #06d6a0; }
        .alert-error { background: rgba(239,71,111,0.1); color: #ef476f; border-left: 4px solid #ef476f; }
        .code-hint { font-size: 0.75rem; color: #6c757d; margin-top: 0.25rem; }
        .recipient-alert { font-size: 0.8rem; background: rgba(67,97,238,0.05); border: 1px solid #e9ecef; border-radius: 10px; padding: 0.75rem; margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-bell"></i>
            Send New Notification
        </div>

        <?php if ($message != ""): ?>
            <script>
                Swal.fire({
                    icon: '<?php echo $message_type; ?>',
                    title: '<?php echo ($message_type == "success") ? "Sent!" : "Failed"; ?>',
                    text: '<?php echo htmlspecialchars($message); ?>',
                    confirmButtonColor: '#4361ee',
                    timer: 2000,
                    showConfirmButton: false
                });
            </script>
            <div class="alert-custom <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>

        <form method="post" id="notificationForm">
            <input type="hidden" name="send_notification" value="1">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-user-tag"></i> User Type <span class="required">*</span>
                    </label>
                    <select name="usertype" class="form-select" id="usertype" required>
                        <option value="">-- Select Recipient Type --</option>
                        <option value="student">Students</option>
                        <option value="teacher">Teachers</option>
                        <option value="all">All Users</option>
                    </select>
                </div>
                
                <div class="col-md-6" id="branchField" style="display: none;">
                    <label class="form-label">
                        <i class="fas fa-code-branch"></i> Branch
                    </label>
                    <select name="branch" class="form-select" id="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo htmlspecialchars($b['branch_code']); ?>">
                                <?php echo htmlspecialchars($b['branch_code'] . ' - ' . $b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6" id="semesterField" style="display: none;">
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i> Semester
                    </label>
                    <select name="semester" class="form-select" id="semester">
                        <option value="0">All Semesters</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-6" id="specificUserField" style="display: none;">
                    <label class="form-label">
                        <i class="fas fa-user-check"></i> Specific User
                    </label>
                    <select name="specific_user" class="form-select" id="specific_user">
                        <option value="0">-- Send to All --</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-heading"></i> Title <span class="required">*</span>
                </label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Exam Schedule, Holiday Notice" required>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Message <span class="required">*</span>
                </label>
                <textarea name="message" class="form-control" rows="4" placeholder="Type notification details..." required></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-link"></i> Link (Optional)
                </label>
                <input type="text" name="link" class="form-control" placeholder="https://example.com/details">
                <div class="code-hint"><i class="fas fa-info-circle"></i> Add a URL redirect if users need to download/view external info</div>
            </div>

            <div class="recipient-alert" id="recipientSummary">
                <i class="fas fa-info-circle" style="color: #4361ee;"></i>
                <span id="recipientSummaryText">Select user type to see recipients summary.</span>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const students = <?php echo json_encode($students); ?>;
    const teachers = <?php echo json_encode($teachers); ?>;

    const usertypeSelect = document.getElementById('usertype');
    const branchSelect = document.getElementById('branch');
    const semesterSelect = document.getElementById('semester');
    const specificUserSelect = document.getElementById('specific_user');

    const branchField = document.getElementById('branchField');
    const semesterField = document.getElementById('semesterField');
    const specificUserField = document.getElementById('specificUserField');
    const recipientSummaryText = document.getElementById('recipientSummaryText');

    function updateFields() {
        const usertype = usertypeSelect.value;
        
        if (usertype === 'student') {
            branchField.style.display = 'block';
            semesterField.style.display = 'block';
            specificUserField.style.display = 'block';
            filterRecipients();
        } else if (usertype === 'teacher') {
            branchField.style.display = 'block';
            semesterField.style.display = 'none';
            specificUserField.style.display = 'block';
            filterRecipients();
        } else {
            branchField.style.display = 'none';
            semesterField.style.display = 'none';
            specificUserField.style.display = 'none';
            if (usertype === 'all') {
                recipientSummaryText.innerHTML = 'Sending to: <strong>All Students & Teachers</strong>';
            } else {
                recipientSummaryText.innerHTML = 'Select user type to see recipients summary.';
            }
        }
    }

    function filterRecipients() {
        const usertype = usertypeSelect.value;
        const selectedBranch = branchSelect.value;
        const selectedSem = parseInt(semesterSelect.value) || 0;

        specificUserSelect.innerHTML = '<option value="0">-- Send to All --</option>';

        if (usertype === 'student') {
            let filteredStudents = students.filter(s => {
                const matchBranch = !selectedBranch || s.Branch === selectedBranch;
                const matchSem = !selectedSem || parseInt(s.Semester) === selectedSem;
                return matchBranch && matchSem;
            });

            filteredStudents.forEach(s => {
                specificUserSelect.innerHTML += `<option value="${s.id}">${s.Name} (${s.registration_no || 'No Reg'}) - ${s.Branch} Sem ${s.Semester}</option>`;
            });

            let summaryText = `Sending to: <strong>All Students</strong>`;
            if (selectedBranch) summaryText += ` in branch <strong>${selectedBranch}</strong>`;
            if (selectedSem > 0) summaryText += ` (Semester <strong>${selectedSem}</strong>)`;
            recipientSummaryText.innerHTML = summaryText;

        } else if (usertype === 'teacher') {
            let filteredTeachers = teachers.filter(t => {
                return !selectedBranch || t.branch === selectedBranch;
            });

            filteredTeachers.forEach(t => {
                specificUserSelect.innerHTML += `<option value="${t.id}">${t.name} - ${t.branch || 'General'}</option>`;
            });

            let summaryText = `Sending to: <strong>All Teachers</strong>`;
            if (selectedBranch) summaryText += ` in branch <strong>${selectedBranch}</strong>`;
            recipientSummaryText.innerHTML = summaryText;
        }

        updateSpecificUserText();
    }

    function updateSpecificUserText() {
        const specificUserVal = specificUserSelect.value;
        const usertype = usertypeSelect.value;
        if (specificUserVal != 0) {
            const opt = specificUserSelect.options[specificUserSelect.selectedIndex];
            recipientSummaryText.innerHTML = `Sending to specific ${usertype}: <strong>${opt.text}</strong>`;
        }
    }

    usertypeSelect.addEventListener('change', updateFields);
    branchSelect.addEventListener('change', filterRecipients);
    semesterSelect.addEventListener('change', filterRecipients);
    specificUserSelect.addEventListener('change', updateSpecificUserText);
</script>

</body>
</html>
