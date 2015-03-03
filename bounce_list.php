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

echo "getting a list of every server with the name Public Access or Access Terminal...";
$res=$dbc->query('
SELECT `computer`,`ip`,`x`,`y` from `vlocation` WHERE `computer` LIKE \'%Public Access%\' OR `computer` LIKE \'%Access Terminal%\';
');
$res=$res->fetchAll(PDO::FETCH_ASSOC);
echo "found ".count($res,COUNT_NORMAL)." servers. done.".PHP_EOL;
echo "sorting servers based X/Y nearest server...";
$sorted_res=sort_by_xy_distance($res);
unset($res);
echo "done.".PHP_EOL;
//var_dump($sorted_res);die();
foreach($sorted_res as $server){
	echo "Adding ".$server['computer']." (".$server['ip'].")".PHP_EOL;
	$AddToListStatement->execute(array($server['ip'],$ListKey,'0(worldmap)'));
	assert($AddToListStatement->rowCount()===1);
	$HideStatement->execute(array('0',$server['ip']));
	assert($HideStatement->rowCount()===1);
	++$ListKey;
}
echo "...done".PHP_EOL;
echo "all finished. added ".$ListKey." servers in total.".PHP_EOL;


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
function sort_by_xy_distance($input_list)
{
    $ret = array();
    $a = $input_list[0];
    array_push($ret, $input_list[0]);
    $input_list[0] = null;
    $i = 1;
    for ($i = 1; $i < count($input_list); ++$i) {
        // if ($input_list[$i] == null) {
            // echo 'already added to list..';
            // continue;
        // }
        $ii = 1;
        $tmpdistance = 0;
        $nearest = array(
            'index' => -1,
            'distance' => PHP_INT_MAX
        );
        for ($ii = 1; $ii < count($input_list); ++$ii) {
            if ($input_list[$ii] == null || $ii == $i) {
                //echo 'already added to list..';
                continue;
            }
            $tmpdistance = abs($input_list[$ii]['x'] - $a['x']) + abs($input_list[$ii]['y'] - $a['y']);
			//$tmpdistance=hypot($input_list[$ii]['x'] - $a['x'] ,$input_list[$ii]['y'] - $a['y'] );
			//$tmpdistance=hypot(($input_list[$ii]['y'] - $input_list[$ii]['x']), ($a['y'] - $a['x']));
            if ($tmpdistance < $nearest['distance']) {
                $nearest['index'] = $ii;
                $nearest['distance'] = $tmpdistance;
            }
        }
        assert($nearest['index'] != -1);
        array_push($ret, $input_list[$nearest['index']]);
        $a = $input_list[$nearest['index']];
        $input_list[$nearest['index']] = null;
    }
    return $ret;
}
