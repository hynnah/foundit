<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Lost & Found Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        body {
            background: #000;
            background-image: linear-gradient(135deg, rgba(0,0,0,0.85) 0%, rgba(26,26,26,0.85) 50%, rgba(45,45,45,0.85) 100%), url('resources/founditbg.png');
            background-repeat: repeat;
            background-size: auto;
            background-attachment: local;
            background-position: top left;
            min-height: 100vh;
        }

        /* Landing Page Specific Styles */
        .landing-page {
            min-height: 100vh;
            width: 100vw;
            background: transparent;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .landing-page::before {
            content: none;
        }

        /* Mobile fix: ensure background always covers scrollable area */
        @media (max-width: 768px) {
            body {
                min-height: 100vh;
                background: #000;
                background-image: linear-gradient(135deg, #000000 0%, #1a1a1a 50%, #2d2d2d 100%), url('resources/founditbg.png');
                background-repeat: repeat;
                background-size: auto;
                background-attachment: local;
                background-position: top left;
            }
            .landing-page {
                min-height: 100vh;
            }
            .landing-page::before {
                content: none !important;
            }
            .landing-container {
                padding: 1.5rem;
            }
        }   

        .landing-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            position: relative;
            z-index: 2;
            width: 100%;
        }

        .hero-section {
            text-align: center;
            max-width: 800px;
            margin-bottom: 2rem;
            flex-shrink: 0;
        }

        .hero-logo {
            width: 300px;
            height: auto;
            margin-bottom: 2rem;
            animation: fadeInScale 1.2s ease-out;
            filter: drop-shadow(0 10px 30px rgba(203, 127, 0, 0.3));
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .hero-logo:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 15px 40px rgba(203, 127, 0, 0.5));
        }

        .logo-container {
            position: relative;
            display: inline-block;
        }

        .character-popup {
            position: absolute;
            top: 50%;
            left: 100%;
            transform: translate(20px, -50%) scale(0.8);
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1000;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .hero-logo:hover + .character-popup,
        .logo-container:hover .character-popup {
            opacity: 1;
            visibility: visible;
            transform: translate(10px, -50%) scale(1);
        }

        .dialog-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 12px 16px;
            min-width: 220px;
            max-width: 280px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            border: 2px solid rgba(203, 127, 0, 0.3);
            order: 1;
            margin-bottom: 15px;
        }

        .dialog-box::before {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid rgba(255, 255, 255, 0.95);
        }

        .dialog-text {
            color: #333;
            font-size: 13px;
            line-height: 1.4;
            text-align: left;
            margin: 0;
            min-height: 60px;
        }

        .typing-cursor {
            display: inline-block;
            background-color: #cb7f00;
            width: 2px;
            height: 16px;
            animation: blink 1s infinite;
            margin-left: 1px;
            vertical-align: baseline;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        .character {
            position: relative;
            width: 70px;
            height: 85px;
            margin: 0 auto;
            animation: characterBounce 2s infinite ease-in-out;
            order: 2;
        }

        .character-body {
            width: 45px;
            height: 55px;
            background: linear-gradient(45deg, #cb7f00, #ffb347);
            border-radius: 22px 22px 12px 12px;
            position: relative;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .character-head {
            width: 35px;
            height: 35px;
            background: linear-gradient(45deg, #ffd700, #ffb347);
            border-radius: 50%;
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .character-eyes {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 8px;
        }

        .character-eyes::before,
        .character-eyes::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 6px;
            background: #333;
            border-radius: 50%;
            top: 1px;
            animation: eyeBlink 3s infinite;
        }

        .character-eyes::before {
            left: 2px;
        }

        .character-eyes::after {
            right: 2px;
        }

        .character-mouth {
            position: absolute;
            top: 22px;
            left: 50%;
            transform: translateX(-50%);
            width: 12px;
            height: 6px;
            border: 2px solid #333;
            border-top: none;
            border-radius: 0 0 12px 12px;
        }

        .character-arms {
            position: absolute;
            top: 20px;
            width: 100%;
            height: 20px;
        }

        .character-arms::before,
        .character-arms::after {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            background: linear-gradient(45deg, #cb7f00, #ffb347);
            border-radius: 50%;
            top: 0;
            animation: armWave 2s infinite ease-in-out;
        }

        .character-arms::before {
            left: -10px;
            animation-delay: 0.2s;
        }

        .character-arms::after {
            right: -10px;
            animation-delay: 0.4s;
        }

        .dialog-text {
            color: #333;
            font-size: 13px;
            line-height: 1.4;
            text-align: left;
            margin: 0;
        }

        .dialog-text strong {
            color: #cb7f00;
            font-weight: 600;
        }

        .sparkles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #ffd700;
            border-radius: 50%;
            animation: sparkleFloat 2s infinite ease-in-out;
        }

        .sparkle:nth-child(1) {
            top: 10%;
            left: 20%;
            animation-delay: 0s;
        }

        .sparkle:nth-child(2) {
            top: 30%;
            right: 15%;
            animation-delay: 0.5s;
        }

        .sparkle:nth-child(3) {
            bottom: 20%;
            left: 30%;
            animation-delay: 1s;
        }

        .sparkle:nth-child(4) {
            bottom: 40%;
            right: 25%;
            animation-delay: 1.5s;
        }

        /* Character Animations */
        @keyframes characterBounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        @keyframes eyeBlink {
            0%, 90%, 100% {
                height: 6px;
            }
            95% {
                height: 1px;
            }
        }

        @keyframes armWave {
            0%, 100% {
                transform: rotate(0deg);
            }
            50% {
                transform: rotate(15deg);
            }
        }

        @keyframes sparkleFloat {
            0%, 100% {
                opacity: 0;
                transform: scale(0) rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: scale(1) rotate(180deg);
            }
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #cb7f00, #ffb347, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: #cccccc;
            margin-bottom: 2rem;
            line-height: 1.6;
            animation: fadeInUp 1s ease-out 0.6s both;
        }

        .hero-description {
            font-size: 1.1rem;
            color: #999999;
            margin-bottom: 2rem;
            line-height: 1.8;
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 1.2s both;
        }

        .cta-button {
            display: inline-flex;
            align-items: center;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 180px;
            justify-content: center;
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .cta-button:hover::before {
            left: 100%;
        }

        .cta-primary {
            background: linear-gradient(45deg, #cb7f00, #ffb347);
            color: white;
            box-shadow: 0 8px 25px rgba(203, 127, 0, 0.4);
        }

        .cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(203, 127, 0, 0.6);
        }

        .cta-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 2px solid rgba(203, 127, 0, 0.5);
            backdrop-filter: blur(10px);
        }

        .cta-secondary:hover {
            background: rgba(203, 127, 0, 0.1);
            border-color: #cb7f00;
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(203, 127, 0, 0.3);
        }

        .features-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
            max-width: 900px;
            animation: fadeInUp 1s ease-out 1.5s both;
            width: 100%;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(203, 127, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(203, 127, 0, 0.1) 0%, transparent 70%);
            transform: scale(0);
            transition: transform 0.5s ease;
        }

        .feature-card:hover::before {
            transform: scale(1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(203, 127, 0, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            font-size: 3rem;
            background: linear-gradient(45deg, #cb7f00, #ffb347);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .feature-description {
            font-size: 0.9rem;
            color: #cccccc;
            line-height: 1.5;
            position: relative;
            z-index: 1;
        }

        .floating-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(203, 127, 0, 0.3);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(even) {
            background: rgba(255, 179, 71, 0.2);
            animation-duration: 25s;
        }

        /* Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

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

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Responsive adjustments for character popup */
        @media (max-width: 768px) {
            .landing-container {
                padding: 1.5rem;
            }
            
            .hero-section {
                margin-bottom: 3rem;
            }
            
            .character-popup {
                left: 50%;
                top: 100%;
                transform: translate(-50%, 20px) scale(0.8);
                flex-direction: column;
                gap: 8px;
            }
            
            .hero-logo:hover + .character-popup,
            .logo-container:hover .character-popup {
                transform: translate(-50%, 10px) scale(1);
            }
            
            .dialog-box {
                order: 2;
                margin-bottom: 0;
                margin-top: 10px;
                min-width: 200px;
                max-width: 260px;
            }
            
            .dialog-box::before {
                top: -8px;
                bottom: auto;
                left: 50%;
                transform: translateX(-50%);
                border-left: 8px solid transparent;
                border-right: 8px solid transparent;
                border-bottom: 8px solid rgba(255, 255, 255, 0.95);
                border-top: none;
            }
            
            .character {
                order: 1;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            
            .features-preview {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                margin-top: 3rem;
            }
            
            .hero-logo {
                width: 250px;
                margin-bottom: 3rem;
            }
        }

        @media (max-width: 480px) {
            .landing-container {
                padding: 1rem;
            }
            
            .hero-section {
                margin-bottom: 2rem;
            }
            
            .character-popup {
                left: 50%;
                top: 100%;
                transform: translate(-50%, 15px) scale(0.9);
                flex-direction: column;
                gap: 6px;
            }
            
            .hero-logo:hover + .character-popup,
            .logo-container:hover .character-popup {
                transform: translate(-50%, 8px) scale(1);
            }
            
            .dialog-box {
                order: 2;
                margin-bottom: 0;
                margin-top: 8px;
                min-width: 180px;
                max-width: 220px;
                padding: 10px 12px;
            }
            
            .dialog-text {
                font-size: 12px;
                min-height: 50px;
            }
            
            .character {
                order: 1;
                width: 60px;
                height: 75px;
            }
            
            .character-body {
                width: 38px;
                height: 48px;
            }
            
            .character-head {
                width: 30px;
                height: 30px;
                top: -15px;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-logo {
                width: 200px;
                margin-bottom: 2rem;
            }
            
            .features-preview {
                margin-top: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="landing-page">
        <!-- Floating Particles Background -->
        <div class="floating-particles">
            <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="particle" style="left: 20%; animation-delay: 2s;"></div>
            <div class="particle" style="left: 30%; animation-delay: 4s;"></div>
            <div class="particle" style="left: 40%; animation-delay: 6s;"></div>
            <div class="particle" style="left: 50%; animation-delay: 8s;"></div>
            <div class="particle" style="left: 60%; animation-delay: 10s;"></div>
            <div class="particle" style="left: 70%; animation-delay: 12s;"></div>
            <div class="particle" style="left: 80%; animation-delay: 14s;"></div>
            <div class="particle" style="left: 90%; animation-delay: 16s;"></div>
        </div>

        <div class="landing-container">
            <div class="hero-section">
                <div class="logo-container">
                    <img src="resources/logo.png" alt="FoundIt Logo" class="hero-logo">
                    <div class="character-popup">
                        <div class="dialog-box">
                            <p class="dialog-text" id="dialog-text"></p>
                        </div>
                        <div class="character">
                            <div class="character-body">
                                <div class="character-head">
                                    <div class="character-eyes"></div>
                                    <div class="character-mouth"></div>
                                </div>
                                <div class="character-arms"></div>
                            </div>
                        </div>
                        <div class="sparkles">
                            <div class="sparkle"></div>
                            <div class="sparkle"></div>
                            <div class="sparkle"></div>
                            <div class="sparkle"></div>
                        </div>
                    </div>
                </div>
                
                <div class="cta-buttons">
                    <a href="login.php" class="cta-button cta-primary">
                        <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                        Login
                    </a>
                    <a href="register.php" class="cta-button cta-secondary">
                        <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
                        Register
                    </a>
                </div>
            </div>

            <div class="features-preview">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="feature-title">Search & Find</h3>
                    <p class="feature-description">
                        Easily search through reported items and find what you're looking for
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="feature-title">Report Items</h3>
                    <p class="feature-description">
                        Quickly report lost items with detailed descriptions and photos
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="feature-title">Connect</h3>
                    <p class="feature-description">
                        Secure messaging system to connect with administrators whom facilitates item recovery.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Secure</h3>
                    <p class="feature-description">
                        Admin-moderated system ensures safe and verified item recovery
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scroll behavior for any anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add particle animation on scroll
        window.addEventListener('scroll', function() {
            const particles = document.querySelectorAll('.particle');
            const scrolled = window.pageYOffset;
            
            particles.forEach((particle, index) => {
                const speed = 0.5 + (index * 0.1);
                particle.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });

        // Typewriter effect for dialog
        let typingStarted = false;
        let typingTimeout = null;

        function typeWriter(element, text, speed = 80) {
            let i = 0;
            let displayText = '';
            if (typingTimeout) {
                clearTimeout(typingTimeout);
                typingTimeout = null;
            }
            element.innerHTML = '';

            function type() {
                if (!typingStarted) return; // Stop if typing cancelled
                if (i < text.length) {
                    if (text.charAt(i) === '<') {
                        let tagEnd = text.indexOf('>', i);
                        if (tagEnd !== -1) {
                            displayText += text.substring(i, tagEnd + 1);
                            i = tagEnd + 1;
                        } else {
                            displayText += text.charAt(i);
                            i++;
                        }
                    } else {
                        displayText += text.charAt(i);
                        i++;
                    }
                    element.innerHTML = displayText + '<span class="typing-cursor"></span>';
                    typingTimeout = setTimeout(type, speed);
                } else {
                    element.innerHTML = displayText;
                    typingTimeout = null;
                }
            }
            typingStarted = true;
            type();
        }

        document.querySelector('.logo-container').addEventListener('mouseenter', function() {
            if (!typingStarted) {
                const dialogText = document.getElementById('dialog-text');
                const message = "Hi there! I'm <strong>Findy</strong>, your friendly FoundIt assistant! 🎉 I help reunite people with their lost treasures through our secure platform. <strong>Lost something? Found something?</strong> I've got you covered!";
                typingStarted = true;
                setTimeout(() => {
                    typeWriter(dialogText, message, 60);
                }, 400);
            }
        });

        document.querySelector('.logo-container').addEventListener('mouseleave', function() {
            typingStarted = false;
            if (typingTimeout) {
                clearTimeout(typingTimeout);
                typingTimeout = null;
            }
            setTimeout(() => {
                document.getElementById('dialog-text').innerHTML = '';
            }, 400);
        });
    </script>
</body>
</html>
