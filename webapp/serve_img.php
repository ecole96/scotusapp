<?php
    // this file serves images to users on the article display page
    // for images to be loaded directly, the images directory has to be publicly accessible
    // we don't want that, so we use this script to indirectly do so
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
    else if (empty($_GET['idImage'])) {
        $msg = "Error: No image ID provided.";
    }
    else {
        $idImage = mysqli_real_escape_string($connect,$_GET['idImage']);
        $images_sql = "SELECT path,url FROM image WHERE idImage='$idImage' LIMIT 1";
        $query = mysqli_query($connect, $images_sql);
        if($query) {
            if(mysqli_num_rows($query) != 1) {
                $msg = "Error: Invalid image ID.";
            }
            else {
                $result = mysqli_fetch_assoc($query);
                $dir = dirname(__DIR__) . '/images/';
                $filename = $result['path'];
                if(file_exists($dir . $filename)) {
                    $img = file_get_contents($dir . $filename);
                }
                else if(!empty($url = $result['url'])) { // if filesystem image doesn't exist, try to load from the image's original URL
                    $img = file_get_contents($url);
                }
            }
        }
        else {
            $msg = "Error: database query failed.";
        }
    }
    if(isset($img)) {
        header("Content-Type:image/jpeg");
        echo $img;
        echo $dir . $filename;
    }
    else if(!empty($msg)) {
        echo $msg;
    }
?>