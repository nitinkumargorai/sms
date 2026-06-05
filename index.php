<?php
session_start();

if (isset($_SESSION['status'])) {
?>
<div class="container-fluid mt-4">
    <div class="alert alert-<?php echo ($_SESSION['status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show"
        role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php
    unset($_SESSION['status']);
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>StudyBuddyHub - Premium Learning Management System</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
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
            --orange: #f97316;
            --cyan: #06b6d4;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--dark);
            background: white;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--light);
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 10px;
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
            position: fixed;
            top: 0;
            width: 100%;
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
        .btn-login {
            background: transparent;
            color: var(--primary) !important;
            border: 2px solid var(--primary);
            padding: 0.5rem 1.25rem !important;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-login:hover {
            background: var(--primary);
            color: white !important;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        .btn-register {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white !important;
            border: none;
            padding: 0.5rem 1.25rem !important;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 6rem 2rem 4rem;
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 50%, #ffffff 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 80%;
            height: 150%;
            background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 60%;
            height: 100%;
            background: radial-gradient(circle, rgba(139,92,246,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(99,102,241,0.1);
            backdrop-filter: blur(10px);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(99,102,241,0.2);
        }
        .hero-content h1 {
            font-size: 3.8rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        .gradient-text {
            background: linear-gradient(135deg, var(--primary), var(--purple), var(--pink));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero-content p {
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        .btn-outline-custom {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            color: var(--dark);
            border: 1px solid var(--gray-light);
            padding: 0.875rem 2rem;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .btn-outline-custom:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-3px);
            background: white;
        }
        .hero-stats {
            display: flex;
            gap: 2.5rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }
        .hero-stats .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: block;
        }
        .hero-stats .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
        }
        .hero-image-wrapper {
            text-align: center;
            animation: float 5s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .hero-image {
            width: 100%;
            max-width: 500px;
            border-radius: 30px;
            box-shadow: var(--shadow-xl);
        }

        /* Sections */
        section {
            padding: 6rem 2rem;
        }
        .section-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 3rem;
        }
        .section-tag {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            color: var(--primary);
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .section-header p {
            color: var(--secondary);
            font-size: 1.1rem;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 28px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--purple), var(--pink));
            transform: scaleX(0);
            transition: var(--transition);
        }
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-size: 2rem;
        }
        .feature-card h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .feature-card p {
            color: var(--secondary);
            line-height: 1.6;
        }

        /* Role Cards */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        .role-card {
            background: white;
            padding: 2rem;
            border-radius: 28px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
            border: 1px solid var(--gray-light);
        }
        .role-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        .role-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--primary);
            font-size: 2.2rem;
        }
        .role-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .role-card p {
            color: var(--secondary);
            margin-bottom: 1rem;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            text-align: left;
            margin-top: 1rem;
        }
        .feature-list li {
            padding: 0.5rem 0;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .feature-list li i {
            color: var(--success);
            font-size: 0.8rem;
        }

        /* Team Section - Premium Redesign */
        .team-section {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            position: relative;
        }
        
        .team-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .team-card {
            background: white;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 28px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--purple), var(--pink));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .team-card:hover::before {
            transform: scaleX(1);
        }
        
        .team-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-xl);
        }
        
        .team-card .card-content {
            padding: 1.5rem;
            height: 100%;
            position: relative;
            z-index: 1;
        }
        
        .team-img {
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto;
            border: 4px solid white;
            box-shadow: var(--shadow-md);
            transition: all 0.4s ease;
        }
        
        .team-card:hover .team-img {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }
        
        .team-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .team-role {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-bg), #f3e8ff);
            color: var(--primary);
            font-weight: 600;
            border-radius: 50px;
            font-size: 0.7rem;
            padding: 0.25rem 1rem;
        }
        
        .team-social {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .team-social a {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .team-social a:hover {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
            transform: translateY(-3px);
        }
        
        /* Outer Team Cards */
        .team-card.outer {
            width: 220px;
        }
        .team-card.outer .card-content {
            padding: 1.25rem;
        }
        .team-card.outer .team-img {
            width: 85px;
            height: 85px;
        }
        .team-card.outer h3 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0.75rem 0 0.25rem;
        }
        .team-card.outer .team-role {
            font-size: 0.65rem;
            padding: 0.2rem 0.7rem;
            margin-bottom: 0.5rem;
        }
        .team-card.outer p {
            font-size: 0.7rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            color: var(--gray);
        }
        .team-card.outer .team-social a {
            width: 30px;
            height: 30px;
            font-size: 0.75rem;
        }
        
        /* Inner Team Cards */
        .team-card.inner {
            width: 260px;
        }
        .team-card.inner .card-content {
            padding: 1.5rem;
        }
        .team-card.inner .team-img {
            width: 110px;
            height: 110px;
        }
        .team-card.inner h3 {
            font-size: 1.15rem;
            margin: 0.75rem 0 0.35rem;
        }
        .team-card.inner .team-role {
            font-size: 0.7rem;
            padding: 0.25rem 0.9rem;
            margin-bottom: 0.6rem;
        }
        .team-card.inner p {
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 0.8rem;
            color: var(--gray);
        }
        
        /* Center Team Card */
        .team-card.center {
            width: 310px;
            z-index: 2;
        }
        .team-card.center .card-content {
            padding: 1.8rem;
            position: relative;
            background: white;
            border-radius: 28px;
        }
        .team-card.center .team-img {
            width: 140px;
            height: 140px;
            border-width: 5px;
        }
        .team-card.center h3 {
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0.9rem 0 0.4rem;
        }
        .team-card.center .team-role {
            font-size: 0.8rem;
            padding: 0.3rem 1.2rem;
            margin-bottom: 0.8rem;
        }
        .team-card.center p {
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            color: var(--gray);
        }
        .team-card.center .team-social a {
            width: 38px;
            height: 38px;
            font-size: 1rem;
        }
        
        /* Mobile Team Grid */
        .team-mobile {
            display: none;
        }
        .team-mobile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .team-mobile-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .team-mobile-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .team-mobile-img {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: var(--shadow);
        }
        .team-mobile-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .team-mobile-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .team-mobile-card .team-role {
            display: inline-block;
            font-size: 0.7rem;
            padding: 0.2rem 0.8rem;
            margin-bottom: 0.75rem;
        }
        .team-mobile-card p {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        /* Testimonials */
        .testimonials-section {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .testimonial-card {
            background: white;
            padding: 2rem;
            border-radius: 24px;
            box-shadow: var(--shadow);
            text-align: center;
            margin: 1rem;
            border: 1px solid var(--gray-light);
        }
        .testimonial-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .testimonial-text {
            font-size: 0.95rem;
            color: var(--secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
            font-style: italic;
        }
        .testimonial-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        .testimonial-role {
            font-size: 0.75rem;
            color: var(--gray);
        }
        .stars {
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 50%, var(--pink) 100%);
            border-radius: 40px;
            margin: 0 2rem 2rem 2rem;
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
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
        .cta-card {
            text-align: center;
            color: white;
            position: relative;
            z-index: 10;
            padding: 4rem 2rem;
        }
        .cta-card h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .cta-card p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        .btn-cta-primary {
            background: white;
            color: var(--primary);
            padding: 0.875rem 2rem;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .btn-cta-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 0.875rem 2rem;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .btn-cta-outline:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-3px);
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            color: white;
            padding: 4rem 2rem 2rem;
            margin-top: 2rem;
        }
        .footer-brand {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .footer-text {
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .social-links {
            display: flex;
            gap: 1rem;
        }
        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: var(--transition);
            text-decoration: none;
        }
        .social-links a:hover {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            transform: translateY(-3px);
        }
        .footer-links h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        .footer-links li {
            margin-bottom: 0.75rem;
        }
        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: var(--transition);
        }
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        .contact-info li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        .contact-info li i {
            width: 20px;
            color: var(--primary);
        }
        .footer-bottom {
            text-align: center;
            padding-top: 3rem;
            margin-top: 3rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8;
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

        /* Responsive */
        @media (max-width: 1300px) {
            .team-container { gap: 1rem; }
            .team-card.outer { width: 200px; }
            .team-card.inner { width: 240px; }
            .team-card.center { width: 280px; }
            .team-card.outer .team-img { width: 75px; height: 75px; }
            .team-card.inner .team-img { width: 100px; height: 100px; }
            .team-card.center .team-img { width: 125px; height: 125px; }
        }
        
        @media (max-width: 1100px) {
            .team-container { gap: 0.8rem; }
            .team-card.outer { width: 180px; }
            .team-card.inner { width: 220px; }
            .team-card.center { width: 260px; }
            .team-card.outer .team-img { width: 70px; height: 70px; }
            .team-card.inner .team-img { width: 90px; height: 90px; }
            .team-card.center .team-img { width: 115px; height: 115px; }
        }
        
        @media (max-width: 992px) {
            .team-container { display: none; }
            .team-mobile { display: block; }
            .hero-content h1 { font-size: 2.8rem; }
            .hero { text-align: center; }
            .hero-stats { justify-content: center; }
            section { padding: 4rem 1rem; }
            .cta-section { margin: 0 1rem 1rem 1rem; }
            .features-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2rem; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .btn-primary-custom, .btn-outline-custom { width: 100%; justify-content: center; }
            .section-header h2 { font-size: 1.8rem; }
            .cta-card h2 { font-size: 1.8rem; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .btn-cta-primary, .btn-cta-outline { width: 100%; justify-content: center; }
            .navbar { padding: 0.75rem 1rem; }
            .cursor-dot, .cursor-outline { display: none; }
            .team-mobile-grid { grid-template-columns: 1fr; }
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
        <a class="navbar-brand" href="#">
            <i class="fas fa-graduation-cap me-2"></i>StudyBuddyHub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#roles">For Who</a></li>
                <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                <li class="nav-item ms-lg-3">
                    <div class="d-flex gap-2">
                        <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="signup.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="hero" id="home">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-lg-6 order-lg-1 order-2" data-aos="fade-right" data-aos-duration="1000">
                <div class="hero-content">
                    <span class="hero-badge"><i class="fas fa-rocket me-1"></i> Next-Gen Learning Platform</span>
                    <h1>Transform Your<br><span class="gradient-text">Educational Journey</span></h1>
                    <p>StudyBuddyHub is a comprehensive learning management system designed for modern education. Access study materials, track attendance, submit assignments, and monitor progress - all in one intuitive platform.</p>
                    <div class="hero-buttons">
                        <a href="signup.php" class="btn-primary-custom"><i class="fas fa-user-plus"></i> Get Started Free <i class="fas fa-arrow-right ms-2"></i></a>
                        <a href="#features" class="btn-outline-custom"><i class="fas fa-play-circle"></i> Explore Features</a>
                    </div>
                    <div class="hero-stats">
                        <div><span class="stat-number">500+</span><div class="stat-label">Active Students</div></div>
                        <div><span class="stat-number">50+</span><div class="stat-label">Expert Teachers</div></div>
                        <div><span class="stat-number">1000+</span><div class="stat-label">Study Resources</div></div>
                        <div><span class="stat-number">95%</span><div class="stat-label">Success Rate</div></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 order-lg-2 order-1" data-aos="fade-left" data-aos-duration="1000">
                <div class="hero-image-wrapper">
                    <img src="image/benifit.jpg" alt="Learning Platform" class="hero-image">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES SECTION -->
<section id="features">
    <div class="container-fluid">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Why Choose Us</span>
            <h2>Everything You Need to Succeed</h2>
            <p>A complete ecosystem designed for modern education with powerful features</p>
        </div>
        <div class="features-grid">
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h4>Student Dashboard</h4>
                <p>Personalized dashboard to track attendance, results, assignments, and study materials all in one place.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="150">
                <div class="feature-icon"><i class="fas fa-chalkboard-user"></i></div>
                <h4>Teacher Panel</h4>
                <p>Comprehensive tools to upload materials, create assignments, mark attendance, and manage student results.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h4>Secure & Reliable</h4>
                <p>Your data is protected with enterprise-grade security measures and regular backups.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="250">
                <div class="feature-icon"><i class="fas fa-file-pdf"></i></div>
                <h4>Study Materials</h4>
                <p>Access notes, presentations, question banks, and reference materials anytime, anywhere.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-icon"><i class="fas fa-tasks"></i></div>
                <h4>Assignments</h4>
                <p>Submit assignments online, track deadlines, and get instant feedback from teachers.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="350">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h4>Performance Analytics</h4>
                <p>Monitor academic progress with detailed reports and actionable insights.</p>
            </div>
        </div>
    </div>
</section>

<!-- ROLE SECTIONS -->
<section id="roles">
    <div class="container-fluid">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Made For Everyone</span>
            <h2>Designed for Every Stakeholder</h2>
            <p>Whether you're a student, parent, or teacher - we've got you covered</p>
        </div>
        <div class="role-grid">
            <div class="role-card" data-aos="fade-up" data-aos-delay="100">
                <div class="role-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>For Students</h3>
                <p>Everything you need to excel in your academic journey</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i> Access study materials anytime</li>
                    <li><i class="fas fa-check-circle"></i> Track attendance and results</li>
                    <li><i class="fas fa-check-circle"></i> Submit assignments online</li>
                    <li><i class="fas fa-check-circle"></i> Get notifications and updates</li>
                    <li><i class="fas fa-check-circle"></i> View personalized dashboard</li>
                </ul>
            </div>
            <div class="role-card" data-aos="fade-up" data-aos-delay="200">
                <div class="role-icon"><i class="fas fa-chalkboard-user"></i></div>
                <h3>For Teachers</h3>
                <p>Powerful tools to manage your classroom effectively</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i> Upload study materials</li>
                    <li><i class="fas fa-check-circle"></i> Create and grade assignments</li>
                    <li><i class="fas fa-check-circle"></i> Mark attendance digitally</li>
                    <li><i class="fas fa-check-circle"></i> Add student results</li>
                    <li><i class="fas fa-check-circle"></i> Manage multiple subjects</li>
                </ul>
            </div>
            <div class="role-card" data-aos="fade-up" data-aos-delay="300">
                <div class="role-icon"><i class="fas fa-chart-simple"></i></div>
                <h3>For Parents</h3>
                <p>Stay informed about your child's academic progress</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i> Track attendance records</li>
                    <li><i class="fas fa-check-circle"></i> Monitor exam results</li>
                    <li><i class="fas fa-check-circle"></i> View assignment progress</li>
                    <li><i class="fas fa-check-circle"></i> Receive notifications</li>
                    <li><i class="fas fa-check-circle"></i> Access performance reports</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- TEAM SECTION - PREMIUM REDESIGN WITH ALL MEMBERS -->
<section id="team" class="team-section">
    <div class="container-fluid">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Meet Our Team</span>
            <h2>The Minds Behind StudyBuddyHub</h2>
            <p>Passionate developers and designers working together to transform education</p>
        </div>
        
        <!-- Desktop View - Pyramid Layout -->
        <div class="team-container">
            <!-- Left Side - Outer -->
            <div class="team-card outer" data-aos="fade-right" data-aos-delay="50">
                <div class="card-content">
                    <div class="team-img"><img src="team/mahima.jpeg" alt="Mahima Surin"></div>
                    <h3>Mahima Surin</h3>
                    <span class="team-role">UI/UX Designer</span>
                    <p>Creative designer crafting intuitive user experiences</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>

            <div class="team-card inner" data-aos="fade-right" data-aos-delay="100">
                <div class="card-content">
                    <div class="team-img"><img src="team/rohit.jpeg" alt="Rohit Gope"></div>
                    <h3>Rohit Gope</h3>
                    <span class="team-role">Backend Developer</span>
                    <p>Expert in server-side logic and database architecture</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>

            <!-- Center - Lead Developer -->
            <div class="team-card center" data-aos="zoom-in" data-aos-delay="150">
                <div class="card-content">
                    <div class="team-img"><img src="team/nitin.jpeg" alt="Nitin Kumar Gorai"></div>
                    <h3>Nitin Kumar Gorai</h3>
                    <span class="team-role">Lead Developer</span>
                    <p>Full-stack expert leading the team to create an exceptional learning platform</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        
                    </div>
                </div>
            </div>

            <div class="team-card inner" data-aos="fade-left" data-aos-delay="100">
                <div class="card-content">
                    <div class="team-img"><img src="team/kundan.jpeg" alt="Kundan Kumar Singh"></div>
                    <h3>Kundan Kumar Singh</h3>
                    <span class="team-role">Frontend Developer</span>
                    <p>Creating responsive and engaging user interfaces</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>

            <div class="team-card outer" data-aos="fade-left" data-aos-delay="50">
                <div class="card-content">
                    <div class="team-img"><img src="team/pakhi.jpeg" alt="Pakhi Mahato"></div>
                    <h3>Pakhi Mahato</h3>
                    <span class="team-role">Quality Assurance</span>
                    <p>Ensuring quality through rigorous testing processes</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile View - Grid Layout -->
        <div class="team-mobile">
            <div class="team-mobile-grid">
                <div class="team-mobile-card">
                    <div class="team-mobile-img"><img src="team/nitin.jpeg" alt="Nitin"></div>
                    <h3>Nitin Kumar Gorai</h3>
                    <span class="team-role">Lead Developer</span>
                    <p>Full-stack developer leading the project with expertise in PHP, MySQL, and modern web technologies.</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="team-mobile-card">
                    <div class="team-mobile-img"><img src="team/rohit.jpeg" alt="Rohit"></div>
                    <h3>Rohit Gope</h3>
                    <span class="team-role">Backend Developer</span>
                    <p>Specialist in database design, API development, and server-side optimization.</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="team-mobile-card">
                    <div class="team-mobile-img"><img src="team/kundan.jpeg" alt="Kundan"></div>
                    <h3>Kundan Kumar Singh</h3>
                    <span class="team-role">Frontend Developer</span>
                    <p>Expert in responsive UI with modern frameworks and smooth animations.</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="team-mobile-card">
                    <div class="team-mobile-img"><img src="team/mahima.jpeg" alt="Mahima"></div>
                    <h3>Mahima Surin</h3>
                    <span class="team-role">UI/UX Designer</span>
                    <p>Creative designer focused on user experience and intuitive interfaces.</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="team-mobile-card">
                    <div class="team-mobile-img"><img src="team/pakhi.jpeg" alt="Pakhi"></div>
                    <h3>Pakhi Mahato</h3>
                    <span class="team-role">Quality Assurance</span>
                    <p>Ensures everything works perfectly through comprehensive testing and validation.</p>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section id="testimonials" class="testimonials-section">
    <div class="container-fluid">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Testimonials</span>
            <h2>What Our Users Say</h2>
            <p>Real experiences from students, teachers, and parents</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-card">
                    <div class="testimonial-avatar"><i class="fas fa-user-graduate"></i></div>
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">"StudyBuddyHub has completely transformed how I study. All my materials are in one place, and I never miss an assignment deadline!"</p>
                    <div class="testimonial-name">Rahul Sharma</div>
                    <div class="testimonial-role">Computer Science Student</div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-card">
                    <div class="testimonial-avatar"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">"The platform has made managing my classes so much easier. Attendance tracking and result management is now a breeze."</p>
                    <div class="testimonial-name">Prof. Meera Singh</div>
                    <div class="testimonial-role">Mathematics Teacher</div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-card">
                    <div class="testimonial-avatar"><i class="fas fa-user-check"></i></div>
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">"As a parent, I love being able to track my child's progress. The regular updates and transparency give me peace of mind."</p>
                    <div class="testimonial-name">Suresh Kumar</div>
                    <div class="testimonial-role">Parent</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="cta-section">
    <div class="container-fluid">
        <div class="cta-card" data-aos="fade-up">
            <h2>Ready to Transform Your Learning Experience?</h2>
            <p>Join thousands of students and teachers already using StudyBuddyHub</p>
            <div class="cta-buttons d-flex gap-3 justify-content-center flex-wrap">
                <a href="signup.php" class="btn-cta-primary"><i class="fas fa-user-plus"></i> Get Started Now <i class="fas fa-arrow-right ms-2"></i></a>
                <a href="login.php" class="btn-cta-outline"><i class="fas fa-sign-in-alt"></i> Login to Account</a>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer" id="contact">
    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand"><i class="fas fa-graduation-cap me-2"></i>StudyBuddyHub</div>
                <p class="footer-text">Your complete digital learning platform for diploma and college students. Empowering education through technology.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-github"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="footer-links">
                    <h5>Quick Links</h5>
                    <ul><li><a href="#home">Home</a></li><li><a href="#features">Features</a></li><li><a href="#roles">For Who</a></li><li><a href="#team">Team</a></li><li><a href="#testimonials">Testimonials</a></li><li><a href="#contact">Contact</a></li></ul>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="footer-links">
                    <h5>Contact Info</h5>
                    <ul class="contact-info">
                        <li><i class="fas fa-envelope"></i> <span>nitinkumargorai2004@gmail.com</span></li>
                        <li><i class="fas fa-phone"></i> <span>+91 98352 89540</span></li>
                        <li><i class="fas fa-map-marker-alt"></i> <span>Jamshedpur, Jharkhand, India</span></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="footer-links">
                    <h5>Newsletter</h5>
                    <p class="footer-text">Subscribe for updates and news</p>
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your Email" style="border-radius: 12px 0 0 12px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: white;">
                        <button class="btn" style="background: linear-gradient(135deg, var(--primary), var(--purple)); border-radius: 0 12px 12px 0; color: white;"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 StudyBuddyHub. All rights reserved. | Designed with <i class="fas fa-heart text-danger"></i> by Team StudyBuddyHub</p>
        </div>
    </div>
</footer>

<a href="#" class="back-to-top" id="backToTop"><i class="fas fa-arrow-up"></i></a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    AOS.init({ duration: 800, once: true, offset: 50 });
    
    // Custom Cursor
    const cursorDot = document.querySelector('.cursor-dot');
    const cursorOutline = document.querySelector('.cursor-outline');
    
    if (cursorDot && cursorOutline) {
        window.addEventListener('mousemove', (e) => {
            cursorDot.style.transform = `translate(${e.clientX - 3}px, ${e.clientY - 3}px)`;
            cursorOutline.style.transform = `translate(${e.clientX - 15}px, ${e.clientY - 15}px)`;
        });
        
        document.querySelectorAll('a, button, .feature-card, .role-card, .testimonial-card, .team-card, .btn-primary-custom, .btn-outline-custom, .btn-login, .btn-register').forEach(el => {
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
    
    // Navbar Scroll Effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        const backToTop = document.getElementById('backToTop');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
        if (window.scrollY > 300) backToTop.classList.add('show');
        else backToTop.classList.remove('show');
    });
    
    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    
    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

</body>
</html>