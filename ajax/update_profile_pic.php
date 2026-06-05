<?php
session_start();

header('Content-Type: application/json');

/* AUTH CHECK */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

/* DB CONNECTION */
$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Action: Delete profile picture
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Get current profile picture
    $query = mysqli_query($data, "SELECT profile_pic FROM user WHERE id = $user_id");
    if ($query && $row = mysqli_fetch_assoc($query)) {
        $old_pic = $row['profile_pic'];
        if (!empty($old_pic)) {
            $filepath = __DIR__ . '/../uploads/profile_pics/' . $old_pic;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
    }
    
    // Update user record
    $update = mysqli_query($data, "UPDATE user SET profile_pic = NULL WHERE id = $user_id");
    if ($update) {
        $_SESSION['profile_pic'] = null;
        echo json_encode(['success' => true, 'message' => 'Profile picture deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
    exit();
}

// Action: Upload / Update profile picture
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
        exit();
    }
    
    // Validate size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds maximum limit of 5MB.']);
        exit();
    }
    
    // Validate type
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: JPG, JPEG, PNG, GIF, WEBP.']);
        exit();
    }
    
    // Fetch old picture to delete after successful upload
    $old_pic = null;
    $query = mysqli_query($data, "SELECT profile_pic FROM user WHERE id = $user_id");
    if ($query && $row = mysqli_fetch_assoc($query)) {
        $old_pic = $row['profile_pic'];
    }
    
    // Generate new filename
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $target_dir = __DIR__ . '/../uploads/profile_pics/';
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_path = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Update database
        $update = mysqli_query($data, "UPDATE user SET profile_pic = '$new_filename' WHERE id = $user_id");
        if ($update) {
            $_SESSION['profile_pic'] = $new_filename;
            
            // Delete old picture file if it existed
            if (!empty($old_pic)) {
                $old_path = $target_dir . $old_pic;
                if (file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture updated successfully.',
                'image_url' => '../uploads/profile_pics/' . $new_filename
            ]);
        } else {
            // Rollback uploaded file if DB update fails
            @unlink($target_path);
            echo json_encode(['success' => false, 'message' => 'Failed to save profile picture to database.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit();
