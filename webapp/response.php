<?php
	include("buildQuery.php");
	include_once("db_connect.php");

	$col = array(
		0 => 'idArticle',
		1 => 'title',
		2 => 'source',
		3 => 'date'
	);

	$search_query = (!empty($_GET['search_query']) ? trim($_GET['search_query']) : '');
    $dateFrom = (!empty($_GET['dateFrom']) ? $_GET['dateFrom'] : '');
	$dateTo = (!empty($_GET['dateTo']) ? $_GET['dateTo'] : '');
	$source_search = (!empty($_GET['source_search']) ? trim($_GET['source_search']) : '');
    $ID_search = (!empty($_GET['ID_search']) ? trim($_GET['ID_search']) : '');
	$sourcebox = (!empty($_GET['sourcebox']) ? $_GET['sourcebox'] : '');
	
	// grab full chunk of data
	$sql = buildQuery($connect,$search_query,$dateFrom,$dateTo,$source_search,$ID_search,$sourcebox,'results');
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