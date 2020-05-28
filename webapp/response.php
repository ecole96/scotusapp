<?php
	include("utils.php");
	include_once("db_connect.php");

	$col = array(
		0 => 'idArticle',
		1 => 'title',
		2 => 'source',
		3 => 'date'
	);

	$title_query = (!empty($_GET['title_query']) ? clean($_GET['title_query']) : '');
    $text_query = (!empty($_GET['text_query']) ? clean($_GET['text_query']) : '');
    $keyword_query = (!empty($_GET['keyword_query']) ? clean($_GET['keyword_query']) : '');
    $bool_search = !empty($_GET['bool_search']) && in_array($_GET['bool_search'],array('OR','AND')) ? $_GET['bool_search'] : 'OR';
    $dateFrom = (!empty($_GET['dateFrom']) ? clean($_GET['dateFrom']) : '');
    $dateTo = (!empty($_GET['dateTo']) ? clean($_GET['dateTo']) : '');
    $source_search = (!empty($_GET['source_search']) ? clean($_GET['source_search']) : '');
    $ID_search = (!empty($_GET['ID_search']) ? clean($_GET['ID_search']) : '');
    $sourcebox = (!empty($_GET['sourcebox']) ? $_GET['sourcebox'] : '');
	
	// grab full chunk of data
	$sql = buildQuery($connect,$title_query,$text_query,$keyword_query,$bool_search,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,'results');
	$query=mysqli_query($connect,$sql);
	$totalData=mysqli_num_rows($query);
	$totalFilter=$totalData;

	// get little chunk
	$sql .= " ORDER BY ".$col[$_REQUEST['order'][0]['column']]." ".$_REQUEST['order'][0]['dir']."  LIMIT ". $_REQUEST['start']."  ,".$_REQUEST['length'];
	$query=mysqli_query($connect,$sql);
	$data = array();
	while($row=mysqli_fetch_assoc($query)) {
	    $subdata=array();
	    $subdata[]=$row['idArticle'];
	    $article_url = "'./display_article.php?idArticle=" . $row['idArticle'] . "'";
	    $subdata[]="<a style='color:black' href={$article_url}>{$row['title']}</a>";
	    $subdata[]=$row['source'];
	    $subdata[]=$row['date'];
	    $data[]=$subdata;
	}

	$json_data=array(
	    "draw"              =>  intval($_REQUEST['draw']),
	    "recordsTotal"      =>  intval($totalData),
	    "recordsFiltered"   =>  intval($totalFilter),
	    "data"              =>  $data
	);

	echo json_encode($json_data);
?>