<?php
    // verifies that email input is proper
    function verifyEmail($email,$connect,&$errs) {
        $is_valid = true;
        if(empty($email)) {
            array_push($errs,"\"Email\" field is empty.");
            $is_valid = false;
        }
        else if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            array_push($errs,"Invalid email address detected.");
            $is_valid = false;
        }
        else {
            $sql = "SELECT idUser FROM user WHERE email='{$email}'";
            $result = mysqli_query($connect, $sql) or die(mysqli_connect_error());
            $rowcount = mysqli_num_rows($result);
            if ($rowcount == 0) {
                array_push($errs,"There is no account associated with this email.");
                $is_valid = false;
            }
        }
        return $is_valid;
    }

    // generates token for password reset
    function generateToken($idUser,$connect) {
        $length = 8;
        $token = bin2hex(random_bytes($length)); // generates random 8 digit token
        $token = mysqli_real_escape_string($connect,$token);
        $expiration = time() + 3600; // token expires in an hour
        $sql = "INSERT INTO password_tokens(token,expiration,idUser) VALUES ('$token',$expiration,$idUser)";
        mysqli_query($connect, $sql) or die(mysqli_connect_error());
        return $token;
    }

    // since we're not logged in when resetting password, we can't use SESSION variables - we've got to get the info we need from the database
    function getUserInfo($email,$connect) {
        $sql = "SELECT idUser,name FROM user WHERE email='{$email}'";
        $query = mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $row = mysqli_fetch_assoc($query);
        $row['name'] = mysqli_real_escape_string($connect,$row['name']);
        $row['idUser'] = mysqli_real_escape_string($connect,$row['idUser']);
        return $row;
    }

    // send reset token + link email
    function sendForgotEmail($email,$name,$token) {
        $reset_url = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php";
        $to = array($name=>$email);
        $subject = "Password Reset";
        $body = "Hi $name,<br><br>
                Your password reset code is: <b>$token</b><br><br>
                <a href='$reset_url'>Click this link</a> to reset your password by entering your code and new password. This code will expire in one hour - after that, you will need to repeat this process.<br><br>
                Thanks,<br>
                SCOTUSApp Team";
        $email = sendEmail($to,$subject,$body);
        return $email;
    }
    
    include_once("db_connect.php");
    include_once("email.php");
    include("admins.php");
    $validEmail = false;
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : "";
        $email = mysqli_real_escape_string($connect,$email);
        $errs = array();
        $validEmail = verifyEmail($email,$connect,$errs);
        if($validEmail) {
            $user = getUserInfo($email,$connect);
            $token = generateToken($user['idUser'],$connect);
            $email_success = sendForgotEmail($email,$user['name'],$token);
            unset($email);
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>SCOTUSApp - Forgot Password</title>
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
        <!-- jQuery library -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <!-- Latest compiled JavaScript -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>
        <script>  //***  change__But and revert__But are functions for events onmouseover and onmouseout of buttons in the webapp. When the user mouses over a button, it highlights the button, and unhighlights when leaving the button area
            function changeSubBut(){  //***
                document.getElementById("formBut").style.backgroundColor =  //***
                "#87ceeb" /*sky blue*/;  //***
            }
            function revertSubBut(){ //revert style back to original for tab2//***
                document.getElementById("formBut").style.backgroundColor =  //***
                "rgba(255, 255, 255, 0.7)" /*transparent white*/;  //***
            }
        </script>
    </head>
    <body style="height:100%; background-color: #fffacd; font-family: monospace; font-weight: bold; font-size:14px;">
        <div style='float:left; margin-left:1.5%;font-size: 18px; font-family: monospace;'>
            <?php echo contactLink(); ?> | <a href='about.html' style='color:black;'>About SCOTUSApp</a>
        </div><br>
        <h1 style="text-align: center; font-size: 50px; font-weight: bold;"><a href='index.php' style='color:black;'>SCOTUSApp</a></h1><hr style="background-color:#fffacd;">
        <h2 style="font-size: 30px; font-weight: bold; text-align:center;">Forgot Password</h2><br>
        <form method="post" action="" style="margin:0 auto;width:30%;">
            <p>Enter the email associated with your account - you'll then receive an email with a code and link to reset your password.</p>
            <fieldset class="form-group">
                <?php
                    if(isset($errs) && sizeof($errs) > 0) {
                        echo "<div style='color:red'>";
                        foreach($errs as $err) {
                            echo "<p>" . $err . "</p>";
                        }
                        echo "</div>";
                    }
                    else if($validEmail) {
                        if($email_success) {
                            echo "<p style='color:green'>You should receive a password reset email soon.</p>";
                        }
                        else {
                            echo "<p style='color:red'>Your password reset email failed to send - contact the administrators for help.</p>";
                        }
                    }
                ?>
                Email
                <input class='form-control' type="text" name="email" <?php if(isset($email)) echo "value='$email'";?>><br>
                <button id="formBut" type='submit' class='btn btn-default' onmouseover='changeSubBut()' onmouseout='revertSubBut()'
                    style = "height: 30px;
                    font-weight: bold;
                    font-family: monospace;
                    background-color: rgba(255, 255, 255, 0.45);
                    border: solid 3px;
                    border-radius: 10px;">Submit</button>
            </fieldset>
        </form>
    </body>
</html>