<?php
	function buildQuery($connect,$search_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,$mode) {
        // preventing SQL injections...(more complex strings handled farther down)
        $search_query = mysqli_real_escape_string($connect,$search_query);
        $dateFrom = mysqli_real_escape_string($connect,$dateFrom);
        $dateTo = mysqli_real_escape_string($connect,$dateTo);
        
        if($mode == 'download') {
            $sql = "SELECT a.idArticle, CONCAT(a.date, '_', LPAD(a.n, 3, '0')) as alt_id, a.datetime, a.source, IFNULL(sb.mbfs_bias,''), IFNULL(sb.mbfs_score,''), IFNULL(sb.factual_reporting,''), IFNULL(sb.allsides_bias,''), IFNULL(sb.allsides_confidence,''), 
                    IFNULL(sb.allsides_agree,''), IFNULL(sb.allsides_disagree,''), a.url, a.title, a.author, IFNULL(a.relevancy_score,''), IFNULL(a.score,''), IFNULL(a.magnitude,''), IFNULL(i.top_entity,''), IFNULL(i.top_entity_score,''), k.keywords, 
                    IFNULL(sa.similarBefore,''), IFNULL(sa.similarAfter,''), IFNULL(a.fb_reactions_initial,''), IFNULL(a.fb_reactions_d1,''), IFNULL(a.fb_reactions_d7,''), IFNULL(a.fb_comments_initial,''), IFNULL(a.fb_comments_d1,''), IFNULL(a.fb_comments_d7,''), IFNULL(a.fb_shares_initial,''), 
                    IFNULL(a.fb_shares_d1,''), IFNULL(a.fb_shares_d7,''), IFNULL(a.fb_comment_plugin_initial,''), IFNULL(a.fb_comment_plugin_d1,''), IFNULL(a.fb_comment_plugin_d7,''), IFNULL(a.tw_tweets_initial,''), IFNULL(a.tw_tweets_d1,''), IFNULL(a.tw_tweets_d7,''), 
                    IFNULL(a.tw_favorites_initial,''), IFNULL(a.tw_favorites_d1,''), IFNULL(a.tw_favorites_d7,''), IFNULL(a.tw_retweets_initial,''), IFNULL(a.tw_retweets_d1,''), IFNULL(a.tw_retweets_d7,''), IFNULL(a.tw_top_favorites_initial,''), IFNULL(a.tw_top_favorites_d1,''), 
                    IFNULL(a.tw_top_favorites_d7,''), IFNULL(a.tw_top_retweets_initial,''), IFNULL(a.tw_top_retweets_d1,''), IFNULL(a.tw_top_retweets_d7,''), IFNULL(a.rdt_posts_initial,''), IFNULL(a.rdt_posts_d1,''), IFNULL(a.rdt_posts_d7,''), IFNULL(a.rdt_total_comments_initial,''), 
                    IFNULL(a.rdt_total_comments_d1,''), IFNULL(a.rdt_total_comments_d7,''), IFNULL(a.rdt_total_scores_initial,''), IFNULL(a.rdt_total_scores_d1,''), IFNULL(a.rdt_total_scores_d7,''), IFNULL(a.rdt_top_comments_initial,''), IFNULL(a.rdt_top_comments_d1,''), 
                    IFNULL(a.rdt_top_comments_d7,''), IFNULL(a.rdt_top_score_initial,''), IFNULL(a.rdt_top_score_d1,''), IFNULL(a.rdt_top_score_d7,''), IFNULL(a.rdt_top_ratio_initial,''), IFNULL(a.rdt_top_ratio_d1,''), IFNULL(a.rdt_top_ratio_d7,''), IFNULL(a.rdt_avg_ratio_initial,''), 
                    IFNULL(a.rdt_avg_ratio_d1,''), IFNULL(a.rdt_avg_ratio_d7,'') 
                    FROM (SELECT @n:=CASE WHEN @pubdate = date(datetime) THEN @n + 1 ELSE 1 END AS n, @pubdate:=date(datetime) as date, idArticle, url, source, author, datetime, title, score, magnitude, relevancy_score,fb_reactions_initial, 
                                    fb_comments_initial, fb_shares_initial, fb_comment_plugin_initial, tw_tweets_initial, tw_favorites_initial, tw_retweets_initial, tw_top_favorites_initial, tw_top_retweets_initial, rdt_posts_initial, rdt_total_comments_initial, 
                                    rdt_total_scores_initial,rdt_top_comments_initial,rdt_top_score_initial, rdt_top_ratio_initial, rdt_avg_ratio_initial, fb_reactions_d1, fb_comments_d1, fb_shares_d1,fb_comment_plugin_d1, tw_tweets_d1, tw_favorites_d1, 
                                    tw_retweets_d1, tw_top_favorites_d1, tw_top_retweets_d1, rdt_posts_d1, rdt_total_comments_d1,rdt_total_scores_d1, rdt_top_comments_d1, rdt_top_score_d1, rdt_top_ratio_d1, rdt_avg_ratio_d1, fb_reactions_d7, fb_comments_d7, 
                                    fb_shares_d7,fb_comment_plugin_d7, tw_tweets_d7, tw_favorites_d7, tw_retweets_d7, tw_top_favorites_d7, tw_top_retweets_d7,rdt_posts_d7, rdt_total_comments_d7, rdt_total_scores_d7, rdt_top_comments_d7, rdt_top_score_d7, 
                                    rdt_top_ratio_d7, rdt_avg_ratio_d7 
                                FROM article ORDER BY date, idArticle) a
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
                        (SELECT b1.source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                        FROM source_bias b1
                        INNER JOIN
                            (SELECT source,MIN(allsides_id) min_id
                            FROM source_bias
                            GROUP BY source) b2 
                            ON b2.source=b1.source AND b1.allsides_id = b2.min_id)
                        UNION
                        (SELECT source,allsides_bias,allsides_confidence,allsides_agree,allsides_disagree,mbfs_bias,mbfs_score,factual_reporting
                        FROM source_bias
                        WHERE allsides_bias IS NULL AND mbfs_bias IS NOT NULL)) sb 
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
            if(!empty($search_query)) { $sql .= ", keywords "; }
            $sql .= "FROM article a ";
            if($mode == 'sourcebox') {
                $sql = "SELECT source, count(source) FROM (" . $sql;
            }
        }

        $conditionsExist = false; // boolean to determine whether WHERE or AND is used in query statement (if true, initial condition has already been set so subsequent conditions are prefixed with AND)

        // primary search box (text, checks title and keywords)
        if(!empty($search_query)) {
            if($mode != 'download') {  // unless we're downloading or doing a text search, we don't need to use the extra resources to gather keywords
                $sql .= 'NATURAL JOIN 
                            (SELECT idArticle, GROUP_CONCAT(keyword) as keywords FROM keyword_instances NATURAL JOIN article_keywords GROUP BY idArticle) k ';
            };
            $search_str = "WHERE (title LIKE '%$search_query%' OR keywords LIKE '%$search_query%') ";
            $conditionsExist = true;
            $sql .= $search_str;
        }

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
?>