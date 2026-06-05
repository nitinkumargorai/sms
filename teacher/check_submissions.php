<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$assignment_id = isset($_GET['id']) ? mysqli_real_escape_string($data, $_GET['id']) : 0;
if ($assignment_id == 0) {
    header("Location: assignments.php");
    exit();
}

$teacher_email = $_SESSION['email'] ?? '';
$teacher_query = mysqli_query($data, "SELECT id FROM teacher WHERE email='$teacher_email'");
$teacher_data = mysqli_fetch_assoc($teacher_query);
$teacher_id = $teacher_data['id'] ?? 0;

// Fetch Assignment Details
$assign_query = mysqli_query($data, "SELECT a.*, s.subject_name FROM assignments a JOIN subjects s ON a.subject_id = s.id WHERE a.id = '$assignment_id' AND a.teacher_id = '$teacher_id'");
if (!$assign_query || mysqli_num_rows($assign_query) == 0) {
    header("Location: assignments.php?msg=" . urlencode("Assignment not found or access denied.") . "&type=error");
    exit();
}
$assignment = mysqli_fetch_assoc($assign_query);

// Handle Grading Post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $sub_id = mysqli_real_escape_string($data, $_POST['submission_id']);
    $marks = mysqli_real_escape_string($data, $_POST['marks']);
    $feedback = mysqli_real_escape_string($data, $_POST['feedback']);
    
    // Ensure this submission is for an assignment owned by this teacher
    $verify_sub = mysqli_query($data, "SELECT s.id FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.id = '$sub_id' AND a.teacher_id = '$teacher_id'");
    if (mysqli_num_rows($verify_sub) > 0) {
        mysqli_query($data, "UPDATE submissions SET marks_obtained='$marks', teacher_feedback='$feedback', status='graded' WHERE id='$sub_id'");
        $success = "Submission graded successfully!";
    } else {
        $error = "Unauthorized action.";
    }
}

// Fetch Submissions
$submissions = mysqli_query($data, "SELECT s.*, a.Name as student_name, a.`Registration No.` as reg_no FROM submissions s JOIN admission a ON s.student_id = a.id WHERE s.assignment_id = '$assignment_id' ORDER BY s.submission_date DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Submissions | StudyBuddyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; padding: 2rem; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-section { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-file-invoice me-2"></i>Review Submissions</h2>
                    <p class="mb-0"><?php echo htmlspecialchars($assignment['title']); ?> | <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                </div>
                <a href="assignments.php" class="btn btn-light"><i class="fas fa-arrow-left me-2"></i>Back to Assignments</a>
            </div>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Registration</th>
                            <th>Date</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Marks (max <?php echo $assignment['total_marks']; ?>)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($sub = mysqli_fetch_assoc($submissions)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sub['student_name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($sub['reg_no']); ?></code></td>
                            <td><?php echo date('M d, H:i', strtotime($sub['submission_date'])); ?></td>
                            <td><a href="../<?php echo $sub['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>View</a></td>
                            <td>
                                <?php if($sub['status'] == 'graded'): ?>
                                    <span class="badge bg-success">Graded</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Submitted</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $sub['marks_obtained'] ?? '-'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $sub['id']; ?>">Grade</button>
                            </td>
                        </tr>

                        <!-- Grade Modal -->
                        <div class="modal fade" id="gradeModal<?php echo $sub['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Grade Submission</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Marks Obtained</label>
                                                <input type="number" name="marks" class="form-control" max="<?php echo $assignment['total_marks']; ?>" value="<?php echo $sub['marks_obtained']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Feedback</label>
                                                <textarea name="feedback" class="form-control" rows="3"><?php echo htmlspecialchars($sub['teacher_feedback']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="grade_submission" class="btn btn-primary">Save Grade</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($submissions) == 0): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4">No submissions found yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
