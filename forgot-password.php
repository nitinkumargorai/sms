<?php
session_start();

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
$reset_sent = false;
$email = "";

/* HANDLE FORGOT PASSWORD REQUEST */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reset_link'])) {
    $email = mysqli_real_escape_string($data, trim($_POST['email']));
    
    if (empty($email)) {
        $message = "Please enter your email address!";
        $message_type = "error";
    } else {
        // Check if email exists in user table
        $check_user = mysqli_query($data, "SELECT id, username, usertype FROM user WHERE email = '$email'");
        
        if (mysqli_num_rows($check_user) > 0) {
            $user_data = mysqli_fetch_assoc($check_user);
            $user_id = $user_data['id'];
            $username = $user_data['username'];
            $usertype = $user_data['usertype'];
            
            // Generate unique reset token
            $reset_token = bin2hex(random_bytes(32));
            $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing reset requests for this user
            mysqli_query($data, "DELETE FROM password_resets WHERE user_id = $user_id");
            
            // Insert new reset request
            $insert_reset = mysqli_query($data, "INSERT INTO password_resets (user_id, email, token, expiry_time, created_at) 
                                                  VALUES ($user_id, '$email', '$reset_token', '$expiry_time', NOW())");
            
            if ($insert_reset) {
                // Build reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token . "&email=" . urlencode($email);
                
                // In a real production environment, you would send an email here
                // For demo purposes, we'll display the reset link
                $reset_sent = true;
                $message = "Password reset link has been generated! Please copy the link below to reset your password.";
                $message_type = "success";
                
                // Store reset link in session for demo display
                $_SESSION['demo_reset_link'] = $reset_link;
                $_SESSION['reset_email'] = $email;
            } else {
                $message = "Error generating reset link. Please try again.";
                $message_type = "error";
            }
        } else {
            $message = "No account found with this email address!";
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Forgot Password | StudyBuddyHub - Reset Your Password</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --primary-bg: #eef2ff;
            --secondary: #64748b;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
            --gray-light: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --pink: #ec4899;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 50%, #ffffff 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 80%;
            height: 150%;
            background: radial-gradient(circle, rgba(79,70,229,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 60%;
            height: 100%;
            background: radial-gradient(circle, rgba(139,92,246,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Custom Cursor */
        .cursor-dot {
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            position: fixed;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease;
        }
        .cursor-outline {
            width: 30px;
            height: 30px;
            border: 2px solid var(--primary-light);
            border-radius: 50%;
            position: fixed;
            pointer-events: none;
            z-index: 9998;
            transition: all 0.15s ease;
            opacity: 0.5;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        .navbar.scrolled {
            padding: 0.75rem 2rem;
            box-shadow: var(--shadow-md);
            background: rgba(255, 255, 255, 0.98);
        }

        .navbar-brand {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--purple), var(--pink));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .nav-link {
            color: var(--dark) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: var(--transition);
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            transition: var(--transition);
            border-radius: 2px;
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        .btn-login-nav {
            background: transparent;
            color: var(--primary) !important;
            border: 2px solid var(--primary);
            padding: 0.5rem 1.25rem !important;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-login-nav:hover {
            background: var(--primary);
            color: white !important;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-register-nav {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white !important;
            border: none;
            padding: 0.5rem 1.25rem !important;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-register-nav:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Forgot Password Container */
        .forgot-container {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }

        /* Forgot Password Card */
        .forgot-card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            max-width: 550px;
            width: 100%;
        }

        .forgot-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        /* Card Header */
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--purple), var(--pink));
            padding: 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .card-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 10;
        }

        .card-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 10;
        }

        .card-header p {
            opacity: 0.95;
            margin-bottom: 0;
            position: relative;
            z-index: 10;
            font-size: 0.9rem;
        }

        /* Card Body */
        .card-body {
            padding: 2rem;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--dark);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary);
            font-size: 0.85rem;
        }

        .input-group {
            border: 2px solid var(--gray-light);
            border-radius: 16px;
            transition: var(--transition);
            background: white;
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: var(--gray);
            padding-left: 1.2rem;
        }

        .form-control {
            border: none;
            padding: 0.85rem 1rem 0.85rem 0;
            font-size: 1rem;
            background: transparent;
            transition: var(--transition);
        }

        .form-control:focus {
            box-shadow: none;
            outline: none;
        }

        /* Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-submit:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Back to Login Link */
        .back-link {
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.8rem;
            border-top: 2px solid var(--gray-light);
        }

        .back-link p {
            color: var(--secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .back-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert-custom {
            border-radius: 16px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
            border-left: 4px solid #059669;
        }

        /* Reset Link Card */
        .reset-link-card {
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1.5rem;
            border: 1px solid var(--gray-light);
        }

        .reset-link-card .link-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reset-link-card .reset-link {
            background: white;
            padding: 0.75rem;
            border-radius: 12px;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.8rem;
            border: 1px solid var(--primary-light);
        }

        .reset-link-card .reset-link a {
            color: var(--primary);
            text-decoration: none;
        }

        .reset-link-card .reset-link a:hover {
            text-decoration: underline;
        }

        .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            margin-top: 0.5rem;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Back to Top */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 999;
            box-shadow: var(--shadow);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: translateY(-3px);
            color: white;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .forgot-card {
            animation: fadeInUp 0.8s ease-out;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .navbar {
                padding: 1rem;
            }
            .navbar-nav {
                text-align: center;
                padding: 1rem 0;
            }
            .nav-link::after {
                display: none;
            }
            .cursor-dot, .cursor-outline {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            .card-header {
                padding: 1.5rem;
            }
            .card-header h2 {
                font-size: 1.5rem;
            }
            .card-header i {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 576px) {
            .forgot-container {
                padding: 1rem;
            }
            .card-body {
                padding: 1.25rem;
            }
            .reset-link-card .reset-link {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

<!-- Custom Cursor -->
<div class="cursor-dot"></div>
<div class="cursor-outline"></div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-graduation-cap me-2"></i>StudyBuddyHub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#team">Team</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#contact">Contact</a>
                </li>
                <li class="nav-item ms-lg-3">
                    <div class="d-flex gap-2">
                        <a href="login.php" class="btn-login-nav">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="signup.php" class="btn-register-nav">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- FORGOT PASSWORD FORM -->
<div class="forgot-container">
    <div class="forgot-card" data-aos="fade-up" data-aos-duration="800">
        <div class="card-header">
            <i class="fas fa-key"></i>
            <h2>Forgot Password? 🔐</h2>
            <p>Don't worry, we'll help you reset it</p>
        </div>
        
        <div class="card-body">
            <?php if ($message != "" && !$reset_sent): ?>
                <div class="alert-custom alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($reset_sent && isset($_SESSION['demo_reset_link'])): ?>
                <div class="alert-custom alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <div class="reset-link-card">
                    <div class="link-label">
                        <i class="fas fa-link"></i> Your Password Reset Link (Demo Mode)
                    </div>
                    <div class="reset-link" id="resetLink">
                        <a href="<?php echo $_SESSION['demo_reset_link']; ?>" target="_blank">
                            <?php echo $_SESSION['demo_reset_link']; ?>
                        </a>
                    </div>
                    <button class="copy-btn" onclick="copyResetLink()">
                        <i class="fas fa-copy"></i> Copy Link
                    </button>
                    <div class="mt-3 text-muted small">
                        <i class="fas fa-info-circle"></i> 
                        In a production environment, this link would be sent to your email. 
                        For demo purposes, you can click the link above to reset your password.
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$reset_sent): ?>
            <form method="post" action="" id="forgotForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" 
                               name="email" 
                               class="form-control"
                               placeholder="Enter your registered email"
                               value="<?php echo htmlspecialchars($email); ?>"
                               required>
                    </div>
                    <small class="text-muted">We'll send a password reset link to this email</small>
                </div>
                
                <button type="submit" name="send_reset_link" class="btn-submit" id="submitBtn">
                    <span id="btnText">Send Reset Link</span>
                    <i class="fas fa-paper-plane" id="planeIcon"></i>
                    <span class="spinner" id="spinner" style="display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite;"></span>
                </button>
                
                <div class="back-link">
                    <p><i class="fas fa-arrow-left"></i> <a href="login.php">Back to Login</a></p>
                </div>
            </form>
            <?php else: ?>
            <div class="back-link">
                <p><i class="fas fa-arrow-left"></i> <a href="login.php">Back to Login</a></p>
                <p class="mt-2"><a href="forgot-password.php" class="text-primary">Try another email</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- BACK TO TOP BUTTON -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<script>
    AOS.init({
        duration: 800,
        once: true,
        offset: 50
    });

    // Custom Cursor
    const cursorDot = document.querySelector('.cursor-dot');
    const cursorOutline = document.querySelector('.cursor-outline');
    
    if (cursorDot && cursorOutline) {
        window.addEventListener('mousemove', (e) => {
            cursorDot.style.transform = `translate(${e.clientX - 3}px, ${e.clientY - 3}px)`;
            cursorOutline.style.transform = `translate(${e.clientX - 15}px, ${e.clientY - 15}px)`;
        });
        
        document.querySelectorAll('a, button, .forgot-card, .btn-submit, .btn-login-nav, .btn-register-nav, .copy-btn').forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursorOutline.style.transform = `scale(1.5)`;
                cursorOutline.style.borderColor = 'var(--primary)';
            });
            el.addEventListener('mouseleave', () => {
                cursorOutline.style.transform = `scale(1)`;
                cursorOutline.style.borderColor = 'var(--primary-light)';
            });
        });
    }

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        const backToTop = document.getElementById('backToTop');
        
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
            backToTop.classList.add('show');
        } else {
            navbar.classList.remove('scrolled');
            backToTop.classList.remove('show');
        }
    });

    // Copy Reset Link Function
    function copyResetLink() {
        const resetLink = document.getElementById('resetLink');
        const linkText = resetLink.innerText;
        
        navigator.clipboard.writeText(linkText).then(() => {
            Swal.fire({
                title: 'Copied!',
                text: 'Reset link copied to clipboard',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }).catch(() => {
            Swal.fire({
                title: 'Error',
                text: 'Failed to copy link',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
        });
    }

    // Loading State on Form Submit
    const forgotForm = document.getElementById('forgotForm');
    if (forgotForm) {
        forgotForm.addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const planeIcon = document.getElementById('planeIcon');
            const spinner = document.getElementById('spinner');
            
            btnText.style.opacity = '0.7';
            if (planeIcon) planeIcon.style.opacity = '0';
            spinner.style.display = 'inline-block';
            btn.style.pointerEvents = 'none';
        });
    }

    // Auto-hide Alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.remove) alert.remove();
                }, 500);
            }
        });
    }, 5000);

    // Smooth scroll for back to top
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        backToTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
</script>

</body>
</html>