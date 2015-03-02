<?php
init();
$save_file='C:\Users\hanshenrik\AppData\Roaming\Onlink\users\newcharalias.db';

$save_file=str_replace("\\",'/',$save_file);//dont ask
$dbc=new PDO('sqlite:'.$save_file,'','',array(
PDO::ATTR_EMULATE_PREPARES => false, 
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));
echo "erasing your saved connection list.. ";
$PDOStatementH=$dbc->prepare("DELETE FROM `saved-connection` WHERE 1");
$PDOStatementH->execute();
echo "deleted ".$PDOStatementH->rowCount()." connections. done.".PHP_EOL;
$AddToListStatement=$dbc->prepare('INSERT INTO `saved-connection` (`content`,`key`,`sup_ref`) VALUES (?,?,?)');
$HideStatement=$dbc->prepare('UPDATE `vlocation` SET displayed = ? WHERE `ip` = ?');
//SELECT * FROM `vlocation` WHERE `ip` NOT LIKE `key` == 0 results.
$ListKey=0;
echo "Adding yourself to the list (technical thing, you must always be first in the list)"; 
echo "(warning: your IP is assumed to be 127.0.0.1 , so i wont actually check the database what your IP is, cause im lazy and its always been 127.0.0.1 in Uplink/Onlink.)".PHP_EOL;
$AddToListStatement->execute(array('127.0.0.1',$ListKey,'0(worldmap)'));
++$ListKey;
echo "done.".PHP_EOL;
echo "finding internic's IP... ";
$PDOStatementH=$dbc->prepare("SELECT `ip`,`key` FROM `computer` WHERE `key` LIKE ?");
$PDOStatementH->execute(array("InterNIC"));

//$rows=$PDOStatementH->rowCount();
//assert(1===$rows,'expected 1, found '.$rows.' internic\'s...');
$ip=$PDOStatementH->fetch(PDO::FETCH_ASSOC)['ip'];
assert($ip!=false,"Couldn't find the InterNIC IP! this should never happen.");
assert(false===$PDOStatementH->fetch(PDO::FETCH_ASSOC),"found more than 1 InterNIC IP! this should never happen.");
echo $ip.". done.".PHP_EOL;
echo "adding internic to the list...";
$AddToListStatement->execute(array($ip,$ListKey,'0(worldmap)'));
$HideStatement->execute(array('1',$ip));
assert($HideStatement->rowCount()===1);
++$ListKey;
echo "done.".PHP_EOL;
unset($ip);
echo "Adding every server with the name Public Access to your saved connection list.".PHP_EOL;
$PDOStatementH->execute(array("%Public Access%"));
while(false!==($row=$PDOStatementH->fetch(PDO::FETCH_ASSOC))){
	echo "Adding ".$row['key']." (".$row['ip'].")".PHP_EOL;
	$AddToListStatement->execute(array($row['ip'],$ListKey,'0(worldmap)'));
	$HideStatement->execute(array('0',$row['ip']));
	assert($HideStatement->rowCount()===1);
	++$ListKey;
}
echo "...done.".PHP_EOL;
echo "Adding every server with the name Access Terminal to your saved connection list.".PHP_EOL;
$PDOStatementH->execute(array("%Access Terminal%"));
while(false!==($row=$PDOStatementH->fetch(PDO::FETCH_ASSOC))){
	echo "Adding ".$row['key']." (".$row['ip'].")".PHP_EOL;
	$AddToListStatement->execute(array($row['ip'],$ListKey,'0(worldmap)'));
	$HideStatement->execute(array('0',$row['ip']));
	assert($HideStatement->rowCount()===1);
	++$ListKey;
}
echo "...done".PHP_EOL;
echo "all finished. added ".$ListKey." servers in total.";
die();


function init(){
	error_reporting(E_ALL);
	set_error_handler("exception_error_handler");
}
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}


