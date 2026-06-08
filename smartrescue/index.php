<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartRescue - Mogadishu Emergency</title>
    <!-- Google Fonts for Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap and Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd; /* Pure Blue */
            --secondary: #0a58ca; /* Darker Blue */
            --accent: #e0f2fe;  /* White Blue */
            --light: #ffffff;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--light);
            color: #334155;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        
        /* Modern entry animations (AOS-like) */
        .reveal-up {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .reveal-up.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        .navbar-brand {
            font-weight: 800;
            color: var(--primary) !important;
            font-size: 1.6rem;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
        }
        .navbar-brand i { margin-right: 8px; font-size: 1.8rem; }
        .nav-link {
            font-weight: 600;
            color: var(--secondary) !important;
            margin: 0 5px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            position: relative;
            transition: color 0.3s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            left: 0; bottom: -5px;
            width: 0; height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        .nav-link:hover { color: var(--primary) !important; }
        .nav-link:hover::after { width: 100%; }

        /* -------------- BUTTONS -------------- */
        .btn-custom {
            border-radius: 8px;
            padding: 12px 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: #fff;
            border: none;
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #0a58ca, #084298);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(13, 110, 253, 0.5);
            color: #fff;
        }
        .btn-outline-custom {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: rgba(13, 110, 253, 0.05);
            font-weight: 700;
        }
        .btn-outline-custom:hover {
            background: var(--primary);
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3);
        }

        /* -------------- HERO SECTION -------------- */
        .hero-section {
            background: linear-gradient(135deg, rgba(240, 248, 255, 0.98), rgba(224, 242, 254, 0.95));
            min-height: 100vh;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            padding-top: 100px;
            position: relative;
            overflow: hidden;
        }
        
        /* Subtle Background Images Design */
        .hero-bg-icons {
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }
        .bg-icon {
            position: absolute;
            font-size: 28rem;
            color: var(--primary);
            opacity: 0.04;
        }
        .bg-icon-1 { top: 10%; right: 5%; transform: rotate(15deg); }
        .bg-icon-2 { bottom: -5%; right: 25%; font-size: 20rem; transform: rotate(-10deg); opacity: 0.05; }
        .bg-icon-3 { top: 30%; right: -5%; font-size: 24rem; transform: rotate(0deg); opacity: 0.03; }

        .hero-section .container { z-index: 1; position: relative; }

        .hero-title {
            font-size: 5rem;
            font-weight: 800;
            margin-bottom: 20px;
            animation: fadeInDown 1s cubic-bezier(0.2, 0.8, 0.2, 1);
            letter-spacing: -2px;
            line-height: 1.1;
            text-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .hero-subtitle {
            font-size: 1.4rem;
            font-weight: 300;
            margin-bottom: 40px;
            opacity: 0.95;
            animation: fadeInUp 1s ease 0.3s both;
            max-width: 650px;
            margin-left: 0;
        }
        .hero-buttons {
            animation: fadeInUp 1s ease 0.5s both;
            margin-bottom: 30px;
        }
        .btn-sos-hero {
            background: linear-gradient(135deg, #e01a2c, #b91c1c); /* Red Color */
            color: white;
            padding: 16px 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(224, 26, 44, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: pulse-red 2s infinite cubic-bezier(0.66, 0, 0, 1), fadeInUp 1s ease 0.7s both;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .btn-sos-hero:hover {
            transform: translateY(-8px) scale(1.05); /* Pronounced hover lift */
            color: white;
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            box-shadow: 0 15px 35px rgba(224, 26, 44, 0.6);
        }
        
        /* -------------- SECTION STYLES -------------- */
        .section-padding { padding: 120px 0; }
        .section-title {
            text-align: center;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 60px;
            position: relative;
            font-size: 2.8rem;
            letter-spacing: -1px;
        }
        .section-title::after {
            content: '';
            width: 80px; height: 5px;
            background: linear-gradient(90deg, var(--primary), #8dc6ff);
            display: block;
            margin: 20px auto 0;
            border-radius: 5px;
        }

        /* -------------- SERVICES AND FEATURES SECTION -------------- */
        .service-card {
            border-radius: 20px; overflow: hidden; transition: all 0.4s ease;
            background: #fff; border: 1px solid rgba(0,0,0,0.03);
        }
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08) !important;
        }

        .features-section { background: #fff; }
        .feature-card { background: #f8f9fa; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; }
        .feature-card:hover { transform: translateY(-7px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); border-color: rgba(230,57,70,0.1); }
        .feature-icon-wrapper { width: 65px; height: 65px; border-radius: 18px; background: rgba(230,57,70,0.08); display: flex; align-items: center; justify-content: center; margin-bottom: 25px; transition: 0.3s; }
        .feature-card:hover .feature-icon-wrapper { background: var(--primary); transform: rotate(5deg) scale(1.05); }
        .feature-card:hover .feature-icon-wrapper i { color: #fff; }
        .feature-icon-wrapper i { font-size: 1.8rem; color: var(--primary); transition: 0.3s; }
        .feature-title { font-weight: 700; color: var(--secondary); font-size: 1.25rem; margin-bottom: 12px; }
        .feature-desc { color: #6c757d; font-size: 0.95rem; line-height: 1.6; margin: 0; }

        /* Alternate Feature Colors based on index */
        .color-1 { background: rgba(13,110,253,0.1) !important; color: #0d6efd !important; }
        .color-2 { background: rgba(10,88,202,0.1) !important; color: #0a58ca !important; }
        .color-3 { background: rgba(13,110,253,0.1) !important; color: #0d6efd !important; }
        .color-4 { background: rgba(10,88,202,0.1) !important; color: #0a58ca !important; }
        .color-5 { background: rgba(13,110,253,0.1) !important; color: #0d6efd !important; }
        .color-6 { background: rgba(10,88,202,0.1) !important; color: #0a58ca !important; }

        /* -------------- HOW IT WORKS CARDS -------------- */
        .step-card {
            background: #fff;
            border-radius: 25px;
            padding: 50px 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.03);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(0,0,0,0.03);
        }
        .step-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: rgba(230, 57, 70, 0.1);
        }
        .step-icon {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center; justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 30px;
            transition: all 0.4s ease;
        }
        .step-card:hover .step-icon {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: #fff;
            transform: scale(1.1) rotate(5deg);
        }
        .step-title {
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--secondary);
            font-size: 1.5rem;
        }
        .step-desc {
            color: #6c757d;
            font-size: 1rem;
            line-height: 1.7;
        }

        /* -------------- SAFETY TIPS -------------- */
        .tips-section { background: var(--accent); position: relative; }
        .tips-section::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0,0,0,0.05), transparent);
        }
        .tip-card {
            background: #fff; border-radius: 20px; padding: 35px; box-shadow: 0 10px 30px rgba(13, 110, 253, 0.05); 
            transition: all 0.4s ease; border-left: 5px solid var(--primary); height: 100%; display: flex; flex-direction: column;
        }
        .tip-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .tip-icon { font-size: 2.5rem; color: var(--primary); margin-bottom: 20px; }
        .tip-title { font-weight: 800; color: var(--secondary); font-size: 1.3rem; margin-bottom: 15px; }
        .tip-text { color: #64748b; font-size: 1rem; line-height: 1.6; }

        /* -------------- CTA SECTION -------------- */
        .cta-box {
            background: #1771e6;
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(13, 110, 253, 0.15);
        }
        .cta-box::before {
            content: ''; position: absolute; top: 0; right: 0; width: 140px; height: 100%;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 0 12px 12px 0;
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
        }
        .cta-box::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100px; height: 80px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 0 20px 0 12px;
        }
        .cta-title { font-size: 2.6rem; font-weight: 700; margin-bottom: 15px; position: relative; z-index: 1; letter-spacing: -0.5px; }
        .cta-desc { font-size: 1.1rem; opacity: 0.95; margin-bottom: 30px; position: relative; z-index: 1; }
        .btn-cta-white, .btn-cta-outline {
            font-weight: 700; padding: 14px 28px; border-radius: 8px; text-decoration: none; display: inline-block; margin: 0 8px 10px; transition: all 0.3s; position: relative; z-index: 1; font-size: 0.95rem;
        }
        .btn-cta-white { background: #fff; color: #1771e6; border: 2px solid #fff; }
        .btn-cta-white:hover { background: rgba(255,255,255,0.9); border-color: transparent; transform: translateY(-3px); }
        .btn-cta-outline { background: transparent; color: #fff; border: 2px solid #fff; }
        .btn-cta-outline:hover { background: #fff; color: #1771e6; transform: translateY(-3px); }

        /* -------------- FOOTER -------------- */
        .footer {
            background: #08214f;
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }
        .footer-brand {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        .footer-brand:hover { color: #fff; opacity: 0.9; }
        .footer-text {
            color: rgba(255,255,255,0.5);
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .social-links a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 45px; height: 45px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            color: #fff;
            margin-right: 12px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            font-size: 1.1rem;
        }
        .social-links a:hover {
            background: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(13,110,253,0.4);
        }
        .footer-title {
            font-size: 1.2rem;
            color: #fff;
            font-weight: 700;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
            position: relative;
            padding-bottom: 10px;
        }
        .footer-title::after {
            content: '';
            position: absolute;
            left: 0; bottom: 0;
            width: 30px; height: 2px;
            background: var(--primary);
        }
        .footer-links { list-style: none; padding: 0; }
        .footer-links li { margin-bottom: 15px; }
        .footer-links a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            padding-left: 0;
        }
        .footer-links a:hover {
            color: var(--primary);
            padding-left: 10px;
        }
        .contact-list { list-style: none; padding: 0; }
        .contact-list li {
            position: relative;
            padding-left: 35px;
            margin-bottom: 18px;
            color: rgba(255,255,255,0.7);
        }
        .contact-list i {
            position: absolute;
            left: 0; top: 4px;
            color: var(--primary);
            font-size: 1.1rem;
        }
        .copyright-area {
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 30px;
            margin-top: 60px;
            text-align: center;
            color: rgba(255,255,255,0.4);
            font-size: 0.95rem;
        }

        /* -------------- ANIMATIONS -------------- */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            to { box-shadow: 0 0 0 35px rgba(13, 110, 253, 0); }
        }
        @keyframes pulse-red {
            to { box-shadow: 0 0 0 35px rgba(224, 26, 44, 0); }
        }
        
        /* -------------- RESPONSIVENESS -------------- */
        @media (max-width: 768px) {
            .hero-title { font-size: 3.5rem; }
            .section-padding { padding: 80px 0; }
            .section-title { font-size: 2.2rem; }
            .navbar { background: rgba(255, 255, 255, 0.98); }
            .nav-link { margin: 10px 0; display: inline-block; }
        }
    </style>
</head>
<body>

<!-- Header / Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
    <div class="container-fluid px-4 px-lg-5">
        <a class="navbar-brand" href="#home" style="display: flex; align-items: center; gap: 8px;">
            <div style="background: linear-gradient(135deg, var(--primary), #0a58ca); color: white; border-radius: 8px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);">
                <i class="fa-solid fa-truck-medical" style="font-size: 1.2rem; margin: 0;"></i>
            </div>
            <span style="font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;"><span style="color: var(--secondary);">Smart</span><span style="color: var(--primary);">Rescue</span></span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="fa-solid fa-bars text-dark fs-4"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#services">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how-it-works">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#safety-tips">Safety Tips</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#about">About Us</a>
                </li>
            </ul>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center mt-3 mt-lg-0">
                <a href="auth/login.php" class="btn btn-outline-custom mb-2 mb-lg-0 me-lg-3">Login</a>
                <a href="auth/register.php" class="btn btn-primary-custom">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Section -->
<main>
    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="hero-bg-icons">
            <i class="fa-solid fa-truck-medical bg-icon bg-icon-1"></i>
            <i class="fa-solid fa-fire-extinguisher bg-icon bg-icon-2"></i>
            <i class="fa-solid fa-shield-halved bg-icon bg-icon-3"></i>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <h1 class="hero-title">Rapid Response<br>Saves Lives</h1>
                    <p class="hero-subtitle">Mogadishu's Premiere Emergency Dispatch System. Connecting citizens with Ambulance, Fire, and Police services in milliseconds.</p>
                    <div class="hero-buttons d-flex flex-wrap align-items-center gap-4">
                        <a href="user/index.php" class="btn-sos-hero"><i class="fa-solid fa-triangle-exclamation me-2"></i> EMERGENCY SOS</a>
                        <a href="#about" class="btn btn-primary-custom" style="padding: 16px 30px;"><i class="fa-solid fa-arrow-right me-2"></i>Discover More</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section-padding" style="background: #f8fafc;">
        <div class="container">
            <h2 class="section-title reveal-up">Our Emergency Services</h2>
            <p class="text-center text-muted mb-5 reveal-up" style="max-width: 650px; margin: 0 auto; font-size: 1.1rem; line-height: 1.7;">Delivering integrated, rapid-response emergency solutions designed to protect the Mogadishu community 24/7.</p>
            <div class="row g-4 mt-2">
                <!-- Medical -->
                <div class="col-lg-3 col-md-6 reveal-up" style="transition-delay: 0.1s;">
                    <div class="service-card shadow-sm h-100">
                        <div style="height: 180px; background: linear-gradient(135deg, rgba(13,110,253,0.1), rgba(13,110,253,0.02)); display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-truck-medical" style="font-size: 4.5rem; color: var(--primary);"></i>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h4 class="fw-bold mb-3" style="color: var(--secondary); font-size: 1.25rem;">Medical Ambulance</h4>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">Immediate dispatch of trained paramedics and fully-equipped ambulances to handle critical health emergencies.</p>
                        </div>
                    </div>
                </div>
                <!-- Fire -->
                <div class="col-lg-3 col-md-6 reveal-up" style="transition-delay: 0.2s;">
                    <div class="service-card shadow-sm h-100">
                        <div style="height: 180px; background: linear-gradient(135deg, rgba(224,26,44,0.1), rgba(224,26,44,0.02)); display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-fire-extinguisher" style="font-size: 4.5rem; color: #e01a2c;"></i>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h4 class="fw-bold mb-3" style="color: var(--secondary); font-size: 1.25rem;">Fire Department</h4>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">Swift deployment of structural fire and hazard specialists to suppress fires and handle material leaks.</p>
                        </div>
                    </div>
                </div>
                <!-- Police -->
                <div class="col-lg-3 col-md-6 reveal-up" style="transition-delay: 0.3s;">
                    <div class="service-card shadow-sm h-100">
                        <div class="bg-light" style="height: 180px; background: linear-gradient(135deg, rgba(10,88,202,0.1), rgba(10,88,202,0.02)) !important; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-shield-halved" style="font-size: 4.5rem; color: var(--secondary);"></i>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h4 class="fw-bold mb-3" style="color: var(--secondary); font-size: 1.25rem;">Police Assistance</h4>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">Direct rapid alert to local law enforcement personnel for crime scenes and ensuring absolute scene safety.</p>
                        </div>
                    </div>
                </div>
                <!-- Accident -->
                <div class="col-lg-3 col-md-6 reveal-up" style="transition-delay: 0.4s;">
                    <div class="service-card shadow-sm h-100">
                        <div style="height: 180px; background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(245,158,11,0.02)); display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-car-burst" style="font-size: 4.5rem; color: #f59e0b;"></i>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h4 class="fw-bold mb-3" style="color: var(--secondary); font-size: 1.25rem;">Accident Response</h4>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">Specialized rapid response for traffic collisions, providing vehicle extrication and immediate road safety.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="section-padding bg-white">
        <div class="container">
            <h2 class="section-title">SmartRescue Features</h2>
            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-1"><i class="fa-solid fa-hand-pointer"></i></div>
                        <h3 class="feature-title">One-Tap SOS</h3>
                        <p class="feature-desc">A single-button interface to instantly trigger an emergency request alongside precise GPS data.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-2"><i class="fa-solid fa-map-location-dot"></i></div>
                        <h3 class="feature-title">Real-Time Tracking</h3>
                        <p class="feature-desc">Live map functionality that allows users to track the responding unit and view the estimated time of arrival (ETA).</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-3"><i class="fa-solid fa-microchip"></i></div>
                        <h3 class="feature-title">Automated Dispatch</h3>
                        <p class="feature-desc">The system automatically identifies and assigns the nearest available emergency vehicle.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-4"><i class="fa-solid fa-file-medical"></i></div>
                        <h3 class="feature-title">Pre-Arrival Notification</h3>
                        <p class="feature-desc">Users or drivers can send brief details about the emergency (e.g., bleeding, unconsciousness) to help hospitals prepare.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-5"><i class="fa-solid fa-users-viewfinder"></i></div>
                        <h3 class="feature-title">Emergency Contacts</h3>
                        <p class="feature-desc">Allows users to add trusted contacts who will be automatically alerted via SMS or push notification.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper color-6"><i class="fa-solid fa-camera"></i></div>
                        <h3 class="feature-title">Photo Upload</h3>
                        <p class="feature-desc">Enables users to optionally upload images related to the emergency situation for quick assessment.</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4 justify-content-center">
                <div class="col-lg-8">
                    <div class="feature-card text-center text-sm-start flex-column align-items-center flex-sm-row p-4 p-md-5 w-100" style="background:#fff; border-color: rgba(230,57,70,0.15); box-shadow: 0 15px 40px rgba(230,57,70,0.05);">
                        <div class="feature-icon-wrapper flex-shrink-0 mb-3 mb-sm-0 me-sm-4 color-1" style="width: 80px; height: 80px;"><i class="fa-solid fa-laptop-medical fs-1" style="color:#0d6efd !important;"></i></div>
                        <div>
                            <h3 class="feature-title mb-2 fs-4">Emergency Service Integration</h3>
                            <p class="feature-desc mb-0 fs-6">Supports medical emergencies, fire incidents, accidents, and police services within one unified system.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Safety Tips Section -->
    <section id="safety-tips" class="section-padding tips-section">
        <div class="container">
            <h2 class="section-title reveal-up">Essential Safety Tips</h2>
            <div class="row g-4 mt-4">
                <div class="col-lg-4 col-md-6 reveal-up" style="transition-delay: 0.1s;">
                    <div class="tip-card">
                        <i class="fa-solid fa-fire-extinguisher tip-icon"></i>
                        <h4 class="tip-title">Fire Emergencies</h4>
                        <p class="tip-text">Stay low to the ground where the air is cleaner. Never use elevators during a fire; always take the stairs. Know your emergency exits.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 reveal-up" style="transition-delay: 0.2s;">
                    <div class="tip-card">
                        <i class="fa-solid fa-house-medical tip-icon"></i>
                        <h4 class="tip-title">Medical First Aid</h4>
                        <p class="tip-text">If someone is injured, do not move them unless they are in immediate danger. Apply pressure to bleeding wounds and call for an ambulance instantly.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 reveal-up" style="transition-delay: 0.3s;">
                    <div class="tip-card">
                        <i class="fa-solid fa-car-burst tip-icon"></i>
                        <h4 class="tip-title">Traffic Accidents</h4>
                        <p class="tip-text">Turn on your hazard lights. If safe to do so, move vehicles out of traffic. Do not leave the scene until emergency services arrive.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="section-padding">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="row g-4 mt-4">
                <!-- Step 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="step-card">
                        <div class="step-icon shadow-sm">
                            <i class="fa-solid fa-mobile-screen-button"></i>
                        </div>
                        <h4 class="step-title">1. Press SOS</h4>
                        <p class="step-desc">Open the application during any emergency and immediately interact with the one-tap SOS button.</p>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="step-card">
                        <div class="step-icon shadow-sm">
                            <i class="fa-solid fa-location-crosshairs"></i>
                        </div>
                        <h4 class="step-title">2. Location Sent</h4>
                        <p class="step-desc">SmartRescue auto-captures your precise GPS coordinates, attaching them to your emergency type payload.</p>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="step-card">
                        <div class="step-icon shadow-sm">
                            <i class="fa-solid fa-truck-medical"></i>
                        </div>
                        <h4 class="step-title">3. Help Arrives</h4>
                        <p class="step-desc">Nearby authorized emergency vehicles are dispatched immediately, slashing response times drastically.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- About Us Section -->
    <section id="about" class="section-padding bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0 position-relative">
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary rounded-4 opacity-10 ms-3 mt-3"></div>
                    <img src="https://images.unsplash.com/photo-1554734867-bf3c00a49371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="About SmartRescue" class="img-fluid rounded-4 shadow-lg position-relative" style="border: 12px solid #fff;">
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <div class="mb-3 d-inline-flex align-items-center px-3 py-1 rounded-pill" style="background: rgba(13, 110, 253, 0.1); color: var(--primary); font-weight: 700; font-size: 0.85rem; letter-spacing: 1px;">
                        <i class="fa-solid fa-shield-halved me-2"></i> ABOUT US
                    </div>
                    <h2 class="mb-4" style="font-weight: 800; color: var(--secondary); font-size: 2.8rem; line-height: 1.2; letter-spacing: -1px;">Dedicated to Protecting Our Community</h2>
                    <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.1rem;">
                        SmartRescue is a cutting-edge emergency response platform built specifically for the residents of Mogadishu. Our mission is to bridge the gap between citizens in distress and immediate professional emergency responders.
                    </p>
                    <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.1rem;">
                        By leveraging real-time geolocation and automated dispatching, we ensure that the right help reaches you precisely when you need it most.
                    </p>
                    <ul class="list-unstyled mt-5" style="color: var(--secondary); font-weight: 700; font-size: 1.05rem;">
                        <li class="mb-3"><i class="fa-solid fa-circle-check text-primary me-3 fs-4 align-middle"></i> 24/7 Rapid Emergency Response</li>
                        <li class="mb-3"><i class="fa-solid fa-circle-check text-primary me-3 fs-4 align-middle"></i> Pinpoint GPS Location Tracking</li>
                        <li class="mb-3"><i class="fa-solid fa-circle-check text-primary me-3 fs-4 align-middle"></i> Integrated Police, Fire & Medical Network</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-5 bg-white mb-4 mt-2">
        <div class="container reveal-up">
            <div class="cta-box">
                <h2 class="cta-title">Secure Your Community Today</h2>
                <p class="cta-desc">Join the network of responders and hospitals making Mogadishu safer every day.</p>
                <div>
                    <a href="auth/register.php" class="btn-cta-white">Join SmartRescue Today</a>
                    <a href="#about" class="btn-cta-outline">Contact Support</a>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <!-- Brand Column -->
            <div class="col-lg-4 mb-5 mb-lg-0 pe-lg-5">
                <a href="#home" class="footer-brand"><i class="fa-solid fa-heart-pulse me-2 text-primary"></i>SmartRescue</a>
                <p class="footer-text">Nidaamka casriga ah ee gurmadka degdega ah. Bridging the gap between distressed citizens and immediate rescue operations in Mogadishu.</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/share/1Gu5vKYcde/"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
            </div>
            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 mb-5 mb-lg-0">
                <h5 class="footer-title">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="auth/login.php">Login / Sign In</a></li>
                    <li><a href="auth/register.php">Create Account</a></li>
                </ul>
            </div>
            <!-- Emergency Services -->
            <div class="col-lg-3 col-md-6 mb-5 mb-lg-0">
                <h5 class="footer-title">Services</h5>
                <ul class="footer-links">
                    <li><a href="#">Medical Ambulance</a></li>
                    <li><a href="#">Fire Department</a></li>
                    <li><a href="#">Police Assistance</a></li>
                    <li><a href="#">Disaster Management</a></li>
                </ul>
            </div>
            <!-- Contact Info -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Contact Us</h5>
                <ul class="contact-list">
                    <li><i class="fa-solid fa-location-dot"></i> Maka Al Mukarama Rd, Mogadishu, Somalia</li>
                    <li><i class="fa-solid fa-phone"></i> 999 (Toll Free Emergency)</li>
                    <li><i class="fa-solid fa-envelope"></i> help@smartrescue.so</li>
                </ul>
            </div>
        </div>
        <div class="copyright-area">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> SmartRescue. Designed & Developed for the Safety of Mogadishu. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Smooth scroll offset adjustment for fixed navbar
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if(target) {
                const navHeight = document.querySelector('.navbar').offsetHeight;
                window.scrollTo({
                    top: target.offsetTop - navHeight + 10,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Dynamic Navbar Styling on Scroll
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('mainNavbar');
        if (window.scrollY > 50) {
            navbar.style.background = 'rgba(255, 255, 255, 0.96)';
            navbar.style.padding = '5px 0';
            navbar.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.08)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.85)';
            navbar.style.padding = '8px 0';
            navbar.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.05)';
        }
    });

    // Close mobile menu on click
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    const menuToggle = document.getElementById('navbarNav');
    const bsCollapse = new bootstrap.Collapse(menuToggle, {toggle: false});
    navLinks.forEach((l) => {
        l.addEventListener('click', () => { if (menuToggle.classList.contains('show')) { bsCollapse.toggle(); } });
    });

    // Reveal Animations on Scroll
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.15
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Auto-select elements to animate if they don't have reveal-up
    document.querySelectorAll('.feature-card, .step-card, .section-title, .hero-title').forEach(el => {
        if(!el.classList.contains('reveal-up')) {
            el.classList.add('reveal-up');
        }
        observer.observe(el);
    });
    document.querySelectorAll('.reveal-up').forEach(el => observer.observe(el));
</script>
</body>
</html>