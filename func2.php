<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

// Connection Check
if (!$con) {
    die("Database Connection Error: " . mysqli_connect_error());
}

if (isset($_POST['patsub1'])) {
    $fname     = mysqli_real_escape_string($con, trim($_POST['fname'] ?? ''));
    $lname     = mysqli_real_escape_string($con, trim($_POST['lname'] ?? ''));
    $gender    = mysqli_real_escape_string($con, $_POST['gender'] ?? '');
    $email     = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
    $contact   = mysqli_real_escape_string($con, trim($_POST['contact'] ?? ''));
    $password  = mysqli_real_escape_string($con, trim($_POST['password'] ?? ''));
    $cpassword = mysqli_real_escape_string($con, trim($_POST['cpassword'] ?? ''));

    // Modification 1: Validate empty required inputs
    if (empty($fname) || empty($email) || empty($password)) {
        echo "<script>alert('Please fill in all required fields!'); window.location.href = 'index.php';</script>";
        exit();
    }

    // Modification 2: Use strict equality check for passwords
    if ($password === $cpassword) {
        
        // Modification 3: Prevent duplicate email registrations
        $check_email = mysqli_query($con, "SELECT email FROM patreg WHERE email='$email'");
        if ($check_email && mysqli_num_rows($check_email) > 0) {
            echo "<script>alert('Email is already registered! Please log in.'); window.location.href = 'index1.php';</script>";
            exit();
        }

        $query  = "INSERT INTO patreg(fname, lname, gender, email, contact, password, cpassword) VALUES ('$fname', '$lname', '$gender', '$email', '$contact', '$password', '$cpassword')";
        $result = mysqli_query($con, $query);

        if ($result) {
            $pid = mysqli_insert_id($con);
            $_SESSION['pid']      = $pid;
            $_SESSION['username'] = $fname . " " . $lname;
            $_SESSION['fname']    = $fname;
            $_SESSION['lname']    = $lname;
            $_SESSION['gender']   = $gender;
            $_SESSION['contact']  = $contact;
            $_SESSION['email']    = $email;

            // Modification 4: Confirm successful registration before redirecting
            echo "<script>alert('Registration Successful! Redirecting to Dashboard...'); window.location.href = 'admin-panel.php';</script>";
            exit();
        } else {
            // Modification 5: Display precise database error if insertion fails
            $err = mysqli_error($con);
            echo "<script>alert('Registration Error: " . addslashes($err) . "'); window.location.href = 'index.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Passwords do not match!'); window.location.href = 'index.php';</script>";
        exit();
    }
}

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

if (isset($_POST['doc_sub'])) {
    $name   = mysqli_real_escape_string($con, $_POST['name'] ?? '');
    $query  = "INSERT INTO doctb(name) VALUES('$name')";
    $result = mysqli_query($con, $query);
    if ($result) {
        header("Location: adddoc.php");
        exit();
    }
}
?>