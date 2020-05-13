<?php
    function verifyName($name,$oldname,&$errs) {
        if (empty($name)) {
            array_push($errs,"\"Name\" field is empty.");
            return false;
        }
        else if ($name == $oldname) { // no need to update if field is unchanged from the user's current info
            return false;
        }
        return true;
    }

    function verifyPassword($password,$confirm_password,&$errs) {
        $is_valid = true;
        if(empty($password) || empty($confirm_password)) {
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

    function verifyEmail($email,$oldemail,$connect,&$errs) {
        $is_valid = true;
        if(empty($email)) {
            array_push($errs,"\"Email\" field is empty.");
            $is_valid = false;
        }
        else if($email == $oldemail) {
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

    function duplicateEmail($email,$connect) {
        $email = mysqli_real_escape_string($connect,$email);
        $sql = "SELECT idUser FROM user WHERE email='{$email}'";
        $result = mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $rowcount = mysqli_num_rows($result);
        return $rowcount != 0;
    }

    // update user information for each field changed
    function updateAccount($data,$field_changed,$idUser,$connect) {
        $data = mysqli_real_escape_string($connect,$data);
        if($field_changed == 'password_hash') {
            $data = mysqli_real_escape_string($connect,password_hash($data,PASSWORD_DEFAULT));
        }
        else if(in_array($field_changed,array("name","email"))) {
            $_SESSION[$field_changed] = $data;
        }
        $sql = ($field_changed == 'notes' && empty($data)) ?  "UPDATE user SET notes=NULL WHERE idUser=$idUser" : "UPDATE user SET $field_changed='$data' WHERE idUser=$idUser";
        mysqli_query($connect, $sql) or die(mysqli_connect_error());
    }

    include_once("authenticate.php");
    include_once("db_connect.php");
    include("admins.php");

    $sql = "SELECT * from user WHERE idUser={$_SESSION['idUser']}"; // load initial user information for currently logged in user
    $query = mysqli_query($connect, $sql) or die(mysqli_connect_error());
    $userinfo = mysqli_fetch_assoc($query);
    $oldname = $userinfo['name'];
    $oldemail = $userinfo['email'];
    $oldnotes = !empty($userinfo['notes']) ? $userinfo['notes'] : "";

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name=isset($_POST['name']) ? trim($_POST['name']) : $oldname;
        $email = isset($_POST['email']) ? trim($_POST['email']) : $oldemail;
        $password=isset($_POST['password']) ? $_POST['password'] : "";
        $confirm_password=isset($_POST['confirm_password']) ? $_POST['confirm_password'] : "";
        $notes=isset($_POST['notes']) ? trim($_POST['notes']) : $oldnotes;

        $errs = array();
        $fields_changed = array(); // keeps track of what fields changed (shown in confirmation message)

        // update fields one by one (and updating text fields where necessary)
        if(verifyName($name,$oldname,$errs)) { updateAccount($name,"name",$_SESSION['idUser'],$connect); array_push($fields_changed,"Name"); $oldname = $name; } 
        if(verifyEmail($email,$oldemail,$connect,$errs)) { updateAccount($email,"email",$_SESSION['idUser'],$connect); array_push($fields_changed,"Email"); $oldemail = $email; }
        if(verifyPassword($password,$confirm_password,$errs)) { updateAccount($password,"password_hash",$_SESSION['idUser'],$connect); array_push($fields_changed,"Password"); }
        if($notes != $oldnotes) { updateAccount($notes,"notes",$_SESSION['idUser'],$connect); array_push($fields_changed,"Notes"); $oldnotes = $notes; }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>SCOTUSApp - Update Account</title>
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
        </div>
        <div style="float:right; margin-right:1.5%;font-size: 18px; font-family: monospace;">
            <a style="color:black;" href="user_page.php"><?php echo $_SESSION['name']?></a> |
            <?php if($_SESSION['authority'] == 2) { echo "<a style='color:black' href='user_log.php'>User Log</a> | "; } ?>
            <a style="color:black;" href="logout.php">Logout</a>
        </div><br>
        <h1 style="text-align: center; font-size: 50px; font-weight: bold;"><a href='index.php' style='color:black;'>SCOTUSApp</a></h1><hr style="background-color:#fffacd;">
        <h2 style="font-size: 30px; font-weight: bold; text-align:center;">Update Account</h2><br>
        <form method="post" action="" style="margin:0 auto;width:30%;">
            <p>If you need to change any of your account information, do so below. Leave fields as-is if you don't want to change anything.</p>
            <fieldset class="form-group">
                <?php
                    if(isset($fields_changed) && sizeof($fields_changed) > 0) {
                        echo "<div style='color:green'>The following fields have been updated:<ul>";
                        foreach($fields_changed as $field) {
                            echo "<li>" . $field . "</li>";
                        }
                        echo "</ul></div><br>";
                    }
                    if(isset($errs) && sizeof($errs) > 0) {
                        echo "<div style='color:red'>Failed to update certain fields. The following errors were found:<ul>";
                        foreach($errs as $err) {
                            echo "<li>" . $err . "</li>";
                        }
                        echo "</ul>Please correct these errors and resubmit.</div><br>";
                    }
                ?>
                * = Required<br><br>
                Name *
                <input class ='form-control' type="text" name="name" <?php if(isset($oldname)) echo "value='$oldname'";?>><br>
                Email (must end in .edu) *
                <input class='form-control' type="text" name="email" <?php if(isset($oldemail)) echo "value='$oldemail'";?>><br>
                New Password * (at least 8 characters, no "control characters")
                <input class='form-control' type="password" name="password"><br> <!-- password fields intentionally left blank -->
                Confirm New Password *
                <input class = 'form-control' type="password" name="confirm_password"><br>
                Notes (why you want to use SCOTUSApp, any necessary information, etc. - 1000 characters max) 
                <textarea class = 'form-control' rows="15" cols="60" maxlength="1000" name="notes"><?php if(isset($oldnotes)) echo $oldnotes;?></textarea><br>
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