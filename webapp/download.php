<?php
    // does the same SQL search query as the one in search.php for any given search, but outputs the rows to a .csv + article text in .txt
    // everything is stored inside a .zip file
    include_once("authenticate.php");
    include_once("db_connect.php");
    include("buildQuery.php");

    ini_set('memory_limit','250M'); // large downloads were hitting some memory usage limit once keywords were added to the CSV - upped it here (will likely need to increase with size of the database)
    ignore_user_abort(true); // still delete temp files if user cancels download
    set_time_limit(600);

    $search_query = (!empty($_GET['search_query']) ? trim($_GET['search_query']) : '');
    $dateFrom = (!empty($_GET['dateFrom']) ? $_GET['dateFrom'] : '');
    $dateTo = (!empty($_GET['dateTo']) ? $_GET['dateTo'] : '');
    $source_search = (!empty($_GET['source_search']) ? trim($_GET['source_search']) : '');
    $ID_search = (!empty($_GET['ID_search']) ? trim($_GET['ID_search']) : '');
    $sourcebox = (!empty($_GET['sourcebox']) ? $_GET['sourcebox'] : '');

    // Download article data into a .zip file consisting of a single .csv file with all of the search results + individual .txt files for each article's content
    $download_id = uniqid(); // download identifier used in zip and csv filenames to differentiate one download instance from another (in case of simultaneous downloads from multiple users)

    $curdir = getcwd();
    $csvName = "article_$download_id.csv";

    $sql = buildQuery($connect,$search_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,"download");
    $outfile_sql = "INTO OUTFILE '$curdir/$csvName'
                    FIELDS TERMINATED BY '\\t' 
                    OPTIONALLY ENCLOSED BY '\\\"' ESCAPED BY '\"'
                    LINES TERMINATED BY '\\n'"; // necessary for MySQL to directly generate the CSV
    $sql .= $outfile_sql;

    // settings user variables necessary for calculating an article's Alt ID
    mysqli_query($connect,"SET SESSION group_concat_max_len = 1000000"); // need to temporarily increase GROUP_CONCAT() length
    mysqli_query($connect,"SET @n=0");
    mysqli_query($connect,"SET @pubdate=''");
    $query = mysqli_query($connect, $sql) or die(mysqli_connect_error()); // execute query
    mysqli_close($connect);

    $zipName = "articles_$download_id.zip";
    $zip = new ZipArchive(); // create a zip file
    if ($zip->open($zipName, ZipArchive::CREATE) && $query && file_exists($csvName))
    {
        if (($f = fopen($csvName, "r")) !== FALSE) { // open CSV and get the article ID of each row to decide which text files need to be added to the archive
            $txtdir = dirname(__DIR__) . '/txtfiles/';
            while (($row = fgetcsv($f,0,"\t","\"",'"')) !== FALSE) {
                $txtfile = $row[0] . ".txt";
                if(file_exists($txtdir . $txtfile)) {
                    $zip->addFile($txtdir . $txtfile,$txtfile);
                }
            }
            fclose($f);
        }

        $headers = array('"Article ID"', '"Alt ID"', '"Date/Time"', '"Source"', '"MBFC Bias"', '"MBFC Score"', '"MBFC Factual Reporting"', '"AllSides Bias"', '"AllSides Confidence"', 
                        '"AllSides Agreement"', '"AllSides Disagreement"', '"URL"', '"Title"', '"Author"', '"Relevancy Score"', '"Sentiment Score"', '"Sentiment Magnitude"', '"Top Image Entity"', 
                        '"Entity Score"', '"Keywords"', '"Similar Articles - Before Publication"', '"Similar Articles - After Publication"','"FB Reactions - Initial Entry"', '"FB Reactions - Day 1"', '"FB Reactions - Day 7"', '"FB Comments - Initial Entry"', 
                        '"FB Comments - Day 1"', '"FB Comments - Day 7"', '"FB Shares - Initial Entry"', '"FB Shares - Day 1"', '"FB Shares - Day 7"', '"FB Comment Plugin - Initial Entry"', 
                        '"FB Comment Plugin - Day 1"', '"FB Comment Plugin - Day 7"', '"TW Tweets - Initial Entry"', '"TW Tweets - Day 1"', '"TW Tweets - Day 7"', '"TW Total Favorites - Initial Entry"', 
                        '"TW Total Favorites - Day 1"', '"TW Total Favorites - Day 7"', '"TW Total Retweets - Initial Entry"', '"TW Total Retweets - Day 1"', '"TW Total Retweets - Day 7"', 
                        '"TW Top Favorites - Initial Entry"', '"TW Top Favorites - Day 1"', '"TW Top Favorites - Day 7"', '"TW Top Retweets - Initial Entry"', '"TW Top Retweets - Day 1"', 
                        '"TW Top Retweets - Day 7"', '"RDT Posts - Initial Entry"', '"RDT Posts - Day 1"', '"RDT Posts - Day 7"', '"RDT Total Comments - Initial Entry"', '"RDT Total Comments - Day 1"', 
                        '"RDT Total Comments - Day 7"', '"RDT Total Scores - Initial Entry"', '"RDT Total Scores - Day 1"', '"RDT Total Scores - Day 7"', '"RDT Top Comments - Initial Entry"', 
                        '"RDT Top Comments - Day 1"', '"RDT Top Comments - Day 7"', '"RDT Top Score - Initial Entry"', '"RDT Top Score - Day 1"', '"RDT Top Score - Day 7"', '"RDT Top Ratio - Initial Entry"', 
                        '"RDT Top Ratio - Day 1"', '"RDT Top Ratio - Day 7"', '"RDT Average Ratio - Initial Entry"', '"RDT Average Ratio - Day 1"', '"RDT Average Ratio - Day 7"');

        // append headers to CSV file (because MySQL's INTO OUTFILE command doesn't include them)
        $data = implode("\t",$headers) . "\n" . file_get_contents($csvName); 
        // the CSV is by default in UTF-8 encoding, which causes some scrambled characters in Excel (like apostrophes), so we convert it to UTF-16LE to fix this
        $data = chr(255) . chr(254) . mb_convert_encoding($data, 'UTF-16LE','UTF-8'); 
        file_put_contents($csvName, $data);
        unset($data);

        $zip->addFile($csvName,$csvName); // add completed CSV to zip
        $zip->close(); // finish zip

        ob_end_clean(); // clean output buffer and stop buffering - large downloads are very spotty otherwise

        // set headers to allow for sending the .zip to user
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=$zipName");
        header("Content-Length: " . filesize($zipName));

        unlink($csvName); // delete csv (no longer needed now that it's in the zip archive)
        readfile($zipName); // serve .zip to user
        unlink($zipName); // delete zip from server
    }
    else
    {
        echo "ERROR: Couldn't download files!";
    }
?>