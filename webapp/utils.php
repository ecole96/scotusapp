<?php
    // used to build dynamic search queries (for results table/searching, downloading, and source data)
    function buildQuery($connect,$title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,$mode) {
        // preventing SQL injections...(more complex strings handled farther down)
        $title_query = mysqli_real_escape_string($connect,$title_query);
        $text_query = mysqli_real_escape_string($connect,$text_query);
        $keyword_query = mysqli_real_escape_string($connect,$keyword_query);
        $bool_search = mysqli_real_escape_string($connect,$bool_search);
        $dateFrom = mysqli_real_escape_string($connect,$dateFrom);
        $dateTo = mysqli_real_escape_string($connect,$dateTo);
        
        if($mode == 'download') {
            $sql = "SELECT a.idArticle, CONCAT(date(a.datetime), '_', LPAD(a.n, 3, '0')) as alt_id, a.datetime, a.source, IFNULL(sb.mbfc_bias,''), IFNULL(sb.mbfc_score,''), IFNULL(sb.mbfc_factual_reporting,''), IFNULL(sb.allsides_bias,''), IFNULL(sb.allsides_confidence,''), 
                    IFNULL(sb.allsides_agree,''), IFNULL(sb.allsides_disagree,''), a.url, a.title, a.author, IFNULL(a.relevancy_score,''), IFNULL(a.score,''), IFNULL(a.magnitude,''), IFNULL(i.top_entity,''), IFNULL(i.top_entity_score,''), k.keywords, 
                    IFNULL(sa.similarBefore,''), IFNULL(sa.similarAfter,''), IFNULL(a.fb_reactions_initial,''), IFNULL(a.fb_reactions_d1,''), IFNULL(a.fb_reactions_d7,''), IFNULL(a.fb_comments_initial,''), IFNULL(a.fb_comments_d1,''), IFNULL(a.fb_comments_d7,''), IFNULL(a.fb_shares_initial,''), 
                    IFNULL(a.fb_shares_d1,''), IFNULL(a.fb_shares_d7,''), IFNULL(a.fb_comment_plugin_initial,''), IFNULL(a.fb_comment_plugin_d1,''), IFNULL(a.fb_comment_plugin_d7,''), IFNULL(a.tw_tweets_initial,''), IFNULL(a.tw_tweets_d1,''), IFNULL(a.tw_tweets_d7,''), 
                    IFNULL(a.tw_favorites_initial,''), IFNULL(a.tw_favorites_d1,''), IFNULL(a.tw_favorites_d7,''), IFNULL(a.tw_retweets_initial,''), IFNULL(a.tw_retweets_d1,''), IFNULL(a.tw_retweets_d7,''), IFNULL(a.tw_top_favorites_initial,''), IFNULL(a.tw_top_favorites_d1,''), 
                    IFNULL(a.tw_top_favorites_d7,''), IFNULL(a.tw_top_retweets_initial,''), IFNULL(a.tw_top_retweets_d1,''), IFNULL(a.tw_top_retweets_d7,''), IFNULL(a.rdt_posts_initial,''), IFNULL(a.rdt_posts_d1,''), IFNULL(a.rdt_posts_d7,''), IFNULL(a.rdt_total_comments_initial,''), 
                    IFNULL(a.rdt_total_comments_d1,''), IFNULL(a.rdt_total_comments_d7,''), IFNULL(a.rdt_total_scores_initial,''), IFNULL(a.rdt_total_scores_d1,''), IFNULL(a.rdt_total_scores_d7,''), IFNULL(a.rdt_top_comments_initial,''), IFNULL(a.rdt_top_comments_d1,''), 
                    IFNULL(a.rdt_top_comments_d7,''), IFNULL(a.rdt_top_score_initial,''), IFNULL(a.rdt_top_score_d1,''), IFNULL(a.rdt_top_score_d7,''), IFNULL(a.rdt_top_ratio_initial,''), IFNULL(a.rdt_top_ratio_d1,''), IFNULL(a.rdt_top_ratio_d7,''), IFNULL(a.rdt_avg_ratio_initial,''), 
                    IFNULL(a.rdt_avg_ratio_d1,''), IFNULL(a.rdt_avg_ratio_d7,'') 
                    FROM article a
                    NATURAL JOIN
                        (SELECT idArticle, GROUP_CONCAT(keyword ORDER BY keyword ASC) as keywords from keyword_instances NATURAL JOIN article_keywords GROUP BY idArticle) k
                    LEFT JOIN
                        (SELECT ii.idArticle,ii.entity as top_entity,imax.top_entity_score
                            FROM (SELECT idArticle,entity,score FROM image NATURAL JOIN entity_instances NATURAL JOIN image_entities) ii
                            INNER JOIN
                            (SELECT idArticle,entity,MAX(score) as top_entity_score FROM image NATURAL JOIN entity_instances NATURAL JOIN image_entities GROUP BY idArticle) imax
                            ON ii.idArticle=imax.idArticle AND ii.entity=imax.entity AND ii.score=imax.top_entity_score) i 
                        ON a.idArticle=i.idArticle
                    LEFT JOIN (
                        (SELECT b1.source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfc_bias,mbfc_score,mbfc_factual_reporting
                        FROM source_bias b1
                        INNER JOIN
                            (SELECT source,MIN(allsides_id) min_id
                            FROM source_bias
                            GROUP BY source) b2 
                            ON b2.source=b1.source AND b1.allsides_id = b2.min_id)
                        UNION
                        (SELECT source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfc_bias,mbfc_score,mbfc_factual_reporting
                        FROM source_bias
                        WHERE allsides_bias IS NULL AND mbfc_bias IS NOT NULL)) sb 
                        ON a.source=sb.source
                    LEFT JOIN
                        (
                            SELECT sa_raw.idArticle, GROUP_CONCAT(CASE WHEN idArticle_datetime >= otherArticle_datetime THEN CONCAT(otherArticle,':',similarity) ELSE null END ORDER BY similarity DESC, otherArticle DESC) as similarBefore,
                            GROUP_CONCAT(CASE WHEN idArticle_datetime < otherArticle_datetime THEN CONCAT(otherArticle,':',similarity) ELSE null END ORDER BY similarity DESC, otherArticle DESC) as similarAfter
                            FROM 
                            (SELECT article1 as idArticle,article2 as otherArticle,similarity FROM similar_articles
                            UNION ALL
                            SELECT article2 as idArticle,article1 as otherArticle,similarity FROM similar_articles) sa_raw
                            INNER JOIN
                            (SELECT idArticle, datetime as idArticle_datetime FROM article) ia
                            ON ia.idArticle=sa_raw.idArticle
                            INNER JOIN
                            (SELECT idArticle, datetime as otherArticle_datetime FROM article) oa
                            ON oa.idArticle=sa_raw.otherArticle
                            GROUP BY sa_raw.idArticle
                        ) sa 
                        ON a.idArticle=sa.idArticle ";
        }
        else {
            $sql = "SELECT a.idArticle,source,title,date(datetime) as date ";
            $from_sql = 'FROM article a ';
            if(!empty($keyword_query)) {  // unless we're downloading or doing a keyword search, we don't need to use the extra resources to gather keywords
                $sql .= ', keywords '; 
                $from_sql .= 'NATURAL JOIN 
                            (SELECT idArticle, GROUP_CONCAT(keyword) as keywords FROM keyword_instances NATURAL JOIN article_keywords GROUP BY idArticle) k ';
            }
            $sql .= $from_sql;
            if($mode == 'sourcebox') {
                $sql = "SELECT source, count(source) FROM (" . $sql;
            }
        }

        $conditionsExist = false; // boolean to determine whether WHERE or AND is used in query statement (if true, initial condition has already been set so subsequent conditions are prefixed with AND)

        // primary search box (text, checks title and keywords)

        if(!empty($title_query)) {
            $title_str = "WHERE (MATCH(title) AGAINST ('$title_query') ";
            $conditionsExist = true;
            $sql .= $title_str;
        }


        if(!empty($text_query)) {
            $text_str = !$conditionsExist ? "WHERE (" : "$bool_search ";
            $text_str .= "MATCH(article_text) AGAINST ('$text_query') ";
            $conditionsExist = true;
            $sql .= $text_str;
        }

        if(!empty($keyword_query)) {
            $keyword_str = !$conditionsExist ? "WHERE (" : "$bool_search ";
            $keyword_str .= "(keywords LIKE '%$keyword_query%') ";
            $conditionsExist = true;
            $sql .= $keyword_str;
        }

        if($conditionsExist) {$sql .= ") ";}

        // date range search - if no dates provided, ignore
        if(!empty($dateFrom) && !empty($dateTo)) {
            // convert date input to Y-m-d format - this is because the bootstrap datepicker sends dates in Y/m/d while SQL only accepts as Y-m-d
        	$dateFrom = date("Y-m-d",strtotime($dateFrom));
            $dateTo = date("Y-m-d",strtotime($dateTo));
            $date_str = !$conditionsExist ? "WHERE " : "AND ";
            $date_str .= "(date(datetime) BETWEEN '$dateFrom' AND '$dateTo') ";
            $conditionsExist = true;
            $sql .= $date_str;
        }

        // if source filter has been applied and search parameters set, limit the sources to what has been checked
        if(!empty($sourcebox) && $mode != 'sourcebox') {
            $source_filter_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $source_filter_str .= "(a.source IN "; 
            $sourcebox_safe = array(); // build an array of sources in SQL string format, ex: ['source1','source2','source3'], all escaped to prevent SQL injections
            foreach($sourcebox as $source) {
                $source_str = "'" . mysqli_real_escape_string($connect,$source) . "'";
                array_push($sourcebox_safe,$source_str);
            }
            $source_filter_str .= "(" . implode(",",$sourcebox_safe) . ") "; // implode array by comma delimiter, bookended by parentheses to give us full list of sources to query against in SQL format
            $sql .= $source_filter_str . ')';
        }

        // source search box (different from sourcebox, though functionally the same)
        if(!empty($source_search)) {
            $ss_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $ss_str .= "(a.source IN ";
            $ss_arr = preg_split('/\s+/', $source_search, -1, PREG_SPLIT_NO_EMPTY); // delimit by whitespace
            $ss_safe = array();
            foreach($ss_arr as $s) {
                $s_str = "'" . mysqli_real_escape_string($connect,$s) . "'";
                array_push($ss_safe,$s_str);
            }
            $ss_str .= "(" . implode(",",$ss_safe) . ") ";
            $sql .= $ss_str . ')';
        }

        if(!empty($ID_search)) {
            $id_str = !$conditionsExist ? "WHERE " : "AND ";
            $conditionsExist = true;
            $id_str .= "(a.idArticle IN ";
            $id_arr = preg_split('/\s+/', $ID_search, -1, PREG_SPLIT_NO_EMPTY);
            $id_safe = array();
            foreach($id_arr as $i) {
                $i_str = "'" . mysqli_real_escape_string($connect,$i) . "'";
                array_push($id_safe,$i_str);
            }
            $id_str .= "(" . implode(",",$id_safe) . ") ";
            $sql .= $id_str . ')';
        }
        if($mode == "download") { $sql .= "ORDER BY a.idArticle DESC "; }
        else if($mode == 'sourcebox') { $sql .= ") AS results GROUP BY source ORDER BY source"; }

        return $sql;
    }

    // cleans input data by trimming whitespace and converting special characters to HTML-friendly ones
    function clean($str) {
        return htmlspecialchars(trim($str));
    }
?>