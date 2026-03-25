<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRỌNG TÀI SỐ - Đấu Trường Đỉnh Cao</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2ecc71;
            --primary-dark: #27ae60;
            --accent: #ff6b00;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --bg-light: #f8fafc;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }
        
        .navbar-custom {
            background: var(--white);
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--accent) !important;
        }
        
        .nav-link {
            font-weight: 600;
            color: var(--text-dark) !important;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--accent) !important;
        }
                .nav-tabs-custom .nav-link.active {
            color: var(--accent);
            background: transparent;
        }
        
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-dark);
            font-weight: 600;
            padding: 20px 25px;
            border-radius: 0;
            margin: 0;
            position: relative;
        }
        
        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
        }
        
        .hero-section {
            background: linear-gradient(rgb(0 0 0 / 50%), rgb(0 0 0 / 55%)), 
                        url('https://images.unsplash.com/photo-1761644658016-324918bc373c?utm_content=DAHAK3DVkBI&utm_campaign=designshare&utm_medium=link2&utm_source=sharebutton?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .hero-content {
            text-align: center;
            z-index: 2;
        }
        
        .logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 5rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -2px;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 0px var(--text-dark);
        }
        
        .logo span {
            color: var(--primary);
            font-size: 2.5rem;
            display: block;
            font-weight: 600;
            letter-spacing: 5px;
            text-shadow: none;
            margin-top: -10px;
        }
        
        .tagline {
            font-size: 1.25rem;
            color: #ff7600;
            margin-bottom: 2.5rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .btn-accent {
            background-color: #CC6600;
            color: var(--white);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            margin: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(255, 107, 0, 0.3);
            text-transform: uppercase;
        }
        
        .btn-accent:hover {
            background-color: #e65100;
            transform: translateY(-3px);
            color: var(--white);
            box-shadow: 0 15px 30px rgba(255, 107, 0, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary-dark);
            background: transparent;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--text-dark);
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--accent);
            margin: 15px auto 0;
            border-radius: 2px;
        }

        .dashboard-section {
            padding: 100px 0;
            background: var(--white);
        }
        
        .dashboard-card {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .dashboard-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }
        
        .dashboard-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        
        .stats-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
        }
        
        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer {
            background: var(--white);
            padding: 60px 0 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .social-link {
            display: inline-block;
            width: 45px;
            height: 45px;
            background: var(--bg-light);
            border-radius: 50%;
            line-height: 45px;
            color: var(--text-dark);
            margin: 0 8px;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--accent);
            color: var(--white);
            transform: translateY(-3px);
        }
        
        .floating-action {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .floating-btn {
            width: 65px;
            height: 65px;
            background: linear-gradient(45deg, var(--accent), #ff9f43);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(255, 107, 0, 0.4);
            transition: all 0.3s ease;
            text-decoration: none;
            border: 2px solid var(--white);
        }
        
        .floating-btn:hover {
            transform: scale(1.1) rotate(15deg);
            color: white;
        }
        
        .animate-fade-in { animation: fadeIn 1s ease-in; }
        .animate-slide-up { animation: slideUp 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .logo { font-size: 3.5rem; }
            .logo span { font-size: 1.5rem; }
            .btn-accent, .btn-outline-primary { 
                display: block; 
                margin: 15px auto; 
                width: 90%; 
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm ">
        <div class="container">
            <a class="navbar-brand" href="index.php">TRỌNG TÀI SỐ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                <a class="nav-link active" href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
                <a class="nav-link" href="tournament_list.php"><i class="fas fa-trophy"></i> Giải đấu</a>
                <a class="nav-link" href="matches.php"><i class="fas fa-table-tennis-paddle-ball"></i> Trận đấu</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-12 hero-content animate-fade-in">
                    <h1 class="logo">TRỌNG TÀI SỐ</h1>
                    <p class="tagline">Đam Mê - Tốc Độ - Kết Nối Cộng Đồng</p>
                    <div class="cta-buttons animate-slide-up">
                        <a href="draw.php" class="btn btn-accent">
                            <i class="fas fa-trophy me-2"></i>TẠO GIẢI ĐẤU
                        </a>
                        <a href="matches.php" class="btn btn-outline-primary">
                            <i class="fas fa-basketball-ball me-2"></i>XEM TRẬN ĐẤU
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Section -->
    <section class="dashboard-section">
        <div class="container">
            <h2 class="section-title">QUẢN LÝ GIẢI ĐẤU</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <a href="draw.php" class="text-decoration-none">
                        <div class="dashboard-card animate-slide-up">
                            <div class="dashboard-icon">
                                <i class="fas fa-random"></i>
                            </div>
                            <h4 class="dashboard-title">BỐC THĂM</h4>
                            <p class="text-muted">Chia bảng, tạo lịch thi đấu tự động</p>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="matches.php" class="text-decoration-none">
                        <div class="dashboard-card animate-slide-up" style="animation-delay: 0.2s">
                            <div class="dashboard-icon">
                                <i class="fas fa-basketball-ball"></i>
                            </div>
                            <h4 class="dashboard-title">QUẢN LÝ TRẬN</h4>
                            <p class="text-muted">Cập nhật kết quả, tính bảng xếp hạng</p>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="admin.php" class="text-decoration-none">
                        <div class="dashboard-card animate-slide-up" style="animation-delay: 0.4s">
                            <div class="dashboard-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h4 class="dashboard-title">QUẢN TRỊ VIÊN</h4>
                            <p class="text-muted">Quản lý toàn bộ hệ thống giải đấu</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="teamCount">0</div>
                    <div class="stat-label">Đội Tham Gia</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="groupCount">0</div>
                    <div class="stat-label">Bảng Đấu</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="matchCount">0</div>
                    <div class="stat-label">Trận Đấu</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Tự Động Hóa</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-3" style="color: var(--accent); font-weight: 800; font-family: 'Montserrat', sans-serif;">TRỌNG TÀI SỐ</h4>
                    <p class="text-muted">Hệ thống quản lý giải đấu pickleball chuyên nghiệp</p>
                    
                    <div class="social-links mt-4">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                        <a href="mailto:admin@trongtaiso.com" class="social-link"><i class="fas fa-envelope"></i></a>
                    </div>
                    
                    <p class="mt-4 mb-0 text-muted small">&copy; 2026 TRỌNG TÀI SỐ. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <a href="draw.php" class="floating-btn" title="Bốc thăm ngay">
            <i class="fas fa-random"></i>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Counter animation for stats
        function animateCounter(element, target, duration = 2000) {
            let start = 0;
            const increment = target / (duration / 16);
            const timer = setInterval(() => {
                start += increment;
                if (start >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(start);
                }
            }, 16);
        }

        // Fetch stats via AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Simulate stats (in real app, fetch from API)
            setTimeout(() => {
                animateCounter(document.getElementById('teamCount'), 32);
                animateCounter(document.getElementById('groupCount'), 4);
                animateCounter(document.getElementById('matchCount'), 48);
            }, 1000);

            // Animation on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'slideUp 0.8s ease-out forwards';
                        entry.target.style.opacity = '1';
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.dashboard-card').forEach(el => {
                el.style.opacity = '0';
                observer.observe(el);
            });

            // Smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>