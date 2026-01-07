<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['auth_id'])) {
    echo "<div class='alert alert-danger'>Unauthorized access.</div>";
    exit();
}

$auth_id = $_SESSION['auth_id'];
$role = $_SESSION['role'];

if ($role === 'Admin') {
    $stmt = $pdo->prepare("
        SELECT a.email, ad.first_name, ad.middle_name, ad.last_name, ad.created_at
        FROM auth a
        JOIN admins ad ON a.id = ad.auth_id
        WHERE a.id = ?
    ");
} elseif ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("
        SELECT a.email, p.first_name, p.middle_name, p.last_name, p.department_id, p.created_at
        FROM auth a
        JOIN lgu_personnel p ON a.id = p.auth_id
        WHERE a.id = ?
    ");
} elseif ($role === 'Resident') {
    $stmt = $pdo->prepare("
        SELECT a.email, r.first_name, r.middle_name, r.last_name, r.address, r.birthday, r.age, 
               r.sex, r.civil_status, r.phone_number, r.created_at
        FROM auth a
        JOIN residents r ON a.id = r.auth_id
        WHERE a.id = ?
    ");
} else {
    echo "<div class='alert alert-danger'>Invalid role.</div>";
    exit();
}

$stmt->execute([$auth_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    exit();
}

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
    
    .profile-card {
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      overflow: hidden;
      animation: fadeInUp 0.6s ease;
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .profile-header {
      background: linear-gradient(135deg,  #2c3e50 0%, #3498db 100%);
      padding: 40px 30px;
      text-align: center;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .profile-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      animation: pulse 15s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }
    
    .profile-avatar {
      width: 120px;
      height: 120px;
      background: rgba(255,255,255,0.2);
      border: 4px solid rgba(255,255,255,0.3);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      backdrop-filter: blur(10px);
      position: relative;
      z-index: 1;
    }
    
    .profile-avatar i {
      font-size: 70px;
      color: white;
    }
    
    .profile-name {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 5px;
      position: relative;
      z-index: 1;
    }
    
    .profile-role {
      font-size: 16px;
      opacity: 0.9;
      font-weight: 300;
      position: relative;
      z-index: 1;
    }
    
    .profile-badge {
      display: inline-block;
      padding: 6px 16px;
      background: rgba(255,255,255,0.2);
      border-radius: 20px;
      font-size: 14px;
      margin-top: 10px;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.3);
      position: relative;
      z-index: 1;
    }
    
    .profile-body {
      padding: 40px;
    }
    
    .info-section {
      margin-bottom: 30px;
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #3498db;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #f0f0f0;
      display: flex;
      align-items: center;
    }
    
    .section-title i {
      margin-right: 10px;
      font-size: 22px;
    }
    
    .info-row {
      display: flex;
      padding: 15px 0;
      border-bottom: 1px solid #f5f5f5;
      transition: background 0.3s ease;
    }
    
    .info-row:hover {
      background: #f8f9fa;
      padding-left: 10px;
      border-radius: 8px;
    }
    
    .info-row:last-child {
      border-bottom: none;
    }
    
    .info-label {
      flex: 0 0 35%;
      font-weight: 600;
      color: #555;
      display: flex;
      align-items: center;
    }
    
    .info-label i {
      margin-right: 8px;
      color: #3498db;
      font-size: 18px;
    }
    
    .info-value {
      flex: 1;
      color: #333;
      word-break: break-word;
    }
    
    .btn-back {
      display: inline-block;
      margin: 20px 0;
      padding: 10px 20px;
      background: #3498db;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      transition: background 0.3s ease;
    }
    
    .btn-back:hover {
      background: #2980b9;
      color: white;
      text-decoration: none;
    }
    
    @media (max-width: 768px) {
      .profile-body {
        padding: 25px;
      }
      
      .info-row {
        flex-direction: column;
      }
      
      .info-label {
        margin-bottom: 5px;
      }
      
      .profile-name {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="profile-container">
    <div class="profile-card">
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar">
          <i class='bx bxs-user-circle'></i>
        </div>
        <h1 class="profile-name"><?= htmlspecialchars($full_name ?: 'User Profile') ?></h1>
        <p class="profile-role"><?= htmlspecialchars($role) ?></p>
        <span class="profile-badge">
          <i class='bx bxs-badge-check'></i> Verified Account
        </span>
      </div>
      
      <!-- Profile Body -->
      <div class="profile-body">
        <!-- Account Information -->
        <div class="info-section">
          <div class="section-title">
            <i class='bx bxs-user-detail'></i>
            Account Information
          </div>
          
          <?php if (isset($user['email'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-envelope'></i>
              Email Address
            </div>
            <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['phone_number'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-phone'></i>
              Phone Number
            </div>
            <div class="info-value"><?= htmlspecialchars($user['phone_number']) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['created_at'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-calendar'></i>
              Member Since
            </div>
            <div class="info-value"><?= date('F d, Y', strtotime($user['created_at'])) ?></div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Personal Information -->
        <?php if ($role === 'Resident' || $role === 'LGU Personnel'): ?>
        <div class="info-section">
          <div class="section-title">
            <i class='bx bxs-user-pin'></i>
            Personal Information
          </div>
          
          <?php if (isset($user['department_id'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-buildings'></i>
              Department ID
            </div>
            <div class="info-value"><?= htmlspecialchars($user['department_id']) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['address'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-map'></i>
              Address
            </div>
            <div class="info-value"><?= htmlspecialchars($user['address']) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['birthday'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-cake'></i>
              Birthday
            </div>
            <div class="info-value"><?= date('F d, Y', strtotime($user['birthday'])) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['age'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-time'></i>
              Age
            </div>
            <div class="info-value"><?= htmlspecialchars($user['age']) ?> years old</div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['sex'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-male-female'></i>
              Sex
            </div>
            <div class="info-value"><?= htmlspecialchars($user['sex']) ?></div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($user['civil_status'])): ?>
          <div class="info-row">
            <div class="info-label">
              <i class='bx bx-heart'></i>
              Civil Status
            </div>
            <div class="info-value"><?= htmlspecialchars($user['civil_status']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>