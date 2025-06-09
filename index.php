<?php
require_once 'functions.php';

$message = '';
$message_type = '';
$show_verification = false;

// Handle back button action
if (isset($_GET['action']) && $_GET['action'] === 'back') {
    $show_verification = false;
    session_start();
    unset($_SESSION['email']);
    header('Location: /');
    exit;
}

// Add unsubscribe handling
if (isset($_POST['action']) && $_POST['action'] === 'unsubscribe' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (unregisterEmail($email)) {
        $_SESSION['message'] = "Successfully unsubscribed from GitHub timeline updates!";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Email not found in our subscription list.";
        $_SESSION['message_type'] = 'error';
    }
    header('Location: /');
    exit;
}

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? '';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && !isset($_POST['verification_code'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $message_type = 'error';
        } else {
            $code = generateVerificationCode();
            $codeDir = __DIR__ . '/codes/';
            if (!is_dir($codeDir)) {
                mkdir($codeDir, 0777, true);
            }
            
            if (file_put_contents($codeDir . "{$email}.txt", $code) === false) {
                $message = "Failed to save verification code. Please try again.";
                $message_type = 'error';
            } else if (sendVerificationEmail($email, $code)) {
                $message = "Verification code sent to your email.";
                $message_type = 'success';
                $show_verification = true;
                session_start();
                $_SESSION['email'] = $email;
                error_log("Verification code $code sent to $email");
            } else {
                $message = "Failed to send verification code. Please try again.";
                $message_type = 'error';
                error_log("Failed to send verification code to $email");
            }
        }
    }

    if (isset($_POST['verification_code']) && isset($_POST['email']) && isset($_POST['github_username'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $code = trim($_POST['verification_code']);
        $github_username = trim($_POST['github_username']);
        $show_verification = true;

        error_log("Verifying code for $email: $code");

        // First validate GitHub username
        if (!validateGitHubUsername($github_username)) {
            $message = "Invalid GitHub username. Please check and try again.";
            $message_type = 'error';
            error_log("Invalid GitHub username: $github_username");
        } else if (!verifyCode($email, $code)) {
            $message = "Invalid verification code.";
            $message_type = 'error';
            error_log("Invalid verification code for $email");
        } else {
            if (registerEmail($email, $github_username)) {
                $message = "Email successfully verified and registered! You'll start receiving GitHub timeline updates.";
                $message_type = 'success';
                $show_verification = false;
                session_start();
                unset($_SESSION['email']);
                error_log("Successfully registered $email with GitHub username $github_username");
            } else {
                $message = "This email is already registered.";
                $message_type = 'error';
                error_log("Email already registered: $email");
            }
        }
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get email from session if exists
$stored_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$show_verification = $show_verification || !empty($stored_email);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Timeline Updates</title>
    <style>
        :root {
            --primary-color: #2ea44f;
            --primary-hover: #2c974b;
            --text-color: #c9d1d9;
            --bg-color: #0d1117;
            --border-color: rgba(46, 164, 79, 0.2);
            --neon-glow: 0 0 10px rgba(46, 164, 79, 0.5),
                        0 0 20px rgba(46, 164, 79, 0.3),
                        0 0 30px rgba(46, 164, 79, 0.1);
            --scan-line-color: rgba(46, 164, 79, 0.1);
        }

        @keyframes matrixRain {
            0% {
                transform: translateY(-100%);
            }
            100% {
                transform: translateY(100vh);
            }
        }

        @keyframes scanline {
            0% {
                transform: translateY(-100%);
            }
            100% {
                transform: translateY(100%);
            }
        }

        @keyframes glitch {
            0% {
                clip-path: inset(50% 0 40% 0);
                transform: translate(-5px, 5px);
            }
            5% {
                clip-path: inset(20% 0 60% 0);
                transform: translate(5px, -5px);
            }
            10% {
                clip-path: inset(60% 0 20% 0);
                transform: translate(-5px, 5px);
            }
            15% {
                clip-path: inset(40% 0 40% 0);
                transform: translate(5px, -5px);
            }
            20% {
                clip-path: inset(20% 0 60% 0);
                transform: translate(-5px, 5px);
            }
            25% {
                clip-path: inset(60% 0 20% 0);
                transform: translate(5px, -5px);
            }
            100% {
                clip-path: inset(50% 0 40% 0);
                transform: translate(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            position: relative;
            overflow: hidden;
        }

        /* Matrix rain effect */
        .matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.3;
            pointer-events: none;
        }

        .matrix-column {
            position: absolute;
            width: 20px;
            top: 0;
            animation: matrixRain 20s linear infinite;
            color: var(--primary-color);
            text-shadow: 0 0 5px var(--primary-color);
            font-family: monospace;
            white-space: nowrap;
            opacity: 0.5;
        }

        /* Scan line effect */
        .scan-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                0deg,
                var(--scan-line-color) 0px,
                var(--scan-line-color) 1px,
                transparent 1px,
                transparent 2px
            );
            pointer-events: none;
            z-index: 1;
        }

        .scan-line {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: linear-gradient(
                to bottom,
                transparent,
                var(--primary-color),
                transparent
            );
            opacity: 0.1;
            animation: scanline 8s linear infinite;
            pointer-events: none;
            z-index: 2;
        }

        .cyber-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(90deg, rgba(0, 168, 255, 0.1) 1px, transparent 1px),
                linear-gradient(rgba(0, 168, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 0;
            opacity: 0.3;
            pointer-events: none;
            animation: float 10s ease-in-out infinite;
        }

        .container {
            width: 90%;
            max-width: 500px;
            background: linear-gradient(145deg, rgba(13, 17, 23, 0.95), rgba(13, 17, 23, 0.85));
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 24px;
            position: relative;
            z-index: 3;
            box-shadow: var(--neon-glow);
            border: 1px solid var(--border-color);
            animation: float 6s ease-in-out infinite;
        }

        .container::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            border-radius: 24px;
            padding: 1px;
            background: linear-gradient(145deg, rgba(0, 168, 255, 0.3), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* Glitch effect for title */
        h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 1rem;
            text-shadow: var(--neon-glow);
            position: relative;
        }

        h1::before,
        h1::after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-color);
        }

        h1::before {
            left: 2px;
            text-shadow: -2px 0 var(--primary-color);
            animation: glitch 3s infinite linear alternate-reverse;
        }

        h1::after {
            left: -2px;
            text-shadow: 2px 0 var(--primary-color);
            animation: glitch 2s infinite linear alternate-reverse;
        }

        p {
            text-align: center;
            color: rgba(226, 232, 240, 0.8);
            margin-bottom: 2rem;
        }

        .form-section {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .form-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .form-section h2 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }

        .input-group {
            margin-bottom: 1.25rem;
        }

        input {
            width: 100%;
            padding: 1rem;
            background: rgba(10, 25, 47, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--neon-glow);
        }

        input::placeholder {
            color: rgba(226, 232, 240, 0.5);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-primary, .btn-secondary {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--primary-color);
            color: white;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            flex: 1;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-hover));
            box-shadow: var(--neon-glow);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--neon-glow);
        }

        .message {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            background: rgba(10, 25, 47, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .message.error {
            border-color: #ef4444;
            color: #ef4444;
        }

        .message.success {
            border-color: #22c55e;
            color: #22c55e;
        }

        @media (max-width: 640px) {
            .container {
                width: 95%;
                padding: 1.5rem;
            }
        }

        .nav-links {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .nav-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            opacity: 0.8;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            opacity: 1;
            text-shadow: var(--neon-glow);
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 1px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary-color);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }

        .nav-links a:hover::before {
            transform: scaleX(1);
            transform-origin: left;
        }

        #unsubscribe-section {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        #unsubscribe-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .unsubscribe-warning {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #ef4444;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="matrix-bg"></div>
    <div class="scan-lines"></div>
    <div class="scan-line"></div>
    <div class="cyber-grid"></div>
    
    <div class="container">
        <h1 data-text="GitHub Timeline Updates">GitHub Timeline Updates</h1>
        <p>Get real-time updates about your GitHub timeline delivered to your inbox!</p>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div id="email-section" class="form-section <?= !$show_verification ? 'active' : '' ?>">
            <h2>🔔 Enter your email</h2>
            <form method="POST" id="email-form">
                <div class="input-group">
                    <input type="email" name="email" required 
                           placeholder="your.email@example.com"
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address">
                </div>
                <button type="submit" class="btn-primary">
                    Get Verification Code
                </button>
            </form>
        </div>

        <div id="verification-section" class="form-section <?= $show_verification ? 'active' : '' ?>">
            <h2>🔐 Verify your email</h2>
            <form method="POST" id="verification-form">
                <div class="input-group">
                    <input type="email" name="email" required 
                           value="<?= htmlspecialchars($stored_email) ?>"
                           readonly
                           placeholder="Confirm your email">
                </div>
                <div class="input-group">
                    <input type="text" name="github_username" 
                           required placeholder="Enter your GitHub username"
                           pattern="[a-zA-Z0-9-]+"
                           title="Please enter a valid GitHub username (letters, numbers, and hyphens only)"
                           autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="text" name="verification_code" 
                           required placeholder="Enter 6-digit code"
                           pattern="[0-9]{6}" maxlength="6"
                           title="Please enter the 6-digit verification code"
                           autocomplete="off">
                </div>
                <div class="button-group">
                    <a href="/?action=back" class="btn-secondary">
                        ← Back
                    </a>
                    <button type="submit" class="btn-primary">
                        Verify & Subscribe
                    </button>
                </div>
            </form>
        </div>

        <div id="unsubscribe-section" class="form-section">
            <h2>🚫 Unsubscribe</h2>
            <div class="unsubscribe-warning">
                Warning: This action cannot be undone. You will stop receiving GitHub timeline updates.
            </div>
            <form method="POST" id="unsubscribe-form">
                <div class="input-group">
                    <input type="email" name="email" required 
                           placeholder="Enter your subscribed email"
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address">
                </div>
                <input type="hidden" name="action" value="unsubscribe">
                <div class="button-group">
                    <a href="/" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        Confirm Unsubscribe
                    </button>
                </div>
            </form>
        </div>

        <div class="nav-links">
            <a href="#" onclick="toggleUnsubscribe(true); return false;">Want to unsubscribe?</a>
        </div>
    </div>

    <script>
        // Matrix rain effect
        function createMatrixRain() {
            const matrixBg = document.querySelector('.matrix-bg');
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%^&*';
            const columns = Math.floor(window.innerWidth / 20);

            for (let i = 0; i < columns; i++) {
                const column = document.createElement('div');
                column.className = 'matrix-column';
                column.style.left = i * 20 + 'px';
                column.style.animationDelay = Math.random() * 20 + 's';

                let str = '';
                for (let j = 0; j < 50; j++) {
                    str += characters[Math.floor(Math.random() * characters.length)] + '<br>';
                }
                column.innerHTML = str;
                matrixBg.appendChild(column);
            }
        }

        // Initialize matrix rain
        createMatrixRain();

        // Recreate matrix rain on window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const matrixBg = document.querySelector('.matrix-bg');
                matrixBg.innerHTML = '';
                createMatrixRain();
            }, 250);
        });

        // Toggle unsubscribe section
        function toggleUnsubscribe(show) {
            if (show) {
                const sections = ['email-section', 'verification-section', 'unsubscribe-section'];
                sections.forEach(section => {
                    const el = document.getElementById(section);
                    if (section === 'unsubscribe-section') {
                        el.classList.toggle('active', show);
                    } else {
                        el.classList.toggle('active', !show);
                    }
                });
            } else {
                window.location.href = '/';
            }
        }

        // Add loading state to buttons and validate GitHub username
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const button = e.target.querySelector('.btn-primary');
                const githubUsername = e.target.querySelector('input[name="github_username"]');
                
                if (githubUsername && !githubUsername.value.match(/^[a-zA-Z0-9-]+$/)) {
                    e.preventDefault();
                    alert('Please enter a valid GitHub username (only letters, numbers, and hyphens allowed)');
                    return;
                }
                
                button.disabled = true;
                button.innerHTML = 'Processing...';
            });
        });

        // Auto-focus on GitHub username or verification code input when shown
        if (document.querySelector('#verification-section.active')) {
            const githubInput = document.querySelector('input[name="github_username"]');
            const codeInput = document.querySelector('input[name="verification_code"]');
            if (githubInput && !githubInput.value) {
                githubInput.focus();
            } else if (codeInput && !codeInput.value) {
                codeInput.focus();
            }
        }
    </script>
</body>
</html>