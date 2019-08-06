#include <Array.au3>
#include <SQLite.au3>
#include <GUIConstantsEx.au3>
#include <StaticConstants.au3>
#include <AutoItConstants.au3>
#include <FontConstants.au3>
#include <MsgBoxConstants.au3>
#include <GuiListBox.au3>
#include <File.au3>
#include <String.au3>


HotKeySet("{PGUP}","WriteTransferMoney");
HotKeySet("{PGDN}","ParseBankLogs");
HotKeySet("{END}","RunProgramsOnAccessControlServer"); i'm just making up these hotkeys as i need em, there's no logic to it.
Func Paste()
   Sleep(1*500);
   Send(ClipGet());
EndFunc
Func init()
   _SQLite_Startup() ; Load the DLL
   If @error Then Exit MsgBox($MB_SYSTEMMODAL, "SQLite Error", "error loading SQLite3.dll ! you can download it from https://www.sqlite.org/download.html , look for ""Precompiled Binaries for Windows"", should be named something like sqlite-dll-win32-x86-3220000.zip");
;why this isn't the default beats me...
   AutoItSetOption("MouseClickDelay",0);
   AutoItSetOption("MouseClickDownDelay",0);
   AutoItSetOption("MouseClickDragDelay",0);
   AutoItSetOption("SendKeyDelay",1);
   AutoItSetOption("SendKeyDownDelay",1);
   AutoItSetOption("WinWaitDelay",0);
   AutoItSetOption("MustDeclareVars",1);
   Global $playerDB=selectPlayer();
   Global $db=_SQLite_Open ( $playerDB,$SQLITE_OPEN_READWRITE, $SQLITE_ENCODING_UTF8 );
   If @error Then Exit MsgBox($MB_SYSTEMMODAL, "SQLite Error", "error loading " & $playerDB & " ! (_SQLite_Open failed, and i'm too lazy to add proper error description code atm)");
EndFunc
Func selectPlayer()
Local $FileList=_FileListToArray(@AppDataDir & "\Onlink\users\","*.db");
Local $GUI = GUICreate("Select agent", 300, 300) ;create gui
Local $ListBox = _GUICtrlListBox_Create($GUI, "", 0, 0,300,300) ;create listbox
For $i = 1 To $FileList[0]
    ;MsgBox(0, $i, $FileList[$i])
    _GUICtrlListBox_AddString($ListBox, StringReplace($FileList[$i],".db","",-1) )
Next

GUISetState() ;show the gui
WinSetOnTop($GUI,"",$WINDOWS_ONTOP);
Local $selected=-1
Do
   Sleep(100);
   $selected=_GUICtrlListBox_GetCurSel ( $ListBox );
Until $selected <> -1;
Local $ret=@AppDataDir & "\Onlink\users\" & _GUICtrlListBox_GetText($ListBox,$selected) & ".db";
ConsoleWriteError($ret);
GUIDelete() ; if autoit expect me to clean up all inner gui controls myself, and this technically leaks memory, here is a list of all the f*ks i give:
Return $ret;
EndFunc

Func SplashMessage($title,$message, $timeoutMS=500)
   Return 0;not working with onlink, onlink overrides WinSetOnTop, apparently...
	  Local $size=50+(StringLen($message)*15);
	  Local $hwnd=SplashTextOn($title, $message, $size, $size, -1, -1, $DLG_TEXTLEFT, "", 24)
	  WinSetOnTop($hwnd,"",$WINDOWS_ONTOP);
	  Sleep($timeoutMS);
	  SplashOff()
   EndFunc

Func e_SQLite_GetTable2d ( $hDB, $sSQL, ByRef $aResult, ByRef $iRows, ByRef $iColumns, $iCharSize = -1, $bSwichDimensions = False )
   if( _SQLite_GetTable2d ( $hDB, $sSQL,$aResult, $iRows,$iColumns, $iCharSize, $bSwichDimensions ) <> $SQLITE_OK) Then
	  Exit MsgBox(0,"SQL error","error executing: " & $sSql );
   EndIf

EndFunc

Func WriteTransferMoney()
   Local $aResult, $iRows, $iColumns;
   local $connectedIp;
   Send("{tab}" & _StringRepeat("{BACKSPACE}",30) & "waiting for db flush, hold on...");
   Do
	  sleep(100);
	  e_SQLite_GetTable2d($db, 'SELECT remotehost FROM player LIMIT 1', $aResult, $iRows, $iColumns) ;
	  $connectedIp=$aResult[1][0];
	  ; get player's account for current bank:
      e_SQLite_GetTable2d($db, 'SELECT SUBSTR(content,LENGTH((SELECT remotehost FROM player LIMIT 1))+2) FROM accounts WHERE content LIKE (SELECT remotehost FROM player LIMIT 1) || '' %'' AND suP_ref LIKE ''%(player)'' LIMIT 1',$aResult, $iRows, $iColumns) ;
	  ;_ArrayDisplay($aResult, "Results from the query");
	  ;ConsoleWrite(UBound($aResult) & @CRLF);
   Until (UBound($aResult) == 2) ; waiting for the game to flush db after entering transfer money screen.. sometimes it takes a few seconds.
   ;_ArrayDisplay($aResult, "Results from the query")
   ;_ArrayDisplay($aResult, "Results from the query");
   local $playerAccountNumber=$aResult[1][0];
   ;ConsoleWrite($playerAccount  & @CRLF );
   e_SQLite_GetTable2d($db, 'SELECT bankaccount.balance FROM bankaccount WHERE bankaccount.accountnumber=(SELECT remote.security_name FROM remote)',$aResult, $iRows, $iColumns) ;
   ;_ArrayDisplay($aResult, "Results from the query");
   Local $connectedAccountBalance=$aResult[1][0];
   e_SQLite_GetTable2d($db, 'SELECT remote.security_name FROM remote WHERE id=1',$aResult, $iRows, $iColumns) ;
   Local $connectedAccountNumber=$aResult[1][0];
   Send(_StringRepeat("{BACKSPACE}",33) & $connectedAccountNumber &  "{tab}" & $connectedIp & "{tab}" & $playerAccountNumber & "{tab}" & $connectedAccountBalance);
; get current connected bank account number: SELECT remote.security_name WHERE id=1

; get balance of current connected bank account: SELECT bankaccount.balance FROM bankaccount WHERE bankaccount.accountnumber=(SELECT remote.security_name FROM remote)

EndFunc
Func ParseBankLogs()
   Local $aResult, $iRows, $iColumns;
   local $connectedIp;
   ;Send("{tab}" & _StringRepeat("{BACKSPACE}",15) & "waiting for db flush, hold on...");
   Do
	  sleep(100);
	  e_SQLite_GetTable2d($db, 'SELECT remotehost FROM player LIMIT 1', $aResult, $iRows, $iColumns) ;
	  $connectedIp=$aResult[1][0];
	  ; get player's account for current bank:
      e_SQLite_GetTable2d($db, 'SELECT SUBSTR(content,LENGTH((SELECT remotehost FROM player LIMIT 1))+2) FROM accounts WHERE content LIKE (SELECT remotehost FROM player LIMIT 1) || '' %'' AND suP_ref LIKE ''%(player)'' LIMIT 1',$aResult, $iRows, $iColumns) ;
	  ;_ArrayDisplay($aResult, "Results from the query");
	  ;ConsoleWrite(UBound($aResult) & @CRLF);
   Until (UBound($aResult) == 2) ; waiting for the game to flush db after entering transfer money screen.. sometimes it takes a few seconds.
   ; current connected account number: SELECT remote.security_name FROM remote WHERE id=1
   ; logbank: SELECT logbank FROM bankaccount WHERE accountnumber = (SELECT remote.security_name FROM remote WHERE id=1)
   ; logs: SELECT data1,data2,data3,date,fromip,fromname FROM accesslog WHERE sup_ref = ((SELECT logbank FROM bankaccount WHERE accountnumber = (SELECT remote.security_name FROM remote WHERE id=1)) || ''(logbank)'')
   e_SQLite_GetTable2d($db,'SELECT data1 FROM accesslog WHERE sup_ref = ((SELECT logbank FROM bankaccount WHERE accountnumber = (SELECT remote.security_name FROM remote WHERE id=1)) || ''(logbank)'')',$aResult, $iRows, $iColumns);
   ;_ArrayDisplay($aResult, "Results from the query");
   Local $cur, $curL, $matchIp, $matchAccount, $sql, $aResult2,$iRows2,$iColumns2;
   For $i = 1 To $iRows Step 1
	  $curL=$aResult[$i][0];
	  ;ConsoleWrite($cur & @CRLF);
	  $cur=StringRegExp($curL,"^(\d+\.\d+\.\d+\.\d+)\ (\d+)$",$STR_REGEXPARRAYMATCH);
	  if @error Then ContinueLoop
	  $matchIp=$cur[0];
	  $matchAccount=$cur[1];
	  ;ConsoleWrite($matchIp & " - " & $matchAccount & @CRLF);
	  e_SQLite_GetTable2d($db,'SELECT * FROM accesscodes WHERE key = ' & _SQLite_Escape($curL) & ' AND sup_ref = ''1(player)''',$aResult2, $iRows2, $iColumns2);
	  ;_ArrayDisplay($aResult2, "Results from the query");
	  ConsoleWrite($iRows2);

	  if($iRows2 > 0) Then ContinueLoop
	  $sql='INSERT INTO accesscodes (content,key,sup_ref) VALUES(''?'', ' & _SQLite_Escape($curL) & ', ''1(player)'');';
	  if(_SQLite_Exec($db,$sql) <> $SQLITE_OK) Then
		 ; sigh...
		Exit MsgBox(0,"error","SQLite error executing " & $sql);
	  EndIf
   Next
   Beep();
EndFunc

Func RunProgramsOnAccessControlServer()
   ; Denial is not included.
   Local $args=[ _
	  "run File_Copier", _
	  "run Proxy_Killer", _
	  "run Firewall_Bypass", _
	  "run Password_Breaker", _
	  "run Decypher", _
	  "run B.Monitor_Killer", _
	  "run File_Deleter", _
	  "run Log_Deleter", _
	  "run Sentry_Bypass", _
	  "run Encryption_Bypass", _
	  "run Proxy_Bypass", _
	  "run Gateway_Nuke", _
	  "run Motion_Sensor", _
	  "run Defrag", _
	  "run Decrypter", _
	  "run Encrypter", _
	  "run PC_AdminMenuInject", _
	  "run PC_ConsoleEnable", _
	  "run PC_LoginEnable", _
	  "run PC_MainMenuInject", _
	  "run PC_md2Crack", _
	  "run PC_md4Crack", _
	  "run PC_md5Crack", _
	  "run PC_SHA-512Crack", _
	  "run B.Monitor_Bypass", _
	  "run Compressor", _
	  "run Decompressor", _
	  "run Encrypter", _
	  "run LAN_Agent", _
	  "run Log_UnDeleter", _
	  "run Voice_Analyser", _
	  "run IP_Lookup", _
	  "run IP_Probe", _
	  "run LAN_Probe", _
	  "run LAN_Scan", _
	  "run LAN_Spoof", _
	  "run LAN_Force", _
	  "run Trace_Tracker", _
	  "run Monitor_Bypass", _
	  "run LAN_Spike", _
	  "run VDPIN_Cracker", _
	  "run Encryption_Killer", _
	  "run Firewall_Killer", _
	  "run Sentry_Killer", _
	  "run Log_Modifier" _
   ];
   Local $iMax = UBound($args)
   Send("dir{ENTER}");
   Sleep(200);
   For $i = 0 to $iMax - 1;
	  Send($args[$i] & "{ENTER}");
	  sleep(60);
   Next
EndFunc


init();
While 1
   Sleep(100);
WEnd
