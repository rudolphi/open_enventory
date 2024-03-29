<?php
/*
Copyright 2006-2018 Felix Rudolphi and Lukas Goossen
open enventory is distributed under the terms of the GNU Affero General Public License, see COPYING for details. You can also find the license under http://www.gnu.org/licenses/agpl.txt

open enventory is a registered trademark of Felix Rudolphi and Lukas Goossen. Usage of the name "open enventory" or the logo requires prior written permission of the trademark holders. 

This file is part of open enventory.

open enventory is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

open enventory is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with open enventory.  If not, see <http://www.gnu.org/licenses/>.
*/
require_once "lib_global_funcs.php";
require_once "lib_constants.php";
require_once "lib_db_query.php";
require_once "lib_db_manip_helper.php";
require_once "lib_formatting.php";
require_once "lib_rxn_pdf.php";

setGlobalVars();

pageHeader(true,false,true,false);

$results=mysql_select_array(array(
	"table" => $_REQUEST["table"], 
	"dbs" => $_REQUEST["db_id"], 
	"filter" => getLongPrimary($_REQUEST["table"])." IN(". secSQL($_REQUEST["pk"]).")", // comma-separated list of pks
	"flags" => QUERY_EDIT, 
));
$pdf=new PDF_MemImage("P","mm","A4");
foreach ($results as $result) {
	addReactionToPDF($pdf,$result);
}
$pdf->Output("test.pdf","I");
?>