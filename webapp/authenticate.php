<?php
    // this script handles user authentication - making sure users have the proper authority to view specific pages on the webapp
    session_start();
    $logged_in = isset($_SESSION['authority']) && $_SESSION['authority'] > 0 && isset($_SESSION['email']) && isset($_SESSION['idUser']) && isset($_SESSION['name']);
    $allowAccess = true;
    $showError = false;
    $currentPage = explode('?',basename($_SERVER['REQUEST_URI']))[0];
    if(!$logged_in && $currentPage != "login.php") { // if not logged in, kick user to the login page first
        $allowAccess = false;
        $redirect = (basename($_SERVER['REQUEST_URI']) == "webapp") ? "index.php" : basename($_SERVER['REQUEST_URI']); // set proper redirect of /webapp/ to index.php
        $_SESSION['redirectBackTo'] = $redirect;  // ...but remember where they were going before logging in so we can redirect them to it after login
        $destination = "login.php";
    }
    else if($logged_in && $currentPage == "login.php") { // don't allow users already logged in to visit login page
        $allowAccess = false;
        $destination = "index.php";
    }
    else if($currentPage == "verify_user.php" && $_SESSION['authority'] != 2) { // verify users have admin status (authority code 2) for them to view 
        $allowAccess = false;
        $showError = true; // display access denied page
        $msg = "Administrator status is required.";
    }

    if(!$allowAccess) {
        if($showError) {
            $msg = "You don't have access to this page: " . $msg;
            $html = 
                            "<!DOCTYPE html>
                            <html>
                                <head>
                                    <meta charset='utf-8'>
                                    <title>SCOTUSApp - Access Denied</title>
                                </head>
                                <body>
                                    <h1>Access Denied</h1>
                                    <p>" . $msg . "</p>
                                </body>
                            </html>";
            echo $html;
        }
        else {
            header("Location: $destination");
        }
        exit();
    }
?>