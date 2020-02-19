<?php
    // log out by unsetting the SESSION variables
    session_start();
    unset($_SESSION['email'],$_SESSION['name'],$_SESSION['idUser'],$_SESSION['authority']);
    header("Location: login.php");
    exit();
?>