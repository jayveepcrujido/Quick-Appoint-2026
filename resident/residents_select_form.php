<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Residents') {
    echo "<div class='alert alert-danger'>Unauthorized access</div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Feedback Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-option {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border: none;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }
        .card-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }
        .card-option h5 {
            margin-bottom: 0.5rem;
        }
        .top-wrapper {
            padding-top: 60px;
        }
<a href="#" class="nav_link" onclick="loadContent('residents_view_departments.php')">
    </style>
</head>
<body>
    <div class="container top-wrapper">
        <div class="w-100" style="max-width: 600px; margin: 0 auto;">
            <div class="text-center mb-4">
                <h3 class="text-primary font-weight-bold">Select Feedback Form</h3>
                <p class="text-muted">Please choose a form below:</p>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="card card-option p-3" onclick="loadContent('residents_submit_feedback.php')">
                        <h5 class="text-primary"><i class="fas fa-comment-alt mr-2"></i>Client Feedback Form</h5>
                        <p class="text-muted mb-0">Let us know your thoughts and suggestions about our services.</p>
                    </div>
                </div>
                <div class="col-md-12 mb-3">
                    <div class="card card-option p-3" onclick="loadContent('residents_submit_commendation.php')">
                        <h5 class="text-success"><i class="fas fa-thumbs-up mr-2"></i>Commendation Form</h5>
                        <p class="text-muted mb-0">Recognize personnel or services that impressed you.</p>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card card-option p-3" onclick="loadContent('residents_submit_complaint.php')">
                        <h5 class="text-danger"><i class="fas fa-exclamation-circle mr-2"></i>Complaint Form</h5>
                        <p class="text-muted mb-0">Report issues, problems, or concerns for improvement.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
