<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch all active branches from database
$branches_query = mysqli_query($data, "SELECT branch_code, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_code");
$branches = [];
if ($branches_query) {
    while ($row = mysqli_fetch_assoc($branches_query)) {
        $branches[] = $row;
    }
}

// Fetch semesters from admission table or set default range
$semesters_query = mysqli_query($data, "SELECT DISTINCT Semester FROM admission ORDER BY Semester");
$semesters = [];
if ($semesters_query && mysqli_num_rows($semesters_query) > 0) {
    while ($row = mysqli_fetch_assoc($semesters_query)) {
        $semesters[] = $row['Semester'];
    }
} else {
    // Default semesters if no data found
    $semesters = [1, 2, 3, 4, 5, 6];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Student Signup | StudyBuddyHub - Join Our Learning Platform</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
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

        /* Signup Container */
        .signup-container {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }

        /* Signup Card */
        .signup-card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            max-width: 1000px;
            width: 100%;
        }

        .signup-card:hover {
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

        .form-control, .form-select {
            border: none;
            padding: 0.85rem 1rem 0.85rem 0;
            font-size: 1rem;
            background: transparent;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            box-shadow: none;
            outline: none;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .strength-weak { color: #dc2626; }
        .strength-medium { color: #f59e0b; }
        .strength-strong { color: #10b981; }

        /* Toggle Password */
        .toggle-password {
            background: transparent;
            border: none;
            padding-right: 1.2rem;
            color: var(--gray);
            transition: var(--transition);
        }

        .toggle-password:hover {
            color: var(--primary);
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

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.8rem;
            border-top: 2px solid var(--gray-light);
        }

        .login-link p {
            color: var(--secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Form Check */
        .form-check-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
            margin-top: 0.2rem;
        }

        .form-check-label {
            font-size: 0.85rem;
            color: var(--secondary);
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

        /* Empty State */
        .empty-branch-message {
            text-align: center;
            padding: 3rem;
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            border-radius: 20px;
        }

        .empty-branch-message i {
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .empty-branch-message h5 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-branch-message p {
            color: var(--secondary);
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

        .signup-card {
            animation: fadeInUp 0.8s ease-out;
        }

        /* Small Text */
        .text-muted small {
            font-size: 0.7rem;
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
            .signup-container {
                padding: 1rem;
            }
            .card-body {
                padding: 1.25rem;
            }
            .form-label {
                font-size: 0.85rem;
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

<!-- SIGNUP FORM -->
<div class="signup-container">
    <div class="signup-card" data-aos="fade-up" data-aos-duration="800">
        <div class="card-header">
            <i class="fas fa-user-graduate"></i>
            <h2>Create Account</h2>
            <p>Join StudyBuddyHub and start your learning journey</p>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['status'])): ?>
                <div class="alert-custom alert-<?php echo ($_SESSION['status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo ($_SESSION['status'] == 'success') ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['status']);
                        unset($_SESSION['message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($branches)): ?>
                <div class="empty-branch-message">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h5>No Branches Available</h5>
                    <p>Please contact the administrator to add branches before signing up.</p>
                </div>
            <?php else: ?>
            <form method="post" action="data_check.php" id="signupForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Create a password" required>
                                <button class="toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">Password must be at least 6 characters</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i> Registration Number
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <input type="text" name="reg_no" class="form-control" placeholder="Enter registration number" required>
                            </div>
                            <small class="text-muted">Unique registration number provided by college</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-code-branch"></i> Branch
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-code-branch"></i>
                                </span>
                                <select name="branch" class="form-select" required>
                                    <option value="">Select Your Branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo htmlspecialchars($branch['branch_code']); ?>">
                                            <?php echo htmlspecialchars($branch['branch_name']); ?> (<?php echo htmlspecialchars($branch['branch_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i> Semester
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <select name="semester" class="form-select" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester; ?>">
                                            <?php 
                                                $suffix = 'th';
                                                if ($semester == 1) $suffix = 'st';
                                                elseif ($semester == 2) $suffix = 'nd';
                                                elseif ($semester == 3) $suffix = 'rd';
                                                echo $semester . $suffix . ' Semester';
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Register Now
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<!-- BACK TO TOP BUTTON -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

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
        
        document.querySelectorAll('a, button, .signup-card, .btn-submit, .btn-login-nav, .btn-register-nav, .toggle-password').forEach(el => {
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

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthDiv = document.getElementById('passwordStrength');

    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            let strengthText = '';
            let strengthClass = '';
            let icon = '';
            
            if (password.length === 0) {
                strengthText = '';
            } else if (strength <= 2) {
                strengthText = 'Weak password';
                strengthClass = 'strength-weak';
                icon = '<i class="fas fa-exclamation-circle"></i>';
            } else if (strength <= 4) {
                strengthText = 'Medium password';
                strengthClass = 'strength-medium';
                icon = '<i class="fas fa-chart-line"></i>';
            } else {
                strengthText = 'Strong password';
                strengthClass = 'strength-strong';
                icon = '<i class="fas fa-check-circle"></i>';
            }
            
            if (strengthText) {
                strengthDiv.innerHTML = `${icon}<span>${strengthText}</span>`;
                strengthDiv.className = `password-strength ${strengthClass}`;
            } else {
                strengthDiv.innerHTML = '';
            }
        });
    }

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Form validation
    document.getElementById('signupForm')?.addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        const regNo = document.querySelector('input[name="reg_no"]').value;
        
        if (password.length < 6) {
            e.preventDefault();
            Swal.fire({
                title: 'Error',
                text: 'Password must be at least 6 characters long!',
                icon: 'error',
                confirmButtonColor: '#4f46e5'
            });
            return false;
        }
        
        return true;
    });

    // Auto-hide alerts after 5 seconds
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
    document.getElementById('backToTop').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>

</body>
</html>