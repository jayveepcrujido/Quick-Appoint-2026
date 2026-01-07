<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}
include '../includes/auto_mark_noshow.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident's Dashboard</title> 
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/resident.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1e293b;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Header Improvements */
        .header {
            background: linear-gradient(to right, #0D92F4, #27548A);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-top: -3rem;
        }

        .header_toggle i {
            font-size: 1.8rem;
            color: white;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .header_toggle i:hover {
            transform: scale(1.1);
        }

        /* --- Style for the notification dot --- */
        .notification-trigger {
            position: relative; /* This is the parent */
        }
        
        .notification-badge {
            position: absolute;
            top: -5px; /* Adjust as needed */
            right: -8px; /* Adjust as needed */
            width: 12px;
            height: 12px;
            background-color: var(--danger-color);
            border-radius: 50%;
            border: 2px solid white;
            display: none; /* Hidden by default, shown by JS */
        }
        /* --- End of notification dot style --- */

        .profile-trigger {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
        }

        .profile-trigger i {
            font-size: 2.5rem;
            color: white;
            transition: all 0.3s ease;
        }

        .profile-trigger:hover i {
            color: #f0f9ff;
        }

        .profile-dropdown {
            animation: slideDown 0.3s ease;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
            border-radius: 12px !important;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-dropdown .dropdown-item {
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 2px 5px;
        }

        .profile-dropdown .dropdown-item:hover {
            background: linear-gradient(to right, #0D92F4, #27548A);
            color: white !important;
            transform: translateX(5px);
        }

        .profile-dropdown .dropdown-item:hover i {
            color: white !important;
        }

        /* Enhanced Professional Sidebar Styles */
        
        /* Sidebar Container */
        .l-navbar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #0D92F4 0%, #1e5fa8 50%, #27548A 100%);
            padding: 1.5rem 0;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .l-navbar::-webkit-scrollbar {
            width: 6px;
        }

        .l-navbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .l-navbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .l-navbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .l-navbar.show {
            left: 0;
        }

        /* Sidebar Logo Section */
        #sidebar-logo {
            display: block;
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 8px;
        }

        #sidebar-logo:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
        }

        /* Sidebar Title */
        .l-navbar h4 {
            text-align: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 1rem 0 2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            padding-bottom: 1rem;
        }

        .l-navbar h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
            border-radius: 2px;
        }

        /* Navigation Container */
        .nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 1rem;
        }

        /* Navigation Links */
        .nav_link {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* Hover Effect Background */
        .nav_link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 0;
        }

        .nav_link:hover::before {
            transform: translateX(0);
        }

        /* Left Border Accent on Hover */
        .nav_link::after {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: white;
            border-radius: 0 2px 2px 0;
            transition: height 0.3s ease;
        }

        .nav_link:hover::after {
            height: 60%;
        }

        .nav_link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Active State */
        .nav_link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .nav_link.active::after {
            height: 60%;
        }

        /* Icon Styling */
        .nav_link i {
            font-size: 1.5rem;
            margin-right: 1rem;
            min-width: 24px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .nav_link:hover i {
            transform: scale(1.15) rotate(5deg);
        }

        /* Text Styling */
        .nav_link span {
            position: relative;
            z-index: 1;
            white-space: nowrap;
        }

        /* Dropdown Submenu */
        .dropdown-submenu {
            padding-left: 0;
            overflow: hidden;
        }

        .sub_link {
            padding: 0.75rem 1.25rem 0.75rem 3.5rem !important;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.75);
        }

        .sub_link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Special Styling for Logout Link */
        .nav_link[data-toggle="modal"] {
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 1.5rem;
        }

        .nav_link[data-toggle="modal"]:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
        }

        /* Content Area */
        .content-area {
            padding: 1rem 1rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 768px) {
            .content-area {
                padding: 2.5rem 2rem;
            }
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(to right, #0D92F4, #27548A);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            color: white;
            box-shadow: var(--card-shadow-hover);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .welcome-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
            margin-bottom: 0;
        }

        @media (max-width: 576px) {
            .welcome-card {
                padding: 1.5rem 1rem;
            }
            .welcome-card h2 {
                font-size: 1.5rem;
            }
            .welcome-card p {
                font-size: 1rem;
            }
        }

        /* Section Title */
        .section-title {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-color);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .icon-primary {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: var(--primary-color);
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: var(--secondary-color);
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: var(--warning-color);
        }

        .icon-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--danger-color);
        }

        .feature-card h5 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .feature-card small {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Responsive Grid */
        @media (max-width: 767px) {
            .feature-card {
                margin-bottom: 1rem;
            }
        }

        /* Animation for cards on load */
        .feature-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }

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

        /* Logout Modal Improvements */
        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: none;
            padding: 1.5rem;
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .modal-body {
            padding: 2rem;
            text-align: center;
        }

        .modal-body i {
            font-size: 3rem;
            color: var(--warning-color);
            margin-bottom: 1rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1.5rem;
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Overlay Enhancement */
        .overlay {
            backdrop-filter: blur(3px);
            transition: all 0.3s ease;
        }
        /* --- Start of Infographic Styles --- */
        .process-wrapper {
            background: white;
            border-radius: 16px;
            padding: 2rem 1rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            position: relative;
        }

        .process-title {
            text-align: center;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 2rem;
            font-size: 1.25rem;
        }

        .step-item {
            text-align: center;
            position: relative;
            z-index: 1;
            padding: 0 10px;
        }

        .step-icon-circle {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
            transition: transform 0.3s ease;
        }

        .step-item:hover .step-icon-circle {
            transform: scale(1.1);
            background: var(--primary-color);
            color: white;
        }

        .step-text {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .step-desc {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.4;
        }

        @media (min-width: 992px) {
            .step-item::after {
                content: '';
                position: absolute;
                top: 30px;
                left: 50%;
                width: 100%;
                height: 3px;
                background: #e2e8f0;
                z-index: -1;
            }
            
            .step-item:last-child::after {
                display: none;
            }
        }
        
        @media (max-width: 991px) {
            .step-item {
                margin-bottom: 2rem;
            }
            .step-item:last-child {
                margin-bottom: 0;
            }
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const toggle = document.getElementById('header-toggle');
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                nav.classList.toggle('show');
                overlay.classList.toggle('show');
            });

            overlay.addEventListener('click', () => {
                nav.classList.remove('show');
                overlay.classList.remove('show');
            });

            document.addEventListener('click', (e) => {
                if (nav.classList.contains('show') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });

            // --- New Notification Check ---
            function checkNotifications() {
                $.ajax({
                    url: 'check_notifications.php', // Path to your new backend script
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.unreadCount > 0) {
                            $('#notificationBadge').show(); // Show the dot
                        } else {
                            $('#notificationBadge').hide(); // Hide the dot
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Failed to check notifications: " + error);
                    }
                });
            }

            // Check for notifications on page load
            checkNotifications();

            // Optionally, check for new notifications every 60 seconds
            setInterval(checkNotifications, 60000);
            // --- End of New Notification Check ---

        });

    function loadContent(page) {
        console.log("Attempting to load: " + page); // Debug line

        // --- ADDED ---
        // If user clicks on notifications, hide badge immediately
        // The backend of resident_notifications.php should mark them as read
        if (page === 'resident_notifications.php') {
            $('#notificationBadge').hide();
        }
        // --- END ---

        $("#content-area").fadeOut(200, function() {
            $(this).load(page, function(response, status, xhr) {
                if (status == "error") {
                    console.log("Error loading page: " + xhr.status + " " + xhr.statusText);
                    console.log("Full URL attempted: " + page);
                    alert("Failed to load content. Error: " + xhr.status + " - " + xhr.statusText);
                } else {
                    console.log("Page loaded successfully!");
                    $(this).fadeIn(200);
                }
            });
        });
    }

        function toggleProfileMenu() {
            const menu = document.getElementById("profileMenu");
            menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
        }

        window.addEventListener('click', function(e) {
            const trigger = document.querySelector('.profile-trigger');
            const menu = document.getElementById("profileMenu");
            if (trigger && menu && !trigger.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = "none";
            }
        });

        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }
    </script>
</head>
<body id="body-pd">
    <!-- Header -->
    <header class="header" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>

<div class="d-flex align-items-center">
        <!-- MODIFIED: Parent div needs to be relative -->
        <div class="position-relative d-inline-block mr-3" style="z-index: 1050;">
            <div class="notification-trigger" onclick="loadContent('resident_notifications.php')" title="Notifications">
                <i class='bx bx-bell' style="font-size: 24px; cursor: pointer; color: #ffffffff;"></i>
                <!-- This span is the notification dot. It's empty but styled with CSS -->
                <span id="notificationBadge" class="notification-badge"></span>
            </div>
        </div>

        <div class="position-relative d-inline-block" style="z-index: 1050;">
            <div class="profile-trigger" onclick="toggleProfileMenu()" title="My Profile">
                <i class='bx bx-user-circle'></i>
            </div>

            <div id="profileMenu" class="profile-dropdown bg-white shadow border rounded position-absolute py-2"
                style="display: none; min-width: 220px; top: 100%; right: 0; z-index: 2000;">
                <a href="#" onclick="loadContent('../view_profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
                    <i class='bx bx-id-card mr-2'></i> View Profile
                </a>
                <a href="#" onclick="loadContent('../profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
                    <i class='bx bx-edit-alt mr-2'></i> Edit Profile
                </a>
                <div class="dropdown-divider my-1"></div>
                <a href="#" class="dropdown-item px-3 py-2 d-flex align-items-center text-danger" data-toggle="modal" data-target="#logoutModal">
                    <i class='bx bx-log-out-circle mr-2'></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <a href="residents_dashboard.php" style="display: block; cursor: pointer;">
            <img src="../assets/images/Unisan_Logo.png" id="sidebar-logo" alt="Sidebar Logo" class="header_img" style="cursor: pointer;">
        </a>
        <h4 style="text-align: center; color: white;">Residents Menu</h4>
        <nav class="nav">
            <a href="javascript:void(0);" class="nav_link" onclick="toggleDropdown('appointmentDropdown')">
                <i class='bx bx-calendar'></i> <span>My Appointments</span> <i class='bx bx-chevron-down ml-auto'></i>
            </a>
            <div id="appointmentDropdown" class="dropdown-submenu" style="display:none;">
                <a href="#" class="nav_link sub_link" onclick="loadContent('residents_pending_appointments.php')">Pending Appointments</a>
                <a href="#" class="nav_link sub_link" onclick="loadContent('residents_completed_appointments.php')">Completed Appointments</a>
                <a href="#" class="nav_link sub_link" onclick="loadContent('residents_noshow_appointments.php')">No Show Appointments</a>
            </div>
            <a href="#" class="nav_link" onclick="loadContent('residents_view_departments.php')">
                <i class='bx bx-user'></i> <span>Book an Appointment</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('residents_completed_appointments.php')">
                <i class='bx bx-message-square'></i> <span>Submit Feedback</span>
            </a>
            <a href="#" data-toggle="modal" data-target="#logoutModal" class="nav_link">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>

    <!-- Content Area -->
    <div class="content-area" id="content-area">
        <div class="container-fluid">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>
                    <i class='bx bx-home-alt mr-2'></i>
                    Welcome Back, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Resident'; ?>!
                </h2>
                <p>Manage your appointments, explore departments, and share your feedback all in one place.</p>
            </div>

            <div class="process-wrapper">
                <h4 class="process-title">How to Book an Appointment</h4>
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-6 offset-lg-1 step-item">
                        <div class="step-icon-circle">
                            <i class='bx bx-menu'></i>
                        </div>
                        <div class="step-text">1. Open Menu</div>
                        <div class="step-desc">Click the toggle button</div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 step-item">
                        <div class="step-icon-circle">
                            <i class='bx bx-user'></i>
                        </div>
                        <div class="step-text">2. Book an Appointment</div>
                        <div class="step-desc">Click "View Departments"</div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 step-item">
                        <div class="step-icon-circle">
                            <i class='bx bx-mouse-alt'></i>
                        </div>
                        <div class="step-text">3. Select Service</div>
                        <div class="step-desc">Choose the service you need</div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 step-item">
                        <div class="step-icon-circle">
                            <i class='bx bx-calendar-event'></i>
                        </div>
                        <div class="step-text">4. Pick Date</div>
                        <div class="step-desc">Select date on calendar</div>
                    </div>

                    <div class="col-lg-2 col-md-4 offset-md-4 offset-lg-0 col-12 step-item">
                        <div class="step-icon-circle">
                            <i class='bx bx-check-double'></i>
                        </div>
                        <div class="step-text">5. Confirm</div>
                        <div class="step-desc">Click "Book Now"</div>
                    </div>
                </div>
            </div>

            <!-- Section Title -->
            <h3 class="section-title">Quick Access Menu</h3>

            <!-- Feature Cards Grid -->
            <div class="row">
                <!-- View Appointments -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card" onclick="loadContent('residents_pending_appointments.php')">
                        <div class="feature-icon icon-primary">
                            <i class='bx bx-calendar'></i>
                        </div>
                        <h5>View Appointments</h5>
                        <small>Check status and details of your past or upcoming appointments</small>
                    </div>
                </div>

                <!-- View Departments -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card" onclick="loadContent('residents_view_departments.php')">
                        <div class="feature-icon icon-success">
                            <i class='bx bx-building-house'></i>
                        </div>
                        <h5>View Departments</h5>
                        <small>Explore available departments and request new appointments</small>
                    </div>
                </div>

                <!-- Submit Feedback -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card" onclick="loadContent('residents_completed_appointments.php')">
                        <div class="feature-icon icon-warning">
                            <i class='bx bx-message-square-dots'></i>
                        </div>
                        <h5>Resident Feedback</h5>
                        <small>Share your experience and help us improve our services</small>
                    </div>
                </div>

                <!-- Logout -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card" data-toggle="modal" data-target="#logoutModal">
                        <div class="feature-icon icon-danger">
                            <i class='bx bx-log-out'></i>
                        </div>
                        <h5>Logout</h5>
                        <small>Safely logout of your account and end your session</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="bx bx-log-out-circle mr-2"></i>Confirm Logout
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <i class='bx bx-error-circle'></i>
                    <p class="mt-3 mb-0">Are you sure you want to log out?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
