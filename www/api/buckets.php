<?php
/**
 * GET
 * {
 *     type: string ("artists", "albums", "songs")
 *     initial_punct: boolean, isset() determines value
 *     separate_the: boolean, isset() determines value
 * }
 *
 * Error response:
 * {
 *     error_message: string
 * }
 *
 * Response:
 * {
 *     buckets: string array
 *     types: string array (albums only)
 * }
 */

if($_SERVER['REQUEST_METHOD'] !== 'GET')
	kill("Must use GET request");
 
require("../config.php");
require("../util.php");

$response = ["buckets" => []];
if(isset($_GET["char"]))
	$give_params = false;
else
	$give_params = true;

$db = new mysqli($dburl, $dbuser, $dbpass, $dbname, $dbport);
if($db->connect_errno)
	kill("Database connection failed (".$db->connect_errno."): ".$db->connect_error);

$addtypes = false;
switch($_GET["type"])
{
	case "albums":
		$table = "albums";
		$field = "title";
		$addtypes = true;
	break;
	case "artists":
		$field = "name";
		$table = "artists";
	break;
	default:
		kill("Not implemented");
	break;
}

$result = $db->query("SELECT DISTINCT LEFT($field, 1) AS a FROM $table ORDER BY a;");
if($result === false)
	kill("Error getting buckets");
$items = $result->fetch_all();

foreach($items as $row)
{
	$response["buckets"][] = $row[0];
}

if($addtypes)
{
	$result = $db->query("SHOW COLUMNS FROM $table WHERE field = 'type';");
	if($result === false)
		kill("Error getting types");
	$items = $result->fetch_all(MYSQLI_ASSOC);
	$response["types"] = parseEnumString($items[0]["Type"]);
}
	

if(isset($_GET["separate_the"]))
{
	$q = "SELECT COUNT(*) AS count FROM $table WHERE LOWER(name) REGEXP '^";
	if(isset($_GET["initial_punct"]))
		$q .= "[[:punct:]]*";
	$q .= "the.*';";
	$result = $db->query($q);
	if($result !== false)
	{
		$row = $result->fetch_assoc();
		if($row["count"] > 0)
			$response["buckets"][] = "The";
	}
}

header("Content-Type: application/json");
echo json_encode($response);
?>
