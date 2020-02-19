<?php
    session_start();
    include_once("db_connect.php");
    $msg = "";
    $logged_in = isset($_SESSION['authority']) && $_SESSION['authority'] > 0 && isset($_SESSION['email']) && isset($_SESSION['idUser']) && isset($_SESSION['name']);
    if (!$logged_in) {
        $msg = "Error: You are not logged in.";
    }
    else if (empty($connect)) {
        $msg = "Error: Could not connect to database.";
    }
    else if (empty($_POST['idArticle'])) {
        $msg = "Error: Invalid article ID.";
    }
    else {
        $idArticle = mysqli_real_escape_string($connect,$_POST['idArticle']);
        $sql = "UPDATE article SET marked_irrelevant=True WHERE idArticle = '$idArticle'";
        $query = mysqli_query($connect, $sql);
        $msg = $query ? "Your recommendation has been noted." : "Error: Database query failed.";
    }
    echo $msg;
?>