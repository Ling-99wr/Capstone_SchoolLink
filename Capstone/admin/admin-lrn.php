<?php
include 'admin_security.php'; 
include '../db_conn.php'; 
date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'System Admin';
$message = '';

// Helper function to remove UTF-8 BOM if present in CSV
function remove_bom($str) {
    if (substr($str, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
        $str = substr($str, 3);
    }
    return $str;
}

// ==========================================
// 1. ADD SINGLE LRN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_lrn'])) {
    $lrn = mysqli_real_escape_string($conn, trim($_POST['lrn_number']));
    
    // Validation check for standard Philippine 12-digit LRN
    if (!is_numeric($lrn) || strlen($lrn) !== 12) {
        $message = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i>Invalid LRN! Must be exactly 12 digits.</div>";
    } else {
        // Check if already exists
        $check = mysqli_query($conn, "SELECT id FROM lrn_masterlist WHERE lrn_number = '$lrn'");
        if(mysqli_num_rows($check) > 0) {
            $message = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation me-2'></i>LRN $lrn is already in the Master List!</div>";
        } else {
            mysqli_query($conn, "INSERT INTO lrn_masterlist (lrn_number) VALUES ('$lrn')");
            $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i>LRN successfully added!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// ==========================================
// 2. BULK UPLOAD LRN (CSV FORMAT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    if(isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0){
        $filename = $_FILES['csv_file']['tmp_name'];
        $file = fopen($filename, "r");
        
        $added_count = 0;
        $duplicate_count = 0;
        $is_first_row = true;

        while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
            $raw_lrn = trim($data[0]);
            
            // Handle Excel UTF-8 BOM protection on the very first cell
            if ($is_first_row) {
                $raw_lrn = remove_bom($raw_lrn);
                $is_first_row = false;
            }

            $lrn = mysqli_real_escape_string($conn, $raw_lrn);
            
            // Skip headers like "lrn" or "lrn_number" if present in CSV, validate 12-digit length
            if(!empty($lrn) && is_numeric($lrn) && strlen($lrn) === 12) {
                $insert = mysqli_query($conn, "INSERT IGNORE INTO lrn_masterlist (lrn_number) VALUES ('$lrn')");
                if(mysqli_affected_rows($conn) > 0) {
                    $added_count++;
                } else {
                    $duplicate_count++;
                }
            }
        }
        fclose($file);
        $message = "<div class='alert alert-success alert-dismissible fade show'>
                        <i class='fa-solid fa-file-csv me-2'></i>Bulk upload complete! Added: <strong>$added_count</strong>. Duplicates/Existing skipped: <strong>$duplicate_count</strong>.
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } else {
        $message = "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
    }
}

// ==========================================
// 3. REMOVE LRN (POST Request Form Implementation)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lrn'])) {
    $del_id = intval($_POST['delete_id']);
    mysqli_query($conn, "DELETE FROM lrn_masterlist WHERE id = '$del_id'");
    $message = "<div class='alert alert-warning alert-dismissible fade show'><i class='fa-solid fa-trash me-2'></i>LRN removed from the list.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// ==========================================
// 4. SEARCH & FETCH LRN LIST
// ==========================================
$search_query = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = mysqli_real_escape_string($conn, trim($_GET['search']));
    $where_clause = "WHERE lrn_number LIKE '%$search_query%'";
}

$lrn_list = [];
$q_lrn = mysqli_query($conn, "SELECT * FROM lrn_masterlist $where_clause ORDER BY date_added DESC LIMIT 100"); 
if ($q_lrn) {
    while($row = mysqli_fetch_assoc($q_lrn)) { $lrn_list[] = $row; }
}

// ==========================================
// 5. PROFILE PICTURE LOGIC
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
    <title>LRN Master List | SchoolLink+ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-red: #dc3545; 
            --dark-blue: #1e293b; 
        }
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; overflow-y: scroll; } 
        .navbar-custom { background-color: var(--dark-blue); border-bottom: 3px solid var(--primary-red); }
        .sidebar { background-color: white; min-height: 92vh; border-right: 1px solid #e2e8f0; }
        
        .nav-pills .nav-link { color: #64748b; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background-color: #f8fafc; color: var(--primary-red); }
        .nav-pills .nav-link.active { background-color: var(--primary-red); color: white; box-shadow: 0 4px 6px -1px rgba(220, 53, 69, 0.2); }
        
        .avatar-circle { width: 40px; height: 40px; background-color: var(--primary-red); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; padding: 0;}
        .card-custom { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
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
            
            <div class="col-md-3 col-lg-2 sidebar p-3 d-none d-md-block">
                <h6 class="text-uppercase text-muted fw-bold mb-3 mt-2 px-2" style="font-size: 11px; letter-spacing: 1px;">Core Management</h6>
                <div class="nav nav-pills flex-column mb-4">
                    <a href="admin-dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line w-20px text-center"></i> Dashboard</a>
                    <a href="admin-users.php" class="nav-link"><i class="fa-solid fa-users-gear w-20px text-center"></i> Manage Users</a>
                    <a href="admin-lrn.php" class="nav-link active"><i class="fa-solid fa-id-card-clip w-20px text-center"></i> LRN Master List</a>
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

            <div class="col-md-9 col-lg-10 p-4 bg-light ms-auto">
                
                <div class="mb-4">
                    <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-id-card-clip me-2" style="color: var(--primary-red);"></i> LRN Master List</h3>
                    <p class="text-muted m-0">Only students with their LRNs present in this list can create an account.</p>
                </div>

                <?php echo $message; ?>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card card-custom p-4 bg-white border h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-plus text-primary me-2"></i> Add Single LRN</h6>
                            <form action="" method="POST" class="d-flex gap-2 mt-auto">
                                <input type="text" maxlength="12" pattern="\d{12}" name="lrn_number" class="form-control" placeholder="Enter 12-digit LRN" required>
                                <button type="submit" name="add_single_lrn" class="btn text-white fw-bold px-4" style="background-color: var(--dark-blue);">Add</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-custom p-4 bg-white border h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-csv text-success me-2"></i> Bulk Upload (CSV)</h6>
                            <form action="" method="POST" enctype="multipart/form-data" class="d-flex gap-2 mt-auto">
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                <button type="submit" name="bulk_upload" class="btn btn-dark fw-bold px-4">Upload</button>
                            </form>
                            <small class="text-muted mt-2 d-block" style="font-size: 11px;">*Upload a CSV file containing 12-digit LRNs in the first column.</small>
                        </div>
                    </div>
                </div>

                <div class="card card-custom p-4 bg-white border">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                        <h6 class="fw-bold text-dark m-0">Registered LRN Database</h6>
                        
                        <form action="" method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search LRN..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-outline-dark btn-sm fw-bold px-3">Search</button>
                            <?php if(!empty($search_query)): ?>
                                <a href="admin-lrn.php" class="btn btn-light btn-sm">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 border">
                            <thead class="table-light">
                                <tr>
                                    <th>LRN Number</th>
                                    <th>Date Added</th>
                                    <th>Account Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($lrn_list) > 0): ?>
                                    <?php foreach($lrn_list as $l): ?>
                                        <tr>
                                            <td class="fw-bold text-dark font-monospace"><?php echo htmlspecialchars($l['lrn_number']); ?></td>
                                            <td class="text-muted small"><?php echo date('M d, Y - h:i A', strtotime($l['date_added'])); ?></td>
                                            <td>
                                                <?php if(isset($l['status']) && $l['status'] == 'Registered'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check me-1"></i> Account Created</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Pending Registration</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form action="" method="POST" onsubmit="return confirm('Remove this LRN from the system? Students with this LRN will not be able to register.');" style="display:inline-block;">
                                                    <input type="hidden" name="delete_id" value="<?php echo $l['id']; ?>">
                                                    <button type="submit" name="delete_lrn" class="btn btn-outline-danger btn-sm fw-bold">
                                                        <i class="fa-solid fa-trash me-1"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fa-solid fa-magnifying-glass mb-2 fs-3 opacity-50 d-block"></i>No LRN records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../bfcache_killer.php'; ?>
</body>
</html>