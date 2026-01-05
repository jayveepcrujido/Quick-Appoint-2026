<?php
session_start();
require_once 'conn.php';
header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'], $_SESSION['role'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
    exit;
}

$auth_id = (int)$_SESSION['auth_id'];
$role    = $_SESSION['role'];

try {
    // Update email in auth if provided
    if (!empty($_POST['email'])) {
        $stmt = $pdo->prepare("UPDATE auth SET email=? WHERE id=?");
        $stmt->execute([$_POST['email'], $auth_id]);
    }

    if ($role === 'Resident') {
        // Construct full address from parts
        $house_number = trim($_POST['house_number'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $province = trim($_POST['province'] ?? '');
        
        $address_parts = array_filter([$house_number, $street, $barangay, $municipality, $province]);
        $address = implode(', ', $address_parts);
        
        // Auto-calculate age from birthday
        $age = null;
        if (!empty($_POST['birthday'])) {
            $birthDate = new DateTime($_POST['birthday']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }
        
        $stmt = $pdo->prepare("UPDATE residents
            SET first_name=?, middle_name=?, last_name=?, birthday=?, age=?, sex=?, civil_status=?, address=?
            WHERE auth_id=?");
        $stmt->execute([
            $_POST['first_name'] ?? null,
            $_POST['middle_name'] ?? null,
            $_POST['last_name'] ?? null,
            $_POST['birthday'] ?? null,
            $age,
            $_POST['sex'] ?? null,
            $_POST['civil_status'] ?? null,
            $address,
            $auth_id
        ]);
    } elseif ($role === 'Admin') {
        $stmt = $pdo->prepare("UPDATE admins
            SET first_name=?, middle_name=?, last_name=?
            WHERE auth_id=?");
        $stmt->execute([
            $_POST['first_name'] ?? null,
            $_POST['middle_name'] ?? null,
            $_POST['last_name'] ?? null,
            $auth_id
        ]);
    } elseif ($role === 'LGU Personnel') {
        $stmt = $pdo->prepare("UPDATE lgu_personnel
            SET first_name=?, middle_name=?, last_name=?
            WHERE auth_id=?");
        $stmt->execute([
            $_POST['first_name'] ?? null,
            $_POST['middle_name'] ?? null,
            $_POST['last_name'] ?? null,
            $auth_id
        ]);
    }

    echo json_encode(['status'=>'success','message'=>'Profile updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
}
?>