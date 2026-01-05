<?php
session_start();
require_once 'conn.php';

// Guard: must be logged in
if (!isset($_SESSION['auth_id']) || empty($_SESSION['auth_id'])) {
  echo "<div class='alert alert-danger m-3'>Unauthorized access.</div>";
  exit();
}

$auth_id = (int)$_SESSION['auth_id'];
$role    = $_SESSION['role'] ?? '';

if (!in_array($role, ['Resident','Admin','LGU Personnel'], true)) {
  echo "<div class='alert alert-danger m-3'>Unknown role.</div>";
  exit();
}

try {
  if ($role === 'Resident') {
    $stmt = $pdo->prepare("SELECT r.*, a.email FROM residents r JOIN auth a ON a.id = r.auth_id WHERE r.auth_id = ? LIMIT 1");
  } elseif ($role === 'Admin') {
    $stmt = $pdo->prepare("SELECT ad.*, a.email FROM admins ad JOIN auth a ON a.id = ad.auth_id WHERE ad.auth_id = ? LIMIT 1");
  } else { // LGU Personnel
    $stmt = $pdo->prepare("SELECT lp.*, a.email, d.name AS department_name
                           FROM lgu_personnel lp
                           JOIN auth a ON a.id = lp.auth_id
                           LEFT JOIN departments d ON d.id = lp.department_id
                           WHERE lp.auth_id = ? LIMIT 1");
  }
  $stmt->execute([$auth_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    echo "<div class='alert alert-danger m-3'>User record not found for role: ".htmlspecialchars($role).".</div>";
    exit();
  }
} catch (Exception $e) {
  echo "<div class='alert alert-danger m-3'>Database error: ".htmlspecialchars($e->getMessage())."</div>";
  exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - <?php echo h($role); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
  body {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  min-height: 100vh;
  padding: 1.5rem 0;
}

.profile-container {
  max-width: 1000px;
  margin: 0 auto;
}

.profile-header {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  position: relative;
  overflow: hidden;
}

.profile-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  border-radius: 50%;
}

.profile-header-content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
  font-weight: bold;
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.profile-info {
  flex: 1;
  min-width: 200px;
}

.profile-info h3 {
  margin: 0;
  color: #2d3748;
  font-weight: 700;
  font-size: 1.5rem;
}

.profile-info p {
  margin: 0.25rem 0 0 0;
  color: #718096;
  font-size: 0.95rem;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
  color: white;
  padding: 0.5rem 1.25rem;
  border-radius: 25px;
  font-weight: 600;
  font-size: 0.9rem;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.section-card {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.section-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
}

.section-title {
  font-weight: 700;
  color: #2d3748;
  font-size: 1.2rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-bottom: 1rem;
  border-bottom: 3px solid #f0f3f7;
}

.section-title i {
  color: #3498db;
  font-size: 1.3rem;
}

.form-group label {
  font-weight: 600;
  color: #4a5568;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.form-control {
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white !important;
  color: #2d3748 !important;
}

.form-control:focus {
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  outline: none;
  background: white;
}

.form-control:disabled {
  background: #f0f3f7;
  color: #718096;
  cursor: not-allowed;
}

/* Enhanced Select Dropdown Styling - CRITICAL FIX */
select.form-control {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232d3748' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 12px;
  padding-right: 2.5rem !important;
  cursor: pointer !important;
  color: #2d3748 !important;
  background-color: white !important;
  font-weight: 500 !important;
  -webkit-text-fill-color: #2d3748 !important;
}

select.form-control::-ms-expand {
  display: none;
}

/* Ensure all options are visible with proper colors */
select.form-control option {
  color: #2d3748 !important;
  background-color: white !important;
  padding: 10px !important;
  font-weight: 500 !important;
}

/* Fix for empty/placeholder options */
select.form-control option[value=""] {
  color: #94a3b8 !important;
  font-style: italic;
}

/* Ensure selected option is visible */
select.form-control option:checked {
  background-color: #3498db !important;
  color: white !important;
}

/* Modal select specific fixes */
.modal-body select.form-control {
  color: #2d3748 !important;
  background-color: white !important;
  -webkit-text-fill-color: #2d3748 !important;
}

.modal-body select.form-control option {
  color: #2d3748 !important;
  background-color: white !important;
}

/* Fix disabled select */
select.form-control:disabled {
  background-color: #f0f3f7 !important;
  color: #94a3b8 !important;
  cursor: not-allowed !important;
  -webkit-text-fill-color: #94a3b8 !important;
}

.btn {
  border-radius: 10px;
  
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  border: none;
}

.btn-primary {
  background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-secondary {
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
  box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

.btn-secondary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4);
}

.btn-info {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.btn-info:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
}

.action-buttons {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.alert {
  border-radius: 12px;
  border: none;
  padding: 1rem 1.25rem;
  font-weight: 500;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.alert-success {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
}

.alert-danger {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #991b1b;
}

.modal-content {
  border-radius: 20px;
  border: none;
  overflow: hidden;
}

.modal-header {
  background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
  color: white;
  padding: 1.5rem;
  border: none;
}

.modal-header .modal-title {
  font-weight: 700;
  font-size: 1.25rem;
}

.modal-header .close {
  color: white;
  opacity: 1;
  font-size: 1.5rem;
  text-shadow: none;
}

.modal-body {
  padding: 2rem;
}

.modal-footer {
  border-top: 2px solid #f0f3f7;
  padding: 1.5rem 2rem;
}

.info-note {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-left: 4px solid #3b82f6;
  padding: 1rem;
  border-radius: 8px;
  color: #1e40af;
  font-size: 0.9rem;
}

.address-display {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  border: 2px solid #e2e8f0;
}

.address-text {
  flex: 1;
  color: #4a5568;
  font-size: 0.95rem;
}

.address-text.empty {
  color: #94a3b8;
  font-style: italic;
}

/* Fix for Address Modal Dropdown Text Visibility */
#addressModal select.form-control {
  color: #2d3748 !important;
  background-color: white !important;
  -webkit-text-fill-color: #2d3748 !important;
}

#addressModal select.form-control option {
  color: #2d3748 !important;
  background-color: white !important;
  
}

#addressModal input.form-control {
  color: #2d3748 !important;
  background-color: white !important;
}

#addressModal .form-control::placeholder {
  color: #94a3b8 !important;
}

#addressModal .form-control:disabled {
  background-color: #f0f3f7 !important;
  color: #94a3b8 !important;
}

/* Ensure dropdown options are visible */
#provinceSelect option,
#municipalitySelect option,
#barangaySelect option {
  color: #2d3748 !important;
  background-color: white !important;
}

/* Fix hover state for options */
#provinceSelect option:hover,
#municipalitySelect option:hover,
#barangaySelect option:hover {
  background-color: #f0f3f7 !important;
}

/* Override general form-control styles inside address modal */
#addressModal .modal-body .form-control {
  background: white !important;
  color: #2d3748 !important;
  border: 2px solid #e2e8f0;
}

#addressModal .modal-body .form-control:focus {
  background: white !important;
  color: #2d3748 !important;
  border-color: #3498db;
}

/* Password Change Modal Styles */
.bg-gradient-blue {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

#changePasswordModal .modal-content {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

#changePasswordModal .modal-header {
  border: none;
  padding: 24px 32px;
}

#changePasswordModal .modal-body {
  padding: 32px;
  background: #ffffff;
}

#changePasswordModal .modal-footer {
  border: none;
  padding: 24px 32px;
  background: #f8fafc;
}

#changePasswordModal .modal-title {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

#changePasswordModal .close {
  opacity: 1;
  text-shadow: none;
  font-size: 28px;
  font-weight: 300;
  transition: transform 0.2s ease;
}

#changePasswordModal .close:hover {
  transform: rotate(90deg);
}

#changePasswordModal .form-group {
  margin-bottom: 24px;
}

#changePasswordModal .form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
}

#changePasswordModal .form-group label i {
  font-size: 14px;
  margin-right: 6px;
  color: #64748b;
}

.password-input-group {
  position: relative;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  transition: all 0.3s ease;
  background: #ffffff;
  display: flex;
  align-items: stretch;
}

.password-input-group:focus-within {
  border-color: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.password-input-group .input-group-prepend,
.password-input-group .input-group-append {
  display: flex;
  align-items: center;
}

.password-input-group .input-group-text {
  background: transparent;
  border: none;
  padding: 0 16px;
  color: #64748b;
}

.password-input-group .form-control {
  border: none;
  padding: 14px 16px;
  font-size: 15px;
  background: transparent;
  height: auto;
  flex: 1;
}

.password-input-group .form-control::placeholder {
  color: #94a3b8;
}

.password-input-group .form-control:focus {
  box-shadow: none;
  background: transparent;
  outline: none;
}

.btn-toggle-password {
  background: transparent;
  border: none;
  color: #64748b;
  padding: 0 16px;
  transition: color 0.2s ease;
  cursor: pointer;
}

.btn-toggle-password:hover {
  color: #2563eb;
}

.btn-toggle-password:focus {
  box-shadow: none;
  outline: none;
}

.strength-container {
  margin-top: 12px;
}

.strength-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.strength-label {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

#strengthText {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.password-progress {
  height: 8px;
  background-color: #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.password-progress .progress-bar {
  transition: width 0.4s ease, background-color 0.4s ease;
  border-radius: 8px;
}

.requirements-box {
  margin-top: 16px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.requirements-title {
  font-size: 13px;
  color: #475569;
  font-weight: 600;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

.requirements-title i {
  margin-right: 6px;
  color: #2563eb;
}

.password-requirements {
  display: grid;
  gap: 8px;
}

.password-requirements small {
  font-size: 13px;
  color: #64748b;
  display: flex;
  align-items: center;
  transition: color 0.3s ease;
}

.password-requirements small i {
  font-size: 8px;
  margin-right: 10px;
  color: #cbd5e1;
  transition: all 0.3s ease;
}

.password-requirements small.met {
  color: #059669 !important;
  font-weight: 600;
}

.password-requirements small.met i {
  color: #059669 !important;
}

#matchMessage {
  font-size: 13px;
  font-weight: 600;
  margin-top: 8px;
  display: flex;
  align-items: center;
}

#matchMessage i {
  margin-right: 6px;
}

#changePasswordModal .btn {
  padding: 12px 28px;
  font-weight: 600;
  font-size: 15px;
  border-radius: 10px;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
}

#changePasswordModal .btn i {
  font-size: 14px;
}

#changePasswordModal .btn-light {
  background: #ffffff;
  color: #475569;
  border: 2px solid #e2e8f0;
}

#changePasswordModal .btn-light:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-1px);
}

#changePasswordModal .btn-primary {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

#changePasswordModal .btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}

#changePasswordModal .btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-card {
  animation: fadeInUp 0.5s ease forwards;
}

.section-card:nth-child(1) { animation-delay: 0.1s; }
.section-card:nth-child(2) { animation-delay: 0.2s; }
.section-card:nth-child(3) { animation-delay: 0.3s; }

/* Mobile Responsive */
@media (max-width: 576px) {
  body {
    padding: 1rem 0;
  }

  .profile-header {
    padding: 1.5rem;
    border-radius: 15px;
  }

  .profile-avatar {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
  }

  .profile-info h3 {
    font-size: 1.25rem;
  }

  .profile-info p {
    font-size: 0.85rem;
  }

  .role-badge {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
  }

  .section-card {
    padding: 1.5rem;
    border-radius: 15px;
  }

  .section-title {
    font-size: 1.1rem;
  }

  .form-control {
    padding: 0.65rem;
    font-size: 0.9rem;
  }

  .btn {
    width: 100%;
    margin-bottom: 0.5rem;
  }

  .action-buttons {
    flex-direction: column;
  }

  .modal-body {
    padding: 1.5rem;
  }

  #changePasswordModal .modal-dialog {
    margin: 0.5rem;
  }

  #changePasswordModal .modal-header,
  #changePasswordModal .modal-body,
  #changePasswordModal .modal-footer {
    padding: 20px;
  }

  #changePasswordModal .modal-footer {
    flex-direction: column-reverse;
    gap: 8px;
  }

  #changePasswordModal .modal-footer .btn {
    width: 100%;
    margin: 0;
  }
}
  </style>
</head>
<body>
<div class="container profile-container px-3 px-md-4">
  <div class="profile-header">
    <div class="profile-header-content">
      <div class="profile-avatar">
        <?php 
          $initials = '';
          if (!empty($user['first_name'])) {
            $initials .= strtoupper(substr($user['first_name'], 0, 1));
          }
          if (!empty($user['last_name'])) {
            $initials .= strtoupper(substr($user['last_name'], 0, 1));
          }
          echo $initials ?: 'U';
        ?>
      </div>
      <div class="profile-info">
        <h3><?php echo h($user['first_name'] ?? '') . ' ' . h($user['last_name'] ?? ''); ?></h3>
        <p><i class="fas fa-envelope mr-1"></i> <?php echo h($user['email'] ?? ''); ?></p>
      </div>
      <div class="ml-auto">
        <span class="role-badge">
          <i class="fas fa-user-tag"></i>
          <?php echo h($role); ?>
        </span>
      </div>
    </div>
  </div>

  <div id="ajaxAlert"></div>

  <form id="updateProfileForm">
    <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
    <input type="hidden" name="role" value="<?php echo h($role); ?>">
    <input type="hidden" name="province" id="hiddenProvince">
    <input type="hidden" name="municipality" id="hiddenMunicipality">
    <input type="hidden" name="barangay" id="hiddenBarangay">
    <input type="hidden" name="street" id="hiddenStreet">
    <input type="hidden" name="house_number" id="hiddenHouseNumber">

    <!-- Account Information -->
    <div class="section-card">
      <div class="section-title">
        <i class="fas fa-user-circle"></i>
        <span>Account Information</span>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> First Name</label>
          <input type="text" name="first_name" class="form-control" value="<?php echo h($user['first_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="<?php echo h($user['middle_name'] ?? ''); ?>">
        </div>
        <div class="form-group col-md-4">
          <label><i class="fas fa-id-badge mr-1"></i> Last Name</label>
          <input type="text" name="last_name" class="form-control" value="<?php echo h($user['last_name'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label><i class="fas fa-envelope mr-1"></i> Email Address</label>
        <input type="email" name="email" class="form-control" value="<?php echo h($user['email'] ?? ''); ?>" required>
      </div>
    </div>

    <?php if ($role === 'Resident'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-address-card"></i>
          <span>Personal Information</span>
        </div>
        
        <div class="form-row">
          <div class="form-group col-md-4">
            <label><i class="fas fa-calendar mr-1"></i> Birthday</label>
            <input type="date" name="birthday" class="form-control" value="<?php echo h($user['birthday'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-2">
            <label><i class="fas fa-hashtag mr-1"></i> Age</label>
            <input type="number" name="age" class="form-control" value="<?php echo h($user['age'] ?? ''); ?>" readonly>
          </div>
          <div class="form-group col-md-3">
            <label><i class="fas fa-venus-mars mr-1"></i> Sex</label>
            <select name="sex" class="form-control">
              <option value="" <?php echo empty($user['sex'])?'selected':''; ?>>-- Select Sex --</option>
              <option value="Male"   <?php echo (isset($user['sex']) && $user['sex']==='Male')?'selected':''; ?>>Male</option>
              <option value="Female" <?php echo (isset($user['sex']) && $user['sex']==='Female')?'selected':''; ?>>Female</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label><i class="fas fa-ring mr-1"></i> Civil Status</label>
            <select name="civil_status" class="form-control">
              <option value="" <?php echo empty($user['civil_status'])?'selected':''; ?>>-- Select Status --</option>
              <option value="Single"  <?php echo (isset($user['civil_status']) && $user['civil_status']==='Single')?'selected':''; ?>>Single</option>
              <option value="Married" <?php echo (isset($user['civil_status']) && $user['civil_status']==='Married')?'selected':''; ?>>Married</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-map-marker-alt mr-1"></i> Address</label>
          <div class="address-display">
            <span class="address-text <?php echo empty($user['address']) ? 'empty' : ''; ?>" id="addressDisplay">
              <?php echo !empty($user['address']) ? h($user['address']) : 'No address set'; ?>
            </span>
            <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#addressModal">
              <i class="fas fa-edit mr-1"></i> Edit Address
            </button>
          </div>
        </div>
      </div>
    <?php elseif ($role === 'LGU Personnel'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-building"></i>
          <span>Personnel Details</span>
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-sitemap mr-1"></i> Department</label>
          <input type="text" class="form-control" value="<?php echo h($user['department_name'] ?? 'Not Assigned'); ?>" disabled>
        </div>
        
        <div class="info-note">
          <i class="fas fa-info-circle mr-2"></i>
          Your department assignment is managed by system administrators. Contact admin if you need to change departments.
        </div>
      </div>
    <?php elseif ($role === 'Admin'): ?>
      <div class="section-card">
        <div class="section-title">
          <i class="fas fa-user-shield"></i>
          <span>Administrator Information</span>
        </div>
        
        <div class="info-note">
          <i class="fas fa-info-circle mr-2"></i>
          As an administrator, your profile contains name and email information. Additional settings can be configured in the admin dashboard.
        </div>
      </div>
    <?php endif; ?>

    <div class="action-buttons">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-2"></i> Save Changes
      </button>
      <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#changePasswordModal">
        <i class="fas fa-lock mr-2"></i> Change Password
      </button>
    </div>
  </form>
</div>

<!-- Address Edit Modal -->
<div class="modal fade" id="addressModal" tabindex="-1" role="dialog" data-backdrop="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-map-marked-alt mr-2"></i>
          Edit Address
        </h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Province <span class="text-danger">*</span></label>
            <select id="provinceSelect" class="form-control" required>
              <option value="">-- Loading Provinces... --</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Municipality/City <span class="text-danger">*</span></label>
            <select id="municipalitySelect" class="form-control" required disabled>
              <option value="">-- Select Municipality --</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Barangay <span class="text-danger">*</span></label>
            <select id="barangaySelect" class="form-control" required disabled>
              <option value="">-- Select Barangay --</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Street/Purok <span class="text-danger">*</span></label>
            <input type="text" id="streetInput" class="form-control" placeholder="Enter street/purok name">
          </div>
        </div>
        <div class="form-group">
          <label>House Number / Building Name (Optional)</label>
          <input type="text" id="houseNumberInput" class="form-control" placeholder="e.g., Block 5 Lot 10">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-2"></i> Cancel
        </button>
        <button type="button" class="btn btn-success" id="saveAddressBtn">
          <i class="fas fa-check mr-2"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form id="changePasswordForm" class="modal-content">
      <div class="modal-header bg-gradient-blue text-white">
        <h5 class="modal-title" id="changePasswordModalLabel">
          <i class="fas fa-shield-alt mr-2"></i>
          Change Password
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      
      <div class="modal-body">
        <input type="hidden" name="auth_id" value="<?php echo $auth_id; ?>">
        
        <!-- Current Password -->
        <div class="form-group">
          <label for="currentPassword">
            <i class="fas fa-lock"></i>
            Current Password
          </label>
          <div class="input-group password-input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"></span>
            </div>
            <input type="password" 
                   name="current_password" 
                   id="currentPassword"
                   class="form-control" 
                   required 
                   placeholder="Enter your current password"
                   autocomplete="current-password">
            <div class="input-group-append">
              <button class="btn btn-toggle-password" 
                      type="button" 
                      onclick="togglePassword('currentPassword', this)"
                      aria-label="Toggle password visibility">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
        
        <!-- New Password -->
        <div class="form-group">
          <label for="newPassword">
            <i class="fas fa-key"></i>
            New Password
          </label>
          <div class="input-group password-input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"></span>
            </div>
            <input type="password" 
                   name="new_password" 
                   id="newPassword"
                   class="form-control" 
                   required 
                   placeholder="Enter new password"
                   autocomplete="new-password"
                   oninput="validatePassword()">
            <div class="input-group-append">
              <button class="btn btn-toggle-password" 
                      type="button" 
                      onclick="togglePassword('newPassword', this)"
                      aria-label="Toggle password visibility">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          
          <!-- Password Strength Indicator -->
          <div class="strength-container">
            <div class="strength-header">
              <span class="strength-label">Password Strength</span>
              <span id="strengthText"></span>
            </div>
            <div class="progress password-progress">
              <div id="strengthBar" 
                   class="progress-bar" 
                   role="progressbar" 
                   style="width: 0%"
                   aria-valuenow="0" 
                   aria-valuemin="0" 
                   aria-valuemax="100">
              </div>
            </div>
          </div>
          
          <!-- Password Requirements -->
          <div class="requirements-box">
            <div class="requirements-title">
              <i class="fas fa-info-circle"></i>
              Password Requirements
            </div>
            <div class="password-requirements">
              <small id="req-length">
                <i class="fas fa-circle"></i> At least 8 characters
              </small>
              <small id="req-number">
                <i class="fas fa-circle"></i> At least 1 number
              </small>
              <small id="req-uppercase">
                <i class="fas fa-circle"></i> At least 1 uppercase letter
              </small>
              <small id="req-lowercase">
                <i class="fas fa-circle"></i> At least 1 lowercase letter
              </small>
            </div>
          </div>
        </div>
        
        <!-- Confirm Password -->
        <div class="form-group" style="margin-bottom: 0;">
          <label for="confirmPassword">
            <i class="fas fa-check-circle"></i>
            Confirm New Password
          </label>
          <div class="input-group password-input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"></span>
            </div>
            <input type="password" 
                   name="confirm_password" 
                   id="confirmPassword"
                   class="form-control" 
                   required 
                   placeholder="Confirm new password"
                   autocomplete="new-password"
                   oninput="checkPasswordMatch()">
            <div class="input-group-append">
              <button class="btn btn-toggle-password" 
                      type="button" 
                      onclick="togglePassword('confirmPassword', this)"
                      aria-label="Toggle password visibility">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <small id="matchMessage"></small>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">
          <i class="fas fa-times mr-2"></i> Cancel
        </button>
        <button type="submit" id="submitBtn" class="btn btn-primary" disabled>
          <i class="fas fa-check mr-2"></i> Change Password
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Load jQuery and Bootstrap only if not already loaded -->
<script>
if (typeof jQuery === 'undefined') {
  document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}
if (typeof bootstrap === 'undefined') {
  document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>');
}
</script>

<script>
// Prevent multiple script execution when loaded via AJAX
if (typeof window.profileScriptsLoaded === 'undefined') {
  window.profileScriptsLoaded = true;

  // Only declare if not already declared
  if (typeof window.API_BASE === 'undefined') {
    window.API_BASE = 'https://psgc.gitlab.io/api';
  }
}

// Always make these functions available globally
const API_BASE = window.API_BASE;

// Auto-calculate age from birthday
$('input[name="birthday"]').on('change', function() {
  const birthday = new Date($(this).val());
  const today = new Date();
  const age = today.getFullYear() - birthday.getFullYear();
  const monthDiff = today.getMonth() - birthday.getMonth();
  
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
    $('input[name="age"]').val(age - 1);
  } else {
    $('input[name="age"]').val(age);
  }
});

// MOVE ALL FUNCTION DECLARATIONS OUTSIDE THE IF BLOCK
async function loadProvinces() {
  try {
    const response = await fetch(`${API_BASE}/provinces/`);
    const provinces = await response.json();
    
    provinces.sort((a, b) => a.name.localeCompare(b.name));
    
    const select = $('#provinceSelect');
    select.empty().append('<option value="">-- Select Province --</option>');
    
    provinces.forEach(province => {
      select.append(`<option value="${province.code}" data-name="${province.name}">${province.name}</option>`);
    });
  } catch (error) {
    console.error('Error loading provinces:', error);
    alert('Failed to load provinces. Please try again.');
  }
}

// Password toggle function
function togglePassword(fieldId, button) {
  const field = document.getElementById(fieldId);
  const icon = button.querySelector('i');
  
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

// Password validation
function validatePassword() {
  const password = document.getElementById('newPassword').value;
  const strengthBar = document.getElementById('strengthBar');
  const strengthText = document.getElementById('strengthText');
  
  // Check requirements
  const hasLength = password.length >= 8;
  const hasNumber = /\d/.test(password);
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  
  // Update requirement indicators
  updateRequirement('req-length', hasLength);
  updateRequirement('req-number', hasNumber);
  updateRequirement('req-uppercase', hasUppercase);
  updateRequirement('req-lowercase', hasLowercase);
  
  // Calculate strength
  let strength = 0;
  if (hasLength) strength++;
  if (hasNumber) strength++;
  if (hasUppercase) strength++;
  if (hasLowercase) strength++;
  if (password.length >= 12) strength++;
  if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
  
  // Update strength bar
  const percentage = (strength / 6) * 100;
  strengthBar.style.width = percentage + '%';
  
  if (strength <= 2) {
    strengthBar.className = 'progress-bar bg-danger';
    strengthText.textContent = 'Weak';
    strengthText.className = 'text-danger';
  } else if (strength <= 4) {
    strengthBar.className = 'progress-bar bg-warning';
    strengthText.textContent = 'Medium';
    strengthText.className = 'text-warning';
  } else {
    strengthBar.className = 'progress-bar bg-success';
    strengthText.textContent = 'Strong';
    strengthText.className = 'text-success';
  }
  
  checkPasswordMatch();
  updateSubmitButton();
}

function updateRequirement(elementId, met) {
  const element = document.getElementById(elementId);
  if (met) {
    element.classList.add('met');
  } else {
    element.classList.remove('met');
  }
}

function checkPasswordMatch() {
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const matchMessage = document.getElementById('matchMessage');
  
  if (confirmPassword.length > 0) {
    if (newPassword === confirmPassword) {
      matchMessage.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match';
      matchMessage.className = 'text-success d-block';
    } else {
      matchMessage.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Passwords do not match';
      matchMessage.className = 'text-danger d-block';
    }
  } else {
    matchMessage.innerHTML = '';
  }
  
  updateSubmitButton();
}

function updateSubmitButton() {
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const submitBtn = document.getElementById('submitBtn');
  
  // Check all requirements
  const hasLength = newPassword.length >= 8;
  const hasNumber = /\d/.test(newPassword);
  const hasUppercase = /[A-Z]/.test(newPassword);
  const hasLowercase = /[a-z]/.test(newPassword);
  const passwordsMatch = newPassword === confirmPassword && confirmPassword.length > 0;
  
  // Enable submit button only if all requirements are met
  if (hasLength && hasNumber && hasUppercase && hasLowercase && passwordsMatch) {
    submitBtn.disabled = false;
  } else {
    submitBtn.disabled = true;
  }
}

// Event handlers - bind once
$(document).ready(function() {
  // Unbind first to prevent duplicate bindings
  $('#addressModal').off('show.bs.modal');
  $('#provinceSelect').off('change');
  $('#municipalitySelect').off('change');
  $('#saveAddressBtn').off('click');
  $('#updateProfileForm').off('submit');
  $('#changePasswordForm').off('submit');
  $('#changePasswordModal').off('hidden.bs.modal');
  
  // Force select visibility on page load
  $('select.form-control').each(function() {
    $(this).css({
      'color': '#2d3748',
      'background-color': 'white',
      '-webkit-text-fill-color': '#2d3748'
    });
  });
  
  // Load provinces when modal opens
  $('#addressModal').on('show.bs.modal', function() {
    loadProvinces();
  });

  $('#provinceSelect').on('change', async function() {
    const provinceCode = $(this).val();
    const municipalitySelect = $('#municipalitySelect');
    const barangaySelect = $('#barangaySelect');
    
    // Reset dependent dropdowns
    municipalitySelect.empty().append('<option value="">-- Select Municipality --</option>').prop('disabled', !provinceCode);
    barangaySelect.empty().append('<option value="">-- Select Barangay --</option>').prop('disabled', true);
    
    if (!provinceCode) return;
    
    try {
      const response = await fetch(`${API_BASE}/provinces/${provinceCode}/cities-municipalities/`);
      const municipalities = await response.json();
      
      municipalities.sort((a, b) => a.name.localeCompare(b.name));
      
      municipalities.forEach(muni => {
        municipalitySelect.append(`<option value="${muni.code}" data-name="${muni.name}">${muni.name}</option>`);
      });
    } catch (error) {
      console.error('Error loading municipalities:', error);
      alert('Failed to load municipalities. Please try again.');
    }
  });

  $('#municipalitySelect').on('change', async function() {
    const muniCode = $(this).val();
    const barangaySelect = $('#barangaySelect');
    
    barangaySelect.empty().append('<option value="">-- Select Barangay --</option>').prop('disabled', !muniCode);
    
    if (!muniCode) return;
    
    try {
      const response = await fetch(`${API_BASE}/cities-municipalities/${muniCode}/barangays/`);
      const barangays = await response.json();
      
      barangays.sort((a, b) => a.name.localeCompare(b.name));
      
      barangays.forEach(brgy => {
        barangaySelect.append(`<option value="${brgy.code}" data-name="${brgy.name}">${brgy.name}</option>`);
      });
    } catch (error) {
      console.error('Error loading barangays:', error);
      alert('Failed to load barangays. Please try again.');
    }
  });

  // Save address button
  $('#saveAddressBtn').on('click', function() {
    const province = $('#provinceSelect option:selected').data('name');
    const municipality = $('#municipalitySelect option:selected').data('name');
    const barangay = $('#barangaySelect option:selected').data('name');
    const street = $('#streetInput').val().trim();
    const houseNumber = $('#houseNumberInput').val().trim();
    
    // Validation
    if (!province || !municipality || !barangay || !street) {
      alert('Please fill in all required fields (Province, Municipality, Barangay, and Street)');
      return;
    }
    
    // Build address string
    const addressParts = [houseNumber, street, barangay, municipality, province].filter(Boolean);
    const fullAddress = addressParts.join(', ');
    
    // Update display
    $('#addressDisplay').text(fullAddress).removeClass('empty');
    
    // Update hidden fields
    $('#hiddenProvince').val(province);
    $('#hiddenMunicipality').val(municipality);
    $('#hiddenBarangay').val(barangay);
    $('#hiddenStreet').val(street);
    $('#hiddenHouseNumber').val(houseNumber);
    
    // Close modal
    $('#addressModal').modal('hide');
  });

  // Profile update form submission
  $("#updateProfileForm").on("submit", function(e){
    e.preventDefault();
    $.ajax({
      url: '../update_profile.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(json){
        const cls = (json && json.status === 'success') ? 'success' : 'danger';
        const msg = (json && json.message) ? json.message : 'Unexpected error.';
        $('#ajaxAlert').html(`<div class="alert alert-${cls}"><i class="fas fa-${cls === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>${msg}</div>`);
        
        $('html, body').animate({
          scrollTop: $('#ajaxAlert').offset().top - 100
        }, 500);

        setTimeout(function() {
          $('#ajaxAlert').fadeOut(500, function() {
            $(this).html('').show();
          });
        }, 5000);
      },
      error: function(){
        $('#ajaxAlert').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Request failed.</div>');
      }
    });
  });

  // Password change form
  $("#changePasswordForm").on("submit", function(e){
    e.preventDefault();
    $.ajax({
      url: '../change_password.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(json){
        $('#changePasswordModal').modal('hide');
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right','');

        const cls = (json && json.status === 'success') ? 'success' : 'danger';
        const msg = (json && json.message) ? json.message : 'Unexpected error.';
        $('#ajaxAlert').html(`<div class="alert alert-${cls}"><i class="fas fa-${cls === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>${msg}</div>`);
        $('#changePasswordForm')[0].reset();

        $('html, body').animate({
          scrollTop: $('#ajaxAlert').offset().top - 100
        }, 500);

        setTimeout(function() {
          $('#ajaxAlert').fadeOut(500, function() {
            $(this).html('').show();
          });
        }, 5000);
      },
      error: function(){
        $('#changePasswordModal').modal('hide');
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right','');
        $('#ajaxAlert').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Request failed.</div>');
      }
    });
  });

  // Reset form when modal is closed
  $('#changePasswordModal').on('hidden.bs.modal', function () {
    document.getElementById('changePasswordForm').reset();
    document.getElementById('strengthBar').style.width = '0%';
    document.getElementById('strengthText').textContent = '';
    document.querySelectorAll('.password-requirements small').forEach(el => {
      el.classList.remove('met');
    });
    document.getElementById('matchMessage').innerHTML = '';
    document.getElementById('submitBtn').disabled = true;
  });
  
  // Fix modal backdrop issue when loaded in AJAX
  $('#addressModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
  });
});
</script>
</body>
</html>