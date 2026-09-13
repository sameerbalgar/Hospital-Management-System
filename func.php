<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

// Handle Patient Login (Accepts Email, Name, or Contact Number)
if (isset($_POST['patsub'])) {
    $login_input = mysqli_real_escape_string($con, trim($_POST['email'] ?? $_POST['username'] ?? $_POST['user'] ?? ''));
    $raw_pass    = $_POST['password'] ?? $_POST['password2'] ?? '';
    $password    = mysqli_real_escape_string($con, trim($raw_pass));

    // Checks against email, fname, contact, or full name
    $query  = "SELECT * FROM patreg WHERE (email='$login_input' OR fname='$login_input' OR contact='$login_input' OR CONCAT(fname, ' ', lname)='$login_input') AND password='$password'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $_SESSION['pid']      = $row['pid'];
        $_SESSION['username'] = $row['fname'] . " " . $row['lname'];
        $_SESSION['fname']    = $row['fname'];
        $_SESSION['lname']    = $row['lname'];
        $_SESSION['gender']   = $row['gender'];
        $_SESSION['contact']  = $row['contact'];
        $_SESSION['email']    = $row['email'];

        header("Location: admin-panel.php");
        exit();
    } else {
        echo "<script>alert('Invalid Username or Password. Try Again!'); window.location.href = 'index1.php';</script>";
        exit();
    }
}
// Handle Payment Status Update
if (isset($_POST['update_data'])) {
    $contact = mysqli_real_escape_string($con, $_POST['contact'] ?? '');
    $status  = mysqli_real_escape_string($con, $_POST['status'] ?? '');
    $query   = "UPDATE appointmenttb SET payment='$status' WHERE contact='$contact'";
    $result  = mysqli_query($con, $query);
    if ($result) {
        header("Location: updated.php");
        exit();
    }
}

// Helper to list doctors in dropdown
if (!function_exists('display_docs')) {
    function display_docs() {
        global $con;
        $query = "SELECT * FROM doctb";
        $result = mysqli_query($con, $query);
        if ($result) {
            while ($row = mysqli_fetch_array($result)) {
                $name = htmlspecialchars($row['username']);
                $cost = htmlspecialchars($row['docFees'] ?? '500');
                $spec = htmlspecialchars($row['spec'] ?? '');
                echo '<option value="' . $name . '" data-value="' . $cost . '" data-spec="' . $spec . '">' . $name . '</option>';
            }
        }
    }
}

// Helper to list specializations in dropdown
if (!function_exists('display_specs')) {
    function display_specs() {
        global $con;
        $query = "SELECT DISTINCT spec FROM doctb WHERE spec IS NOT NULL AND spec != ''";
        $result = mysqli_query($con, $query);
        if ($result) {
            while ($row = mysqli_fetch_array($result)) {
                $spec = htmlspecialchars($row['spec']);
                echo '<option value="' . $spec . '">' . $spec . '</option>';
            }
        }
    }
}

// Handle Adding Doctors
if (isset($_POST['doc_sub'])) {
    $doctor    = mysqli_real_escape_string($con, $_POST['doctor'] ?? '');
    $dpassword = mysqli_real_escape_string($con, $_POST['dpassword'] ?? '');
    $demail    = mysqli_real_escape_string($con, $_POST['demail'] ?? '');
    $docFees   = mysqli_real_escape_string($con, $_POST['docFees'] ?? '');
    
    $query  = "INSERT INTO doctb(username, password, email, docFees) VALUES('$doctor', '$dpassword', '$demail', '$docFees')";
    $result = mysqli_query($con, $query);
    if ($result) {
        header("Location: adddoc.php");
        exit();
    }
}
?>