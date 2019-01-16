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
// carbolution
$GLOBALS["code"]="carbolution";
$code=$GLOBALS["code"];

$GLOBALS["suppliers"][$code]=array(
	"code" => $code, 
	"name" => "carbolution", 
	"logo" => "logo_carbolution.png", 
	"height" => 40, 
	"vendor" => true, 
	"hasPriceList" => 3, 
	"stripChars" => "\t\r\n \0\x09", 
	"testCas" => array("18162-48-6" => array(
			array("butyldimethylchlorsilan"),
		)
	),
	"testEmpFormula" => array("C6H15ClSi" => array(
			array("butyldimethylchlorsilan"),
		)
	),

"init" => create_function('',getFunctionHeader().'
	$suppliers[$code]["urls"]["server"]="http://www.carbolution-chemicals.de"; // startPage
	$suppliers[$code]["urls"]["search"]=$urls["server"]."/shop/advanced_search_result.php?keywords=";
	$suppliers[$code]["urls"]["detail"]=$urls["server"]."/shop/product_info.php?products_id=";
	$suppliers[$code]["urls"]["startPage"]=$urls["server"];
'),
"requestResultList" => create_function('$query_obj',getFunctionHeader().'
	$retval["method"]="url";
	$retval["action"]=$suppliers[$code]["urls"]["search"].$query_obj["vals"][0][0];
	
	return $retval;
'),
"getDetailPageURL" => create_function('$catNo',getFunctionHeader().'
	if (empty($catNo)) {
		return;
	}
	return $urls["detail"].$catNo."&referrer=enventory";
'),
"getInfo" => create_function('$catNo',getFunctionHeader().'
	$url=$self["getDetailPageURL"]($catNo);
	if (empty($url)) {
		return $noConnection;
	}
	$my_http_options=$default_http_options;
	$my_http_options["redirect"]=maxRedir;
	$response=@oe_http_get($url,$my_http_options);
	if ($response==FALSE) {
		return $noConnection;
	}
	return $self["procDetail"]($response,$catNo);
'),
"getHitlist" => create_function('$searchText,$filter,$mode="ct",$paramHash=array()',getFunctionHeader().'
	$url=$urls["search"].urlencode($searchText);
	$my_http_options=$default_http_options;
	$my_http_options["redirect"]=maxRedir;
	$response=oe_http_get($url,$my_http_options);
	if ($response==FALSE) {
		return $noConnection;
	}

	return $self["procHitlist"]($response);
'),
"add_safety_clause" => create_function('& $clauses,$prefix,$text',getFunctionHeader().'
	$text=trim(strip_tags($text),$self["stripChars"]);
	
	if ($text!="" && substr($text,0,1)==$prefix) {
		$firstSpc=stripos($text," ");
		if ($firstSpc===FALSE) {
			$text=substr($text,1);
		}
		else {
			$text=substr($text,1,$firstSpc-1);
		}
		$clauses[]=$text;
	}
'),
"procDetail" => create_function('& $response,$catNo=""',getFunctionHeader().'
	$body=utf8_decode(@$response->getBody());
	if (preg_match("/(?ims)class=\"contentsTopics\"[^>]*>(.*)Vielleicht interessieren Sie sich auch/",$body,$cut)) {
		$body=$cut[1];
	}
	$body=str_replace(array("&nbsp;","&ndash;","<div id=\"PRODUKT_BESCHREIBUNG\">"),array(" ","-",""),$body);
	$elements=explode("</table>",$body);
	
	if (count($elements)>=5) {
		$result=$self["procHeadline"]($elements[0]);
		$result["molecule_names_array"][]=$result["name"];
		$body=implode("",array_slice($elements,4));
		preg_match_all("/(?ims)<(td|h1|div)[^>]*>(.*?)<\/\\\\1>/",$body,$cells,PREG_PATTERN_ORDER);
		$cells=$cells[2];
		//~ var_dump($cells);die($body);
		
		$safety_h=array();
		$safety_p=array();
		if (count($cells)) foreach ($cells as $cell) {
			$current=trim(strip_tags($cell),$self["stripChars"]);
			
			switch($current) {
			case "Identifikation":
			case "Transport (ADR)":
				$phase=0;
			break;
			case "Synonyme":
				$phase=1;
			break;
			case "Gefahrenhinweise":
				$phase=2;
			break;
			case "Sicherheitshinweise":
				$phase=3;
			break;
			default:
				switch($phase) {
				case 1:
					$result["molecule_names_array"][]=substr($current,2);
				break;
				case 2:
					$clauses=preg_split("/(?ims)<[^>]+>/",$cell);
					if (count($clauses)) foreach ($clauses as $clause) {
						$self["add_safety_clause"]($safety_h,"H",$clause);
					}
				break;
				case 3:
					$clauses=preg_split("/(?ims)<[^>]+>/",$cell);
					if (count($clauses)) foreach ($clauses as $clause) {
						$self["add_safety_clause"]($safety_p,"P",$clause);
					}
				break;
				default:
					if ($previous=="Piktogramm") {
						preg_match_all("/(?ims)alt=\"([^\"]*)\"/",$cell,$ghs,PREG_PATTERN_ORDER);
						$result["safety_sym_ghs"]=implode(",",$ghs[1]);
					}
					elseif ($current!="") {
						switch($previous) {
						case "CAS":
							$result["cas_nr"]=$current;
						break;
						case "Summenformel":
							$result["emp_formula"]=$current;
						break;
						case "Schmelzpunkt":
							list($result["mp_low"],$result["mp_high"])=getRange($current);
						break;
						case "Siedepunkt":
							list($result["bp_low"],$result["bp_high"])=getRange($current);
						break;
						case "Dichte":
							$result["density_20"]=getNumber($current);
						break;
						case "Signalwort":
							$result["safety_text"]=$current;
						break;
						default:
							if ($previous=="Sicherheitsdatenblatt" || $current=="Sicherheitsdatenblatt") {
								// German only
								if (preg_match("/(?ims)<a[^>]*href=\"([^\"]*)\"[^>]*>/",$cell,$msds_data)) {
									$result["alt_default_safety_sheet"]="";
									$result["alt_default_safety_sheet_url"]="-".$msds_data[1];
									$result["alt_default_safety_sheet_by"]=$self["name"];
								}
							}
							elseif (strpos($previous,"lmasse")!==FALSE) {
								$result["mw"]=$current;
							}
						}
					}
				}
			}
			$previous=$current;
		}
		$result["safety_h"]=implode("-",$safety_h);
		$result["safety_p"]=implode("-",$safety_p);
	}
	
	$result["supplierCode"]=$code;
	$result["catNo"]=$catNo;
	return $result;
'),
"procHeadline" => create_function('& $headline_html',getFunctionHeader().'
	$headCells=explode("</span>",$headline_html);
	if (count($headCells)>=2) {
		preg_match("/(?ims)<a[^>]*href=\".*?products_id=([^&\"]*).*?\"[^>]*>/",$headCells[0],$intCatNo_data);
		preg_match("/(?ims)\(Produkt-Nr\.: (.*?)\)/",$headCells[2],$catNo_data);
		
		return array(
			"name" => trim(strip_tags($headCells[0]),$self["stripChars"]),
			"beautifulCatNo" => $catNo_data[1],
			"catNo" => $intCatNo_data[1],
			"supplierCode" => $code, 
		);
	}
'),
"procHitlist" => create_function('& $response',getFunctionHeader().'
	$body=utf8_decode(@$response->getBody());
	if (strpos($body,"Leider ergab Ihre Suche keine Treffer")!==FALSE) {
		return $noResults;
	}
	
	$result=array();
	preg_match_all("/(?ims)<article[^>]*>.*?<a[^>]*href=\".*?products_id=([^&\"]*).*?\"[^>]*>(.*?)<\/a>.*?\(Produkt-Nr\.: (.*?)\).*?(<table.*?)<\/article>/",$body,$htmlEntries,PREG_SET_ORDER);
//~ 	print_r($htmlEntries);die();
	for ($b=0;$b<count($htmlEntries);$b++) {
		$result[$b]=array(
			"supplierCode" => $code, 
			"name" => fixTags($htmlEntries[$b][2]),
			"beautifulCatNo" => fixTags($htmlEntries[$b][3]),
			"catNo" => fixTags($htmlEntries[$b][1]),
			"price" => array()
		);
		
		preg_match_all("/(?ims)<tr.*?<\/tr>/",$htmlEntries[$b][4],$manyLines,PREG_PATTERN_ORDER);
		$manyLines=$manyLines[0];
		//~ var_dump($manyLines);die();
		
		for ($c=0;$c<count($manyLines);$c++) {
			preg_match_all("/(?ims)<td.*?<\/td>/",$manyLines[$c],$cells,PREG_PATTERN_ORDER);
			$cells=$cells[0];
			
			if (count($cells)<3) {
				continue;
			}
			
			$cell1=fixTags($cells[0]);
			if ($cell1=="Menge") {
				continue;
			}
			
			list(,$amount,$amount_unit)=getRange($cell1);
			list(,$price,$currency)=getRange(fixTags($cells[1]));
			
			$result[$b]["price"][]=array(
				"supplier" => $code, 
				"beautifulCatNo" => $result[$b]["beautifulCatNo"],
				"catNo" => $result[$b]["catNo"],
				"amount" => $amount, 
				"amount_unit" => $amount_unit, 
				"price" => $price, 
				"currency" => fixCurrency($currency), 
			);
		}
	}
//~ 	print_r($result);
	return $result;
'),
"getBestHit" => create_function('& $hitlist,$name=NULL','
	if (count($hitlist)>0) {
		return 0;
	}
')
);
$GLOBALS["suppliers"][$code]["init"]();
?>