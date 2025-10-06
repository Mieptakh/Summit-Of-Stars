<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting... - MHTeams</title>
    <link rel="shortcut icon" href="images\20250320_190104[1].png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="refresh" content="2;url=login.php"> <!-- delay 2 detik -->
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            text-align: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated Background Elements */
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }
        
        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.15);
            bottom: -160px;
            animation: square 25s infinite;
            transition-timing-function: linear;
            border-radius: 50%;
        }
        
        .bg-bubbles li:nth-child(1) {
            left: 10%;
            animation-delay: 0s;
            width: 80px;
            height: 80px;
        }
        
        .bg-bubbles li:nth-child(2) {
            left: 20%;
            animation-delay: 2s;
            animation-duration: 17s;
            width: 60px;
            height: 60px;
        }
        
        .bg-bubbles li:nth-child(3) {
            left: 25%;
            animation-delay: 4s;
            width: 100px;
            height: 100px;
        }
        
        .bg-bubbles li:nth-child(4) {
            left: 40%;
            animation-delay: 0s;
            animation-duration: 22s;
            width: 50px;
            height: 50px;
        }
        
        .bg-bubbles li:nth-child(5) {
            left: 70%;
            animation-delay: 3s;
            width: 70px;
            height: 70px;
        }
        
        .bg-bubbles li:nth-child(6) {
            left: 80%;
            animation-delay: 2s;
            width: 90px;
            height: 90px;
        }
        
        .bg-bubbles li:nth-child(7) {
            left: 32%;
            animation-delay: 4s;
            width: 120px;
            height: 120px;
        }
        
        .bg-bubbles li:nth-child(8) {
            left: 55%;
            animation-delay: 1s;
            animation-duration: 20s;
            width: 40px;
            height: 40px;
        }
        
        .bg-bubbles li:nth-child(9) {
            left: 25%;
            animation-delay: 3s;
            animation-duration: 18s;
            width: 60px;
            height: 60px;
        }
        
        .bg-bubbles li:nth-child(10) {
            left: 90%;
            animation-delay: 5s;
            width: 80px;
            height: 80px;
        }
        
        @keyframes square {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 50%;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 10%;
            }
        }
        
        .redirect-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #fff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        p {
            font-size: 1.1rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .countdown {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 20px 0;
            color: #ffdd00;
        }
        
        .progress-container {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            overflow: hidden;
            margin: 25px 0;
        }
        
        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #ffdd00, #ff4e50);
            border-radius: 4px;
            animation: progress 2s linear forwards;
        }
        
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        .redirect-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-weight: 500;
        }
        
        .redirect-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .spinner:before {
            content: '';
            box-sizing: border-box;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 4px solid transparent;
            border-top-color: #ffdd00;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .redirect-content {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .logo {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }
            
            p {
                font-size: 1rem;
            }
            
            .logo {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Elements -->
    <ul class="bg-bubbles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>
    
    <div class="redirect-content">
        <div class="logo">
            <i class="fas fa-rocket"></i>
        </div>
        
        <div class="spinner"></div>
        
        <h1>Sedang Mengalihkan...</h1>
        
        <div class="countdown" id="countdown">Mengarahkan dalam 2 detik</div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <p>Anda akan diarahkan ke halaman login secara otomatis.</p>
        <p>Mohon tunggu sebentar...</p>
        
        <a href="login.php" class="redirect-link">
            <i class="fas fa-external-link-alt me-2"></i> Klik di sini jika tidak otomatis
        </a>
    </div>

    <script>
        // Countdown timer
        let timeLeft = 2;
        const countdownElement = document.getElementById('countdown');
        
        const updateCountdown = () => {
            countdownElement.textContent = `Mengarahkan dalam ${timeLeft} detik`;
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(countdownInterval);
            }
        };
        
        updateCountdown(); // Initial call
        const countdownInterval = setInterval(updateCountdown, 1000);
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const redirectContent = document.querySelector('.redirect-content');
            
            // Add subtle floating animation
            redirectContent.style.transition = 'transform 0.3s ease';
            
            // Parallax effect on mouse move
            document.addEventListener('mousemove', function(e) {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
                redirectContent.style.transform = `translate(${xAxis}px, ${yAxis}px)`;
            });
        });
    </script>
</body>
</html>