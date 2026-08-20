<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// ==========================================
// 1. UPDATE PROFILE INFORMATION & PICTURE (SECURED)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['full_name']);
    $new_email = trim($_POST['email']);
    
    $profile_pic_query = "";
    $uploaded_filename = null;
    $upload_success = true;

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $uploaded_filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/' . $uploaded_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $_SESSION['profile_picture'] = $uploaded_filename; 
            } else {
                $upload_success = false;
                $message = "<div class='alert alert-danger shadow-sm border-0'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to upload the file.</div>";
            }
        } else {
            $upload_success = false;
            $message = "<div class='alert alert-warning shadow-sm border-0'><i class='fa-solid fa-triangle-exclamation me-2'></i>Invalid image format. Only JPG, PNG, and GIF are allowed.</div>";
        }
    }

    if ($upload_success) {
        if ($uploaded_filename !== null) {
            $update_sql = "UPDATE users SET full_name = ?, email = ?, profile_picture = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_update, "sssi", $new_name, $new_email, $uploaded_filename, $user_id);
        } else {
            $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_update, "ssi", $new_name, $new_email, $user_id);
        }

        if (mysqli_stmt_execute($stmt_update)) {
            $_SESSION['full_name'] = $new_name;
            if (empty($message)) { 
                $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0'><i class='fa-solid fa-circle-check me-2'></i>Profile updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        } else {
            $message = "<div class='alert alert-danger shadow-sm border-0'><i class='fa-solid fa-triangle-exclamation me-2'></i>Error updating profile. Email might already be taken.</div>";
        }
        mysqli_stmt_close($stmt_update);
    }
}

// ==========================================
// 2. REMOVE PROFILE PICTURE (NEW FEATURE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_pic'])) {
    $stmt_pic = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pic, "i", $user_id);
    mysqli_stmt_execute($stmt_pic);
    $res_pic = mysqli_stmt_get_result($stmt_pic);
    $old_pic = mysqli_fetch_assoc($res_pic)['profile_picture'] ?? null;
    mysqli_stmt_close($stmt_pic);

    if (!empty($old_pic) && file_exists("../uploads/" . $old_pic)) {
        unlink("../uploads/" . $old_pic); // Burahin physical file sa folder
    }

    $stmt_rem = mysqli_prepare($conn, "UPDATE users SET profile_picture = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt_rem, "i", $user_id);
    if (mysqli_stmt_execute($stmt_rem)) {
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0'><i class='fa-solid fa-circle-check me-2'></i>Profile photo removed successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    mysqli_stmt_close($stmt_rem);
}

// ==========================================
// 3. CHANGE PASSWORD (SECURED)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt_pass = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pass, "i", $user_id);
    mysqli_stmt_execute($stmt_pass);
    $res_pass = mysqli_stmt_get_result($stmt_pass);
    $db_pass = mysqli_fetch_assoc($res_pass)['password'] ?? '';
    mysqli_stmt_close($stmt_pass);

    if (!password_verify($current_password, $db_pass)) {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm border-0'><i class='fa-solid fa-triangle-exclamation me-2'></i>Current password is incorrect!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else if ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-warning alert-dismissible fade show shadow-sm border-0'><i class='fa-solid fa-circle-exclamation me-2'></i>New passwords do not match!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt_change = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_change, "si", $hashed_new, $user_id);
        
        if (mysqli_stmt_execute($stmt_change)) {
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0'><i class='fa-solid fa-shield-check me-2'></i>Password changed successfully! Keep it safe.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger shadow-sm border-0'><i class='fa-solid fa-triangle-exclamation me-2'></i>Failed to update password. Please try again.</div>";
        }
        mysqli_stmt_close($stmt_change);
    }
}

// ==========================================
// 4. FETCH CURRENT ADMIN DATA (SECURED)
// ==========================================
$stmt_admin = mysqli_prepare($conn, "SELECT full_name, email, profile_picture FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_admin, "i", $user_id);
mysqli_stmt_execute($stmt_admin);
$res_admin = mysqli_stmt_get_result($stmt_admin);
$admin_data = mysqli_fetch_assoc($res_admin);
mysqli_stmt_close($stmt_admin);

$full_name = $_SESSION['full_name'] ?? $admin_data['full_name'] ?? 'System Admin';
$profile_pic = $admin_data['profile_picture'] ?? null;

$words = explode(" ", $full_name); $initials = "";
foreach ($words as $w) { $initials .= mb_substr($w, 0, 1); }
$initials = strtoupper(substr($initials, 0, 2));

$avatar_html = "";
$has_custom_pic = false;

if (!empty($profile_pic) && file_exists("../uploads/" . $profile_pic)) {
    $avatar_html = "<img src='../uploads/" . htmlspecialchars($profile_pic) . "' alt='Profile' style='width: 100%; height: 100%; object-fit: cover;'>";
    $has_custom_pic = true;
} else {
    $avatar_html = $initials;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | SchoolLink+ Admin</title>
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
        
        /* UPDATED SIDEBAR CSS (RED ACTIVE THEME) */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.25); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0; }
        
        /* MODERN CARD STYLING */
        .card-custom { border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); overflow: hidden; background: white; }
        
        /* PROFILE AVATAR & BANNER (RED ACCENT) */
        .profile-banner { height: 120px; background: linear-gradient(135deg, var(--dark-blue), #334155); border-bottom: 4px solid var(--primary-red); position: relative; }
        .profile-avatar-large { 
            width: 110px; height: 110px; 
            background-color: white; color: var(--dark-blue); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-size: 2.8rem; font-weight: 800; margin: -55px auto 15px auto; 
            border: 5px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            position: relative; z-index: 2; overflow: hidden; padding: 0;
        }

        /* MODERN TABS */
        .modern-tabs { border-bottom: 2px solid #e2e8f0; }
        .modern-tabs .nav-link { color: #64748b; font-weight: 600; padding: 15px 25px; border: none; border-bottom: 3px solid transparent; background: transparent; transition: 0.3s ease; border-radius: 0; }
        .modern-tabs .nav-link:hover { color: var(--dark-blue); }
        .modern-tabs .nav-link.active { color: var(--dark-blue); border-bottom: 3px solid var(--dark-blue); }

        /* INPUT STYLING */
        .form-control-modern { padding: 12px 15px; border-radius: 10px; border: 1px solid #cbd5e1; background-color: #f8fafc; transition: 0.2s; }
        .form-control-modern:focus { background-color: white; border-color: var(--dark-blue); box-shadow: 0 0 0 4px rgba(30, 41, 59, 0.1); }
        .form-label-modern { font-weight: 700; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; text-transform: uppercase; margin-bottom: 8px; }

        /* BUTTON STYLING (RED HOVER) */
        .btn-modern { background-color: var(--dark-blue); color: white; border-radius: 10px; padding: 12px 24px; font-weight: 600; transition: 0.3s; border: none; }
        .btn-modern:hover { background-color: var(--primary-red); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); color: white; }
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
                        <a href="admin-reports.php" class="nav-link text-white-50"><i class="fa-solid fa-chart-pie"></i> System Overview</a>
                        <a href="admin-profile.php" class="nav-link active text-white"><i class="fa-solid fa-user-shield"></i> My Profile</a>
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
                        <a href="admin-reports.php" class="nav-link"><i class="fa-solid fa-chart-pie w-20px text-center"></i> System Overview</a>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold mb-3 px-2" style="font-size: 11px; letter-spacing: 1px;">Account</h6>
                    <div class="nav nav-pills flex-column">
                        <a href="admin-profile.php" class="nav-link active"><i class="fa-solid fa-user-shield w-20px text-center"></i> My Profile</a>
                    </div>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 p-4 bg-light">
                
                <div class="mb-5">
                    <h3 class="fw-bold text-dark m-0">Administrator Profile</h3>
                    <p class="text-muted m-0">Manage your personal information and account security.</p>
                </div>

                <?php echo $message; ?>

                <div class="row g-4">
                    
                    <div class="col-md-4">
                        <div class="card card-custom h-100 pb-4 text-center">
                            <div class="profile-banner"></div>
                            <div class="profile-avatar-large shadow"><?php echo $avatar_html; ?></div>
                            <div class="px-4 mt-2">
                                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($admin_data['full_name'] ?? ''); ?></h4>
                                <p class="text-muted mb-4"><?php echo htmlspecialchars($admin_data['email'] ?? ''); ?></p>
                                
                                <div class="d-inline-flex align-items-center bg-danger bg-opacity-10 text-danger px-4 py-2 rounded-pill fw-bold mb-3" style="font-size: 0.85rem;">
                                    <i class="fa-solid fa-crown me-2"></i> System Administrator
                                </div>
                                
                                <?php if($has_custom_pic): ?>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove your profile photo?');">
                                        <button type="submit" name="remove_pic" class="btn btn-outline-danger btn-sm fw-bold px-3 rounded-pill mt-2">
                                            <i class="fa-solid fa-trash-can me-1"></i> Remove Photo
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card card-custom h-100">
                            <div class="px-3 pt-2">
                                <ul class="nav nav-tabs modern-tabs" id="profileTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#info">
                                            <i class="fa-regular fa-id-badge me-2"></i>Personal Info
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#security">
                                            <i class="fa-solid fa-shield-halved me-2"></i>Security Settings
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="card-body p-4">
                                <div class="tab-content">
                                    
                                    <div class="tab-pane fade show active" id="info">
                                        <form action="" method="POST" enctype="multipart/form-data">
                                            
                                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                                <label class="form-label-modern text-dark"><i class="fa-solid fa-camera me-2"></i>Update Profile Picture (Optional)</label>
                                                <input type="file" name="profile_pic" accept="image/*" class="form-control form-control-modern bg-white">
                                            </div>

                                            <div class="row g-4 mb-4">
                                                <div class="col-12">
                                                    <label class="form-label-modern">Full Name</label>
                                                    <input type="text" name="full_name" class="form-control form-control-modern" value="<?php echo htmlspecialchars($admin_data['full_name'] ?? ''); ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label-modern">Email Address</label>
                                                    <input type="email" name="email" class="form-control form-control-modern" value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            <button type="submit" name="update_profile" class="btn-modern w-100">
                                                <i class="fa-solid fa-floppy-disk me-2"></i> Save Changes
                                            </button>
                                        </form>
                                    </div>

                                    <div class="tab-pane fade" id="security">
                                        <form action="" method="POST">
                                            <div class="d-flex align-items-center p-3 mb-4 rounded-3" style="background-color: #f1f5f9; border-left: 4px solid var(--dark-blue);">
                                                <i class="fa-solid fa-circle-info text-dark fs-4 me-3"></i>
                                                <small class="text-secondary fw-medium">Ensure your new password is secure. You will be required to use this upon your next login.</small>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label class="form-label-modern">Current Password</label>
                                                <input type="password" name="current_password" class="form-control form-control-modern" placeholder="••••••••" required>
                                            </div>
                                            
                                            <div class="row g-3 mb-5">
                                                <div class="col-md-6">
                                                    <label class="form-label-modern">New Password</label>
                                                    <input type="password" name="new_password" class="form-control form-control-modern" placeholder="••••••••" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-modern">Confirm New Password</label>
                                                    <input type="password" name="confirm_password" class="form-control form-control-modern" placeholder="••••••••" required>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="change_password" class="btn-modern w-100">
                                                <i class="fa-solid fa-lock me-2"></i> Update Password
                                            </button>
                                        </form>
                                    </div>

                                </div>
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