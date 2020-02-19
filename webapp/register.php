<?php
    // verify the name field is filled
    function verifyName($name,&$errs) {
        if (empty($name)) {
            array_push($errs,"\"Name\" field is empty.");
            return false;
        }
        return true;
    }

    function verifyTOS($tos,&$errs) {
        if(empty($tos) || $tos != "1") {
            array_push($errs,"You must agree to the terms of use before proceeding.");
            return false;
        }
        return true;
    }

    // verify valid password
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
            if(preg_match('/[\x00-\x1F\x7F]/',$password)) { // Unicode control characters are not allowed in a password - things like backspace and tab
                array_push($errs,"Invalid characters found in password.");
                $is_valid = false;
            }
        }
        return $is_valid;
    }

    // verify proper email input
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
        else if(!validDomain($email)) {
            array_push($errs,"Email must be a .edu address.");
            $is_valid = false;
        }
        else if(duplicateEmail($email,$connect)) {
            array_push($errs,"There is already an account associated with this email.");
            $is_valid = false;
        }
        return $is_valid;
    }

    // checks whether email domain is in our list of accepted domains (for now, only .edu)
    function validDomain($email) {
        $domain = substr(strrchr($email, "@"), 1);
        $validDomains = array("edu");
        $validDomain = false;
        foreach($validDomains as $vd) {
            $pattern = "/\S+\.$vd/";
            if(preg_match($pattern,$domain)){
                $validDomain = true;
                break;
            }
        }
        return $validDomain;
    }

    // checks whether email is already associated with another account
    function duplicateEmail($email,$connect) {
        $email = mysqli_real_escape_string($connect,$email);
        $sql = "SELECT idUser FROM user WHERE email='{$email}'";
        $result = mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $rowcount = mysqli_num_rows($result);
        return $rowcount != 0;
    }

    // insert user info into the database
    function addUser($email,$name,$password,$notes,$connect) {
        $email = mysqli_real_escape_string($connect,$email);
        $name = mysqli_real_escape_string($connect,$name);
        $notes = mysqli_real_escape_string($connect,$notes);
        $password_hash = mysqli_real_escape_string($connect,password_hash($password,PASSWORD_DEFAULT)); // we're not storing passwords in the database, but hashes of them
        if(empty($notes)) {  // no notes given on signup = notes entered into database as NULL
            $sql = "INSERT INTO user(email,name,password_hash,authority) VALUES ('$email','$name','$password_hash',0)"; } // default authority code all users upons signup is 0 (unauthorized). They cannot log in until they are authorized by an admin.
        else { 
            $sql = "INSERT INTO user(email,name,password_hash,notes,authority) VALUES ('$email','$name','$password_hash','$notes',0)"; }
        mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $idUser = mysqli_insert_id($connect);
        return $idUser;
    }

    // send user verification link to admins
    function sendVerificationEmail($email,$name,$notes,$idUser) {
        $verify_url = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/verify_user.php?idUser=" . $idUser;
        $to = getAdmins(true);
        $subject = "SCOTUSApp - New User Verification";
        $body = "A new user has signed up for SCOTUSApp. Review their information and decide whether to authorize them or not:<br><br>
                    Name: $name<br>
                    Email: $email<br>
                    User notes:<br>$notes<br><br>
                    <a href='$verify_url'>Click this link</a> to authorize this user (you may be prompted to sign into your administrator account).";
        $email = sendEmail($to,$subject,$body);
        return $email;
    }

    include_once("db_connect.php");
    include_once("email.php");
    include("admins.php");
    $valid_reg = false;
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name=isset($_POST['name']) ? trim($_POST['name']) : "";
        $email = isset($_POST['email']) ? trim($_POST['email']) : "";
        $password=isset($_POST['password']) ? ($_POST['password']) : "";
        $confirm_password=isset($_POST['confirm_password']) ? $_POST['confirm_password'] : "";
        $notes=isset($_POST['notes']) ? trim($_POST['notes']) : "";
        $tos = isset($_POST['tos']) ? $_POST['tos'] : "";
        $errs = array(); // this array keeps track of any errors found in the registration process; passed in by reference to the verification functions

        $validName = verifyName($name,$errs);
        $validEmail = verifyEmail($email,$connect,$errs);
        $validPassword = verifyPassword($password,$confirm_password,$errs);
        $validTOS = verifyTOS($tos,$errs);
        $valid_reg = $validName && $validEmail && $validPassword && $validTOS; // all conditions must be true for successful registration

        if($valid_reg) {
            $idUser = addUser($email,$name,$password,$notes,$connect);
            $email_success = sendVerificationEmail($email,$name,$notes,$idUser);
            unset($name,$email,$password,$confirm_password,$notes); // clear fields upon successful register
        }
    } 
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>SCOTUSApp - Register</title>
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
        <h2 style="font-size: 30px; font-weight: bold; text-align:center;">Register Account</h2><br>
        <form method="post" action="register.php" style="margin:0 auto;width:45%;">
            <p>SCOTUSApp can only be used by verified accounts - once you register, your information (minus password) will be sent to our administrators for vetting. Upon verification, you will be sent a success email and can begin use of SCOTUSApp.</p>
            <fieldset class="form-group">
                <?php
                    if(isset($errs) && sizeof($errs) > 0) { // errors occurred
                        echo "<div style='color:red'>Couldn't complete registration. The following errors were found:<ul>";
                        foreach($errs as $err) {
                            echo "<li>" . $err . "</li>";
                        }
                        echo "</ul>Please correct these errors and resubmit.</div><br>";
                    }
                    else if($valid_reg) {
                        if($email_success) { // all good
                            echo "<p style='color:green'>Registration was a success. When you are verified by the administrators, you will receive an email. 
                            If it's been a few days and you haven't received anything, contact the administrators (and make sure to check your junk mail).</p>";
                        }
                        else { // email failed to send
                            echo "<p style='color:red'>Your account has been registered, but the verification email failed to send to our administrators - contact them for help.</p>";
                        }
                    }
                ?>
                * = Required<br><br>
                Name *
                <input class ='form-control' type="text" name="name" <?php if(isset($name)) echo "value='$name'";?>><br>
                Email (must end in .edu) *
                <input class='form-control' type="text" name="email" <?php if(isset($email)) echo "value='$email'";?>><br>
                Password * (at least 8 characters, no "control characters")
                <input class='form-control' type="password" name="password"><br>
                Confirm Password *
                <input class = 'form-control' type="password" name="confirm_password"><br>
                Notes (why you want to use SCOTUSApp, any necessary information, etc. - 1000 characters max) 
                <textarea class = 'form-control' rows="15" cols="60" maxlength="1000" name="notes"><?php if(isset($notes)) echo $notes;?></textarea><br>
                I agree to the <a href='tos.html'>SCOTUSApp Terms of Use</a>: <input type="checkbox" name="tos" value="1"><br><br>
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