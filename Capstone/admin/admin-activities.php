<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// ==========================================
// 1. DELETE ACTIVITY / ANNOUNCEMENT (SECURED WITH PREPARED STATEMENT)
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    $stmt_del = mysqli_prepare($conn, "DELETE FROM org_announcements WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $del_id);
    
    if (mysqli_stmt_execute($stmt_del)) {
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-trash-can me-2'></i>Activity successfully removed from the system.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fa-solid fa-circle-exclamation me-2'></i>Failed to delete activity.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    mysqli_stmt_close($stmt_del);
}

// ==========================================
// 2. SEARCH & FILTER LOGIC
// ==========================================
$search_query = $_GET['search'] ?? '';
$org_filter = $_GET['org_filter'] ?? '';

$where_clauses = [];
if (!empty($search_query)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "title LIKE '%$safe_search%'"; 
}
if (!empty($org_filter)) {
    $safe_org = mysqli_real_escape_string($conn, $org_filter);
    $where_clauses[] = "org_name = '$safe_org'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// ==========================================
// 3. FETCH DATA
// ==========================================
$activities = [];
$q_acts = mysqli_query($conn, "SELECT * FROM org_announcements $where_sql ORDER BY date_posted DESC");
if ($q_acts) { while($row = mysqli_fetch_assoc($q_acts)) { $activities[] = $row; } }

$organizations = [];
$q_orgs = mysqli_query($conn, "SELECT org_name FROM organizations WHERE status = 'Active' ORDER BY org_name ASC");
if ($q_orgs) { while($row = mysqli_fetch_assoc($q_orgs)) { $organizations[] = $row; } }

// ==========================================
// 4. SECURED PROFILE PICTURE LOGIC
// ==========================================
$stmt_pic = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_pic, "i", $user_id);
mysqli_stmt_execute($stmt_pic);
$res_pic = mysqli_stmt_get_result($stmt_pic);
$db_pic = mysqli_fetch_assoc($res_pic);
$profile_pic = $db_pic['profile_picture'] ?? null;
mysqli_stmt_close($stmt_pic);

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
    <title>Activities Monitor | SchoolLink+ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-red: #dc3545; 
            --dark-blue: #1e293b; 
            --admin-color: #4f46e5; 
        }
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: var(--dark-blue); border-bottom: 3px solid var(--primary-red); }
        
        /* RESPONSIVE SIDEBAR FIXES */
        .sidebar { background-color: white; border-right: 1px solid #e2e8f0; }
        @media (min-width: 768px) {
            .sidebar { min-height: 92vh; position: sticky; top: 70px; }
        }
        
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.25); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .activity-card { cursor: pointer; transition: all 0.2s ease-in-out; background-color: white; border: 1px solid #e2e8f0; border-left: 4px solid var(--dark-blue); }
        .activity-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
        .text-truncate-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .org-badge { background-color: #e0e7ff; color: var(--dark-blue); font-weight: 600; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="admin-dashboard.php">School<span class="text-danger">Link+</span> <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">ADMIN PORTAL</span></a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="adminNavbarContent">
                <div class="d-md-none my-3 border-bottom pb-3">
                    <h6 class="text-uppercase text-light opacity-75 fw-bold mb-2">Menu</h6>
                    <div class="nav nav-pills flex-column">
                        <a href="admin-dashboard.php" class="nav-link text-white-50"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                        <a href="admin-users.php" class="nav-link text-white-50"><i class="fa-solid fa-users-gear"></i> Manage Users</a>
                        <a href="admin-lrn.php" class="nav-link text-white-50"><i class="fa-solid fa-id-card-clip"></i> LRN Master List</a>
                        <a href="admin-orgs.php" class="nav-link text-white-50"><i class="fa-solid fa-building-flag"></i> Manage Orgs</a>
                        <a href="admin-activities.php" class="nav-link active text-white"><i class="fa-solid fa-list-check"></i> Activities Monitor</a>
                        <a href="admin-reports.php" class="nav-link text-white-50"><i class="fa-solid fa-chart-pie"></i> System Overview</a>
                        <a href="admin-profile.php" class="nav-link text-white-50"><i class="fa-solid fa-user-shield"></i> My Profile</a>
                    </div>
                </div>

                <div class="ms-auto d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-md-end text-white gap-3 gap-md-0 w-100 w-md-auto">
                    <div class="d-flex align-items-center me-md-4">
                        <div class="avatar-circle me-2 shadow-sm"><?php echo $avatar_html; ?></div>
                        <span>Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong></span>
                    </div>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            
            <div class="col-md-3 col-lg-2 sidebar p-3 d-none d-md-block">
                <h6 class="text-uppercase text-muted fw-bold mb-3 mt-2 px-2" style="font-size: 11px; letter-spacing: 1px;">Core Management</h6>
                <div class="nav nav-pills flex-column mb-4">
                    <a href="admin-dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line w-20px text-center"></i> Dashboard</a>
                    <a href="admin-users.php" class="nav-link"><i class="fa-solid fa-users-gear w-20px text-center"></i> Manage Users</a>
                    <a href="admin-lrn.php" class="nav-link"><i class="fa-solid fa-id-card-clip w-20px text-center"></i> LRN Master List</a>
                </div>
                <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Organizations</h6>
                <div class="nav nav-pills flex-column mb-4">
                    <a href="admin-orgs.php" class="nav-link"><i class="fa-solid fa-building-flag w-20px text-center"></i> Manage Orgs</a>
                    <a href="admin-activities.php" class="nav-link active"><i class="fa-solid fa-list-check w-20px text-center"></i> Activities Monitor</a>
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

            <div class="col-md-9 col-lg-10 p-4 bg-light">
                
                <div class="mb-4">
                    <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-list-check me-2" style="color: var(--primary-red);"></i> Activities Monitor</h3>
                    <p class="text-muted m-0">Monitor, filter, and moderate all announcements and events posted by organizations.</p>
                </div>

                <?php echo $message; ?>

                <div class="card card-custom p-3 bg-white border mb-4">
                    <form action="" method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search keywords..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="org_filter" class="form-select">
                                <option value="">-- All Organizations --</option>
                                <?php foreach($organizations as $org): ?>
                                    <option value="<?php echo htmlspecialchars($org['org_name']); ?>" <?php if($org_filter === $org['org_name']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($org['org_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn text-white fw-bold w-100" style="background-color: var(--dark-blue);">Filter Results</button>
                            <?php if(!empty($search_query) || !empty($org_filter)): ?>
                                <a href="admin-activities.php" class="btn btn-light border fw-bold">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="row g-4">
                    <?php if(count($activities) > 0): ?>
                        <?php foreach($activities as $act): ?>
                            
                            <?php 
                                $title = htmlspecialchars($act['title'] ?? 'Untitled Announcement');
                                $org = htmlspecialchars($act['org_name'] ?? 'Unknown Org');
                                $poster = htmlspecialchars($act['posted_by'] ?? 'Organization Officer');
                                $date = isset($act['date_posted']) ? date('F d, Y - h:i A', strtotime($act['date_posted'])) : 'Unknown Date';
                                $content = htmlspecialchars($act['content'] ?? $act['description'] ?? $act['message'] ?? 'No details provided.');
                            ?>

                            <div class="col-md-6 col-lg-4">
                                <div class="card card-custom h-100 activity-card" 
                                     data-title="<?php echo $title; ?>"
                                     data-org="<?php echo $org; ?>"
                                     data-poster="<?php echo $poster; ?>"
                                     data-date="<?php echo $date; ?>"
                                     data-content="<?php echo $content; ?>"
                                     onclick="openPostModal(this)">
                                    
                                    <div class="card-body p-4 d-flex flex-column h-100">
                                        <div class="mb-2">
                                            <span class="org-badge"><i class="fa-solid fa-users me-1"></i> <?php echo $org; ?></span>
                                        </div>
                                        
                                        <h5 class="fw-bold text-dark mb-2 text-truncate"><?php echo $title; ?></h5>
                                        
                                        <p class="text-secondary text-truncate-3 small mb-4">
                                            <?php echo $content; ?>
                                        </p>
                                        
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-clock me-1"></i> <?php echo isset($act['date_posted']) ? date('M d, Y', strtotime($act['date_posted'])) : 'N/A'; ?>
                                            </span>
                                            
                                            <a href="admin-activities.php?delete_id=<?php echo $act['id']; ?>" class="btn btn-link text-danger p-0 text-decoration-none small fw-bold" onclick="event.stopPropagation(); return confirm('Delete this post permanently from the system?');">
                                                <i class="fa-solid fa-trash-can me-1"></i> Take Down
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5 text-muted bg-white rounded-4 border">
                                <i class="fa-solid fa-comment-slash fs-1 mb-3 opacity-50" style="color: var(--primary-red);"></i>
                                <h5 class="fw-bold text-dark">No Activities Found</h5>
                                <p class="m-0">The dashboard is squeaky clean!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="viewPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
                <div class="modal-header border-0 pb-0">
                    <span class="org-badge id-modal-org"><i class="fa-solid fa-users me-1"></i> Club Name</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <h4 class="fw-bold text-dark mb-1 id-modal-title">Announcement Title</h4>
                    <div class="text-muted mb-4" style="font-size: 0.8rem;">
                        <i class="fa-solid fa-user-pen me-1"></i> By: <span class="id-modal-poster">Officer</span> &nbsp;|&nbsp;
                        <i class="fa-solid fa-calendar-alt me-1"></i> <span class="id-modal-date">Date</span>
                    </div>
                    <div class="p-3 bg-light rounded-3 border text-dark id-modal-content" style="white-space: pre-wrap; font-size: 0.95rem; max-height: 400px; overflow-y: auto;">
                        Full content goes here...
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary fw-bold px-4 btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPostModal(card) {
            const title = card.getAttribute('data-title');
            const org = card.getAttribute('data-org');
            const poster = card.getAttribute('data-poster');
            const date = card.getAttribute('data-date');
            const content = card.getAttribute('data-content');

            document.querySelector('.id-modal-title').innerText = title;
            document.querySelector('.id-modal-org').innerHTML = '<i class="fa-solid fa-users me-1"></i> ' + org;
            document.querySelector('.id-modal-poster').innerText = poster;
            document.querySelector('.id-modal-date').innerText = date;
            document.querySelector('.id-modal-content').innerText = content;

            new bootstrap.Modal(document.getElementById('viewPostModal')).show();
        }
    </script>
    <?php include '../bfcache_killer.php'; ?>
</body>
</html>