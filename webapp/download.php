<?php
    // does the same SQL search query as the one in search.php for any given search, but outputs the rows to a .csv + article text in .txt
    // everything is stored inside a .zip file
    include_once("authenticate.php");
    include_once("db_connect.php");
    include("utils.php");

    // prepends headers and byte order mark to CSV
    function prepareCSV($fname) {
        $headers = array('"Article ID"', '"Alt ID"', '"Date/Time"', '"Source"', '"MBFC Bias"', '"MBFC Score"', '"MBFC Z-Score"', '"MBFC Factual Reporting"', '"AllSides Bias"', '"AllSides Score"', '"AllSides Z-Score"', '"AllSides Confidence"', 
        '"AllSides Agreement"', '"AllSides Disagreement"', '"MBM Score"', '"MBM Z-Score"', '"URL"', '"Title"', '"Author"', '"Relevancy Score"', '"Sentiment Score"', '"Sentiment Magnitude"', '"Top Image Entity"', 
        '"Entity Score"', '"Keywords"', '"Similar Articles - Before Publication"', '"Similar Articles - After Publication"','"FB Reactions - Initial Entry"', '"FB Reactions - Day 1"', '"FB Reactions - Day 7"', '"FB Reactions - Post 7 Days"', '"FB Comments - Initial Entry"', 
        '"FB Comments - Day 1"', '"FB Comments - Day 7"', '"FB Comments - Post 7 Days"', '"FB Shares - Initial Entry"', '"FB Shares - Day 1"', '"FB Shares - Day 7"', '"FB Shares - Post 7 Days"', '"FB Comment Plugin - Initial Entry"', 
        '"FB Comment Plugin - Day 1"', '"FB Comment Plugin - Day 7"', '"FB Comment Plugin - Post 7 Days"', '"TW Tweets - Initial Entry"', '"TW Tweets - Day 1"', '"TW Tweets - Day 7"', '"TW Total Favorites - Initial Entry"', 
        '"TW Total Favorites - Day 1"', '"TW Total Favorites - Day 7"', '"TW Total Retweets - Initial Entry"', '"TW Total Retweets - Day 1"', '"TW Total Retweets - Day 7"', 
        '"TW Top Favorites - Initial Entry"', '"TW Top Favorites - Day 1"', '"TW Top Favorites - Day 7"', '"TW Top Retweets - Initial Entry"', '"TW Top Retweets - Day 1"', 
        '"TW Top Retweets - Day 7"', '"RDT Posts - Initial Entry"', '"RDT Posts - Day 1"', '"RDT Posts - Day 7"', '"RDT Posts - Post 7 Days"', '"RDT Total Comments - Initial Entry"', '"RDT Total Comments - Day 1"', 
        '"RDT Total Comments - Day 7"', '"RDT Total Comments - Post 7 Days"', '"RDT Total Scores - Initial Entry"', '"RDT Total Scores - Day 1"', '"RDT Total Scores - Day 7"', '"RDT Total Scores - Post 7 Days"', '"RDT Top Comments - Initial Entry"', 
        '"RDT Top Comments - Day 1"', '"RDT Top Comments - Day 7"', '"RDT Top Comments - Post 7 Days"', '"RDT Top Score - Initial Entry"', '"RDT Top Score - Day 1"', '"RDT Top Score - Day 7"', '"RDT Top Score - Post 7 Days"', '"RDT Top Ratio - Initial Entry"', 
        '"RDT Top Ratio - Day 1"', '"RDT Top Ratio - Day 7"', '"RDT Top Ratio - Post 7 Days"', '"RDT Average Ratio - Initial Entry"', '"RDT Average Ratio - Day 1"', '"RDT Average Ratio - Day 7"', '"RDT Average Ratio - Post 7 Days"',
        '"MBM Political Alignment - Very Conservative"', '"MBM Political Alignment - Very Liberal"', '"MBM Political Alignment - Moderate"', '"MBM Political Alignment - Liberal"', '"MBM Political Alignment - Conservative"', 
        '"MBM Political Engagement - Moderate"', '"MBM Political Engagement - Liberal"', '"MBM Political Engagement - Conservative"', '"MBM Age - 25-34"', '"MBM Age - 35-44"', '"MBM Age - 45-54"', '"MBM Age - Under 18"', 
        '"MBM Age - Above 65"', '"MBM Age - 55-64"', '"MBM Age - 18-24"', '"MBM Income ($) - 250k to 350k"', '"MBM Income ($) - 75k to 100k"', '"MBM Income ($) - Over 500k"', '"MBM Income ($) - 125k to 150k"', '"MBM Income ($) - 40k to 50k"',
        '"MBM Income ($) - 150k to 250k"', '"MBM Income ($) - 100k to 125k"', '"MBM Income ($) - 30k to 40k"', '"MBM Income ($) - 350k to 500k"', '"MBM Income ($) - 50k to 75k"', '"MBM Race - Hispanic (All)"', '"MBM Race - Other"', '"MBM Race - Asian-American"', 
        '"MBM Race - African-American"', '"MBM Gender - Male"', '"MBM Gender - Female"', '"MBM Education - Grad school"', '"MBM Education - College"', '"MBM Education - High School"');

        $data = implode(",",$headers) . "\n" . file_get_contents($fname); // prepend headers to CSV file (because MySQL's INTO OUTFILE command doesn't include them)
        $data = chr(239) . chr(187) . chr(191) . $data; // ...then prepend UTF-8 byte order marks to make it Excel-friendly (certain characters appear funky otherwise)
        file_put_contents($fname, $data);
    }

    ini_set('memory_limit','250M'); // large downloads were hitting some memory usage limit once keywords were added to the CSV - upped it here (will likely need to increase with size of the database)
    ignore_user_abort(true); // still delete temp files if user cancels download
    set_time_limit(1800);

    // sanitize input
    $title_query = (!empty($_GET['title_query']) ? clean($_GET['title_query']) : '');
    $text_query = (!empty($_GET['text_query']) ? clean($_GET['text_query']) : '');
    $keyword_query = (!empty($_GET['keyword_query']) ? clean($_GET['keyword_query']) : '');
    $bool_search = !empty($_GET['bool_search']) && in_array($_GET['bool_search'],array('OR','AND')) ? $_GET['bool_search'] : 'OR';
    $dateFrom = (!empty($_GET['dateFrom']) ? clean($_GET['dateFrom']) : '');
    $dateTo = (!empty($_GET['dateTo']) ? clean($_GET['dateTo']) : '');
    $source_search = (!empty($_GET['source_search']) ? clean($_GET['source_search']) : '');
    $ID_search = (!empty($_GET['ID_search']) ? clean($_GET['ID_search']) : '');
    $sourcebox = (!empty($_GET['sourcebox']) ? $_GET['sourcebox'] : '');
    $dl_txt = (!empty($_GET['dl_txt']) ? filter_var($_GET['dl_txt'], FILTER_VALIDATE_BOOLEAN) : false);

    // determine whether to download pregenerated full dataset, or custom download
    $download_full_set = true;
    // if none of these parameters are selected, then the download full set
    foreach(array($title_query,$text_query,$keyword_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox) as $var) {
        if(!empty($var)) {
            $download_full_set = false;
        }
    }

    $dl_file = null;
    if($download_full_set) { // downloading full set (files have been pre-generated)
        $files = $dl_txt ? glob("Full_Dataset.*.zip") : glob("Full_Dataset.*.csv");
        if($files) { $dl_file = $files[0];}
    }
    else { // custom dataset
        // Download article data into a .zip file consisting of a single .csv file with all of the search results + individual .txt files for each article's content
        $download_id = uniqid(); // download identifier used in zip and csv filenames to differentiate one download instance from another (in case of simultaneous downloads from multiple users)

        $curdir = getcwd();
        $fname = "articles_$download_id";

        $sql = buildQuery($connect,$title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,'download');
        $outfile_sql = "INTO OUTFILE '$curdir/$fname.csv'
                        FIELDS TERMINATED BY ',' 
                        OPTIONALLY ENCLOSED BY '\\\"' ESCAPED BY '\"'
                        LINES TERMINATED BY '\\r\\n'"; // necessary for MySQL to directly generate the CSV
        $sql .= $outfile_sql;

        // settings user variables necessary for calculating an article's Alt ID
        mysqli_query($connect,"SET SESSION group_concat_max_len = 1000000"); // need to temporarily increase GROUP_CONCAT() length
        $query = mysqli_query($connect, $sql) or die(mysqli_connect_error()); // execute query
        mysqli_close($connect);

        if($query && file_exists($fname.".csv")) {
            prepareCSV($fname.".csv");
            if(!$dl_txt) { // CSV only
                $dl_file = $fname.".csv";
            } 
            else { // generate .zip
                $zip = new ZipArchive();
                if ($zip->open($fname.".zip", ZipArchive::CREATE))
                {
                    if (($f = fopen($fname.".csv", "r")) !== FALSE) { // open CSV and get the article ID of each row to decide which text files need to be added to the archive
                        $txtdir = dirname(__DIR__) . '/txtfiles/';
                        fgetcsv($f,0,",","\"",'"'); // skip headers
                        while (($row = fgetcsv($f,0,",","\"",'"')) !== FALSE) {
                            $txtfile = $row[0] . ".txt";
                            if(file_exists($txtdir . $txtfile)) {
                                $zip->addFile($txtdir . $txtfile,$txtfile);
                            }
                        }
                        fclose($f);
                    }
    
                    $zip->addFile($fname.".csv",$fname.".csv"); // add completed CSV to zip
                    $zip->close(); // finish zip
                    $dl_file = "articles_$download_id.zip";
    
                    ob_end_clean(); // clean output buffer and stop buffering - large downloads are very spotty otherwise
                    unlink($fname . ".csv"); // delete csv (no longer needed now that it's in the zip archive)
                }
            }
        } 
    }

    if($dl_file) {
        // set headers
        $dl_txt ? header('Content-Type: application/zip') : header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=$dl_file");
        header("Content-Length: " . filesize($dl_file));
        readfile($dl_file); // serve file to user
        if(!$download_full_set) {
            unlink($dl_file); // delete file from server
        }
    }
    else {
        echo "An error occurred during the download process.";
    }
?>