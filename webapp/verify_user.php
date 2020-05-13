<?php
    // when a new user signs up, an authorization email containing the user's information (name, email, notes) is sent to the administrators for vetting. 
    // Once they click on the link contained in that email, the user is authorized.
    // In the database, each user has an authorization code. 0 = unauthorized, 1 = authorized for normal use, 2 = authorized for admin use (able to confirm new users)
    // This script sets a new user's auth code to 1. It can only be executed by an admin (a user with an auth code of 2). 

    function authenticateUser($idUser,$auth,$db) {
        if($auth == 1) {
            $sql = "UPDATE user SET authority=1 WHERE idUser=$idUser"; // update user's auth code
            mysqli_query($db,$sql);
        }
        else {
            $sql = "DELETE FROM user WHERE idUser=$idUser";
            mysqli_query($db,$sql);
        }
    }

    function sendAuthEmail($email,$name,$auth,$admin_notes) {
        $to = array($name=>$email);
        $body = "Hi $name,<br><br>";
        if($auth == 1) {
            $subject = "SCOTUSApp - User Authorized";
            $login_url = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
            $body .= "Our administrators have now authorized you to use SCOTUSApp. <a href='$login_url'>Click this link</a> to login and begin use. If you have any questions, please contact us via the contact link on the app's homepage.<br><br>";
        }
        else {
            $subject = "SCOTUSApp - User Declined";
            $body = "Unfortunately, our administrators have declined your request to use SCOTUSApp. Your account has been deleted, but you are welcome to register again. If you have any questions, please contact us via the contact link on the app's homepage.<br><br>";
        }
        $body .= (!empty($admin_notes) ? "Notes from the admins:<br>$admin_notes<br><br>" : "") . "Thanks,<br>SCOTUSApp Team";
        $email = sendEmail($to,$subject,$body);
        return $email;
    }

    include_once("authenticate.php"); // auth script verifies user is logged in and has admin privileges
    include_once("email.php");
    include("admins.php");

    $displayForm = true;
    if(!isset($_GET['idUser'])) {
        $msg = "No user id provided.";
        $displayForm = false;
    }
    else {
        include_once("db_connect.php");
        $idUser = $_GET['idUser'];
        $idUser = mysqli_real_escape_string($connect,(int)$idUser);
        $sql = "SELECT email,name,idUser,authority,notes FROM user WHERE idUser=$idUser LIMIT 1";
        $result = mysqli_query($connect, $sql) or die(mysqli_connect_error());
        $row = mysqli_fetch_assoc($result);
        if(!$row) { $msg = "Invalid user id."; $displayForm = false; }
        else if($row['authority'] != 0) { $msg = "User has already been authorized."; $displayForm = false; }
        else {
            if($_SERVER['REQUEST_METHOD'] == 'POST') {
                if(!isset($_POST['auth']) || !in_array($_POST['auth'],array(0,1))) {
                    $msg = "You must choose to either accept or decline this user.";
                }
                else {
                    authenticateUser($idUser,$_POST['auth'],$connect);
                    $email_success = sendAuthEmail($row['email'],$row['name'],$_POST['auth'],$_POST['admin_notes']);
                    if($email_success) {
                        $msg = $_POST['auth'] == 1 ? "This user has now been authorized - they should receive a confirmation email momentarily." 
                        : "This user has been rejected - they should receive a notification email momentarily. Their account has also been deleted to conserve space (they can re-register, however).";
                    }
                    else {
                        $msg = "Authorization/rejection email failed to send. You may need to contact them directly at {$row['email']}.";
                    }
                    $displayForm = false;
                }
            }
            
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>SCOTUSApp - Verify User</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
        <style>
            label {font-size:16px;}
        </style>
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
    <body style="height:100%; background-color: #fffacd; font-family: monospace; font-weight: bold; font-size: 14px;">
        <div style='float:left; margin-left:1.5%;font-size: 18px; font-family: monospace;'>
            <?php echo contactLink(); ?> | <a href='about.html' style='color:black;'>About SCOTUSApp</a>
        </div>
        <div style="float:right; margin-right:1.5%;font-size: 18px; font-family: monospace;">
            <a style="color:black;" href="user_page.php"><?php echo $_SESSION['name']?></a> |
            <?php if($_SESSION['authority'] == 2) { echo "<a style='color:black' href='user_log.php'>User Log</a> | "; } ?>
            <a style="color:black;" href="logout.php">Logout</a>
        </div><br>
        <h1 style="text-align: center; font-size: 50px; font-weight: bold;"><a href='index.php' style='color:black;'>SCOTUSApp</a></h1><hr style="background-color:#fffacd;">
        <h2 style="font-size: 30px; font-weight: bold; text-align:center;">Verify User</h2><br>
        <div class='container' style='margin: 0 auto; width:45%'>
        <?php
            //<input type='hidden' name='idUser' value='$idUser'>
            if(isset($msg)) { echo "<p style='text-align:center'>$msg</p>";}
            if(!empty($row) && $displayForm) {
                $html = "<form method='post' action=''>
                            <div class='form-group row'>
                                <label class='col-md-2 col-form-label'>Name</label>
                                <div class='col-md-10'>{$row['name']}</div>
                            </div>
                            <div class='form-group row'>
                                <label class='col-md-2 col-form-label'>Email</label>
                                <div class='col-md-10'><a href='mailto:{$row['email']}'>{$row['email']}</a></div>
                            </div>
                            <div class='form-group row'>
                                <label class='col-md-2 col-form-label'>User Notes</label>
                                <div class='col-md-10'>" . (!empty($row['notes']) ? $row['notes'] : "None") . "</div>
                            </div>
                            <div class='form-group row' style='font-size:18px;'>
                                <div class='col-md-offset-5'>
                                    <div class='radio-inline'>
                                        <input class='form-check-input' type='radio' name='auth' id='accept' value='1'>
                                        <label class='form-check-label' for='accept'>Accept</label>
                                    </div>
                                    <div class='radio-inline'>
                                        <input class='form-check-input' type='radio' name='auth' id='decline' value='0'>
                                        <label class='form-check-label' for='decline'>Decline</label>
                                    </div>
                                </div>
                            </div>
                            <div class='form-group row'>
                                <label class='col-md-2 col-form-label'>Admin Notes</label>
                                <div class='col-md-10'>
                                    <textarea class='form-control' name='admin_notes' rows='10' maxlength='1000' placeholder='This will be emailed to the user (optional, 1000 characters max)'>"
                                    . (!empty($_POST['admin_notes']) ? $_POST['admin_notes'] : "") .
                                    "</textarea>
                                </div>
                            </div>
                            <div class='form-group row'>
                                <div class='col-md-offset-6'>
                                    <button id='formBut' type='submit' class='btn btn-default' onmouseover='changeSubBut()' onmouseout='revertSubBut()'
                                    style = 'height: 30px;
                                    font-weight: bold;
                                    font-family: monospace;
                                    background-color: rgba(255, 255, 255, 0.45);
                                    border: solid 3px;
                                    border-radius: 10px;'>Submit</button>
                                </div>
                            </div>
                        </form>";
                echo $html;
            }
        ?>
        </div>
    </body>
</html>