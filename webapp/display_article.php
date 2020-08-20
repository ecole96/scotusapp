<?php
    // this page displays the full details about any given article
    include_once("authenticate.php");
    include("admins.php");
    include_once("db_connect.php");

    // this function dynamically generates the social media metrics tables (because there's lots of columns and it's repetitive work, I automated it)
    // headers parameter is an array following the $label=>$colname format, where the label is how the metric type is displayed on the table, 
    // and colname is the column name of that data in the database
    function generate_SMM_table($headers,$row) {
        $html = "<table class='table table-sm table-bordered'><tr>";
        $html .= "<th></th>";
        foreach($headers as $label=>$colname) {
            $html .= "<th>$label</th>";
        }
        $html .= "</tr>";
        // intervals follows $label=>postfix format
        // label is how the time interval is displayed on the table
        // postfix is the respective string at the end of each metric column in the database to differentiate between time intervals
        $intervals=array("Initial Entry"=>"initial","Day 1"=>"d1","Day 7"=>"d7"); 
        foreach($intervals as $label=>$postfix) {
            $html .= "<tr><th>$label</th>";
            foreach($headers as $label=>$colname) {
                $colname .= "_$postfix";
                $data = isset($row[$colname]) ? $row[$colname] : "N/A";
                $html .= "<td>$data</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";
        return $html;
    }

    // generates before and after publication similarity tables
    function generate_sim_table($query) {
        $html = '<table class="table table-sm table-bordered">
                        <tr>
                            <th>Article ID</th>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Title</th>
                            <th>Similarity Score</th>
                        </tr>';
        while($row = mysqli_fetch_assoc($query)) {
            $html .= "<tr>
                                <td style='text-align:center;width:8%'>{$row['idArticle']}</td>
                                <td style='text-align:center;width:15%'>{$row['datetime']}</td>
                                <td style='text-align:center';width:10%'>{$row['source']}</td>
                                <td style='width:57%'><a href='display_article.php?idArticle={$row['idArticle']}'><b>{$row['title']}</b></a></td>
                                <td style='text-align:center;width:10%'>{$row['similarity']}</td>
                            </tr>";
        }
        $html .= '</table>';
        return $html;
    }

    function generate_mbm_charts($bias) {
        $cols = array("pol_align"=>array("very_conservative","very_liberal","moderate","conservative","liberal"),
                      "pol_engage"=>array("liberal","moderate","conservative"),
                      "age"=>array("young_1","young_2","adolescent","mid_aged_1","mid_aged_2","old_1","old_2"),
                      "income"=>array("250k_to_350k","75k_to_100k","over_500k","125k_to_150k","40k_to_50k","100k_to_125k","30k_to_40k","350k_to_500k","50k_to_75k"),
                      "race"=>array("hispanic_all","asian_american","african_american","other"),
                      "gen"=>array("male","female"),
                      "edu"=>array("grad_school","college","high_school")
                    );
        $titles = array("pol_align"=>"Political Alignment","pol_engage"=>"Political Engagement","age"=>"Age","income"=>"Income ($)","race"=>"Race","gen"=>"Gender","edu"=>"Education");
        $blocks = array();
        foreach($cols as $field => $labels) {
            $title = $titles[$field];
            $data = array();
            foreach($labels as $l) {
                if($field != "age") { // dynamically creating chart labels based on DB naming conventions
                    $label = ucfirst(str_replace("_"," ",$l)); 
                }
                else { // ...except for age, which requires more explicit naming
                    $label_map = array("young_1"=>'18-24',"young_2"=>"25-34","adolescent"=>'Under 18',"mid_aged_1"=>'35-44',"mid_aged_2"=>'45-54',"old_1"=>'55-64',"old_2"=>'Above 65');
                    $label = $label_map[$l];
                }
                $sql_col = "mbm_$field" . "_$l";
                $y = round($bias[$sql_col]*100,2);
                array_push($data,array("label"=>$label,"y"=>$y));
            }
            $block = 
            "<div id=\"$field\" style=\"height:30%;\">
                <script>display_chart(\"$title\",\"$field\"," . json_encode($data, JSON_NUMERIC_CHECK) . ")</script>
            </div>";
            array_push($blocks,$block); // add chart HTML to set
        }
        $html = implode("<br>",$blocks); // implode by line break to get HTML for every chart
        return $html;
    }

    $idArticle = (!empty($_GET['idArticle']) ? trim($_GET['idArticle']) : '');
    $idArticle = mysqli_real_escape_string($connect,$idArticle);

    $details_sql = "SELECT *,CONCAT(date(datetime), '_', LPAD(n, 3, '0')) as alt_id FROM article WHERE idArticle='$idArticle'";
    $keywords_sql = "SELECT keyword FROM keyword_instances NATURAL JOIN article_keywords WHERE idArticle = '$idArticle' ORDER BY keyword";
    $images_sql = "SELECT idImage FROM image WHERE idArticle='$idArticle'";
    $similar_sql = "SELECT idArticle, datetime, source, title, similarity
                    FROM article a
                    INNER JOIN
                    (SELECT CASE WHEN article1='$idArticle' THEN article2 ELSE article1 END AS otherArticle,similarity FROM similar_articles WHERE '$idArticle' in (article1,article2)) sa
                    ON a.idArticle = sa.otherArticle
                    WHERE datetime ";
    $sim_postfix = "(SELECT datetime FROM article WHERE idArticle='$idArticle' LIMIT 1) ORDER BY similarity DESC, idArticle DESC";
    $sim_before_sql = $similar_sql . '<= ' . $sim_postfix;
    $sim_after_sql = $similar_sql . '> ' . $sim_postfix;
    $details_query = mysqli_query($connect, $details_sql);
    $keywords_query = mysqli_query($connect, $keywords_sql);
    $images_query = mysqli_query($connect, $images_sql);
    $sim_before_query = mysqli_query($connect,$sim_before_sql);
    $sim_after_query = mysqli_query($connect,$sim_after_sql);
?>

<html>
    <head>
        <title>SCOTUSApp - Display Article</title>
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
        <script>
			function changeResBut(){  //***
				document.getElementById("resBut").style.backgroundColor =  //***
				"#87ceeb" /*sky blue*/;  //***
			}
			function revertResBut(){ //revert style back to original for tab2
				document.getElementById("resBut").style.backgroundColor =  //***
				"rgba(255, 255, 255, 0.7)" /*transparent white*/;  //***
            }
            function ir_marking() { // function 
                $.ajax({
                    type: "POST",
                    url: "ir_marking.php",
                    data: { idArticle: "<?php echo $idArticle ?>"},
                    success:function(msg) {
                        if(msg == "Your recommendation has been noted.") {
                            // disable button (flag has been set, any further markings are redundant)
                            document.getElementById("ir_wrapper").innerHTML = "<span id='ir' style='color:red'>Marked as Irrelevant</span>";
                        }
                        alert(msg);
                    }
                })
            }
            function display_chart(title_text,id,chart_data) {
                var chart = new CanvasJS.Chart(id, {
                    animationEnabled: true,
                    title: {
                        text: title_text
                    },
                    data: [{
                        type: "pie",
                        yValueFormatString: "#,##0.00\"%\"",
                        indexLabel: "{label} ({y})",
                        dataPoints: chart_data
                    }]
                });
                chart.render();
            }
		</script>
        <style>
            .box {
                background-color:white; 
                border-radius: 25px; 
                padding: 20px; 
                border: 2px solid #000000;
                word-wrap: break-word
            }
            .box.hr {
                border-color:"black";
                background-color:"#black";
                color:"#black";
            }
            .box-header {
                font-size: 25px;
            }
            .field-header {
                font-size: 15.5px;
            }
            .subheader {
                font-size:17px;
            }
            #entities {
                font-weight: bold;
                font-size:14px;
            }
            #smm th, #smm td {
                text-align:center;
                font-size:14px;
            }
            #similar_articles th {
                text-align:center;
                font-size:14px;
            }
        </style>
    </head>
    <body style="height:100%; background-color: #fffacd; font-family: monospace; font-weight: bold;">
        <script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
        <div style='float:left; margin-left:1.5%;font-size: 18px; font-family: monospace;'>
            <?php echo contactLink(); ?> | <a href='about.html' style='color:black;'>About SCOTUSApp</a>
        </div>
        <div style="float:right; margin-right:1.5%;font-size: 18px; font-family: monospace;">
            <a style="color:black;" href="user_page.php"><?php echo $_SESSION['name']?></a> |
            <?php if($_SESSION['authority'] == 2) { echo "<a style='color:black' href='user_log.php'>User Log</a> | "; } ?>
            <a style="color:black;" href="logout.php">Logout</a>
        </div>
        <!-- header -->
        <div style="background-color: #fffacd; padding: 30px; text-align: center;">  <!--***-->
            <h1 style="font-size: 50px; font-family: monospace; font-weight: bold;"><a href='index.php' style='color:black;'>SCOTUSApp</a></h1>  <!--***-->
            <div align="right">
                <a style="color:black; text-decoration:none;" href="index.php">
                <button class="btn btn-default" id="resBut" onmouseover="changeResBut()" onmouseout="revertResBut()" style="height: 30px; font-weight: bold; font-family: monospace; background-color: rgba(255, 255, 255, 0.45); border: solid 3px; border-radius: 10px;">
                Restart
                </button>
                </a>
            </div>
            <hr>
        </div>
        <div class='container'>
            <div class="row">
                <div class="col-md-3">
                    <div id="details" class="box">
                        <span class="box-header">Details</span><br><br>
                        <?php $row = mysqli_fetch_assoc($details_query) ?>
                        <span class="field-header">ID: <?php echo isset($row['idArticle']) ? $row['idArticle'] : "N/A"; ?></span><br>
                        <span class="field-header">Alt ID: <?php echo !empty($row['alt_id']) ? $row['alt_id'] : "N/A"; ?></span><br><br>
                        <span class="field-header">Author</span><br><?php echo !empty($row['author']) ? $row['author'] : "N/A"; ?><br><br>
                        <span class="field-header">Source</span><br><?php echo !empty($row['source']) ? $row['source'] : "N/A"; ?><br><br>
                        <span class="field-header">Publication Date</span><br><?php echo !empty($row['datetime']) ? $row['datetime'] : "N/A"; ?><br><br>
                        <span class="field-header">URL</span><br><?php echo !empty($row['url']) ? "<a href='{$row['url']}'>{$row['url']}</a>" : "N/A"; ?><br><br>
                        <span class="field-header">Relevancy: <?php echo isset($row['relevancy_score']) ? round($row['relevancy_score'],4) : "N/A"; ?></span><br><br>
                        <span class="field-header">Sentiment: <?php echo isset($row['score']) ? $row['score'] : "N/A"; ?></span><br>
                        <span class="field-header">Magnitude: <?php echo isset($row['magnitude']) ? $row['magnitude'] : "N/A"; ?></span><br><br>
                        <?php
                            if(!empty($row)) { // if article exists, show irrelevancy marking functionality
                                echo "<div id='ir_wrapper'>";
                                // if article isn't already marked as irrelevant, show the marking button - if not, show a "Marked as Irrelevant" message
                                echo !$row['marked_irrelevant'] ? "<button id='ir' onclick='ir_marking()'>Mark as Irrelevant</button>" : "<span id='ir' style='color:red'>Marked as Irrelevant</span>";
                                echo "</div>";
                            }
                        ?>
                    </div>
                    <div id="bias" class="box" style="margin-top:6%;">
                        <span class="box-header">Source Bias</span><hr>
                        <?php
                            if(!empty($row['source'])) {
                                $bias_sql = "SELECT source_bias.*, ROUND((allsides_score - allsides_mean)/allsides_sd,2) as allsides_z, ROUND((mbfc_score - mbfc_mean)/mbfc_sd,2) as mbfc_z, ROUND((mbm_score - mbm_mean)/mbm_sd,2) as mbm_z 
                                             FROM source_bias
                                             CROSS JOIN (
                                                 SELECT AVG(allsides_score) as allsides_mean, AVG(mbfc_score) as mbfc_mean, AVG(mbm_score) as mbm_mean, 
                                                 STD(allsides_score) as allsides_sd, STD(mbfc_score) as mbfc_sd, STD(mbm_score) as mbm_sd
                                                 FROM source_bias
                                                    WHERE source in (SELECT DISTINCT source FROM article)
                                                ) agg
                                            WHERE source = '{$row['source']}' LIMIT 1";
                                $bias_query = mysqli_query($connect,$bias_sql);
                                $bias = mysqli_fetch_assoc($bias_query);
                                echo "<span class='subheader'>";
                                if(!empty($bias['allsides_bias'])) {
                                    echo "<a href='https://www.allsides.com/node/{$bias['allsides_id']}'>AllSides</a></span><br><br>";
                                    echo "<span class='field-header'>Bias: {$bias['allsides_bias']}</span><br><br>";
                                    echo "<span class='field-header'>Score: {$bias['allsides_score']}</span><br><br>";
                                    echo "<span class='field-header'>Z-Score: {$bias['allsides_z']}</span><br><br>";
                                    echo "<span class='field-header'>Confidence</span><br>{$bias['allsides_confidence']}<br><br>";
                                    $total_votes = $bias['allsides_agree'] + $bias['allsides_disagree'];
                                    $community_agreement = $total_votes > 0 ? round(($bias['allsides_agree'] / $total_votes) * 100,2) . "%" : "N/A";
                                    echo "<span class='field-header'>Community Agreement</span><br>$community_agreement [{$bias['allsides_agree']} / {$bias['allsides_disagree']}]";
                                }
                                else {
                                    echo "AllSides</span><br><br>";
                                    echo "N/A";
                                }
                                echo "<hr>";
                                echo "<span class='subheader'>";
                                if(!empty($bias['mbfc_bias'])) {
                                    echo "<a href='https://mediabiasfactcheck.com/{$bias['mbfc_id']}/'>Media Bias Fact Check</a></span><br><br>";
                                    echo "<span class='field-header'>Bias: {$bias['mbfc_bias']}</span><br><br>";
                                    echo "<span class='field-header'>Score: {$bias['mbfc_score']}</span><br><br>";
                                    echo "<span class='field-header'>Z-Score: {$bias['mbfc_z']}</span><br><br>";
                                    echo "<span class='field-header'>Factual Reporting</span><br>{$bias['mbfc_factual_reporting']}";
                                }
                                else {
                                    echo "Media Bias Fact Check</span><br><br>";
                                    echo "N/A";
                                }
                                echo "<hr><span class='subheader'>Media Bias Monitor</span><br><br>";
                                if(!empty($bias['mbm_score'])) {
                                    echo "<span class='field-header'>Score: " . round($bias['mbm_score'],4) . "</span><br><br>";
                                    echo "<span class='field-header'>Z-Score: {$bias['mbm_z']}</span>";
                                }
                                else {
                                    echo "N/A";
                                }
                            }
                            else { 
                                echo "No data";
                            }
                        ?>
                    </div>
                    <div id="keywords" class="box" style="margin-top:6%; margin-bottom: 6%;">
                        <span class="box-header">Keywords</span><br><br>
                        <?php
                            if(mysqli_num_rows($keywords_query) > 0) {
                                while($keywords = mysqli_fetch_assoc($keywords_query)) {
                                    echo "{$keywords['keyword']}<br>";
                                }
                            }
                            else {
                                echo "None";
                            }  
                        ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <div id="main" class="box">
                        <?php echo !empty($row['title']) ? "<span class='subheader'>{$row['title']}</span>" : "N/A"; ?><hr>
                        <?php 
                            // display only a third of the article text (for copyright reasons)
                            if(!empty($row['article_text'])) {
                                $text = $row['article_text'];
                                $n = floor(strlen($text) / 3);
                                $text = nl2br(substr($text,0,$n) . "...");
                            }
                            else {
                                $text = "N/A";
                            }
                            echo $text;
                        ?>
                    </div>
                    <div id="images" class="box" style="margin-top:1.25%;">
                        <span class="box-header">Images & Entities</span><br><br>
                        <?php
                            if(mysqli_num_rows($images_query) > 0) {
                                while($image = mysqli_fetch_assoc($images_query)) {
                                    echo "<img src='serve_img.php?idImage={$image['idImage']}' style='max-width:85%;'><br><br>";
                                    $img_entity_sql = "SELECT entity,score FROM image_entities NATURAL JOIN entity_instances WHERE idImage={$image['idImage']}";
                                    $img_entity_query = mysqli_query($connect,$img_entity_sql);
                                    if(mysqli_num_rows($img_entity_query) > 0) {
                                        $table =   "<table id='entities' style='width:75%'>
                                                        <thead>
                                                            <tr class='subheader'>
                                                                <th>Entity</th>
                                                                <th>Score</th>
                                                            </tr>
                                                        </thead>
                                                    <tbody>";
                                        while($entity = mysqli_fetch_assoc($img_entity_query)) {
                                            $table .=  "<tr>
                                                            <td>{$entity['entity']}</td>
                                                            <td>{$entity['score']}</td>
                                                        <tr>";        
                                        }
                                        $table .= "</tbody></table>";
                                        echo $table;
                                    }
                                    else {
                                        echo "No entities";
                                    }
                                }
                            }
                            else {
                                echo "None";
                            }
                        ?>
                    </div>
                    <div id="smm" class="box" style="margin-top:1.25%;">
                        <span class="box-header">Social Media Metrics</span><br><br>
                        <div id='fb'>
                            <span class='subheader'>Facebook</span><br>
                            <?php
                                $headers = array("Reactions"=>"fb_reactions","Comments"=>"fb_comments","Shares"=>"fb_shares","Comment Plugin"=>"fb_comment_plugin");
                                echo generate_SMM_table($headers,$row) ?>
                        </div>
                        <div id='twitter'>
                            <span class='subheader'>Twitter</span>
                            <?php
                                $headers = array("Tweets"=>"tw_tweets","Total Favorites"=>"tw_favorites","Total Retweets"=>"tw_retweets","Top Favorites"=>"tw_top_favorites","Top Retweets"=>"tw_top_retweets");
                                echo generate_SMM_table($headers,$row) ?>
                        </div>
                        <div id='reddit'>
                            <span class='subheader'>Reddit</span>
                            <?php
                                $headers = array("Posts"=>"rdt_posts","Total Comments"=>"rdt_total_comments","Total Scores"=>"rdt_total_scores",
                                "Top Comments"=>"rdt_top_comments","Top Score"=>"rdt_top_score","Top Ratio"=>"rdt_top_ratio","Average Ratio"=>"rdt_avg_ratio");
                                echo generate_SMM_table($headers,$row) ?>
                        </div>
                    </div>
                    <div id="similar_articles" class="box" style="margin-top:1.25%;">
                        <span class="box-header">Similar Articles (Within 3 Days of Publication)</span><br><br>
                        <?php
                            $n_before = mysqli_num_rows($sim_before_query);
                            $n_after = mysqli_num_rows($sim_after_query);
                            $total = $n_before + $n_after;
                            echo "<span style='font-size:18px'>$total Article" . ($total != 1 ? "s" : "") . "</span><br><br>";
                        ?>
                        <div id="before_pub">
                            <span class='subheader'>Before Publication <?php echo "($n_before)"?></span>
                            <br>
                            <?php echo ($n_before > 0) ? generate_sim_table($sim_before_query) : "None<br><br>"; ?>
                        </div>
                        <div id="after_pub">
                            <span class='subheader'>After Publication <?php echo "($n_after)"?></span>
                            <br>
                            <?php echo ($n_after > 0) ? generate_sim_table($sim_after_query) : "None"; ?>
                        </div>
                    </div>
                    <div id="mbm" class="box" style="margin-top:1.25%;">
                        <span class="box-header">Source Audience Data (Media Bias Monitor)</span><br><br>
                        <div id="charts">
                            <?php 
                                echo !empty($bias['mbm_score']) ? generate_mbm_charts($bias) : "None";
                            ?>
                        <div>
                    </div>
                </div>
            </div>
        </div>
        <!-- source bias citations -->
        <footer style='text-align:center; margin-top:1.25%'>
            <div class="row">
                <div class="col-md-12">
                    <!--THIS PORTION OF THE ATTRIBUTION MUST BE INCLUDED-->
                    <a rel="license" href="http://creativecommons.org/licenses/by-nc/4.0/"><img style="margin-top: 5px; margin-bottom: 5px;" alt="Creative Commons License" style="border-width:0" src="https://i.creativecommons.org/l/by-nc/4.0/88x31.png" /></a><br />
                    <p><a xmlns:dct="http://purl.org/dc/terms/" href="https://www.allsides.com/media-bias/media-bias-ratings" rel="dct:source"><span xmlns:dct="http://purl.org/dc/terms/" href="http://purl.org/dc/dcmitype/Dataset" property="dct:title" rel="dct:type">AllSides Media Bias Ratings</span></a> by <a xmlns:cc="http://creativecommons.org/ns#" href="https://www.allsides.com/unbiased-balanced-news" property="cc:attributionName" rel="cc:attributionURL">AllSides.com</a> are licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-nc/4.0/">Creative Commons Attribution-NonCommercial 4.0 International License</a>. You may use this data for research or noncommercial purposes provided you include this attribution.</p>
                    <p>Media Bias Fact Check data courtesy of <a href="https://mediabiasfactcheck.com">MediaBiasFactCheck.com</a>.
                    You may use this data for research or noncommercial purposes provided you include this attribution.</p>
                    <p>Media Bias Monitor data courtesy of <a href='https://homepages.dcc.ufmg.br/~filiperibeiro/'>Filipe N. Ribiero</a>'s <a href='https://twitter-app.mpi-sws.org/media-bias-monitor/'>Media Bias Monitor project</a></p>
                    <p>Use of this application must be in accordance with the <a href='tos.html'>SCOTUSApp Terms of Use</a>.</p>
                </div>  
            </div>
        </footer>
    </body>
</html>