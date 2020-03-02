<?php
    // does the same SQL search query as the one in search.php for any given search, but outputs the rows to a .csv + article text in .txt
    // everything is stored inside a .zip file

    include_once("authenticate.php");
    include_once("db_connect.php");
    include("buildQuery.php");

    ini_set('memory_limit','1G'); // large downloads were hitting some memory usage limit once keywords were added to the CSV - upped it here (will likely need to increase with size of the database)
    ignore_user_abort(true); // still delete temp files if user cancels download
    set_time_limit(300);

    $search_query = (!empty($_GET['search_query']) ? trim($_GET['search_query']) : '');
    $dateFrom = (!empty($_GET['dateFrom']) ? $_GET['dateFrom'] : '');
    $dateTo = (!empty($_GET['dateTo']) ? $_GET['dateTo'] : '');
    $source_search = (!empty($_GET['source_search']) ? trim($_GET['source_search']) : '');
    $ID_search = (!empty($_GET['ID_search']) ? trim($_GET['ID_search']) : '');
    $sourcebox = (!empty($_GET['sourcebox']) ? $_GET['sourcebox'] : '');

    $sql = buildQuery($connect,$search_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,"download");
    // settings user variables necessary for calculating an article's Alt ID
    mysqli_query($connect,"SET @n=0");
    mysqli_query($connect,"SET @pubdate=''");
    $query = mysqli_query($connect, $sql) or die(mysqli_connect_error()); // execute query

    // Download article data into a .zip file consisting of a single .csv file with all of the search results + individual .txt files for each article's content
    $download_id = uniqid(); // download identifier used in zip and csv filenames to differentiate one download instance from another (in case of simultaneous downloads from multiple users)
    $zipName = "articles_" . $download_id . ".zip";
    $zip = new ZipArchive(); // create a zip file
    if ($zip->open($zipName, ZipArchive::CREATE) && $query)
    {
        $csvName = "article_data_" . $download_id . ".csv";
        $csv = fopen($csvName, 'w') or die ("Unable to generate CSV: " . $csvName);

        // CSV column headers
        $headers = array("Article ID", "Alt ID", "Date/Time", "Source", "MBFS Bias","MBFS Score","MBFS Factual Reporting", "AllSides Bias","AllSides Confidence","AllSides Agreement","AllSides Disagreement","URL","Title","Author","Relevancy Score","Sentiment Score","Sentiment Magnitude","Top Image Entity","Entity Score","Keywords");
        $metric_headers = array("FB Reactions"=>"fb_reactions","FB Comments"=>"fb_comments","FB Shares"=>"fb_shares","FB Comment Plugin"=>"fb_comment_plugin", 
                         "TW Tweets"=>"tw_tweets","TW Total Favorites"=>"tw_favorites","TW Total Retweets"=>"tw_retweets","TW Top Favorites"=>"tw_top_favorites","TW Top Retweets"=>"tw_top_retweets",
                         "RDT Posts"=>"rdt_posts","RDT Total Comments"=>"rdt_total_comments","RDT Total Scores"=>"rdt_total_scores","RDT Top Comments"=>"rdt_top_comments",
                         "RDT Top Score"=>"rdt_top_score","RDT Top Ratio"=>"rdt_top_ratio","RDT Average Ratio"=>"rdt_avg_ratio");
        foreach($metric_headers as $header=>$colname) { // dynamically adding social media metric headers
            array_push($headers,"$header - Initial Entry"); array_push($headers,"$header - Day 1"); array_push($headers,"$header - Day 7"); 
        }
        fputcsv($csv, $headers,"\t");
        // build files to go into zip
        $txt_path = "../txtfiles/"; // where all txt files are stored
        while ($row = mysqli_fetch_assoc($query)) {
            $altID = $row['date'] . '_' . sprintf("%03d",$row['n']); // alternate ID as requested in Y-m-d_n format, where n is the Nth article of its publishing date (sorted by idArticle ascending) [n is 3 digits, with leading zeroes as necessary]
            $data = array($row['idArticle'],$altID,$row['datetime'], $row['source'],$row['mbfs_bias'],$row['mbfs_score'],$row['factual_reporting'],$row['allsides_bias'],$row['allsides_confidence'],$row['allsides_agree'],
                        $row['allsides_disagree'],  $row['url'], $row['title'], $row['author'], $row['relevancy_score'],$row['score'],$row['magnitude'],
                        $row['top_entity'],$row['top_entity_score'],$row['keywords']);
            foreach($metric_headers as $header=>$colname) { // dynamically adding social media metric data
                array_push($data,$row["$colname" . "_initial"]); array_push($data,$row["$colname" . "_d1"]); array_push($data,$row["$colname" . "_d7"]); 
            }
            fputcsv($csv, $data,"\t"); // insert row in CSV (tab delimiter necessary for Excel compatibility fix)
            $txtName = $row['idArticle'] . ".txt"; // get {idArticle}.txt file from /txtfiles/ folder
            if (file_exists($txt_path . $txtName)) {
                $zip->addFile($txt_path . $txtName, $txtName); // add .txt to zip
            }
        }
        fclose($csv); // CSV finished - all rows inserted

        // the CSV is by default in UTF-8 encoding, which causes some scrambled characters in Excel (like apostrophes), so we convert it to UTF-16LE to fix this
        $data = file_get_contents($csvName); 
        $data = chr(255) . chr(254) . mb_convert_encoding($data, 'UTF-16LE','UTF-8');
        file_put_contents($csvName, $data);

        $zip->addFile($csvName,$csvName); // add completed CSV to zip
        $zip->close(); // finish zip

        ob_end_clean(); // clean output buffer and stop buffering - large downloads are very spotty otherwise

        // set headers to allow for sending the .zip to user
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=$zipName");
        header("Content-Length: " . filesize($zipName));

        unlink($csvName); // delete csv (no longer needed now that it's in the zip archive)
        readfile($zipName); // download zip
        unlink($zipName); // delete zip from server
    }
    else
    {
        echo "ERROR: Couldn't download file!";
    }
?>