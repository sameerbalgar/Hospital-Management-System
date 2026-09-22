<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('func.php');  

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

// Extract session variables safely
$pid      = $_SESSION['pid'] ?? '';
$username = $_SESSION['username'] ?? ($_SESSION['fname'] ?? 'Patient');
$email    = $_SESSION['email'] ?? '';
$fname    = $_SESSION['fname'] ?? '';
$lname    = $_SESSION['lname'] ?? '';
$gender   = $_SESSION['gender'] ?? '';
$contact  = $_SESSION['contact'] ?? '';

// Patient-only portal: anyone without a session is sent to login
if (empty($pid)) {
    header("Location: index1.php");
    exit();
}

date_default_timezone_set('Asia/Kolkata');
$pid_int = (int)$pid;

// ---------------- Dashboard statistics (real data, scoped to patient) ----------------
$tot_appts   = 0;
$num_upcoming = 0;
$num_pres    = 0;
$num_bills   = 0;
$upcoming    = null;

$stmt = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM appointmenttb WHERE pid = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $pid_int);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) { $tot_appts = (int)$row['c']; }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

$stmt = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM appointmenttb WHERE pid = ? AND userStatus = 1 AND doctorStatus = 1 AND appdate >= CURDATE()");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $pid_int);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) { $num_upcoming = (int)$row['c']; }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

// Next upcoming appointment (with disease from prestb, if available)
$stmt = mysqli_prepare($con, "SELECT a.ID, a.doctor, a.docFees, a.appdate, a.apptime, a.userStatus, a.doctorStatus, p.disease
                              FROM appointmenttb a
                              LEFT JOIN prestb p ON a.ID = p.ID
                              WHERE a.pid = ? AND a.userStatus = 1 AND a.doctorStatus = 1 AND a.appdate >= CURDATE()
                              ORDER BY a.appdate ASC, a.apptime ASC LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $pid_int);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $upcoming = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

$stmt = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM prestb WHERE pid = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $pid_int);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) { $num_pres = (int)$row['c']; }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

$sales_check = mysqli_query($con, "SELECT 1 FROM pharmacy_sales LIMIT 1");
if ($sales_check) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM pharmacy_sales WHERE pid = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $pid_int);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) { $num_bills = (int)$row['c']; }
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('format_pp_time')) {
    function format_pp_time($t) {
        if (empty($t)) { return ''; }
        $ts = is_numeric($t) ? $t : strtotime($t);
        return ($ts && $ts > 0) ? date('h:i A', $ts) : htmlspecialchars($t);
    }
}
if (!function_exists('format_pp_date')) {
    function format_pp_date($d) {
        if (empty($d) || $d === '0000-00-00') { return '—'; }
        $ts = strtotime($d);
        return $ts ? date('d M Y', $ts) : htmlspecialchars($d);
    }
}

$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}
$initial = strtoupper(substr(trim($fname), 0, 1));
if ($initial === '') { $initial = 'P'; }

// Handle Appointment Booking
if (isset($_POST['app-submit'])) {
    $doctor  = mysqli_real_escape_string($con, $_POST['doctor'] ?? '');
    $docFees = mysqli_real_escape_string($con, $_POST['docFees'] ?? '');
    $appdate = mysqli_real_escape_string($con, $_POST['appdate'] ?? '');
    $apptime = mysqli_real_escape_string($con, $_POST['apptime'] ?? '');

    $cur_date = date("Y-m-d");
    $cur_time = date("H:i:s");

    $apptime1 = strtotime($apptime);
    $appdate1 = strtotime($appdate);

    if (date("Y-m-d", $appdate1) >= $cur_date) {
        if ((date("Y-m-d", $appdate1) == $cur_date && date("H:i:s", $apptime1) > $cur_time) || date("Y-m-d", $appdate1) > $cur_date) {
            $check_query = mysqli_query($con, "SELECT apptime FROM appointmenttb WHERE doctor='$doctor' AND appdate='$appdate' AND apptime='$apptime'");

            if ($check_query && mysqli_num_rows($check_query) == 0) {
                $stmt = mysqli_prepare($con, "INSERT INTO appointmenttb (pid, fname, lname, gender, email, contact, doctor, docFees, appdate, apptime, userStatus, doctorStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '1', '1')");
                mysqli_stmt_bind_param($stmt, "isssssssss", $pid, $fname, $lname, $gender, $email, $contact, $doctor, $docFees, $appdate, $apptime);
                $query = mysqli_stmt_execute($stmt);

                if ($query) {
                    echo "<script>alert('Your appointment was successfully booked');</script>";
                } else {
                    echo "<script>alert('Unable to process your request. Please try again!');</script>";
                }
            } else {
                echo "<script>alert('Doctor is not available at this date or time. Please choose another slot!');</script>";
            }
        } else {
            echo "<script>alert('Select a date or time in the future!');</script>";
        }
    } else {
        echo "<script>alert('Select a date or time in the future!');</script>";
    }
}

// Handle Appointment Cancellation
if (isset($_GET['cancel'])) {
    $id = mysqli_real_escape_string($con, $_GET['ID'] ?? '');
    $query = mysqli_query($con, "UPDATE appointmenttb SET userStatus='0' WHERE ID = '$id'");
    if ($query) {
        echo "<script>alert('Your appointment was successfully cancelled');</script>";
    }
}

// Function to generate PDF bill content
function generate_bill() {
    global $con, $pid;
    $id = mysqli_real_escape_string($con, $_GET['ID'] ?? '');
    $pid_clean = mysqli_real_escape_string($con, $pid);
    $output = '';
    $query = mysqli_query($con, "SELECT p.pid, p.ID, p.fname, p.lname, p.doctor, p.appdate, p.apptime, p.disease, p.allergy, p.prescription, a.docFees FROM prestb p INNER JOIN appointmenttb a ON p.ID=a.ID WHERE p.pid = '$pid_clean' AND p.ID = '$id'");
    
    if ($query) {
        while ($row = mysqli_fetch_array($query)) {
            $output .= '
            <label> Patient ID : </label>' . $row["pid"] . '<br/><br/>
            <label> Appointment ID : </label>' . $row["ID"] . '<br/><br/>
            <label> Patient Name : </label>' . $row["fname"] . ' ' . $row["lname"] . '<br/><br/>
            <label> Doctor Name : </label>' . $row["doctor"] . '<br/><br/>
            <label> Appointment Date : </label>' . $row["appdate"] . '<br/><br/>
            <label> Appointment Time : </label>' . $row["apptime"] . '<br/><br/>
            <label> Disease : </label>' . $row["disease"] . '<br/><br/>
            <label> Allergies : </label>' . $row["allergy"] . '<br/><br/>
            <label> Prescription : </label>' . $row["prescription"] . '<br/><br/>
            <label> Fees Paid : </label>' . $row["docFees"] . '<br/>';
        }
    }
    return $output;
}

// PDF Export Handler
if (isset($_GET["generate_bill"])) {
    if (file_exists("TCPDF/tcpdf.php")) {
        require_once("TCPDF/tcpdf.php");
        $obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $obj_pdf->SetCreator(PDF_CREATOR);
        $obj_pdf->SetTitle("Generate Bill");
        $obj_pdf->SetHeaderData('', '', PDF_HEADER_TITLE, PDF_HEADER_STRING);
        $obj_pdf->SetHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $obj_pdf->SetFooterFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $obj_pdf->SetDefaultMonospacedFont('helvetica');
        $obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $obj_pdf->SetMargins(PDF_MARGIN_LEFT, '5', PDF_MARGIN_RIGHT);
        $obj_pdf->SetPrintHeader(false);
        $obj_pdf->SetPrintFooter(false);
        $obj_pdf->SetAutoPageBreak(TRUE, 10);
        $obj_pdf->SetFont('helvetica', '', 12);
        $obj_pdf->AddPage();

        $content = '<br/><h2 align="center">Smart Hospital</h2><br/><h3 align="center">Bill</h3>';
        $content .= generate_bill();
        $obj_pdf->writeHTML($content);
        ob_end_clean();
        $obj_pdf->Output("bill.pdf", 'I');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Smart Hospital - Patient Dashboard</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="patient-panel.css">
  </head>

  <body class="pp-body">

    <div class="pp-overlay" id="ppOverlay"></div>

    <!-- ================= Sidebar ================= -->
    <aside class="pp-sidebar" id="ppSidebar">
      <div class="pp-brand">
        <div class="pp-brand-icon"><i class="fa fa-hospital-o" aria-hidden="true"></i></div>
        <div class="pp-brand-text">
          <strong>Smart Hospital</strong>
          <small>Patient Portal</small>
        </div>
      </div>

      <div class="pp-profile">
        <div class="pp-avatar"><?php echo htmlspecialchars($initial); ?></div>
        <div class="pp-profile-info">
          <strong><?php echo htmlspecialchars($username); ?></strong>
          <small><i class="fa fa-user" aria-hidden="true"></i> Patient</small>
        </div>
      </div>

      <ul class="pp-menu">
        <li class="pp-menu-label">Menu</li>
        <li><a class="pp-menu-item active" href="#list-dash" data-tab="list-dash"><i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard</a></li>
        <li><a class="pp-menu-item" href="#list-home" data-tab="list-home"><i class="fa fa-calendar" aria-hidden="true"></i> Book Appointment</a></li>
        <li><a class="pp-menu-item" href="#app-hist" data-tab="app-hist"><i class="fa fa-history" aria-hidden="true"></i> Appointment History</a></li>
        <li><a class="pp-menu-item" href="#list-pres" data-tab="list-pres"><i class="fa fa-file-text-o" aria-hidden="true"></i> Prescriptions</a></li>
        <li><a class="pp-menu-item" href="pharmacy_bills.php"><i class="fa fa-money" aria-hidden="true"></i> Pharmacy Bills</a></li>
        <li><a class="pp-menu-item pp-logout" href="logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- ================= Topbar ================= -->
    <header class="pp-topbar">
      <div class="pp-topbar-left">
        <button class="pp-sidebar-toggle" id="ppSidebarToggle" aria-label="Toggle sidebar"><i class="fa fa-bars" aria-hidden="true"></i></button>
        <div class="pp-topbar-title">
          <strong>Smart Hospital</strong>
          <small>Patient Portal</small>
        </div>
      </div>
      <div class="pp-topbar-right">
        <div class="pp-topbar-user">
          <div class="pp-avatar-sm"><?php echo htmlspecialchars($initial); ?></div>
          <div class="pp-uname">
            <strong><?php echo htmlspecialchars($username); ?></strong>
            <small>Patient Account</small>
          </div>
        </div>
        <a href="logout.php" class="pp-topbar-logout" title="Logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fa fa-sign-out" aria-hidden="true"></i></a>
      </div>
    </header>

    <!-- ================= Main ================= -->
    <div class="pp-wrap">
      <main class="pp-content">

        <!-- Welcome -->
        <div class="pp-welcome">
          <div>
            <h2><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($fname ?: $username); ?> <span aria-hidden="true">👋</span></h2>
            <p>Manage your appointments, prescriptions and pharmacy bills.</p>
          </div>
          <div class="pp-welcome-logo"><i class="fa fa-heartbeat" aria-hidden="true"></i></div>
        </div>

        <!-- Summary Cards -->
        <div class="pp-stats">
          <a class="pp-stat" href="#app-hist" data-tab="app-hist">
            <div class="pp-stat-icon pp-stat-icon-blue"><i class="fa fa-calendar" aria-hidden="true"></i></div>
            <div class="pp-stat-info">
              <span class="pp-stat-num"><?php echo (int)$tot_appts; ?></span>
              <span class="pp-stat-label">Total Appointments</span>
            </div>
          </a>
          <a class="pp-stat" href="#app-hist" data-tab="app-hist">
            <div class="pp-stat-icon pp-stat-icon-teal"><i class="fa fa-clock-o" aria-hidden="true"></i></div>
            <div class="pp-stat-info">
              <span class="pp-stat-num"><?php echo (int)$num_upcoming; ?></span>
              <span class="pp-stat-label">Upcoming Appointments</span>
            </div>
          </a>
          <a class="pp-stat" href="#list-pres" data-tab="list-pres">
            <div class="pp-stat-icon pp-stat-icon-amber"><i class="fa fa-file-text-o" aria-hidden="true"></i></div>
            <div class="pp-stat-info">
              <span class="pp-stat-num"><?php echo (int)$num_pres; ?></span>
              <span class="pp-stat-label">Prescriptions</span>
            </div>
          </a>
          <a class="pp-stat" href="pharmacy_bills.php">
            <div class="pp-stat-icon pp-stat-icon-green"><i class="fa fa-money" aria-hidden="true"></i></div>
            <div class="pp-stat-info">
              <span class="pp-stat-num"><?php echo (int)$num_bills; ?></span>
              <span class="pp-stat-label">Pharmacy Bills</span>
            </div>
          </a>
        </div>

        <!-- Dashboard Tab Pane -->
        <div class="tab-content">

          <div class="tab-pane fade show active" id="list-dash" role="tabpanel">

            <!-- Upcoming Appointment -->
            <div class="pp-card">
              <div class="pp-card-header">
                <h5 class="pp-card-title"><i class="fa fa-calendar" aria-hidden="true"></i> Upcoming Appointment</h5>
              </div>
              <div class="pp-card-body">
                <?php if ($upcoming): ?>
                  <div class="pp-upcoming">
                    <div class="pp-doctor-block">
                      <div class="pp-doctor-avatar"><i class="fa fa-user-md" aria-hidden="true"></i></div>
                      <div>
                        <span class="pp-upcoming-key">Doctor</span>
                        <div class="pp-upcoming-val"><?php echo htmlspecialchars($upcoming['doctor']); ?></div>
                      </div>
                    </div>
                    <div class="pp-upcoming-meta">
                      <div class="pp-upcoming-item">
                        <span class="pp-upcoming-key">Appointment Date</span>
                        <span class="pp-upcoming-val"><?php echo format_pp_date($upcoming['appdate']); ?></span>
                      </div>
                      <div class="pp-upcoming-item">
                        <span class="pp-upcoming-key">Appointment Time</span>
                        <span class="pp-upcoming-val"><?php echo format_pp_time($upcoming['apptime']); ?></span>
                      </div>
                      <div class="pp-upcoming-item">
                        <span class="pp-upcoming-key">Consultancy Fees</span>
                        <span class="pp-upcoming-val">&#8377; <?php echo htmlspecialchars($upcoming['docFees']); ?></span>
                      </div>
                      <div class="pp-upcoming-item">
                        <span class="pp-upcoming-key">Status</span>
                        <span class="pp-badge pp-badge-confirmed"><i class="fa fa-check-circle" aria-hidden="true"></i> Confirmed</span>
                      </div>
                    </div>
                    <?php if (!empty($upcoming['disease'])): ?>
                    <div class="pp-upcoming-item" style="flex-basis:100%;">
                      <span class="pp-upcoming-key">Disease / Diagnosis</span>
                      <span class="pp-upcoming-val"><?php echo htmlspecialchars($upcoming['disease']); ?></span>
                    </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="pp-empty">
                    <div class="pp-empty-icon"><i class="fa fa-calendar" aria-hidden="true"></i></div>
                    <h5>No upcoming appointments</h5>
                    <p>You don&rsquo;t have any upcoming appointments scheduled right now.</p>
                    <a href="#list-home" class="pp-btn pp-btn-primary" data-tab="list-home"><i class="fa fa-pencil" aria-hidden="true"></i> Book Appointment</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Quick actions -->
            <div class="pp-card">
              <div class="pp-card-header">
                <h5 class="pp-card-title"><i class="fa fa-gears" aria-hidden="true"></i> Quick Actions</h5>
              </div>
              <div class="pp-card-body">
                <div class="row">
                  <div class="col-sm-4 mb-3">
                    <a href="#list-home" class="pp-btn pp-btn-primary pp-btn-100" data-tab="list-home"><i class="fa fa-pencil" aria-hidden="true"></i> Book Appointment</a>
                  </div>
                  <div class="col-sm-4 mb-3">
                    <a href="#app-hist" class="pp-btn pp-btn-outline pp-btn-100" data-tab="app-hist"><i class="fa fa-history" aria-hidden="true"></i> View Appointment History</a>
                  </div>
                  <div class="col-sm-4 mb-3">
                    <a href="pharmacy_bills.php" class="pp-btn pp-btn-outline pp-btn-100"><i class="fa fa-money" aria-hidden="true"></i> View Pharmacy Bills</a>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Book Appointment Tab -->
          <div class="tab-pane fade" id="list-home" role="tabpanel">
            <div class="pp-card">
              <div class="pp-card-header">
                <h5 class="pp-card-title"><i class="fa fa-calendar" aria-hidden="true"></i> Book an Appointment</h5>
              </div>
              <div class="pp-card-body">
                <form class="form-group" method="post" action="admin-panel.php">
                  <div class="row">
                    <div class="col-md-4"><label class="pp-form-label" for="spec">Specialization:</label></div>
                    <div class="col-md-8">
                      <select name="spec" class="form-control" id="spec">
                        <option value="" disabled selected>Select Specialization</option>
                        <?php if (function_exists('display_specs')) { display_specs(); } ?>
                      </select>
                    </div>
                    <div class="col-md-4"><br></div>
                    <div class="col-md-8"><br><br></div>

                    <div class="col-md-4"><label class="pp-form-label" for="doctor">Doctors:</label></div>
                    <div class="col-md-8">
                      <select name="doctor" class="form-control" id="doctor" required>
                        <option value="" disabled selected>Select Doctor</option>
                        <?php if (function_exists('display_docs')) { display_docs(); } ?>
                      </select>
                    </div>
                    <div class="col-md-4"><br></div>
                    <div class="col-md-8"><br><br></div>

                    <div class="col-md-4"><label class="pp-form-label" for="docFees">Consultancy Fees:</label></div>
                    <div class="col-md-8">
                      <input class="form-control" type="text" name="docFees" id="docFees" readonly/>
                    </div>
                    <div class="col-md-4"><br></div>
                    <div class="col-md-8"><br><br></div>

                    <div class="col-md-4"><label class="pp-form-label">Appointment Date:</label></div>
                    <div class="col-md-8"><input type="date" class="form-control datepicker" name="appdate" required></div>
                    <div class="col-md-4"><br></div>
                    <div class="col-md-8"><br><br></div>

                    <div class="col-md-4"><label class="pp-form-label">Appointment Time:</label></div>
                    <div class="col-md-8">
                      <select name="apptime" class="form-control" id="apptime" required>
                        <option value="" disabled selected>Select Time</option>
                        <option value="08:00:00">8:00 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                      </select>
                    </div>
                    <div class="col-md-4"><br></div>
                    <div class="col-md-8"><br><br></div>

                    <div class="col-md-4"></div>
                    <div class="col-md-8">
                      <input type="submit" name="app-submit" value="Create New Appointment" class="pp-btn pp-btn-primary" id="inputbtn">
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Appointment History Tab -->
          <div class="tab-pane fade" id="app-hist" role="tabpanel">
            <div class="pp-card">
              <div class="pp-card-header">
                <h5 class="pp-card-title"><i class="fa fa-history" aria-hidden="true"></i> Appointment History</h5>
                <div class="pp-search-box" style="max-width:260px;">
                  <i class="fa fa-search" aria-hidden="true"></i>
                  <input type="text" class="form-control" id="apptSearch" placeholder="Search by doctor or date...">
                </div>
              </div>
              <div class="pp-card-body">
                <?php if (!empty($pid)): ?>
                  <?php
                    $pid_clean = mysqli_real_escape_string($con, $pid);
                    $query = "SELECT ID, doctor, docFees, appdate, apptime, userStatus, doctorStatus FROM appointmenttb WHERE pid = '$pid_clean' ORDER BY appdate DESC";
                    $result = mysqli_query($con, $query);
                    $has_history = ($result && mysqli_num_rows($result) > 0);
                  ?>
                  <?php if ($has_history): ?>
                    <div class="table-responsive pp-table-wrap">
                      <table class="table pp-table" id="apptTable">
                        <thead>
                          <tr>
                            <th scope="col">Appointment ID</th>
                            <th scope="col">Doctor</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Fees</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php while ($row = mysqli_fetch_array($result)): ?>
                            <tr>
                              <td>#<?php echo htmlspecialchars($row['ID']); ?></td>
                              <td><?php echo htmlspecialchars($row['doctor']); ?></td>
                              <td><?php echo format_pp_date($row['appdate']); ?></td>
                              <td><?php echo format_pp_time($row['apptime']); ?></td>
                              <td>&#8377; <?php echo htmlspecialchars($row['docFees']); ?></td>
                              <td>
                                <?php
                                  if ($row['userStatus'] == 1 && $row['doctorStatus'] == 1) {
                                      echo '<span class="pp-badge pp-badge-confirmed"><i class="fa fa-check-circle"></i> Confirmed</span>';
                                  } elseif ($row['userStatus'] == 0 && $row['doctorStatus'] == 1) {
                                      echo '<span class="pp-badge pp-badge-cancelled"><i class="fa fa-times-circle"></i> Cancelled by You</span>';
                                  } elseif ($row['userStatus'] == 1 && $row['doctorStatus'] == 0) {
                                      echo '<span class="pp-badge pp-badge-cancelled"><i class="fa fa-times-circle"></i> Cancelled by Doctor</span>';
                                  } else {
                                      echo '<span class="pp-badge pp-badge-cancelled"><i class="fa fa-times-circle"></i> Cancelled</span>';
                                  }
                                ?>
                              </td>
                              <td>
                                <?php if ($row['userStatus'] == 1 && $row['doctorStatus'] == 1) { ?>
                                  <a href="admin-panel.php?ID=<?php echo htmlspecialchars($row['ID']); ?>&cancel=update#app-hist"
                                     onClick="return confirm('Are you sure you want to cancel this appointment?')"
                                     class="pp-btn pp-btn-danger pp-btn-sm">Cancel</a>
                                <?php } else { echo '<span class="text-muted">Cancelled</span>'; } ?>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="pp-empty">
                      <div class="pp-empty-icon"><i class="fa fa-history" aria-hidden="true"></i></div>
                      <h5>No appointments found</h5>
                      <p>You haven&rsquo;t booked any appointments yet.</p>
                      <a href="#list-home" class="pp-btn pp-btn-primary" data-tab="list-home"><i class="fa fa-pencil" aria-hidden="true"></i> Book Appointment</a>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="pp-empty">
                    <div class="pp-empty-icon"><i class="fa fa-user" aria-hidden="true"></i></div>
                    <h5>Not signed in</h5>
                    <p>Please log in to view your appointment history.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Prescriptions Tab -->
          <div class="tab-pane fade" id="list-pres" role="tabpanel">
            <div class="pp-card">
              <div class="pp-card-header">
                <h5 class="pp-card-title"><i class="fa fa-file-text-o" aria-hidden="true"></i> Prescriptions</h5>
              </div>
              <div class="pp-card-body">
                <?php if (!empty($pid)): ?>
                  <?php
                    $pid_clean = mysqli_real_escape_string($con, $pid);
                    $query = "SELECT doctor, ID, appdate, apptime, disease, allergy, prescription FROM prestb WHERE pid='$pid_clean' ORDER BY ID DESC";
                    $result = mysqli_query($con, $query);
                    $has_pres = ($result && mysqli_num_rows($result) > 0);
                  ?>
                  <?php if ($has_pres): ?>
                    <div class="table-responsive pp-table-wrap">
                      <table class="table pp-table">
                        <thead>
                          <tr>
                            <th scope="col">Doctor</th>
                            <th scope="col">Appointment ID</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Disease</th>
                            <th scope="col">Allergies</th>
                            <th scope="col">Prescription</th>
                            <th scope="col">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php while ($row = mysqli_fetch_array($result)): ?>
                            <tr>
                              <td><?php echo htmlspecialchars($row['doctor']); ?></td>
                              <td>#<?php echo htmlspecialchars($row['ID']); ?></td>
                              <td><?php echo format_pp_date($row['appdate']); ?></td>
                              <td><?php echo format_pp_time($row['apptime']); ?></td>
                              <td><?php echo htmlspecialchars($row['disease']); ?></td>
                              <td><?php echo htmlspecialchars($row['allergy']); ?></td>
                              <td><?php echo htmlspecialchars($row['prescription']); ?></td>
                              <td>
                                <a href="prescription_report.php?ID=<?php echo urlencode($row['ID']); ?>" class="pp-btn pp-btn-primary pp-btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View Prescription Report</a>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="pp-empty">
                      <div class="pp-empty-icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></div>
                      <h5>No prescriptions available</h5>
                      <p>Your doctor&rsquo;s prescriptions will appear here after your appointments.</p>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="pp-empty">
                    <div class="pp-empty-icon"><i class="fa fa-user" aria-hidden="true"></i></div>
                    <h5>Not signed in</h5>
                    <p>Please log in to view your prescriptions.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>

      </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

    <script>
      // ---------- Tab switching + sidebar active state ----------
      function activateTab(id, updateHash) {
        var pane = document.getElementById(id);
        if (!pane) { id = 'list-dash'; pane = document.getElementById(id); }
        document.querySelectorAll('.tab-pane').forEach(function (p) {
          p.classList.remove('active', 'show');
        });
        if (pane) { pane.classList.add('active', 'show'); }
        document.querySelectorAll('.pp-menu-item[data-tab]').forEach(function (li) {
          li.classList.toggle('active', li.getAttribute('data-tab') === id);
        });
        if (updateHash && window.history.replaceState) {
          window.history.replaceState(null, '', '#' + id);
        }
        closeSidebar();
      }

      function openSidebar() {
        document.getElementById('ppSidebar').classList.add('pp-open');
        document.getElementById('ppOverlay').classList.add('pp-show');
      }

      function closeSidebar() {
        document.getElementById('ppSidebar').classList.remove('pp-open');
        document.getElementById('ppOverlay').classList.remove('pp-show');
      }

      document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('[data-tab]');
        if (t) {
          e.preventDefault();
          activateTab(t.getAttribute('data-tab'), true);
          return;
        }
        if (e.target.closest && e.target.closest('#ppSidebarToggle')) {
          var sb = document.getElementById('ppSidebar');
          if (sb.classList.contains('pp-open')) { closeSidebar(); } else { openSidebar(); }
          return;
        }
        if (e.target.closest && e.target.closest('#ppOverlay')) { closeSidebar(); }
      });

      // Restore last active tab (supports deep links like #list-pres / #app-hist)
      (function () {
        var hash = window.location.hash.replace('#', '');
        var valid = ['list-dash', 'list-home', 'app-hist', 'list-pres'];
        activateTab(valid.indexOf(hash) !== -1 ? hash : 'list-dash', false);
      })();

      // ---------- Appointment history search ----------
      $('#apptSearch').on('keyup', function () {
        var q = this.value.toLowerCase();
        $('#apptTable tbody tr').each(function () {
          $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
        });
      });

      // ---------- Filter doctors based on selected Specialization ----------
      var specSelect = document.getElementById('spec');
      if (specSelect) {
        specSelect.onchange = function () {
          var spec = this.value;
          var doctorSelect = document.getElementById('doctor');
          var docs = [].slice.call(doctorSelect.options);
          docs.forEach(function (el) {
            if (el.value === '') return;
            if (el.getAttribute('data-spec') !== spec) {
              el.style.display = 'none';
            } else {
              el.style.display = 'block';
            }
          });
          doctorSelect.value = '';
          document.getElementById('docFees').value = '';
        };
      }

      // ---------- Auto-update Consultancy Fees based on selected Doctor ----------
      var docSelect = document.getElementById('doctor');
      if (docSelect) {
        docSelect.onchange = function () {
          var selectedOption = this.options[this.selectedIndex];
          var fees = selectedOption.getAttribute('data-value') || '';
          document.getElementById('docFees').value = fees;
        };
      }
    </script>
  </body>
</html>