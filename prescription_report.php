<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

if (!$con) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$pid = $_SESSION['pid'] ?? '';

if (empty($pid)) {
    header("Location: index1.php");
    exit();
}

$pp_user    = $_SESSION['username'] ?? ($_SESSION['fname'] ?? 'Patient');
$pp_initial = strtoupper(substr(trim($_SESSION['fname'] ?? 'P'), 0, 1));

$ID = isset($_GET['ID']) ? intval($_GET['ID']) : 0;

$report = null;
if ($ID > 0) {
    $stmt = mysqli_prepare($con, "SELECT doctor, pid, ID, fname, lname, appdate, apptime, disease, allergy, prescription FROM prestb WHERE ID = ? AND pid = ?");
    mysqli_stmt_bind_param($stmt, "ii", $ID, $pid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $report = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Smart Hospital - Prescription Report</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="patient-panel.css">

    <style>
      .report-header {
        border-bottom: 3px double #342ac1;
        padding-bottom: 10px;
        margin-bottom: 20px;
      }
      .report-title {
        font-family: 'IBM Plex Sans', sans-serif;
        color: #342ac1;
      }
      .report-label {
        font-weight: 600;
        color: #495057;
      }
      .prescription-box {
        background: #f8f9fa;
        border-left: 4px solid #342ac1;
        padding: 12px 14px;
        white-space: pre-wrap;
        border-radius: 8px;
      }
      @media print {
        .no-print {
          display: none !important;
        }
        .pp-topbar {
          display: none !important;
        }
        body.pp-body {
          background: #fff !important;
        }
        .pp-paper {
          border: none;
          box-shadow: none;
        }
      }
    </style>
  </head>

  <body class="pp-body">
    <header class="pp-topbar">
      <div class="pp-topbar-left">
        <a href="admin-panel.php#list-pres" class="pp-topbar-title" style="text-decoration:none;color:#243b53;display:block;">
          <strong>Smart Hospital</strong>
          <small>Patient Portal &bull; Prescription Report</small>
        </a>
      </div>
      <div class="pp-topbar-right">
        <a href="admin-panel.php#list-pres" class="pp-topbar-user" title="Back to Dashboard">
          <div class="pp-avatar-sm"><?php echo htmlspecialchars($pp_initial); ?></div>
          <div class="pp-uname">
            <strong><?php echo htmlspecialchars($pp_user); ?></strong>
            <small>Patient Account</small>
          </div>
        </a>
        <a href="logout.php" class="pp-topbar-logout" title="Logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fa fa-sign-out" aria-hidden="true"></i></a>
      </div>
    </header>

    <main class="pp-page">
      <?php if ($report): ?>
        <div class="pp-paper p-4 bg-white">

          <div class="report-header text-center">
            <h3 class="report-title mb-0"><i class="fa fa-hospital-o" aria-hidden="true"></i> SMART HOSPITAL</h3>
            <h5 class="text-uppercase text-dark mt-2">Prescription Report</h5>
            <div class="text-muted small">Generated on <?php echo date('d M Y, h:i A'); ?></div>
          </div>

          <div class="row mb-2">
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-user text-primary" aria-hidden="true"></i> Patient Name:</span>
              <span><?php echo htmlspecialchars($report['fname']) . ' ' . htmlspecialchars($report['lname']); ?></span>
            </div>
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-id-card text-primary" aria-hidden="true"></i> Patient ID:</span>
              <span><?php echo htmlspecialchars($report['pid']); ?></span>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-user-md text-primary" aria-hidden="true"></i> Doctor Name:</span>
              <span><?php echo htmlspecialchars($report['doctor']); ?></span>
            </div>
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-hashtag text-primary" aria-hidden="true"></i> Appointment ID:</span>
              <span><?php echo htmlspecialchars($report['ID']); ?></span>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-calendar text-primary" aria-hidden="true"></i> Appointment Date:</span>
              <span><?php echo htmlspecialchars($report['appdate']); ?></span>
            </div>
            <div class="col-sm-6">
              <span class="report-label"><i class="fa fa-clock-o text-primary" aria-hidden="true"></i> Appointment Time:</span>
              <span><?php echo htmlspecialchars($report['apptime']); ?></span>
            </div>
          </div>

          <div class="mb-3">
            <div class="report-label mb-1"><i class="fa fa-stethoscope text-primary" aria-hidden="true"></i> Disease / Diagnosis:</div>
            <div><?php echo htmlspecialchars($report['disease']); ?></div>
          </div>

          <div class="mb-3">
            <div class="report-label mb-1"><i class="fa fa-exclamation-circle text-primary" aria-hidden="true"></i> Allergies:</div>
            <div><?php echo htmlspecialchars($report['allergy']); ?></div>
          </div>

          <div class="mb-3">
            <div class="report-label mb-1"><i class="fa fa-file-text-o text-primary" aria-hidden="true"></i> Prescription:</div>
            <div class="prescription-box"><?php echo htmlspecialchars($report['prescription']); ?></div>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-4 no-print">
            <a href="admin-panel.php#list-pres" class="pp-btn pp-btn-outline pp-btn-sm"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
            <button type="button" class="pp-btn pp-btn-primary pp-btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Print Report</button>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center mt-5">
          <h4 class="text-danger"><i class="fa fa-exclamation-triangle"></i> Prescription not found or you are not authorised to view it.</h4>
          <a href="admin-panel.php#list-pres" class="pp-btn pp-btn-primary pp-btn-sm mt-3"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>
      <?php endif; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
  </body>
</html>