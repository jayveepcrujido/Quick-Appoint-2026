<?php
session_start();
include 'conn.php';
require 'send_reset_email.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    $query = "SELECT id, email, role FROM auth WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(50));

        $update = $pdo->prepare("UPDATE auth SET reset_token = :token WHERE email = :email");
        $update->execute(['token' => $token, 'email' => $email]);

        $resetLink = "http://localhost/capstone-2/capstone/reset_password.php?token=$token";

        $result = sendResetEmail($email, $email, $resetLink); 

        if ($result === true) {
            $message = "<div class='alert success'>A reset link has been sent to <strong>$email</strong>. Please check your inbox.</div>";
        } else {
            $message = "<div class='alert error'>Failed to send email: $result</div>";
        }
    } else {
        $message = "<div class='alert error'>No account found with that email.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #0d94f4bc, #27548ac3);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
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
            z-index: -1;
        }

        .container {
            background: #fff;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 12px;
            max-width: 450px;
            width: 100%;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 14px;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            color: #2c3e50;
            font-weight: 500;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            background: linear-gradient(to right, #0D92F4, #27548A);
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            margin-top: 20px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            background: linear-gradient(to right, #27548A, #0D92F4);
        }

        button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 25px;
            }

            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Your Password?</h2>
    <p class="subtitle">Enter your email to receive a password reset link</p>

    <?= $message ?>

    <form method="POST">
        <label for="email">Email Address</label>
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
        <button type="submit">Send Reset Link</button>
    </form>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
</div>
</body>
</html>