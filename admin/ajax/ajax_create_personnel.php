<?php
session_start();
include '../../conn.php';
include '../../send_reset_email.php'; // ADD THIS LINE

header('Content-Type: application/json');

try {
    // Security check - only admins can create personnel
    if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only admins can create personnel.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    // Get inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $department_name = trim($_POST['department_name'] ?? ''); // ADD THIS LINE
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_department_head = isset($_POST['is_department_head']) ? 1 : 0;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    if ($department_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid department.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    // Sanitize inputs
    $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // Check duplicate email
    $check = $pdo->prepare("SELECT id FROM auth WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
        exit;
    }

    if ($is_department_head) {
        $check_head = $pdo->prepare("
            SELECT id FROM lgu_personnel 
            WHERE department_id = ? AND is_department_head = 1
        ");
        $check_head->execute([$department_id]);
        if ($check_head->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This department already has a department head. Please remove the existing head first.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    // Insert into auth first
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO auth (email, password, role) VALUES (?, ?, 'LGU Personnel')");
    $stmt->execute([$email, $hashedPassword]);
    $authId = $pdo->lastInsertId();

    // Insert into lgu_personnel
    $stmt = $pdo->prepare("
        INSERT INTO lgu_personnel 
        (auth_id, first_name, middle_name, last_name, department_id, is_department_head, created_by_personnel_id)
        VALUES (?, ?, ?, ?, ?, ?, NULL)
    ");
    $stmt->execute([
        $authId, 
        $first_name, 
        $middle_name, 
        $last_name, 
        $department_id,
        $is_department_head
    ]);

    $pdo->commit();
    
    // ============================================
    // SEND WELCOME EMAIL - ADD THIS SECTION
    // ============================================
    $fullName = trim("$first_name " . ($middle_name ? "$middle_name " : "") . "$last_name");
    
    // Get the actual domain (change this to your actual domain)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'];
    $loginLink = $protocol . "://" . $domain . "/login.php"; // Adjust path if needed
    
    // Or use a fixed link:
    // $loginLink = "https://yourdomain.com/login.php";
    
    $emailSent = sendPersonnelWelcomeEmail($email, $fullName, [
        'email' => $email,
        'password' => $password, // Plain password (only sent once)
        'department_name' => $department_name ?: 'Not Assigned',
        'is_department_head' => $is_department_head,
        'login_link' => $loginLink
    ]);
    
    // Prepare success message
    $role_text = $is_department_head ? 'Department Head' : 'LGU Personnel';
    $message = $role_text . ' created successfully!';
    
    if (!$emailSent) {
        $message .= ' (Note: Welcome email could not be sent. Please provide credentials manually.)';
    } else {
        $message .= ' A welcome email with login credentials has been sent to ' . $email;
    }
    // ============================================
    
    echo json_encode([
        'success' => true, 
        'message' => $message
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>