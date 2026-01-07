<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}
include '../../conn.php';

// Fetch departments with services
$stmt = $pdo->query("SELECT d.*, 
                            GROUP_CONCAT(s.service_name ORDER BY s.id SEPARATOR ', ') AS services
                     FROM departments d
                     LEFT JOIN department_services s ON d.id = s.department_id
                     GROUP BY d.id ORDER BY d.created_at DESC");
$departments = $stmt->fetchAll();

$serviceMap = [];
$stmt = $pdo->query("SELECT ds.id AS service_id, ds.department_id, ds.service_name, sr.requirement
                     FROM department_services ds
                     LEFT JOIN service_requirements sr ON ds.id = sr.service_id
                     ORDER BY ds.department_id, ds.id");

while ($row = $stmt->fetch()) {
    $deptId = $row['department_id'];
    $serviceId = $row['service_id'];

    if (!isset($serviceMap[$deptId][$serviceId])) {
        $serviceMap[$deptId][$serviceId] = [
            'name' => $row['service_name'],
            'requirements' => []
        ];
    }

    if ($row['requirement']) {
        $serviceMap[$deptId][$serviceId]['requirements'][] = $row['requirement'];
    }
}

// Return the grid HTML and stats
?>
<div class="row" id="departmentGrid">
    <?php foreach ($departments as $d): 
        $searchText = strtolower($d['name'] . ' ' . $d['acronym'] . ' ' . $d['description'] . ' ' . $d['services']);
        $services = array_filter(array_map('trim', explode(',', $d['services'])));
    ?>
    <div class="col-lg-4 col-md-6 dept-card-wrapper" data-search="<?= htmlspecialchars($searchText) ?>">
        <div class="dept-card" data-toggle="modal" data-target="#viewModal<?= $d['id'] ?>">
            <div class="dept-card-header">
                <h4 class="dept-acronym">
                    <i class='bx bx-building-house'></i>
                    <?= htmlspecialchars($d['acronym']) ?>
                </h4>
                <p class="dept-name"><?= htmlspecialchars($d['name']) ?></p>
            </div>
            <div class="dept-card-body">
                <?php if ($services): ?>
                    <div>
                        <?php foreach (array_slice($services, 0, 3) as $s): ?>
                            <span class="service-badge"><?= htmlspecialchars($s) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($services) > 3): ?>
                            <span class="service-badge">+<?= count($services) - 3 ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-services">
                        <i class='bx bx-info-circle'></i>
                        No services available
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal<?= $d['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header primary">
                    <h5 class="modal-title">
                        <i class='bx bx-info-circle'></i>
                        <?= htmlspecialchars($d['name']) ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="info-label">
                            <i class='bx bx-detail'></i>
                            Description
                        </p>
                        <p><?= htmlspecialchars($d['description']) ?: 'No description provided.' ?></p>
                    </div>

                    <div>
                        <p class="info-label">
                            <i class='bx bx-list-check'></i>
                            Services & Requirements
                        </p>
                        <?php if (isset($serviceMap[$d['id']])): ?>
                            <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                <div class="service-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="service-name">
                                                <i class='bx bx-check-circle'></i>
                                                <?= htmlspecialchars($svc['name']) ?>
                                            </div>
                                            <?php if (!empty($svc['requirements'])): ?>
                                                <ul class="requirement-list">
                                                    <?php foreach ($svc['requirements'] as $req): ?>
                                                        <li>
                                                            <i class='bx bx-right-arrow-alt'></i>
                                                            <span><?= htmlspecialchars($req) ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted mb-0"><em>No specific requirements</em></p>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" 
                                                class="btn btn-sm remove-btn delete-service-btn ml-3" 
                                                data-service-id="<?= $svcId ?>"
                                                data-service-name="<?= htmlspecialchars($svc['name']) ?>"
                                                title="Delete Service">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class='bx bx-package'></i>
                                <h4>No Services Available</h4>
                                <p>This department has no services listed yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                        <i class='bx bx-x'></i> Close
                    </button>
                    
                    <button type="button" class="btn btn-danger-custom delete-dept-btn" data-id="<?= $d['id'] ?>">
                        <i class='bx bx-trash'></i> Delete Department
                    </button>

                    <button class="btn btn-warning-custom edit-dept-btn" data-dept-id="<?= $d['id'] ?>">
                        <i class='bx bx-edit'></i> Edit Department
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content edit-dept-form" method="post" action="ajax/ajax_update_department.php">
                <div class="modal-header warning">
                    <h5 class="modal-title">
                        <i class='bx bx-edit'></i>
                        Edit Department
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="department_id" value="<?= $d['id'] ?>">

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-building'></i>
                            Department Name
                        </label>
                        <input name="name" class="form-control form-control-custom" value="<?= htmlspecialchars($d['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-tag'></i>
                            Acronym
                        </label>
                        <input name="acronym" class="form-control form-control-custom" value="<?= htmlspecialchars($d['acronym']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-detail'></i>
                            Description
                        </label>
                        <textarea name="description" class="form-control form-control-custom" rows="3"><?= htmlspecialchars($d['description']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class='bx bx-list-ul'></i>
                            Services & Requirements
                        </label>
                        <div class="service-edit-area">
                            <?php if (isset($serviceMap[$d['id']])): ?>
                                <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                    <div class="service-edit-block">
                                        <input type="hidden" name="service_ids[]" value="<?= $svcId ?>">
                                        <input type="text" name="service_names[]" class="form-control form-control-custom mb-3" value="<?= htmlspecialchars($svc['name']) ?>" placeholder="Service Name" required>
                                        
                                        <div class="requirement-group">
                                            <?php if (!empty($svc['requirements'])): ?>
                                                <?php foreach ($svc['requirements'] as $req): ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" value="<?= htmlspecialchars($req) ?>" placeholder="Requirement">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn remove-btn remove-req">
                                                                <i class='bx bx-x'></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <div class="input-group mb-2">
                                                <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" placeholder="Add new requirement (optional)">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn remove-btn remove-req">
                                                        <i class='bx bx-x'></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-custom btn-sm-custom add-req">
                                            <i class='bx bx-plus'></i>
                                            Add Requirement
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-success-custom btn-sm-custom mt-2" id="addNewServiceBtn<?= $d['id'] ?>">
                            <i class='bx bx-plus-circle'></i>
                            Add New Service
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                        <i class='bx bx-x'></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-warning-custom">
                        <i class='bx bx-save'></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Hidden elements for stats update -->
<div style="display:none;">
    <span class="stat-number"><?= count($departments) ?></span>
    <span class="stat-number"><?= array_sum(array_map(function($d) use ($serviceMap) { 
        return isset($serviceMap[$d['id']]) ? count($serviceMap[$d['id']]) : 0; 
    }, $departments)) ?></span>
</div>