<?php
	function buildQuery($connect,$search_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,$mode) {
        // preventing SQL injections...(sourcebox strings handled farther down)
        $search_query = mysqli_real_escape_string($connect,$search_query);
        $dateFrom = mysqli_real_escape_string($connect,$dateFrom);
        $dateTo = mysqli_real_escape_string($connect,$dateTo);
        
        if($mode == 'download') {
            /*$sql = "SELECT a.idArticle,a.n,a.datetime,a.date,a.url,a.source,a.author,a.title,a.article_text,a.score,a.magnitude,
                    GROUP_CONCAT(DISTINCT keyword) as keywords, entity as top_entity, MAX(entity_instances.score) as top_entity_score, allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                    FROM (SELECT idArticle,@n:=CASE WHEN @pubdate = date(datetime) THEN @n + 1 ELSE 1 END AS n, @pubdate:=date(datetime) as date,datetime,url,source,author,title,article_text,score,magnitude FROM article ORDER BY date, idArticle) a
                    NATURAL JOIN (article_keywords NATURAL JOIN keyword_instances)
                    LEFT JOIN (image NATURAL JOIN image_entities NATURAL JOIN entity_instances) ON a.idArticle = image.idArticle
                    LEFT JOIN (
                                (SELECT b1.source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                                FROM source_bias b1
                                INNER JOIN
                                    (SELECT source,MIN(allsides_id) min_id
                                    FROM source_bias
                                    GROUP BY source) b2 ON b2.source=b1.source
                                AND b1.allsides_id = b2.min_id)
                            UNION
                                (SELECT source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                                FROM source_bias
                                WHERE allsides_bias IS NULL
                                    AND mbfs_bias IS NOT NULL)) bias ON a.source=bias.source ";*/
            $sql = "SELECT a.*, GROUP_CONCAT(DISTINCT keyword) as keywords, entity as top_entity, MAX(entity_instances.score) as top_entity_score, 
                    allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                    FROM (SELECT *, @n:=CASE WHEN @pubdate = date(datetime) THEN @n + 1 ELSE 1 END AS n, @pubdate:=date(datetime) as date FROM article ORDER BY date, idArticle) a
                    NATURAL JOIN (article_keywords NATURAL JOIN keyword_instances)
                    LEFT JOIN (image NATURAL JOIN image_entities NATURAL JOIN entity_instances) ON a.idArticle = image.idArticle
                    LEFT JOIN (
                                (SELECT b1.source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                                FROM source_bias b1
                                INNER JOIN
                                    (SELECT source,MIN(allsides_id) min_id
                                    FROM source_bias
                                    GROUP BY source) b2 ON b2.source=b1.source
                                AND b1.allsides_id = b2.min_id)
                            UNION
                                (SELECT source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                                FROM source_bias
                                WHERE allsides_bias IS NULL
                                    AND mbfs_bias IS NOT NULL)) bias ON a.source=bias.source ";
        }
        else {
            $sql = "SELECT idArticle,source,title,date(datetime) as date,GROUP_CONCAT(keyword) as keywords FROM article a NATURAL JOIN (article_keywords NATURAL JOIN keyword_instances) ";
            if($mode == 'sourcebox') {
                $sql = "SELECT source, count(source) FROM (" . $sql;
            }
        }

        $conditionsExist = false; // boolean to determine whether WHERE or AND is used in query statement (if true, initial condition has already been set so subsequent conditions are prefixed with AND)

        // date range search - if no dates provided, ignore
        if(!empty($dateFrom) && !empty($dateTo)) {
            // convert date input to Y-m-d format - this is because the bootstrap datepicker sends dates in Y/m/d while SQL only accepts as Y-m-d
        	$dateFrom = date("Y-m-d",strtotime($dateFrom));
            $dateTo = date("Y-m-d",strtotime($dateTo));
            $date_str = "WHERE date(datetime) BETWEEN '$dateFrom' AND '$dateTo' ";
            $conditionsExist = true;
            $sql .= $date_str;
        }

        // if source filter has been applied and search parameters set, limit the sources to what has been checked
        if(!empty($sourcebox) && $mode != 'sourcebox') {
            $source_filter_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $source_filter_str .= "a.source IN "; 
            $sourcebox_safe = array(); // build an array of sources in SQL string format, ex: ['source1','source2','source3'], all escaped to prevent SQL injections
            foreach($sourcebox as $source) {
                $source_str = "'" . mysqli_real_escape_string($connect,$source) . "'";
                array_push($sourcebox_safe,$source_str);
            }
            $source_filter_str .= "(" . implode(",",$sourcebox_safe) . ") "; // implode array by comma delimiter, bookended by parentheses to give us full list of sources to query against in SQL format
            $sql .= $source_filter_str;
        }

        // source search box (different from sourcebox, though functionally the same)
        if(!empty($source_search)) {
            $ss_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $ss_str .= "a.source IN ";
            $ss_arr = preg_split('/\s+/', $source_search, -1, PREG_SPLIT_NO_EMPTY); // delimit by whitespace
            $ss_safe = array();
            foreach($ss_arr as $s) {
                $s_str = "'" . mysqli_real_escape_string($connect,$s) . "'";
                array_push($ss_safe,$s_str);
            }
            $ss_str .= "(" . implode(",",$ss_safe) . ") ";
            $sql .= $ss_str;
        }

        if(!empty($ID_search)) {
            $id_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $id_str .= "a.idArticle IN ";
            $id_arr = preg_split('/\s+/', $ID_search, -1, PREG_SPLIT_NO_EMPTY);
            $id_safe = array();
            foreach($id_arr as $i) {
                $i_str = "'" . mysqli_real_escape_string($connect,$i) . "'";
                array_push($id_safe,$i_str);
            }
            $id_str .= "(" . implode(",",$id_safe) . ") ";
            $sql .= $id_str;
        }

        $sql .= "GROUP BY a.idArticle ";
        if(!empty($search_query)) { // HAVING clause necessary to correctly check search query against list of keywords 
            $sql .= "HAVING title LIKE '%$search_query%' OR keywords LIKE '%$search_query%' ";
        }
        if($mode == "download") { $sql .= "ORDER BY a.idArticle DESC"; }
        else if($mode == 'sourcebox') { $sql .= ") AS results GROUP BY source ORDER BY source"; }
        //echo $sql;
        return $sql;
    }
?>