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

// Handle Appointment Booking
if (isset($_POST['app-submit'])) {
    $doctor  = mysqli_real_escape_string($con, $_POST['doctor'] ?? '');
    $docFees = mysqli_real_escape_string($con, $_POST['docFees'] ?? '');
    $appdate = mysqli_real_escape_string($con, $_POST['appdate'] ?? '');
    $apptime = mysqli_real_escape_string($con, $_POST['apptime'] ?? '');

    $cur_date = date("Y-m-d");
    date_default_timezone_set('Asia/Kolkata');
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
    <title>Smart Hospital - Dashboard</title>

    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="style2.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Sans&display=swap" rel="stylesheet">
  </head>

  <body style="padding-top:50px;">
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
      <div class="container-fluid">
        <a class="navbar-brand" href="#"><i class="fa fa-user-plus" aria-hidden="true"></i> Smart Hospital </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
           <ul class="navbar-nav mr-auto">
             <li class="nav-item">
              <a class="nav-link" href="logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

   <div class="container-fluid" style="margin-top:50px;">
    <h3 style="margin-left: 40%; padding-bottom: 20px; font-family: 'IBM Plex Sans', sans-serif;"> 
      Welcome <?php echo htmlspecialchars($username); ?>
    </h3>

    <div class="row">
      <div class="col-md-4" style="max-width:25%; margin-top: 3%">
        <div class="list-group" id="list-tab" role="tablist">
          <a class="list-group-item list-group-item-action active" id="list-dash-list" data-toggle="list" href="#list-dash" role="tab">Dashboard</a>
          <a class="list-group-item list-group-item-action" id="list-home-list" data-toggle="list" href="#list-home" role="tab">Book Appointment</a>
          <a class="list-group-item list-group-item-action" id="list-pat-list" data-toggle="list" href="#app-hist" role="tab">Appointment History</a>
          <a class="list-group-item list-group-item-action" id="list-pres-list" data-toggle="list" href="#list-pres" role="tab">Prescriptions</a>
        </div><br>
      </div>

      <div class="col-md-8" style="margin-top: 3%;">
        <div class="tab-content" id="nav-tabContent" style="width: 100%;">

          <!-- Dashboard Tab -->
          <div class="tab-pane fade show active" id="list-dash" role="tabpanel">
            <div class="container-fluid bg-white">
              <div class="row">
                <div class="col-sm-4">
                  <div class="panel panel-white no-radius text-center border p-3 m-2">
                    <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-terminal fa-stack-1x fa-inverse"></i> </span>
                    <h4 class="StepTitle mt-3">Book My Appointment</h4>
                    <p class="links cl-effect-1">
                      <a href="#list-home" onclick="document.getElementById('list-home-list').click();">Book Appointment</a>
                    </p>
                  </div>
                </div>

                <div class="col-sm-4">
                  <div class="panel panel-white no-radius text-center border p-3 m-2">
                    <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-paperclip fa-stack-1x fa-inverse"></i> </span>
                    <h4 class="StepTitle mt-3">My Appointments</h4>
                    <p class="cl-effect-1">
                      <a href="#app-hist" onclick="document.getElementById('list-pat-list').click();">View Appointment History</a>
                    </p>
                  </div>
                </div>

                <div class="col-sm-4">
                  <div class="panel panel-white no-radius text-center border p-3 m-2">
                    <span class="fa-stack fa-2x"> <i class="fa fa-square fa-stack-2x text-primary"></i> <i class="fa fa-list-ul fa-stack-1x fa-inverse"></i> </span>
                    <h4 class="StepTitle mt-3">Prescriptions</h4>
                    <p class="cl-effect-1">
                      <a href="#list-pres" onclick="document.getElementById('list-pres-list').click();">View Prescription List</a>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Book Appointment Tab -->
          <div class="tab-pane fade" id="list-home" role="tabpanel">
            <div class="container-fluid">
              <div class="card">
                <div class="card-body">
                  <center><h4>Create an appointment</h4></center><br>
                  <form class="form-group" method="post" action="admin-panel.php">
                    <div class="row">
                      <div class="col-md-4"><label for="spec">Specialization:</label></div>
                      <div class="col-md-8">
                        <select name="spec" class="form-control" id="spec">
                            <option value="" disabled selected>Select Specialization</option>
                            <?php if (function_exists('display_specs')) { display_specs(); } ?>
                        </select>
                      </div><br><br>

                      <div class="col-md-4"><label for="doctor">Doctors:</label></div>
                      <div class="col-md-8">
                        <select name="doctor" class="form-control" id="doctor" required>
                          <option value="" disabled selected>Select Doctor</option>
                          <?php if (function_exists('display_docs')) { display_docs(); } ?>
                        </select>
                      </div><br><br> 

                      <div class="col-md-4"><label for="docFees">Consultancy Fees</label></div>
                      <div class="col-md-8">
                        <input class="form-control" type="text" name="docFees" id="docFees" readonly/>
                      </div><br><br>

                      <div class="col-md-4"><label>Appointment Date</label></div>
                      <div class="col-md-8"><input type="date" class="form-control datepicker" name="appdate" required></div><br><br>

                      <div class="col-md-4"><label>Appointment Time</label></div>
                      <div class="col-md-8">
                        <select name="apptime" class="form-control" id="apptime" required>
                          <option value="" disabled selected>Select Time</option>
                          <option value="08:00:00">8:00 AM</option>
                          <option value="10:00:00">10:00 AM</option>
                          <option value="12:00:00">12:00 PM</option>
                          <option value="14:00:00">2:00 PM</option>
                          <option value="16:00:00">4:00 PM</option>
                        </select>
                      </div><br><br>

                      <div class="col-md-4">
                        <input type="submit" name="app-submit" value="Create new entry" class="btn btn-primary" id="inputbtn">
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Appointment History Tab -->
          <div class="tab-pane fade" id="app-hist" role="tabpanel">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th scope="col">Doctor Name</th>
                  <th scope="col">Consultancy Fees</th>
                  <th scope="col">Appointment Date</th>
                  <th scope="col">Appointment Time</th>
                  <th scope="col">Current Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  if (!empty($fname) && !empty($lname)) {
                      $fname_clean = mysqli_real_escape_string($con, $fname);
                      $lname_clean = mysqli_real_escape_string($con, $lname);
                      $query = "SELECT ID, doctor, docFees, appdate, apptime, userStatus, doctorStatus FROM appointmenttb WHERE fname ='$fname_clean' AND lname='$lname_clean'";
                      $result = mysqli_query($con, $query);
                      if ($result) {
                          while ($row = mysqli_fetch_array($result)) {
                ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['doctor']); ?></td>
                      <td><?php echo htmlspecialchars($row['docFees']); ?></td>
                      <td><?php echo htmlspecialchars($row['appdate']); ?></td>
                      <td><?php echo htmlspecialchars($row['apptime']); ?></td>
                      <td>
                        <?php 
                          if ($row['userStatus'] == 1 && $row['doctorStatus'] == 1) echo "Active";
                          elseif ($row['userStatus'] == 0 && $row['doctorStatus'] == 1) echo "Cancelled by You";
                          elseif ($row['userStatus'] == 1 && $row['doctorStatus'] == 0) echo "Cancelled by Doctor";
                        ?>
                      </td>
                      <td>
                        <?php if ($row['userStatus'] == 1 && $row['doctorStatus'] == 1) { ?>
                          <a href="admin-panel.php?ID=<?php echo $row['ID'] ?>&cancel=update" 
                             onClick="return confirm('Are you sure you want to cancel this appointment?')"
                             class="btn btn-danger btn-sm">Cancel</a>
                        <?php } else { echo "Cancelled"; } ?>
                      </td>
                    </tr>
                <?php 
                          }
                      }
                  } else {
                      echo "<tr><td colspan='6' class='text-center'>Please log in to view appointment history.</td></tr>";
                  }
                ?>
              </tbody>
            </table>
          </div>

          <!-- Prescriptions Tab -->
          <div class="tab-pane fade" id="list-pres" role="tabpanel">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th scope="col">Doctor Name</th>
                  <th scope="col">Appointment ID</th>
                  <th scope="col">Appointment Date</th>
                  <th scope="col">Appointment Time</th>
                  <th scope="col">Diseases</th>
                  <th scope="col">Allergies</th>
                  <th scope="col">Prescription</th>
                  <th scope="col">Bill Payment</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  if (!empty($pid)) {
                      $pid_clean = mysqli_real_escape_string($con, $pid);
                      $query = "SELECT doctor, ID, appdate, apptime, disease, allergy, prescription FROM prestb WHERE pid='$pid_clean'";
                      $result = mysqli_query($con, $query);
                      if ($result && mysqli_num_rows($result) > 0) {
                          while ($row = mysqli_fetch_array($result)) {
                ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['doctor']); ?></td>
                      <td><?php echo htmlspecialchars($row['ID']); ?></td>
                      <td><?php echo htmlspecialchars($row['appdate']); ?></td>
                      <td><?php echo htmlspecialchars($row['apptime']); ?></td>
                      <td><?php echo htmlspecialchars($row['disease']); ?></td>
                      <td><?php echo htmlspecialchars($row['allergy']); ?></td>
                      <td><?php echo htmlspecialchars($row['prescription']); ?></td>
                      <td>
                        <form method="get" action="admin-panel.php">
                          <input type="hidden" name="ID" value="<?php echo $row['ID']; ?>"/>
                          <input type="submit" name="generate_bill" class="btn btn-success btn-sm" value="Pay Bill"/>
                        </form>
                      </td>
                    </tr>
                <?php 
                          }
                      } else {
                          echo "<tr><td colspan='8' class='text-center'>No prescriptions found.</td></tr>";
                      }
                  } else {
                      echo "<tr><td colspan='8' class='text-center'>Please log in to view prescriptions.</td></tr>";
                  }
                ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
   </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

    <script>
      // Filter doctors based on selected Specialization
      var specSelect = document.getElementById('spec');
      if (specSelect) {
        specSelect.onchange = function() {
          var spec = this.value;   
          var doctorSelect = document.getElementById('doctor');
          var docs = [...doctorSelect.options];
          
          docs.forEach(function(el) {
            if (el.value === "") return;
            if (el.getAttribute("data-spec") !== spec) {
              el.style.display = "none";
            } else {
              el.style.display = "block";
            }
          });
          doctorSelect.value = "";
          document.getElementById('docFees').value = "";
        };
      }

      // Auto-update Consultancy Fees based on selected Doctor
      var docSelect = document.getElementById('doctor');
      if (docSelect) {
        docSelect.onchange = function() {
          var selectedOption = this.options[this.selectedIndex];
          var fees = selectedOption.getAttribute('data-value') || '';
          document.getElementById('docFees').value = fees;
        };
      }
    </script>
  </body>
</html>