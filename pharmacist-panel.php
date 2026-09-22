<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['pharmacist_username'])) {
    header("Location: index.php");
    exit();
}

include('func_pharmacy.php');

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

$pharmacist_user = $_SESSION['pharmacist_username'];
$pharmacist_name = $_SESSION['pharmacist_name'] ?? $pharmacist_user;

// Dashboard Metrics
$total_meds_res = mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb");
$total_meds = mysqli_fetch_assoc($total_meds_res)['total'] ?? 0;

$low_stock_res = mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE quantity <= 10 AND quantity > 0 AND expiry_date >= CURRENT_DATE()");
$low_stock_count = mysqli_fetch_assoc($low_stock_res)['total'] ?? 0;

$out_stock_res = mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE quantity <= 0");
$out_stock_count = mysqli_fetch_assoc($out_stock_res)['total'] ?? 0;

$expired_res = mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE expiry_date < CURRENT_DATE()");
$expired_count = mysqli_fetch_assoc($expired_res)['total'] ?? 0;

$today_sales_res = mysqli_query($con, "SELECT IFNULL(SUM(total_amount), 0) AS total FROM pharmacy_sales WHERE DATE(sale_date) = CURRENT_DATE()");
$today_sales = mysqli_fetch_assoc($today_sales_res)['total'] ?? 0;

// Recent stock movements for dashboard
$recent_movs_res = mysqli_query($con, "SELECT sm.*, m.medicine_name FROM medicine_stock_movements sm JOIN medicinetb m ON sm.medicine_id = m.medicine_id ORDER BY sm.movement_id DESC LIMIT 8");

// Filter and Search for Inventory Tab
$med_search = trim($_GET['med_search'] ?? '');
$cat_filter = trim($_GET['cat_filter'] ?? '');

$query_inv = "SELECT * FROM medicinetb WHERE 1=1";
if (!empty($med_search)) {
    $safe_search = mysqli_real_escape_string($con, $med_search);
    $query_inv .= " AND (medicine_name LIKE '%$safe_search%' OR generic_name LIKE '%$safe_search%' OR batch_no LIKE '%$safe_search%')";
}
if (!empty($cat_filter)) {
    $safe_cat = mysqli_real_escape_string($con, $cat_filter);
    $query_inv .= " AND category = '$safe_cat'";
}
$query_inv .= " ORDER BY medicine_name ASC";
$result_inv = mysqli_query($con, $query_inv);

// Categories list for filter and dropdowns
$cat_res = mysqli_query($con, "SELECT DISTINCT category FROM medicinetb WHERE category IS NOT NULL AND category != ''");

// All active available medicines for dispensing dropdown
$active_meds = mysqli_query($con, "SELECT medicine_id, medicine_name, generic_name, quantity, unit_price, expiry_date, batch_no FROM medicinetb WHERE quantity > 0 AND expiry_date >= CURRENT_DATE() ORDER BY medicine_name ASC");
$active_meds_array = [];
while ($m = mysqli_fetch_assoc($active_meds)) {
    $active_meds_array[] = $m;
}

// Patient search result for Prescriptions / Patient tab
$search_pat_query = trim($_GET['pat_search'] ?? '');
$searched_patients = [];
if (!empty($search_pat_query)) {
    $sp_safe = mysqli_real_escape_string($con, $search_pat_query);
    $pat_sql = "SELECT * FROM patreg WHERE pid = '$sp_safe' OR contact LIKE '%$sp_safe%' OR fname LIKE '%$sp_safe%' OR lname LIKE '%$sp_safe%'";
    $pat_res = mysqli_query($con, $pat_sql);
    while ($p = mysqli_fetch_assoc($pat_res)) {
        $searched_patients[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Smart Hospital - Pharmacy Management</title>
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="font-awesome-4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: #f8f9fa;
            padding-top: 65px;
        }
        .bg-primary {
            background: -webkit-linear-gradient(left, #3931af, #00c6ff) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .list-group-item.active {
            z-index: 2;
            color: #fff;
            background-color: #342ac1;
            border-color: #342ac1;
        }
        .btn-primary {
            background-color: #342ac1;
            border-color: #342ac1;
        }
        .btn-primary:hover {
            background-color: #271fa3;
            border-color: #271fa3;
        }
        .badge-available { background-color: #28a745; color: #fff; }
        .badge-low-stock { background-color: #ffc107; color: #212529; }
        .badge-out-stock { background-color: #dc3545; color: #fff; }
        .badge-expired { background-color: #6c757d; color: #fff; }
        .card-stat {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-3px);
        }
        .table th {
            background-color: #f1f4f9;
            color: #495057;
            font-weight: 600;
        }
        @media print {
            body * { visibility: hidden; }
            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="pharmacist-panel.php">
            <i class="fa fa-medkit" aria-hidden="true"></i> Smart Hospital | Pharmacy Management
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <i class="fa fa-user-circle"></i> Logged in as: <strong><?php echo htmlspecialchars($pharmacist_name); ?></strong>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-light btn-sm ml-2 px-3 text-white" href="func_pharmacy.php?logout_pharmacist=1">
                        <i class="fa fa-sign-out"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 25px;">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="list-group shadow-sm" id="list-tab" role="tablist">
                <a class="list-group-item list-group-item-action active" id="tab-dash-link" data-toggle="list" href="#list-dash" role="tab">
                    <i class="fa fa-tachometer"></i> &nbsp; Dashboard
                </a>
                <a class="list-group-item list-group-item-action" id="tab-med-link" data-toggle="list" href="#list-med" role="tab">
                    <i class="fa fa-list"></i> &nbsp; Medicine Inventory
                </a>
                <a class="list-group-item list-group-item-action" id="tab-addmed-link" data-toggle="list" href="#list-addmed" role="tab">
                    <i class="fa fa-plus-circle"></i> &nbsp; Add Medicine
                </a>
                <a class="list-group-item list-group-item-action" id="tab-presc-link" data-toggle="list" href="#list-presc" role="tab">
                    <i class="fa fa-file-text-o"></i> &nbsp; Patient & Prescriptions
                </a>
                <a class="list-group-item list-group-item-action" id="tab-dispense-link" data-toggle="list" href="#list-dispense" role="tab">
                    <i class="fa fa-shopping-cart"></i> &nbsp; Dispense Medicine
                </a>
                <a class="list-group-item list-group-item-action" id="tab-alerts-link" data-toggle="list" href="#list-alerts" role="tab">
                    <i class="fa fa-bell"></i> &nbsp; Alerts & Stock Warnings
                    <?php if ($low_stock_count > 0 || $expired_count > 0): ?>
                        <span class="badge badge-danger badge-pill float-right"><?php echo ($low_stock_count + $expired_count); ?></span>
                    <?php endif; ?>
                </a>
                <a class="list-group-item list-group-item-action" id="tab-sales-link" data-toggle="list" href="#list-sales" role="tab">
                    <i class="fa fa-history"></i> &nbsp; Sales History
                </a>
            </div>
        </div>

        <!-- Main Tab Content -->
        <div class="col-md-9 col-lg-10">
            <div class="tab-content" id="nav-tabContent">

                <!-- 1. DASHBOARD TAB -->
                <div class="tab-pane fade show active" id="list-dash" role="tabpanel">
                    <h3 class="mb-4 text-dark font-weight-bold">
                        <i class="fa fa-dashboard text-primary"></i> Pharmacy Dashboard Overview
                    </h3>

                    <div class="row">
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card card-stat bg-white p-3 border-left border-primary" style="border-left-width: 5px !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Total Medicines</h6>
                                        <h3 class="text-primary font-weight-bold mb-0"><?php echo $total_meds; ?></h3>
                                    </div>
                                    <i class="fa fa-cubes fa-2x text-primary opacity-50"></i>
                                </div>
                                <small class="mt-2 text-muted"><a href="#list-med" onclick="$('#tab-med-link').click();">View catalog &rarr;</a></small>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card card-stat bg-white p-3 border-left border-warning" style="border-left-width: 5px !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Low Stock (≤ 10)</h6>
                                        <h3 class="text-warning font-weight-bold mb-0"><?php echo $low_stock_count; ?></h3>
                                    </div>
                                    <i class="fa fa-exclamation-triangle fa-2x text-warning"></i>
                                </div>
                                <small class="mt-2 text-muted"><a href="#list-alerts" onclick="$('#tab-alerts-link').click();">Inspect low stock &rarr;</a></small>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card card-stat bg-white p-3 border-left border-danger" style="border-left-width: 5px !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Out of Stock</h6>
                                        <h3 class="text-danger font-weight-bold mb-0"><?php echo $out_stock_count; ?></h3>
                                    </div>
                                    <i class="fa fa-times-circle fa-2x text-danger"></i>
                                </div>
                                <small class="mt-2 text-muted"><a href="#list-alerts" onclick="$('#tab-alerts-link').click();">Requires reorder &rarr;</a></small>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-6 mb-3">
                            <div class="card card-stat bg-white p-3 border-left border-secondary" style="border-left-width: 5px !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Expired Medicines</h6>
                                        <h3 class="text-secondary font-weight-bold mb-0"><?php echo $expired_count; ?></h3>
                                    </div>
                                    <i class="fa fa-calendar-times-o fa-2x text-secondary"></i>
                                </div>
                                <small class="mt-2 text-muted"><a href="#list-alerts" onclick="$('#tab-alerts-link').click();">View expired batches &rarr;</a></small>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-6 mb-3">
                            <div class="card card-stat bg-white p-3 border-left border-success" style="border-left-width: 5px !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Today's Dispensed Sales</h6>
                                        <h3 class="text-success font-weight-bold mb-0">$<?php echo number_format($today_sales, 2); ?></h3>
                                    </div>
                                    <i class="fa fa-money fa-2x text-success"></i>
                                </div>
                                <small class="mt-2 text-muted"><a href="#list-sales" onclick="$('#tab-sales-link').click();">View transaction log &rarr;</a></small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Row -->
                    <div class="card shadow-sm mt-3">
                        <div class="card-header bg-white font-weight-bold">
                            <i class="fa fa-bolt text-warning"></i> Quick Operations
                        </div>
                        <div class="card-body">
                            <button class="btn btn-primary mr-2 mb-2" onclick="$('#tab-dispense-link').click();">
                                <i class="fa fa-cart-plus"></i> Dispense New Prescription
                            </button>
                            <button class="btn btn-outline-primary mr-2 mb-2" onclick="$('#tab-addmed-link').click();">
                                <i class="fa fa-plus"></i> Add New Medicine
                            </button>
                            <button class="btn btn-outline-info mr-2 mb-2" onclick="$('#tab-presc-link').click();">
                                <i class="fa fa-user-circle"></i> Lookup Patient Prescriptions
                            </button>
                            <button class="btn btn-outline-secondary mb-2" onclick="$('#tab-sales-link').click();">
                                <i class="fa fa-print"></i> Search Bills & Invoices
                            </button>
                        </div>
                    </div>

                    <!-- Recent Stock Movements Card -->
                    <div class="card shadow-sm mt-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold"><i class="fa fa-history text-primary"></i> Recent Stock Movements</span>
                            <a href="admin-panel1.php#list-movements" class="small">Full history &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Type</th>
                                            <th>Qty</th>
                                            <th>Batch</th>
                                            <th>By / Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_movs_res && mysqli_num_rows($recent_movs_res) > 0):
                                            while ($rmov = mysqli_fetch_assoc($recent_movs_res)):
                                                $rm_type = $rmov['movement_type'];
                                                $rm_badge = ($rm_type === 'PURCHASE') ? 'badge-primary' : (($rm_type === 'RESTOCK') ? 'badge-success' : (($rm_type === 'DISPENSE') ? 'badge-warning' : 'badge-info'));
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($rmov['medicine_name']); ?></strong></td>
                                                <td><span class="badge <?php echo $rm_badge; ?>"><?php echo htmlspecialchars($rm_type); ?></span></td>
                                                <td>
                                                    <?php if ($rm_type === 'DISPENSE'): ?>
                                                        <strong class="text-danger">-<?php echo $rmov['quantity']; ?></strong>
                                                    <?php else: ?>
                                                        <strong class="text-success">+<?php echo $rmov['quantity']; ?></strong>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($rmov['batch_no']); ?></code></td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($rmov['performed_by']); ?><br><span class="text-muted"><?php echo htmlspecialchars($rmov['movement_date']); ?></span></small>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="5" class="text-center text-muted py-3">No stock movements recorded yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. MEDICINE INVENTORY TAB -->
                <div class="tab-pane fade" id="list-med" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                            <h4 class="mb-0 text-dark"><i class="fa fa-medkit text-primary"></i> Medicine Inventory</h4>
                            <button class="btn btn-sm btn-primary mt-1" onclick="$('#tab-addmed-link').click();">
                                <i class="fa fa-plus"></i> Add Medicine
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Search & Filter Bar -->
                            <form method="get" action="pharmacist-panel.php" class="form-inline mb-3">
                                <input type="text" name="med_search" class="form-control mr-2 mb-2" placeholder="Search by name, generic, batch..." value="<?php echo htmlspecialchars($med_search); ?>" style="min-width: 250px;">
                                <select name="cat_filter" class="form-control mr-2 mb-2">
                                    <option value="">All Categories</option>
                                    <?php 
                                    mysqli_data_seek($cat_res, 0);
                                    while ($c = mysqli_fetch_assoc($cat_res)): 
                                        $selected = ($cat_filter === $c['category']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($c['category']); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($c['category']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" class="btn btn-primary mb-2 mr-2"><i class="fa fa-search"></i> Search</button>
                                <a href="pharmacist-panel.php#list-med" class="btn btn-outline-secondary mb-2">Reset</a>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Medicine Name</th>
                                            <th>Generic Name</th>
                                            <th>Category</th>
                                            <th>Batch No</th>
                                            <th>Expiry Date</th>
                                            <th>Stock Qty</th>
                                            <th>Unit Price</th>
                                            <th>Supplier</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result_inv && mysqli_num_rows($result_inv) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result_inv)): 
                                                $status = calculate_medicine_status($row['quantity'], $row['expiry_date']);
                                                $badge_class = 'badge-available';
                                                if ($status === 'Low Stock') $badge_class = 'badge-low-stock';
                                                elseif ($status === 'Out of Stock') $badge_class = 'badge-out-stock';
                                                elseif ($status === 'Expired') $badge_class = 'badge-expired';
                                            ?>
                                                <tr>
                                                    <td><?php echo $row['medicine_id']; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['generic_name']); ?></td>
                                                    <td><span class="badge badge-light border"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                                    <td><code><?php echo htmlspecialchars($row['batch_no']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($row['expiry_date']); ?></td>
                                                    <td><strong><?php echo $row['quantity']; ?></strong></td>
                                                    <td>$<?php echo number_format($row['unit_price'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                                                    <td><span class="badge <?php echo $badge_class; ?> px-2 py-1"><?php echo $status; ?></span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info edit-med-btn" 
                                                            data-id="<?php echo $row['medicine_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['medicine_name']); ?>"
                                                            data-generic="<?php echo htmlspecialchars($row['generic_name']); ?>"
                                                            data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                                            data-manufacturer="<?php echo htmlspecialchars($row['manufacturer']); ?>"
                                                            data-batch="<?php echo htmlspecialchars($row['batch_no']); ?>"
                                                            data-expiry="<?php echo htmlspecialchars($row['expiry_date']); ?>"
                                                            data-qty="<?php echo $row['quantity']; ?>"
                                                            data-price="<?php echo $row['unit_price']; ?>"
                                                            data-supplier="<?php echo htmlspecialchars($row['supplier']); ?>"
                                                            title="Edit Medicine">
                                                            <i class="fa fa-pencil"></i>
                                                        </button>
                                                        <a href="func_pharmacy.php?delete_medicine=<?php echo $row['medicine_id']; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this medicine?');"
                                                           title="Delete Medicine">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-muted py-4">No medicines found matching criteria.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ADD MEDICINE TAB -->
                <div class="tab-pane fade" id="list-addmed" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white font-weight-bold">
                            <h4 class="mb-0 text-dark"><i class="fa fa-plus-circle text-primary"></i> Add New Medicine</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="func_pharmacy.php">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Medicine Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="medicine_name" placeholder="e.g. Paracetamol 500mg" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Generic Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="generic_name" placeholder="e.g. Acetaminophen" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Category <span class="text-danger">*</span></label>
                                        <select class="form-control" name="category" required>
                                            <option value="" disabled selected>Select Category</option>
                                            <option value="Tablet">Tablet</option>
                                            <option value="Capsule">Capsule</option>
                                            <option value="Syrup">Syrup</option>
                                            <option value="Injection">Injection</option>
                                            <option value="Ointment">Ointment</option>
                                            <option value="Drops">Drops</option>
                                            <option value="Inhaler">Inhaler</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Manufacturer <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="manufacturer" placeholder="e.g. Cipla Ltd" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Batch Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="batch_no" placeholder="e.g. BTC-2024-88" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Expiry Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="expiry_date" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Initial Stock Quantity <span class="text-danger">*</span></label>
                                        <input type="number" min="0" class="form-control" name="quantity" placeholder="e.g. 100" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Unit Price ($) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" class="form-control" name="unit_price" placeholder="e.g. 12.50" required>
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label>Supplier / Distributor <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="supplier" placeholder="e.g. MedLife Pharma Distributors" required>
                                    </div>
                                </div>
                                <button type="submit" name="add_medicine" class="btn btn-primary px-4 py-2">
                                    <i class="fa fa-check"></i> Save Medicine
                                </button>
                                <button type="reset" class="btn btn-light border px-3 py-2">Reset</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 4. PATIENT & PRESCRIPTIONS VIEW TAB -->
                <div class="tab-pane fade" id="list-presc" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h4 class="mb-0 text-dark"><i class="fa fa-user-md text-primary"></i> Patient & Doctor Prescriptions Lookup</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" action="pharmacist-panel.php" class="form-inline mb-4">
                                <label class="mr-2 font-weight-bold">Find Patient:</label>
                                <input type="text" name="pat_search" class="form-control mr-2" placeholder="Enter Patient ID, Name or Phone..." value="<?php echo htmlspecialchars($search_pat_query); ?>" style="min-width: 320px;" required>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search Patient</button>
                            </form>

                            <?php if (!empty($search_pat_query)): ?>
                                <?php if (!empty($searched_patients)): ?>
                                    <?php foreach ($searched_patients as $sp): ?>
                                        <div class="card mb-4 border-primary">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0 text-primary">
                                                    <i class="fa fa-user"></i> Patient #<?php echo $sp['pid']; ?>: <?php echo htmlspecialchars($sp['fname'] . ' ' . $sp['lname']); ?>
                                                </h5>
                                                <button class="btn btn-sm btn-success" onclick="selectPatientForDispense(<?php echo $sp['pid']; ?>, '<?php echo addslashes($sp['fname'] . ' ' . $sp['lname']); ?>')">
                                                    <i class="fa fa-cart-plus"></i> Dispense to this Patient
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-3"><strong>Gender:</strong> <?php echo htmlspecialchars($sp['gender']); ?></div>
                                                    <div class="col-md-3"><strong>Contact:</strong> <?php echo htmlspecialchars($sp['contact']); ?></div>
                                                    <div class="col-md-6"><strong>Email:</strong> <?php echo htmlspecialchars($sp['email']); ?></div>
                                                </div>

                                                <!-- Load Prescriptions from prestb -->
                                                <h6 class="font-weight-bold text-secondary border-bottom pb-2">Prescription History</h6>
                                                <?php 
                                                $pid_sp = $sp['pid'];
                                                $presc_res = mysqli_query($con, "SELECT * FROM prestb WHERE pid = $pid_sp ORDER BY appdate DESC, apptime DESC");
                                                if ($presc_res && mysqli_num_rows($presc_res) > 0):
                                                ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Appt #</th>
                                                                    <th>Doctor</th>
                                                                    <th>Date & Time</th>
                                                                    <th>Diagnosis / Disease</th>
                                                                    <th>Allergies</th>
                                                                    <th>Doctor Prescription</th>
                                                                    <th>Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php while ($pr = mysqli_fetch_assoc($presc_res)): ?>
                                                                    <tr>
                                                                        <td><?php echo $pr['ID']; ?></td>
                                                                        <td><strong>Dr. <?php echo htmlspecialchars($pr['doctor']); ?></strong></td>
                                                                        <td><?php echo htmlspecialchars($pr['appdate'] . ' ' . $pr['apptime']); ?></td>
                                                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($pr['disease']); ?></span></td>
                                                                        <td><span class="text-danger font-weight-bold"><?php echo htmlspecialchars($pr['allergy']); ?></span></td>
                                                                        <td><div class="p-1 bg-light border rounded"><?php echo nl2br(htmlspecialchars($pr['prescription'])); ?></div></td>
                                                                        <td>
                                                                            <button class="btn btn-sm btn-primary" onclick="selectPrescriptionForDispense(<?php echo $sp['pid']; ?>, '<?php echo addslashes($sp['fname'] . ' ' . $sp['lname']); ?>', <?php echo $pr['ID']; ?>, '<?php echo addslashes($pr['doctor']); ?>')">
                                                                                <i class="fa fa-check"></i> Select
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endwhile; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted mb-0">No doctor prescriptions found in records for this patient.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">No patients found matching "<?php echo htmlspecialchars($search_pat_query); ?>".</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Search for a registered patient above to review their disease diagnoses, allergy notes, and doctor prescriptions.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. DISPENSE MEDICINE TAB -->
                <div class="tab-pane fade" id="list-dispense" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="mb-0 text-dark"><i class="fa fa-shopping-cart text-primary"></i> Dispense Medicines & Bill Patient</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="func_pharmacy.php" id="dispenseForm">
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label>Select Patient <span class="text-danger">*</span></label>
                                        <select class="form-control" name="pid" id="dispense_pid" required>
                                            <option value="" disabled selected>-- Select Patient --</option>
                                            <?php 
                                            $all_pats = mysqli_query($con, "SELECT pid, fname, lname, contact FROM patreg ORDER BY fname ASC");
                                            while ($pt = mysqli_fetch_assoc($all_pats)):
                                            ?>
                                                <option value="<?php echo $pt['pid']; ?>">
                                                    #<?php echo $pt['pid']; ?> - <?php echo htmlspecialchars($pt['fname'] . ' ' . $pt['lname']); ?> (<?php echo htmlspecialchars($pt['contact']); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Attending Doctor (Optional)</label>
                                        <input type="text" class="form-control" name="doctor_name" id="dispense_doctor" placeholder="e.g. Dr. Dinesh">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Linked Appointment # (Optional)</label>
                                        <input type="number" class="form-control" name="appointment_id" id="dispense_app_id" placeholder="e.g. 11">
                                    </div>
                                </div>

                                <hr>
                                <h5 class="font-weight-bold mb-3 text-secondary">Prescribed Medicine Line Items</h5>

                                <div id="medicineRowsContainer">
                                    <!-- Row 1 -->
                                    <div class="row medicine-row align-items-center mb-2 pb-2 border-bottom">
                                        <div class="col-md-5">
                                            <label class="small font-weight-bold">Medicine</label>
                                            <select class="form-control med-select" name="med_id[]" required onchange="updateMedRow(this)">
                                                <option value="" data-price="0" data-stock="0">-- Select Available Medicine --</option>
                                                <?php foreach ($active_meds_array as $am): ?>
                                                    <option value="<?php echo $am['medicine_id']; ?>" 
                                                            data-price="<?php echo $am['unit_price']; ?>" 
                                                            data-stock="<?php echo $am['quantity']; ?>"
                                                            data-expiry="<?php echo $am['expiry_date']; ?>">
                                                        <?php echo htmlspecialchars($am['medicine_name']); ?> | Batch: <?php echo htmlspecialchars($am['batch_no']); ?> | Stock: <?php echo $am['quantity']; ?> | $<?php echo number_format($am['unit_price'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold">Unit Price ($)</label>
                                            <input type="text" class="form-control unit-price-input" readonly value="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold">Quantity</label>
                                            <input type="number" min="1" value="1" class="form-control qty-input" name="dispense_qty[]" required oninput="calcRowSubtotal(this)">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold">Subtotal ($)</label>
                                            <input type="text" class="form-control subtotal-input" readonly value="0.00">
                                        </div>
                                        <div class="col-md-1 text-center">
                                            <label class="small d-block font-weight-bold">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMedRow(this)" title="Remove Item">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMedRow()">
                                    <i class="fa fa-plus"></i> Add Another Medicine
                                </button>

                                <hr>

                                <div class="row align-items-center mt-3">
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold">Payment Method</label>
                                        <select class="form-control" name="payment_type" required>
                                            <option value="Cash" selected>Cash</option>
                                            <option value="Card">Credit/Debit Card</option>
                                            <option value="UPI">UPI / Digital</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold">Pharmacist Notes</label>
                                        <input type="text" class="form-control" name="notes" placeholder="e.g. Instructions given">
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <h6 class="text-muted text-uppercase mb-1 font-weight-bold">Total Bill Amount</h6>
                                        <h2 class="text-primary font-weight-bold mb-0" id="grandTotalText">$0.00</h2>
                                    </div>
                                </div>

                                <div class="mt-4 text-right">
                                    <button type="submit" name="dispense_submit" class="btn btn-success btn-lg px-5 py-2 font-weight-bold">
                                        <i class="fa fa-check-circle"></i> Confirm Dispense & Generate Bill
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 6. ALERTS TAB -->
                <div class="tab-pane fade" id="list-alerts" role="tabpanel">
                    <h3 class="mb-4 text-dark font-weight-bold"><i class="fa fa-bell text-danger"></i> Stock & Expiry Alerts</h3>

                    <!-- Low Stock Alerts -->
                    <div class="card shadow-sm mb-4 border-warning">
                        <div class="card-header bg-warning text-dark font-weight-bold">
                            <i class="fa fa-exclamation-triangle"></i> Low-Stock Medicines (Quantity ≤ 10)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th>Batch</th>
                                        <th>Available Qty</th>
                                        <th>Unit Price</th>
                                        <th>Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $low_q = mysqli_query($con, "SELECT * FROM medicinetb WHERE quantity <= 10 AND quantity > 0 AND expiry_date >= CURRENT_DATE() ORDER BY quantity ASC");
                                    if ($low_q && mysqli_num_rows($low_q) > 0):
                                        while ($l = mysqli_fetch_assoc($low_q)):
                                    ?>
                                        <tr>
                                            <td><?php echo $l['medicine_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($l['medicine_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($l['category']); ?></td>
                                            <td><?php echo htmlspecialchars($l['batch_no']); ?></td>
                                            <td><span class="badge badge-warning font-weight-bold px-2 py-1"><?php echo $l['quantity']; ?> left</span></td>
                                            <td>$<?php echo number_format($l['unit_price'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($l['supplier']); ?></td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-3">No low-stock medicines found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Out of Stock Alerts -->
                    <div class="card shadow-sm mb-4 border-danger">
                        <div class="card-header bg-danger text-white font-weight-bold">
                            <i class="fa fa-times-circle"></i> Out of Stock Medicines (Quantity = 0)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th>Batch</th>
                                        <th>Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $out_q = mysqli_query($con, "SELECT * FROM medicinetb WHERE quantity <= 0 ORDER BY medicine_name ASC");
                                    if ($out_q && mysqli_num_rows($out_q) > 0):
                                        while ($o = mysqli_fetch_assoc($out_q)):
                                    ?>
                                        <tr>
                                            <td><?php echo $o['medicine_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($o['medicine_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($o['category']); ?></td>
                                            <td><?php echo htmlspecialchars($o['batch_no']); ?></td>
                                            <td><?php echo htmlspecialchars($o['supplier']); ?></td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No out-of-stock medicines. Stock levels healthy!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Expired Medicines Alerts -->
                    <div class="card shadow-sm mb-4 border-secondary">
                        <div class="card-header bg-secondary text-white font-weight-bold">
                            <i class="fa fa-calendar-times-o"></i> Expired Medicines (Expired before Today)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Medicine Name</th>
                                        <th>Batch</th>
                                        <th>Expiry Date</th>
                                        <th>Stock in Store</th>
                                        <th>Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $exp_q = mysqli_query($con, "SELECT * FROM medicinetb WHERE expiry_date < CURRENT_DATE() ORDER BY expiry_date ASC");
                                    if ($exp_q && mysqli_num_rows($exp_q) > 0):
                                        while ($e = mysqli_fetch_assoc($exp_q)):
                                    ?>
                                        <tr>
                                            <td><?php echo $e['medicine_id']; ?></td>
                                            <td><strong class="text-danger"><?php echo htmlspecialchars($e['medicine_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($e['batch_no']); ?></td>
                                            <td><span class="badge badge-dark"><?php echo htmlspecialchars($e['expiry_date']); ?></span></td>
                                            <td><?php echo $e['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($e['supplier']); ?></td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">No expired medicines found in inventory.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 7. SALES HISTORY TAB -->
                <div class="tab-pane fade" id="list-sales" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-dark"><i class="fa fa-history text-primary"></i> Pharmacy Sales & Billing History</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Bill #</th>
                                            <th>Date & Time</th>
                                            <th>Patient Name</th>
                                            <th>Doctor</th>
                                            <th>Pharmacist</th>
                                            <th>Payment Method</th>
                                            <th>Total Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $sales_sql = "SELECT s.*, p.fname, p.lname, p.contact FROM pharmacy_sales s LEFT JOIN patreg p ON s.pid = p.pid ORDER BY s.bill_id DESC";
                                        $sales_res = mysqli_query($con, $sales_sql);
                                        if ($sales_res && mysqli_num_rows($sales_res) > 0):
                                            while ($sale = mysqli_fetch_assoc($sales_res)):
                                                $p_name = htmlspecialchars(($sale['fname'] ?? 'Unknown') . ' ' . ($sale['lname'] ?? ''));
                                        ?>
                                            <tr>
                                                <td><strong>#<?php echo $sale['bill_id']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                                                <td><?php echo $p_name; ?> <br><small class="text-muted"><?php echo htmlspecialchars($sale['contact'] ?? ''); ?></small></td>
                                                <td><?php echo !empty($sale['doctor_name']) ? 'Dr. ' . htmlspecialchars($sale['doctor_name']) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($sale['pharmacist_username']); ?></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($sale['payment_type']); ?></span></td>
                                                <td><strong class="text-success">$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewBillDetails(<?php echo $sale['bill_id']; ?>)">
                                                        <i class="fa fa-eye"></i> View Bill
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="8" class="text-center text-muted py-4">No pharmacy sales recorded yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- EDIT MEDICINE MODAL -->
<div class="modal fade" id="editMedicineModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="func_pharmacy.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-pencil-square-o"></i> Edit Medicine Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="medicine_id" id="edit_med_id">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Medicine Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="medicine_name" id="edit_med_name" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Generic Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="generic_name" id="edit_generic_name" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Category <span class="text-danger">*</span></label>
                            <select class="form-control" name="category" id="edit_category" required>
                                <option value="Tablet">Tablet</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Syrup">Syrup</option>
                                <option value="Injection">Injection</option>
                                <option value="Ointment">Ointment</option>
                                <option value="Drops">Drops</option>
                                <option value="Inhaler">Inhaler</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Manufacturer <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="manufacturer" id="edit_manufacturer" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Batch Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="batch_no" id="edit_batch_no" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expiry_date" id="edit_expiry_date" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" min="0" class="form-control" name="quantity" id="edit_quantity" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Unit Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" id="edit_unit_price" required>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Supplier <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="supplier" id="edit_supplier" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_medicine" class="btn btn-primary">Update Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW BILL INVOICE MODAL -->
<div class="modal fade" id="viewBillModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-file-text-o"></i> Pharmacy Bill & Receipt</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="printableInvoice">
                <div class="text-center pb-3 border-bottom mb-3">
                    <h3 class="font-weight-bold text-primary mb-0"><i class="fa fa-hospital-o"></i> Smart Hospital Pharmacy</h3>
                    <p class="text-muted mb-0">Official Medication Dispensing Receipt</p>
                </div>
                <div id="billDetailsContent">
                    <p class="text-center text-muted">Loading invoice details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print Invoice</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

<script>
    // Handle Edit Medicine button
    $('.edit-med-btn').on('click', function() {
        $('#edit_med_id').val($(this).data('id'));
        $('#edit_med_name').val($(this).data('name'));
        $('#edit_generic_name').val($(this).data('generic'));
        $('#edit_category').val($(this).data('category'));
        $('#edit_manufacturer').val($(this).data('manufacturer'));
        $('#edit_batch_no').val($(this).data('batch'));
        $('#edit_expiry_date').val($(this).data('expiry'));
        $('#edit_quantity').val($(this).data('qty'));
        $('#edit_unit_price').val($(this).data('price'));
        $('#edit_supplier').val($(this).data('supplier'));
        $('#editMedicineModal').modal('show');
    });

    // Handle Medicine Row calculations in Dispense Tab
    function updateMedRow(selectEl) {
        var row = $(selectEl).closest('.medicine-row');
        var opt = $(selectEl).find('option:selected');
        var price = parseFloat(opt.data('price')) || 0;
        var maxStock = parseInt(opt.data('stock')) || 0;

        row.find('.unit-price-input').val(price.toFixed(2));
        var qtyInput = row.find('.qty-input');
        qtyInput.attr('max', maxStock);
        
        if (maxStock <= 0) {
            qtyInput.val(0);
        } else if (parseInt(qtyInput.val()) > maxStock) {
            qtyInput.val(maxStock);
        }
        calcRowSubtotal(qtyInput);
    }

    function calcRowSubtotal(qtyEl) {
        var row = $(qtyEl).closest('.medicine-row');
        var price = parseFloat(row.find('.unit-price-input').val()) || 0;
        var maxStock = parseInt(row.find('.med-select option:selected').data('stock')) || 0;
        var qty = parseInt($(qtyEl).val()) || 0;

        if (qty > maxStock && maxStock > 0) {
            alert('Cannot dispense more than available stock (' + maxStock + ')!');
            $(qtyEl).val(maxStock);
            qty = maxStock;
        }

        var subtotal = price * qty;
        row.find('.subtotal-input').val(subtotal.toFixed(2));
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var grandTotal = 0;
        $('.subtotal-input').each(function() {
            grandTotal += parseFloat($(this).val()) || 0;
        });
        $('#grandTotalText').text('$' + grandTotal.toFixed(2));
    }

    function addMedRow() {
        var firstRow = $('.medicine-row').first().clone();
        firstRow.find('input').val('');
        firstRow.find('.qty-input').val(1);
        firstRow.find('.unit-price-input').val('0.00');
        firstRow.find('.subtotal-input').val('0.00');
        firstRow.find('.med-select').prop('selectedIndex', 0);
        $('#medicineRowsContainer').append(firstRow);
    }

    function removeMedRow(btn) {
        if ($('.medicine-row').length > 1) {
            $(btn).closest('.medicine-row').remove();
            calculateGrandTotal();
        } else {
            alert('At least one medicine item is required.');
        }
    }

    // Select Patient for Dispensing from Prescriptions view
    function selectPatientForDispense(pid, name) {
        $('#tab-dispense-link').click();
        $('#dispense_pid').val(pid);
    }

    function selectPrescriptionForDispense(pid, name, apptId, doctor) {
        $('#tab-dispense-link').click();
        $('#dispense_pid').val(pid);
        $('#dispense_doctor').val(doctor);
        $('#dispense_app_id').val(apptId);
    }

    // View Bill details modal via AJAX
    function viewBillDetails(billId) {
        $('#billDetailsContent').html('<p class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Loading Bill #' + billId + '...</p>');
        $('#viewBillModal').modal('show');

        $.get('pharmacist-panel.php?ajax_bill=' + billId, function(data) {
            $('#billDetailsContent').html(data);
        });
    }

    // URL Hash tab switching
    $(document).ready(function() {
        var hash = window.location.hash;
        if (hash) {
            $('.list-group a[href="' + hash + '"]').tab('show');
        }
        $('a[data-toggle="list"]').on('shown.bs.tab', function (e) {
            window.location.hash = e.target.hash;
        });
    });
</script>

</body>
</html>
<?php
// Handle AJAX request for Bill Details
if (isset($_GET['ajax_bill'])) {
    $b_id = intval($_GET['ajax_bill']);
    $bill_q = mysqli_query($con, "SELECT s.*, p.fname, p.lname, p.contact, p.email FROM pharmacy_sales s LEFT JOIN patreg p ON s.pid = p.pid WHERE s.bill_id = $b_id");
    if ($bill = mysqli_fetch_assoc($bill_q)) {
        echo '<div class="row mb-3">
                <div class="col-6">
                    <p class="mb-1"><strong>Invoice Number:</strong> #' . $bill['bill_id'] . '</p>
                    <p class="mb-1"><strong>Patient Name:</strong> ' . htmlspecialchars($bill['fname'] . ' ' . $bill['lname']) . '</p>
                    <p class="mb-1"><strong>Patient Phone:</strong> ' . htmlspecialchars($bill['contact'] ?? '-') . '</p>
                </div>
                <div class="col-6 text-right">
                    <p class="mb-1"><strong>Date:</strong> ' . htmlspecialchars($bill['sale_date']) . '</p>
                    <p class="mb-1"><strong>Doctor:</strong> ' . (!empty($bill['doctor_name']) ? 'Dr. ' . htmlspecialchars($bill['doctor_name']) : '-') . '</p>
                    <p class="mb-1"><strong>Pharmacist:</strong> ' . htmlspecialchars($bill['pharmacist_username']) . '</p>
                </div>
              </div>';

        echo '<table class="table table-bordered table-sm mb-3">
                <thead class="bg-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Generic Name</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';

        $items_q = mysqli_query($con, "SELECT i.*, m.medicine_name, m.generic_name FROM pharmacy_sale_items i LEFT JOIN medicinetb m ON i.medicine_id = m.medicine_id WHERE i.bill_id = $b_id");
        while ($it = mysqli_fetch_assoc($items_q)) {
            echo '<tr>
                    <td>' . htmlspecialchars($it['medicine_name']) . '</td>
                    <td>' . htmlspecialchars($it['generic_name']) . '</td>
                    <td class="text-center">' . $it['quantity'] . '</td>
                    <td class="text-right">$' . number_format($it['unit_price'], 2) . '</td>
                    <td class="text-right">$' . number_format($it['subtotal'], 2) . '</td>
                  </tr>';
        }

        echo '</tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Paid (' . htmlspecialchars($bill['payment_type']) . '):</th>
                        <th class="text-right text-success">$' . number_format($bill['total_amount'], 2) . '</th>
                    </tr>
                </tfoot>
              </table>';

        if (!empty($bill['notes'])) {
            echo '<p class="text-muted small"><strong>Notes:</strong> ' . htmlspecialchars($bill['notes']) . '</p>';
        }
    } else {
        echo '<p class="text-danger text-center">Invoice not found.</p>';
    }
    exit();
}
?>
