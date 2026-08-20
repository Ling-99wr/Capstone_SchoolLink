<?php
session_start();

// 🔥 ANTI-CACHE HEADERS PARA SA PROTECTED PAGES 🔥
// Pinipigilan ang browser na i-save ang page sa history memory
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';

// ==========================================
// 1. DASHBOARD STATS CALCULATION
// ==========================================
$q_users = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'Student'");
$total_users = ($q_users) ? mysqli_fetch_assoc($q_users)['total'] : 0;

$q_subs = @mysqli_query($conn, "SELECT COUNT(*) as total FROM assessments_submissions");
$total_submissions = ($q_subs) ? mysqli_fetch_assoc($q_subs)['total'] : 0;

$q_orgs = mysqli_query($conn, "SELECT COUNT(*) as total FROM organizations WHERE status = 'Active'");
$total_orgs = ($q_orgs) ? mysqli_fetch_assoc($q_orgs)['total'] : 0;

$q_acts = @mysqli_query($conn, "SELECT COUNT(*) as total FROM org_announcements");
$total_activities = ($q_acts) ? mysqli_fetch_assoc($q_acts)['total'] : 0;

// ==========================================
// 2. BULLETPROOF CHART DATA LOGIC (DB CONNECTED)
// ==========================================
$chart_labels = [];
$chart_data = [];
// Generate past 6 months
for ($i = 5; $i >= 0; $i--) {
    $chart_labels[] = '"' . date('M', strtotime("-$i months")) . '"';
    $chart_data[date('n', strtotime("-$i months"))] = 0; 
}

try {
    // Susubukan nating kunin ang actual user registrations per month
    $q_chart = @mysqli_query($conn, "SELECT MONTH(date_created) as m, COUNT(*) as c FROM users WHERE date_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY MONTH(date_created)");
    if ($q_chart) {
        while($row = mysqli_fetch_assoc($q_chart)) {
            $chart_data[$row['m']] = $row['c'];
        }
    }
} catch (Exception $e) {
    // Safe fallback kung walang date_created
}

$chart_labels_js = implode(',', $chart_labels);
$chart_data_js = implode(',', array_values($chart_data));

// ==========================================
// 3. RECENT SYSTEM LOGS LOGIC (DB CONNECTED)
// ==========================================
$recent_users = [];
try {
    $q_recent = @mysqli_query($conn, "SELECT full_name, role FROM users ORDER BY id DESC LIMIT 3");
    if($q_recent) {
        while($row = mysqli_fetch_assoc($q_recent)) {
            $recent_users[] = $row;
        }
    }
} catch (Exception $e) {}

// ==========================================
// 4. BULLETPROOF PROFILE PICTURE LOGIC
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
    <title>Admin Dashboard | SchoolLink+</title>
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
        
        /* SIDEBAR RED THEME */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.2); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .stat-card { transition: transform 0.2s; border-top: 4px solid var(--dark-blue); }
        .stat-card:hover { transform: translateY(-4px); }
        .icon-box { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        
        /* Hiding specific print elements */
        @media print {
            .sidebar, .navbar, .btn { display: none !important; }
            .col-md-9 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="#">School<span class="text-danger">Link+</span> <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">ADMIN PORTAL</span></a>
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
                        <a href="admin-dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-line w-20px text-center"></i> Dashboard</a>
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
                        <a href="admin-reports.php" class="nav-link"><i class="fa-solid fa-chart-pie w-20px text-center"></i> System Overview</a>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Account</h6>
                    <div class="nav nav-pills flex-column">
                        <a href="admin-profile.php" class="nav-link"><i class="fa-solid fa-user-shield w-20px text-center"></i> My Profile</a>
                    </div>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 p-4 bg-light">
                
                <div class="mb-4 d-flex justify-content-between align-items-end">
                    <div>
                        <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-chart-line me-2" style="color: var(--primary-red);"></i> Administrator Dashboard</h3>
                        <p class="text-muted m-0">Overview of system analytics, user growth, and activities.</p>
                    </div>
                    <button class="btn btn-dark shadow-sm fw-bold px-4" onclick="window.print()"><i class="fa-solid fa-download me-2"></i> Generate Report</button>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card card-custom stat-card p-3 bg-white" style="border-top-color: var(--primary-red);">
                            <div class="d-flex align-items-center">
                                <div class="icon-box text-white me-3" style="background-color: var(--primary-red);"><i class="fa-solid fa-users"></i></div>
                                <div>
                                    <h3 class="fw-bold text-dark m-0"><?php echo number_format($total_users); ?></h3>
                                    <span class="text-muted small fw-semibold">Total Students</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom stat-card p-3 bg-white" style="border-top-color: var(--dark-blue);">
                            <div class="d-flex align-items-center">
                                <div class="icon-box text-white me-3" style="background-color: var(--dark-blue);"><i class="fa-solid fa-clipboard-check"></i></div>
                                <div>
                                    <h3 class="fw-bold text-dark m-0"><?php echo number_format($total_submissions); ?></h3>
                                    <span class="text-muted small fw-semibold">Assessments Done</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom stat-card p-3 bg-white" style="border-top-color: #22c55e;">
                            <div class="d-flex align-items-center">
                                <div class="icon-box text-white me-3" style="background-color: #22c55e;"><i class="fa-solid fa-sitemap"></i></div>
                                <div>
                                    <h3 class="fw-bold text-dark m-0"><?php echo number_format($total_orgs); ?></h3>
                                    <span class="text-muted small fw-semibold">Active Orgs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom stat-card p-3 bg-white" style="border-top-color: #0ea5e9;">
                            <div class="d-flex align-items-center">
                                <div class="icon-box text-white me-3" style="background-color: #0ea5e9;"><i class="fa-solid fa-calendar-days"></i></div>
                                <div>
                                    <h3 class="fw-bold text-dark m-0"><?php echo number_format($total_activities); ?></h3>
                                    <span class="text-muted small fw-semibold">Total Activities</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-8">
                        <div class="card card-custom p-4 bg-white h-100 border">
                            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-chart-area text-primary me-2" style="color: var(--dark-blue) !important;"></i> User Growth & Activity Analytics</h6>
                            <div class="rounded" style="height: 300px; width: 100%; position: relative;">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card card-custom p-4 bg-white h-100 border d-flex flex-column">
                            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-clock-rotate-left text-danger me-2" style="color: var(--primary-red) !important;"></i> Recent System Logs</h6>
                            
                            <div class="flex-grow-1">
                                <?php if(count($recent_users) > 0): ?>
                                    <?php foreach($recent_users as $ru): ?>
                                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                                            <div class="text-primary mt-1" style="color: var(--dark-blue) !important;"><i class="fa-solid fa-user-plus"></i></div>
                                            <div>
                                                <p class="m-0 text-dark small fw-semibold">New user: <?php echo htmlspecialchars($ru['full_name']); ?></p>
                                                <small class="text-muted" style="font-size: 11px;">Registered as <?php echo htmlspecialchars($ru['role']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fa-solid fa-folder-open mb-2 fs-3 opacity-50"></i>
                                        <p class="small m-0">No recent logs available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="pt-3 text-center mt-auto">
                                <a href="admin-reports.php" class="text-decoration-none small fw-bold" style="color: var(--primary-red);">View Full Logs <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // DYNAMIC CHART LOGIC (Connected to Database variables)
        const ctx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(ctx, {
            type: 'bar', 
            data: {
                labels: [<?php echo $chart_labels_js; ?>], 
                datasets: [{
                    label: 'New Users Registered',
                    data: [<?php echo $chart_data_js; ?>], 
                    backgroundColor: 'rgba(30, 41, 59, 0.8)', // Dark Blue Theme
                    borderColor: '#1e293b', 
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1 } 
                    }
                }
            }
        });

        // 🔥 ADVANCED BFCache Killer para sa Protected Pages 🔥
        window.addEventListener('pageshow', function (event) {
            var historyTraversal = event.persisted || 
                                   (typeof window.performance != "undefined" && 
                                    window.performance.navigation.type === 2);
            if (historyTraversal) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>