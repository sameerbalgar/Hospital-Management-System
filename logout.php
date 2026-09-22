<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Smart Hospital - Logged Out</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="patient-panel.css">
  </head>

  <body class="pp-auth-body">
    <div class="pp-auth-card">
      <div class="pp-auth-icon"><i class="fa fa-sign-out" aria-hidden="true"></i></div>
      <h3>You have logged out.</h3>
      <p>Thank you for visiting Smart Hospital. Your session has been safely closed.</p>
      <a href="index1.php" class="pp-btn pp-btn-primary"><i class="fa fa-user" aria-hidden="true"></i> Back to Login Page</a>
    </div>
  </body>
</html>