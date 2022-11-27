/*
Copyright 2006-2009 Felix Rudolphi and Lukas Goossen
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

; Do NOT modifiy the following lines
#SingleInstance off

; functions
SafeDelete(Filename) {
	if FileExist(Filename) {
		FileSetAttrib -RHS,%Filename%
		FileDelete %Filename%
	}
}

;findPatternInPath(Path,Filter) {
;	Loop, %Path%\%Filter%,0,1
;	{
	
;	}
;}

ConvertClip(sFileTo) {
	SplitPath, sFileTo, , sDirTo, sExtTo, sNameTo
	
	DllCall("LoadLibrary", "str", "gdiplus.dll")
	VarSetCapacity(si, 16, 0), si := Chr(1)
	DllCall("gdiplus\GdiplusStartup", "UintP", pToken, "Uint", &si, "Uint", 0)

	DllCall("OpenClipboard", "Uint", 0)
	; bmp CF_BITMAP = 2 
	If (DllCall("IsClipboardFormatAvailable", "Uint", 2) && (hBM:=DllCall("GetClipboardData", "Uint", 2)))
	{
		DllCall("gdiplus\GdipCreateBitmapFromHBITMAP", "Uint", hBM, "Uint", 0, "UintP", pImage)
	}
	else If (DllCall("IsClipboardFormatAvailable", "Uint", 14) && (hBM:=DllCall("GetClipboardData", "Uint", 14)))
	{
		; CF_ENHMETAFILE = 14
		DllCall("gdiplus\CopyEnhMetaFile", "Uint", hBM, "str", sDirTo . sNameTo . ".emf")
	}

	DllCall("CloseClipboard")
	
	; go through filters to save file
	DllCall("gdiplus\GdipGetImageEncodersSize", "UintP", nCount, "UintP", nSize)
	VarSetCapacity(ci, nSize)
	DllCall("gdiplus\GdipGetImageEncoders", "Uint", nCount, "Uint", nSize, "Uint", &ci)
	Loop,   %nCount%
	{
		If (!InStr(Ansi4Unicode(NumGet(ci, 76 * (A_Index - 1) + 44)), "." . sExtTo))
		{
			 Continue
		}
		pCodec := &ci + 76 * (A_Index - 1)
		Break
	}

	If (pImage)
	{
		pCodec   ? DllCall("gdiplus\GdipSaveImageToFile", "Uint", pImage, "Uint", Unicode4Ansi(wFileTo,sFileTo), "Uint", pCodec, "Uint", 0) : DllCall("gdiplus\GdipCreateHBITMAPFromBitmap", "Uint", pImage, "UintP", hBitmap, "Uint", 0) . SetClipboardData(hBitmap), DllCall("gdiplus\GdipDisposeImage", "Uint", pImage)
	}
	
	DllCall("gdiplus\GdiplusShutdown" , "Uint", pToken)
	DllCall("FreeLibrary", "Uint", hGdiPlus)
}

Unicode4Ansi(ByRef wString, sString) {
	nSize := DllCall("MultiByteToWideChar", "Uint", 0, "Uint", 0, "Uint", &sString, "int", -1, "Uint", 0, "int", 0)
	VarSetCapacity(wString, nSize * 2)
	DllCall("MultiByteToWideChar", "Uint", 0, "Uint", 0, "Uint", &sString, "int", -1, "Uint", &wString, "int", nSize)
	Return &wString
}

Ansi4Unicode(pString) {
	nSize := DllCall("WideCharToMultiByte", "Uint", 0, "Uint", 0, "Uint", pString, "int", -1, "Uint", 0, "int",  0, "Uint", 0, "Uint", 0)
	VarSetCapacity(sString, nSize)
	DllCall("WideCharToMultiByte", "Uint", 0, "Uint", 0, "Uint", pString, "int", -1, "str", sString, "int", nSize, "Uint", 0, "Uint", 0)
	Return sString
}

SetClipboardData(hBitmap) {
	DllCall("GetObject", "Uint", hBitmap, "int", VarSetCapacity(oi,84,0), "Uint", &oi)
	hDIB :=   DllCall("GlobalAlloc", "Uint", 2, "Uint", 40+NumGet(oi,44))
	pDIB :=   DllCall("GlobalLock", "Uint", hDIB)
	DllCall("RtlMoveMemory", "Uint", pDIB, "Uint", &oi+24, "Uint", 40)
	DllCall("RtlMoveMemory", "Uint", pDIB+40, "Uint", NumGet(oi,20), "Uint", NumGet(oi,44))
	DllCall("GlobalUnlock", "Uint", hDIB)
	DllCall("DeleteObject", "Uint", hBitmap)
	DllCall("OpenClipboard", "Uint", 0)
	DllCall("EmptyClipboard")
	DllCall("SetClipboardData", "Uint", 8, "Uint", hDIB)
	DllCall("CloseClipboard")
}

;-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

;register for spz with current A_ScriptFullPath
;RegWrite, REG_SZ,HKEY_CLASSES_ROOT,`.spz,,spz-file
;RegWrite, REG_SZ,HKEY_CLASSES_ROOT,`.spz,Content Type,application/x-spz
;RegWrite, REG_SZ,HKEY_CLASSES_ROOT,spz-file,,Zipped spectrum
;RegWrite, REG_SZ,HKEY_CLASSES_ROOT,spz-file\Shell\Open\Command,,"%A_ScriptFullPath%" "`%1"

;IniRead tempPath,makro.ini,System,temp
load_ini:
SetWorkingDir %A_ScriptDir%
IniRead nconvertPath,makro.ini,System,nconvert
IniRead sevenzipPath,makro.ini,System,sevenzip
IniRead curlPath,makro.ini,System,curl

if (!FileExist(sevenzipPath) or !FileExist(curlPath))
{
	; ask for autoini, goto load_ini:
	MsgBox 4+32,Hilfsprogramme suchen / Search libraries,Soll sich das Programm automatisch konfigurieren? / Do you want to start auto-config?
	ifMsgBox Yes
	{
		RunWait %A_ScriptDir%\autoini.exe
		goto load_ini
	}
	else
	{
		ExitApp
	}
}

ifEqual 1,
{
	MsgBox Kein Startparameter / No parameter given.
	ExitApp
}

;now := A_Now
now = %A_NowUTC%
now -= 19700101000000,seconds
; cut to 8 chars for old progs
StringRight now,now,8

SetWorkingDir %A_Temp%
FileCreateDir %now%
SetWorkingDir %A_Temp%\%now%

; extract %1% to new directory in tempPath
ifNotInString 1,\
{
	; file in current folder
	zipname=%A_ScriptDir%\%1%
	filename=%1%
}
else
{
	zipname=%1%
	StringGetPos,bspos,1,\,R1

	bspos+=2
	StringMid filename,1,%bspos%
}

; fixing IE problems with file lock
FileCopy %zipname%,%zipname%`.tgz

IfNotExist %zipname%`.tgz
{
	MsgBox Rename of SPZ-File failed.
	ExitApp
}

; ungzip
; msgbox %sevenzipPath% e "%zipname%`.tgz" -aoa
RunWait %sevenzipPath% e "%zipname%`.tgz" -aoa

;untar
; msgbox %sevenzipPath% x "%A_Temp%\%now%\%filename%`.tar" -aoa
RunWait %sevenzipPath% x "%A_Temp%\%now%\%filename%`.tar" -aoa

;del tar file
SafeDelete(A_Temp . "\" . now . "\" . filename . "`.tar")

; fixing IE problems with file lock
SafeDelete(zipname . "`.tgz")

FileMove %zipname%`.tgz,%zipname%

; find out what program to call
IniRead driverCode,`.openenv,Spectrum parameters,analytics_device_driver
IniRead sessID,`.openenv,Spectrum parameters,sessionId
IniRead userAgent,`.openenv,Spectrum parameters,userAgent
IniRead dirname,`.openenv,Spectrum parameters,analytical_data_identifier
IniRead uploadURL,`.openenv,Spectrum parameters,uploadURL
IniRead fileParameters,%A_ScriptDir%\makro.ini,Parameters,%driverCode%

IniRead programPath,%A_ScriptDir%\makro.ini,Programs,%driverCode%

; cut away everything before a slash (/)
lastSlashPos := InStr(dirname,"/",false,0) + 1
dirname := SubStr(dirname, lastSlashPos)

; get file extension
lastDotPos := InStr(dirname,".",false,0) + 1
ext := SubStr(dirname, lastDotPos)

StringRight, lastSeven, dirname, 5
ifEqual lastSeven,.tar.gz
{
	ext = tar.gz
}

; msgbox %ext%

; cut away .tar.gz,.tgz,.zip
cutAwayExt = tar.gz,tgz,zip
Loop, parse, cutAwayExt, `,
{
	ifEqual ext,%A_LoopField%
	{
		dirname := SubStr(dirname,0,StrLen(dirname)-StrLen(A_LoopField)-1)
		break
	}
}

leer := " "

IfNotExist, %programPath%
{
	programPath =
	leer =
}

ifEqual fileParameters,ERROR
{
	fileParameters :=
}
; fileParameters can be \1\fid for bruker

; check for sub-experiments
FoundPos := RegExMatch(dirname,"^\d+$")

if ((driverCode = "bruker" or driverCode = "bruker_xwin" or driverCode = "acd"))
{
	if (FoundPos <> "0")
	{
		FileCreateDir %A_Temp%\%now%\spec%dirname%
		FileMoveDir %A_Temp%\%now%\%dirname%,%A_Temp%\%now%\spec%dirname%\1
		dirname = spec%dirname%
	}
	else IfNotExist %A_Temp%\%now%\%dirname%\1
	{
		; loop for other numbered dir
		loop %A_Temp%\%now%\%dirname%\*,2
		{
			if (RegExMatch(A_LoopFileShortName,"^\d+$") <> 0)
			{
				FileMoveDir %A_Temp%\%now%\%dirname%\%A_LoopFileShortName%,%A_Temp%\%now%\%dirname%\1
				break
			}
		}
	}
}

filePath = %A_Temp%\%now%\%dirname%

; check if dirname is rather a single file, strip dirname then
FileGetAttrib FileAtt,%filePath%
isSingle = 0
IfNotInString, FileAtt, D
{
	filePath = %A_Temp%\%now%
	isSingle = 1
}

IfNotExist %filePath%
{
	MsgBox Extracted folder could not be found.
	ExitApp
}

; allow modification of cmdParam
cmdParam = "%filePath%%fileParameters%"

; You CAN modifiy the following lines to adapt the script to your specific situation
file_found = 0

; run program
if (driverCode = "bruker" or driverCode = "bruker2d" or driverCode = "bruker_xwin" or driverCode = "acd")
{
	; acd only
	If (InStr(programPath,"Mestre")>0 or InStr(programPath,"MestRe")>0)
	{
		; is there an mnova-file?
		loop %filePath%\*.mnova
		{
			file_found = 1
			cmdParam = "%A_LoopFileFullPath%"
			break
		}
	}
	else If (InStr(programPath,"acd")>0 or InStr(programPath,"specman")>0)
	{
		; is there an esp-file?
		loop %filePath%\*.esp
		{
			file_found = 1
			cmdParam = "%A_LoopFileFullPath%"
			break
		}
	}
	; mnova, esp nicht gefunden
	
	; search for 2rr
	loop %filePath%\2rr.,0,1
	{
		fid_found = 1
		fidParam = %A_LoopFileFullPath%
		break
	}
	
	; no (2D) data yet, try fid
	if fid_found <> 1
	{
		ifExist %filePath%\1\fid
		{
			fid_found = 1
			fidParam = %filePath%\1\fid
		}
		else
		{
			; LMU Munich style
			loop %filePath%\fid.,0,1
			{
				fid_found = 1
				fidParam = %A_LoopFileFullPath%
				break
			}
		}
	}
	; msgbox %fidParam%
	molfile_name = %A_Temp%\%now%\molecule.mol
	
	if file_found <> 1
	{
		; msgbox %filePath%
		If fid_found = 1
		{
			If (InStr(programPath,"Mestre")>0 or InStr(programPath,"MestRe")>0)
			{
				file_found = 1
				macroname = %A_Temp%\macro.qs
				SafeDelete(macroname)
				
				targetName = %filePath%\spectrum.mnova
				StringReplace fidParam,fidParam,\,/,1
				StringReplace molfile_name,molfile_name,\,/,1
				StringReplace targetName,targetName,\,/,1
				
				FileAppend function openSaveDocument() {`r`n,%macroname%
				FileAppend var files=new Array("%fidParam%.");`r`n,%macroname%
				ifExist %molfile_name%
				{
					FileAppend files.push("%molfile_name%");`r`n,%macroname%
				}
				FileAppend serialization.open(files);`r`n,%macroname%
				FileAppend serialization.save("%targetName%"`, "mnova");`r`n,%macroname%
				FileAppend }`r`n,%macroname%
				cmdParam = "%macroname%" -sf "openSaveDocument"
			}
			else If (InStr(programPath,"acd")>0 or InStr(programPath,"specman")>0)
			{
				file_found = 1
				; create ACD macro
				macroname = %A_Temp%\1h.mcr
				SafeDelete(macroname)
				FileAppend ACD/MACRO <1D NMR> v7.0(17 Dec 2003 by "goossen")`r`n,%macroname%
				FileAppend CheckDocument (Type = "FID"; Nucleus = "Any")`r`n,%macroname%
				FileAppend WindowFunction (Method = "Exponential"; LB = 0.2)`r`n,%macroname%
				FileAppend FT (Operation = "Default")`r`n,%macroname%
				FileAppend CheckDocument (Type = "Spectrum"; Nucleus = "Any")`r`n,%macroname%
				FileAppend Phase (Method = "Simple")`r`n,%macroname%
				FileAppend BaseLine (Range = Full; Method = "Polynomial"; Order = 4)`r`n,%macroname%
				FileAppend PeakPicking (Range = Full; NoiseFactor = 3; MinHeight = 5; PosPeaks = True; NegPeaks = False)`r`n,%macroname%
				FileAppend Integration (Method = "Auto"; DetectSign = True; NegSign = True; NoiseFactor = 10; RefValue = 1)`r`n,%macroname%
				ifExist %molfile_name%
				{
					FileAppend AttachStructure (Format = "molfile"; FileName = "%molfile_name%")`r`n,%macroname%
				}
				FileAppend CopyToSketch (ReportType = "Template"; TemplateFile = "%A_ScriptDir%\1h.sk2")`r`n,%macroname%
				FileAppend SaveDocument (Dir = "%filePath%"; FileName = "%dirname%.esp"; IfExist = "Overwrite")`r`n,%macroname%
				cmdParam = /sp%fidParam%. /m%macroname%
			}
			else If (InStr(programPath,"topspin")>0)
			{
				file_found = 1
				; kopieren nach topspindata (z.B. C:\Bruker\TOPSPIN\data\guest\nmr)
				IniRead topspindata,%A_ScriptDir%\makro.ini,bruker,topspindata
				
				; so heißt das neue Verzeichnis
				topspindata = %topspindata%\%dirname%
				
				; 2 means target is overwritten if it already exists
				program = topspin
				FileMoveDir %filePath%,%topspindata%,2
				
				; no parameter
				cmdParam =
			}
			else If (InStr(programPath,"nmrproc")>0 or InStr(programPath,"winnmr")>0 or InStr(programPath,"win1d")>0)
			{
				file_found = 1
				program = win1d
				; open nmr by macro
				cmdParam = %filePath%%fileParameters%
				
				; msgbox %cmdParam%
				
				IniRead savePath,%A_ScriptDir%\makro.ini,bruker,savePath
				ifNotEqual savePath,ERROR
				{
					savePath = %savePath%\%dirname%
					FileMoveDir %filePath%,%savePath%,2
					cmdParam = %savePath%%fileParameters%
				}
				
				; msgbox %cmdParam%
				
				; make filename short
				; also check for 1r
				Loop,%cmdParam%
				{
					short_filename = %A_LoopFileShortPath%
					break
				}
				
				; msgbox %short_filename%
				
				IniRead ini_name,%A_ScriptDir%\makro.ini,bruker,ini_name
				ifEqual ini_name,ERROR
				{
					ini_name = nmrproc.ini
				}
				; msgbox %ini_name%
				
				; have the file to open as [LastFiles] f1=path in NMRPROC.INI
				IniWrite %short_filename%,%A_WinDir%\%ini_name%,LastFiles,f1
			}
			else If (InStr(programPath,"spinworks")>0 and fid_found = 1)
			{
				cmdParam = %fidParam%
			}
		}
	}
}
else ifEqual driverCode,agilent
{
	; create HP startup macro
	file_found = 1
	; is a folder, therefore 2
	Loop,%filePath%,2
	{
		short_filename = %A_LoopFileShortPath%
		break
	}
	; msgbox %short_filename%
	macroname = C:\HPCHEM\CORE\user.mac
	SafeDelete(macroname)
	FileAppend loadfile "%short_filename%",%macroname%
	cmdParam = %fileParameters%
}
else ifEqual driverCode,varian_sms
{
	; is there an sms-file?
	loop %filePath%\*.sms
	{
		cmdParam = %A_LoopFileFullPath%
		file_found = 1
		break
	}
}

if file_found <> 1
{
	Extensions = pdf,bmp,tif,tiff,pcx,wmf,emf,gif,png,jpg,jpeg,doc,docx,xls,xlsx,ppt,pps,pptx,odt,ods,odp,raw,txt,tga,rle
	Loop, parse, Extensions, `,
	{
		loop %filePath%\*.%A_LoopField%
		{
			file_found = 1
			; use shell registered app
			programPath =
			leer =
			; use registered application
			cmdParam = %A_LoopFileFullPath%
			Goto loopend1
		}
	}
}

loopend1:

; Do NOT modifiy the following lines

; msgbox X%programPath%%leer%%cmdParam%X

; bruker win1d special handling
ifEqual program,win1d
{
	; run and continue
	Run %programPath%,,,OutputVarPID
	
	; wait for window
	WinWait 1D WINNMR
	WinGet OutputVarPID,PID,1D WINNMR
	WinActivate ahk_pid %OutputVarPID%
	
	; open file through menu
	; WinMenuSelectItem ahk_pid %OutputVarPID%,,File,Open...
	SetDefaultMouseSpeed 0
	sleep 200
	
	; [Alt]+f+1
	SendInput !f1
	
	; wait until it is closed
	WinWaitClose ahk_pid %OutputVarPID%
	
	; check if savePath is set
	ifNotEqual savePath,ERROR
	{
		; msgbox %savePath%XX%A_Temp%\%now%\%dirname%
		FileMoveDir %savePath%,%A_Temp%\%now%\%dirname%
	}
}
else
{
	If (InStr(programPath,"acd")>0 or InStr(programPath,"specman")>0)
	{
		SetTimer, nagscreen, 2000
	}
	
	RunWait %programPath%%leer%%cmdParam%
}

; topspin special handling
ifEqual program,topspin
{
	;move back
	; msgbox %topspindata%,%A_Temp%\%now%
	FileMoveDir %topspindata%,%A_Temp%\%now%\%dirname%
}

; clipboard => gif
readOnlyFormats = gif,jpg,png,pdf
Loop, parse, readOnlyFormats, `,
{
	; check if file is of that kind
	ifEqual ext,%A_LoopField%
	{
		; view only, no upload
		Goto cleanup
	}
}

; start GUI
Gui, Show, W400 H100,Optionen nach f�r %dirname% der Bearbeitung/Options for %dirname% after processing
Gui, +AlwaysOnTop
Gui, Add, Button, default, Mit Grafik aus der Zwischenablage hochladen/Upload with image from clipboard
Gui, Add, Button,, Nur hochladen/Upload only
Gui, Add, Button,, �nderungen verwerfen/Discard changes
return

ButtonMitGrafikausderZwischenablagehochladen/Uploadwithimagefromclipboard:
	;msgbox "%nconvertPath%" -colors 256 -clipboard -o "%A_Temp%\%now%\image.gif" -out gif
	ifNotEqual nconvertPath,ERROR
	{
		RunWait "%nconvertPath%" -colors 256 -clipboard -o "%A_Temp%\%now%\image.gif" -out gif
	}
	else
	{
		; use builtin function, maybe less good
		ConvertClip( A_Temp . "\" . now . "\image.gif" )
	}

ButtonNurhochladen/Uploadonly:

; compress again
newSpz=%A_Temp%\%now%.spz
RunWait %sevenzipPath% a -r -tTAR %newSpz%`.tar "%A_Temp%\%now%\*"
RunWait %sevenzipPath% a -r -tGZIP %newSpz% %newSpz%`.tar

;upload
; msgbox "%curlPath%" -F spzfile=@%newSpz%;type=application/x-gzip -F mode=plain -b enventory=%sessID% -A "%userAgent%" -o "%A_Temp%\%now%`.log" "%uploadURL%"
; exitapp
retry_upload:
RunWait "%curlPath%" -F spzfile=@%newSpz%;type=application/x-gzip -F mode=plain -b enventory=%sessID% -A "%userAgent%" -o "%A_Temp%\%now%`.log" "%uploadURL%"


; check if successful
Loop,Read,%A_Temp%\%now%`.log
{
	ifEqual A_LoopReadLine,success
	{
		Goto cleanup
	}
}
msgbox 5,Speichern in der Datenbank fehlgeschlagen / Saving in the database failed,Speichern in der Datenbank fehlgeschlagen / Saving in the database failed
IfMsgBox Retry
{
	Goto retry_upload
}
ExitApp

Button�nderungenverwerfen/Discardchanges:
GuiClose:
cleanup:
; msgbox %zipname%
; delete remainders
SafeDelete(newSpz . "`.tar")
SafeDelete(newSpz)
FileRemoveDir %A_Temp%\%now%,1
SafeDelete(zipname . "`.tgz")
SafeDelete(zipname)
SafeDelete(A_Temp . "\" . now . "`.log")
ExitApp

		
nagscreen:
; remove nagscreen
title = ACD/Labs Products
ifWinExist %title%
{
	; ControlClick X325 Y261,%title%
	ControlClick OK,%title%,,,, NA
}
return