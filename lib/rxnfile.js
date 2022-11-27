/*
Copyright 2006-2020 Felix Rudolphi and Lukas Goossen
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

// generate RXN file from molfiles

var emptyHeader="  0  0  0  0  0  0  0  0999 V2000",emptyHeaderOld="  0  0  0  0  0  0  0  0  0  0  0 V2000",headerOld="  0  0  0  0  0  0  0  0  0 V2000",bottomLine="M  END\n",emptyKetcher="\n  Ketcher  2112221192D 1   1.00000     0.00000     0\n\n  0  0  0     0  0            999 V2000\nM  END\n";
var searchAtom=/^([\-\s\d\.]{10})([\-\s\d\.]{10})([\-\s\d\.]{10}\s{1,3}[A-Z\%][a-z]?[^\d^\-]+[\d\-]+[^\d^\-]+[\d\-]+.*)$/i;

function fixMolfile(molfile) { // fix ACD molfiles
	molfile=String(molfile);
	return molfile.replace(/\$\$\$\$$/m, "M  END\n"); // find $$$$ at the end
	//~ return molfile.replace(/M  END\n\$MOL/m, "M  END\n\n\$MOL"); // make sure add line is there
}

/* function cleanMolfile(molfile) {
	// if it is a RXN, take 1st molecule
	molfile=String(molfile);
	if (molfile.substr(0,5)=="$RXN\n") {
		var rxn=new Reaction();
		rxn.setRxnfile(molfile);
		if (rxn.reactants.length) {
			return rxn.reactants[0];
		}
		if (rxn.products.length) {
			return rxn.products[0];
		}
		return "";
	}
	return molfile;
} */

function makeOldStyle(molfile) {
	var molfile=String(molfile),needle=new RegExp(emptyHeader,"g"),needle2=new RegExp(bottomLine,"g");
	molfile=molfile.replace(needle,headerOld);
	return molfile;
}

function makeNewStyle(molfile) {
	var molfile=String(molfile),needle=new RegExp(headerOld,"g"),needle2=new RegExp(bottomLine+"\n","g");
	molfile=molfile.replace(needle,emptyHeader);
	molfile=molfile.replace(needle2,bottomLine);
	return molfile;
}

function sp3(text) {
	var ret="   "+text;
	return ret.substr(ret.length-3);
}

function joinMolfiles(molfileArray,clean) {
	var rxnfile="";
	for (var b=0,max=molfileArray.length;b<max;b++) {
		if (molfileArray[b]!="" && molfileArray[b].indexOf(emptyHeaderOld)==-1) {
			rxnfile+="$MOL|"+addPipes(molfileArray[b]);
		}
		else if (!clean) {
			rxnfile+="$MOL||open enventory Thu, 26.06.2008 16:29:21||"+emptyHeader+"|M  END|";
		}
	}
	return rxnfile;
}

function isEmptyMolfile(molfile) {
	if (molfile=="" || molfile.indexOf(emptyHeaderOld)!=-1) {
		return true;
	}
	return false;
}

function getEmptyMolfile() {
	return "\n\n" + emptyHeader + "\n" + bottomLine;
}

function countMolfiles(molfileArray,clean) {
	if (clean) {
		var count=0;
		for (var b=0,max=molfileArray.length;b<max;b++) {
			if (isEmptyMolfile(molfileArray[b])==false) {
				count++;
			}
		}
		return count;
	}
	else {
		return molfileArray.length;
	}
}

function createRxnfile(reactants,products,clean) { // read arrays of molfiles, write RXN
	var rxnfile="";
	rxnfile="$RXN|||open enventory|"+sp3(countMolfiles(reactants,clean))+sp3(countMolfiles(products,clean))+"|"+joinMolfiles(reactants.concat(products),clean);
	return rxnfile;
}

function cleanRxnfile(rxnfile) { // remove 0 atom parts from rxnfile
	var rxn=new Reaction();
	rxn.setRxnfile(rxnfile);
	//~ alert(rxn.reactants.length+" "+rxn.products.length);
	if (rxn.reactants.length==0 && rxn.products.length==0) {
		return "";
	}
	var retval=rxn.getRxnfile(true);
	return retval;
}

// split RXN file into molfiles

function setReaction(rxnfile) {
	//~ rxnfile=escape(removePipes(rxnfile));
	//~ var molfiles=rxnfile.split("%24MOL%0A");
	this.reactants=[]; // required to handle 0
	this.products=[]; // required to handle 0
	rxnfile=removePipes(rxnfile);
	if (rxnfile=="") { // empty
		return;
	}
	if (rxnfile.indexOf("$MOL\n")==-1 && rxnfile.indexOf("END\n")!=-1) { // molfile
		this.reactants[0]=rxnfile;
		return;
	}
	var molfiles=rxnfile.split("$MOL\n");
	var header=molfiles.shift();
	for (var b=0,maxb=molfiles.length;b<maxb;b++) {
		molfiles[b]=unescape(molfiles[b]);
	}
	var header_lines=header.split("\n");
	var countsLine=unescape(header_lines[ header_lines.length-2 ]);
	var reactants_length=parseInt(countsLine.substr(0,3)),products_length=parseInt(countsLine.substr(3,3)),reagents_length=parseInt(countsLine.substr(6,3));
	if (reagents_length>0) {
		// reorder, add to reactants
		var reagent_molecules=molfiles.splice(reactants_length+products_length);
		molfiles.splice(reactants_length,0,reagent_molecules);
		
		reactants_length+=reagents_length;
	}
	var result,offsetX=0.0,reactionCenterY,aX,aY;
	for (var b=0,maxb=molfiles.length;b<maxb;b++) {
		// cleanly arrange without overlaps in x
		var lines=molfiles[b].split("\n");
		// determine min and max y value
		var minX=Number.MAX_VALUE,maxX=Number.MIN_VALUE,minY=Number.MAX_VALUE,maxY=Number.MIN_VALUE,centerY;
		for (var c=0,maxc=lines.length;c<maxc;c++) {
			if  (result=searchAtom.exec(lines[c])) {
				aX=parseFloat(trim(result[1]));
				aY=parseFloat(trim(result[2]));
				if (isNaN(aX) || isNaN(aY)) continue;
				minX=Math.min(minX,aX);
				minY=Math.min(minY,aY);
				maxX=Math.max(maxX,aX);
				maxY=Math.max(maxY,aY);
			}
		}
		centerY=0.5*(minY+maxY);
		if (reactionCenterY==undefined) {
			reactionCenterY=centerY;
		}
		if (b==reactants_length) {
			// add space for arrow
			offsetX+=40.0;
		}
		// reposition
		for (var c=0,maxc=lines.length;c<maxc;c++) {
			if  (result=searchAtom.exec(lines[c])) {
				var replacement=sp(parseFloat(trim(result[1]))-minX+offsetX,10)+sp(parseFloat(trim(result[2]))-centerY+reactionCenterY,10)+result[3];
				lines[c]=replacement;
			}
		}
		offsetX+=maxX-minX+15.0; // add width of molfiles[b]
		molfiles[b]=lines.join("\n");
	}
	this.reactants=molfiles.slice(0,reactants_length);
	this.products=molfiles.slice(reactants_length,reactants_length+products_length);
}

// little bit of OO

function createRxnfileFromReactionObj(clean) {
	return createRxnfile(this.reactants,this.products,clean);
}

function Reaction() {
	this.reactants=[];
	this.products=[];
	this.getRxnfile=createRxnfileFromReactionObj;
	this.setRxnfile=setReaction;
}


// applet related stuff

function getAppletMol(appletName,force) {
	if (force==undefined) {
		force=molApplet;
	}
	return getApplet(appletName,force);
}

function getAppletRxn(appletName,force) {
	if (force==undefined) {
		force=rxnApplet;
	}
	return getApplet(appletName,force);
}

function getApplet(appletName,force) {
	switch (force) {
	case "":
	case "ketcher":
	case "ketcher2":
	case "MarvinJS":
	case "ChemDoodle":
	case "VectorMol":
		var editorFrame=document.getElementById(appletName);
		
		if ('contentDocument' in editorFrame) {
			return editorFrame.contentWindow;
		}
		else {// IE7
			return document.frames[appletName].window;
		}
	break;
	case "text":
		return $(appletName);
	break;
	}
}


function putMolfile(appletName,molfile,force) {
	if (force==undefined) {
		force=molApplet;
	}
	var obj=getAppletMol(appletName,force);
	if (obj) {
		switch (force) {
		case "MarvinJS":
			obj=(obj.marvin.sketcherInstance||obj.marvin.sketch);
			obj.importAsMol(removePipes(molfile));
		break;
		case "":
		case "VectorMol":
			obj.setMolRxnfile(molfile);
		break;
		case "ketcher":
		case "ketcher2":
			molfile=molfile||emptyKetcher;
			obj.ketcher.setMolecule(removePipes(molfile));
		break;
		case "ChemDoodle":
			molfile=molfile||getEmptyMolfile();
			var molecule=obj.ChemDoodle.readMOL(removePipes(molfile));
			obj.sketcher.loadMolecule(molecule);
		break;
		case "text":
			obj.value=removePipes(molfile);
		break;
		}
	}
}

function putRxnfile(appletName,molfile,force) {
	if (force==undefined) {
		force=rxnApplet;
	}
	var obj=getAppletRxn(appletName,force);
	if (obj) {
		switch (force) {
		case "":
		case "VectorMol":
			obj.setMolRxnfile(molfile);
		break;
		case "ketcher":
		case "ketcher2":
			molfile=molfile||emptyKetcher;
			obj.ketcher.setMolecule(removePipes(cleanRxnfile(molfile)));
		break;
		case "ChemDoodle":
			molfile=molfile||getEmptyMolfile();
			var molShapeData=obj.ChemDoodle.readRXN(removePipes(molfile),1);
			obj.sketcher.loadContent(molShapeData.molecules,molShapeData.shapes);
		break;
		case "text":
			obj.value=removePipes(molfile);
		break;
		}
	}
}

function getMolfile(appletName,force) {
	if (force==undefined) {
		force=molApplet;
	}
	var obj=getAppletMol(appletName,force),retval="";
	if (obj) {
		switch (force) {
		case "MarvinJS":
			obj=(obj.marvin.sketcherInstance||obj.marvin.sketch);
			retval=obj.exportAsMol();
		break;
		case "":
		case "VectorMol":
			retval=obj.getMolRxnfile();
		break;
		case "ketcher":
		case "ketcher2":
			retval=obj.ketcher.getMolfile();
		break;
		case "ChemDoodle":
			var molecules=obj.sketcher.getMolecules(),molecule=molecules[0];
			for (var i=1,iMax=molecules.length;i<iMax;i++) {+
				// combine all into one, what a crap
				Array.prototype.push.apply(molecule.atoms,molecules[i].atoms);
				Array.prototype.push.apply(molecule.bonds,molecules[i].bonds);
				Array.prototype.push.apply(molecule.rings,molecules[i].rings);
			}
			retval=obj.ChemDoodle.writeMOL(molecule)+"\n";
		break;
		case "text":
			retval=obj.value;
		break;
		}
		return fixMolfile(retval);
	}
}

function getRxnfile(appletName,force) {
	if (force==undefined) {
		force=rxnApplet;
	}
	var obj=getAppletRxn(appletName,force),retval="";
	if (obj) {
		switch (force) {
		case "":
		case "VectorMol":
			retval=obj.getMolRxnfile();
		break;
		case "ketcher":
		case "ketcher2":
			retval=obj.ketcher.getMolfile();
		break;
		case "ChemDoodle":
			retval=obj.ChemDoodle.writeRXN(obj.sketcher.getMolecules(),obj.sketcher.getShapes())+"\n";
		break;
		case "text":
			retval=obj.value;
		break;
		}
		return fixMolfile(retval);
	}
}

function isAppletReady(appletName,mode,force) {
	if (force==undefined) {
		if (mode=="rxn") {
			force=rxnApplet;
		}
		else {
			force=molApplet;
		}
	}
	var obj=getApplet(appletName,force);
	if (obj) {
		switch (force) {
		case "":
		case "VectorMol":
			if (obj) {
				return obj.isReady;
			}
		break;
		case "ketcher":
			if (obj.ui) {
				return obj.ui.initialized;
			}
		break;
		case "ketcher2":
			return (obj && obj.ketcher && is_function(obj.ketcher.setMolecule));
		break;
		case "ChemDoodle":
			if (obj.ChemDoodle && obj.sketcher) {
				return is_function(obj.ChemDoodle.getVersion);
			}
		break;
		case "MarvinJS":
			if (obj.marvin && (obj=(obj.marvin.sketcherInstance||obj.marvin.sketch))) {
				return is_function(obj.exportAsMol);
			}
		break;
		case "text":
		default:
			return true;
		}
	}
	return false;
}
