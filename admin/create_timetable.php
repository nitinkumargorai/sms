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

// Fetch branches
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
while ($row = mysqli_fetch_assoc($branches_query)) {
    $branches[] = $row;
}

// Fetch teachers
$teachers_query = mysqli_query($data, "SELECT id, name, email FROM teacher WHERE is_active = 1 ORDER BY name");
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_query)) {
    $teachers[] = $row;
}

// Fetch subjects
$subjects_query = mysqli_query($data, "SELECT id, subject_code, subject_name, branch, semester, credits FROM subjects ORDER BY branch, semester, subject_code");
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_query)) {
    $subjects[] = $row;
}

/* HANDLE ADD TIMETABLE SLOT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot') {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $day = mysqli_real_escape_string($data, $_POST['day'] ?? '');
    $start_time = mysqli_real_escape_string($data, $_POST['start_time'] ?? '');
    $end_time = mysqli_real_escape_string($data, $_POST['end_time'] ?? '');
    $room_no = mysqli_real_escape_string($data, $_POST['room_no'] ?? '');
    $branch = mysqli_real_escape_string($data, $_POST['branch'] ?? '');
    $semester = intval($_POST['semester'] ?? 0);
    $academic_year = mysqli_real_escape_string($data, $_POST['academic_year'] ?? date('Y') . '-' . (date('Y') + 1));

    // Check for teacher time clash
    $check_clash = mysqli_query($data, "SELECT id FROM timetable WHERE teacher_id = $teacher_id AND day = '$day' AND (
        (start_time <= '$start_time' AND end_time > '$start_time') OR 
        (start_time < '$end_time' AND end_time >= '$end_time') OR
        ('$start_time' <= start_time AND '$end_time' >= end_time)
    )");
    
    if (mysqli_num_rows($check_clash) > 0) {
        $message = "Teacher has a time clash on this day!";
        $message_type = "error";
    } else {
        $stmt = mysqli_prepare($data, "INSERT INTO timetable (subject_id, teacher_id, day, start_time, end_time, room_no, branch, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iissssiss", $subject_id, $teacher_id, $day, $start_time, $end_time, $room_no, $branch, $semester, $academic_year);
            if (mysqli_stmt_execute($stmt)) {
                $message = "success";
                $message_type = "success";
            } else {
                $message = "Error: " . mysqli_error($data);
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // If AJAX request, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($message_type == "success" && $message == "success") {
            echo json_encode(['status' => 'success', 'message' => 'Timetable slot added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
        exit();
    }
    
    header("Location: timetable.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            min-width: 200px;
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
        .me-2 {
            margin-right: 0.5rem;
        }
        .align-items-center {
            align-items: center;
        }
        .justify-content-end {
            justify-content: flex-end;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="modal-container">
    <div class="form-card">
        <div class="form-title">
            <i class="fas fa-calendar-plus"></i>
            Create Timetable Slot
        </div>

        <form method="post" id="addTimetableForm" action="">
            <input type="hidden" name="action" value="add_slot">
            <div class="row">
                <div class="col">
                    <label class="form-label"><i class="fas fa-book"></i> Subject <span class="required">*</span></label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['branch'] . ' Sem ' . $subject['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-chalkboard-teacher"></i> Teacher <span class="required">*</span></label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['name'] . ' (' . $teacher['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-calendar-day"></i> Day <span class="required">*</span></label>
                    <select name="day" class="form-select" required>
                        <option value="">-- Select Day --</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <label class="form-label"><i class="fas fa-clock"></i> Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-clock"></i> End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-door-open"></i> Room No</label>
                    <input type="text" name="room_no" class="form-control" placeholder="e.g., Room 101, Lab A">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <label class="form-label"><i class="fas fa-code-branch"></i> Branch <span class="required">*</span></label>
                    <select name="branch" class="form-select" required>
                        <option value="">-- Select Branch --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                <?php echo htmlspecialchars($branch['branch_code'] . ' - ' . $branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-layer-group"></i> Semester <span class="required">*</span></label>
                    <select name="semester" class="form-select" required>
                        <option value="">-- Select Semester --</option>
                        <option value="1">1st Semester</option>
                        <option value="2">2nd Semester</option>
                        <option value="3">3rd Semester</option>
                        <option value="4">4th Semester</option>
                        <option value="5">5th Semester</option>
                        <option value="6">6th Semester</option>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Academic Year <span class="required">*</span></label>
                    <input type="text" name="academic_year" class="form-control" value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" placeholder="2025-2026" required>
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn-light" onclick="closeModal()">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Create Slot
                </button>
            </div>
        </form>

        <div class="info-card">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h6>Important Notes</h6>
                    <p>
                        <i class="fas fa-check-circle text-success me-2"></i>Each timetable slot requires a subject, teacher, day, and time<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Teacher time clash is automatically checked<br>
                        <i class="fas fa-check-circle text-success me-2"></i>Same teacher cannot be assigned to overlapping time slots
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function closeModal() {
        const modal = document.querySelector('#quickAddModal');
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

    document.getElementById('addTimetableForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Creating Timetable Slot...',
            text: 'Please wait.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
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
                    }).then(() => {
                        closeModal();
                        if (window.parent && window.parent.location) {
                            window.parent.location.reload();
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
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#4361ee'
                });
            }
        });
    });
</script>

</body>
</html>