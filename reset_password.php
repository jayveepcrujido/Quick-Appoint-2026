<?php
include 'conn.php';

$showForm = false;
$message = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT * FROM auth WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $showForm = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];

            if ($password !== $confirm) {
                $message = "<div class='alert error'>Passwords do not match.</div>";
            } elseif (strlen($password) < 8) {
                $message = "<div class='alert error'>Password must be at least 8 characters long.</div>";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $message = "<div class='alert error'>Password must contain at least one uppercase letter.</div>";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $message = "<div class='alert error'>Password must contain at least one lowercase letter.</div>";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $message = "<div class='alert error'>Password must contain at least one number.</div>";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE auth SET password = ?, reset_token = NULL WHERE reset_token = ?");
                $update->execute([$hashed, $token]);

                $message = "<div class='alert success'><strong>Success!</strong> Password reset complete. <a href='login.php'>Click here to login</a>.</div>";
                $showForm = false;
            }
        }
    } else {
        $message = "<div class='alert error'>Invalid or expired token. Please request a new password reset link.</div>";
    }
} else {
    $message = "<div class='alert error'>No token provided.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LGU Quick Appoint</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0d94f4bc, #27548ac3);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/LGU_Unisan.jpg') no-repeat center center/cover;
            opacity: 0.1;
            z-index: 0;
        }

        .container {
            background: #fff;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .lock-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 4px 15px rgba(13, 146, 244, 0.3);
        }

        .lock-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        h2 {
            color: #2c3e50;
            font-size: 26px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .subtitle {
            color: #7f8c8d;
            font-size: 14px;
        }

        .alert {
            padding: 14px 18px;
            margin-bottom: 25px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .success a {
            color: #155724;
            font-weight: 600;
            text-decoration: underline;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
            display: block;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 13px 45px 13px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        input[type="password"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: #0D92F4;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(13, 146, 244, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #0D92F4;
        }

        .password-requirements {
            background: #f8f9fa;
            border-left: 3px solid #0D92F4;
            padding: 12px 15px;
            margin-top: 15px;
            border-radius: 6px;
            font-size: 13px;
        }

        .password-requirements h4 {
            color: #2c3e50;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .requirement {
            color: #7f8c8d;
            margin: 5px 0;
            display: flex;
            align-items: center;
            transition: color 0.3s ease;
        }

        .requirement.met {
            color: #27ae60;
        }

        .requirement::before {
            content: '○';
            margin-right: 8px;
            font-weight: bold;
        }

        .requirement.met::before {
            content: '✓';
            color: #27ae60;
        }

        button {
            width: 100%;
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            border: none;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 146, 244, 0.3);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 146, 244, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #0D92F4;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #27548A;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 35px 25px;
            }

            h2 {
                font-size: 24px;
            }

            .lock-icon {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 22px;
            }

            input[type="password"] {
                font-size: 14px;
            }

            button {
                padding: 13px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="lock-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1C8.676 1 6 3.676 6 7v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v3H8V7c0-2.276 1.724-4 4-4zm0 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
            </svg>
        </div>
        <h2>Reset Your Password</h2>
        <p class="subtitle">Create a strong, secure password</p>
    </div>

    <?= $message ?>

    <?php if ($showForm): ?>
    <form method="POST" id="resetForm">
        <div class="form-group">
            <label for="password">New Password</label>
            <div class="input-wrapper">
                <input type="password" name="password" id="password" placeholder="Enter new password" required>
                <span class="toggle-password" onclick="togglePassword('password')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <div class="input-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                <span class="toggle-password" onclick="togglePassword('confirm_password')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="password-requirements">
            <h4>Password must contain:</h4>
            <div class="requirement" id="req-length">At least 8 characters</div>
            <div class="requirement" id="req-uppercase">One uppercase letter (A-Z)</div>
            <div class="requirement" id="req-lowercase">One lowercase letter (a-z)</div>
            <div class="requirement" id="req-number">One number (0-9)</div>
        </div>

        <button type="submit" id="submitBtn">Reset Password</button>
    </form>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
    <?php else: ?>
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        const lengthMet = password.length >= 8;
        document.getElementById('req-length').classList.toggle('met', lengthMet);
        
        const uppercaseMet = /[A-Z]/.test(password);
        document.getElementById('req-uppercase').classList.toggle('met', uppercaseMet);
        
        const lowercaseMet = /[a-z]/.test(password);
        document.getElementById('req-lowercase').classList.toggle('met', lowercaseMet);
        
        const numberMet = /[0-9]/.test(password);
        document.getElementById('req-number').classList.toggle('met', numberMet);
        
        const allMet = lengthMet && uppercaseMet && lowercaseMet && numberMet;
        document.getElementById('submitBtn').disabled = !allMet;
    });
}
</script>
</body>
</html>