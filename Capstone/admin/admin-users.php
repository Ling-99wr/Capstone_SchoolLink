<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// ==========================================
// 1. ADD NEW USER (GUIDANCE COUNSELOR ONLY)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fname = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if(mysqli_num_rows($check_email) > 0) {
        $message = "<div class='alert alert-danger shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Email already exists!</div>";
    } else {
        // Automatic na 'Guidance' ang role at 'Staff' ang grade_level
        $insert = "INSERT INTO users (full_name, email, password, role, grade_level, status) VALUES ('$fname', '$email', '$pass', 'Guidance', 'Staff', 'Active')";
        if(mysqli_query($conn, $insert)) {
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>Guidance Counselor account created successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// ==========================================
// 2. EDIT USER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $u_id = intval($_POST['user_id']);
    $fname = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $grade = mysqli_real_escape_string($conn, trim($_POST['grade_level']));

    $update = "UPDATE users SET full_name = '$fname', email = '$email', grade_level = '$grade' WHERE id = '$u_id'";
    if(mysqli_query($conn, $update)) {
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-pen-to-square me-2'></i>User profile updated!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// ==========================================
// 3. ARCHIVE, ALUMNI & RESTORE ACTIONS
// ==========================================
// A. Move to Archive
if (isset($_GET['archive_id'])) {
    $arch_id = intval($_GET['archive_id']);
    mysqli_query($conn, "UPDATE users SET status = 'Archived' WHERE id = '$arch_id'");
    header("Location: admin-users.php?tab=archived");
    exit();
}

// B. Move to Alumni
if (isset($_GET['alumni_id'])) {
    $alum_id = intval($_GET['alumni_id']);
    mysqli_query($conn, "UPDATE users SET grade_level = 'Alumni', status = 'Active', is_officer = 0, organization_name = NULL WHERE id = '$alum_id'");
    header("Location: admin-users.php?tab=alumni");
    exit();
}

// C. Restore to Active
if (isset($_GET['restore_id'])) {
    $restore_id = intval($_GET['restore_id']);
    mysqli_query($conn, "UPDATE users SET status = 'Active' WHERE id = '$restore_id'");
    header("Location: admin-users.php?tab=active");
    exit();
}

// D. PERMANENT DELETE (Hard Delete)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM users WHERE id = '$delete_id'");
    header("Location: admin-users.php?tab=archived");
    exit();
}

// ==========================================
// 4. ROLE MANAGEMENT (OFFICERS & GUIDANCE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_officer'])) {
    $student_id = intval($_POST['student_id']);
    $org_name = mysqli_real_escape_string($conn, $_POST['org_name']);
    mysqli_query($conn, "UPDATE users SET is_officer = 1, organization_name = '$org_name' WHERE id = '$student_id'");
    $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-star me-2'></i>Promoted to Officer! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demote_officer'])) {
    $student_id = intval($_POST['student_id']);
    mysqli_query($conn, "UPDATE users SET is_officer = 0, organization_name = NULL WHERE id = '$student_id'");
    $message = "<div class='alert alert-warning alert-dismissible fade show shadow-sm'><i class='fa-solid fa-user-minus me-2'></i>Officer privileges revoked.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// GUIDANCE COUNSELOR PROMOTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_guidance'])) {
    $u_id = intval($_POST['user_id']);
    mysqli_query($conn, "UPDATE users SET role = 'Guidance', is_officer = 0, organization_name = NULL WHERE id = '$u_id'");
    $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-user-tie me-2'></i>User promoted to Guidance Counselor successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_guidance'])) {
    $u_id = intval($_POST['user_id']);
    // Gagawin nating 'Deactivated Staff' ang grade level para mawalan siya ng access
    mysqli_query($conn, "UPDATE users SET grade_level = 'Deactivated Staff' WHERE id = '$u_id'");
    $message = "<div class='alert alert-warning alert-dismissible fade show shadow-sm'><i class='fa-solid fa-user-slash me-2'></i>Guidance Counselor privileges have been deactivated. Account remains in the system but access is denied.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_guidance'])) {
    $u_id = intval($_POST['user_id']);
    // Pag ni-reactivate o ginawang Guidance, ibabalik sa 'Staff' ang grade level
    mysqli_query($conn, "UPDATE users SET role = 'Guidance', grade_level = 'Staff', is_officer = 0, organization_name = NULL WHERE id = '$u_id'");
    $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-user-tie me-2'></i>Guidance Counselor account activated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// ==========================================
// 5. ACADEMIC YEAR END ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['graduate_g12'])) {
    mysqli_query($conn, "UPDATE users SET grade_level = 'Alumni', status = 'Active', is_officer = 0, organization_name = NULL WHERE (grade_level = 'Grade 12' OR grade_level = '12') AND role = 'Student'");
    header("Location: admin-users.php?tab=alumni");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_all'])) {
    // 1st Step: I-graduate muna ang mga Grade 12 para lumuwag ang space (Hubaran din ng Officer roles)
    mysqli_query($conn, "UPDATE users SET grade_level = 'Alumni', is_officer = 0, organization_name = NULL WHERE (grade_level = 'Grade 12' OR grade_level = '12') AND role = 'Student' AND status = 'Active'");
    
    // 2nd Step: I-promote na ang mga nasa ilalim (Sunod-sunod mula taas pababa para hindi magka-mix-up!)
    mysqli_query($conn, "UPDATE users SET grade_level = 'Grade 12' WHERE (grade_level = 'Grade 11' OR grade_level = '11') AND role = 'Student' AND status = 'Active'");
    mysqli_query($conn, "UPDATE users SET grade_level = 'Grade 11' WHERE (grade_level = 'Grade 10' OR grade_level = '10') AND role = 'Student' AND status = 'Active'");
    mysqli_query($conn, "UPDATE users SET grade_level = 'Grade 10' WHERE (grade_level = 'Grade 9' OR grade_level = '9') AND role = 'Student' AND status = 'Active'");
    mysqli_query($conn, "UPDATE users SET grade_level = 'Grade 9' WHERE (grade_level = 'Grade 8' OR grade_level = '8') AND role = 'Student' AND status = 'Active'");
    mysqli_query($conn, "UPDATE users SET grade_level = 'Grade 8' WHERE (grade_level = 'Grade 7' OR grade_level = '7') AND role = 'Student' AND status = 'Active'");
    
    $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm'><i class='fa-solid fa-arrow-trend-up me-2'></i>All students successfully promoted! Grade 12 students are now Alumni.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// ==========================================
// TAB LOGIC, FILTER, & FETCH DATA
// ==========================================
$current_tab = $_GET['tab'] ?? 'active';
$grade_filter = $_GET['grade_filter'] ?? '';

// Default base: Wag isama ang mismong Admin account sa listahan
$where_sql = "role != 'Admin'";

if ($current_tab === 'archived') {
    $where_sql .= " AND status = 'Archived'";
    
    // Pwede pa rin i-filter sa loob ng archive tab
    if (!empty($grade_filter)) {
        if ($grade_filter === 'Counselors') {
            $where_sql .= " AND role = 'Guidance'";
        } else {
            $safe_grade = mysqli_real_escape_string($conn, $grade_filter);
            $where_sql .= " AND role = 'Student' AND grade_level = '$safe_grade'";
        }
    }
    
} elseif ($current_tab === 'alumni') {
    $where_sql .= " AND status = 'Active' AND grade_level = 'Alumni'";
    
} else { 
    // ACTIVE TAB LOGIC
    $where_sql .= " AND status = 'Active' AND grade_level != 'Alumni'";
    
    // Dito lang natin itatago ang Guidance kapag naka "All Filter"
    if (!empty($grade_filter)) {
        if ($grade_filter === 'Counselors') {
            $where_sql .= " AND role = 'Guidance'";
        } else {
            $safe_grade = mysqli_real_escape_string($conn, $grade_filter);
            $where_sql .= " AND role = 'Student' AND grade_level = '$safe_grade'";
        }
    } else {
        // Kapag "All Filter" sa Active Tab, Students lang ipapakita
        $where_sql .= " AND role = 'Student'";
    }
}

$students = [];
$q_students = mysqli_query($conn, "SELECT id, full_name, email, grade_level, is_officer, organization_name, role FROM users WHERE $where_sql ORDER BY role ASC, grade_level ASC, full_name ASC");
if ($q_students) { while($row = mysqli_fetch_assoc($q_students)) { $students[] = $row; } }

$organizations = [];
$q_orgs = mysqli_query($conn, "SELECT org_name FROM organizations WHERE status = 'Active' ORDER BY org_name ASC");
if ($q_orgs) { while($row = mysqli_fetch_assoc($q_orgs)) { $organizations[] = $row; } }

// ==========================================
// BULLETPROOF PROFILE PICTURE LOGIC
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
    <title>Manage Users | SchoolLink+ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-red: #dc3545; 
            --dark-blue: #1e293b; 
            --admin-color: #4f46e5;
        }
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; overflow-y: scroll; }
        .navbar-custom { background-color: var(--dark-blue); border-bottom: 3px solid var(--primary-red); z-index: 1030;}
        
        /* 🔥 THE ULTIMATE STICKY SIDEBAR FIX 🔥 */
        .sidebar { background-color: white; border-right: 1px solid #e2e8f0; }
        @media (min-width: 768px) {
            .sidebar { 
                position: -webkit-sticky;
                position: sticky !important;
                top: 56px !important;
                height: calc(100vh - 56px) !important;
                overflow-y: auto !important;
                z-index: 100;
            }
        }
        
        /* UNIFORM SIDEBAR STYLES (GRAY/RED) */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.2); }
        .w-20px { width: 20px; text-align: center; }
        
        .avatar-circle { width: 32px; height: 32px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0; font-size: 0.85rem;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        /* TABS & DROPDOWN STYLING */
        .custom-tabs .nav-link { color: var(--dark-blue); font-weight: 600; border: none; padding: 8px 20px; border-radius: 8px; transition: 0.2s; }
        .custom-tabs .nav-link.active { background-color: white; color: var(--primary-red); box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .custom-dropdown { border-radius: 12px; border: 1px solid #e2e8f0; padding: 8px; min-width: 180px; }
        .custom-dropdown .dropdown-item { border-radius: 6px; padding: 8px 16px; font-size: 0.9rem; font-weight: 500; transition: 0.2s; margin-bottom: 2px; }
        .custom-dropdown .dropdown-item:hover { background-color: #f1f5f9; color: var(--dark-blue); }
        .custom-dropdown .dropdown-item.text-danger:hover { background-color: #fef2f2; color: #dc2626 !important; }
        .kebab-btn { background-color: #f8fafc; border: 1px solid transparent; transition: 0.2s; color: #64748b; }
        .kebab-btn:hover { background-color: #e2e8f0; color: var(--dark-blue); border-color: #cbd5e1; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top py-2">
        <div class="container-fluid px-4">
            <button class="navbar-toggler me-2 d-lg-none border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarContent">
                <i class="fa-solid fa-bars fs-4"></i>
            </button>
            <a class="navbar-brand fw-bold" href="admin-dashboard.php">School<span class="text-danger">Link+</span> <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">ADMIN PORTAL</span></a>
            
            <div class="collapse navbar-collapse" id="adminNavbarContent">
                <div class="d-md-none my-3 border-bottom pb-3">
                    <h6 class="text-uppercase text-light opacity-75 fw-bold mb-2" style="font-size: 11px; letter-spacing: 1px;">Core Management</h6>
                    <div class="nav nav-pills flex-column mb-3">
                        <a href="admin-dashboard.php" class="nav-link text-white-50"><i class="fa-solid fa-chart-line w-20px text-center"></i> Dashboard</a>
                        <a href="admin-users.php" class="nav-link active text-white"><i class="fa-solid fa-users-gear w-20px text-center"></i> Manage Users</a>
                        <a href="admin-lrn.php" class="nav-link text-white-50"><i class="fa-solid fa-id-card-clip w-20px text-center"></i> LRN Master List</a>
                    </div>
                </div>

                <div class="ms-auto d-flex align-items-center text-white">
                    <div class="avatar-circle me-2 shadow-sm"><?php echo $avatar_html; ?></div>
                    <span class="me-4 d-none d-md-inline">Welcome, <strong><?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?></strong></span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm px-2 py-1" style="font-size: 0.85rem;"><i class="fa-solid fa-sign-out-alt text-center"></i> Logout</a>
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
                        <a href="admin-users.php" class="nav-link active"><i class="fa-solid fa-users-gear w-20px text-center"></i> Manage Users</a>
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
                
                <div class="mb-4">
                    <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-users-gear me-2" style="color: var(--primary-red);"></i> User Management</h3>
                    <p class="text-muted m-0">View, add, edit, and assign organization roles to active students.</p>
                </div>

                <?php echo $message ?? ''; ?>

                <?php if($current_tab === 'active'): ?>
                <div class="card card-custom p-3 bg-white border mb-4 border-start border-4 border-warning">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h6 class="fw-bold text-dark m-0"><i class="fa-solid fa-calendar-check text-warning me-2"></i> Academic Year End Actions</h6>
                            <small class="text-muted">Use these options carefully. These will affect all registered active students.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="" class="m-0" onsubmit="return confirm('Are you sure you want to graduate ALL Grade 12 students? They will be moved to the Alumni List.');">
                                <button type="submit" name="graduate_g12" class="btn btn-outline-dark fw-bold btn-sm px-3"><i class="fa-solid fa-graduation-cap me-1"></i> Graduate Grade 12</button>
                            </form>
                            <form method="POST" action="" class="m-0" onsubmit="return confirm('WARNING: Are you sure you want to promote ALL students to the next grade level?');">
                                <button type="submit" name="promote_all" class="btn btn-outline-danger fw-bold btn-sm px-3"><i class="fa-solid fa-arrow-trend-up me-1"></i> Mass Promote</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <ul class="nav nav-tabs custom-tabs border-0 gap-2 mb-4 bg-secondary bg-opacity-10 p-1 rounded-3" style="max-width: fit-content;">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'active') ? 'active' : ''; ?>" href="admin-users.php?tab=active">
                            <i class="fa-solid fa-user-check me-1"></i> Active Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'alumni') ? 'active' : ''; ?>" href="admin-users.php?tab=alumni">
                            <i class="fa-solid fa-user-graduate me-1"></i> Alumni List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'archived') ? 'active' : ''; ?>" href="admin-users.php?tab=archived">
                            <i class="fa-solid fa-box-archive me-1"></i> Archived
                        </a>
                    </li>
                </ul>

                <div class="card card-custom p-4 bg-white border">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                        <h6 class="fw-bold text-dark m-0">
                            <?php 
                                if($current_tab === 'active') echo 'Active Users Database';
                                elseif($current_tab === 'alumni') echo 'Alumni Database';
                                else echo 'Archived Accounts Database';
                            ?>
                        </h6>
                        
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            
                            <!-- 🔥 NEW: LIVE SEARCH BAR 🔥 -->
                            <div class="input-group input-group-sm shadow-sm" style="width: 220px;">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-0" placeholder="Search by name...">
                            </div>

                            <?php if($current_tab !== 'alumni'): ?>
                                <form method="GET" action="admin-users.php" class="m-0 d-flex gap-2">
                                    <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
                                    <select name="grade_filter" class="form-select form-select-sm fw-bold text-secondary shadow-sm" onchange="this.form.submit()" style="min-width: 140px;">
                                        <option value="">All Filter</option> 
                                        <option value="Grade 7" <?php if($grade_filter == 'Grade 7') echo 'selected'; ?>>Grade 7</option>
                                        <option value="Grade 8" <?php if($grade_filter == 'Grade 8') echo 'selected'; ?>>Grade 8</option>
                                        <option value="Grade 9" <?php if($grade_filter == 'Grade 9') echo 'selected'; ?>>Grade 9</option>
                                        <option value="Grade 10" <?php if($grade_filter == 'Grade 10') echo 'selected'; ?>>Grade 10</option>
                                        <option value="Grade 11" <?php if($grade_filter == 'Grade 11') echo 'selected'; ?>>Grade 11</option>
                                        <option value="Grade 12" <?php if($grade_filter == 'Grade 12') echo 'selected'; ?>>Grade 12</option>
                                        <option value="Counselors" <?php if($grade_filter == 'Counselors') echo 'selected'; ?>>Counselors</option>
                                    </select>
                                </form>
                            <?php endif; ?>

                            <?php if($current_tab === 'active'): ?>
                                <button class="btn btn-primary btn-sm fw-bold px-3 text-white shadow-sm" style="background-color: var(--dark-blue); border:none;" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fa-solid fa-user-plus me-1"></i> Add Counselor
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 border" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>Grade Level</th>
                                    <th>Email</th>
                                    <th>Status / Role</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($students) > 0): ?>
                                    <?php foreach($students as $stu): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($stu['full_name']); ?></td>
                                            <td>
                                                <?php if($stu['grade_level'] === 'Alumni'): ?>
                                                    <span class="badge bg-info text-dark rounded-pill">Alumni</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($stu['grade_level']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($stu['email']); ?></td>
                                            
                                            <!-- 🔥 UPDATED: STATUS / ROLE BADGE 🔥 -->
                                            <td>
                                                <?php if($current_tab === 'archived'): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1">Archived Account</span>
                                                <?php else: ?>
                                                    <?php if($stu['role'] === 'Guidance'): ?>
                                                        <?php if($stu['grade_level'] === 'Deactivated Staff'): ?>
                                                            <span class="badge bg-secondary rounded-pill px-3 py-1"><i class="fa-solid fa-user-slash me-1"></i> Deactivated Staff</span>
                                                        <?php else: ?>
                                                            <span class="badge rounded-pill text-white px-3 py-1" style="background-color: var(--admin-color);"><i class="fa-solid fa-user-tie me-1"></i> Guidance Counselor</span>
                                                        <?php endif; ?>
                                                    <?php elseif($stu['is_officer'] == 1): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-star me-1"></i> <?php echo htmlspecialchars($stu['organization_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-25 text-dark rounded-pill px-3 py-1">Regular Student</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- 🔥 UPDATED: ACTIONS DROPDOWN 🔥 -->
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm rounded-circle kebab-btn px-2 py-1" data-bs-toggle="dropdown">
                                                        <i class="fa-solid fa-ellipsis-vertical fs-6"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow custom-dropdown">
                                                        
                                                        <?php if($current_tab === 'active'): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   data-id="<?php echo $stu['id']; ?>" 
                                                                   data-name="<?php echo htmlspecialchars($stu['full_name']); ?>"
                                                                   data-email="<?php echo htmlspecialchars($stu['email']); ?>"
                                                                   data-grade="<?php echo htmlspecialchars($stu['grade_level']); ?>"
                                                                   onclick="triggerEditModal(this)">
                                                                   <i class="fa-solid fa-pen text-secondary me-2" style="width: 16px;"></i> Edit Info
                                                                </a>
                                                            </li>
                                                            
                                                            <?php if($stu['role'] === 'Guidance'): ?>
                                                                <!-- 🔥 DITO ANG MAGIC PARA SA STAFF 🔥 -->
                                                                <?php if($stu['grade_level'] === 'Deactivated Staff'): ?>
                                                                    <li>
                                                                        <form method="POST" action="" class="m-0" onsubmit="return confirm('Reactivate this Counselor account?');">
                                                                            <input type="hidden" name="user_id" value="<?php echo $stu['id']; ?>">
                                                                            <button type="submit" name="assign_guidance" class="dropdown-item text-success fw-bold">
                                                                                <i class="fa-solid fa-user-check me-2" style="width: 16px;"></i> Reactivate Account
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php else: ?>
                                                                    <li>
                                                                        <form method="POST" action="" class="m-0" onsubmit="return confirm('Deactivate this Counselor account?');">
                                                                            <input type="hidden" name="user_id" value="<?php echo $stu['id']; ?>">
                                                                            <button type="submit" name="revoke_guidance" class="dropdown-item text-warning fw-bold">
                                                                                <i class="fa-solid fa-user-slash me-2" style="width: 16px;"></i> Deactivate Account
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                            <?php else: ?>
                                                                <!-- STUDENT ACTIONS -->
                                                                <?php if($stu['is_officer'] == 1): ?>
                                                                    <li>
                                                                        <form method="POST" action="" class="m-0" onsubmit="return confirm('Revoke officer privileges?');">
                                                                            <input type="hidden" name="student_id" value="<?php echo $stu['id']; ?>">
                                                                            <button type="submit" name="demote_officer" class="dropdown-item text-warning fw-bold">
                                                                                <i class="fa-solid fa-arrow-down me-2" style="width: 16px;"></i> Revoke Officer
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php else: ?>
                                                                    <li>
                                                                        <a class="dropdown-item text-success fw-bold" href="#" 
                                                                           data-id="<?php echo $stu['id']; ?>" 
                                                                           data-name="<?php echo htmlspecialchars($stu['full_name']); ?>"
                                                                           onclick="triggerPromoteModal(this)">
                                                                           <i class="fa-solid fa-star me-2" style="width: 16px;"></i> Promote to Officer
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" action="" class="m-0" onsubmit="return confirm('Promote to Guidance Counselor?');">
                                                                            <input type="hidden" name="user_id" value="<?php echo $stu['id']; ?>">
                                                                            <button type="submit" name="assign_guidance" class="dropdown-item fw-bold" style="color: var(--admin-color);">
                                                                                <i class="fa-solid fa-user-tie me-2" style="width: 16px;"></i> Assign as Guidance
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <li><hr class="dropdown-divider my-1"></li>
                                                                <li>
                                                                    <a class="dropdown-item text-dark fw-bold" href="admin-users.php?alumni_id=<?php echo $stu['id']; ?>" onclick="return confirm('Mark this user as an Alumni?');">
                                                                        <i class="fa-solid fa-user-graduate me-2" style="width: 16px;"></i> Move to Alumni
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            
                                                            <!-- COMMON ARCHIVE BUTTON FOR ACTIVE TAB -->
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="admin-users.php?archive_id=<?php echo $stu['id']; ?>" onclick="return confirm('Archive this user?');">
                                                                    <i class="fa-solid fa-box-archive me-2" style="width: 16px;"></i> Archive User
                                                                </a>
                                                            </li>

                                                        <?php elseif($current_tab === 'alumni'): ?>
                                                            <!-- ALUMNI ACTIONS -->
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   data-id="<?php echo $stu['id']; ?>" 
                                                                   data-name="<?php echo htmlspecialchars($stu['full_name']); ?>"
                                                                   data-email="<?php echo htmlspecialchars($stu['email']); ?>"
                                                                   data-grade="Alumni"
                                                                   onclick="triggerEditModal(this)">
                                                                   <i class="fa-solid fa-pen text-secondary me-2" style="width: 16px;"></i> Edit Info
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider my-1"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="admin-users.php?archive_id=<?php echo $stu['id']; ?>" onclick="return confirm('Move this alumni to Archive?');">
                                                                    <i class="fa-solid fa-box-archive me-2" style="width: 16px;"></i> Move to Archive
                                                                </a>
                                                            </li>

                                                        <?php else: // ARCHIVED ACTIONS ?>
                                                            <li>
                                                                <a class="dropdown-item text-success fw-bold" href="admin-users.php?restore_id=<?php echo $stu['id']; ?>" onclick="return confirm('Restore this user account?');">
                                                                    <i class="fa-solid fa-arrow-up-from-bracket me-2" style="width: 16px;"></i> Restore Account
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider my-1"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger fw-bold" href="admin-users.php?delete_id=<?php echo $stu['id']; ?>" onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY delete this user? This action cannot be undone.');">
                                                                    <i class="fa-solid fa-trash-can me-2" style="width: 16px;"></i> Permanent Delete
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fa-solid <?php echo ($current_tab === 'active') ? 'fa-users' : ($current_tab === 'alumni' ? 'fa-user-graduate' : 'fa-box-archive'); ?> mb-2 fs-3 opacity-50 d-block"></i>No users found matching your filters.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="" method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" style="color: var(--dark-blue);"><i class="fa-solid fa-user-tie me-2"></i> Add Guidance Counselor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small shadow-sm">
                            <i class="fa-solid fa-circle-info me-1"></i> Students must register via the public registration page using their LRN.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">FULL NAME</label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Clara" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">EMAIL ADDRESS</label>
                            <input type="email" name="email" class="form-control" placeholder="counselor@school.edu.ph" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">TEMPORARY PASSWORD</label>
                            <input type="text" name="password" class="form-control" value="Guidance123!" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn text-white fw-bold px-4" style="background-color: var(--dark-blue);">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="" method="POST">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-primary" style="color: var(--dark-blue) !important;"></i> Edit User Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">FULL NAME</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">EMAIL ADDRESS</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">GRADE LEVEL / ALUMNI</label>
                            <select name="grade_level" id="edit_grade" class="form-select" required>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 10">Grade 10</option>
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                                <option value="Alumni">Alumni</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-dark fw-bold px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="promoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="" method="POST">
                    <input type="hidden" name="student_id" id="promote_student_id">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-ranking-star me-2"></i> Assign Officer Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-4">Assign <strong class="text-dark" id="promote_student_name"></strong> to an organization.</p>
                        <select name="org_name" class="form-select p-2 fw-semibold border-success" required>
                            <option value="" disabled selected>-- Select an Organization --</option>
                            <?php foreach($organizations as $org): ?>
                                <option value="<?php echo htmlspecialchars($org['org_name']); ?>"><?php echo htmlspecialchars($org['org_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="promote_officer" class="btn btn-success fw-bold w-100">Confirm Promotion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function triggerEditModal(btn) {
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_full_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_email').value = btn.getAttribute('data-email');
            
            let grade = btn.getAttribute('data-grade');
            if(!grade.includes('Grade') && grade !== 'Alumni') { grade = 'Grade ' + grade; }
            document.getElementById('edit_grade').value = grade;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function triggerPromoteModal(btn) {
            document.getElementById('promote_student_id').value = btn.getAttribute('data-id');
            document.getElementById('promote_student_name').innerText = btn.getAttribute('data-name');
            new bootstrap.Modal(document.getElementById('promoteModal')).show();
        }

        // 🔥 NEW: REAL-TIME LIVE SEARCH SCRIPT 🔥
        document.getElementById('liveSearchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#usersTable tbody tr');

            rows.forEach(row => {
                // Ignore the "No users found" row
                if(row.cells.length === 1) return;

                // Hanapin ang name sa first column (index 0)
                let nameCell = row.cells[0]; 
                if (nameCell) {
                    let textValue = nameCell.textContent || nameCell.innerText;
                    if (textValue.toLowerCase().indexOf(filter) > -1) {
                        row.style.display = ""; // Ipakita kung may match
                    } else {
                        row.style.display = "none"; // Itago kung walang match
                    }
                }
            });
        });
    </script>
    
    <?php include '../bfcache_killer.php'; ?>
</body>
</html>