<?php
    include_once("authenticate.php");
    include_once("db_connect.php");
    $download_id = uniqid(); // download identifier used in zip and csv filenames to differentiate one download instance from another (in case of simultaneous downloads from multiple users)
    $filename = "UserReport_$download_id.csv";
    $curdir = getcwd();
    $sql = "SELECT name,email,time_in,IFNULL(time_out,'')
            FROM user INNER JOIN user_log
            ON user.idUser = user_log.idUser
            ORDER BY idLog DESC
            INTO OUTFILE '$curdir/$filename'
            FIELDS TERMINATED BY ',' 
            OPTIONALLY ENCLOSED BY '\\\"' ESCAPED BY '\"'
            LINES TERMINATED BY '\\n'";
    $query = mysqli_query($connect,$sql);
    mysqli_close($connect);
    if($query && file_exists($filename)) {
        $header_str = "\"Name\",\"Email\",\"Time In\",\"Time Out\"\n";
        $data = $header_str . file_get_contents($filename);
        file_put_contents($filename,$data);
        unset($data);
        ob_end_clean();
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Length: " . filesize($filename));
        readfile($filename);
        unlink($filename);
    }
    else {
        echo "Download failed.";
    }
?>