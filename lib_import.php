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

function getMoleculeFromOwnDB($cas_nr) {
	global $db;
	if ($cas_nr=="") {
		return;
	}
	$res_link=mysqli_query($db,"SELECT molecule.molecule_id FROM (molecule INNER JOIN molecule_names ON molecule.molecule_id=molecule_names.molecule_id) WHERE cas_nr LIKE ".fixStrSQL($cas_nr).";") or die(mysqli_error($db));
	if (mysqli_num_rows($res_link)>0) {
		$result=mysqli_fetch_assoc($res_link);
		return $result["molecule_id"];
	}
}

function createStorageIfNotExist($name) {
	global $db;
	$name=trim($name);
	if ($name=="") {
		return;
	}
	$res_link=mysqli_query($db,"SELECT storage_id FROM storage WHERE storage_name LIKE ".fixStr($name).";") or die(mysqli_error($db));
	if (mysqli_num_rows($res_link)==0) { // neues erstellen
		mysqli_query($db,"INSERT INTO storage (storage_id,storage_name) VALUES (NULL,".fixStr($name).");");
		return mysqli_insert_id($db);
	}
	$result=mysqli_fetch_assoc($res_link);
	return $result["storage_id"];
}

// Khoi: create person if not exist, used in import tab-separated text file
function createPersonIfNotExist($name) {
	global $db;
	$name=trim($name);
	if ($name=="") {
		return;
	}
	$res_link=mysqli_query($db,"SELECT person_id FROM person WHERE username LIKE ".fixStr($name).";") or die(mysqli_error($db));
	if (mysqli_num_rows($res_link)==0) { // create a new one
		mysqli_query($db,"INSERT INTO person (person_id,username) VALUES (NULL,".fixStr($name).");");
		return mysqli_insert_id($db);
	}
	$result=mysqli_fetch_assoc($res_link);
	return $result["person_id"];
}


function createMoleculeTypeIfNotExist($name) {
	global $db;
	$name=trim($name);
	if ($name=="") {
		return;
	}
	$res_link=mysqli_query($db,"SELECT molecule_type_id FROM molecule_type WHERE molecule_type_name LIKE ".fixStr($name).";") or die(mysqli_error($db));
	if (mysqli_num_rows($res_link)==0) { // neues erstellen
		mysqli_query($db,"INSERT INTO molecule_type (molecule_type_id,molecule_type_name) VALUES (NULL,".fixStr($name).");");
		return mysqli_insert_id($db);
	}
	$result=mysqli_fetch_assoc($res_link);
	return $result["molecule_type_id"];
}

function createChemicalStorageTypeIfNotExist($name) {
	global $db;
	$name=trim($name);
	if ($name=="") {
		return;
	}
	$res_link=mysqli_query($db,"SELECT chemical_storage_type_id FROM chemical_storage_type WHERE chemical_storage_type_name LIKE ".fixStr($name).";") or die(mysqli_error($db));
	if (mysqli_num_rows($res_link)==0) { // neues erstellen
		mysqli_query($db,"INSERT INTO chemical_storage_type (chemical_storage_type_id,chemical_storage_type_name) VALUES (NULL,".fixStr($name).");");
		return mysqli_insert_id($db);
	}
	$result=mysqli_fetch_assoc($res_link);
	return $result["chemical_storage_type_id"];
}

function repairUnit($unit) {
	$unit=str_replace(
		array("M", ), 
		array("mol/l", ), 
		$unit
	);
	return str_replace(
		array("litros", "litro", "gr", "G", "umol", "ML" ), 
		array("l", "l", "g", "g", "µmol", "ml"), 
		strtolower($unit)
	);
}

function getValue($key,$cells) {
	$idx=$_REQUEST["col_".$key];
	if (!isEmptyStr($idx)) {
		return $cells[$idx];
	}
	return $_REQUEST["fixed_".$key];
}

function importEachEntry($a, $row, $cols_molecule, $for_chemical_storage, $for_supplier_offer, $for_storage, $for_person) {
    global $db, $_REQUEST;
    $molecule=array();
    $chemical_storage=array();
    $supplier_offer=array();
    // Khoi: added for importing tab-separated text file for storage locations and users
    $storage = array();
    $person = array();
    
    $cells=$row;
    //    echo var_dump($cells);
    for ($b=0;$b<count($cells);$b++) {
        $cells[$b]=trim(autodecode($cells[$b]),$trimchars);
    }
    if ((!$for_storage && !$for_person)  // Khoi: check if it is not importing storage location or person
        && empty($cells[$_REQUEST["col_molecule_name"]]) && empty($cells[$_REQUEST["col_cas_nr"]])) {
        //		continue;
        //        echo "Missing molecule's name and CAS no!";
        return false;
    }
    
    $molecule["molecule_names_array"]=array();
    foreach ($cols_molecule as $col_molecule) {
        switch ($col_molecule) {
            case "molecule_name":
                $molecule["molecule_names_array"][]=getValue($col_molecule,$cells);
                break;
            case "alt_molecule_name":
            case "alt_molecule_name2":
            case "alt_molecule_name3":
            case "mp_high":
                list($molecule["mp_low"],$molecule["mp_high"])=getRange(getValue($col_molecule,$cells));
                break;
            case "bp_high":
                list($molecule["bp_low"],$molecule["bp_high"],$press)=getRange(getValue($col_molecule,$cells));
                if (isEmptyStr($molecule["bp_high"])) {
                    // do nothing
                }
                elseif (trim($press)!="") {
                    $molecule["bp_press"]=getNumber($press);
                    if (strpos($press,"mm")!==FALSE) {
                        $molecule["press_unit"]="torr";
                    }
                }
                else {
                    $molecule["bp_press"]="1";
                    $molecule["press_unit"]="bar";
                }
                break;
            default:
                $molecule[$col_molecule]=getValue($col_molecule,$cells);
        }
    }

    if ($for_chemical_storage) {
        $molecule["storage_name"]=getValue("storage_name",$cells);
        $molecule["order_date"]=getSQLFormatDate(getTimestampFromDate(getValue("order_date",$cells)));
        // echo "{$molecule["order_date"]}";
        $molecule["open_date"]=getSQLFormatDate(getTimestampFromDate(getValue("open_date",$cells)));
        $chemical_storage["order_date"]=getSQLFormatDate(getTimestampFromDate(getValue("order_date",$cells)));
        // echo "{$molecule["order_date"]}";
        $chemical_storage["open_date"]=getSQLFormatDate(getTimestampFromDate(getValue("open_date",$cells)));
        $chemical_storage["migrate_id_cheminstor"]=getValue("migrate_id_cheminstor",$cells);
        $chemical_storage["comment_cheminstor"]=getValue("comment_cheminstor",$cells);
        $chemical_storage["compartment"]=getValue("compartment",$cells);
        $chemical_storage["description"]=getValue("description",$cells);
        $chemical_storage["cat_no"]=getValue("cat_no",$cells);
        $chemical_storage["lot_no"]=getValue("lot_no",$cells);
        // $chemical_storage["chemical_storage_barcode"]=getValue("chemical_storage_barcode",$cells);
        $chemical_storage["chemical_storage_barcode"]=rtrim(getValue("chemical_storage_barcode",$cells));    // Khoi: fixed so that if this column is the last column in the text file, it will not add whitespace or \n character
        $molecule["supplier"]=getValue("supplier",$cells);
        $molecule["price"]=getNumber(getValue("price",$cells));
        $molecule["price_currency"]=getValue("price_currency",$cells);
    }

    $amount=str_replace(array("(", ")", ),"",getValue("amount",$cells)); // G
    if (preg_match("/(?ims)([\d\.\,]+)\s*[x\*]\s*(.*)/",$amount,$amount_data)) { // de Mendoza-Fix
        $molecule["add_multiple"]=$amount_data[1];
        $amount=$amount_data[2];
    } else {
        $molecule["add_multiple"]=ifempty(getNumber(getValue("add_multiple",$cells)),1); // J
        if ($molecule["add_multiple"]>10) { // probably an error
            $molecule["add_multiple"]=1;
        }
    }
    preg_match("/(?ims)([\d\.\,]+)\s*([a-zA-Zµ]+)/",$amount,$amount_data);
    $molecule["amount"]=fixNumber($amount_data[1]);
    $amount_data[2]=repairUnit($amount_data[2]);
    $molecule["amount_unit"]=$amount_data[2];

    // tmd
    $tmd=getValue("tmd",$cells); // G
    preg_match("/(?ims)([\d\.\,]+)\s*([a-zA-Zµ]+)/",$tmd,$tmd_data);
    $molecule["tmd"]=fixNumber($tmd_data[1]);
    $tmd_data[2]=repairUnit($tmd_data[2]);
    $molecule["tmd_unit"]=$tmd_data[2];

    $molecule["migrate_id_mol"]=getValue("migrate_id_mol",$cells); // K

    if ($for_supplier_offer) {
        $supplier_offer["so_package_amount"]=$molecule["amount"];
        if ($molecule["add_multiple"]) {
            $supplier_offer["so_package_amount"]*=$molecule["add_multiple"];
        }
        $supplier_offer["so_package_amount_unit"]=$molecule["amount_unit"];
        $supplier_offer["supplier"]=getValue("supplier",$cells);
        $supplier_offer["so_price"]=getNumber(getValue("so_price",$cells));
        $supplier_offer["so_price_currency"]=getValue("so_price_currency",$cells);
        $supplier_offer["catNo"]=getValue("catNo",$cells);
        $supplier_offer["beautifulCatNo"]=getValue("beautifulCatNo",$cells);
    }
    elseif ($for_chemical_storage) {
        $text_actual_amount=getValue("actual_amount",$cells);
        $number_actual_amount=getNumber($text_actual_amount);
        if ($number_actual_amount==="") {
            $chemical_storage["actual_amount"]="";
        }
        else {
            // does it contain any letter(s)?
            if (preg_match("/(?ims)([A-Za-zµ]+)/",$text_actual_amount,$actual_amount_unit)) {
                $actual_amount_unit=repairUnit($actual_amount_unit[1]);
                if ($actual_amount_unit==$molecule["amount_unit"]) {
                    // same unit like the nominal amount
                    $chemical_storage["actual_amount"]=$number_actual_amount; // P
                }
                else {
                    // different unit, try to calculate value
                    $act_factor=getUnitFactor($actual_amount_unit);
                    $factor=getUnitFactor($molecule["amount_unit"]);
                    if ($act_factor && $factor) { // skip if anything not found
                        if ($act_factor < $factor) { // number_actual_amount in mg (0.001), amount in g (1)
                            $chemical_storage["actual_amount"]=$number_actual_amount;
                            $molecule["amount"]*=$factor/$act_factor; // => 1000 mg
                            $molecule["amount_unit"]=$actual_amount_unit;
                        }
                        else {
                            $chemical_storage["actual_amount"]=$number_actual_amount*$act_factor/$factor;
                        }
                    }
                    //~ var_dump($molecule);
                    //~ var_dump($chemical_storage);
                    //~ die($actual_amount_unit."X".$molecule["amount_unit"]."Y".$act_factor."Z".$factor);
                }
            }
            else { // %
                $chemical_storage["actual_amount"]=$molecule["amount"]*$number_actual_amount/100; // P
            }
        }

        // purity concentration/ solvent
        if (preg_match("/(?ims)([\d\.\,]+)\s*([a-zA-Zµ\/%]+)(\sin\s)?(.*)?/",getValue("chemical_storage_conc",$cells),$concentration_data)) { // Q
            $chemical_storage["chemical_storage_conc"]=fixNumber($concentration_data[1]);
            $chemical_storage["chemical_storage_conc_unit"]=repairUnit($concentration_data[2]);
            // solvent, empty if not provided
            $chemical_storage["chemical_storage_solvent"]=$concentration_data[4];

            $chemical_storage_density_20=getValue("chemical_storage_density_20",$cells);
            if (!empty($chemical_storage_density_20)) {
                $chemical_storage["chemical_storage_density_20"]=fixNumber($chemical_storage_density_20); // R
            }
        }
    }

    // Khoi: for import text-separated text file import of storage locations
    elseif ($for_storage) {
        $storage["storage_name"] = rtrim(getValue("storage_name",$cells));
        $storage["storage_barcode"] = rtrim(getValue("storage_barcode",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        // echo "<br>lib_import, line 269 ".$storage["storage_name"];
    }
    // var_dump($storage);
    // Khoi: for import text-separated text file import of user
    elseif ($for_person) {
        $person["title"] = rtrim(getValue("title",$cells));
        $person["last_name"] = rtrim(getValue("last_name",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["first_name"] = rtrim(getValue("first_name",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["username"] = rtrim(getValue("username",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["email"] = rtrim(getValue("email",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["person_barcode"] = rtrim(getValue("person_barcode",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["new_password"] = rtrim(getValue("new_password",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["new_password_repeat"] = $person["new_password"];    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        $person["new_permission"] = rtrim(getValue("permissions",$cells));    // Khoi: rtrim() to get rid of whitespace or \n or \t at the end of the string. This happens if this is the last column in the text file
        if($person["new_permission"] == 'admin') {
            $person["permissions_general"] = array(_admin);    // 
            $person["permissions_chemical"] = array(_storage_modify, _chemical_create, _chemical_edit, _chemical_edit_own, _chemical_borrow, _chemical_inventarise, _chemical_delete, _chemical_read);    
            $person["permissions_lab_journal"] = array(_lj_read);    // allow limited search in lab journal on default
        }
        elseif (empty($person["new_permission"]) || $person["new_permission"] == 'read') {
            $person["permissions_chemical"] = array(_chemical_read, _chemical_borrow);    // allow borrowing and searching chemicals on default
            $person["permissions_lab_journal"] = array(_lj_read);    // allow limited search in lab journal on default
        }
    }

    // set_time_limit(180);
    set_time_limit(90);

    // find cas
    echo "<br>".ucfirst(s("line"))." ".($_REQUEST["skip_lines"]+$a).": ".$molecule["cas_nr"]."<br>";
    flush();
    ob_flush();
    $chemical_storage["molecule_id"]=getMoleculeFromOwnDB($molecule["cas_nr"]);
    $supplier_offer["molecule_id"]=$chemical_storage["molecule_id"];
    if ((!$for_storage && !$for_person)  // Khoi: check if it is not importing storage location or person
        && $chemical_storage["molecule_id"]=="") { // neues Molekül
        if (!empty($molecule["cas_nr"])) {
            // print warning if CAS No is not valid
            if (!isCAS($molecule["cas_nr"])) {
                echo "Warning: ".$molecule["cas_nr"]." is not valid<br>";
            }
            // echo "Molecule value is ".var_dump($molecule);
            getAddInfo($molecule); // Daten von suppliern holen, kann dauern
        }
        extendMoleculeNames($molecule);
        $oldReq=$_REQUEST;
        $_REQUEST=array_merge($_REQUEST,$molecule);
        $list_int_name="molecule_property";
        $_REQUEST[$list_int_name]=array();
        if (is_array($molecule[$list_int_name])) foreach ($molecule[$list_int_name] as $UID => $property) {
            $_REQUEST[$list_int_name][]=$UID;
            $_REQUEST["desired_action_".$list_int_name."_".$UID]="add";
            $_REQUEST[$list_int_name."_".$UID."_class"]=$property["class"];
            $_REQUEST[$list_int_name."_".$UID."_source"]=$property["source"];
            $_REQUEST[$list_int_name."_".$UID."_conditions"]=$property["conditions"];
            $_REQUEST[$list_int_name."_".$UID."_value_low"]=$property["value_low"];
            $_REQUEST[$list_int_name."_".$UID."_value_high"]=$property["value_high"];
            $_REQUEST[$list_int_name."_".$UID."_unit"]=$property["unit"];
        }
        performEdit("molecule",-1,$db);
        $chemical_storage["molecule_id"]=$_REQUEST["molecule_id"];
        $supplier_offer["molecule_id"]=$_REQUEST["molecule_id"];
        $_REQUEST=$oldReq;
    }

    if ($for_supplier_offer) {
        $oldReq=$_REQUEST;
        $_REQUEST=array_merge($_REQUEST,$supplier_offer);
        performEdit("supplier_offer",-1,$db);
        $_REQUEST=$oldReq;
    }
    elseif ($for_chemical_storage) {
        // make mass out of moles, fix for Ligon
        if (getUnitType($molecule["amount_unit"])=="n") {
            // get mw
            list($result)=mysql_select_array(array(
                "table" => "molecule",
                "filter" => "molecule.molecule_id=".fixNull($chemical_storage["molecule_id"]),
                "dbs" => -1,
                "flags" => QUERY_CUSTOM,
            ));

            // get suitable mass unit
            $mass_unit=getComparableUnit($molecule["amount_unit"],"m",$molecule["amount"]*$result["mw"]);

            // calc mass
            $molecule["amount"]=get_mass_from_amount($mass_unit,$molecule["amount"],$molecule["amount_unit"],$result["mw"]);
            $molecule["amount_unit"]=$mass_unit;
        }

        // do we have to create chemical_storage?
        if ($molecule["storage_name"]!="") {
            $chemical_storage["storage_id"]=createStorageIfNotExist($molecule["storage_name"]);
        }
        else {
            $chemical_storage["storage_id"]="";
        }
        $chemical_storage=array_merge(
            $chemical_storage,
            array_key_filter(
                $molecule,
                array(
                    "supplier",
                    "price",
                    "price_currency",
                    "comment_cheminstor",
                    "purity",
                    "amount",
                    "amount_unit",
                    "add_multiple",
                    "order_date",
                    "open_date",
                )
            )
        );
        // do we have to create storage first?
        $oldReq=$_REQUEST;
        $_REQUEST=array_merge($_REQUEST,$chemical_storage);
        performEdit("chemical_storage",-1,$db);
        $_REQUEST=$oldReq;
    }
    // Khoi: for import text-separated text file import of storage locations and user
    elseif ($for_storage) {
        // Create storage if it does not exist, 
        // return $storage["storage_id"] of the newly created storage or of the existing one
        if ($storage["storage_name"] != "") {
            $storage["storage_id"] = createStorageIfNotExist($storage["storage_name"]);
        }
        else {
            $storage["storage_id"] = "";
        }

        $oldReq=$_REQUEST;
        $_REQUEST=array_merge($_REQUEST,$storage);
        // var_dump($_REQUEST);
        $paramHash = array( "ignoreLock" => true,);
        performEdit("storage",-1,$db, $paramHash);
        $_REQUEST=$oldReq;
    }
    elseif ($for_person) {
        // Khoi: create person if not exist
        // echo "<br> lib_import, line 425<br>";
        if ($person["username"] != "") {
            $person["person_id"] = createPersonIfNotExist($person["username"]);
        }
        else {
            $person["person_id"] = "";
        }

        $oldReq=$_REQUEST;
        $_REQUEST=array_merge($_REQUEST,$person);
        // var_dump($_REQUEST);
        $paramHash = array( "ignoreLock" => true,);
        performEdit("person",-1,$db, $paramHash);
        $_REQUEST=$oldReq;
    }
}

?>
