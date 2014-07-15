showMySQLTable
==============

Display a table from a mysql database that users can filter


Usage:
======
```
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
	"id" => "Customer Id",
	"date" => "Date Entered",
	"name" => "Customer Name",
	"active" => "Active"

//set column types
$showTable->setColumnTypes(array(
	"id" => "int",
	"date" => "datetime",
	"name" => "string",
	"active" => "boolean"
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
```
