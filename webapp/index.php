<!-- //*** denotes new lines of code added -->
<!--This is the homepage of the web application. It presents a search form with a datepicker. Sources are listed on the left, and article titles along with their source and date are presented. There is also a download button for a zip folder of the articles currently on the webpage.-->
<!-- originally written by Evan Cole, Darin Ellis, Connor Martin, and Abdullah Alosail, with contributions by John Tompkins, Mauricio Sanchez, and Jonathan Dingess -->

<?php
    include_once("authenticate.php");
    include("utils.php");
    include("admins.php");
    include_once("db_connect.php"); // connect to database (or not)

    // generates download button url 
    function generateDownloadURL($title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox) {
        $url = "download.php?";
        $vars = array("title_query"=>$title_query,"text_query"=>$text_query,"keyword_query"=>$keyword_query,"bool_search"=>$bool_search,
                      "dateFrom"=>$dateFrom,"dateTo"=>$dateTo,"source_search"=>$source_search,"ID_search"=>$ID_search,"sourcebox"=>$sourcebox);
        $add_ampersand = false; // flag for multivariables - every query string beyond the first will be prefixed with &
        foreach($vars as $key=>$var) {
            if(!empty($var)) {
                if($add_ampersand) { $url .= "&"; }
                if($key != "sourcebox") { 
                    $url .= "$key=" . $var; }
                else { // each entry in sourcebox array needs its own query string (separated by ampersand)
                    $str_arr = array();
                    foreach($var as $v) { array_push($str_arr,"{$key}[]=$v"); }
                    $url .= implode("&",$str_arr);  
                }
                if(!$add_ampersand) { $add_ampersand = true; }
            }
        }
        return $url;
    }

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

    $downloadURL = generateDownloadURL($title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox);
    $sourcebox_sql = buildQuery($connect,$title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,'sourcebox');
    $sourcebox_query = mysqli_query($connect, $sourcebox_sql) or die(mysqli_connect_error()); // execute source sidebar query
?>

<!DOCTYPE html>
<html>
    <head>
        <title>SCOTUSApp</title>
        <meta charset="utf-8">

        <!-- Bootstrap stuff -->
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <!-- jQuery library -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <!-- Latest compiled JavaScript -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.16/css/jquery.dataTables.css">
        <script type="text/javascript" charset="utf8" src="//cdn.datatables.net/1.10.16/js/jquery.dataTables.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.7.1/css/bootstrap-datepicker.min.css" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.7.1/js/bootstrap-datepicker.min.js"></script>
        <script>
            $(document).ready(function() {
                            $('.datebox').datepicker({clearBtn: true });
                          });
        </script>
        <script>  //***  change__But and revert__But are functions for events onmouseover and onmouseout of buttons in the webapp. When the user mouses over a button, it highlights the button, and unhighlights when leaving the button area
            function hoverOver(id) {
                document.getElementById(id).style.backgroundColor =  "#87ceeb" /*sky blue*/;
            }

            function hoverOut(id) {
                document.getElementById(id).style.backgroundColor =  "rgba(255, 255, 255, 0.7)" /*transparent white*/;
            }
        </script>
        <style>
            .source-results a {
                font-size:20px;
                font-weight:bold;
                color:black;
            }
        </style>
    </head>
    <body style="height:100%; background-color: #fffacd; font-family: monospace; font-weight: bold;">  <!--***  changes appearance of webpage-->
        <!-- header -->
        <div style='float:left; margin-left:1.5%;font-size: 18px; font-family: monospace;'>
            <?php echo contactLink(); ?> | <a href='about.html' style='color:black;'>About SCOTUSApp</a>
        </div>
        <div style="float:right; margin-right:1.5%;font-size: 18px; font-family: monospace;">
            <a style="color:black;" href="user_page.php"><?php echo $_SESSION['name']?></a> |
            <?php if($_SESSION['authority'] == 2) { echo "<a style='color:black' href='user_log.php'>User Log</a> | "; } ?>
            <a style="color:black;" href="logout.php">Logout</a>
        </div>
        <div style="background-color: #fffacd; padding: 30px; text-align: center;">
            <h1 style="font-size: 50px; font-family: monospace; font-weight: bold;"><a href='index.php' style='color:black;'>SCOTUSApp</a></h1>
            <hr>
        </div>

        <div class="container">
            <form action='' method='GET'>
                <div class='form-group row'>
                    <label for='search_q' class='col-sm-1 col-form-label'>Title</label>
                    <div class="col-sm-3">
                        <input type="text" class="form-control" name="title_query" placeholder="Search term in title..."
                        <?php 
                            if(!empty($title_query)) echo " value=\"{$title_query}\""; 
                        ?> >
                    </div>
                    <label for='text_query' class='col-sm-1 col-form-label'>Article Body</label>
                    <div class="col-sm-3">
                        <input type="text" class="form-control" name="text_query" placeholder="Search term in text..."
                        <?php 
                            if(!empty($text_query)) echo " value=\"{$text_query}\""; 
                        ?> >
                    </div>
                    <label for='keyword_query' class='col-sm-1 col-form-label'>Keyword</label>
                    <div class="col-sm-3">
                        <input type="text" class="form-control" name="keyword_query" placeholder="Search term in keywords..."
                        <?php 
                            if(!empty($keyword_query)) echo " value=\"{$keyword_query}\""; 
                        ?> >
                    </div>
                </div>
                <div class='form-group row'>
                    <!-- New search function: "Combine Text Fields" allows you to AND/OR the title/text/keyword fields -->
                    <label for='bool_search' class='col-sm-1 col-form-label'>Combine Text Fields</label>
                    <div class="col-sm-3">
                        <div class="radio-inline">
                            <label><input type="radio" name="bool_search" value = "OR"
                            <?php
                                if($bool_search != 'AND') { // OR is default
                                    echo "checked";
                                }
                            ?>
                            >OR</label>
                        </div>
                        <div class="radio-inline">
                            <label><input type="radio" name="bool_search" value= "AND"
                            <?php
                                if($bool_search == 'AND') {
                                    echo "checked";
                                }
                            ?>
                            >AND</label>
                        </div>
                    </div>
                    <label for='dateFrom' class='col-sm-1 col-form-label'>From</label>
                    <div class="col-sm-3">
                        <input data-provide="datepicker" class="form-control datebox" type="text" placeholder="Start date range" name="dateFrom" <?php if(!empty($dateFrom) && !empty($dateTo)) { echo " value = \"{$dateFrom}\""; } ?> >
                    </div>
                    <label for='dateTo' class='col-sm-1 col-form-label'>To</label>
                    <div class="col-sm-3">
                        <input data-provide="datepicker" class="form-control datebox" type="text" placeholder="End date range" name="dateTo" <?php if(!empty($dateFrom) && !empty($dateTo)) { echo " value = \"{$dateTo}\""; } ?> >
                    </div>
                </div>
                <div class='form-group row'>
                    <label for='source_search' class='col-sm-1 col-form-label'>Sources</label>
                    <div class="col-sm-3">
                        <input class='form-control' type="text" name="source_search" style="width:275px;" placeholder='Separate sources by spaces...'
                        <?php 
                            if(!empty($source_search)) echo " value=\"{$source_search}\""; 
                        ?> >
                    </div>
                    <label for='ID_search' class='col-sm-1 col-form-label'>IDs</label>
                    <div class="col-sm-3">
                        <input class='form-control' type="text" name="ID_search" style="width:250px;" placeholder='Separate IDs by spaces...'
                        <?php 
                            if(!empty($ID_search)) echo " value=\"{$ID_search}\""; 
                        ?> >
                    </div>
                    <div class='col-sm-3 col-sm-offset-1'>
                        <button id="submit" type='submit' class='btn btn-default' onmouseover="hoverOver('submit')" onmouseout="hoverOut('submit')"
                                                                    style = "height: 30px;
                                                                    font-weight: bold;
                                                                    font-family: monospace;
                                                                    background-color: rgba(255, 255, 255, 0.45);
                                                                    border: solid 3px;
                                                                    border-radius: 10px;">Submit</button>
                    </div>
                </div>
            </form>
        </div>

        <!--download button -->
        <div align="right">
            <button class="btn btn-default" id="download_csv" onmouseover="hoverOver('download_csv')" onmouseout="hoverOut('download_csv')" style="height: 30px;font-weight: bold; font-family: monospace; background-color: rgba(255, 255, 255, 0.45); border: solid 3px;border-radius: 10px;">
                <a style="color:black; text-decoration:none;" href="<?php echo $downloadURL . "&dl_txt=0" ?>">Download (CSV)</a>
            </button>&nbsp;
            <button class="btn btn-default" id="download_zip" onmouseover="hoverOver('download_zip')" onmouseout="hoverOut('download_zip')" style="height: 30px;font-weight: bold; font-family: monospace; background-color: rgba(255, 255, 255, 0.45); border: solid 3px;border-radius: 10px;">
                <a style="color:black; text-decoration:none;" href="<?php echo $downloadURL . "&dl_txt=1" ?>">Download (CSV + TXTs)</a>
            </button>&nbsp;
        </div>
        <hr>

        <!-- display query results as table -->
        <div class="mainWrapper" style="overflow:hidden;">
            <div class="floatLeft" style="width: 18%; float:left">
                    <br>
                    <div class="panel panel-default">
                        <div class="panel-heading" style="font-size:20px; background-color: #e0eee0;">  <!--***-->
                            Sources (<?php echo mysqli_num_rows($sourcebox_query) ?>)
                        </div>
                        <div class="panel-body" style="font-size: 16px; background-color: #e0eee0">  <!--***-->
                            <?php
                                // build search filter panel (list of sources with checkboxes)
                                // Known "defect" - because we're using two forms (the search form and filter form), any changes to the search parameters after a filter has been applied will be ignored (like changing the date range after selecting specific sources) - a new search will have to be done
                                // not enough time to come up with a more elegant solution
                                if(mysqli_num_rows($sourcebox_query) == 0) // no results
                                {
                                    echo "No sources";
                                }
                                else
                                {
                                    echo "<form action='' method='GET'>";
                                    echo "<button type='submit' class='btn btn-default' id='applyBut' name='submit' onmouseover='changeApplyBut()' onmouseout='revertApplyBut()'
																		style='height: 30px;
																		font-weight: bold;
																		font-family: monospace;
																		background-color: rgba(255, 255, 255, 0.45);
																		border: solid 3px;
																		border-radius: 10px;'>Apply Filter</button><br><br>";  //***

                                    // pass in search parameters (if any) into filter form
                                    //$hiddenvars = array('search_query'=>$search_query,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'ID_search'=>$ID_search);
                                    $hiddenvars = array('title_query'=>$title_query,'text_query'=>$text_query,'keyword_query'=>$keyword_query,'bool_search'=>$bool_search,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'ID_search'=>$ID_search);
                                    foreach($hiddenvars as $key=>$var) {
                                        if(!empty($var)) {
                                            echo "<input type='hidden' name='$key' value='$var'>";
                                        }
                                    }

                                    // if sourcebox is active - check which groups need to be displayed by default after search
                                    $open_groups = array();
                                    if(!empty($sourcebox)) {
                                        foreach($sourcebox as $source) {
                                            $group = !is_numeric($source[0]) ? ucfirst($source[0]) : "0-9";
                                            if(!in_array($group,$open_groups)) {
                                                array_push($open_groups,$group);
                                            }  
                                        }
                                    }

                                    // generate list of sources in a scrollbox
                                    echo "<div class='source-results' style='max-height: 810px; overflow:auto'>";
                                    $group = null; // used to keep track of alphabetical categories
                                    while($row = mysqli_fetch_assoc($sourcebox_query)) {
                                        $source = $row['source'];
                                        $prevGroup = $group;
                                        $group = !is_numeric($source[0]) ? ucfirst($source[0]) : "0-9";
                                        if($group !== $prevGroup) { // create new group
                                            if($prevGroup != null) { echo "</div>"; }
                                            echo "<a data-toggle='collapse' href=#$group>$group</a><br>";
                                            $class = in_array($group,$open_groups) ? "collapse in" : "collapse"; // if a source is selected, display that group by default upon search
                                            echo "<div class='$class' id='$group'>";
                                        }
                                        $count = $row['count(source)'];
                                        echo "$source ($count) <input type='checkbox' name='sourcebox[]' value='$source' ";
                                        if(!empty($sourcebox) && in_array($source,$sourcebox)) { 
                                            echo "checked = 'checked' ";  // if source already checked, it will remain checked upon submit
                                        }
                                        echo "><br>";
                                    }
                                    echo "</div></div>";
                                    echo "</form>";
                                }
                            ?>
                        </div>
                    </div>
                    <br>
            </div>

			<!--style of table-->
            <div class="floatRight" style="width:81%; float: right; ">
                <table id="results-table" style="background-color: #e0eee0;table-layout: fixed;" width="100%" class="stripe hover"  align="center">
                    <thead>
                        <tr align="center">
                        <th width="8%"><strong>ID</strong></th>
                        <th width="65%"><strong>Title</strong></th>
                        <th width="15%"><strong>Source</strong></th>
                        <th width="12%"><strong>Date</strong></th>
                        </tr>
                    </thead>
                </table>

                <script>
                    $(document).ready(function() {
                        $('#results-table').DataTable({
                            "searching":false,
                            "order": [[0,"desc"]],
                            "columnDefs": [
                                {
                                    //"targets": [ 0 ], // sort by article ID to avoid "shuffling" articles, but keep the IDs themselves hidden
                                    //"visible": false
                                }
                            ],
                            "pageLength": 50,
                             "processing": true,
                             "serverSide": true,
                             "ajax":{
                                url :"response.php", // json datasource
                                type: "get",  // type of method  , by default would be get
                                data: function (d) {
                                    d.title_query = "<?php echo $title_query ?>";
                                    d.text_query = "<?php echo $text_query ?>";
                                    d.keyword_query = "<?php echo $keyword_query ?>";
                                    d.bool_search = "<?php echo $bool_search ?>"
                                    d.dateFrom = "<?php echo $dateFrom ?>";
                                    d.dateTo = "<?php echo $dateTo ?>";
                                    d.ID_search = "<?php echo $ID_search ?>";
                                    d.source_search = "<?php echo $source_search ?>";
                                    d.sourcebox = <?php echo json_encode($sourcebox) ?>;
                                }
                              }
                            });   
                    });
                </script>
            </div>
        </div>
        <footer>
            <div style="margin-top:25px; margin-bottom:25px; text-align:center; font-size:14px;">
            <p>Use of this application must be in accordance with the <a href='tos.html'>SCOTUSApp Terms of Use</a>.</p>
            </div>
        </footer>
    </body>
</html>