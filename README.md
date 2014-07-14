showMySQLTable
==============

Display a table from a mysql database that users can filter


Usage:
======

<?php
include_once("./showTable.php");

$showTable = new showTable();

//set database
try {
	$db = new PDO("mysql:host=localhost;port=3306;dbname=myDatabase", "username", "password");
} catch (PDOException $e) {
	die('Mysql Connection failed: ' . $e->getMessage());
}
$showTable->setDatabase($db);

//set table or view you want to display
$showTable->setTableName("my_table_or_view");

//set column names
$showTable->setColumnNames(array(
	"customerid" => "Customer Id", //csv.q2
	"customername" => "Customer Name", //csv.q1
	"branch" => "Branch", //b.name
	"salesperson" => "Sales Person", //csv.q9
	"driver" => "Driver", //csv.q10
	"datesurveyed" => "Date Surveyed", //m.upload_date
	"completed" => "Completed", //m.completed_date is not null
	"datecompleted" => "Completed Date", //m.completed_date
	"followupneeded" => "Followup Needed", //csv.q35 = 1 or csv.q38 = 1 or csv.q43 = 1
	"datefollowedup" => "Followup Date", //csv.q42
));

//set column types
$showTable->setColumnTypes(array(
	"customerid" => "string",
	"customername" => "string",
	"branch" => "string",
	"salesperson" => "string",
	"driver" => "string",
	"datesurveyed" => "date",
	"completed" => "boolean",
	"datecompleted" => "date",
	"followupneeded" => "boolean",
	"datefollowedup" => "date",
));

?>
<!DOCTYPE html>
<html>
  <head>
    <title>My table</title>
    <link rel='stylesheet' type='text/css' href='./showTable.css' />
    <script type='text/javascript' src='./showTable.js'></script>
  </head>
  <body>
    <?= $showTable->printHTML() ?>
  </body>
</html>
