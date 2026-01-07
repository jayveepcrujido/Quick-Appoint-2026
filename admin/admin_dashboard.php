<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include_once '../includes/auto_mark_noshow.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
body {
    background: #f8f9fa;
}

.content-area {
    padding-top: 20px;
    animation: fadeIn 0.5s ease-in;
}

.welcome-banner {
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-banner h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
}

.welcome-banner p {
    font-size: 1.1rem;
    opacity: 0.95;
    margin-bottom: 0;
    position: relative;
    z-index: 1;
}

.dashboard-card {
    border: none;
    border-radius: 12px;
    transition: all 0.3s ease;
    height: 100%;
    background: white;
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: currentColor;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.dashboard-card:hover::before {
    opacity: 1;
}

.card-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    transition: all 0.3s ease;
}

.dashboard-card:hover .card-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.card-icon-wrapper i {
    font-size: 26px;
}

.card-content h5 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: #2d3748;
}

.card-content small {
    color: #718096;
    font-size: 0.875rem;
}

.card-primary { color: #667eea; }
.card-primary .card-icon-wrapper { background: rgba(102, 126, 234, 0.1); }

.card-success { color: #48bb78; }
.card-success .card-icon-wrapper { background: rgba(72, 187, 120, 0.1); }

.card-warning { color: #f6ad55; }
.card-warning .card-icon-wrapper { background: rgba(246, 173, 85, 0.1); }

.card-danger { color: #fc8181; }
.card-danger .card-icon-wrapper { background: rgba(252, 129, 129, 0.1); }

.card-info { color: #4299e1; }
.card-info .card-icon-wrapper { background: rgba(66, 153, 225, 0.1); }

.card-secondary { color: #a0aec0; }
.card-secondary .card-icon-wrapper { background: rgba(160, 174, 192, 0.1); }

.card-dark { color: #2d3748; }
.card-dark .card-icon-wrapper { background: rgba(45, 55, 72, 0.1); }

.stats-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.stats-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.section-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: #1a202c;
    margin-top: 2rem;
    margin-bottom: 1.25rem;
    position: relative;
    padding-bottom: 0.5rem;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 2px;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    border-radius: 8px;
}

.spinner-border {
    border-width: 3px;
}

@media (max-width: 768px) {
    .welcome-banner {
        padding: 1.5rem;
    }
    
    .welcome-banner h2 {
        font-size: 1.5rem;
    }
    
    .welcome-banner p {
        font-size: 1rem;
    }
    
    .card-icon-wrapper {
        width: 50px;
        height: 50px;
    }
    
    .card-icon-wrapper i {
        font-size: 24px;
    }
    
    .card-content h5 {
        font-size: 1rem;
    }
    
    .section-title {
        font-size: 1.25rem;
    }
}

@media (max-width: 576px) {
    .dashboard-card {
        margin-bottom: 1rem;
    }
    
    .card-icon-wrapper {
        margin-bottom: 0.5rem;
    }
    
    .dashboard-card .d-flex {
        flex-direction: column;
        text-align: center;
    }
    
    .dashboard-card .d-flex.align-items-center {
        align-items: center !important;
    }
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

.flatpickr-calendar {
    z-index: 9999 !important;
}

body > .flatpickr-calendar:not(.open) {
    display: none !important;
}

.flatpickr-calendar:not(.open):not(.inline) {
    display: none !important;
    visibility: hidden !important;
}
    </style>

<script>
    window.pageCleanupRegistry = {
        intervals: [],
        timeouts: [],
        eventHandlers: [],
        ajaxRequests: []
    };

    const originalSetInterval = window.setInterval;
    window.setInterval = function(fn, delay) {
        const id = originalSetInterval(fn, delay);
        window.pageCleanupRegistry.intervals.push(id);
        return id;
    };

    const originalSetTimeout = window.setTimeout;
    window.setTimeout = function(fn, delay) {
        const id = originalSetTimeout(fn, delay);
        window.pageCleanupRegistry.timeouts.push(id);
        return id;
    };

    if (typeof jQuery !== 'undefined') {
        $(document).ajaxSend(function(event, jqXHR, settings) {
            window.pageCleanupRegistry.ajaxRequests.push(jqXHR);
        });
    }

    window.cleanupAllPages = function() {
        console.log('=== STARTING UNIVERSAL CLEANUP ===');
        
        console.log('Clearing', window.pageCleanupRegistry.intervals.length, 'intervals');
        window.pageCleanupRegistry.intervals.forEach(function(id) {
            clearInterval(id);
        });
        window.pageCleanupRegistry.intervals = [];
        
        console.log('Clearing', window.pageCleanupRegistry.timeouts.length, 'timeouts');
        window.pageCleanupRegistry.timeouts.forEach(function(id) {
            clearTimeout(id);
        });
        window.pageCleanupRegistry.timeouts = [];
        
        console.log('Aborting', window.pageCleanupRegistry.ajaxRequests.length, 'AJAX requests');
        window.pageCleanupRegistry.ajaxRequests.forEach(function(jqXHR) {
            if (jqXHR && jqXHR.abort) {
                try {
                    jqXHR.abort();
                } catch(e) {
                    console.warn('Error aborting AJAX request:', e);
                }
            }
        });
        window.pageCleanupRegistry.ajaxRequests = [];
        
        if (window.availableDatesCleanup && typeof window.availableDatesCleanup === 'function') {
            console.log('Running availableDatesCleanup...');
            try {
                window.availableDatesCleanup();
            } catch(e) {
                console.error('Error in availableDatesCleanup:', e);
            }
        }
        
        if (window.appointmentStatusCleanup && typeof window.appointmentStatusCleanup === 'function') {
            console.log('Running appointmentStatusCleanup...');
            try {
                window.appointmentStatusCleanup();
            } catch(e) {
                console.error('Error in appointmentStatusCleanup:', e);
            }
        }
        
        if (window.manageAppointmentsCleanup && typeof window.manageAppointmentsCleanup === 'function') {
            console.log('Running manageAppointmentsCleanup...');
            try {
                window.manageAppointmentsCleanup();
            } catch(e) {
                console.error('Error in manageAppointmentsCleanup:', e);
            }
        }
        
        if (window.feedbackPageCleanup && typeof window.feedbackPageCleanup === 'function') {
            console.log('Running feedbackPageCleanup...');
            try {
                window.feedbackPageCleanup();
            } catch(e) {
                console.error('Error in feedbackPageCleanup:', e);
            }
        }
        
        if (window.adminFeedbackPageCleanup && typeof window.adminFeedbackPageCleanup === 'function') {
            console.log('Running adminFeedbackPageCleanup...');
            try {
                window.adminFeedbackPageCleanup();
            } catch(e) {
                console.error('Error in adminFeedbackPageCleanup:', e);
            }
        }
        
        if (typeof jQuery !== 'undefined' && jQuery) {
            const namespaces = ['availDates', 'appointmentStatus', 'manageAppt', 'analytics', 'feedback'];
            namespaces.forEach(function(ns) {
                console.log('Removing event handlers for namespace:', ns);
                $(document).off('.' + ns);
            });
            
            try {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                
                if (typeof $.fn.modal !== 'undefined') {
                    $('.modal').modal('hide');
                } else {
                    $('.modal').hide();
                    $('.modal').attr('aria-hidden', 'true');
                }
            } catch(e) {
                console.warn('Error cleaning up modals:', e);
            }
            
            $('body').css('overflow', '');
            $('body').css('padding-right', '');
        }
        
        console.log('Force removing all Flatpickr calendars...');
        document.querySelectorAll('.flatpickr-calendar').forEach(function(calendar) {
            console.log('Removing Flatpickr calendar:', calendar);
            calendar.remove();
        });
        
        console.log('=== CLEANUP COMPLETE ===');
    };

    let currentPage = null;
    let isLoading = false;

    document.addEventListener("DOMContentLoaded", function () {
        const toggle = document.getElementById('header-toggle');
        const nav = document.getElementById('nav-bar');
        const overlay = document.getElementById('overlay');

        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                nav.classList.toggle('show');
                overlay.classList.toggle('show');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                nav.classList.remove('show');
                overlay.classList.remove('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (nav && nav.classList.contains('show') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    });

    function loadContent(page) {
        console.log('\n>>> NAVIGATION REQUEST: ' + page + ' <<<');
        
        if (isLoading) {
            console.warn('⚠️ Already loading content, please wait...');
            return false;
        }

        if (currentPage === page) {
            console.log('ℹ️ Already on page:', page);
            return false;
        }

        console.log('✓ Navigation approved from', currentPage || 'homepage', 'to', page);
        isLoading = true;
        
        const nav = document.getElementById('nav-bar');
        const overlay = document.getElementById('overlay');
        if (nav && nav.classList.contains('show')) {
            nav.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
        
        console.log('🧹 Initiating comprehensive cleanup...');
        window.cleanupAllPages();
        
        const cacheBuster = '?_=' + new Date().getTime();
        
        const contentArea = $("#content-area");
        contentArea.html(
            '<div class="text-center p-5">' +
            '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">' +
            '<span class="sr-only">Loading...</span>' +
            '</div>' +
            '<p class="mt-3 font-weight-bold">Loading ' + page + '...</p>' +
            '</div>'
        );
        
        setTimeout(function() {
            console.log('📥 Loading content from:', page + cacheBuster);
            
            contentArea.load(page + cacheBuster, function(response, status, xhr) {
                isLoading = false;
                
                if (status === "error") {
                    console.error("❌ Error loading content:", xhr.status, xhr.statusText);
                    currentPage = null;
                } else if (status === "success") {
                    console.log('✅ Content loaded successfully:', page);
                    currentPage = page;
                    
                    $('html, body').animate({ scrollTop: 0 }, 300);
                    
                    setTimeout(function() {
                        console.log('⚙️ Content initialization phase complete for:', page);
                        
                        if (page.includes('personnel_view_feedbacks.php')) {
                            console.log('📋 Initializing personnel feedback page...');
                            if (typeof window.initFeedbackPage === 'function') {
                                window.initFeedbackPage();
                            }
                        }
                        
                        if (page.includes('admin_view_feedback.php')) {
                            console.log('📋 Initializing admin feedback page...');
                            if (typeof window.initAdminFeedbackPage === 'function') {
                                window.initAdminFeedbackPage();
                            }
                        }
                        
                        if (page.includes('personnel_view_appointments_status.php')) {
                            console.log('📅 Initializing appointment status page...');
                            if (typeof window.initAppointmentStatusPage === 'function') {
                                window.initAppointmentStatusPage();
                            }
                        }
                        
                        if (page.includes('analytics')) {
                            console.log('📊 Initializing analytics page...');
                            if (typeof initializeCharts === 'function') {
                                initializeCharts();
                            }
                        }
                        
                        if (page.includes('create_available_dates.php')) {
                            console.log('📅 Verifying calendar initialization...');
                            
                            setTimeout(function() {
                                if ($('#calendar').length > 0) {
                                    if ($('#calendar').children().length === 0) {
                                        console.warn('⚠️ Calendar empty, attempting manual init...');
                                        if (window.availableDatesModule && 
                                            typeof window.availableDatesModule.generateCalendar === 'function') {
                                            const now = new Date();
                                            window.availableDatesModule.generateCalendar(now.getMonth(), now.getFullYear());
                                            window.availableDatesModule.loadExistingDates();
                                            window.availableDatesModule.checkSubmitButton();
                                        }
                                    } else {
                                        console.log('✓ Calendar initialized with', $('#calendar').children().length, 'elements');
                                    }
                                }
                            }, 300);
                        }
                    }, 200);
                }
            });
        }, 150);
        
        return false;
    }

    function toggleDropdown(id) {
        $("#" + id).slideToggle("fast");
    }

    function toggleProfileMenu() {
        const menu = document.getElementById("profileMenu");
        if (menu) {
            menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
        }
    }

    window.addEventListener('click', function(e) {
        const trigger = document.querySelector('.profile-trigger');
        const menu = document.getElementById("profileMenu");
        if (trigger && menu && !trigger.contains(e.target) && !menu.contains(e.target)) {
            menu.style.display = "none";
        }
    });

    window.addEventListener('popstate', function(event) {
        if (currentPage) {
            console.log('⏮️ Browser navigation detected');
            const pageToLoad = currentPage;
            currentPage = null;
            loadContent(pageToLoad);
        }
    });

    window.addEventListener('beforeunload', function() {
        console.log('🚪 Page unloading, running final cleanup...');
        window.cleanupAllPages();
    });

    window.addEventListener('error', function(e) {
        console.error('💥 Global error:', e.message, 'at', e.filename + ':' + e.lineno);
    });

    window.debugLoadContent = function() {
        console.log('=== DEBUG INFO ===');
        console.log('Current page:', currentPage);
        console.log('Is loading:', isLoading);
        console.log('Intervals tracked:', window.pageCleanupRegistry.intervals.length);
        console.log('Timeouts tracked:', window.pageCleanupRegistry.timeouts.length);
        console.log('AJAX requests tracked:', window.pageCleanupRegistry.ajaxRequests.length);
        console.log('==================');
    };
</script>
</head>
<body id="body-pd">
<header class="header" id="header">
    <div class="header_toggle"> 
        <i class='bx bx-menu' id="header-toggle"></i> 
    </div>

    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none" id="profileDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class='bx bx-user-circle text-light' style="font-size: 40px; cursor:pointer;"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="profileDropdown">
            <a href="#" class="dropdown-item d-flex align-items-center" onclick="loadContent('../view_profile.php')">
                <i class='bx bx-id-card mr-2'></i> View Profile
            </a>
            <a href="#" class="dropdown-item d-flex align-items-center" onclick="loadContent('../profile.php')">
                <i class='bx bx-edit-alt mr-2'></i> Edit Profile
            </a>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item text-danger d-flex align-items-center" data-toggle="modal" data-target="#logoutModal">
                <i class='bx bx-log-out-circle mr-2'></i> Logout
            </a>
        </div>
    </div>
</header>

    <div class="l-navbar" id="nav-bar">
        <a href="admin_dashboard.php" style="display: block; cursor: pointer;">
            <img src="../assets/images/Unisan_logo.png" id="sidebar-logo" alt="Sidebar Logo" class="header_img" style="cursor: pointer;">
        </a>
        <h4 style="text-align: center; color: white;">Admin Menu</h4>
        <nav class="nav">
            <a href="admin_dashboard.php" class="nav_link">
                <i class='bx bx-home-alt'></i> <span>Dashboard</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_analytics.php')">
                <i class='bx bx-dashboard'></i> <span>Analytics</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_GeoMap.php')">
                <i class='bx bx-location-pin'></i> <span>Appointments GeoMap</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_create_lgu_personnel.php')">
                <i class='bx bx-user-plus'></i> <span>Manage LGU Personnel</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_manage_departments.php')">
                <i class='bx bx-building-house'></i> <span>Manage Department</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_view_appointments.php')">
                <i class='bx bx-calendar-event'></i> <span>All Appointments</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_manage_residents_accounts.php')">
                <i class='bx bx-group'></i> <span>Manage Residents Accounts</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_view_feedback.php')">
                <i class='bx bx-message'></i> <span>View Feedbacks</span>
            </a>
            <a href="#" class="nav_link" data-toggle="modal" data-target="#logoutModal">
                <i class='bx bx-arrow-out-right-circle-half'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>

    <div class="content-area" id="content-area">
        <div class="container-fluid px-3 px-md-4 py-4">
            <div class="welcome-banner">
                <h2><i class='bx bx-grid-alt mr-2'></i>Welcome Back, 
                    <?php 
                    if (isset($_SESSION['user_name'])) {
                        echo htmlspecialchars($_SESSION['user_name']);
                    } else {
                        echo 'Admin';
                    }
                    ?>!
                </h2>
                <p>Manage your municipality's operations efficiently from your dashboard</p>
            </div>

            <h3 class="section-title">Quick Actions</h3>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_analytics.php')">
                        <div class="dashboard-card analytics-card p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-dashboard'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Analytics Dashboard</h5>
                                    <small>View comprehensive system analytics and insights</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-12 col-md-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_GeoMap.php')">
                        <div class="dashboard-card card-info p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-map'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Appointments GeoMap</h5>
                                    <small>View appointment locations on interactive map</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <h3 class="section-title mt-4">Management</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_create_lgu_personnel.php')">
                        <div class="dashboard-card card-primary p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-user-plus'></i>
                                </div>
                                <div class="card-content">
                                    <h5>LGU Personnel</h5>
                                    <small>Create and manage personnel accounts</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_manage_departments.php')">
                        <div class="dashboard-card card-success p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-building-house'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Departments</h5>
                                    <small>Organize and manage departments</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_manage_residents_accounts.php')">
                        <div class="dashboard-card card-info p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-group'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Resident Accounts</h5>
                                    <small>Monitor and manage resident users</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <h3 class="section-title mt-4">Services & Feedback</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_view_appointments.php')">
                        <div class="dashboard-card card-danger p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-calendar-event'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Appointments</h5>
                                    <small>Track and manage all appointments</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_view_feedback.php')">
                        <div class="dashboard-card card-warning p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-message'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Feedback Center</h5>
                                    <small>Review service, personnel, and system feedback</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <h3 class="section-title mt-4">System</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <div class="dashboard-card card-secondary p-3 p-md-4" data-toggle="modal" data-target="#logoutModal" style="cursor:pointer;">
                        <div class="d-flex align-items-center">
                            <div class="card-icon-wrapper">
                                <i class='bx bx-arrow-out-right-circle-half'></i>
                            </div>
                            <div class="card-content">
                                <h5>Logout</h5>
                                <small>Securely end your session</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="bx bx-log-out-circle mr-2"></i>Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    Are you sure you want to log out?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
