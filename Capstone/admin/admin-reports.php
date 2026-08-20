<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// ==========================================
// 1. FETCH TOTAL STATS (CORRECTED CODES)
// ==========================================
// FIX: Hinarang natin si Alumni gamit ang `grade_level != 'Alumni'` para purong active students lang ang mabilang!
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'Student' AND status = 'Active' AND grade_level != 'Alumni'"))['count'] ?? 0;
$total_alumni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE grade_level = 'Alumni'"))['count'] ?? 0;
$total_officers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE is_officer = 1 AND status = 'Active' AND grade_level != 'Alumni'"))['count'] ?? 0;
$total_orgs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM organizations WHERE status = 'Active'"))['count'] ?? 0;

// ==========================================
// 2. FETCH POPULATION PER GRADE LEVEL
// ==========================================
$grade_counts = [
    'Grade 7' => 0, 'Grade 8' => 0, 'Grade 9' => 0,
    'Grade 10' => 0, 'Grade 11' => 0, 'Grade 12' => 0
];
// FIX: Nagdagdag din ng `AND grade_level != 'Alumni'` para hindi mag-gulo sa loops
$q_grades = mysqli_query($conn, "SELECT grade_level, COUNT(*) as count FROM users WHERE role = 'Student' AND status = 'Active' AND grade_level != 'Alumni' GROUP BY grade_level");
if ($q_grades) {
    while ($row = mysqli_fetch_assoc($q_grades)) {
        $g = $row['grade_level'];
        if (!str_contains($g, 'Grade ')) { $g = 'Grade ' . $g; }
        if (array_key_exists($g, $grade_counts)) {
            $grade_counts[$g] = $row['count'];
        }
    }
}

// ==========================================
// 3. FETCH RECENT POSTS LOG
// ==========================================
$recent_logs = [];
$q_logs = mysqli_query($conn, "SELECT title, org_name, date_posted FROM org_announcements ORDER BY date_posted DESC LIMIT 5");
if ($q_logs) { while($row = mysqli_fetch_assoc($q_logs)) { $recent_logs[] = $row; } }

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
    <title>System Overview | SchoolLink+ Admin</title>
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
        
        /* RESPONSIVE SIDEBAR FIXES & STICKY LOGIC */
        .sidebar { background-color: white; border-right: 1px solid #e2e8f0; }
        @media (min-width: 768px) {
            .sidebar { 
                position: -webkit-sticky;
                position: sticky !important;
                top: 58px !important;
                height: calc(100vh - 58px) !important;
                overflow-y: auto !important;
                z-index: 100;
            }
        }
        
        /* SIDEBAR LINKS (RED ACTIVE THEME) */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.25); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background-color: white; border: 1px solid #e2e8f0; }
        
        .stat-card { border-top: 4px solid var(--admin-color); }
        
        /* FIXED ACTIVITY LOG TIMELINE WITH RED/BLUE DOT */
        .log-item { border-left: 3px solid #cbd5e1; padding-left: 15px; position: relative; margin-bottom: 15px; margin-left: 10px; }
        .log-item::before { 
            content: ''; 
            width: 12px; height: 12px; 
            background: var(--primary-red); 
            border-radius: 50%; 
            position: absolute; left: -7.5px; top: 4px; 
            border: 2px solid white;
            box-shadow: 0 0 0 1px rgba(220, 53, 69, 0.3);
        }
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
                        <a href="admin-activities.php" class="nav-link text-white-50"><i class="fa-solid fa-list-check"></i> Activities Monitor</a>
                        <a href="admin-reports.php" class="nav-link active text-white"><i class="fa-solid fa-chart-pie"></i> System Overview</a>
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
                        <a href="admin-orgs.php" class="nav-link"><i class="fa-solid fa-building-flag w-20px text-center"></i> Manage Orgs</a>
                        <a href="admin-activities.php" class="nav-link"><i class="fa-solid fa-list-check w-20px text-center"></i> Activities Monitor</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Reports & Logs</h6>
                    <div class="nav nav-pills flex-column mb-4">
                        <a href="admin-reports.php" class="nav-link active"><i class="fa-solid fa-chart-pie w-20px text-center"></i> System Overview</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Account</h6>
                    <div class="nav nav-pills flex-column">
                        <a href="admin-profile.php" class="nav-link"><i class="fa-solid fa-user-shield w-20px text-center"></i> My Profile</a>
                    </div>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 p-4 bg-light">
                
                <div class="mb-4">
                    <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-chart-pie me-2" style="color: var(--primary-red);"></i> System Overview & Reports</h3>
                    <p class="text-muted m-0">Real-time statistics, registration summaries, and club posting activities.</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card card-custom stat-card p-3" style="border-top-color: var(--primary-red);">
                            <small class="text-muted fw-bold text-uppercase">Active Students</small>
                            <h2 class="fw-bold m-0 text-dark mt-1"><?php echo $total_students; ?></h2>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-custom stat-card p-3" style="border-top-color: var(--dark-blue);">
                            <small class="text-muted fw-bold text-uppercase">Registered Clubs</small>
                            <h2 class="fw-bold m-0 text-dark mt-1"><?php echo $total_orgs; ?></h2>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-custom stat-card p-3" style="border-top-color: #22c55e;">
                            <small class="text-muted fw-bold text-uppercase">Club Officers</small>
                            <h2 class="fw-bold m-0 text-dark mt-1"><?php echo $total_officers; ?></h2>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card card-custom stat-card p-3" style="border-top-color: #0ea5e9;">
                            <small class="text-muted fw-bold text-uppercase">Total Alumni</small>
                            <h2 class="fw-bold m-0 text-dark mt-1"><?php echo $total_alumni; ?></h2>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card card-custom p-4 h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-users text-secondary me-2"></i> Population Breakdown per Grade Level</h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Grade Level</th>
                                            <th class="text-end">Enrolled Students</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($grade_counts as $grade => $count): ?>
                                            <tr>
                                                <td class="fw-semibold text-secondary"><?php echo $grade; ?></td>
                                                <td class="text-end fw-bold text-dark"><?php echo $count; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card card-custom p-4 h-100">
                            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> Recent Club Activities Log</h6>
                            
                            <div class="pe-2 ps-2 pt-1" style="max-height: 320px; overflow-y: auto;">
                                <?php if(count($recent_logs) > 0): ?>
                                    <?php foreach($recent_logs as $log): ?>
                                        <div class="log-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <strong class="text-dark d-block text-truncate mb-1" style="max-width: 200px;"><?php echo htmlspecialchars($log['title']); ?></strong>
                                                <span class="text-muted style-date" style="font-size: 0.7rem;"><?php echo date('M d, g:i A', strtotime($log['date_posted'])); ?></span>
                                            </div>
                                            <small class="badge bg-light text-secondary border rounded-pill px-2 py-0.5"><i class="fa-solid fa-users me-1"></i> <?php echo htmlspecialchars($log['org_name']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fa-solid fa-history fs-3 mb-2 opacity-50"></i>
                                        <p class="small m-0">No posting activities logged yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../bfcache_killer.php'; ?>
</body>
</html>