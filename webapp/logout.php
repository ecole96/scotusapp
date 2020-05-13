<?php
    // log out by unsetting the SESSION variables
    session_start();
    include_once("db_connect.php");
    mysqli_query($connect,"UPDATE user_log SET time_out=NOW() WHERE idLog=(SELECT MAX(idLog) FROM user_log WHERE idUser={$_SESSION['idUser']})");
    unset($_SESSION['email'],$_SESSION['name'],$_SESSION['idUser'],$_SESSION['authority']);
    header("Location: login.php");
    exit();
?>