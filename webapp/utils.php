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
            $sql = "SELECT a.idArticle, CONCAT(date(a.datetime), '_', LPAD(a.n, 3, '0')) as alt_id, a.datetime, a.source, IFNULL(sb.mbfc_bias,''), IFNULL(sb.mbfc_score,''), IFNULL(sb.mbfc_z,''), IFNULL(sb.mbfc_factual_reporting,''), IFNULL(sb.allsides_bias,''), IFNULL(sb.allsides_score,''), IFNULL(sb.allsides_z,''), IFNULL(sb.allsides_confidence,''), 
            IFNULL(sb.allsides_agree,''), IFNULL(sb.allsides_disagree,''), IFNULL(sb.mbm_score,''), IFNULL(sb.mbm_z,''), a.url, a.title, a.author, IFNULL(a.relevancy_score,''), IFNULL(a.score,''), IFNULL(a.magnitude,''), IFNULL(i.top_entity,''), IFNULL(i.top_entity_score,''), k.keywords, 
            IFNULL(sa.similarBefore,''), IFNULL(sa.similarAfter,''), IFNULL(a.fb_reactions_initial,''), IFNULL(a.fb_reactions_d1,''), IFNULL(a.fb_reactions_d7,''), IFNULL(a.fb_comments_initial,''), IFNULL(a.fb_comments_d1,''), IFNULL(a.fb_comments_d7,''), IFNULL(a.fb_shares_initial,''), 
            IFNULL(a.fb_shares_d1,''), IFNULL(a.fb_shares_d7,''), IFNULL(a.fb_comment_plugin_initial,''), IFNULL(a.fb_comment_plugin_d1,''), IFNULL(a.fb_comment_plugin_d7,''), IFNULL(a.tw_tweets_initial,''), IFNULL(a.tw_tweets_d1,''), IFNULL(a.tw_tweets_d7,''), 
            IFNULL(a.tw_favorites_initial,''), IFNULL(a.tw_favorites_d1,''), IFNULL(a.tw_favorites_d7,''), IFNULL(a.tw_retweets_initial,''), IFNULL(a.tw_retweets_d1,''), IFNULL(a.tw_retweets_d7,''), IFNULL(a.tw_top_favorites_initial,''), IFNULL(a.tw_top_favorites_d1,''), 
            IFNULL(a.tw_top_favorites_d7,''), IFNULL(a.tw_top_retweets_initial,''), IFNULL(a.tw_top_retweets_d1,''), IFNULL(a.tw_top_retweets_d7,''), IFNULL(a.rdt_posts_initial,''), IFNULL(a.rdt_posts_d1,''), IFNULL(a.rdt_posts_d7,''), IFNULL(a.rdt_total_comments_initial,''), 
            IFNULL(a.rdt_total_comments_d1,''), IFNULL(a.rdt_total_comments_d7,''), IFNULL(a.rdt_total_scores_initial,''), IFNULL(a.rdt_total_scores_d1,''), IFNULL(a.rdt_total_scores_d7,''), IFNULL(a.rdt_top_comments_initial,''), IFNULL(a.rdt_top_comments_d1,''), 
            IFNULL(a.rdt_top_comments_d7,''), IFNULL(a.rdt_top_score_initial,''), IFNULL(a.rdt_top_score_d1,''), IFNULL(a.rdt_top_score_d7,''), IFNULL(a.rdt_top_ratio_initial,''), IFNULL(a.rdt_top_ratio_d1,''), IFNULL(a.rdt_top_ratio_d7,''), IFNULL(a.rdt_avg_ratio_initial,''), 
            IFNULL(a.rdt_avg_ratio_d1,''), IFNULL(a.rdt_avg_ratio_d7,''), IFNULL(sb.mbm_pol_align_very_conservative,''), IFNULL(sb.mbm_pol_align_very_liberal,''), IFNULL(sb.mbm_pol_align_moderate,''), IFNULL(sb.mbm_pol_align_liberal,''), 
            IFNULL(sb.mbm_pol_align_conservative,''), IFNULL(sb.mbm_pol_engage_moderate,''), IFNULL(sb.mbm_pol_engage_liberal,''), IFNULL(sb.mbm_pol_engage_conservative,''), IFNULL(sb.mbm_age_young_2,''), IFNULL(sb.mbm_age_mid_aged_1,''), IFNULL(sb.mbm_age_mid_aged_2,''), 
            IFNULL(sb.mbm_age_adolescent,''), IFNULL(sb.mbm_age_old_2,''), IFNULL(sb.mbm_age_old_1,''), IFNULL(sb.mbm_age_young_1,''), IFNULL(sb.mbm_income_250k_to_350k,''), IFNULL(sb.mbm_income_75k_to_100k,''), IFNULL(sb.mbm_income_over_500k,''), IFNULL(sb.mbm_income_125k_to_150k,''), 
            IFNULL(sb.mbm_income_40k_to_50k,''), IFNULL(sb.mbm_income_150k_to_250k,''), IFNULL(sb.mbm_income_100k_to_125k,''), IFNULL(sb.mbm_income_30k_to_40k,''), IFNULL(sb.mbm_income_350k_to_500k,''), IFNULL(sb.mbm_income_50k_to_75k,''), IFNULL(sb.mbm_race_hispanic_all,''), 
            IFNULL(sb.mbm_race_other,''), IFNULL(sb.mbm_race_asian_american,''), IFNULL(sb.mbm_race_african_american,''), IFNULL(sb.mbm_gen_male,''), IFNULL(sb.mbm_gen_female,''), IFNULL(sb.mbm_edu_grad_school,''), IFNULL(sb.mbm_edu_college,''), IFNULL(sb.mbm_edu_high_school,'')
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
                SELECT source_bias.*, ROUND((allsides_score - allsides_mean)/allsides_sd,2) as allsides_z, ROUND((mbfc_score - mbfc_mean)/mbfc_sd,2) as mbfc_z, ROUND((mbm_score - mbm_mean)/mbm_sd,2) as mbm_z 
                FROM source_bias
                CROSS JOIN (
                    SELECT AVG(allsides_score) as allsides_mean, AVG(mbfc_score) as mbfc_mean, AVG(mbm_score) as mbm_mean, 
                    STD(allsides_score) as allsides_sd, STD(mbfc_score) as mbfc_sd, STD(mbm_score) as mbm_sd
                    FROM source_bias
                    WHERE source in (SELECT DISTINCT source FROM article)
                    ) agg
                WHERE source in (SELECT DISTINCT source FROM article)
            ) sb
            ON a.source = sb.source
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
            $title_str = "WHERE (MATCH(title) AGAINST ('\"$title_query\"') ";
            $conditionsExist = true;
            $sql .= $title_str;
        }


        if(!empty($text_query)) {
            $text_str = !$conditionsExist ? "WHERE (" : "$bool_search ";
            $text_str .= "MATCH(article_text) AGAINST ('\"$text_query\"') ";
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