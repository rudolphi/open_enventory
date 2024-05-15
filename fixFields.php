<?php
require_once "lib_global_funcs.php";
require_once "lib_global_settings.php";
require_once "lib_db_manip.php";
require_once "lib_db_query.php";
require_once "lib_root_funcs.php";
require_once "lib_constants_tables.php";

pageHeader(); // must login as root
echo "</head><body><pre>";

$ret_val=getDatabases($db);
print_r($ret_val);
/*
if ($result=mysqli_query($db,"SHOW DATABASES;")) {
	$totalCount=mysqli_num_rows($result);
	$ret_val=array();

	for($a=0;$a<$totalCount;$a++) { // Datenbanken durchgehen
		$temp=mysqli_fetch_array($result,MYSQLI_ASSOC);
		if (!in_array(strtolower($temp["Database"]),$forbidden_db_names)) {
			$ret_val[]=$temp["Database"];
		}
	}
	print_r($ret_val);
	mysqli_free_result($result);
}
*/
set_time_limit(0);

for ($f=0;$f<arrCount($ret_val);$f++) {
	//~ $db_name=$ret_val[$f];
	$db_name=$ret_val[$f]["name"];
	echo "<h1>".$db_name."</h1>";
	switchDB($db_name,$db);
	//~ mysqli_query($db,"USE ".$db_name);
	//~ mysqli_query($db,"SET CHARACTER SET utf8;");
	//~ mysqli_query($db,"SET NAMES utf8;");
	
	//~ $db_type=getGVar("Database");
	$db_type=$ret_val[$f]["type"];
	if (!in_array($db_type,array(db_type))) { // check if database type fits
		continue;
	}
	
	//~ if ($_REQUEST["perform"] && $db_type=="") {
		//~ setDBtype();
	//~ }
	
	//~ $version=getGVar("Version");
	$version=$ret_val[$f]["version"];
	updateCurrentDatabaseFormat($_REQUEST["perform"]??false);
	
	// bring to innodb
	if ($result=mysqli_query($db,"SHOW TABLE STATUS WHERE engine LIKE \"MyIsam\";")) {
		$totalCount=mysqli_num_rows($result);
		$ret_val2=array();
		for($a=0;$a<$totalCount;$a++) {
			$ret_val2[$a]=mysqli_fetch_array($result,MYSQLI_ASSOC);
		}
		mysqli_free_result($result);
	}
	$sql_query=array();
	for ($a=0;$a<arrCount($ret_val2);$a++) {
		set_time_limit(0);
		
		$sql_query[]="ALTER TABLE ".$ret_val2[$a]["Name"]." ENGINE = InnoDB;";
	}
	
	// Custom modifications
	$sql_query=array_merge($sql_query,getUpdateSQL($version));
	if (count($sql_query)) {
		echo "<pre>".print_r($sql_query,true)."</pre>";
		if ($_REQUEST["perform"]??false) {
			performQueries($sql_query,$db); // ignore errors
			/*
			mysqli_query($db,"TRUNCATE units;"); // ignore errors
			createDefaultTableEntries("units");
			mysqli_query($db,"TRUNCATE class;"); // ignore errors
			createDefaultTableEntries("class");
			*/
		}
	}
	
	if ($_REQUEST["perform"]??false) {
		updateFrom($version);
		setupInitTables($db_name); // update version
		
		// write units and classes new
		refreshUnitsClasses($db);

		sleep(2); // maybe this fixes exisiting permission problems
		$generated_passwords=refreshUsers();
		displayPasswordsHtml($generated_passwords);
	}
}
echo "</pre></body></html>";

?>