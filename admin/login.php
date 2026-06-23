<?php
require_once '../db_connect.php';
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin  = $result->fetch_assoc();
        $stmt->close();

        // DEBUG - remove after fixing
        echo "Found user: " . ($admin ? $admin['username'] : 'NONE') . "<br>";
        echo "Password received: [" . $password . "]<br>";
        echo "Hash in DB: " . $admin['password_hash'] . "<br>";
        echo "Verify: " . ($admin ? (password_verify($password, $admin['password_hash']) ? 'TRUE' : 'FALSE') : 'N/A') . "<br>";
        exit;

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Login | Premium Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: radial-gradient(ellipse at center, #1a1a1a 0%, #0a0a0a 100%);
        }

        .login-box {
            background: #111;
            border: 1px solid var(--gold);
            border-radius: 12px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 0 60px rgba(212, 175, 55, 0.08);
            animation: fadeInUp 0.6s ease-out;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .login-logo span {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--gold);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .login-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 2.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--gold);
            font-size: 0.8rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            background: #0a0a0a;
            border: 1px solid #333;
            border-radius: 6px;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 0.9rem;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
            font-family: 'Inter', sans-serif;
            margin-top: 0.5rem;
        }

        .btn-login:hover  { 
            background: var(--gold-hover);
            transform: scale(1.03);
        }
        .btn-login:active { 
            transform: scale(0.98); 
        }

        .error-msg {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #e74c3c;
            color: #e74c3c;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .back-link:hover { color: var(--gold); }

        .divider {
            border: none;
            border-top: 1px solid #222;
            margin: 2rem 0 1.5rem;
        }

        /* Loading bar */
        .loading-bar {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            background: var(--gold);
            width: 0%;
            transition: width 0.4s ease;
            z-index: 9999;
        }
    </style>
</head>
<body>
<div class="loading-bar" id="loading-bar"></div>

<div class="login-wrapper">
    <div class="login-box">
        <div class="login-logo">
            <span>Premium Auto</span>
        </div>
        <p class="login-subtitle">Dealer Portal</p>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn-login" id="login-btn">Sign In</button>
        </form>

        <hr class="divider">
        <a href="../index.php" class="back-link">← Back to showroom</a>
    </div>
</div>

<script>
    document.getElementById('login-form').addEventListener('submit', function() {
        const bar = document.getElementById('loading-bar');
        const btn = document.getElementById('login-btn');
        btn.textContent = 'Signing in...';
        btn.disabled = true;
        let w = 0;
        const iv = setInterval(() => {
            w += Math.random() * 15;
            if (w > 85) w = 85;
            bar.style.width = w + '%';
        }, 200);
    });
</script>
</body>
</html>
