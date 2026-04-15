<?php
session_start();
require '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill username and password.";
    } else {
        // Search by username OR email
        $stmt = $conn->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['full_name']= $user['full_name'];

                if ($user['role'] === 'admin') {
                    header("Location: ../admin/dashboard.php");
                } elseif ($user['role'] === 'staff') {
                    header("Location: ../user/dashboard.php");
                } elseif ($user['role'] === 'subscriber') {
                    header("Location: index.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}
// All redirects done — safe to output HTML now
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - TeacherPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            color: #333;
        }

        .login-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        .login-wrapper h1 {
            font-size: 1.5rem;
            font-weight: 400;
            color: #333;
            margin-bottom: 25px;
        }

        .login-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 30px 35px 35px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 2px;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            border-color: #aaa;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            font-size: 1rem;
            user-select: none;
            line-height: 1;
        }

        /* reCAPTCHA */
        .recaptcha-mock {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 14px 16px;
            background: #f9f9f9;
            width: 300px;
            margin-bottom: 18px;
            gap: 12px;
        }

        .recaptcha-mock input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .recaptcha-mock .recaptcha-label {
            font-size: 0.95rem;
            color: #333;
            flex: 1;
        }

        .recaptcha-mock .recaptcha-links {
            font-size: 0.6rem;
            color: #999;
            text-align: center;
            line-height: 1.4;
        }

        .login-bottom-row {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 14px;
        }

        .btn-login {
            background: #e8651a;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: background 0.2s;
            text-transform: uppercase;
        }

        .btn-login:hover {
            background: #c8550e;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #333;
            cursor: pointer;
        }

        .remember-label input[type="checkbox"] {
            width: 15px;
            height: 15px;
        }

        .forgot-link {
            display: block;
            color: #2a6496;
            font-size: 0.9rem;
            text-decoration: none;
            margin-top: 4px;
        }

        .forgot-link:hover {
            text-decoration: underline;
            color: #e8651a;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <h1>Login</h1>

        <div class="login-box">
            <?php if ($error): ?>
                <div class="alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label>Username or email address <span class="required">*</span></label>
                    <input type="text" name="username" required
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required>
                        <span class="toggle-password" onclick="togglePassword()">&#128065;</span>
                    </div>
                </div>

                <!-- reCAPTCHA mock -->
                <div class="recaptcha-mock">
                    <input type="checkbox" id="recaptcha">
                    <label class="recaptcha-label" for="recaptcha">I'm not a robot</label>
                    <div>
                        <svg width="32" height="32" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto 3px;">
                            <circle cx="32" cy="32" r="30" fill="#4A90D9"/>
                            <path d="M32 10 C20 10 12 20 12 32 C12 44 20 54 32 54 C44 54 52 44 52 32" fill="none" stroke="white" stroke-width="5" stroke-linecap="round"/>
                            <path d="M44 14 L52 32 L36 28 Z" fill="white"/>
                        </svg>
                        <div class="recaptcha-links">reCAPTCHA<br>Privacy - Terms</div>
                    </div>
                </div>

                <div class="login-bottom-row">
                    <button type="submit" class="btn-login">LOG IN</button>
                    <label class="remember-label">
                        <input type="checkbox" name="remember" id="remember">
                        Remember me
                    </label>
                </div>

                <a href="forgot-password.php" class="forgot-link">Lost your password?</a>
            </form>
        </div>
    </div>

    <script>
    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = pw.nextElementSibling;
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.textContent = '🙈';
        } else {
            pw.type = 'password';
            icon.textContent = '👁';
        }
    }
    </script>
</body>
</html>