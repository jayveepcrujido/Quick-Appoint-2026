<?php
include '../conn.php';
session_start();

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

if (!isset($_SESSION['is_department_head']) || !$_SESSION['is_department_head']) {
    echo '<div class="alert alert-warning">
            <i class="bx bx-error-circle"></i> 
            Access Denied: Only Department Heads can manage co-personnel.
          </div>';
    exit();
}

$personnel_id = $_SESSION['personnel_id'];
$dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE id = ?");
$dept_stmt->execute([$personnel_id]);
$department_id = $dept_stmt->fetchColumn();

$co_personnel_query = $pdo->prepare("
    SELECT 
        lp.id,
        lp.first_name,
        lp.middle_name,
        lp.last_name,
        a.email,
        lp.created_at,
        lp.created_by_personnel_id,
        creator.first_name as creator_first_name,
        creator.last_name as creator_last_name
    FROM lgu_personnel lp
    JOIN auth a ON lp.auth_id = a.id
    LEFT JOIN lgu_personnel creator ON lp.created_by_personnel_id = creator.id
    WHERE lp.department_id = ? 
    AND lp.id != ?
    AND lp.is_department_head = 0
    ORDER BY lp.created_at DESC
");
$co_personnel_query->execute([$department_id, $personnel_id]);
$co_personnel_list = $co_personnel_query->fetchAll(PDO::FETCH_ASSOC);

$dept_name_query = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
$dept_name_query->execute([$department_id]);
$department_name = $dept_name_query->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Co-Personnel</title>
    <style>
        .co-personnel-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-header h2 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-add-personnel {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-add-personnel:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .personnel-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #0D92F4;
        }

        .personnel-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .personnel-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .personnel-details h5 {
            margin: 0 0 0.5rem 0;
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .personnel-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .meta-item i {
            color: #0D92F4;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-personnel {
            background: #d1fae5;
            color: #065f46;
        }

        .personnel-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-edit:hover {
            background: #fde68a;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
        }

        .modal-header .close {
            color: white;
            opacity: 0.9;
        }

        .form-group label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #0D92F4;
            box-shadow: 0 0 0 3px rgba(13, 146, 244, 0.1);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        @media (max-width: 768px) {
            .co-personnel-container {
                padding: 1rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .personnel-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .personnel-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="co-personnel-container">
        <div class="page-header">
            <h2><i class='bx bx-user-plus'></i> Manage Co-Personnel</h2>
            <p>Department: <strong><?php echo htmlspecialchars($department_name); ?></strong></p>
        </div>

        <div class="action-bar">
            <div>
                <h5 class="mb-0">Co-Personnel List</h5>
                <small class="text-muted">Total: <?php echo count($co_personnel_list); ?> personnel</small>
            </div>
            <button class="btn-add-personnel" data-toggle="modal" data-target="#addCoPersonnelModal">
                <i class='bx bx-plus-circle'></i> Add Co-Personnel
            </button>
        </div>

        <div id="alertMessage"></div>

        <?php if (empty($co_personnel_list)): ?>
            <div class="empty-state">
                <i class='bx bx-user-x'></i>
                <h4>No Co-Personnel Yet</h4>
                <p>Click "Add Co-Personnel" to create your first team member</p>
            </div>
        <?php else: ?>
            <?php foreach ($co_personnel_list as $person): ?>
            <div class="personnel-card">
                <div class="personnel-info">
                    <div class="personnel-details">
                        <h5>
                            <?php 
                                echo htmlspecialchars($person['first_name'] . ' ');
                                if (!empty($person['middle_name'])) {
                                    echo htmlspecialchars(substr($person['middle_name'], 0, 1) . '. ');
                                }
                                echo htmlspecialchars($person['last_name']);
                            ?>
                        </h5>
                        <div class="personnel-meta">
                            <div class="meta-item">
                                <i class='bx bx-envelope'></i>
                                <?php echo htmlspecialchars($person['email']); ?>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-calendar'></i>
                                Added: <?php echo date('M d, Y', strtotime($person['created_at'])); ?>
                            </div>
                            
                            <?php if ($person['created_by_personnel_id'] && $person['creator_first_name']): ?>
                                <div class="meta-item">
                                    <i class='bx bx-user'></i>
                                    Created by: <?php echo htmlspecialchars($person['creator_first_name'] . ' ' . $person['creator_last_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                    <div class="personnel-actions">
                        <button class="btn-action btn-edit" onclick="editPersonnel(<?php echo $person['id']; ?>)">
                            <i class='bx bx-edit'></i> Edit
                        </button>
                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?>')">
                            <i class='bx bx-trash'></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="addCoPersonnelModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-user-plus'></i> Add New Co-Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="addCoPersonnelForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                            <small class="form-text text-muted">Must be a valid email address</small>
                        </div>
                        <div class="form-group">
                            <label>Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                            <small class="form-text text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save'></i> Create Co-Personnel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCoPersonnelModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-edit'></i> Edit Co-Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="editCoPersonnelForm">
                    <input type="hidden" name="personnel_id" id="edit_personnel_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="alert alert-info">
                            <i class='bx bx-info-circle'></i> Leave password fields empty to keep current password
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" class="form-control" name="password" minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save'></i> Update Personnel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class='bx bx-error-circle'></i> Confirm Deletion</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <i class='bx bx-user-x' style="font-size: 4rem; color: #ef4444;"></i>
                    <p class="mt-3">Are you sure you want to delete <strong id="deletePersonnelName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                    <input type="hidden" id="deletePersonnelId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deletePersonnel()">
                        <i class='bx bx-trash'></i> Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
$('#addCoPersonnelForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    
    if (formData.get('password') !== formData.get('confirm_password')) {
        showAlert('Passwords do not match!', 'danger');
        return;
    }
    
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Creating...');
    
    $.ajax({
        url: 'ajax/add_co_personnel.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#addCoPersonnelModal').modal('hide');
                $('#addCoPersonnelForm')[0].reset();
                refreshCoPersonnelList();
            } else {
                showAlert(response.message, 'danger');
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            showAlert('An error occurred. Please try again.', 'danger');
            submitBtn.prop('disabled', false).html(originalBtnText);
        }
    });
});

function editPersonnel(id) {
    $.ajax({
        url: 'ajax/get_co_personnel.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#edit_personnel_id').val(response.data.id);
                $('#edit_first_name').val(response.data.first_name);
                $('#edit_middle_name').val(response.data.middle_name);
                $('#edit_last_name').val(response.data.last_name);
                $('#edit_email').val(response.data.email);
                $('#editCoPersonnelModal').modal('show');
            } else {
                showAlert('Failed to load personnel data', 'danger');
            }
        },
        error: function() {
            showAlert('An error occurred', 'danger');
        }
    });
}

$('#editCoPersonnelForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password || confirmPassword) {
        if (password !== confirmPassword) {
            showAlert('Passwords do not match!', 'danger');
            return;
        }
    }
    
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Updating...');
    
    $.ajax({
        url: 'ajax/update_co_personnel.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('Response:', response);
            if (response.success) {
                showAlert(response.message, 'success');
                $('#editCoPersonnelModal').modal('hide');
                refreshCoPersonnelList();
            } else {
                showAlert(response.message, 'danger');
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            showAlert('An error occurred: ' + error, 'danger');
            submitBtn.prop('disabled', false).html(originalBtnText);
        }
    });
});

function confirmDelete(id, name) {
    $('#deletePersonnelId').val(id);
    $('#deletePersonnelName').text(name);
    $('#deleteConfirmModal').modal('show');
}

function deletePersonnel() {
    const id = $('#deletePersonnelId').val();
    const deleteBtn = $('#deleteConfirmModal').find('.btn-danger');
    const originalBtnText = deleteBtn.html();
    
    deleteBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Deleting...');
    
    $.ajax({
        url: 'ajax/delete_co_personnel.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                deleteBtn.prop('disabled', false).html(originalBtnText);
                $('#deleteConfirmModal').modal('hide');
                refreshCoPersonnelList();
            } else {
                showAlert(response.message, 'danger');
                deleteBtn.prop('disabled', false).html(originalBtnText);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            showAlert('An error occurred. Please try again.', 'danger');
            deleteBtn.prop('disabled', false).html(originalBtnText);
        }
    });
}

function refreshCoPersonnelList() {
    console.log('Starting refresh...');
    
    $('.personnel-card, .empty-state').remove();
    
    const loadingHtml = `
        <div class="text-center py-5" id="loadingIndicator" style="background: white; border-radius: 12px; margin-top: 1rem; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
            <i class='bx bx-loader-alt bx-spin' style='font-size: 3rem; color: #0D92F4;'></i>
            <p class="mt-3" style="color: #64748b;">Refreshing data...</p>
        </div>
    `;
    $('.action-bar').after(loadingHtml);
    
    $.ajax({
        url: 'ajax/get_co_personnel_list.php',
        method: 'GET',
        dataType: 'json',
        cache: false,
        timeout: 10000,
        success: function(response) {
            console.log('AJAX Success:', response);
            
            $('#loadingIndicator').remove();
            
            if (response.success) {
                $('.action-bar small').text('Total: ' + response.count + ' personnel');
                
                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(person) {
                        const middleInitial = person.middle_name ? person.middle_name.charAt(0) + '. ' : '';
                        const fullName = person.first_name + ' ' + middleInitial + person.last_name;
                        const createdDate = new Date(person.created_at).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });
                        
                        let creatorHtml = '';
                        if (person.created_by_personnel_id && person.creator_first_name) {
                            creatorHtml = `
                                <div class="meta-item">
                                    <i class='bx bx-user'></i>
                                    Created by: ${person.creator_first_name} ${person.creator_last_name}
                                </div>
                            `;
                        }
                        
                        const cardHtml = `
                            <div class="personnel-card">
                                <div class="personnel-info">
                                    <div class="personnel-details">
                                        <h5>${fullName}</h5>
                                        <div class="personnel-meta">
                                            <div class="meta-item">
                                                <i class='bx bx-envelope'></i>
                                                ${person.email}
                                            </div>
                                            <div class="meta-item">
                                                <i class='bx bx-calendar'></i>
                                                Added: ${createdDate}
                                            </div>
                                            ${creatorHtml}
                                        </div>
                                    </div>
                                    <div class="personnel-actions">
                                        <button class="btn-action btn-edit" onclick="editPersonnel(${person.id})">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(${person.id}, '${fullName.replace(/'/g, "\\'")}')">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        $('.action-bar').after(cardHtml);
                    });
                    
                } else {
                    const emptyStateHtml = `
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <h4>No Co-Personnel Yet</h4>
                            <p>Click "Add Co-Personnel" to create your first team member</p>
                        </div>
                    `;
                    $('.action-bar').after(emptyStateHtml);
                }
                
                cleanupModals();
                
            } else {
                showAlert(response.message || 'Failed to load personnel', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error Details:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText
            });
            
            $('#loadingIndicator').remove();
            
            const errorHtml = `
                <div class="empty-state">
                    <i class='bx bx-error-circle' style="color: #ef4444;"></i>
                    <h4>Failed to Load Data</h4>
                    <p>Error: ${error || 'Unknown error'}</p>
                    <button class="btn btn-primary mt-3" onclick="refreshCoPersonnelList()">
                        <i class='bx bx-refresh'></i> Try Again
                    </button>
                </div>
            `;
            $('.action-bar').after(errorHtml);
            
            showAlert('Unable to refresh data. Check console for details.', 'danger');
        }
    });
}

function cleanupModals() {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('padding-right', '');
    $('body').css('overflow', '');
}

$('.modal').on('hidden.bs.modal', function() {
    cleanupModals();
});

function showAlert(message, type) {
    $('.toast-notification').remove();
    
    const bgColor = type === 'success' ? 'linear-gradient(135deg, #10b981, #059669)' : 
                   type === 'danger' ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 
                   type === 'warning' ? 'linear-gradient(135deg, #f59e0b, #d97706)' :
                   'linear-gradient(135deg, #0D92F4, #27548A)';
    
    const icon = type === 'success' ? 'bx-check-circle' : 
                type === 'danger' ? 'bx-error-circle' : 
                type === 'warning' ? 'bx-error' :
                'bx-info-circle';
    
    const alertHtml = `
        <div class="toast-notification" style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 400px;
            min-width: 300px;
            animation: slideInRight 0.4s ease-out;
        ">
            <i class='bx ${icon}' style='font-size: 1.5rem; flex-shrink: 0;'></i>
            <span style='font-weight: 500; flex: 1;'>${message}</span>
            <button type="button" class="toast-close" style="
                background: transparent;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                opacity: 0.8;
                transition: opacity 0.2s;
                margin-left: 0.5rem;
            " onclick="$(this).parent().fadeOut(200, function(){ $(this).remove(); })">
                &times;
            </button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    setTimeout(() => {
        $('.toast-notification').fadeOut(400, function() {
            $(this).remove();
        });
    }, 4000);
}

if (!$('#toastAnimationStyles').length) {
    $('head').append(`
        <style id="toastAnimationStyles">
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .toast-close:hover {
                opacity: 1 !important;
                transform: scale(1.1);
            }
            
            @media (max-width: 576px) {
                .toast-notification {
                    top: 10px !important;
                    right: 10px !important;
                    left: 10px !important;
                    max-width: none !important;
                    min-width: auto !important;
                }
            }
        </style>
    `);
}
</script>
</body>
</html>
