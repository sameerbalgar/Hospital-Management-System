<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

if (isset($_POST['docsub1'])) {
    $dname = mysqli_real_escape_string($con, trim($_POST['username3'] ?? ''));
    $dpass = mysqli_real_escape_string($con, trim($_POST['password3'] ?? ''));

    $query  = "SELECT * FROM doctb WHERE username='$dname' AND password='$dpass'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $_SESSION['dname'] = $row['username'];
        header("Location: doctor-panel.php");
        exit();
    } else {
        echo "<script>alert('Invalid Username or Password. Try Again!'); window.location.href = 'index.php';</script>";
        exit();
    }
}
?>