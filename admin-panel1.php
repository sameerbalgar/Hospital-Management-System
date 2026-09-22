<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$con=mysqli_connect("localhost","root","","myhmsdb");

include('newfunc.php');
include_once('func_pharmacy.php');

// Handle AJAX requests for Bill and Medicine details early
if (isset($_GET['ajax_admin_bill'])) {
    $b_id = intval($_GET['ajax_admin_bill']);
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
                        <th class="text-right text-success font-weight-bold">$' . number_format($bill['total_amount'], 2) . '</th>
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

if (isset($_GET['ajax_med_details'])) {
    $m_id = intval($_GET['ajax_med_details']);
    $mq = mysqli_query($con, "SELECT * FROM medicinetb WHERE medicine_id = $m_id");
    if ($med = mysqli_fetch_assoc($mq)) {
        $st = calculate_medicine_status($med['quantity'], $med['expiry_date']);
        $badge = ($st === 'Available') ? 'badge-success' : (($st === 'Low Stock') ? 'badge-warning' : (($st === 'Out of Stock') ? 'badge-danger' : 'badge-secondary'));
        echo '<div class="row mb-3">
                <div class="col-md-6">
                    <h5 class="text-primary font-weight-bold">' . htmlspecialchars($med['medicine_name']) . '</h5>
                    <p class="mb-1"><strong>Generic Name:</strong> ' . htmlspecialchars($med['generic_name']) . '</p>
                    <p class="mb-1"><strong>Category:</strong> ' . htmlspecialchars($med['category']) . '</p>
                    <p class="mb-1"><strong>Manufacturer:</strong> ' . htmlspecialchars($med['manufacturer']) . '</p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Batch Number:</strong> <code>' . htmlspecialchars($med['batch_no']) . '</code></p>
                    <p class="mb-1"><strong>Expiry Date:</strong> ' . htmlspecialchars($med['expiry_date']) . '</p>
                    <p class="mb-1"><strong>Current Stock:</strong> ' . $med['quantity'] . ' units</p>
                    <p class="mb-1"><strong>Unit Price:</strong> $' . number_format($med['unit_price'], 2) . '</p>
                    <p class="mb-1"><strong>Supplier:</strong> ' . htmlspecialchars($med['supplier']) . '</p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge ' . $badge . '">' . $st . '</span></p>
                </div>
              </div>';
        echo '<h6 class="font-weight-bold border-bottom pb-2 mt-3">Recent Dispensing & Sales History</h6>';
        $sq = mysqli_query($con, "SELECT si.quantity, si.unit_price, si.subtotal, s.bill_id, s.sale_date, s.pharmacist_username, p.fname, p.lname FROM pharmacy_sale_items si JOIN pharmacy_sales s ON si.bill_id = s.bill_id LEFT JOIN patreg p ON s.pid = p.pid WHERE si.medicine_id = $m_id ORDER BY s.bill_id DESC LIMIT 10");
        if ($sq && mysqli_num_rows($sq) > 0) {
            echo '<table class="table table-sm table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th>Pharmacist</th>
                        </tr>
                    </thead>
                    <tbody>';
            while ($s_row = mysqli_fetch_assoc($sq)) {
                echo '<tr>
                        <td>#' . $s_row['bill_id'] . '</td>
                        <td>' . htmlspecialchars($s_row['sale_date']) . '</td>
                        <td>' . htmlspecialchars(($s_row['fname'] ?? 'Unknown') . ' ' . ($s_row['lname'] ?? '')) . '</td>
                        <td class="text-center">' . $s_row['quantity'] . '</td>
                        <td>$' . number_format($s_row['subtotal'], 2) . '</td>
                        <td>' . htmlspecialchars($s_row['pharmacist_username']) . '</td>
                      </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="text-muted small">No past dispensing records for this medicine.</p>';
        }
    } else {
        echo '<p class="text-danger text-center">Medicine details not found.</p>';
    }
    exit();
}

// Existing Doctor Handlers
if(isset($_POST['docsub']))
{
  $doctor=$_POST['doctor'];
  $dpassword=$_POST['dpassword'];
  $demail=$_POST['demail'];
  $spec=$_POST['special'];
  $docFees=$_POST['docFees'];
  $query="insert into doctb(username,password,email,spec,docFees)values('$doctor','$dpassword','$demail','$spec','$docFees')";
  $result=mysqli_query($con,$query);
  if($result)
    {
      echo "<script>alert('Doctor added successfully!');</script>";
  }
}

if(isset($_POST['docsub1']))
{
  $demail=$_POST['demail'];
  $query="delete from doctb where email='$demail';";
  $result=mysqli_query($con,$query);
  if($result)
    {
      echo "<script>alert('Doctor removed successfully!');</script>";
  }
  else{
    echo "<script>alert('Unable to delete!');</script>";
  }
}

// -------------------------------------------------------------
// Admin Pharmacy Handlers
// -------------------------------------------------------------
// 1. Add Pharmacist
if (isset($_POST['add_pharmacist_submit'])) {
    $pname     = trim($_POST['pharma_name'] ?? '');
    $pusername = trim($_POST['pharma_username'] ?? '');
    $pemail    = trim($_POST['pharma_email'] ?? '');
    $pcontact  = trim($_POST['pharma_contact'] ?? '');
    $ppassword = trim($_POST['pharma_password'] ?? '');
    $pstatus   = trim($_POST['pharma_status'] ?? 'Active');

    if (empty($pname) || empty($pusername) || empty($pemail) || empty($ppassword)) {
        echo "<script>alert('Please fill in all required fields.'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
    } else {
        $chk = mysqli_prepare($con, "SELECT pharmacist_id FROM pharmacisttb WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($chk, "ss", $pusername, $pemail);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            echo "<script>alert('Username or Email already exists for another pharmacist!'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
        } else {
            $hashed = password_hash($ppassword, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($con, "INSERT INTO pharmacisttb (name, username, email, contact, password, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "ssssss", $pname, $pusername, $pemail, $pcontact, $hashed, $pstatus);
            if (mysqli_stmt_execute($ins)) {
                echo "<script>alert('Pharmacist added successfully!'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
            } else {
                echo "<script>alert('Failed to add pharmacist: " . addslashes(mysqli_error($con)) . "'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
            }
            mysqli_stmt_close($ins);
        }
        mysqli_stmt_close($chk);
    }
}

// 2. Edit Pharmacist
if (isset($_POST['edit_pharmacist_submit'])) {
    $pid      = intval($_POST['edit_pharma_id'] ?? 0);
    $pname    = trim($_POST['edit_pharma_name'] ?? '');
    $pemail   = trim($_POST['edit_pharma_email'] ?? '');
    $pcontact = trim($_POST['edit_pharma_contact'] ?? '');
    $pstatus  = trim($_POST['edit_pharma_status'] ?? 'Active');
    $newpass  = trim($_POST['edit_pharma_password'] ?? '');

    if ($pid > 0 && !empty($pname) && !empty($pemail)) {
        if (!empty($newpass)) {
            $hashed = password_hash($newpass, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($con, "UPDATE pharmacisttb SET name = ?, email = ?, contact = ?, status = ?, password = ? WHERE pharmacist_id = ?");
            mysqli_stmt_bind_param($upd, "sssssi", $pname, $pemail, $pcontact, $pstatus, $hashed, $pid);
        } else {
            $upd = mysqli_prepare($con, "UPDATE pharmacisttb SET name = ?, email = ?, contact = ?, status = ? WHERE pharmacist_id = ?");
            mysqli_stmt_bind_param($upd, "ssssi", $pname, $pemail, $pcontact, $pstatus, $pid);
        }
        if (mysqli_stmt_execute($upd)) {
            echo "<script>alert('Pharmacist details updated successfully!'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
        } else {
            echo "<script>alert('Failed to update pharmacist.'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
        }
        mysqli_stmt_close($upd);
    }
}

// 3. Toggle Pharmacist Status (Activate / Deactivate)
if (isset($_GET['toggle_pharmacist'])) {
    $pid = intval($_GET['toggle_pharmacist']);
    $res = mysqli_query($con, "SELECT status FROM pharmacisttb WHERE pharmacist_id = $pid");
    if ($r = mysqli_fetch_assoc($res)) {
        $new_st = ($r['status'] === 'Active') ? 'Inactive' : 'Active';
        mysqli_query($con, "UPDATE pharmacisttb SET status = '$new_st' WHERE pharmacist_id = $pid");
        echo "<script>alert('Pharmacist account marked as " . $new_st . "'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
    }
}

// 4. Safe Delete Pharmacist
if (isset($_GET['delete_pharmacist'])) {
    $pid = intval($_GET['delete_pharmacist']);
    $u_res = mysqli_query($con, "SELECT username FROM pharmacisttb WHERE pharmacist_id = $pid");
    if ($u_row = mysqli_fetch_assoc($u_res)) {
        $pharma_user = mysqli_real_escape_string($con, $u_row['username']);
        $chk_sales = mysqli_query($con, "SELECT bill_id FROM pharmacy_sales WHERE pharmacist_username = '$pharma_user' LIMIT 1");
        if ($chk_sales && mysqli_num_rows($chk_sales) > 0) {
            echo "<script>alert('Cannot delete this pharmacist account because they have recorded sales/dispensing history. You can Deactivate the account instead.'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
        } else {
            mysqli_query($con, "DELETE FROM pharmacisttb WHERE pharmacist_id = $pid");
            echo "<script>alert('Pharmacist account removed successfully.'); window.location.href = 'admin-panel1.php#list-pharmacists';</script>";
        }
    }
}

// 5. Safe Delete Medicine
if (isset($_GET['admin_delete_med'])) {
    $mid = intval($_GET['admin_delete_med']);
    $chk_items = mysqli_query($con, "SELECT item_id FROM pharmacy_sale_items WHERE medicine_id = $mid LIMIT 1");
    $chk_movs  = mysqli_query($con, "SELECT movement_id FROM medicine_stock_movements WHERE medicine_id = $mid LIMIT 1");

    if ($chk_items && mysqli_num_rows($chk_items) > 0) {
        echo "<script>alert('Cannot delete this medicine because it is referenced in past pharmacy sales records. Stock can be adjusted to 0 instead.'); window.location.href = 'admin-panel1.php#list-pharma-stock';</script>";
    } elseif ($chk_movs && mysqli_num_rows($chk_movs) > 0) {
        echo "<script>alert('Cannot delete this medicine because it has historical stock purchase/movement records.'); window.location.href = 'admin-panel1.php#list-pharma-stock';</script>";
    } else {
        mysqli_query($con, "DELETE FROM medicinetb WHERE medicine_id = $mid");
        echo "<script>alert('Medicine removed successfully.'); window.location.href = 'admin-panel1.php#list-pharma-stock';</script>";
    }
}

// Metrics for Pharmacy Dashboard
$adm_total_meds   = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb"))['total'] ?? 0;
$adm_total_units  = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(quantity), 0) AS total FROM medicinetb"))['total'] ?? 0;
$adm_avail_meds   = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE quantity > 10 AND expiry_date >= CURRENT_DATE()"))['total'] ?? 0;
$adm_low_stock    = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE quantity <= 10 AND quantity > 0 AND expiry_date >= CURRENT_DATE()"))['total'] ?? 0;
$adm_out_stock    = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE quantity <= 0"))['total'] ?? 0;
$adm_expired      = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM medicinetb WHERE expiry_date < CURRENT_DATE()"))['total'] ?? 0;
$adm_today_sales  = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(total_amount), 0) AS total FROM pharmacy_sales WHERE DATE(sale_date) = CURRENT_DATE()"))['total'] ?? 0;
$adm_today_restock = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(quantity), 0) AS total FROM medicine_stock_movements WHERE DATE(movement_date) = CURRENT_DATE() AND movement_type IN ('PURCHASE', 'RESTOCK')"))['total'] ?? 0;
$adm_today_dispense = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(si.quantity), 0) AS total FROM pharmacy_sale_items si JOIN pharmacy_sales s ON si.bill_id = s.bill_id WHERE DATE(s.sale_date) = CURRENT_DATE()"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
  <head>


    <!-- Required meta tags -->
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" type="text/css" href="font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
  <a class="navbar-brand" href="#"><i class="fa fa-user-plus" aria-hidden="true"></i> Smart Hospital </a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <script >
    var check = function() {
  if (document.getElementById('dpassword').value ==
    document.getElementById('cdpassword').value) {
    document.getElementById('message').style.color = '#5dd05d';
    document.getElementById('message').innerHTML = 'Matched';
  } else {
    document.getElementById('message').style.color = '#f55252';
    document.getElementById('message').innerHTML = 'Not Matching';
  }
}

    function alphaOnly(event) {
  var key = event.keyCode;
  return ((key >= 65 && key <= 90) || key == 8 || key == 32);
};
  </script>

  <style >
    .bg-primary {
    background: -webkit-linear-gradient(left, #3931af, #00c6ff);
}

.col-md-4{
  max-width:20% !important;
}

.list-group-item.active {
    z-index: 2;
    color: #fff;
    background-color: #342ac1;
    border-color: #007bff;
}
.text-primary {
    color: #342ac1!important;
}

#cpass {
  display: -webkit-box;
}

#list-app{
  font-size:15px;
}

.btn-primary{
  background-color: #3c50c1;
  border-color: #3c50c1;
}
  </style>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
     <ul class="navbar-nav mr-auto">
       <li class="nav-item">
        <a class="nav-link" href="logout1.php"><i class="fa fa-sign-out" aria-hidden="true"></i>Logout</a>
      </li>
       <li class="nav-item">
        <a class="nav-link" href="#"></a>
      </li>
    </ul>
  </div>
</nav>
  </head>
  <style type="text/css">
    button:hover{cursor:pointer;}
    #inputbtn:hover{cursor:pointer;}
  </style>
  <body style="padding-top:50px;">
   <div class="container-fluid" style="margin-top:50px;">
    <h3 style = "margin-left: 40%; padding-bottom: 20px;font-family: 'IBM Plex Sans', sans-serif;"> WELCOME RECEPTIONIST </h3>
    <div class="row">
  <div class="col-md-4" style="max-width:25%;margin-top: 3%;">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active" id="list-dash-list" data-toggle="list" href="#list-dash" role="tab" aria-controls="home">Dashboard</a>
      <a class="list-group-item list-group-item-action" href="#list-doc" id="list-doc-list"  role="tab"    aria-controls="home" data-toggle="list">Doctor List</a>
      <a class="list-group-item list-group-item-action" href="#list-pat" id="list-pat-list"  role="tab" data-toggle="list" aria-controls="home">Patient List</a>
      <a class="list-group-item list-group-item-action" href="#list-app" id="list-app-list"  role="tab" data-toggle="list" aria-controls="home">Appointment Details</a>
      <a class="list-group-item list-group-item-action" href="#list-pres" id="list-pres-list"  role="tab" data-toggle="list" aria-controls="home">Prescription List</a>
      <a class="list-group-item list-group-item-action" href="#list-settings" id="list-adoc-list"  role="tab" data-toggle="list" aria-controls="home">Add Doctor</a>
      <a class="list-group-item list-group-item-action" href="#list-settings1" id="list-ddoc-list"  role="tab" data-toggle="list" aria-controls="home">Delete Doctor</a>
      <a class="list-group-item list-group-item-action" href="#list-mes" id="list-mes-list"  role="tab" data-toggle="list" aria-controls="home">Queries</a>
      <a class="list-group-item list-group-item-action" href="#list-pharma-stock" id="list-pharma-stock-list" role="tab" data-toggle="list" aria-controls="home">Pharmacy Stock</a>
      <a class="list-group-item list-group-item-action" href="#list-restock" id="list-restock-list" role="tab" data-toggle="list" aria-controls="home">Restock Medicine</a>
      <a class="list-group-item list-group-item-action" href="#list-movements" id="list-movements-list" role="tab" data-toggle="list" aria-controls="home">Stock Movements</a>
      <a class="list-group-item list-group-item-action" href="#list-suppliers" id="list-suppliers-list" role="tab" data-toggle="list" aria-controls="home">Manage Suppliers</a>
      <a class="list-group-item list-group-item-action" href="#list-pharmacists" id="list-pharmacists-list" role="tab" data-toggle="list" aria-controls="home">Manage Pharmacists</a>
      <a class="list-group-item list-group-item-action" href="#list-pharma-sales" id="list-pharma-sales-list" role="tab" data-toggle="list" aria-controls="home">Pharmacy Sales</a>
    </div><br>
  </div>
  <div class="col-md-8" style="margin-top: 3%;">
    <div class="tab-content" id="nav-tabContent" style="width: 950px;">



      <div class="tab-pane fade show active" id="list-dash" role="tabpanel" aria-labelledby="list-dash-list">
        <div class="container-fluid container-fullw bg-white" >
              <div class="row">
               <div class="col-sm-4">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body">
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-users fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Doctor List</h4>
                      <script>
                        function clickDiv(id) {
                          document.querySelector(id).click();
                        }
                      </script> 
                      <p class="links cl-effect-1">
                        <a href="#list-doc" onclick="clickDiv('#list-doc-list')">
                          View Doctors
                        </a>
                      </p>
                    </div>
                  </div>
                </div>

                <div class="col-sm-4" style="left: -3%">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body" >
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-users fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Patient List</h4>
                      
                      <p class="cl-effect-1">
                        <a href="#app-hist" onclick="clickDiv('#list-pat-list')">
                          View Patients
                        </a>
                      </p>
                    </div>
                  </div>
                </div>
              

                <div class="col-sm-4">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body" >
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-paperclip fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Appointment Details</h4>
                    
                      <p class="cl-effect-1">
                        <a href="#app-hist" onclick="clickDiv('#list-app-list')">
                          View Appointments
                        </a>
                      </p>
                    </div>
                  </div>
                </div>
                </div>

                <div class="row">
                <div class="col-sm-4" style="left: 13%;margin-top: 5%;">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body" >
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-list-ul fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Prescription List</h4>
                    
                      <p class="cl-effect-1">
                        <a href="#list-pres" onclick="clickDiv('#list-pres-list')">
                          View Prescriptions
                        </a>
                      </p>
                    </div>
                  </div>
                </div>


                <div class="col-sm-4" style="left: 18%;margin-top: 5%">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body" >
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-plus fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Manage Doctors</h4>
                    
                      <p class="cl-effect-1">
                        <a href="#app-hist" onclick="clickDiv('#list-adoc-list')">Add Doctors</a>
                        &nbsp|
                        <a href="#app-hist" onclick="clickDiv('#list-ddoc-list')">
                          Delete Doctors
                        </a>
                      </p>
                    </div>
                  </div>
                </div>

                <div class="col-sm-4" style="left: 23%;margin-top: 5%">
                  <div class="panel panel-white no-radius text-center">
                    <div class="panel-body" >
                      <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-medkit fa-stack-1x fa-inverse"></i> </span>
                      <h4 class="StepTitle" style="margin-top: 5%;">Pharmacy Module</h4>
                      <p class="cl-effect-1">
                        <a href="#list-pharma-stock" onclick="clickDiv('#list-pharma-stock-list')">Stock & Alerts</a>
                        &nbsp;|&nbsp;
                        <a href="#list-pharmacists" onclick="clickDiv('#list-pharmacists-list')">Pharmacists</a>
                      </p>
                    </div>
                  </div>
                </div>
                </div>
                        

      
                
              </div>
            </div>
      
                
      






      <div class="tab-pane fade" id="list-doc" role="tabpanel" aria-labelledby="list-home-list">
              

              <div class="col-md-8">
      <form class="form-group" action="doctorsearch.php" method="post">
        <div class="row">
        <div class="col-md-10"><input type="text" name="doctor_contact" placeholder="Enter Email ID" class = "form-control"></div>
        <div class="col-md-2"><input type="submit" name="doctor_search_submit" class="btn btn-primary" value="Search"></div></div>
      </form>
    </div>
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th scope="col">Doctor Name</th>
                    <th scope="col">Specialization</th>
                    <th scope="col">Email</th>
                    <th scope="col">Password</th>
                    <th scope="col">Fees</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    $con=mysqli_connect("localhost","root","","myhmsdb");
                    global $con;
                    $query = "select * from doctb";
                    $result = mysqli_query($con,$query);
                    while ($row = mysqli_fetch_array($result)){
                      $username = $row['username'];
                      $spec = $row['spec'];
                      $email = $row['email'];
                      $password = $row['password'];
                      $docFees = $row['docFees'];
                      
                      echo "<tr>
                        <td>$username</td>
                        <td>$spec</td>
                        <td>$email</td>
                        <td>$password</td>
                        <td>$docFees</td>
                      </tr>";
                    }

                  ?>
                </tbody>
              </table>
        <br>
      </div>
    

    <div class="tab-pane fade" id="list-pat" role="tabpanel" aria-labelledby="list-pat-list">

       <div class="col-md-8">
      <form class="form-group" action="patientsearch.php" method="post">
        <div class="row">
        <div class="col-md-10"><input type="text" name="patient_contact" placeholder="Enter Contact" class = "form-control"></div>
        <div class="col-md-2"><input type="submit" name="patient_search_submit" class="btn btn-primary" value="Search"></div></div>
      </form>
    </div>
        
              <table class="table table-hover">
                <thead>
                  <tr>
                  <th scope="col">Patient ID</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">Gender</th>
                    <th scope="col">Email</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Password</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    $con=mysqli_connect("localhost","root","","myhmsdb");
                    global $con;
                    $query = "select * from patreg";
                    $result = mysqli_query($con,$query);
                    while ($row = mysqli_fetch_array($result)){
                      $pid = $row['pid'];
                      $fname = $row['fname'];
                      $lname = $row['lname'];
                      $gender = $row['gender'];
                      $email = $row['email'];
                      $contact = $row['contact'];
                      $password = $row['password'];
                      
                      echo "<tr>
                        <td>$pid</td>
                        <td>$fname</td>
                        <td>$lname</td>
                        <td>$gender</td>
                        <td>$email</td>
                        <td>$contact</td>
                        <td>$password</td>
                      </tr>";
                    }

                  ?>
                </tbody>
              </table>
        <br>
      </div>


      <div class="tab-pane fade" id="list-pres" role="tabpanel" aria-labelledby="list-pres-list">

       <div class="col-md-8">
  
        <div class="row">
        
    
        
              <table class="table table-hover">
                <thead>
                  <tr>
                  <th scope="col">Doctor</th>
                    <th scope="col">Patient ID</th>
                    <th scope="col">Appointment ID</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">Appointment Date</th>
                    <th scope="col">Appointment Time</th>
                    <th scope="col">Disease</th>
                    <th scope="col">Allergy</th>
                    <th scope="col">Prescription</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    $con=mysqli_connect("localhost","root","","myhmsdb");
                    global $con;
                    $query = "select * from prestb";
                    $result = mysqli_query($con,$query);
                    while ($row = mysqli_fetch_array($result)){
                      $doctor = $row['doctor'];
                      $pid = $row['pid'];
                      $ID = $row['ID'];
                      $fname = $row['fname'];
                      $lname = $row['lname'];
                      $appdate = $row['appdate'];
                      $apptime = $row['apptime'];
                      $disease = $row['disease'];
                      $allergy = $row['allergy'];
                      $pres = $row['prescription'];

                      
                      echo "<tr>
                        <td>$doctor</td>
                        <td>$pid</td>
                        <td>$ID</td>
                        <td>$fname</td>
                        <td>$lname</td>
                        <td>$appdate</td>
                        <td>$apptime</td>
                        <td>$disease</td>
                        <td>$allergy</td>
                        <td>$pres</td>
                      </tr>";
                    }

                  ?>
                </tbody>
              </table>
        <br>
      </div>
      </div>
      </div>




      <div class="tab-pane fade" id="list-app" role="tabpanel" aria-labelledby="list-pat-list">

         <div class="col-md-8">
      <form class="form-group" action="appsearch.php" method="post">
        <div class="row">
        <div class="col-md-10"><input type="text" name="app_contact" placeholder="Enter Contact" class = "form-control"></div>
        <div class="col-md-2"><input type="submit" name="app_search_submit" class="btn btn-primary" value="Search"></div></div>
      </form>
    </div>
        
              <table class="table table-hover">
                <thead>
                  <tr>
                  <th scope="col">Appointment ID</th>
                  <th scope="col">Patient ID</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">Gender</th>
                    <th scope="col">Email</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Doctor Name</th>
                    <th scope="col">Consultancy Fees</th>
                    <th scope="col">Appointment Date</th>
                    <th scope="col">Appointment Time</th>
                    <th scope="col">Appointment Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 

                    $con=mysqli_connect("localhost","root","","myhmsdb");
                    global $con;

                    $query = "select * from appointmenttb;";
                    $result = mysqli_query($con,$query);
                    while ($row = mysqli_fetch_array($result)){
                  ?>
                      <tr>
                        <td><?php echo $row['ID'];?></td>
                        <td><?php echo $row['pid'];?></td>
                        <td><?php echo $row['fname'];?></td>
                        <td><?php echo $row['lname'];?></td>
                        <td><?php echo $row['gender'];?></td>
                        <td><?php echo $row['email'];?></td>
                        <td><?php echo $row['contact'];?></td>
                        <td><?php echo $row['doctor'];?></td>
                        <td><?php echo $row['docFees'];?></td>
                        <td><?php echo $row['appdate'];?></td>
                        <td><?php echo $row['apptime'];?></td>
                        <td>
                    <?php if(($row['userStatus']==1) && ($row['doctorStatus']==1))  
                    {
                      echo "Active";
                    }
                    if(($row['userStatus']==0) && ($row['doctorStatus']==1))  
                    {
                      echo "Cancelled by Patient";
                    }

                    if(($row['userStatus']==1) && ($row['doctorStatus']==0))  
                    {
                      echo "Cancelled by Doctor";
                    }
                        ?></td>
                      </tr>
                    <?php } ?>
                </tbody>
              </table>
        <br>
      </div>

<div class="tab-pane fade" id="list-messages" role="tabpanel" aria-labelledby="list-messages-list">...</div>

      <div class="tab-pane fade" id="list-settings" role="tabpanel" aria-labelledby="list-settings-list">
        <form class="form-group" method="post" action="admin-panel1.php">
          <div class="row">
                  <div class="col-md-4"><label>Doctor Name:</label></div>
                  <div class="col-md-8"><input type="text" class="form-control" name="doctor" onkeydown="return alphaOnly(event);" required></div><br><br>
                  <div class="col-md-4"><label>Specialization:</label></div>
                  <div class="col-md-8">
                   <select name="special" class="form-control" id="special" required="required">
                      <option value="head" name="spec" disabled selected>Select Specialization</option>
                      <option value="General" name="spec">General</option>
                      <option value="Cardiologist" name="spec">Cardiologist</option>
                      <option value="Neurologist" name="spec">Neurologist</option>
                      <option value="Pediatrician" name="spec">Pediatrician</option>
                    </select>
                    </div><br><br>
                  <div class="col-md-4"><label>Email ID:</label></div>
                  <div class="col-md-8"><input type="email"  class="form-control" name="demail" required></div><br><br>
                  <div class="col-md-4"><label>Password:</label></div>
                  <div class="col-md-8"><input type="password" class="form-control"  onkeyup='check();' name="dpassword" id="dpassword"  required></div><br><br>
                  <div class="col-md-4"><label>Confirm Password:</label></div>
                  <div class="col-md-8"  id='cpass'><input type="password" class="form-control" onkeyup='check();' name="cdpassword" id="cdpassword" required>&nbsp &nbsp<span id='message'></span> </div><br><br>
                   
                  
                  <div class="col-md-4"><label>Consultancy Fees:</label></div>
                  <div class="col-md-8"><input type="text" class="form-control"  name="docFees" required></div><br><br>
                </div>
          <input type="submit" name="docsub" value="Add Doctor" class="btn btn-primary">
        </form>
      </div>

      <div class="tab-pane fade" id="list-settings1" role="tabpanel" aria-labelledby="list-settings1-list">
        <form class="form-group" method="post" action="admin-panel1.php">
          <div class="row">
          
                  <div class="col-md-4"><label>Email ID:</label></div>
                  <div class="col-md-8"><input type="email"  class="form-control" name="demail" required></div><br><br>
                  
                </div>
          <input type="submit" name="docsub1" value="Delete Doctor" class="btn btn-primary" onclick="confirm('do you really want to delete?')">
        </form>
      </div>


       <div class="tab-pane fade" id="list-attend" role="tabpanel" aria-labelledby="list-attend-list">...</div>

       <div class="tab-pane fade" id="list-mes" role="tabpanel" aria-labelledby="list-mes-list">

         <div class="col-md-8">
      <form class="form-group" action="messearch.php" method="post">
        <div class="row">
        <div class="col-md-10"><input type="text" name="mes_contact" placeholder="Enter Contact" class = "form-control"></div>
        <div class="col-md-2"><input type="submit" name="mes_search_submit" class="btn btn-primary" value="Search"></div></div>
      </form>
    </div>
        
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th scope="col">User Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Message</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 

                    $con=mysqli_connect("localhost","root","","myhmsdb");
                    global $con;

                    $query = "select * from contact;";
                    $result = mysqli_query($con,$query);
                    while ($row = mysqli_fetch_array($result)){
              
                      #$fname = $row['fname'];
                      #$lname = $row['lname'];
                      #$email = $row['email'];
                      #$contact = $row['contact'];
                  ?>
                      <tr>
                        <td><?php echo $row['name'];?></td>
                        <td><?php echo $row['email'];?></td>
                        <td><?php echo $row['contact'];?></td>
                        <td><?php echo $row['message'];?></td>
                      </tr>
                    <?php } ?>
                </tbody>
              </table>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Pharmacy Stock & Inventory Management               -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-pharma-stock" role="tabpanel" aria-labelledby="list-pharma-stock-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-medkit text-primary"></i> Pharmacy Stock & Inventory</h4>
        
        <!-- Summary Dashboard Metrics Cards -->
        <div class="row mb-3">
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-primary shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Total Medicines</small>
              <h4 class="text-primary font-weight-bold mb-0"><?php echo $adm_total_meds; ?> <small class="text-muted" style="font-size: 13px;">(<?php echo $adm_total_units; ?> units)</small></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-success shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Today Restocked</small>
              <h4 class="text-success font-weight-bold mb-0"><?php echo $adm_today_restock; ?> <small class="text-muted" style="font-size: 13px;">units</small></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-info shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Today Dispensed</small>
              <h4 class="text-info font-weight-bold mb-0"><?php echo $adm_today_dispense; ?> <small class="text-muted" style="font-size: 13px;">units</small></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-secondary shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Today Sales ($)</small>
              <h4 class="text-secondary font-weight-bold mb-0">$<?php echo number_format($adm_today_sales, 2); ?></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-success shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Available Stock</small>
              <h4 class="text-success font-weight-bold mb-0"><?php echo $adm_avail_meds; ?></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-warning shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Low Stock (≤ 10)</small>
              <h4 class="text-warning font-weight-bold mb-0"><?php echo $adm_low_stock; ?></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-danger shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Out of Stock</small>
              <h4 class="text-danger font-weight-bold mb-0"><?php echo $adm_out_stock; ?></h4>
            </div>
          </div>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center p-2 border-dark shadow-sm">
              <small class="text-muted font-weight-bold text-uppercase">Expired Meds</small>
              <h4 class="text-dark font-weight-bold mb-0"><?php echo $adm_expired; ?></h4>
            </div>
          </div>
        </div>

        <?php if ($adm_low_stock > 0 || $adm_out_stock > 0 || $adm_expired > 0): ?>
          <div class="alert alert-warning py-2 mb-3 small d-flex justify-content-between align-items-center">
            <span>
              <i class="fa fa-exclamation-triangle text-danger font-weight-bold"></i>
              <strong>Inventory Warnings:</strong> 
              <?php if ($adm_low_stock > 0): ?><span class="badge badge-warning"><?php echo $adm_low_stock; ?> Low Stock</span> <?php endif; ?>
              <?php if ($adm_out_stock > 0): ?><span class="badge badge-danger"><?php echo $adm_out_stock; ?> Out of Stock</span> <?php endif; ?>
              <?php if ($adm_expired > 0): ?><span class="badge badge-secondary"><?php echo $adm_expired; ?> Expired</span> <?php endif; ?>
            </span>
            <small class="text-muted">Filtered lists available below.</small>
          </div>
        <?php endif; ?>

        <!-- Search & Filter Form -->
        <?php 
          $adm_m_search = trim($_GET['adm_m_search'] ?? '');
          $adm_m_cat    = trim($_GET['adm_m_cat'] ?? '');
          $adm_m_status = trim($_GET['adm_m_status'] ?? '');

          $med_q_str = "SELECT * FROM medicinetb WHERE 1=1";
          if (!empty($adm_m_search)) {
              $safe_ms = mysqli_real_escape_string($con, $adm_m_search);
              $med_q_str .= " AND (medicine_name LIKE '%$safe_ms%' OR generic_name LIKE '%$safe_ms%' OR batch_no LIKE '%$safe_ms%')";
          }
          if (!empty($adm_m_cat)) {
              $safe_mc = mysqli_real_escape_string($con, $adm_m_cat);
              $med_q_str .= " AND category = '$safe_mc'";
          }
          if ($adm_m_status === 'Available') {
              $med_q_str .= " AND quantity > 10 AND expiry_date >= CURRENT_DATE()";
          } elseif ($adm_m_status === 'Low Stock') {
              $med_q_str .= " AND quantity <= 10 AND quantity > 0 AND expiry_date >= CURRENT_DATE()";
          } elseif ($adm_m_status === 'Out of Stock') {
              $med_q_str .= " AND quantity <= 0";
          } elseif ($adm_m_status === 'Expired') {
              $med_q_str .= " AND expiry_date < CURRENT_DATE()";
          }
          $med_q_str .= " ORDER BY medicine_name ASC";
          $adm_meds_res = mysqli_query($con, $med_q_str);

          $all_cats = mysqli_query($con, "SELECT DISTINCT category FROM medicinetb WHERE category IS NOT NULL AND category != ''");
        ?>
        <form method="get" action="admin-panel1.php" class="form-inline mb-3">
          <input type="text" name="adm_m_search" class="form-control form-control-sm mr-2 mb-2" placeholder="Search name/generic/batch..." value="<?php echo htmlspecialchars($adm_m_search); ?>" style="min-width: 220px;">
          <select name="adm_m_cat" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Categories</option>
            <?php while ($cat = mysqli_fetch_assoc($all_cats)): ?>
              <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php if ($adm_m_cat === $cat['category']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($cat['category']); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <select name="adm_m_status" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Statuses</option>
            <option value="Available" <?php if ($adm_m_status === 'Available') echo 'selected'; ?>>Available</option>
            <option value="Low Stock" <?php if ($adm_m_status === 'Low Stock') echo 'selected'; ?>>Low Stock</option>
            <option value="Out of Stock" <?php if ($adm_m_status === 'Out of Stock') echo 'selected'; ?>>Out of Stock</option>
            <option value="Expired" <?php if ($adm_m_status === 'Expired') echo 'selected'; ?>>Expired</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary mb-2 mr-1"><i class="fa fa-filter"></i> Filter</button>
          <a href="admin-panel1.php#list-pharma-stock" class="btn btn-sm btn-outline-secondary mb-2">Reset</a>
        </form>

        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
          <table class="table table-hover table-bordered table-sm">
            <thead class="bg-light">
              <tr>
                <th>ID</th>
                <th>Medicine Name</th>
                <th>Generic Name</th>
                <th>Category</th>
                <th>Batch No</th>
                <th>Expiry</th>
                <th>Stock</th>
                <th>Price</th>
                <th>Supplier</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($adm_meds_res && mysqli_num_rows($adm_meds_res) > 0): ?>
                <?php while ($m = mysqli_fetch_assoc($adm_meds_res)): 
                  $mst = calculate_medicine_status($m['quantity'], $m['expiry_date']);
                  $mbadge = ($mst === 'Available') ? 'badge-success' : (($mst === 'Low Stock') ? 'badge-warning' : (($mst === 'Out of Stock') ? 'badge-danger' : 'badge-secondary'));
                ?>
                  <tr>
                    <td><?php echo $m['medicine_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($m['medicine_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($m['generic_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['category']); ?></td>
                    <td><code><?php echo htmlspecialchars($m['batch_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($m['expiry_date']); ?></td>
                    <td><strong><?php echo $m['quantity']; ?></strong></td>
                    <td>$<?php echo number_format($m['unit_price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($m['supplier']); ?></td>
                    <td><span class="badge <?php echo $mbadge; ?>"><?php echo $mst; ?></span></td>
                    <td>
                      <button type="button" class="btn btn-info btn-sm py-0 px-1" onclick="viewMedDetails(<?php echo $m['medicine_id']; ?>)" title="View Details">
                        <i class="fa fa-info-circle"></i> Details
                      </button>
                      <a href="admin-panel1.php?admin_delete_med=<?php echo $m['medicine_id']; ?>" class="btn btn-danger btn-sm py-0 px-1" onclick="return confirm('Are you sure you want to delete this medicine? Safe deletion will verify no past sales exist.');" title="Delete Medicine">
                        <i class="fa fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="11" class="text-center text-muted py-3">No medicines found matching filter criteria.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Medicine Stock Restocking / Purchase                -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-restock" role="tabpanel" aria-labelledby="list-restock-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-cubes text-primary"></i> Restock Medicine Inventory</h4>
        
        <div class="alert alert-info py-2 mb-3 small">
          <i class="fa fa-info-circle font-weight-bold"></i>
          <strong>Batch & Audit Preserved:</strong> When adding stock, existing inventory quantity is increased immediately. Every transaction permanently logs the incoming batch number, expiry date, purchase cost, and supplier in the <strong>Stock Movement History</strong>.
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-white font-weight-bold">
            <i class="fa fa-cart-plus text-primary"></i> Purchase & Stock Addition Form
          </div>
          <div class="card-body">
            <form method="post" action="func_pharmacy.php">
              <?php 
                $all_restock_meds = mysqli_query($con, "SELECT medicine_id, medicine_name, generic_name, quantity, unit_price, batch_no, expiry_date, supplier FROM medicinetb ORDER BY medicine_name ASC");
                $all_restock_sups = mysqli_query($con, "SELECT supplier_id, supplier_name FROM suppliertb WHERE status = 'Active' ORDER BY supplier_name ASC");
              ?>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Select Medicine to Restock <span class="text-danger">*</span></label>
                  <select name="restock_medicine_id" id="restock_med_select" class="form-control" required onchange="onRestockMedChange(this)">
                    <option value="" disabled selected>-- Choose Medicine --</option>
                    <?php while ($rm = mysqli_fetch_assoc($all_restock_meds)): ?>
                      <option value="<?php echo $rm['medicine_id']; ?>" 
                              data-stock="<?php echo $rm['quantity']; ?>"
                              data-batch="<?php echo htmlspecialchars($rm['batch_no']); ?>"
                              data-price="<?php echo $rm['unit_price']; ?>"
                              data-expiry="<?php echo $rm['expiry_date']; ?>"
                              data-sup="<?php echo htmlspecialchars($rm['supplier']); ?>">
                        <?php echo htmlspecialchars($rm['medicine_name']); ?> (Current Stock: <?php echo $rm['quantity']; ?>)
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div class="col-md-3 form-group">
                  <label>Movement Type <span class="text-danger">*</span></label>
                  <select name="movement_type" class="form-control" required>
                    <option value="RESTOCK" selected>RESTOCK (Batch Replenish)</option>
                    <option value="PURCHASE">PURCHASE (New Shipment)</option>
                    <option value="ADJUSTMENT">ADJUSTMENT (Audit Correction)</option>
                  </select>
                </div>

                <div class="col-md-3 form-group">
                  <label>Supplier / Distributor</label>
                  <select name="restock_supplier_id" class="form-control">
                    <option value="">-- Select Supplier (Optional) --</option>
                    <?php while ($rs = mysqli_fetch_assoc($all_restock_sups)): ?>
                      <option value="<?php echo $rs['supplier_id']; ?>">
                        <?php echo htmlspecialchars($rs['supplier_name']); ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>

              <!-- Current Medicine Details Box -->
              <div class="card bg-light p-2 mb-3 border" id="currentMedInfoBox" style="display: none;">
                <div class="row small">
                  <div class="col-md-3"><strong>Current Stock:</strong> <span id="dispCurrentStock" class="badge badge-primary">0</span></div>
                  <div class="col-md-3"><strong>Current Batch:</strong> <code id="dispCurrentBatch">-</code></div>
                  <div class="col-md-3"><strong>Current Expiry:</strong> <span id="dispCurrentExpiry">-</span></div>
                  <div class="col-md-3"><strong>Selling Price:</strong> $<span id="dispCurrentPrice">0.00</span></div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3 form-group">
                  <label>Quantity to Add <span class="text-danger">*</span></label>
                  <input type="number" name="restock_quantity" id="restock_qty_input" min="1" class="form-control" placeholder="e.g. 50" required oninput="calcRestockNewTotal()">
                </div>
                <div class="col-md-3 form-group">
                  <label>Unit Purchase Cost ($) <span class="text-danger">*</span></label>
                  <input type="number" step="0.01" min="0" name="restock_unit_price" id="restock_price_input" class="form-control" placeholder="e.g. 5.50" required>
                </div>
                <div class="col-md-3 form-group">
                  <label>Batch Number <span class="text-danger">*</span></label>
                  <input type="text" name="restock_batch_no" id="restock_batch_input" class="form-control" placeholder="e.g. BTC-2025-01" required>
                </div>
                <div class="col-md-3 form-group">
                  <label>Batch Expiry Date <span class="text-danger">*</span></label>
                  <input type="date" name="restock_expiry_date" id="restock_expiry_input" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-12 form-group">
                  <label>Reference Note / Invoice Number</label>
                  <input type="text" name="restock_reference_note" class="form-control" placeholder="e.g. Invoice #INV-9081 from MedLife">
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-2">
                <div>
                  <h5 class="mb-0 text-secondary" id="previewStockCalc" style="display:none;">
                    Projected New Stock: <span class="badge badge-success" id="dispNewStockTotal">0</span> units
                  </h5>
                </div>
                <button type="submit" name="admin_restock_submit" class="btn btn-success px-4 py-2 font-weight-bold">
                  <i class="fa fa-plus-circle"></i> Confirm Restock & Update Stock
                </button>
              </div>
            </form>
          </div>
        </div>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Stock Movement History                              -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-movements" role="tabpanel" aria-labelledby="list-movements-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-history text-primary"></i> Stock Movement & Purchase History</h4>

        <?php 
          $mov_med_f  = intval($_GET['mov_med_f'] ?? 0);
          $mov_type_f = trim($_GET['mov_type_f'] ?? '');
          $mov_sup_f  = intval($_GET['mov_sup_f'] ?? 0);
          $mov_date_f = trim($_GET['mov_date_f'] ?? '');

          $mov_sql = "SELECT sm.*, m.medicine_name, s.supplier_name FROM medicine_stock_movements sm JOIN medicinetb m ON sm.medicine_id = m.medicine_id LEFT JOIN suppliertb s ON sm.supplier_id = s.supplier_id WHERE 1=1";
          if ($mov_med_f > 0) {
              $mov_sql .= " AND sm.medicine_id = $mov_med_f";
          }
          if (!empty($mov_type_f)) {
              $safe_mt = mysqli_real_escape_string($con, $mov_type_f);
              $mov_sql .= " AND sm.movement_type = '$safe_mt'";
          }
          if ($mov_sup_f > 0) {
              $mov_sql .= " AND sm.supplier_id = $mov_sup_f";
          }
          if (!empty($mov_date_f)) {
              $safe_md = mysqli_real_escape_string($con, $mov_date_f);
              $mov_sql .= " AND DATE(sm.movement_date) = '$safe_md'";
          }
          $mov_sql .= " ORDER BY sm.movement_id DESC";
          $mov_res = mysqli_query($con, $mov_sql);

          $f_meds = mysqli_query($con, "SELECT medicine_id, medicine_name FROM medicinetb ORDER BY medicine_name ASC");
          $f_sups = mysqli_query($con, "SELECT supplier_id, supplier_name FROM suppliertb ORDER BY supplier_name ASC");
        ?>

        <form method="get" action="admin-panel1.php" class="form-inline mb-3">
          <select name="mov_med_f" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Medicines</option>
            <?php while ($fm = mysqli_fetch_assoc($f_meds)): ?>
              <option value="<?php echo $fm['medicine_id']; ?>" <?php if ($mov_med_f === intval($fm['medicine_id'])) echo 'selected'; ?>>
                <?php echo htmlspecialchars($fm['medicine_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>

          <select name="mov_type_f" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Types</option>
            <option value="RESTOCK" <?php if ($mov_type_f === 'RESTOCK') echo 'selected'; ?>>RESTOCK</option>
            <option value="PURCHASE" <?php if ($mov_type_f === 'PURCHASE') echo 'selected'; ?>>PURCHASE</option>
            <option value="ADJUSTMENT" <?php if ($mov_type_f === 'ADJUSTMENT') echo 'selected'; ?>>ADJUSTMENT</option>
            <option value="DISPENSE" <?php if ($mov_type_f === 'DISPENSE') echo 'selected'; ?>>DISPENSE</option>
          </select>

          <select name="mov_sup_f" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Suppliers</option>
            <?php while ($fs = mysqli_fetch_assoc($f_sups)): ?>
              <option value="<?php echo $fs['supplier_id']; ?>" <?php if ($mov_sup_f === intval($fs['supplier_id'])) echo 'selected'; ?>>
                <?php echo htmlspecialchars($fs['supplier_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>

          <input type="date" name="mov_date_f" class="form-control form-control-sm mr-2 mb-2" value="<?php echo htmlspecialchars($mov_date_f); ?>">
          <button type="submit" class="btn btn-sm btn-primary mb-2 mr-1"><i class="fa fa-filter"></i> Filter</button>
          <a href="admin-panel1.php#list-movements" class="btn btn-sm btn-outline-secondary mb-2">Reset</a>
        </form>

        <div class="table-responsive">
          <table class="table table-hover table-bordered table-sm">
            <thead class="bg-light">
              <tr>
                <th>#</th>
                <th>Date & Time</th>
                <th>Medicine</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th>Supplier</th>
                <th>By</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($mov_res && mysqli_num_rows($mov_res) > 0): ?>
                <?php while ($m_row = mysqli_fetch_assoc($mov_res)): 
                  $type_badge = ($m_row['movement_type'] === 'PURCHASE') ? 'badge-primary' : (($m_row['movement_type'] === 'RESTOCK') ? 'badge-success' : (($m_row['movement_type'] === 'DISPENSE') ? 'badge-warning' : 'badge-info'));
                  $is_dispense = ($m_row['movement_type'] === 'DISPENSE');
                ?>
                  <tr>
                    <td><strong>#<?php echo $m_row['movement_id']; ?></strong></td>
                    <td><?php echo htmlspecialchars($m_row['movement_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars($m_row['medicine_name']); ?></strong></td>
                    <td><span class="badge <?php echo $type_badge; ?>"><?php echo htmlspecialchars($m_row['movement_type']); ?></span></td>
                    <td>
                      <?php if ($is_dispense): ?>
                        <strong class="text-danger">-<?php echo $m_row['quantity']; ?></strong>
                      <?php else: ?>
                        <strong class="text-success">+<?php echo $m_row['quantity']; ?></strong>
                      <?php endif; ?>
                    </td>
                    <td>$<?php echo number_format($m_row['unit_price'], 2); ?></td>
                    <td><code><?php echo htmlspecialchars($m_row['batch_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($m_row['expiry_date']); ?></td>
                    <td><?php echo htmlspecialchars($m_row['supplier_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($m_row['performed_by']); ?></td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($m_row['reference_note'] ?? ''); ?></small></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No stock movement records found matching criteria.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Supplier Management                                 -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-suppliers" role="tabpanel" aria-labelledby="list-suppliers-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-truck text-primary"></i> Medicine Supplier Management</h4>

        <?php 
          $sup_search = trim($_GET['sup_search'] ?? '');
          $sup_q_str = "SELECT * FROM suppliertb WHERE 1=1";
          if (!empty($sup_search)) {
              $safe_sup = mysqli_real_escape_string($con, $sup_search);
              $sup_q_str .= " AND (supplier_name LIKE '%$safe_sup%' OR contact_person LIKE '%$safe_sup%' OR phone LIKE '%$safe_sup%')";
          }
          $sup_q_str .= " ORDER BY supplier_id ASC";
          $all_sup_res = mysqli_query($con, $sup_q_str);
        ?>

        <form method="get" action="admin-panel1.php" class="form-inline mb-3">
          <input type="text" name="sup_search" class="form-control mr-2" placeholder="Search supplier by name, contact, phone..." value="<?php echo htmlspecialchars($sup_search); ?>" style="min-width: 280px;">
          <button type="submit" class="btn btn-primary mr-1"><i class="fa fa-search"></i> Search</button>
          <a href="admin-panel1.php#list-suppliers" class="btn btn-outline-secondary">Reset</a>
        </form>

        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-white font-weight-bold">Registered Suppliers</div>
          <div class="card-body p-0">
            <table class="table table-hover table-bordered mb-0">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>Supplier Name</th>
                  <th>Contact Person</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Address</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($all_sup_res && mysqli_num_rows($all_sup_res) > 0): ?>
                  <?php while ($sup = mysqli_fetch_assoc($all_sup_res)): 
                    $is_active_sup = ($sup['status'] === 'Active');
                  ?>
                    <tr>
                      <td><?php echo $sup['supplier_id']; ?></td>
                      <td><strong><?php echo htmlspecialchars($sup['supplier_name']); ?></strong></td>
                      <td><?php echo htmlspecialchars($sup['contact_person'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($sup['email'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($sup['phone'] ?? '-'); ?></td>
                      <td><small><?php echo htmlspecialchars($sup['address'] ?? '-'); ?></small></td>
                      <td><span class="badge <?php echo $is_active_sup ? 'badge-success' : 'badge-danger'; ?>"><?php echo htmlspecialchars($sup['status']); ?></span></td>
                      <td>
                        <a href="func_pharmacy.php?toggle_supplier=<?php echo $sup['supplier_id']; ?>" class="btn btn-sm <?php echo $is_active_sup ? 'btn-outline-warning' : 'btn-outline-success'; ?> py-0 px-2">
                          <?php echo $is_active_sup ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($sup)); ?>)">
                          Edit
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add Supplier Form -->
        <div class="card shadow-sm">
          <div class="card-header bg-white font-weight-bold">
            <i class="fa fa-plus-circle text-primary"></i> Add New Supplier
          </div>
          <div class="card-body">
            <form method="post" action="func_pharmacy.php">
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Supplier / Company Name <span class="text-danger">*</span></label>
                  <input type="text" name="supplier_name" class="form-control" placeholder="e.g. Zenith Pharma Distributors" required>
                </div>
                <div class="col-md-6 form-group">
                  <label>Contact Person</label>
                  <input type="text" name="contact_person" class="form-control" placeholder="e.g. Rahul Verma">
                </div>
                <div class="col-md-4 form-group">
                  <label>Email Address</label>
                  <input type="email" name="email" class="form-control" placeholder="e.g. orders@zenithpharma.com">
                </div>
                <div class="col-md-4 form-group">
                  <label>Phone Number</label>
                  <input type="text" name="phone" class="form-control" placeholder="e.g. 9812345678">
                </div>
                <div class="col-md-4 form-group">
                  <label>Status</label>
                  <select name="status" class="form-control">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-md-12 form-group">
                  <label>Office / Warehouse Address</label>
                  <textarea name="address" class="form-control" rows="2" placeholder="Full address details"></textarea>
                </div>
              </div>
              <button type="submit" name="add_supplier_submit" class="btn btn-primary px-4">
                <i class="fa fa-check"></i> Register Supplier
              </button>
            </form>
          </div>
        </div>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Manage Pharmacists                                  -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-pharmacists" role="tabpanel" aria-labelledby="list-pharmacists-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-user-md text-primary"></i> Pharmacist Management</h4>
        
        <!-- Pharmacist List Table -->
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-white font-weight-bold">Registered Pharmacists</div>
          <div class="card-body p-0">
            <table class="table table-hover table-bordered mb-0">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>Full Name</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Contact</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  $pharma_res = mysqli_query($con, "SELECT * FROM pharmacisttb ORDER BY pharmacist_id ASC");
                  if ($pharma_res && mysqli_num_rows($pharma_res) > 0):
                    while ($p = mysqli_fetch_assoc($pharma_res)):
                      $is_active = ($p['status'] === 'Active');
                ?>
                  <tr>
                    <td><?php echo $p['pharmacist_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                    <td><?php echo htmlspecialchars($p['contact']); ?></td>
                    <td>
                      <span class="badge <?php echo $is_active ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo htmlspecialchars($p['status']); ?>
                      </span>
                    </td>
                    <td>
                      <a href="admin-panel1.php?toggle_pharmacist=<?php echo $p['pharmacist_id']; ?>" class="btn btn-sm <?php echo $is_active ? 'btn-outline-warning' : 'btn-outline-success'; ?> py-0 px-2">
                        <?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editPharmacist(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                        Edit
                      </button>
                      <a href="admin-panel1.php?delete_pharmacist=<?php echo $p['pharmacist_id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Are you sure you want to delete this pharmacist account? (Safe check will prevent deletion if they have sales history).');">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No pharmacists registered yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add Pharmacist Form -->
        <div class="card shadow-sm">
          <div class="card-header bg-white font-weight-bold">
            <i class="fa fa-user-plus text-primary"></i> Add New Pharmacist
          </div>
          <div class="card-body">
            <form method="post" action="admin-panel1.php">
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="pharma_name" class="form-control" placeholder="e.g. John Doe" required>
                </div>
                <div class="col-md-6 form-group">
                  <label>Username <span class="text-danger">*</span></label>
                  <input type="text" name="pharma_username" class="form-control" placeholder="e.g. jdoe_pharma" required>
                </div>
                <div class="col-md-6 form-group">
                  <label>Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="pharma_email" class="form-control" placeholder="e.g. jdoe@hospital.com" required>
                </div>
                <div class="col-md-6 form-group">
                  <label>Contact Number <span class="text-danger">*</span></label>
                  <input type="text" name="pharma_contact" class="form-control" placeholder="e.g. 9876543210" required>
                </div>
                <div class="col-md-6 form-group">
                  <label>Password <span class="text-danger">*</span></label>
                  <input type="password" name="pharma_password" class="form-control" placeholder="Set initial password" required>
                  <small class="text-muted">Password will be safely encrypted with password_hash().</small>
                </div>
                <div class="col-md-6 form-group">
                  <label>Account Status</label>
                  <select name="pharma_status" class="form-control">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
              <button type="submit" name="add_pharmacist_submit" class="btn btn-primary px-4">
                <i class="fa fa-check"></i> Register Pharmacist
              </button>
            </form>
          </div>
        </div>
        <br>
      </div>

      <!-- ======================================================== -->
      <!-- TAB: Pharmacy Sales & Invoices History                   -->
      <!-- ======================================================== -->
      <div class="tab-pane fade" id="list-pharma-sales" role="tabpanel" aria-labelledby="list-pharma-sales-list">
        <h4 class="font-weight-bold mb-3"><i class="fa fa-shopping-cart text-primary"></i> Pharmacy Sales History</h4>

        <?php 
          $adm_sale_search = trim($_GET['adm_sale_search'] ?? '');
          $sq_str = "SELECT s.*, p.fname, p.lname, p.contact FROM pharmacy_sales s LEFT JOIN patreg p ON s.pid = p.pid WHERE 1=1";
          if (!empty($adm_sale_search)) {
              $safe_ss = mysqli_real_escape_string($con, $adm_sale_search);
              $sq_str .= " AND (s.bill_id = '$safe_ss' OR p.fname LIKE '%$safe_ss%' OR p.lname LIKE '%$safe_ss%' OR s.pharmacist_username LIKE '%$safe_ss%')";
          }
          $sq_str .= " ORDER BY s.bill_id DESC";
          $adm_sales_res = mysqli_query($con, $sq_str);
        ?>
        <form method="get" action="admin-panel1.php" class="form-inline mb-3">
          <input type="text" name="adm_sale_search" class="form-control mr-2" placeholder="Search by Bill #, Patient, Pharmacist..." value="<?php echo htmlspecialchars($adm_sale_search); ?>" style="min-width: 280px;">
          <button type="submit" class="btn btn-primary mr-1"><i class="fa fa-search"></i> Search Sales</button>
          <a href="admin-panel1.php#list-pharma-sales" class="btn btn-outline-secondary">Reset</a>
        </form>

        <div class="table-responsive">
          <table class="table table-hover table-bordered">
            <thead class="bg-light">
              <tr>
                <th>Bill #</th>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Pharmacist</th>
                <th>Payment</th>
                <th>Total Amount</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($adm_sales_res && mysqli_num_rows($adm_sales_res) > 0): ?>
                <?php while ($sl = mysqli_fetch_assoc($adm_sales_res)): ?>
                  <tr>
                    <td><strong>#<?php echo $sl['bill_id']; ?></strong></td>
                    <td><?php echo htmlspecialchars($sl['sale_date']); ?></td>
                    <td>
                      <?php echo htmlspecialchars(($sl['fname'] ?? 'Unknown') . ' ' . ($sl['lname'] ?? '')); ?>
                      <br><small class="text-muted"><?php echo htmlspecialchars($sl['contact'] ?? ''); ?></small>
                    </td>
                    <td><?php echo !empty($sl['doctor_name']) ? 'Dr. ' . htmlspecialchars($sl['doctor_name']) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($sl['pharmacist_username']); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($sl['payment_type']); ?></span></td>
                    <td><strong class="text-success">$<?php echo number_format($sl['total_amount'], 2); ?></strong></td>
                    <td>
                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewAdminBill(<?php echo $sl['bill_id']; ?>)">
                        <i class="fa fa-eye"></i> View Invoice
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No pharmacy sales records found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <br>
      </div>

    </div>
  </div>
</div>
</div>

<!-- ======================================================== -->
<!-- MODALS FOR ADMIN PHARMACY MANAGEMENT                     -->
<!-- ======================================================== -->

<!-- Edit Pharmacist Modal -->
<div class="modal fade" id="editPharmacistModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="admin-panel1.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fa fa-user-circle"></i> Edit Pharmacist Account</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_pharma_id" id="edit_pharma_id">
          <div class="form-group">
            <label>Full Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_pharma_name" id="edit_pharma_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Username</label>
            <input type="text" id="edit_pharma_username" class="form-control" readonly>
            <small class="text-muted">Username cannot be changed.</small>
          </div>
          <div class="form-group">
            <label>Email Address <span class="text-danger">*</span></label>
            <input type="email" name="edit_pharma_email" id="edit_pharma_email" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Contact Number <span class="text-danger">*</span></label>
            <input type="text" name="edit_pharma_contact" id="edit_pharma_contact" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Reset Password (Optional)</label>
            <input type="password" name="edit_pharma_password" class="form-control" placeholder="Leave blank to keep current password">
          </div>
          <div class="form-group">
            <label>Account Status</label>
            <select name="edit_pharma_status" id="edit_pharma_status" class="form-control">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_pharmacist_submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="func_pharmacy.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fa fa-truck"></i> Edit Supplier</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_supplier_id" id="edit_supplier_id">
          <div class="form-group">
            <label>Supplier / Company Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_supplier_name" id="edit_supplier_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="edit_contact_person" id="edit_contact_person" class="form-control">
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="edit_email" id="edit_email" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="edit_phone" id="edit_phone" class="form-control">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="edit_status" id="edit_status" class="form-control">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label>Office / Warehouse Address</label>
            <textarea name="edit_address" id="edit_address" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_supplier_submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Medicine Details Modal -->
<div class="modal fade" id="adminMedDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa fa-medkit"></i> Medicine Stock & Dispensing Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="adminMedDetailsContent">
        <p class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading details...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Admin View Bill Invoice Modal -->
<div class="modal fade" id="adminViewBillModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa fa-file-text-o"></i> Pharmacy Invoice</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="adminPrintableInvoice">
        <div class="text-center pb-2 border-bottom mb-3">
          <h4 class="font-weight-bold text-primary mb-0"><i class="fa fa-hospital-o"></i> Smart Hospital Pharmacy</h4>
          <p class="text-muted small mb-0">Official Medication Invoice</p>
        </div>
        <div id="adminBillContent">
          <p class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading invoice...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print Invoice</button>
      </div>
    </div>
  </div>
</div>

<!-- Optional JavaScript -->
<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/6.10.1/sweetalert2.all.min.js"></script>

<script>
  // Edit Pharmacist Modal
  function editPharmacist(p) {
    $('#edit_pharma_id').val(p.pharmacist_id);
    $('#edit_pharma_name').val(p.name);
    $('#edit_pharma_username').val(p.username);
    $('#edit_pharma_email').val(p.email);
    $('#edit_pharma_contact').val(p.contact);
    $('#edit_pharma_status').val(p.status);
    $('#editPharmacistModal').modal('show');
  }

  // Edit Supplier Modal
  function editSupplier(sup) {
    $('#edit_supplier_id').val(sup.supplier_id);
    $('#edit_supplier_name').val(sup.supplier_name);
    $('#edit_contact_person').val(sup.contact_person || '');
    $('#edit_email').val(sup.email || '');
    $('#edit_phone').val(sup.phone || '');
    $('#edit_address').val(sup.address || '');
    $('#edit_status').val(sup.status || 'Active');
    $('#editSupplierModal').modal('show');
  }

  // View Medicine Details Modal via AJAX
  function viewMedDetails(medId) {
    $('#adminMedDetailsContent').html('<p class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading details for medicine #' + medId + '...</p>');
    $('#adminMedDetailsModal').modal('show');
    $.get('admin-panel1.php?ajax_med_details=' + medId, function(data) {
      $('#adminMedDetailsContent').html(data);
    });
  }

  // View Bill Details Modal via AJAX
  function viewAdminBill(billId) {
    $('#adminBillContent').html('<p class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading Invoice #' + billId + '...</p>');
    $('#adminViewBillModal').modal('show');
    $.get('admin-panel1.php?ajax_admin_bill=' + billId, function(data) {
      $('#adminBillContent').html(data);
    });
  }

  // URL Hash Tab Management
  $(document).ready(function() {
    var hash = window.location.hash;
    if (hash) {
      $('.list-group a[href="' + hash + '"]').tab('show');
    }
    $('a[data-toggle="list"]').on('shown.bs.tab', function(e) {
      window.location.hash = e.target.hash;
    });
  });
</script>
</body>
</html>