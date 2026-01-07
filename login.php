<?php
session_start();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT id AS auth_id, email, password, role 
              FROM auth 
              WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['email' => $email]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($auth) {
        $dbPassword = $auth['password'];
        $isPasswordValid = password_verify($password, $dbPassword) || $password === $dbPassword;

        if ($isPasswordValid) {
            if ($password === $dbPassword) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE auth SET password = :hashedPassword WHERE id = :id")
                    ->execute(['hashedPassword' => $hashedPassword, 'id' => $auth['auth_id']]);
            }

            $user = null;
            switch ($auth['role']) {
                case 'Admin':
                    $sql = "SELECT first_name, last_name 
                            FROM admins 
                            WHERE auth_id = :auth_id";
                    break;

                case 'LGU Personnel':
                    $sql = "SELECT first_name, last_name, department_id 
                            FROM lgu_personnel 
                            WHERE auth_id = :auth_id";
                    break;

                case 'Resident':
                    $sql = "SELECT first_name, last_name 
                            FROM residents 
                            WHERE auth_id = :auth_id";
                    break;

                default:
                    echo "<script>alert('Unknown role. Contact system admin.');</script>";
                    exit();
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['auth_id' => $auth['auth_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['auth_id'] = $auth['auth_id'];
                $_SESSION['role'] = $auth['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

                if ($auth['role'] === 'LGU Personnel') {
                    $_SESSION['department_id'] = $user['department_id'];
                }

                switch ($auth['role']) {
                    case 'Admin':
                        header('Location: admin/admin_dashboard.php');
                        break;
                    case 'LGU Personnel':
                        header('Location: personnel/personnel_dashboard.php');
                        break;
                    case 'Resident':
                        header('Location: resident/residents_dashboard.php');
                        break;
                }
                exit();
            } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Profile Not Found',
                    text: 'We couldn\\'t locate a profile for this account.',
                    confirmButtonText: 'Okay',
                    confirmButtonColor: '#f39c12',
                    customClass: {
                        popup: 'swal-popup',
                        confirmButton: 'swal-confirm'
                    }
                });
            });
            </script>";
            }
        } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Password',
                    text: 'Please try again.',
                    confirmButtonText: 'Okay',
                    confirmButtonColor: '#e74c3c',
                    customClass: {
                        popup: 'swal-popup',
                        confirmButton: 'swal-confirm'
                    }
                });
            });
            </script>";
        }
    } else {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'info',
                title: 'Account Not Found',
                text: 'Please register first or check your email.',
                confirmButtonText: 'Got it',
                confirmButtonColor: '#3498db',
                customClass: {
                    popup: 'swal-popup',
                    confirmButton: 'swal-confirm'
                }
            });
        });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LGU QuickAppoint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(to right, #0d94f4bc, #27548ac3);
            position: relative;
            overflow: hidden;
            padding: 20px;
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

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 70%;
            left: 80%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 40%;
            left: 5%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-30px) rotate(180deg);
            }
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 480px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 45px 45px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            animation: slideUp 0.6s ease-out;
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

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            position: relative;
            display: inline-block;
            margin-bottom: 13px;
        }

        .login-logo img {
            width: 80px;
            height: 80px;
            transition: transform 0.3s ease;
        }

        .login-logo:hover img {
            transform: scale(1.05);
        }

        .login-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: #27548A;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            font-size: 14px;
            color: #6c757d;
            font-weight: 400;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #6c757d;
            font-size: 16px;
            pointer-events: none;
            z-index: 1;
        }

        .form-control {
            width: 100%;
            height: 52px;
            padding: 14px 16px 14px 45px;
            font-size: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background-color: #f9fafb;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .show-password {
            position: absolute;
            right: 16px;
            cursor: pointer;
            color: #6c757d;
            font-size: 18px;
            transition: color 0.3s ease;
            z-index: 2;
            background: none;
            border: none;
            padding: 8px;
        }

        .show-password:hover {
            color: #27548A;
        }

        .btn-primary {
            width: 100%;
            height: 52px;
            font-size: 16px;
            font-weight: 600;
            background: linear-gradient(to right, #0D92F4, #27548A);
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            margin-top: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            background: linear-gradient(to right, #27548A, #0D92F4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: #5568d3;
            text-decoration: none;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 15px 0;
            color: #9ca3af;
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }

        .divider span {
            padding: 0 12px;
        }

        .register-link {
            display: block;
            margin-top: 12px;
            font-size: 14px;
            color: #6c757d;
        }

        .register-link a {
            color: #667eea;
            font-weight: 600;
        }

        /* SweetAlert Custom Styling */
        .swal-popup {
            border-radius: 16px !important;
            padding: 20px !important;
        }

        .swal-confirm {
            border-radius: 8px !important;
            padding: 10px 24px !important;
            font-weight: 600 !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 40px 30px;
                border-radius: 20px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .login-header p {
                font-size: 14px;
            }

            .form-control {
                height: 48px;
                font-size: 14px;
            }

            .btn-primary {
                height: 48px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .login-container {
                padding: 35px 25px;
                border-radius: 18px;
            }

            .login-logo img {
                width: 75px;
                height: 75px;
            }

            .login-header h2 {
                font-size: 22px;
            }

            .form-control {
                height: 46px;
                padding-left: 42px;
            }

            .input-icon {
                font-size: 14px;
                left: 14px;
            }

            .btn-primary {
                height: 46px;
            }
        }

        /* Loading State */
        .btn-primary.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-primary.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="LGU Logo">
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to LGU QuickAppoint</p>
            </div>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="show-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <a href="forgot_password.php">Forgot your password?</a>
                <div class="divider">
                    <span>or</span>
                </div>
                <div class="register-link">
                    Don't have an account? <a href="register.php">Create one now</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.textContent = 'Signing in...';
        });

        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>