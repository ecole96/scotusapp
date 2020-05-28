<?php
    // log out by unsetting the SESSION variables
    session_start();
    include_once("db_connect.php");
    $query = mysqli_query($connect,"SELECT MAX(idLog) as latest_session FROM user_log WHERE idUser={$_SESSION['idUser']}"); // get user's latest session
    $row = mysqli_fetch_assoc($query);
    mysqli_query($connect,"UPDATE user_log SET time_out=NOW() WHERE idLog={$row['latest_session']}");
    unset($_SESSION['email'],$_SESSION['name'],$_SESSION['idUser'],$_SESSION['authority']);
    header("Location: login.php");
    exit();
?>