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
require_once "lib_navigation.php";

function getCostCentreParamHash($int_name,$dbs,$accNoId=null,$text=null) {
	$cost_centre_paramHash=array(
		"item" => "input", 
		"type" => "combo", 
		"int_name" => $int_name, 
		"size" => 6, 
		"maxlength" => 20, 
		
		// pk_select stuff
		"table" => "cost_centre", 
		"dbs" => $dbs, 
		"pkName" => "cost_centre", // not the real pk, but has to be like this
		"nameField" => "cost_centre", 
		"includeRawResults" => !is_null($accNoId),
	);
	if (!is_null($accNoId)) {
		$cost_centre_paramHash["onChange"]="syncAccNo(".fixQuot($int_name).",".fixQuot($accNoId).");";
	}
	
	if (!is_null($text)) {
		$cost_centre_paramHash["text"]=$text;
	}
	pk_select_getList($cost_centre_paramHash);
	return $cost_centre_paramHash;
}

function getIntNames($groups,$group) {
	return getExpArray($groups[$group],cumSum($groups,$group));
}

function getTexts($groups,$texts,$group) {
	return array_slice($texts,cumSum($groups,$group),$groups[$group]);
}

function getMaskSlice($groups,$value,$group) {
	return $value >> cumSum($groups,$group); // higher bits ignored
}

function getTriSelectSettings($paramHash) {
	$paramHash["item"]="select";
	$paramHash["int_names"]=array(-1,1,0);
	$paramHash["texts"]=array(s("default"),s("yes"),s("no"));
	return $paramHash;
}

function getTriSelectForm($paramHash) { // 0=default (not shown), 1=yes, 2=no
	$paramHash["item"]="select";
	$paramHash["int_names"]=array("",1,2);
	$paramHash["texts"]=array(s("default"),s("yes"),s("no"));
	return $paramHash;
}

function getHelperTop() { // add helper elements for subitemlist to page
	return "<span id=\"temp\" style=\"display:none\"></span><form name=\"update_queue\" id=\"update_queue\" target=\"comm\" method=\"post\"></form>";
}
function getHelperBottom() { // add helper elements for subitemlist to page which are at the end of the HTML due to z-index
	return "<div id=\"overlay\" onMouseover=\"cancelOverlayTimeout(); \" onMouseout=\"hideOverlay(); \" onDblClick=\"if (scaled_obj && is_function(scaled_obj.ondblclick)) { scaled_obj.ondblclick.call(); } \"></div>";
}

function getAnalyticalDataParamHash($for_table) {
	global $settings;
	$retval=array(
		"item" => "subitemlist", 
		"int_name" => "analytical_data", 
		"onBeforeAddLine" => "editAnalyticalData(list_int_name,UID,\"\"); return false;", 
		"addText" => s("add_spectrum"), 
		"onBeforeDelete" => "var retval=delAnalyticalData(list_int_name,UID,\"del\"); if (retval) { delMainAnalytics(list_int_name,UID); } return retval;", 
		//~ "allowReorder" => true, 
		"allowCollapse" => true, 

		"fields" => array( // Typ (NMR,...), Methode, GerÃ¤t, gemessen durch, Zuordnung reaction_chemical (Produkte zuerst), Kommentar, Links, (neue Zeile) Bild
			array("item" => "cell"),
			array("item" => "hidden", "int_name" => "analytical_data_id"),
			array("item" => "input", "int_name" => "analytical_data_identifier", DEFAULTREADONLY => true, "sortButtons" => true), // contains a copy of the name, we must deal with confilcts so that this text overrules
			array("item" => "cell"),
			array("item" => "input", "int_name" => "analytics_type_name", DEFAULTREADONLY => true, "sortButtons" => true), // contains a copy of the name, we must deal with confilcts so that this text overrules
			array("item" => "hidden", "int_name" => "analytics_type_code"),
			array("item" => "hidden", "int_name" => "analytics_type_id"),
			array("item" => "cell"),
			array("item" => "input", "int_name" => "analytics_device_name", DEFAULTREADONLY => true, "sortButtons" => true),
			array("item" => "hidden", "int_name" => "analytics_device_driver"),
			array("item" => "hidden", "int_name" => "analytics_device_id"),
			array("item" => "cell"),
			array("item" => "input", "int_name" => "analytics_method_name", DEFAULTREADONLY => true, "sortButtons" => true),
			//~ array("item" => "pk_select", "int_name" => "analytics_method_id", "nameField" => "analytics_method_name"),
			array("item" => "hidden", "int_name" => "analytics_method_id"),
			array("item" => "cell"),
			array("item" => "input", "int_name" => "measured_by", "sortButtons" => true), 
	));
	
	if ($for_table=="reaction") {
		// for reaction only
		$retval["fields"][]=array("item" => "cell");
		$retval["fields"][]=array("item" => "select", "int_name" => "reaction_chemical_uid", "sortButtons" => true); // the value is NOT the reaction_chemical_id but the UID of the line belonging to the respective reaction_chemical!! This allows to assign reaction_chemicals which are not yet in the DB. The matching is done when saving, but the rest of analytical_data is saved immediately
		$retval["fields"][]=array("item" => "cell");
		$retval["fields"][]=array("item" => "input", "int_name" => "fraction_no", "sortButtons" => true);
	}
	
	// add the rest
	$retval["fields"]=array_merge(
		$retval["fields"], 
		array(
			array("item" => "cell"),
			array("item" => "input", "int_name" => "analytical_data_comment"),
			
			array("item" => "line"),
			array("item" => "cell"),
			
			// report.txt
			array("item" => "input", "int_name" => "analytical_data_interpretation", "text" => "", "type" => "textarea", "softLineBreakAfter" => 80, "classRo" => "analytical_data_interpretation", ),
			array("item" => "hidden", "int_name" => "analytical_data_properties_blob"),
			
			array("item" => "text", "value" => "<table class=\"noborder\"><tr><td>"), 
			
			// Spektrum
			array(
				"item" => "js", 
				"int_name" => "analytical_data_graphics_blob", 
				"functionBody" => 'getAnalyticalDataImg(list_int_name,UID,int_name,values["db_id"],values["analytical_data_id"],0,a_timestamp'.($settings["disable_analytical_data_mouseover"]??false?",true":"").');', 
			),
			
			array("item" => "text", "value" => "</td></tr><tr><td>"), 
			
			// Peak-Tabelle
			array(
				"item" => "js", 
				"int_name" => "gc_peak", 
				"functionBody" => "if (values.length!=0) { updateRcUID(UID); }", 
			), // GC-Peak-Tabelle
			
			array("item" => "text", "value" => "</td></tr></table>"), 
			
			
			array("item" => "cell", "colspan" => 1, "class" => "noprint", "style" => "width:33px;", ),
			
			// default for this type (gc,hnmr,cnmr,ms)
			array("item" => "checkbox", "int_name" => "default_for_type", "value" => "1", "onChange" => "updateMainAnalytics"),
			
			// onAddLine: edit.php (new)
			// edit: open window edit.php in reduziertem Modus (keine Datensatzauswahl)
			array("item" => "button", "onClick" => "editAnalyticalData", "class" => "imgButtonSm", "img" => "lib/details_sm.png", "hideReadOnly" => true),
			// unlink: set reaction_id und reaction_chemical_id auf NULL
			array("item" => "button", "onClick" => "invokeAnalyticsEdit", "text" => s("get_analytical_data_raw_blob"), "class" => "imgButtonSm", "img" => "lib/edit_sm.png"),
			array("item" => "button", "onClick" => "invokeAnalyticsEditOrig", "text" => s("get_orig_analytical_data_raw_blob"), "class" => "imgButtonSm", "img" => "lib/reset_sm.png"),
			
			array("item" => "button", "onClick" => "refreshAnalyticalDataImgId", "text" => s("refresh"), "class" => "imgButtonSm", "img" => "lib/refresh_sm.png"),
			array("item" => "button", "onClick" => "void unlinkAnalyticalData", "text" => s("unlink_data"), "class" => "imgButtonSm", "img" => "lib/unlink_sm.png", "hideReadOnly" => true),
			// onBeforeDelete: ask, delete dataset if yes
			array("item" => "links"), 
			
			// KnÃ¶pfe und Anzeige fÃ¼r mehrere Bilder
			array("item" => "text", "ro" => "<div id=\"ro_analytical_data_~UID~_btn_image\">", "rw" => "<div id=\"analytical_data_~UID~_btn_image\">", ), 
			array("item" => "button", "onClick" => "upAnalyticalData", "class" => "imgButtonSm", "img" => "lib/up_sm.png", ),
			array("item" => "js", "int_name" => "analytical_data_image", "functionBody" => "updateAnalyticalDataImage(list_int_name,UID,value.length); ", ), 
			array("item" => "button", "onClick" => "downAnalyticalData", "class" => "imgButtonSm", "img" => "lib/down_sm.png", ),
			array("item" => "text", "value" => "</div>", ), 
		)
	);
	
	return $retval;
}

function getLiteratureParamHash() {
	// suche in neuem fenster wie für analytik
	// formatiertes Zitat | DOI | Links (Bearbeiten,Unlink,Löschen)
	return array(
		"item" => "subitemlist", 
		"int_name" => "literature", 
		"addText" => s("add_literature"), 
		"onBeforeAddLine" => "editLiterature(list_int_name,UID,\"\"); return false;", 
		"onBeforeDelete" => "return delLiterature(list_int_name,UID,\"del\");", 
		"allowCollapse" => true, 
		
		"fields" => array(
			array("item" => "cell"), 
			array("item" => "hidden", "int_name" => "literature_id"),
			array("item" => "text", "headline" => s("literature_citation")),
			array(
				"item" => "js", 
				"int_name" => "citation", 
				"functionBody" => 'getCitation(values)', 
				"onMouseover" => "showOtherCitations", 
				"onMouseout" => "hideOtherCitations", 
			),
			array(
				"item" => "js", 
				"int_name" => "other_citations", 
				VISIBLE => false, 
				"class" => "structureOverlay", 
				"functionBody" => 'getOtherCitations(values)', 
				"onMouseover" => "showOtherCitations", 
				"onMouseout" => "hideOtherCitations", 
			),
			array("item" => "cell"), 
			array("item" => "input", "int_name" => "literature_title", ),
			array("item" => "cell"), 
			array("item" => "text", "headline" => s("doi")),
			array("item" => "js", "int_name" => "doiLink", "functionBody" => "getDOILink(values)"),
			
			array("item" => "line"),
			array("item" => "cell"), 
			
			// 2nd line for pdf
			//~ array("item" => "js", "int_name" => "literature_graphics_blob", "functionBody" => "getLiteratureImg(list_int_name,UID,int_name,values[\"db_id\"],values[\"literature_id\"],a_timestamp);"), 
			array("item" => "js", "int_name" => "literature_graphics_blob", "functionBody" => "getLiteratureImgDelayed(list_int_name,UID,pos,int_name,values[\"db_id\"],values[\"literature_id\"],a_timestamp);"), 
			// delayed loading to boost performance
			
			array("item" => "cell", "colspan" => 1, "class" => "noprint"),
			array("item" => "button", "onClick" => "editLiterature", "class" => "imgButtonSm", "img" => "lib/details_sm.png", "hideReadOnly" => true),
			array("item" => "js", "int_name" => "btn_download", "functionBody" => "literature_getDownload(values);"),
			array("item" => "button", "onClick" => "refreshLiteratureImgId", "class" => "imgButtonSm", "img" => "lib/refresh_sm.png"),
			array("item" => "button", "onClick" => "void unlinkLiterature", "class" => "imgButtonSm", "img" => "lib/unlink_sm.png", "hideReadOnly" => true),
			array("item" => "js", "int_name" => "detailbutton", "functionBody" => "(readOnly==true?get_reference_link(\"literature\",values[\"db_id\"],values[\"literature_id\"]):\"\");", "class" => "noprint", "hideReadWrite" => true, ), 
			array("item" => "links"),
			
		)
	);
}

function getSplitControl(& $nextParamHash) {
	$nextParamHash[SPLITMODE]=true;
	$int_name=$nextParamHash["int_name"]??null;
	
	switch ($nextParamHash["item"]) {
	case "check":
		list($roInput,$rwInput)=getCheck($nextParamHash);
	break;
	case "checkset":
		list($roInput,$rwInput)=getCheckSet($nextParamHash);
	break;
	case "input":
		list($roInput,$rwInput)=getInput($nextParamHash);
	break;
	case "pk_select":
		list($roInput,$rwInput)=getDBSelect($nextParamHash);
	break;
	case "select":
		list($roInput,$rwInput)=getSelect($nextParamHash);
	break;
	case "text": // common TEXT also possible
		$roInput=($nextParamHash["ro"]??"").($nextParamHash["text"]??"");
		$rwInput=($nextParamHash["rw"]??"").($nextParamHash["text"]??"");
	break;
	}
	
	// wrap with span
	$roInput=startEl("","ro_".$int_name).$roInput.endEl("");
	$rwInput=startEl("","rw_".$int_name).$rwInput.endEl("");
	
	return array($roInput,$rwInput);
}

function SILgetButton($thisParamHash) { // $type,$style,$quot_list_int_name,$int_name,$noUID=false) {
	$paramHash["url"]="javascript:void ";
	$paramHash["src"]="lib/";
	
	$paramHash["text2"]=($thisParamHash["buttonText"]??null);
	$type=& $thisParamHash["type"];
	$style=& $thisParamHash["style"];
	$quot_list_int_name=& $thisParamHash["quot_list_int_name"];
	$int_name=& $thisParamHash["int_name"];
	$noUID=& $thisParamHash["noUID"];
	$multiple=& $thisParamHash["multiple"];
	
	switch ($type) {
	case "up":
		$paramHash["url"].="SILmoveUp(".$quot_list_int_name;
		$paramHash["l"]="move_up";
	break;
	case "down":
		$paramHash["url"].="SILmoveDown(".$quot_list_int_name;
		$paramHash["l"]="move_down";
	break;
	case "add_line":
		$paramHash["l"]="add_line";
		if ($multiple>1) {
			$paramHash["url"].="SILmanualAddLineMultiple(".$multiple.",".$quot_list_int_name;
			$paramHash["text1"]=$multiple;
		}
		else {
			$paramHash["url"].="SILmanualAddLine(".$quot_list_int_name;
		}
	break;
	case "del":
		$paramHash["url"].="SILmanualDelLine(".$quot_list_int_name;
		$paramHash["l"]="delete";
	break;
	}
	if (!$noUID) {
		$paramHash["url"].=",~UID~";
	}
	$paramHash["url"].=")";
	
	$paramHash["a_id"]=$int_name;
	if (!$noUID) {
		$paramHash["a_id"].="_~UID~";
	}
	$paramHash["a_id"].="_".$type;
	
	$paramHash["src"].=$type."_".strtolower($style).".png";;
	$paramHash["a_class"]="imgButton".$style;
	return getImageLink($paramHash);
}

function pk_select_getList(& $paramHash) { // einflechten der Daten in paramHash
	$paramHash["int_names"]=array();
	$paramHash["texts"]=array();
	
	if (!($paramHash["multiMode"]??false)) {
		if ($paramHash["allowAuto"]??false) {
			$paramHash["int_names"][]="-1";
			$paramHash["texts"][]=ifempty(
				$paramHash["autoText"]??null,
				s("autodetect")
			);
		}
		if ($paramHash["allowNone"]??false) {
			$paramHash["int_names"][]="";
			$paramHash["texts"][]=ifempty(
				$paramHash["noneText"]??null,
				s("none")
			);
		}
	}
	
	if ($paramHash["table"]=="other_db" && !($paramHash["skipOwn"]??false)) {
		$paramHash["int_names"][]="-1";
		$paramHash["texts"][]=s("own_database");
	}
	//~ print_r($paramHash);
	
	$results=mysql_select_array(array(
		"table" => $paramHash["table"], 
		"filterDisabled" => $paramHash["filterDisabled"]??"", 
		"dbs" => $paramHash["dbs"]??"", 
		"order_obj" => $paramHash["order_obj"]??"", 
		"filter" => $paramHash["filter"]??"", 
		"flags" => QUERY_PK_SEARCH, 
	)); // filter possible choices
	
	if ($paramHash["includeRawResults"]??false) {
		$paramHash["rawResults"]=$results;
	}
	
	for ($a=0;$a<count($results);$a++) {
		$int_names=$results[$a][ $paramHash["pkName"] ];
		if ($paramHash["table"]=="other_db" 
			&& ($paramHash["filterDisabled"] ??"")
			&& in_array($int_names,$_SESSION["other_db_disabled"])) {
			array_splice($results,$a,1);
			$a--;
			continue;
		}
		
		$paramHash["int_names"][]=$int_names;
		switch ($paramHash["table"]) {
		case "person":
			$paramHash["texts"][]=formatPersonNameCommas($results[$a]);
		break;
		case "analytics_type";
			$paramHash["texts"][]=$results[$a][ $paramHash["nameField"] ].ifnotempty(" (",$results[$a]["analytics_device_name"]??"",")");
		break;
		default:
			$displayValue=$results[$a][ $paramHash["nameField"] ];
			if (isset($paramHash["maxlength"])) {
				$displayValue=strcut($displayValue,$paramHash["maxlength"]);
			}
			$paramHash["texts"][]=$displayValue;
		}
	}
	return $results;
}

function handleColumnCount(& $paramHash) { // Warum? Damit man auch die 1. Zeile ggf mit colspan auffüllen kann
	$line_col_counts=array();
	$line_field_indices=array();
	// Berücksichtigen: line, cell, colspan, rowspan (zusätzliche zelle(n) (bei colspan) für zeilen darunter)
	$col_count=0;
	$active_line=0;
	
	for ($a=0;$a<count($paramHash["fields"]);$a++) {
		switch($paramHash["fields"][$a]["item"]) {
		case "cell":
			// colspan
			if (!isset($paramHash["fields"][$a]["colspan"])) {
				$colspan=1;
			}
			else {
				if ($paramHash["fields"][$a]["colspan"]<1) {
					$paramHash["fields"][$a]["colspan"]=1;
				}
				$colspan=$paramHash["fields"][$a]["colspan"];
			}
			
			// rowspan
			if (!isset($paramHash["fields"][$a]["rowspan"])) {
				$rowspan=1;
			}
			else {
				if ($paramHash["fields"][$a]["rowspan"]<1) {
					$paramHash["fields"][$a]["rowspan"]=1;
				}
				$rowspan=$paramHash["fields"][$a]["rowspan"];
			}
			
			for ($b=0;$b<$rowspan;$b++) {
				$idx=$active_line+$b;
				$line_col_counts[$idx]=($line_col_counts[$idx]??0)+$colspan;
			}
			
		break;
		case "line":
			$line_field_indices[$active_line]=$a;
			$active_line++;
		break;
		}
	}
	$line_field_indices[$active_line]=$a;
	// get max
	$paramHash["cols"]=max($line_col_counts);
	$paramHash["lineCount"]=$active_line+1;
	// immer die letzte Zelle einer Zeile auffüllen
	for ($b=0;$b<count($line_field_indices);$b++) {
		// letzte cell suchen, die nur in einer zeile ist
		$a=$line_field_indices[$b]-1;
		while ($paramHash["fields"][$a]["item"]!="cell" || isset($paramHash["fields"][$a]["rowspan"]) || isset($paramHash["fields"][$a]["colspan"])) {
			if ($a<($line_field_indices[$b-1]??0)) {
				continue 2; 
			}
			$a--;
		}
		$paramHash["fields"][$a]["colspan"]=(1+$paramHash["cols"]-$line_col_counts[$b]);
	}
}

function getFormFunctions(& $paramHash) { // byref to unset the definitions
	global $formFunctions;
	
	$retval="";
	for ($a=0;$a<count($formFunctions);$a++) {
		$name=& $formFunctions[$a]["name"];
		$parameters=& $formFunctions[$a]["parameters"];
		$postCode=& $formFunctions[$a]["postCode"];
		
		if (isset($paramHash[$name])) {
			$retval.="formulare[".fixStr($paramHash["int_name"])."][".fixStr($name)."]=function(".$parameters.") { ".$paramHash[$name].$postCode." };\n"; // smaller
			unset($paramHash[$name]); // save bandwidth
		}
	}
	return $retval;
}

function getControlFunctions(& $paramHash) { // byref to unset the definitions
	global $controlFunctions;
	
	$retval="";
	for ($a=0;$a<count($controlFunctions);$a++) {
		$name=& $controlFunctions[$a]["name"];
		$parameters=& $controlFunctions[$a]["parameters"];
		$postCode=& $controlFunctions[$a]["postCode"];
		
		if (isset($paramHash[$name])) {
			$retval.="controls[".fixStr($paramHash["int_name"])."][".fixStr($name)."]=function(".$parameters.") {".$paramHash[$name].$postCode."};\n";
			unset($paramHash[$name]); // save bandwidth
		}
	}
	return $retval;
}

function getRegisterControls(& $controls, & $registerControls,& $paramHash) { // Definition des Steuerelements sowie "Zusatz-"JS nach Definition des Steuerelements ausgeben
	if (is_array($paramHash)) { // set standard parameters, inherit
		// register additional stuff from functions that is set in $thisParamHash["registerControls"]
		$tempRegisterControls=getControlFunctions($paramHash).($paramHash["registerControls"]??"");
		unset($paramHash["registerControls"]); // don't save in controls as well
		
		if (!empty($paramHash["int_name"]??"")) {
			$controls[]=$paramHash["int_name"]; // to list
			$filteredParamHash=$paramHash;
			array_key_remove($filteredParamHash,array("freeControls","roInputs","rwInputs",TABLEMODE,SPLITMODE,"size","maxlength","noAutoComp","onChange","class")); // unneccessary in all cases
			$registerControls.="controls[".fixStr($paramHash["int_name"])."]=".json_encode($filteredParamHash).";\n";
		}
		$registerControls.=$tempRegisterControls;
	}
}

function prepareControl(& $paramHash) {
	if (!isset($paramHash["text"])) {
		$paramHash["text"]=s($paramHash["int_name"]??null);
	}
	
	if (!empty($paramHash["int_name"]??"")) {
		$paramHash["int_name"]=($paramHash["prefix"]??"").$paramHash["int_name"];
	}
}

function getControlText($paramHash) {
	return $paramHash["text"]??s($paramHash["int_name"]??null);
}

function getNameId($id) {
	if (is_array($id??null)) { // $paramHash
		$name=ifempty($id["name"]??null,$id["int_name"]).(($id["multiMode"]??false)?"[]":"");
		return " id=".fixStr($id["int_name"])." name=".fixStr($name);
	}
	return " id=".fixStr($id)." name=".fixStr($id); // needed for some inputs and backward compat
}

function getMultiCheck($int_name) {
	return "<input type=\"checkbox\"".getNameId("update_".$int_name)." value=\"1\" onClick=\"highlightUpdate(".fixQuot($int_name).")\">";
}

function getClass($paramHash,$readOnly=null) {
	if ($readOnly==true) {
		$className=$paramHash["classRo"]??"";
	}
	elseif ($readOnly==false) {
		$className=$paramHash["classRw"]??"";
	}
	
	if (empty($className)) {
		$className=$paramHash["class"]??"";
	}
	
	if (!empty($className)) {
		return " class=".fixStr($className);
	}
}

function getTab($tab) {
	if (is_numeric($tab)) {
		return " tabindex=".fixStr($tab);
	}
}

function getLock($allowLock) {
	if ($allowLock===FALSE) {
		return " allowLock=\"false\"";
	}
}

function startEl($tableMode,$id,$paramHash=array()) {
	$styleText="";
	$idText="";
	$colspanText="";
	
	if ($paramHash["hide"]??false) {
		$styleText=" style=\"display:none\"";
	}
	if (!empty($id)) {
		$idText=" id=".fixStr($id);
	}
	switch ($tableMode) {
	case "v":
		return "<td".$idText." class=\"noborder\"><table><tr><td>";
	break;
	case "hl":
		return "<td".$idText." class=\"noborder\"><table><tr><td><b>";
	break;
	case "h":
		if (isset($paramHash["colspan"])) {
			$colspanText=" colspan=".fixStr($paramHash["colspan"]);
		}
		return "<tr".$idText.$styleText."><td".$colspanText.">"; //  class=\"formAlignName\"
	break;
	default:
		$retval="";
		if ($tableMode=="div") {
			$retval.="<div id=\"div_".$id."\" style=\"position:absolute\">"; // restlichen style über #div_...
		}
		$retval.="<span".$idText.$styleText.">";
		return $retval;
	}
}

function middleEl($tableMode,$paramHash=array()) {
	switch ($tableMode) {
	case "v":
		return "</td></tr><tr><td>";
	break;
	case "hl":
		return "</b></td><td>";
	break;
	case "h":
		return "</td><td onClick=\"f1(event,this)\">"; //  class=\"formAlignValue\"
	break;
	}
	if ($paramHash["br"]??false) {
		return "<br/>";
	}
	else {
		return "&nbsp;";
	}
}

function endEl($tableMode) {
	switch ($tableMode) {
	case "v":
	case "hl":
		return "</td></tr></table></td>";
	break;
	case "h":
		return "</td></tr>";
	break;
	default:
		$retval="</span> ";
		if ($tableMode=="div") {
			$retval.="</div>";
		}
		return $retval;
	}
}

?>