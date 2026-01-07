<?php
session_start();
include 'conn.php';

function compareFaces($image1Path, $image2Path) {
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $address = $_POST['address'];
    $id_type = $_POST['valid_id_type'];
    $role = 'Residents';

    $id_image = $_FILES['valid_id_image'];
    $selfie_image = $_FILES['selfie_image'];

    $upload_dir = 'uploads/';
    $id_path = $upload_dir . 'ids/';
    $selfie_path = $upload_dir . 'selfies/';
    if (!is_dir($id_path)) mkdir($id_path, 0777, true);
    if (!is_dir($selfie_path)) mkdir($selfie_path, 0777, true);

    $id_filename = $id_path . uniqid() . '_' . basename($id_image['name']);
    $selfie_filename = $selfie_path . uniqid() . '_' . basename($selfie_image['name']);

    try {
        move_uploaded_file($id_image['tmp_name'], $id_filename);
        move_uploaded_file($selfie_image['tmp_name'], $selfie_filename);

        $ocr_output = shell_exec("tesseract " . escapeshellarg($id_filename) . " stdout");
        $ocr_output_clean = trim(preg_replace('/\s+/', ' ', $ocr_output));

        $face_match = compareFaces($id_filename, $selfie_filename);
        if (!$face_match) {
            throw new Exception("Face in selfie does not match the ID photo.");
        }

        if (preg_match('/([A-Z]{2,}\s+[A-Z]{2,}\s+[A-Z]{2,})/', $ocr_output_clean, $matches)) {
            $ocr_full_name = $matches[1];
        } else {
            $ocr_full_name = "$first_name $middle_name $last_name";
        }

        $pdo->beginTransaction();

        $userStmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name) VALUES (?, ?, ?)");
        $userStmt->execute([$first_name, $middle_name, $last_name]);
        $user_id = $pdo->lastInsertId();

        $authStmt = $pdo->prepare("INSERT INTO auth (user_id, email, password, role) VALUES (?, ?, ?, ?)");
        $authStmt->execute([$user_id, $email, $password, $role]);

        $docStmt = $pdo->prepare("INSERT INTO resident_documents (user_id, id_type, id_image_path, selfie_image_path, is_validated) VALUES (?, ?, ?, ?, 1)");
        $docStmt->execute([$user_id, $id_type, $id_filename, $selfie_filename]);

        switch ($id_type) {
            case 'philsys':
                $stmt = $pdo->prepare("INSERT INTO philsys_ids (user_id, full_name, address) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $ocr_full_name, $address]);
                break;
            case 'drivers_license':
                $stmt = $pdo->prepare("INSERT INTO drivers_license_ids (user_id, full_name, address) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $ocr_full_name, $address]);
                break;
            case 'philhealth':
                $stmt = $pdo->prepare("INSERT INTO philhealth_ids (user_id, full_name) VALUES (?, ?)");
                $stmt->execute([$user_id, $ocr_full_name]);
                break;
            case 'tin':
                $stmt = $pdo->prepare("INSERT INTO tin_ids (user_id, full_name, address) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $ocr_full_name, $address]);
                break;
        }

        $pdo->commit();

        echo "<div class='container mt-4'><div class='alert alert-success'><strong>Registration successful!</strong><br> Extracted Text: <pre>" . htmlspecialchars($ocr_output_clean) . "</pre></div></div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='container mt-4'><div class='alert alert-danger'>Registration failed: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | LGU QuickAppoint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Register for LGU QuickAppoint</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Middle Name</label>
                <input type="text" name="middle_name" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
            </div>
        </div>
        <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Valid ID Type</label>
            <select name="valid_id_type" class="form-control" required>
                <option value="">-- Select ID Type --</option>
                <option value="philsys">PhilSys ID</option>
                <option value="drivers_license">Driver's License</option>
                <option value="philhealth">PhilHealth</option>
                <option value="tin">TIN ID</option>
                <option value="others">Others</option>
            </select>
        </div>
        <div class="form-group">
            <label>Upload Valid ID Image</label>
            <input type="file" name="valid_id_image" class="form-control" accept="image/*" required>
        </div>
        <div class="form-group">
            <label>Upload Selfie with ID</label>
            <input type="file" name="selfie_image" class="form-control" accept="image/*" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
        <div class="text-center mt-2">
            <a href="index.php">Already have an account? Login</a>
        </div>
    </form>
</div>
</body>
</html>
