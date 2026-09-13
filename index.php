<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Smart Hospital Management System</title>
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <link rel="stylesheet" type="text/css" href="style1.css">
    <link rel="stylesheet" type="text/css" href="style2.css">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">

    <script>
        var check = function() {
            var pass = document.getElementById('password').value;
            var cpass = document.getElementById('cpassword').value;
            var msg = document.getElementById('message');
            if (pass === cpass) {
                msg.style.color = '#5dd05d';
                msg.innerHTML = 'Matched';
            } else {
                msg.style.color = '#f55252';
                msg.innerHTML = 'Not Matching';
            }
        };

        function alphaOnly(event) {
            var key = event.keyCode;
            return ((key >= 65 && key <= 90) || key == 8 || key == 32);
        }

        function checklen() {
            var pass1 = document.getElementById("password");  
            if (pass1.value.length < 6) {  
                alert("Password must be at least 6 characters long. Try again!");  
                return false;  
            }  
        }
    </script>
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
    <div class="container">
      <a class="navbar-brand js-scroll-trigger" href="#">
        <h4><i class="fa fa-hospital-o" aria-hidden="true"></i> &nbsp; Smart Hospital Management System</h4>
      </a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item" style="margin-right: 40px;">
            <a class="nav-link js-scroll-trigger" href="index.php"><h6>HOME</h6></a>
          </li>
          <li class="nav-item" style="margin-right: 40px;">
            <a class="nav-link js-scroll-trigger" href="services.html"><h6>ABOUT US</h6></a>
          </li>
          <li class="nav-item">
            <a class="nav-link js-scroll-trigger" href="contact.html"><h6>CONTACT</h6></a>
          </li>
        </ul>
      </div>
    </div>
</nav>

<div class="container register">
    <div class="row">
        <div class="col-md-3 register-left">
            <i class="fa fa-plus-square" aria-hidden="true" style="font-size:44px;"></i>
            <h3>Welcome to Smart Hospital</h3>
        </div>
        <div class="col-md-9 register-right">
            <ul class="nav nav-tabs nav-justified" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab">Patient</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Doctor</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab">Receptionist</a>
                </li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <!-- Patient Registration Tab -->
                <div class="tab-pane fade show active" id="home" role="tabpanel">
                    <h3 class="register-heading">Register as Patient</h3>
                    <form method="post" action="func2.php">
                        <div class="row register-form">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="First Name *" name="fname" onkeydown="return alphaOnly(event);" required/>
                                </div>
                                <div class="form-group">
                                    <input type="email" class="form-control" placeholder="Your Email *" name="email" required/>
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control" placeholder="Password *" id="password" name="password" onkeyup="check();" required/>
                                </div>
                                <div class="form-group">
                                    <div class="maxl">
                                        <label class="radio inline"> 
                                            <input type="radio" name="gender" value="Male" checked>
                                            <span> Male </span> 
                                        </label>
                                        <label class="radio inline"> 
                                            <input type="radio" name="gender" value="Female">
                                            <span> Female </span> 
                                        </label>
                                    </div>
                                    <a href="index1.php">Already have an account?</a>
                                </div>
                            </div>
                        
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="Last Name *" name="lname" onkeydown="return alphaOnly(event);" required/>
                                </div>
                                <div class="form-group">
                                    <input type="tel" minlength="10" maxlength="10" name="contact" class="form-control" placeholder="Your Phone *" required/>
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control" id="cpassword" placeholder="Confirm Password *" name="cpassword" onkeyup="check();" required/>
                                    <span id="message"></span>
                                </div>
                                <input type="submit" class="btnRegister" name="patsub1" onclick="return checklen();" value="Register"/>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Doctor Login Tab -->
                <div class="tab-pane fade" id="profile" role="tabpanel">
                    <h3 class="register-heading">Login as Doctor</h3>
                    <form method="post" action="func1.php">
                        <div class="row register-form">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="User Name *" name="username3" onkeydown="return alphaOnly(event);" required/>
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control" placeholder="Password *" name="password3" required/>
                                </div>
                                <input type="submit" class="btnRegister" name="docsub1" value="Login"/>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Admin/Receptionist Login Tab -->
                <div class="tab-pane fade" id="admin" role="tabpanel">
                    <h3 class="register-heading">Login as Admin</h3>
                    <form method="post" action="func3.php">
                        <div class="row register-form">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="User Name *" name="username1" onkeydown="return alphaOnly(event);" required/>
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control" placeholder="Password *" name="password2" required/>
                                </div>
                                <input type="submit" class="btnRegister" name="adsub" value="Login"/>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>