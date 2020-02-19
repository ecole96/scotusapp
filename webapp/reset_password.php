<?php
    function verifyToken($token,$connect, &$errs) {
        $is_valid = true;
        if(empty($token)) {
            $is_valid = false;
            array_push($errs,"\"Token\" field is empty.");
        }
        else {
            $sql = "SELECT expiration,tokenid FROM password_tokens WHERE token = '$token'";
            $query = mysqli_query($connect, $sql) or die(mysqli_connect_error());
            $row = mysqli_fetch_assoc($query);
            if (!$row) { // token does not exist in database
                array_push($errs,"Token is invalid.");
                $is_valid = false;
            }
            else if(time() > $row['expiration']) { // user is trying to use token an hour past generation
                array_push($errs,"Token has expired - you must request another one.");
                $is_valid = false;
            }
        }
        return $is_valid;
    }

    function verifyPassword($password,$confirm_password,&$errs) {
        $is_valid = true;
        if(empty($password) || empty($confirm_password)) {
            array_push($errs,"\"Password\" and/or \"Confirm Password\" fields are empty.");
            $is_valid = false;
        }
        else if ($password != $confirm_password) {
            array_push($errs,"\"Password\" and \"Confirm Password\" fields do not match.");
            $is_valid = false;
        }
        else {
            if(strlen($password) < 8) {
                array_push($errs,"Password is shorter than 8 characters.");
                $is_valid = false;
            }
            if(preg_match('/[\x00-\x1F\x7F]/',$password)) {
                array_push($errs,"Invalid characters found in password.");
                $is_valid = false;
            }
        }
        return $is_valid;
    }

    // get user id associated with a token
    function getUserID($token,$connect) { 
        $sql = "SELECT idUser FROM password_tokens where token='$token'";
        $query = mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $row = mysqli_fetch_assoc($query);
        return $row['idUser'];
    }

    function resetPassword($password,$idUser,$connect) {
        $password_hash = mysqli_real_escape_string($connect,password_hash($password,PASSWORD_DEFAULT));
        $sql = "UPDATE user SET password_hash='$password_hash' WHERE idUser=$idUser"; // update password hash
        mysqli_query($connect, $sql) or die(mysqli_connect_error());
    }

    // once user's password is reset, delete all tokens associated with the user
    function cleanup($idUser,$connect) {
        $sql = "DELETE FROM password_tokens WHERE idUser=$idUser"; 
        mysqli_query($connect, $sql) or die(mysqli_connect_error());
    }
    
    include_once("db_connect.php");
    include("admins.php");
    $validReset = false;
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $token = isset($_POST['token']) ? trim($_POST['token']) : "";
        $password=isset($_POST['password']) ? $_POST['password'] : "";
        $confirm_password=isset($_POST['confirm_password']) ? $_POST['confirm_password'] : "";
        $token = mysqli_real_escape_string($connect,$token);
        $password = mysqli_real_escape_string($connect,$password);
        $confirm_password = mysqli_real_escape_string($connect,$confirm_password);
        $errs = array();

        $validReset = verifyToken($token,$connect,$errs) && verifyPassword($password,$confirm_password,$errs);
        if($validReset) {
            $idUser = getUserID($token,$connect);
            resetPassword($password,$idUser,$connect);
            cleanup($idUser,$connect);
            unset($token,$password,$confirm_password); // clear fields upon success
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
        <h2 style="font-size: 30px; font-weight: bold; text-align:center;">Reset Password</h2><br>
        <form method="post" action="" style="margin:0 auto;width:30%;">
            <p>Enter your password reset code and desired new password.</p>
            <fieldset class="form-group">
                <?php
                    if(isset($errs) && sizeof($errs) > 0) {
                        echo "<div style='color:red'>The following errors were found:<ul>";
                        foreach($errs as $err) {
                            echo "<li>" . $err . "</li>";
                        }
                        echo "</ul>Please correct these errors and resubmit.</div><br>";
                    }
                    else if($validReset) {
                        echo "<p style='color:green'>Your password is reset - log in with your new credentials.</p>";
                    }
                ?>
                Token
                <input class='form-control' type="text" name="token"<?php if(isset($token)) echo "value='$token'";?>><br>
                Password (at least 8 characters, no "control characters")
                <input class='form-control' type="password" name="password"><br>
                Confirm Password
                <input class = 'form-control' type="password" name="confirm_password"><br>
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