<?php
session_start();
include 'conn.php';
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['validate_only'])) {
  if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    echo "<script>
      alert('⚠️ Security Error: Please verify your OTP first!');
      window.history.back();
    </script>";
    exit();
  }
  
  unset($_SESSION['otp_verified']);
  
  require_once 'validate_id.php';
  
  $valid_id_type  = $_POST['valid_id_type'];
  $first_name     = $_POST['first_name'];
  $middle_name    = $_POST['middle_name'];
  $last_name      = $_POST['last_name'];
  
  $house_number   = $_POST['house_number'];
  $street         = $_POST['street'];
  $barangay       = $_POST['barangay'];
  $municipality   = $_POST['municipality'];
  $province       = $_POST['province'];
  
  $address_parts = array_filter([$house_number, $street, $barangay, $municipality, $province]);
  $address = implode(', ', $address_parts);
  
  $birthday       = $_POST['birthday'];
  $age            = $_POST['age'];
  $sex            = $_POST['sex'];
  $civil_status   = $_POST['civil_status'];
  $email          = $_POST['email'];
  $phone_number   = '+63' . $_POST['phone_number'];
  $password       = password_hash($_POST['password'], PASSWORD_BCRYPT);
  $role           = 'Resident';

  $uploadDir = 'uploads/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  $temp_id = uniqid();
  $temp_front_path = $uploadDir . 'temp_' . $temp_id . '_front_' . basename($_FILES['id_front']['name']);
  $temp_selfie_path = $uploadDir . 'temp_' . $temp_id . '_selfie_' . basename($_FILES['selfie_with_id']['name']);

  if (move_uploaded_file($_FILES['id_front']['tmp_name'], $temp_front_path) &&
    move_uploaded_file($_FILES['selfie_with_id']['tmp_name'], $temp_selfie_path)) {

    $validator = new IDValidator();
    $validationResult = $validator->validateID($temp_front_path, $valid_id_type);
    
    if (!$validationResult['valid']) {
      if (file_exists($temp_front_path)) unlink($temp_front_path);
      if (file_exists($temp_selfie_path)) unlink($temp_selfie_path);
      
      echo "<script>
        alert('Invalid ID picture! The uploaded ID doesn\\'t match the selected ID type.\\n\\nMatch Score: " . $validationResult['score'] . "%\\n\\nPlease upload the correct ID picture for: " . addslashes($valid_id_type) . "');
        window.history.back();
      </script>";
      exit();
    }

    try {
      $pdo->beginTransaction();

      $authStmt = $pdo->prepare("
        INSERT INTO auth (email, password, role)
        VALUES (:email, :password, :role)
      ");
      $authStmt->execute([
        'email'    => $email,
        'password' => $password,
        'role'     => $role
      ]);
      $auth_id = $pdo->lastInsertId();

      $residentStmt = $pdo->prepare("
        INSERT INTO residents (
          auth_id, first_name, middle_name, last_name,
          address, birthday, age, sex, civil_status,
          valid_id_type, id_front_image, selfie_with_id_image, phone_number
        ) VALUES (
          :auth_id, :first_name, :middle_name, :last_name,
          :address, :birthday, :age, :sex, :civil_status,
          :valid_id_type, :id_front_image, :selfie_with_id_image, :phone_number
        )
      ");
      $residentStmt->execute([
        'auth_id'              => $auth_id,
        'first_name'           => $first_name,
        'middle_name'          => $middle_name,
        'last_name'            => $last_name,
        'address'              => $address,
        'birthday'             => $birthday,
        'age'                  => $age,
        'sex'                  => $sex,
        'civil_status'         => $civil_status,
        'valid_id_type'        => $valid_id_type,
        'id_front_image'       => $temp_front_path,
        'selfie_with_id_image' => $temp_selfie_path,
        'phone_number'         => $phone_number
      ]);
      
      $resident_id = $pdo->lastInsertId();

      $front_ext = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
      $selfie_ext = pathinfo($_FILES['selfie_with_id']['name'], PATHINFO_EXTENSION);

      $new_front_path = $uploadDir . 'resident_' . $resident_id . '_front.' . $front_ext;
      $new_selfie_path = $uploadDir . 'resident_' . $resident_id . '_selfie.' . $selfie_ext;

      rename($temp_front_path, $new_front_path);
      rename($temp_selfie_path, $new_selfie_path);

      $updateStmt = $pdo->prepare("
        UPDATE residents 
        SET id_front_image = :front, 
          selfie_with_id_image = :selfie
        WHERE id = :resident_id
      ");
      $updateStmt->execute([
        'front' => $new_front_path,
        'selfie' => $new_selfie_path,
        'resident_id' => $resident_id
      ]);

      $pdo->commit();

      echo "<script>
        alert('✅ Registration successful! ID validation score: " . $validationResult['score'] . "%\\n\\nYou can now login with your credentials.'); 
        window.location.href='login.php';
      </script>";
    } catch (Exception $e) {
      $pdo->rollBack();
      
      if (file_exists($temp_front_path)) unlink($temp_front_path);
      if (file_exists($temp_selfie_path)) unlink($temp_selfie_path);
      
      echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
  } else {
    echo "<script>alert('Error uploading files. Please try again.');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - LGU QuickAppoint</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
  body {
    background: linear-gradient(rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.85)),
    url('assets/images/LGU_Unisan.jpg') no-repeat center center/cover;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    padding: 20px 15px;
    min-height: 100vh;
  }

  .register-container {
    margin: auto;
    padding: 2rem;
    border-radius: 12px;
    background: #fff;
    max-width: 900px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
  }

  .register-container h2 {
    text-align: center;
    font-weight: 700;
    color: #27548A;
    margin-bottom: 1.5rem;
    font-size: 24px;
  }

  .progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    position: relative;
  }

  .progress-steps::before {
    content: '';
    position: absolute;
    top: 25px;
    left: 0;
    right: 0;
    height: 3px;
    background: #e9ecef;
    z-index: 0;
  }

  .progress-steps-fill {
    position: absolute;
    top: 25px;
    left: 0;
    height: 3px;
    background: #27548A;
    z-index: 1;
    transition: width 0.3s ease;
  }

  .step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 2;
  }

  .step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e9ecef;
    border: 3px solid #e9ecef;
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #6c757d;
    transition: all 0.3s ease;
  }

  .step.active .step-circle {
    background: #27548A;
    border-color: #27548A;
    color: white;
  }

  .step.completed .step-circle {
    background: #28a745;
    border-color: #28a745;
    color: white;
  }

  .step-label {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    margin-top: 5px;
  }

  .step.active .step-label {
    color: #27548A;
  }

  .step.completed .step-label {
    color: #28a745;
  }

  .step-content {
    display: none;
    animation: fadeIn 0.3s ease;
  }

  .step-content.active {
    display: block;
  }

  @keyframes fadeIn {
    from {
    opacity: 0;
    transform: translateY(10px);
    }
    to {
    opacity: 1;
    transform: translateY(0);
    }
  }

  .step-instruction {
    background: #f0f8ff;
    border-left: 4px solid #27548A;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
  }

  .step-instruction i {
    color: #27548A;
    margin-right: 10px;
  }

  .step-instruction p {
    margin: 0;
    color: #495057;
    font-size: 14px;
  }

  .form-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
  }

  .section-title {
    font-size: 16px;
    font-weight: 700;
    color: #27548A;
    margin-bottom: 1rem;
  }

  .form-row {
    margin-left: -8px;
    margin-right: -8px;
  }

  .form-row > .col,
  .form-row > [class*="col-"] {
    padding-left: 8px;
    padding-right: 8px;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .form-control {
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
    border: 1px solid #ced4da;
  }

  .form-control:focus {
    border-color: #27548A;
    box-shadow: 0 0 0 0.2rem rgba(39, 84, 138, 0.25);
  }

  select.form-control {
    height: auto;
    min-height: 42px;
  }

  label {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.4rem;
  }

  .required::after {
    content: " *";
    color: red;
  }

  .wizard-buttons {
    display: flex;
    gap: 10px;
    margin-top: 1.5rem;
  }

  .btn-wizard {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 15px;
    transition: 0.3s;
    border: none;
  }

  .btn-wizard-primary {
    background-color: #27548A;
    color: white;
  }

  .btn-wizard-primary:hover:not(:disabled) {
    background-color: #1b3b61;
  }

  .btn-wizard-secondary {
    background-color: transparent;
    color: #27548A;
    border: 2px solid #27548A;
  }

  .btn-wizard-secondary:hover {
    background-color: #27548A;
    color: white;
  }

  .btn-wizard:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .btn-upload-id {
    background-color: #27548A;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 15px 30px;
    font-weight: bold;
    font-size: 16px;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 20px auto;
    max-width: 300px;
  }

  .btn-upload-id:hover {
    background-color: #1b3b61;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39, 84, 138, 0.3);
  }

  .id-status {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 600;
  }

  .id-status.pending {
    background-color: #fff3cd;
    color: #856404;
    border: 2px solid #ffc107;
  }

  .id-status.verified {
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #28a745;
  }

  .input-group-text {
    background-color: #27548A;
    color: white;
    border-radius: 8px 0 0 8px;
    border: none;
    font-weight: 600;
    font-size: 14px;
  }

  .input-group .form-control {
    border-radius: 0 8px 8px 0;
  }

  .password-strength {
    font-size: 12px;
    margin-top: 5px;
  }

  .strength-weak { color: #dc3545; }
  .strength-medium { color: #ffc107; }
  .strength-strong { color: #28a745; }

  .password-requirements {
    font-size: 11px;
    color: #6c757d;
    margin-top: 5px;
    padding-left: 15px;
  }

  .password-requirements li {
    list-style: none;
    position: relative;
    margin-bottom: 3px;
  }

  .password-requirements li::before {
    content: "○";
    position: absolute;
    left: -15px;
  }

  .password-requirements li.valid::before {
    content: "✓";
    color: #28a745;
  }

  .custom-control-label {
    font-size: 12px;
    line-height: 1.5;
    color: #495057;
    font-weight: normal;
    cursor: pointer;
    padding-left: 5px;
  }

  .custom-control-label strong {
    color: #27548A;
    font-weight: 600;
  }

  .custom-control-input:checked ~ .custom-control-label::before {
    background-color: #27548A;
    border-color: #27548A;
  }

  .otp-container {
    max-width: 400px;
    margin: 0 auto;
    text-align: center;
  }

  .otp-input {
    text-align: center;
    font-size: 32px;
    letter-spacing: 15px;
    font-weight: bold;
    max-width: 100%;
    margin: 20px auto;
    padding: 15px;
  }

  .otp-timer {
    font-size: 18px;
    font-weight: 600;
    color: #dc3545;
    margin: 15px 0;
  }

  .otp-help-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 15px;
  }

  .login-link {
    text-align: center;
    display: block;
    margin-top: 1rem;
    font-size: 14px;
    color: #27548A;
  }

  .modal-dialog {
    max-height: 90vh;
    margin: 1.75rem auto;
    display: flex;
    align-items: center;
  }

  .modal-dialog.modal-lg {
    max-width: 800px;
    max-height: 90vh;
  }

  .modal-content {
    border-radius: 12px;
    border: none;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .modal-header {
    background-color: #27548A;
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 20px;
    flex-shrink: 0;
  }

  .modal-header .close {
    color: white;
    opacity: 1;
  }

  .modal-body {
    padding: 30px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1 1 auto;
    max-height: calc(90vh - 80px);
  }

  .modal-title {
    font-weight: 700;
    font-size: 20px;
  }

  .upload-box {
    border: 3px dashed #27548A;
    border-radius: 12px;
    padding: 30px 20px;
    text-align: center;
    background-color: #f8f9fa;
    cursor: pointer;
    transition: 0.3s;
    margin-bottom: 15px;
  }

  .upload-box:hover {
    background-color: #e9ecef;
    border-color: #1b3b61;
  }

  .upload-box i {
    font-size: 40px;
    color: #27548A;
    margin-bottom: 10px;
  }

  .upload-box.has-file {
    border-color: #28a745;
    background-color: #d4edda;
  }

  .upload-box.has-file i {
    color: #28a745;
  }

  .preview-container {
    margin-top: 20px;
    text-align: center;
  }

  .preview-image {
    max-width: 100%;
    max-height: 200px;
    width: auto;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }

  .validation-result {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    text-align: center;
    font-size: 14px;
  }

  .validation-result.success {
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #28a745;
  }

  .validation-result.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 2px solid #dc3545;
  }

  .validation-result.loading {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 2px solid #17a2b8;
  }

  .btn-validate {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
    margin-top: 15px;
  }

  .btn-validate:hover {
    background-color: #218838;
  }

  .method-buttons {
    display: flex;
    gap: 15px;
    margin: 20px 0;
  }

  .btn-method {
    flex: 1;
    padding: 15px 20px;
    background-color: #27548A;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-method:hover:not(:disabled) {
    background-color: #1b3b61;
    transform: translateY(-2px);
  }

  .btn-method:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    opacity: 0.6;
  }

  .btn-cancel {
    width: 100%;
    padding: 12px;
    background-color: transparent;
    color: #27548A;
    border: 2px solid #27548A;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
    margin-top: 10px;
  }

  .btn-cancel:hover {
    background-color: #27548A;
    color: white;
  }

  .camera-container {
    width: 100%;
    max-width: 640px;
    max-height: 300px;
    margin: 0 auto 20px;
    border-radius: 12px;
    overflow: hidden;
    background-color: #000;
  }

  #cameraStream {
    width: 100%;
    max-height: 300px;
    height: auto;
    object-fit: cover;
    display: block;
  }

  .capture-instructions {
    text-align: center;
    padding: 10px;
    background-color: #fff3cd;
    border-radius: 8px;
    margin-bottom: 15px;
  }

  .capture-instructions p {
    margin: 0;
    font-weight: 600;
    color: #856404;
  }

  .captured-images {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 15px 0;
  }

  .captured-preview {
    text-align: center;
    flex: 1;
    max-width: 200px;
  }

  .captured-preview img {
    width: 100%;
    height: auto;
    max-height: 120px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 8px;
  }

  .captured-preview p {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #27548A;
  }

  .camera-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 15px 0;
  }

  .btn-capture, .btn-retake {
    padding: 12px 30px;
    background-color: #27548A;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .btn-capture:hover, .btn-retake:hover {
    background-color: #1b3b61;
  }

  .btn-retake {
    background-color: #dc3545;
  }

  .btn-retake:hover {
    background-color: #c82333;
  }

  .btn-submit {
    width: 100%;
    padding: 12px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    transition: 0.3s;
    margin-top: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }

  .btn-submit:hover {
    background-color: #218838;
  }

  @media (max-width: 768px) {
    .register-container {
    padding: 1.5rem;
    }

    .step-circle {
    width: 40px;
    height: 40px;
    font-size: 16px;
    }

    .step-label {
    font-size: 11px;
    }

    .otp-input {
    font-size: 24px;
    letter-spacing: 10px;
    }
  }

  @media (max-width: 576px) {
    body {
    padding: 10px;
    }

    .register-container {
    padding: 1rem;
    }

    .register-container h2 {
    font-size: 20px;
    }

    .wizard-buttons {
    flex-direction: column;
    }
  }
  </style>
</head>
<body>
  <div class="register-container">
  <h2>Register for LGU QuickAppoint</h2>
  
  <div class="progress-steps">
    <div class="progress-steps-fill" id="progressFill"></div>
    <div class="step active" data-step="1">
    <div class="step-circle">
      <i class="fas fa-user"></i>
    </div>
    <div class="step-label">Personal Info</div>
    </div>
    <div class="step" data-step="2">
    <div class="step-circle">
      <i class="fas fa-lock"></i>
    </div>
    <div class="step-label">Account Info</div>
    </div>
    <div class="step" data-step="3">
    <div class="step-circle">
      <i class="fas fa-shield-alt"></i>
    </div>
    <div class="step-label">Verification</div>
    </div>
  </div>

  <form method="POST" enctype="multipart/form-data" id="registrationForm">
    
    <input type="hidden" name="valid_id_type" id="hidden_valid_id_type" required>
    <input type="file" name="id_front" id="hidden_id_front" style="display: none;" required>
    <input type="file" name="selfie_with_id" id="hidden_selfie_with_id" style="display: none;" required>

    <div class="step-content active" id="step1">
    <div class="step-instruction">
      <i class="fas fa-info-circle"></i>
      <p><strong>Step 1 of 3:</strong> Please fill in your personal information and upload a valid ID to proceed.</p>
    </div>

    <div id="idStatus" class="id-status pending">
      <i class="fas fa-id-card"></i> Valid ID Not Yet Uploaded
    </div>

    <button type="button" class="btn-upload-id" id="openModalBtn">
      <i class="fas fa-cloud-upload-alt"></i>
      Upload Valid ID
    </button>

    <div class="form-section">
      <div class="section-title">Personal Information</div>
      
      <div class="form-row">
      <div class="col-md-4 form-group">
        <label for="first_name" class="required">First Name</label>
        <input type="text" name="first_name" id="first_name" class="form-control" required>
      </div>

      <div class="col-md-4 form-group">
        <label for="middle_name">Middle Name</label>
        <input type="text" name="middle_name" id="middle_name" class="form-control">
      </div>

      <div class="col-md-4 form-group">
        <label for="last_name" class="required">Last Name</label>
        <input type="text" name="last_name" id="last_name" class="form-control" required>
      </div>
      </div>

      <div class="form-row">
      <div class="col-md-4 form-group">
        <label for="birthday" class="required">Birthday</label>
        <input type="date" name="birthday" id="birthday" class="form-control" required>
      </div>

      <div class="col-md-2 form-group">
        <label for="age">Age</label>
        <input type="number" name="age" id="age" class="form-control" readonly required>
      </div>

      <div class="col-md-3 form-group">
        <label for="sex" class="required">Sex</label>
        <select name="sex" id="sex" class="form-control" required>
        <option value="">-- Select --</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        </select>
      </div>

      <div class="col-md-3 form-group">
        <label for="civil_status" class="required">Civil Status</label>
        <select name="civil_status" id="civil_status" class="form-control" required>
        <option value="">-- Select --</option>
        <option value="Single">Single</option>
        <option value="Married">Married</option>
        <option value="Separated">Separated</option>
        <option value="Widowed">Widowed</option>
        <option value="Divorced">Divorced</option>
        <option value="Annulled">Annulled</option>
        <option value="Widower">Widower</option>
        <option value="Single Parent">Single Parent</option>
        </select>
      </div>
      </div>

      <div class="form-row">
      <div class="col-md-6 form-group">
        <label for="province" class="required">Province</label>
        <select name="province" id="province" class="form-control" required>
        <option value="">-- Loading Provinces... --</option>
        </select>
      </div>

      <div class="col-md-6 form-group">
        <label for="municipality" class="required">Municipality/City</label>
        <select name="municipality" id="municipality" class="form-control" required disabled>
        <option value="">-- Select Municipality --</option>
        </select>
      </div>
      </div>

      <div class="form-row">
      <div class="col-md-6 form-group">
        <label for="barangay" class="required">Barangay</label>
        <select name="barangay" id="barangay" class="form-control" required disabled>
        <option value="">-- Select Barangay --</option>
        </select>
      </div>

      <div class="col-md-6 form-group">
        <label for="street" class="required">Street/Purok</label>
        <input type="text" name="street" id="street" class="form-control" placeholder="Enter street/purok name" required>
      </div>
      </div>

      <div class="form-group">
      <label for="house_number">House Number / Building Name (Optional)</label>
      <input type="text" name="house_number" id="house_number" class="form-control" placeholder="e.g., Block 5 Lot 10">
      </div>
    </div>

    <div class="wizard-buttons">
      <button type="button" class="btn-wizard btn-wizard-primary" id="nextStep1" disabled>
      Next <i class="fas fa-arrow-right"></i>
      </button>
    </div>
    </div>

    <div class="step-content" id="step2">
    <div class="step-instruction">
      <i class="fas fa-info-circle"></i>
      <p><strong>Step 2 of 3:</strong> Create your account credentials and review our data privacy policy.</p>
    </div>

    <div class="form-section">
      <div class="section-title">Account Information</div>
      
      <div class="form-row">
      <div class="col-md-6 form-group">
        <label for="email" class="required">Email Address</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="example@email.com" required>
      </div>

      <div class="col-md-6 form-group">
        <label for="phone_number" class="required">Phone Number</label>
        <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">+63</span>
        </div>
        <input type="tel" name="phone_number" id="phone_number" class="form-control" placeholder="9123456789" pattern="[0-9]{10}" maxlength="10" required>
        </div>
        <small class="form-text text-muted">Enter 10-digit mobile number</small>
      </div>
      </div>

      <div class="form-group">
      <label for="password" class="required">Password</label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" required>
        <div class="input-group-append">
        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
          <i class="fas fa-eye" id="eyeIcon"></i>
        </button>
        </div>
      </div>
      <div id="passwordStrength" class="password-strength"></div>
      <ul class="password-requirements">
        <li id="length">At least 8 characters</li>
        <li id="uppercase">At least one uppercase letter</li>
        <li id="lowercase">At least one lowercase letter</li>
        <li id="number">At least one number</li>
        <li id="special">At least one special character (!@#$%^&*)</li>
      </ul>
      </div>
    </div>

    <div class="form-section">
      <div class="section-title">Data Privacy Consent & Declaration</div>
      
      <div class="form-group">
      <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="privacy_consent" name="privacy_consent" required>
        <label class="custom-control-label" for="privacy_consent">
        <strong>Data Privacy Consent:</strong> I consent to the collection and processing of my personal information in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>. I understand that my data will be used solely for legitimate and official LGU transactions.
        </label>
      </div>
      </div>

      <div class="form-group">
      <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="declaration_truth" name="declaration_truth" required>
        <label class="custom-control-label" for="declaration_truth">
        <strong>Declaration of Truth:</strong> I hereby certify that all information provided is true, correct, and complete to the best of my knowledge. I acknowledge that providing false information may result in the denial of services and penalties under applicable laws.
        </label>
      </div>
      </div>
    </div>

    <div class="wizard-buttons">
      <button type="button" class="btn-wizard btn-wizard-secondary" id="backStep2">
      <i class="fas fa-arrow-left"></i> Back
      </button>
      <button type="button" class="btn-wizard btn-wizard-primary" id="sendOTPBtn" disabled>
      Send OTP <i class="fas fa-paper-plane"></i>
      </button>
    </div>
    </div>

    <div class="step-content" id="step3">
    <div class="step-instruction">
      <i class="fas fa-info-circle"></i>
      <p><strong>Step 3 of 3:</strong> Enter the 6-digit code we sent to your email and phone number.</p>
    </div>

    <div class="otp-container">
      <div class="form-group">
      <label for="otp_input" style="text-align: center; display: block; font-weight: bold; font-size: 16px;">Enter OTP Code</label>
      <input type="text" 
           id="otp_input" 
           class="form-control otp-input" 
           maxlength="6" 
           pattern="[0-9]{6}" 
           placeholder="000000"
           required>
      </div>

      <div class="otp-timer" id="otpTimer">
      <i class="fas fa-clock"></i> Expires in: <span id="timerDisplay">05:00</span>
      </div>

      <div class="wizard-buttons" style="max-width: 400px; margin: 20px auto;">
      <button type="button" class="btn-wizard btn-wizard-secondary" id="resendOTPBtn">
        <i class="fas fa-redo"></i> Resend
      </button>
      <button type="button" class="btn-wizard btn-wizard-primary" id="verifyOTPBtn">
        Verify <i class="fas fa-check-circle"></i>
      </button>
      </div>

      <button type="button" class="btn-wizard btn-wizard-secondary" id="backStep3" style="max-width: 400px; margin: 10px auto; display: block;">
      <i class="fas fa-arrow-left"></i> Back
      </button>

      <button type="submit" class="btn-wizard btn-wizard-primary" id="completeRegistrationBtn" style="display: none; max-width: 400px; margin: 20px auto; background-color: #28a745;">
      <i class="fas fa-check-circle"></i> Complete Registration
      </button>

      <p class="otp-help-text">
      Didn't receive the code? Check your spam folder or click Resend OTP.
      </p>
    </div>
    </div>

    <a href="login.php" class="login-link">Already have an account? Login Here</a>
  </form>
  </div>

  <div class="modal fade" id="idUploadModal" tabindex="-1" role="dialog" aria-labelledby="idUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="idUploadModalLabel">
      <i class="fas fa-id-card"></i> Select ID Type
      </h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
      <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="modal-body">
      <div class="form-group">
      <select class="form-control" id="modal_valid_id_type">
        <option value="">- Select -</option>
        <option value="Integrated Bar of the Philippines">Integrated Bar of the Philippines</option>
        <option value="Overseas Workers Welfare Administration">Overseas Workers Welfare Administration</option>
        <option value="Person with Disability">Person with Disability</option>
        <option value="PH Driver's License">PH Driver's License</option>
        <option value="PH National ID">PH National ID</option>
        <option value="PhilHealth">PhilHealth</option>
        <option value="Philippine Passport">Philippine Passport</option>
        <option value="Philippine Statistics Authority Live Birth">Philippine Statistics Authority Live Birth</option>
        <option value="Postal ID">Postal ID</option>
        <option value="Professional Regulation Commission">Professional Regulation Commission</option>
        <option value="Seaman's Book">Seaman's Book</option>
        <option value="Senior Citizen">Senior Citizen</option>
        <option value="Social Security System">Social Security System</option>
        <option value="Solo Parent">Solo Parent</option>
        <option value="Tax Identification Number">Tax Identification Number</option>
        <option value="Unified Multi-purpose ID">Unified Multi-purpose ID</option>
        <option value="Voter's ID">Voter's ID</option>
      </select>
      </div>

      <div class="method-buttons" id="methodButtons" style="display: none;">
      <button type="button" class="btn btn-method" id="scanIDBtn" disabled>
        <i class="fas fa-camera"></i> SCAN ID
      </button>
      <button type="button" class="btn btn-method" id="uploadPhotoBtn" disabled>
        <i class="fas fa-upload"></i> UPLOAD PHOTO
      </button>
      </div>

      <button type="button" class="btn btn-cancel" data-dismiss="modal">CANCEL</button>
    </div>
    </div>
  </div>
  </div>

  <div class="modal fade" id="scanIDModal" tabindex="-1" role="dialog" aria-labelledby="scanIDModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="scanIDModalLabel">
      <i class="fas fa-camera"></i> Scan ID
      </h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
      <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="modal-body">
      <div id="cameraSection">
      <div class="camera-container">
        <video id="cameraStream" autoplay playsinline></video>
        <canvas id="captureCanvas" style="display: none;"></canvas>
      </div>

      <div class="capture-instructions" id="captureInstructions">
        <p><strong>Step 1:</strong> Take a photo of your Valid ID</p>
      </div>

      <div class="captured-images">
        <div class="captured-preview" id="capturedIDPreview" style="display: none;">
        <img id="capturedIDImage" src="" alt="Captured ID">
        <p>Valid ID</p>
        </div>
        <div class="captured-preview" id="capturedSelfiePreview" style="display: none;">
        <img id="capturedSelfieImage" src="" alt="Captured Selfie">
        <p>Selfie with ID</p>
        </div>
      </div>

      <div class="camera-buttons">
        <button type="button" class="btn btn-capture" id="captureBtn">
        <i class="fas fa-camera"></i> Capture
        </button>
        <button type="button" class="btn btn-retake" id="retakeBtn" style="display: none;">
        <i class="fas fa-redo"></i> Retake
        </button>
      </div>

      <div class="validation-result" id="scanValidationResult" style="display: none;"></div>

      <button type="button" class="btn btn-submit" id="submitScannedBtn" style="display: none;">
        <i class="fas fa-check"></i> Submit
      </button>
      </div>
    </div>
    </div>
  </div>
  </div>

  <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="uploadPhotoModalLabel">
      <i class="fas fa-upload"></i> Upload Photo
      </h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
      <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="modal-body">
      <div class="upload-box" id="idFrontBox" onclick="document.getElementById('modal_id_front').click()">
      <i class="fas fa-id-card"></i>
      <h6>Upload Valid ID</h6>
      <p>Click to select image</p>
      <input type="file" id="modal_id_front" accept="image/*" style="display: none;">
      </div>
      <div class="preview-container" id="idFrontPreview" style="display: none;">
      <img class="preview-image" id="idFrontImage" src="" alt="ID Preview">
      </div>

      <div class="upload-box" id="selfieBox" onclick="document.getElementById('modal_selfie_with_id').click()">
      <i class="fas fa-camera"></i>
      <h6>Selfie Holding ID</h6>
      <p>Click to select/take photo</p>
      <input type="file" id="modal_selfie_with_id" accept="image/*" style="display: none;">
      </div>
      <div class="preview-container" id="selfiePreview" style="display: none;">
      <img class="preview-image" id="selfieImage" src="" alt="Selfie Preview">
      </div>

      <div class="validation-result" id="uploadValidationResult" style="display: none;"></div>

      <button type="button" class="btn btn-validate" id="validateBtn" disabled onclick="validateUploadedID()">
      <i class="fas fa-check-circle"></i> Validate ID
      </button>
    </div>
    </div>
  </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
let currentStep = 1;
let otpVerified = false;
let otpSent = false;
let timerInterval = null;

let provinces = [];
let municipalities = [];
let barangays = [];
let isIDValidated = false;
let uploadedIDFront = null;
let uploadedSelfie = null;
let selectedIDType = null;

function updateProgressBar() {
  const steps = document.querySelectorAll('.step');
  const progressFill = document.getElementById('progressFill');
  
  steps.forEach((step, index) => {
  const stepNum = index + 1;
  if (stepNum < currentStep) {
    step.classList.add('completed');
    step.classList.remove('active');
  } else if (stepNum === currentStep) {
    step.classList.add('active');
    step.classList.remove('completed');
  } else {
    step.classList.remove('active', 'completed');
  }
  });
  
  const progress = ((currentStep - 1) / 2) * 100;
  progressFill.style.width = progress + '%';
}

function showStep(stepNum) {
  document.querySelectorAll('.step-content').forEach(content => {
  content.classList.remove('active');
  });
  
  document.getElementById('step' + stepNum).classList.add('active');
  currentStep = stepNum;
  updateProgressBar();
  
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep1() {
  const firstName = document.getElementById('first_name').value.trim();
  const lastName = document.getElementById('last_name').value.trim();
  const birthday = document.getElementById('birthday').value;
  const sex = document.getElementById('sex').value;
  const civilStatus = document.getElementById('civil_status').value;
  const province = document.getElementById('province').value;
  const municipality = document.getElementById('municipality').value;
  const barangay = document.getElementById('barangay').value;
  const street = document.getElementById('street').value.trim();
  
  const isValid = firstName && lastName && birthday && sex && civilStatus && 
          province && municipality && barangay && street && isIDValidated;
  
  document.getElementById('nextStep1').disabled = !isValid;
  return isValid;
}

function validateStep2() {
  const email = document.getElementById('email').value.trim();
  const phoneNumber = document.getElementById('phone_number').value.trim();
  const password = document.getElementById('password').value;
  const privacyConsent = document.getElementById('privacy_consent').checked;
  const declarationTruth = document.getElementById('declaration_truth').checked;
  
  const hasLength = password.length >= 8;
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
  
  const passwordValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
  const phoneValid = phoneNumber.length === 10;
  
  const isValid = email && phoneValid && passwordValid && privacyConsent && declarationTruth;
  
  document.getElementById('sendOTPBtn').disabled = !isValid;
  return isValid;
}

document.getElementById('nextStep1').addEventListener('click', function() {
  if (validateStep1()) {
  showStep(2);
  } else {
  alert('⚠️ Please complete all required fields and upload a valid ID!');
  }
});

document.getElementById('backStep2').addEventListener('click', function() {
  showStep(1);
});

document.getElementById('backStep3').addEventListener('click', function() {
  showStep(2);
  stopTimer();
});

document.getElementById('first_name').addEventListener('input', validateStep1);
document.getElementById('last_name').addEventListener('input', validateStep1);
document.getElementById('birthday').addEventListener('change', validateStep1);
document.getElementById('sex').addEventListener('change', validateStep1);
document.getElementById('civil_status').addEventListener('change', validateStep1);
document.getElementById('province').addEventListener('change', validateStep1);
document.getElementById('municipality').addEventListener('change', validateStep1);
document.getElementById('barangay').addEventListener('change', validateStep1);
document.getElementById('street').addEventListener('input', validateStep1);

document.getElementById('email').addEventListener('input', validateStep2);
document.getElementById('phone_number').addEventListener('input', validateStep2);
document.getElementById('password').addEventListener('input', validateStep2);
document.getElementById('privacy_consent').addEventListener('change', validateStep2);
document.getElementById('declaration_truth').addEventListener('change', validateStep2);

function startTimer(duration) {
  let timeRemaining = duration;
  const timerDisplay = document.getElementById('timerDisplay');
  
  stopTimer();
  
  timerInterval = setInterval(function() {
  const minutes = Math.floor(timeRemaining / 60);
  const seconds = timeRemaining % 60;
  
  timerDisplay.textContent = 
    (minutes < 10 ? '0' : '') + minutes + ':' + 
    (seconds < 10 ? '0' : '') + seconds;
  
  if (timeRemaining <= 0) {
    stopTimer();
    timerDisplay.textContent = 'EXPIRED';
    alert('⏱️ OTP has expired. Please request a new one.');
  }
  
  timeRemaining--;
  }, 1000);
}

function stopTimer() {
  if (timerInterval) {
  clearInterval(timerInterval);
  timerInterval = null;
  }
}

document.getElementById('sendOTPBtn').addEventListener('click', async function() {
  if (!validateStep2()) {
  alert('⚠️ Please complete all required fields first!');
  return;
  }
  
  const email = document.getElementById('email').value;
  const phoneNumber = document.getElementById('phone_number').value;
  const phone = '+63' + phoneNumber;
  const firstName = document.getElementById('first_name').value;
  const lastName = document.getElementById('last_name').value;
  const name = firstName + ' ' + lastName;
  
  const sendBtn = document.getElementById('sendOTPBtn');
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending OTP...';
  
  try {
  const response = await fetch('send_otp.php', {
    method: 'POST',
    headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
    email: email,
    phone: phone,
    name: name
    })
  });
  
  const result = await response.json();
  
  if (result.success) {
    otpSent = true;
    
    let successMsg = '✅ OTP sent successfully!\n\n';
    if (result.email_sent) successMsg += '📧 Email: Sent to ' + email + '\n';
    if (result.sms_sent) successMsg += '📱 SMS: Sent to ' + phone + '\n';
    
    if (result.errors && result.errors.length > 0) {
    successMsg += '\n⚠️ Some notifications failed:\n' + result.errors.join('\n');
    }
    
    alert(successMsg);
    
    showStep(3);
    startTimer(300);
    document.getElementById('otp_input').focus();
  } else {
    alert('❌ Failed to send OTP: ' + result.message);
    sendBtn.disabled = false;
    sendBtn.innerHTML = 'Send OTP <i class="fas fa-paper-plane"></i>';
  }
  } catch (error) {
  console.error('Error:', error);
  alert('❌ An error occurred while sending OTP. Please try again.');
  sendBtn.disabled = false;
  sendBtn.innerHTML = 'Send OTP <i class="fas fa-paper-plane"></i>';
  }
});

document.getElementById('verifyOTPBtn').addEventListener('click', async function() {
  const otp = document.getElementById('otp_input').value.trim();
  
  if (otp.length !== 6) {
  alert('⚠️ Please enter a valid 6-digit OTP code.');
  return;
  }
  
  const verifyBtn = document.getElementById('verifyOTPBtn');
  verifyBtn.disabled = true;
  verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';
  
  try {
  const response = await fetch('verify_otp.php', {
    method: 'POST',
    headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
    otp: otp
    })
  });
  
  const result = await response.json();
  
  if (result.success) {
    otpVerified = true;
    stopTimer();
    
    alert('✅ OTP Verified Successfully!\n\nYou can now complete your registration.');
    
    document.getElementById('verifyOTPBtn').style.display = 'none';
    document.getElementById('resendOTPBtn').style.display = 'none';
    document.getElementById('backStep3').style.display = 'none';
    document.getElementById('otp_input').disabled = true;
    document.getElementById('completeRegistrationBtn').style.display = 'block';
    
  } else {
    alert('❌ ' + result.message);
    verifyBtn.disabled = false;
    verifyBtn.innerHTML = 'Verify <i class="fas fa-check-circle"></i>';
    document.getElementById('otp_input').value = '';
    document.getElementById('otp_input').focus();
  }
  } catch (error) {
  console.error('Error:', error);
  alert('❌ An error occurred while verifying OTP. Please try again.');
  verifyBtn.disabled = false;
  verifyBtn.innerHTML = 'Verify <i class="fas fa-check-circle"></i>';
  }
});

document.getElementById('resendOTPBtn').addEventListener('click', async function() {
  const email = document.getElementById('email').value;
  const phoneNumber = document.getElementById('phone_number').value;
  const phone = '+63' + phoneNumber;
  const firstName = document.getElementById('first_name').value;
  const lastName = document.getElementById('last_name').value;
  const name = firstName + ' ' + lastName;
  
  const resendBtn = document.getElementById('resendOTPBtn');
  resendBtn.disabled = true;
  resendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
  
  try {
  const response = await fetch('send_otp.php', {
    method: 'POST',
    headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
    email: email,
    phone: phone,
    name: name
    })
  });
  
  const result = await response.json();
  
  if (result.success) {
    alert('✅ OTP resent successfully! Please check your email and phone.');
    document.getElementById('otp_input').value = '';
    document.getElementById('otp_input').focus();
    startTimer(300);
  } else {
    alert('❌ Failed to resend OTP: ' + result.message);
  }
  } catch (error) {
  console.error('Error:', error);
  alert('❌ An error occurred while resending OTP. Please try again.');
  } finally {
  resendBtn.disabled = false;
  resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend';
  }
});

document.getElementById('otp_input').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
  e.preventDefault();
  document.getElementById('verifyOTPBtn').click();
  }
});

document.getElementById('registrationForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  if (!otpVerified) {
  alert('⚠️ Please verify your OTP first!');
  return false;
  }
  
  const completeBtn = document.getElementById('completeRegistrationBtn');
  completeBtn.disabled = true;
  completeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Registering...';
  
  this.submit();
});

async function loadProvinces() {
  try {
  const response = await fetch('https://psgc.gitlab.io/api/provinces/');
  provinces = await response.json();
  
  provinces.sort((a, b) => a.name.localeCompare(b.name));
  
  const provinceSelect = document.getElementById('province');
  provinceSelect.innerHTML = '<option value="">-- Select Province --</option>';
  
  provinces.forEach(province => {
    const option = document.createElement('option');
    option.value = province.name;
    option.setAttribute('data-code', province.code);
    option.textContent = province.name;
    provinceSelect.appendChild(option);
  });
  } catch (error) {
  console.error('Error loading provinces:', error);
  alert('Failed to load provinces. Please refresh the page.');
  }
}

async function loadMunicipalities(provinceCode) {
  try {
  const response = await fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`);
  municipalities = await response.json();
  
  municipalities.sort((a, b) => a.name.localeCompare(b.name));
  
  const municipalitySelect = document.getElementById('municipality');
  municipalitySelect.innerHTML = '<option value="">-- Select Municipality --</option>';
  municipalitySelect.disabled = false;
  
  municipalities.forEach(municipality => {
    const option = document.createElement('option');
    option.value = municipality.name;
    option.setAttribute('data-code', municipality.code);
    option.textContent = municipality.name;
    municipalitySelect.appendChild(option);
  });
  
  document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
  document.getElementById('barangay').disabled = true;
  
  validateStep1();
  } catch (error) {
  console.error('Error loading municipalities:', error);
  alert('Failed to load municipalities. Please try again.');
  }
}

async function loadBarangays(municipalityCode) {
  try {
  const response = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${municipalityCode}/barangays/`);
  barangays = await response.json();
  
  barangays.sort((a, b) => a.name.localeCompare(b.name));
  
  const barangaySelect = document.getElementById('barangay');
  barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
  barangaySelect.disabled = false;
  
  barangays.forEach(barangay => {
    const option = document.createElement('option');
    option.value = barangay.name;
    option.textContent = barangay.name;
    barangaySelect.appendChild(option);
  });
  
  validateStep1();
  } catch (error) {
  console.error('Error loading barangays:', error);
  alert('Failed to load barangays. Please try again.');
  }
}

document.getElementById('province').addEventListener('change', function() {
  const selectedOption = this.options[this.selectedIndex];
  const provinceCode = selectedOption.getAttribute('data-code');
  
  if (provinceCode) {
  loadMunicipalities(provinceCode);
  } else {
  document.getElementById('municipality').innerHTML = '<option value="">-- Select Municipality --</option>';
  document.getElementById('municipality').disabled = true;
  document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
  document.getElementById('barangay').disabled = true;
  }
});

document.getElementById('municipality').addEventListener('change', function() {
  const selectedOption = this.options[this.selectedIndex];
  const municipalityCode = selectedOption.getAttribute('data-code');
  
  if (municipalityCode) {
  loadBarangays(municipalityCode);
  } else {
  document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
  document.getElementById('barangay').disabled = true;
  }
});

window.addEventListener('DOMContentLoaded', function() {
  loadProvinces();
});

document.getElementById('openModalBtn').addEventListener('click', function() {
  $('#idUploadModal').modal('show');
});

document.getElementById('modal_valid_id_type').addEventListener('change', function() {
  const idType = this.value;
  const methodButtons = document.getElementById('methodButtons');
  const scanBtn = document.getElementById('scanIDBtn');
  const uploadBtn = document.getElementById('uploadPhotoBtn');
  
  if (idType) {
  selectedIDType = idType;
  methodButtons.style.display = 'flex';
  scanBtn.disabled = false;
  uploadBtn.disabled = false;
  } else {
  methodButtons.style.display = 'none';
  scanBtn.disabled = true;
  uploadBtn.disabled = true;
  selectedIDType = null;
  }
});

document.getElementById('scanIDBtn').addEventListener('click', function() {
  if (!selectedIDType) {
  alert('Please select an ID type first!');
  return;
  }
  $('#idUploadModal').modal('hide');
  $('#scanIDModal').modal('show');
  startCamera();
});

document.getElementById('uploadPhotoBtn').addEventListener('click', function() {
  if (!selectedIDType) {
  alert('Please select an ID type first!');
  return;
  }
  $('#idUploadModal').modal('hide');
  $('#uploadPhotoModal').modal('show');
  resetUploadSection();
});

let cameraStream = null;
let capturedIDBlob = null;
let capturedSelfieBlob = null;
let currentCaptureStep = 'id';

async function startCamera() {
  try {
  const stream = await navigator.mediaDevices.getUserMedia({ 
    video: { facingMode: 'environment' },
    audio: false 
  });
  const videoElement = document.getElementById('cameraStream');
  videoElement.srcObject = stream;
  cameraStream = stream;
  
  capturedIDBlob = null;
  capturedSelfieBlob = null;
  currentCaptureStep = 'id';
  document.getElementById('captureInstructions').innerHTML = '<p><strong>Step 1:</strong> Take a photo of your Valid ID</p>';
  document.getElementById('capturedIDPreview').style.display = 'none';
  document.getElementById('capturedSelfiePreview').style.display = 'none';
  document.getElementById('submitScannedBtn').style.display = 'none';
  document.getElementById('scanValidationResult').style.display = 'none';
  } catch (error) {
  console.error('Error accessing camera:', error);
  alert('Unable to access camera. Please ensure you have granted camera permissions.');
  }
}

function stopCamera() {
  if (cameraStream) {
  cameraStream.getTracks().forEach(track => track.stop());
  cameraStream = null;
  }
}

document.getElementById('captureBtn').addEventListener('click', function() {
  const video = document.getElementById('cameraStream');
  const canvas = document.getElementById('captureCanvas');
  const context = canvas.getContext('2d');
  
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  context.drawImage(video, 0, 0, canvas.width, canvas.height);
  
  canvas.toBlob(function(blob) {
  if (currentCaptureStep === 'id') {
    capturedIDBlob = blob;
    const imageUrl = URL.createObjectURL(blob);
    document.getElementById('capturedIDImage').src = imageUrl;
    document.getElementById('capturedIDPreview').style.display = 'block';
    
    currentCaptureStep = 'selfie';
    document.getElementById('captureInstructions').innerHTML = '<p><strong>Step 2:</strong> Take a selfie while holding your Valid ID</p>';
    document.getElementById('retakeBtn').style.display = 'inline-flex';
    
  } else if (currentCaptureStep === 'selfie') {
    capturedSelfieBlob = blob;
    const imageUrl = URL.createObjectURL(blob);
    document.getElementById('capturedSelfieImage').src = imageUrl;
    document.getElementById('capturedSelfiePreview').style.display = 'block';
    
    document.getElementById('captureBtn').style.display = 'none';
    document.getElementById('retakeBtn').style.display = 'inline-flex';
    document.getElementById('captureInstructions').innerHTML = '<p><strong>✓ Both photos captured!</strong> Click Submit to validate.</p>';
    
    stopCamera();
    validateScannedID();
  }
  }, 'image/jpeg', 0.9);
});

document.getElementById('retakeBtn').addEventListener('click', function() {
  if (currentCaptureStep === 'selfie') {
  capturedIDBlob = null;
  capturedSelfieBlob = null;
  currentCaptureStep = 'id';
  document.getElementById('capturedIDPreview').style.display = 'none';
  document.getElementById('capturedSelfiePreview').style.display = 'none';
  document.getElementById('captureInstructions').innerHTML = '<p><strong>Step 1:</strong> Take a photo of your Valid ID</p>';
  document.getElementById('captureBtn').style.display = 'inline-flex';
  document.getElementById('retakeBtn').style.display = 'none';
  document.getElementById('submitScannedBtn').style.display = 'none';
  document.getElementById('scanValidationResult').style.display = 'none';
  startCamera();
  }
});

async function validateScannedID() {
  const validationResult = document.getElementById('scanValidationResult');
  validationResult.style.display = 'block';
  validationResult.className = 'validation-result loading';
  validationResult.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating ID...';
  
  const formData = new FormData();
  formData.append('id_front', capturedIDBlob, 'id_front.jpg');
  formData.append('selfie_with_id', capturedSelfieBlob, 'selfie.jpg');
  formData.append('id_type', selectedIDType);
  
  try {
  const response = await fetch('validate_id.php', {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  
  if (result.success) {
    validationResult.className = 'validation-result success';
    validationResult.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
    document.getElementById('submitScannedBtn').style.display = 'block';
  } else {
    validationResult.className = 'validation-result error';
    validationResult.innerHTML = '<i class="fas fa-times-circle"></i> ' + result.message;
    document.getElementById('retakeBtn').style.display = 'inline-flex';
  }
  } catch (error) {
  console.error('Validation error:', error);
  validationResult.className = 'validation-result error';
  validationResult.innerHTML = '<i class="fas fa-times-circle"></i> Validation failed. Please try again.';
  }
}

document.getElementById('submitScannedBtn').addEventListener('click', function() {
  uploadedIDFront = capturedIDBlob;
  uploadedSelfie = capturedSelfieBlob;
  isIDValidated = true;
  
  document.getElementById('hidden_valid_id_type').value = selectedIDType;
  
  const idFrontFile = new File([capturedIDBlob], 'id_front.jpg', { type: 'image/jpeg' });
  const selfieFile = new File([capturedSelfieBlob], 'selfie.jpg', { type: 'image/jpeg' });
  
  const idFrontInput = document.getElementById('hidden_id_front');
  const selfieInput = document.getElementById('hidden_selfie_with_id');
  
  const dataTransfer1 = new DataTransfer();
  dataTransfer1.items.add(idFrontFile);
  idFrontInput.files = dataTransfer1.files;
  
  const dataTransfer2 = new DataTransfer();
  dataTransfer2.items.add(selfieFile);
  selfieInput.files = dataTransfer2.files;
  
  document.getElementById('idStatus').className = 'id-status verified';
  document.getElementById('idStatus').innerHTML = '<i class="fas fa-check-circle"></i> Valid ID Verified (' + selectedIDType + ')';
  
  $('#scanIDModal').modal('hide');
  stopCamera();
  
  validateStep1();
  
  alert('✅ ID Successfully Validated!\n\nYou can now proceed with your registration.');
});

$('#scanIDModal').on('hidden.bs.modal', function() {
  stopCamera();
});

function resetUploadSection() {
  document.getElementById('modal_id_front').value = '';
  document.getElementById('modal_selfie_with_id').value = '';
  document.getElementById('idFrontPreview').style.display = 'none';
  document.getElementById('selfiePreview').style.display = 'none';
  document.getElementById('idFrontBox').classList.remove('has-file');
  document.getElementById('selfieBox').classList.remove('has-file');
  document.getElementById('validateBtn').disabled = true;
  document.getElementById('uploadValidationResult').style.display = 'none';
}

document.getElementById('modal_id_front').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('idFrontImage').src = e.target.result;
    document.getElementById('idFrontPreview').style.display = 'block';
    document.getElementById('idFrontBox').classList.add('has-file');
    checkUploadReadiness();
  };
  reader.readAsDataURL(file);
  }
});

document.getElementById('modal_selfie_with_id').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('selfieImage').src = e.target.result;
    document.getElementById('selfiePreview').style.display = 'block';
    document.getElementById('selfieBox').classList.add('has-file');
    checkUploadReadiness();
  };
  reader.readAsDataURL(file);
  }
});

function checkUploadReadiness() {
  const idFront = document.getElementById('modal_id_front').files[0];
  const selfie = document.getElementById('modal_selfie_with_id').files[0];
  
  if (idFront && selfie) {
  document.getElementById('validateBtn').disabled = false;
  }
}

async function validateUploadedID() {
  const idFront = document.getElementById('modal_id_front').files[0];
  const selfie = document.getElementById('modal_selfie_with_id').files[0];
  
  if (!idFront || !selfie) {
  alert('Please upload both ID and selfie images!');
  return;
  }
  
  const validationResult = document.getElementById('uploadValidationResult');
  validationResult.style.display = 'block';
  validationResult.className = 'validation-result loading';
  validationResult.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating ID...';
  
  const validateBtn = document.getElementById('validateBtn');
  validateBtn.disabled = true;
  validateBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validating...';
  
  const formData = new FormData();
  formData.append('id_front', idFront);
  formData.append('selfie_with_id', selfie);
  formData.append('id_type', selectedIDType);
  
  try {
  const response = await fetch('validate_id.php', {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  
  if (result.success) {
    validationResult.className = 'validation-result success';
    validationResult.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
    
    uploadedIDFront = idFront;
    uploadedSelfie = selfie;
    isIDValidated = true;
    
    document.getElementById('hidden_valid_id_type').value = selectedIDType;
    
    const dataTransfer1 = new DataTransfer();
    dataTransfer1.items.add(idFront);
    document.getElementById('hidden_id_front').files = dataTransfer1.files;
    
    const dataTransfer2 = new DataTransfer();
    dataTransfer2.items.add(selfie);
    document.getElementById('hidden_selfie_with_id').files = dataTransfer2.files;
    
    document.getElementById('idStatus').className = 'id-status verified';
    document.getElementById('idStatus').innerHTML = '<i class="fas fa-check-circle"></i> Valid ID Verified (' + selectedIDType + ')';
    
    setTimeout(function() {
    $('#uploadPhotoModal').modal('hide');
    validateStep1();
    alert('✅ ID Successfully Validated!\n\nYou can now proceed with your registration.');
    }, 1500);
    
  } else {
    validationResult.className = 'validation-result error';
    validationResult.innerHTML = '<i class="fas fa-times-circle"></i> ' + result.message;
    validateBtn.disabled = false;
    validateBtn.innerHTML = '<i class="fas fa-check-circle"></i> Validate ID';
  }
  } catch (error) {
  console.error('Validation error:', error);
  validationResult.className = 'validation-result error';
  validationResult.innerHTML = '<i class="fas fa-times-circle"></i> Validation failed. Please try again.';
  validateBtn.disabled = false;
  validateBtn.innerHTML = '<i class="fas fa-check-circle"></i> Validate ID';
  }
}

document.getElementById('password').addEventListener('input', function() {
  const password = this.value;
  const strengthDisplay = document.getElementById('passwordStrength');
  
  const hasLength = password.length >= 8;
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
  
  document.getElementById('length').className = hasLength ? 'valid' : '';
  document.getElementById('uppercase').className = hasUppercase ? 'valid' : '';
  document.getElementById('lowercase').className = hasLowercase ? 'valid' : '';
  document.getElementById('number').className = hasNumber ? 'valid' : '';
  document.getElementById('special').className = hasSpecial ? 'valid' : '';
  
  let strength = 0;
  if (hasLength) strength++;
  if (hasUppercase) strength++;
  if (hasLowercase) strength++;
  if (hasNumber) strength++;
  if (hasSpecial) strength++;
  
  if (strength <= 2) {
  strengthDisplay.innerHTML = '<span class="strength-weak">⚠️ Weak Password</span>';
  } else if (strength <= 4) {
  strengthDisplay.innerHTML = '<span class="strength-medium">⚡ Medium Password</span>';
  } else {
  strengthDisplay.innerHTML = '<span class="strength-strong">✓ Strong Password</span>';
  }
  
  validateStep2();
});

document.getElementById('togglePassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');
  
  if (passwordInput.type === 'password') {
  passwordInput.type = 'text';
  eyeIcon.classList.remove('fa-eye');
  eyeIcon.classList.add('fa-eye-slash');
  } else {
  passwordInput.type = 'password';
  eyeIcon.classList.remove('fa-eye-slash');
  eyeIcon.classList.add('fa-eye');
  }
});

document.getElementById('birthday').addEventListener('change', function() {
  const birthday = new Date(this.value);
  const today = new Date();
  let age = today.getFullYear() - birthday.getFullYear();
  const monthDiff = today.getMonth() - birthday.getMonth();
  
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
  age--;
  }
  
  document.getElementById('age').value = age;
});

document.getElementById('phone_number').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
  if (this.value.length > 10) {
  this.value = this.value.slice(0, 10);
  }
});

document.getElementById('otp_input').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});
  </script>
</body>
</html>
