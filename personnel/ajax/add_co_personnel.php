<?php
include '../../conn.php';
include '../../send_reset_email.php'; // ADD THIS LINE
session_start();

header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_SESSION['is_department_head']) || !$_SESSION['is_department_head']) {
    echo json_encode(['success' => false, 'message' => 'Only department heads can add co-personnel']);
    exit();
}

try {
    // Validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        throw new Exception('All required fields must be filled');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Sanitize inputs
    $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // Check if email already exists
    $check_email = $pdo->prepare("SELECT id FROM auth WHERE email = ?");
    $check_email->execute([$email]);
    if ($check_email->fetch()) {
        throw new Exception('Email address already exists');
    }
    
    // Get department info and department head name
    $personnel_id = $_SESSION['personnel_id'];
    $dept_info = $pdo->prepare("
        SELECT lp.department_id, d.name as dept_name, 
               lp.first_name as head_first_name, lp.last_name as head_last_name
        FROM lgu_personnel lp
        JOIN departments d ON lp.department_id = d.id
        WHERE lp.id = ?
    ");
    $dept_info->execute([$personnel_id]);
    $dept_data = $dept_info->fetch(PDO::FETCH_ASSOC);
    
    if (!$dept_data) {
        throw new Exception('Department not found');
    }
    
    $department_id = $dept_data['department_id'];
    $department_name = $dept_data['dept_name'];
    $dept_head_name = $dept_data['head_first_name'] . ' ' . $dept_data['head_last_name'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into auth table
    $auth_stmt = $pdo->prepare("
        INSERT INTO auth (email, password, role) 
        VALUES (?, ?, 'LGU Personnel')
    ");
    $auth_stmt->execute([$email, $hashed_password]);
    $auth_id = $pdo->lastInsertId();
    
    // Insert into lgu_personnel table
    $personnel_stmt = $pdo->prepare("
        INSERT INTO lgu_personnel 
        (auth_id, first_name, middle_name, last_name, department_id, is_department_head, created_by_personnel_id) 
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ");
    $personnel_stmt->execute([
        $auth_id,
        $first_name,
        $middle_name,
        $last_name,
        $department_id,
        $personnel_id
    ]);
    
    $pdo->commit();
    
    // ============================================
    // SEND WELCOME EMAIL TO CO-PERSONNEL
    // ============================================
    $fullName = trim("$first_name " . ($middle_name ? "$middle_name " : "") . "$last_name");
    
    // Get the actual domain
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'];
    $loginLink = $protocol . "://" . $domain . "/login.php";
    
    // Or use fixed link:
    // $loginLink = "https://yourdomain.com/login.php";
    
    $emailSent = sendCoPersonnelWelcomeEmail($email, $fullName, [
        'email' => $email,
        'password' => $password,
        'department_name' => $department_name,
        'created_by' => $dept_head_name,
        'login_link' => $loginLink
    ]);
    
    // Prepare success message
    $message = 'Co-personnel created successfully!';
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
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>