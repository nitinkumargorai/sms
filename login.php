<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Login | StudyBuddyHub - Your Digital Learning Companion</title>

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

        /* Login Container */
        .login-container {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }

        /* Login Card */
        .login-card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            max-width: 500px;
            width: 100%;
            position: relative;
        }

        .login-card:hover {
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

        /* Password Toggle */
        .password-toggle {
            background: transparent;
            border: none;
            padding-right: 1.2rem;
            color: var(--gray);
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .remember-me input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .remember-me label {
            color: var(--secondary);
            font-size: 0.9rem;
            cursor: pointer;
            margin: 0;
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Login Button */
        .btn-login {
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

        .btn-login::before {
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

        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Sign Up Link */
        .signup-link {
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.8rem;
            border-top: 2px solid var(--gray-light);
        }

        .signup-link p {
            color: var(--secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        .signup-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .signup-link a:hover {
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

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

        .login-card {
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
            .cursor-dot, .cursor-outline {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }
            .card-body {
                padding: 1.25rem;
            }
            .form-options {
                flex-direction: column;
                align-items: flex-start;
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

<!-- LOGIN FORM -->
<div class="login-container">
    <div class="login-card" data-aos="fade-up" data-aos-duration="800">
        <div class="card-header">
            <i class="fas fa-graduation-cap"></i>
            <h2>Welcome Back! 👋</h2>
            <p>Login to continue your learning journey</p>
        </div>
        
        <div class="card-body">
            <?php
            if (isset($_SESSION['loginMessage'])) {
                echo '<div class="alert-custom alert-danger alert-dismissible fade show" role="alert">';
                echo '<i class="fas fa-exclamation-circle"></i>';
                echo '<span>' . $_SESSION['loginMessage'] . '</span>';
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['loginMessage']);
            }
            
            if (isset($_SESSION['successMessage'])) {
                echo '<div class="alert-custom alert-success alert-dismissible fade show" role="alert">';
                echo '<i class="fas fa-check-circle"></i>';
                echo '<span>' . $_SESSION['successMessage'] . '</span>';
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['successMessage']);
            }
            ?>
            
            <form method="post" action="login_check.php" id="loginForm">
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
                               placeholder="Enter your email"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               class="form-control"
                               placeholder="Enter your password"
                               id="password"
                               required>
                        <button class="password-toggle" type="button" onclick="togglePassword()">
                            <i class="far fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="forgot-password">
                        <i class="fas fa-question-circle"></i> Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span id="btnText">Log In</span>
                    <i class="fas fa-arrow-right" id="arrowIcon"></i>
                    <span class="spinner" id="spinner"></span>
                </button>
                
                <div class="signup-link">
                    <p>Don't have an account? <a href="signup.php">Create an account</a></p>
                </div>
            </form>
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
        
        document.querySelectorAll('a, button, .login-card, .btn-login, .btn-login-nav, .btn-register-nav, .forgot-password').forEach(el => {
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

    // Password Toggle Function
    function togglePassword() {
        const password = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (password.type === 'password') {
            password.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            password.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Loading State on Form Submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        const arrowIcon = document.getElementById('arrowIcon');
        
        btnText.style.opacity = '0.7';
        if (arrowIcon) arrowIcon.style.opacity = '0';
        spinner.style.display = 'inline-block';
        btn.style.pointerEvents = 'none';
    });

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
    document.getElementById('backToTop').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>

</body>
</html>