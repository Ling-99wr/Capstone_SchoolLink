<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// ==========================================
// 1. ADD ORGANIZATION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_org'])) {
    $org_name = mysqli_real_escape_string($conn, trim($_POST['org_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    $check = mysqli_query($conn, "SELECT id FROM organizations WHERE org_name = '$org_name' AND status = 'Active'");
    if(mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>'$org_name' already exists!</div>";
    } else {
        mysqli_query($conn, "INSERT INTO organizations (org_name, description) VALUES ('$org_name', '$description')");
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>Organization successfully created!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// ==========================================
// 2. EDIT ORGANIZATION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_org'])) {
    $org_id = intval($_POST['org_id']);
    $org_name = mysqli_real_escape_string($conn, trim($_POST['org_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    mysqli_query($conn, "UPDATE organizations SET org_name = '$org_name', description = '$description' WHERE id = '$org_id'");
    
    $old_name_query = mysqli_query($conn, "SELECT org_name FROM organizations WHERE id = '$org_id'");
    $old_name = mysqli_fetch_assoc($old_name_query)['org_name'] ?? '';
    if($old_name !== $org_name) {
        mysqli_query($conn, "UPDATE users SET organization_name = '$org_name' WHERE organization_name = '$old_name'");
    }
    $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-pen-to-square me-2'></i>Organization updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// ==========================================
// 3. ARCHIVE ORGANIZATION
// ==========================================
if (isset($_GET['archive_id'])) {
    $arch_id = intval($_GET['archive_id']);
    $q_name = mysqli_query($conn, "SELECT org_name FROM organizations WHERE id = '$arch_id'");
    $row = mysqli_fetch_assoc($q_name);
    
    if ($row) {
        $o_name = $row['org_name'];
        mysqli_query($conn, "UPDATE organizations SET status = 'Archived' WHERE id = '$arch_id'");
        mysqli_query($conn, "UPDATE users SET is_officer = 0, organization_name = NULL WHERE organization_name = '$o_name'");
    }
    header("Location: admin-orgs.php?tab=archived");
    exit();
}

// ==========================================
// 4. UNARCHIVE ORGANIZATION
// ==========================================
if (isset($_GET['unarchive_id'])) {
    $unarch_id = intval($_GET['unarchive_id']);
    mysqli_query($conn, "UPDATE organizations SET status = 'Active' WHERE id = '$unarch_id'");
    header("Location: admin-orgs.php?tab=active");
    exit();
}

// ALAMIN KUNG ANONG TAB ANG NAKA-OPEN
$current_tab = $_GET['tab'] ?? 'active';
$status_filter = ($current_tab === 'archived') ? 'Archived' : 'Active';

// ==========================================
// 5. FETCH DATA (UPDATED PARA KASAMA ANG OFFICER NAMES)
// ==========================================
$organizations = [];
$q_orgs = mysqli_query($conn, "
    SELECT o.*, 
    (SELECT COUNT(*) FROM users u WHERE u.organization_name = o.org_name AND u.is_officer = 1 AND u.status = 'Active') as officer_count,
    (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM users u WHERE u.organization_name = o.org_name AND u.is_officer = 1 AND u.status = 'Active') as officer_names
    FROM organizations o 
    WHERE o.status = '$status_filter' 
    ORDER BY o.org_name ASC
");

if ($q_orgs) {
    while($row = mysqli_fetch_assoc($q_orgs)) { $organizations[] = $row; }
}

// ==========================================
// 6. BULLETPROOF PROFILE PICTURE LOGIC
// ==========================================
$q_pic = mysqli_query($conn, "SELECT profile_picture FROM users WHERE id = '$user_id'");
$db_pic = mysqli_fetch_assoc($q_pic);
$profile_pic = $db_pic['profile_picture'] ?? null;

$words = explode(" ", $full_name); $initials = "";
foreach ($words as $w) { $initials .= mb_substr($w, 0, 1); }
$initials = strtoupper(substr($initials, 0, 2));

$avatar_html = $initials;
if (!empty($profile_pic) && file_exists("../uploads/" . $profile_pic)) {
    $avatar_html = "<img src='../uploads/" . htmlspecialchars($profile_pic) . "' alt='Profile' style='width: 100%; height: 100%; object-fit: cover;'>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Organizations | SchoolLink+ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-red: #dc3545; 
            --dark-blue: #1e293b; 
            --admin-color: #4f46e5; 
        }
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; overflow-y: scroll; }
        .navbar-custom { background-color: var(--dark-blue); border-bottom: 3px solid var(--primary-red); }
        
        /* 🔥 THE ULTIMATE STICKY SIDEBAR FIX 🔥 */
        .sidebar { 
            position: -webkit-sticky;
            position: sticky !important;
            top: 58px !important;
            height: calc(100vh - 58px) !important;
            overflow-y: auto !important;
            background-color: white; 
            border-right: 1px solid #e2e8f0; 
            z-index: 100;
        }
        
        /* UPDATED RED SIDEBAR THEME */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.25); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        /* CUSTOM TAB STYLING */
        .custom-tabs .nav-link { color: var(--dark-blue); font-weight: 600; border: none; padding: 8px 20px; border-radius: 8px; transition: 0.2s; }
        .custom-tabs .nav-link.active { background-color: white; color: var(--dark-blue); box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        
        /* PINAGANDANG KEBAB DROPDOWN MENU */
        .custom-dropdown { border-radius: 12px; border: 1px solid #e2e8f0; padding: 8px; min-width: 200px; }
        .custom-dropdown .dropdown-item { border-radius: 6px; padding: 8px 16px; font-size: 0.9rem; font-weight: 500; transition: 0.2s; margin-bottom: 2px; }
        .custom-dropdown .dropdown-item:hover { background-color: #f1f5f9; color: var(--dark-blue); }
        .custom-dropdown .dropdown-item.text-danger:hover { background-color: #fef2f2; color: #dc2626 !important; }
        .kebab-btn { background-color: #f8fafc; border: 1px solid transparent; transition: 0.2s; color: #64748b; }
        .kebab-btn:hover { background-color: #e2e8f0; color: var(--dark-blue); border-color: #cbd5e1; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="admin-dashboard.php">School<span class="text-danger">Link+</span> <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">ADMIN PORTAL</span></a>
            <div class="ms-auto d-flex align-items-center text-white">
                <div class="avatar-circle me-2 shadow-sm"><?php echo $avatar_html; ?></div>
                <span class="me-4 d-none d-md-inline">Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong></span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            
            <div class="col-md-3 col-lg-2 d-none d-md-block p-0">
                <div class="sidebar p-3">
                    <h6 class="text-uppercase text-muted fw-bold mb-3 mt-2 px-2" style="font-size: 11px; letter-spacing: 1px;">Core Management</h6>
                    <div class="nav nav-pills flex-column mb-4">
                        <a href="admin-dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line w-20px text-center"></i> Dashboard</a>
                        <a href="admin-users.php" class="nav-link"><i class="fa-solid fa-users-gear w-20px text-center"></i> Manage Users</a>
                        <a href="admin-lrn.php" class="nav-link"><i class="fa-solid fa-id-card-clip w-20px text-center"></i> LRN Master List</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Organizations</h6>
                    <div class="nav nav-pills flex-column mb-4">
                        <a href="admin-orgs.php" class="nav-link active"><i class="fa-solid fa-building-flag w-20px text-center"></i> Manage Orgs</a>
                        <a href="admin-activities.php" class="nav-link"><i class="fa-solid fa-list-check w-20px text-center"></i> Activities Monitor</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Reports & Logs</h6>
                    <div class="nav nav-pills flex-column mb-4">
                        <a href="admin-reports.php" class="nav-link"><i class="fa-solid fa-chart-pie w-20px text-center"></i> System Overview</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Account</h6>
                    <div class="nav nav-pills flex-column">
                        <a href="admin-profile.php" class="nav-link"><i class="fa-solid fa-user-shield w-20px text-center"></i> My Profile</a>
                    </div>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 p-4 bg-light">
                
                <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-building-flag me-2" style="color: var(--primary-red);"></i> Manage Organizations</h3>
                        <p class="text-muted m-0">Create, edit, and oversee student clubs and organizations.</p>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: var(--dark-blue); border:none;" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                        <i class="fa-solid fa-plus me-2"></i> Create Organization
                    </button>
                </div>

                <?php echo $message; ?>

                <ul class="nav nav-tabs custom-tabs border-0 gap-2 mb-4 bg-secondary bg-opacity-10 p-1 rounded-3" style="max-width: fit-content;">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'active') ? 'active' : ''; ?>" href="admin-orgs.php?tab=active">
                            <i class="fa-solid fa-building-flag me-1"></i> Active Organizations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'archived') ? 'active' : ''; ?>" href="admin-orgs.php?tab=archived">
                            <i class="fa-solid fa-box-archive me-1"></i> Archived History
                        </a>
                    </li>
                </ul>

                <div class="row g-4">
                    <?php if(count($organizations) > 0): ?>
                        <?php foreach($organizations as $org): ?>
                            
                            <?php 
                                $date_display = "Legacy Record";
                                if (isset($org['date_created']) && !empty($org['date_created'])) {
                                    $date_display = date('M d, Y', strtotime($org['date_created']));
                                }
                            ?>

                            <div class="col-md-6 col-lg-4">
                                <div class="card card-custom h-100 bg-white border position-relative overflow-hidden">
                                    <div class="p-4 d-flex flex-column h-100">
                                        
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded p-3 fs-4" style="color: var(--dark-blue) !important;">
                                                <i class="fa-solid fa-users"></i>
                                            </div>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-sm rounded-circle kebab-btn px-2 py-1" data-bs-toggle="dropdown">
                                                    <i class="fa-solid fa-ellipsis-vertical fs-6"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow custom-dropdown">
                                                    <?php if($current_tab === 'active'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="#" 
                                                               data-id="<?php echo $org['id']; ?>"
                                                               data-name="<?php echo htmlspecialchars($org['org_name']); ?>"
                                                               data-desc="<?php echo isset($org['description']) ? htmlspecialchars($org['description']) : ''; ?>"
                                                               onclick="triggerEditModal(this)">
                                                               <i class="fa-solid fa-pen text-secondary me-2" style="width: 16px;"></i> Edit Details
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="admin-orgs.php?archive_id=<?php echo $org['id']; ?>" onclick="return confirm('Archive this organization? All officers assigned to this will be demoted automatically.');">
                                                                <i class="fa-solid fa-box-archive me-2" style="width: 16px;"></i> Archive Org
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <a class="dropdown-item text-success" href="admin-orgs.php?unarchive_id=<?php echo $org['id']; ?>" onclick="return confirm('Restore this organization?');">
                                                                <i class="fa-solid fa-arrow-up-from-bracket me-2" style="width: 16px;"></i> Restore Org
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($org['org_name']); ?></h5>
                                        <small class="text-muted mb-3 d-block">Created on: <?php echo $date_display; ?></small>
                                        
                                        <p class="text-secondary text-truncate-2 mb-4" style="font-size: 0.9rem;">
                                            <?php echo (isset($org['description']) && !empty($org['description'])) ? htmlspecialchars($org['description']) : '<em>No description provided.</em>'; ?>
                                        </p>

                                        <!-- 🔥 CLICKABLE LIST SA MODAL (THEME MATCHED) 🔥 -->
                                        <div class="mb-3">
                                            <small class="fw-bold text-dark d-block mb-2">Officers List:</small>
                                            <?php if(!empty($org['officer_names'])): ?>
                                                <button type="button" class="btn btn-sm fw-bold rounded-pill px-3 shadow-sm" 
                                                        style="background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; transition: all 0.3s ease;"
                                                        onmouseover="this.style.backgroundColor='#1e293b'; this.style.color='#ffffff'; this.style.borderColor='#1e293b';" 
                                                        onmouseout="this.style.backgroundColor='#eff6ff'; this.style.color='#1e40af'; this.style.borderColor='#bfdbfe';"
                                                        onclick="showOfficersModal('<?php echo htmlspecialchars($org['org_name']); ?>', '<?php echo htmlspecialchars($org['officer_names']); ?>')">
                                                    <i class="fa-solid fa-users-viewfinder me-1"></i> View Officers (<?php echo $org['officer_count']; ?>)
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted fst-italic" style="font-size: 0.8rem;">No officers assigned.</small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="small fw-bold text-dark">Status:</span>
                                            <?php if($current_tab === 'active'): ?>
                                                <?php if($org['officer_count'] > 0): ?>
                                                    <span class="badge rounded-pill bg-success px-3"><?php echo $org['officer_count']; ?> Officers</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-secondary bg-opacity-25 text-dark px-3">No Officers</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-danger px-3">Archived</span>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5 text-muted bg-white rounded-4 border">
                                <i class="fa-solid <?php echo ($current_tab === 'active') ? 'fa-building-flag' : 'fa-box-archive'; ?> fs-1 mb-3 opacity-50" style="color: var(--dark-blue);"></i>
                                <h5 class="fw-bold text-dark">No <?php echo ucfirst($current_tab); ?> Organizations</h5>
                                <p class="m-0">Your list is currently empty.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- MODALS SECTION -->
    <div class="modal fade" id="addOrgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="" method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" style="color: var(--dark-blue);"><i class="fa-solid fa-plus me-2"></i> Create Organization</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">ORGANIZATION NAME</label>
                            <input type="text" name="org_name" class="form-control" placeholder="e.g. Science Club" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-secondary">DESCRIPTION (Optional)</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="What is this club about?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_org" class="btn text-white fw-bold px-4" style="background-color: var(--dark-blue);">Save Organization</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editOrgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="" method="POST">
                    <input type="hidden" name="org_id" id="edit_org_id">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Organization</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">ORGANIZATION NAME</label>
                            <input type="text" name="org_name" id="edit_org_name" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-secondary">DESCRIPTION</label>
                            <textarea name="description" id="edit_org_desc" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_org" class="btn btn-dark fw-bold px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 🔥 OFFICERS MODAL (THEME MATCHED) 🔥 -->
    <div class="modal fade" id="viewOfficersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="border-radius: 16px; border-top: 5px solid var(--dark-blue);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--dark-blue);">
                        <i class="fa-solid fa-users me-2 text-danger"></i><span id="modalOrgName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">Currently assigned officers for this organization:</p>
                    <div id="modalOfficerList" class="p-3 bg-light rounded-3 border text-dark fw-bold" style="line-height: 1.8;"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn fw-bold text-white rounded-pill px-4 w-100" style="background-color: var(--dark-blue);" data-bs-dismiss="modal">Close Window</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function triggerEditModal(btn) {
            document.getElementById('edit_org_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_org_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_org_desc').value = btn.getAttribute('data-desc');
            new bootstrap.Modal(document.getElementById('editOrgModal')).show();
        }

        // Script para sa pag-pop-up ng Officers List
        function showOfficersModal(orgName, officerNames) {
            document.getElementById('modalOrgName').innerText = orgName + " Officers";
            
            // Reformat the comma separated names with nice icons
            let formattedNames = officerNames.split(', ').join('<br><i class="fa-solid fa-user-check me-2" style="color: var(--primary-red);"></i>');
            document.getElementById('modalOfficerList').innerHTML = '<i class="fa-solid fa-user-check me-2" style="color: var(--primary-red);"></i>' + formattedNames;
            
            new bootstrap.Modal(document.getElementById('viewOfficersModal')).show();
        }
    </script>
    <?php include '../bfcache_killer.php'; ?>
</body>
</html>