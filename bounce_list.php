<?php
declare(strict_types = 1);
init ();
$save_file = 'C:\Users\aa\AppData\Roaming\Onlink\users\kira.db';
$save_file = str_replace ( "\\", '/', $save_file ); // dont ask
$dbc = new PDO ( 'sqlite:' . $save_file, '', '', array (
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION 
) );
$dbc->beginTransaction ();
echo "erasing your saved connection list.. ";
$PDOStatementH = $dbc->prepare ( "DELETE FROM `saved-connection` WHERE 1" );
$PDOStatementH->execute ();
echo "deleted " . $PDOStatementH->rowCount () . " connections. done." . PHP_EOL;
$AddToListStatement = $dbc->prepare ( 'INSERT INTO `saved-connection` (`content`,`key`) VALUES (?,?)' );
$HideStatement = $dbc->prepare ( 'UPDATE `vlocation` SET displayed = ? WHERE `ip` = ?' );
// SELECT * FROM `vlocation` WHERE `ip` NOT LIKE `key` == 0 results.
$ListKey = 0;
echo "Adding yourself to the list (technical thing, you must always be first in the list)";
echo "(warning: your IP is assumed to be 127.0.0.1 , so i wont actually check the database what your IP is, cause im lazy and its always been 127.0.0.1 in Uplink/Onlink.)" . PHP_EOL;
$AddToListStatement->execute ( array (
		'127.0.0.1',
		$ListKey 
) );
++ $ListKey;
echo "done." . PHP_EOL;
echo "finding internic's IP... ";
$PDOStatementH = $dbc->prepare ( "SELECT `ip`,`key` FROM `computer` WHERE `key` LIKE ?" );
$PDOStatementH->execute ( array (
		"InterNIC" 
) );
// $rows=$PDOStatementH->rowCount();
// assert(1===$rows,'expected 1, found '.$rows.' internic\'s...');
$ip = $PDOStatementH->fetch ( PDO::FETCH_ASSOC ) ['ip'];
assert ( $ip != false, "Couldn't find the InterNIC IP! this should never happen." );
assert ( false === $PDOStatementH->fetch ( PDO::FETCH_ASSOC ), "found more than 1 InterNIC IP! this should never happen." );
echo $ip . ". done." . PHP_EOL;
echo "adding internic to the list...";
$AddToListStatement->execute ( array (
		$ip,
		$ListKey 
) );
$HideStatement->execute ( array (
		'1',
		$ip 
) );
assert ( $HideStatement->rowCount () === 1 );
++ $ListKey;
echo "done." . PHP_EOL;
unset ( $ip );
echo "getting a list of every server with the name Public Access or Access Terminal...";
$res = $dbc->query ( '
SELECT `computer`,`ip`,`x`,`y` FROM `vlocation` WHERE `computer` LIKE \'%Public Access%\' OR `computer` LIKE \'%Access Terminal%\'
;
' );
$res = $res->fetchAll ( PDO::FETCH_ASSOC );
echo "found " . count ( $res, COUNT_NORMAL ) . " servers. total walking distance:" . calculate_total_xy_distance ( $res ) . ".. done." . PHP_EOL;
echo "sorting servers based X/Y nearest server...";
$sorted_res = sort_by_xy_distance ( $res );
unset ( $res );
echo "new total walking distance:" . calculate_total_xy_distance ( $sorted_res ) . "..";
echo "done." . PHP_EOL;
// var_dump($sorted_res);die();
foreach ( $sorted_res as $server ) {
	echo "Adding " . $server ['computer'] . " (" . $server ['ip'] . ")" . PHP_EOL;
	$AddToListStatement->execute ( array (
			$server ['ip'],
			$ListKey 
	) );
	assert ( $AddToListStatement->rowCount () === 1 );
	$HideStatement->execute ( array (
			'0',
			$server ['ip'] 
	) );
	assert ( $HideStatement->rowCount () === 1 );
	++ $ListKey;
}
echo "...done, commiting.." . PHP_EOL;
$dbc->commit ();
echo "all finished. added " . $ListKey . " servers in total." . PHP_EOL;
function init() {
	error_reporting ( E_ALL );
	set_error_handler ( "exception_error_handler" );
	ini_set ( "log_errors", '1' );
	ini_set ( "display_errors", '1' );
	ini_set ( "log_errors_max_len", '0' );
	ini_set ( "error_prepend_string", '<error>' );
	ini_set ( "error_append_string", '</error>' . PHP_EOL );
	ini_set ( "error_log", __DIR__ . '/error_log.php' );
}
function exception_error_handler($errno, $errstr, $errfile, $errline) {
	if (! (error_reporting () & $errno)) {
		// This error code is not included in error_reporting
		return;
	}
	throw new ErrorException ( $errstr, 0, $errno, $errfile, $errline );
}
function sort_by_xy_distance(array $input_list) {
	$ret = array ();
	$a = $input_list [0];
	array_push ( $ret, $input_list [0] );
	$input_list [0] = null;
	$i = 1;
	for($i = 1; $i < count ( $input_list ); ++ $i) {
		// if ($input_list[$i] == null) {
		// echo 'already added to list..';
		// continue;
		// }
		$ii = 1;
		$tmpdistance = 0;
		$nearest = array (
				'index' => - 1,
				'distance' => PHP_INT_MAX 
		);
		for($ii = 1; $ii < count ( $input_list ); ++ $ii) {
			if ($input_list [$ii] == null || $ii == $i) {
				// echo 'already added to list..';
				continue;
			}
			$tmpdistance = abs ( $input_list [$ii] ['x'] - $a ['x'] ) + abs ( $input_list [$ii] ['y'] - $a ['y'] );
			// $tmpdistance=hypot($input_list[$ii]['x'] - $a['x'] ,$input_list[$ii]['y'] - $a['y']);
			if ($tmpdistance < $nearest ['distance']) {
				$nearest ['index'] = $ii;
				$nearest ['distance'] = $tmpdistance;
			}
		}
		assert ( $nearest ['index'] != - 1 );
		array_push ( $ret, $input_list [$nearest ['index']] );
		$a = $input_list [$nearest ['index']];
		$input_list [$nearest ['index']] = null;
	}
	return $ret;
}
function calculate_total_xy_distance(array $input_list) {
	$ret = 0;
	$startX = $input_list [0] ['x'];
	$startY = $input_list [0] ['y'];
	$i = 1;
	for($i = 1; $i < count ( $input_list, COUNT_NORMAL ); ++ $i) {
		$ret += abs ( $input_list [$i] ['x'] - $startX ) + abs ( $input_list [$i] ['y'] - $startY );
		// $ret+=hypot($input_list[$i]['x'] - $startX ,$input_list[$i]['y'] - $startY);
		$startX = $input_list [$i] ['x'];
		$startY = $input_list [$i] ['y'];
	}
	return $ret;
}
// warning: probably will use a lot of ram..
function generate_list_for_dijkstra(array $input_list) {
	/*
	 * $graph_array = array(
	 * array("a", "b", 7),
	 * array("a", "c", 9),
	 * array("a", "f", 14),
	 * array("b", "c", 10),
	 * array("b", "d", 15),
	 * array("c", "d", 11),
	 * array("c", "f", 2),
	 * array("d", "e", 6),
	 * array("e", "f", 9)
	 * );
	 */
	$ret = array ();
	$i = 0;
	$max = count ( $input_list, COUNT_NORMAL );
	for($i = 0; $i < $max; ++ $i) {
		for($ii = $i + 1; $ii < $max; ++ $ii) {
			$tmparr = array ();
			$tmparr [0] = $input_list [$i] ['ip'];
			$tmparr [1] = $input_list [$ii] ['ip'];
			$tmparr [2] = hypot ( $input_list [$ii] ['x'] - $input_list [$i] ['x'], $input_list [$ii] ['y'] - $input_list [$i] ['y'] );
			array_push ( $ret, $tmparr );
		}
	}
	return $ret;
}
function dijkstra($graph_array, $source, $target) {
	$vertices = array ();
	$neighbours = array ();
	foreach ( $graph_array as $edge ) {
		array_push ( $vertices, $edge [0], $edge [1] );
		$neighbours [$edge [0]] [] = array (
				"end" => $edge [1],
				"cost" => $edge [2] 
		);
		$neighbours [$edge [1]] [] = array (
				"end" => $edge [0],
				"cost" => $edge [2] 
		);
	}
	$vertices = array_unique ( $vertices );
	
	foreach ( $vertices as $vertex ) {
		$dist [$vertex] = INF;
		$previous [$vertex] = NULL;
	}
	
	$dist [$source] = 0;
	$Q = $vertices;
	while ( count ( $Q ) > 0 ) {
		
		// TODO - Find faster way to get minimum
		$min = INF;
		foreach ( $Q as $vertex ) {
			if ($dist [$vertex] < $min) {
				$min = $dist [$vertex];
				$u = $vertex;
			}
		}
		
		$Q = array_diff ( $Q, array (
				$u 
		) );
		if ($dist [$u] == INF or $u == $target) {
			break;
		}
		
		if (isset ( $neighbours [$u] )) {
			foreach ( $neighbours [$u] as $arr ) {
				$alt = $dist [$u] + $arr ["cost"];
				if ($alt < $dist [$arr ["end"]]) {
					$dist [$arr ["end"]] = $alt;
					$previous [$arr ["end"]] = $u;
				}
			}
		}
	}
	$path = array ();
	$u = $target;
	while ( isset ( $previous [$u] ) ) {
		array_unshift ( $path, $u );
		$u = $previous [$u];
	}
	array_unshift ( $path, $u );
	return $path;
}
