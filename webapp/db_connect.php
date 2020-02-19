<?php
	// shared script to connect to database
	$connect = mysqli_connect(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASSWORD")) or die(mysqli_connect_error());
	mysqli_set_charset($connect, "utf8");
	mysqli_select_db($connect, "SupremeCourtApp") or die(mysqli_connect_error());
?>