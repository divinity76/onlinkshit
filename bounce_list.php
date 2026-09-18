<?php
declare(strict_types = 1);
init ();
$dbc = getSave ();
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
$InternicLocationStatement = $dbc->prepare ( 'SELECT `x`,`y` FROM `vlocation` WHERE `ip` = ? LIMIT 1' );
$InternicLocationStatement->execute ( array (
		$ip 
) );
$internicLocation = $InternicLocationStatement->fetch ( PDO::FETCH_ASSOC );
if ($internicLocation === false) {
	throw new RuntimeException ( "Couldn't find InterNIC's map location." );
}
unset ( $ip );
echo "getting a list of every server with the name Public Access or Access Terminal...";
$res = $dbc->query ( '
SELECT `computer`,`ip`,`x`,`y` FROM `vlocation` WHERE `computer` LIKE \'%Public Access%\' OR `computer` LIKE \'%Access Terminal%\'
;
' );
$res = $res->fetchAll ( PDO::FETCH_ASSOC );
echo "found " . count ( $res, COUNT_NORMAL ) . " servers. done." . PHP_EOL;
$unsortedDistance = calculate_total_xy_distance_from ( $internicLocation, $res );
echo "original distance before sorting: " . $unsortedDistance . PHP_EOL;
echo "running original X/Y nearest-server sort...";
$originalStartedAt = microtime ( true );
$original_sorted_res = count ( $res, COUNT_NORMAL ) < 2 ? $res : sort_by_xy_distance ( $res );
$originalElapsed = microtime ( true ) - $originalStartedAt;
echo "done." . PHP_EOL;
echo "running smart multi-start nearest-neighbour + 2-opt sort...";
$smartStartedAt = microtime ( true );
$smart_sorted_res = sort_by_xy_distance_smart ( $res, $internicLocation, $original_sorted_res );
$smartElapsed = microtime ( true ) - $smartStartedAt;
echo "done." . PHP_EOL;
echo "running Lin-Kernighan-style variable k-opt sort...";
$linKernighanStartedAt = microtime ( true );
$lin_kernighan_sorted_res = sort_by_xy_distance_lin_kernighan ( $smart_sorted_res, $internicLocation );
$linKernighanElapsed = microtime ( true ) - $linKernighanStartedAt;
echo "done." . PHP_EOL;
$originalDistance = calculate_total_xy_distance_from ( $internicLocation, $original_sorted_res );
$smartDistance = calculate_total_xy_distance_from ( $internicLocation, $smart_sorted_res );
$linKernighanDistance = calculate_total_xy_distance_from ( $internicLocation, $lin_kernighan_sorted_res );
$difference = $originalDistance - $smartDistance;
$improvement = $originalDistance > 0 ? ($difference / $originalDistance) * 100 : 0.0;
$linKernighanDifference = $originalDistance - $linKernighanDistance;
$linKernighanImprovement = $originalDistance > 0 ? ($linKernighanDifference / $originalDistance) * 100 : 0.0;
echo "route comparison from InterNIC (127.0.0.1 -> InterNIC is fixed):" . PHP_EOL;
echo "  original: " . $originalDistance . " (" . number_format ( $originalElapsed, 6 ) . " seconds)" . PHP_EOL;
echo "  smart:    " . $smartDistance . " (" . number_format ( $smartElapsed, 6 ) . " seconds)" . PHP_EOL;
echo "  smart improvement: " . $difference . " (" . number_format ( $improvement, 2 ) . "%)" . PHP_EOL;
echo "  Lin-Kernighan: " . $linKernighanDistance . " (" . number_format ( $linKernighanElapsed, 6 ) . " seconds)" . PHP_EOL;
echo "  Lin-Kernighan improvement: " . $linKernighanDifference . " (" . number_format ( $linKernighanImprovement, 2 ) . "%)" . PHP_EOL;
echo "  total sorting time: " . number_format ( $originalElapsed + $smartElapsed + $linKernighanElapsed, 6 ) . " seconds" . PHP_EOL;
$routes = array (
		'original' => array (
				'distance' => $originalDistance,
				'route' => $original_sorted_res 
		),
		'smart' => array (
				'distance' => $smartDistance,
				'route' => $smart_sorted_res 
		),
		'Lin-Kernighan' => array (
				'distance' => $linKernighanDistance,
				'route' => $lin_kernighan_sorted_res 
		) 
);
$selectedAlgorithm = 'original';
foreach ( $routes as $algorithm => $candidate ) {
	if ($candidate ['distance'] < $routes [$selectedAlgorithm] ['distance']) {
		$selectedAlgorithm = $algorithm;
	}
}
$sorted_res = $routes [$selectedAlgorithm] ['route'];
echo "selected " . $selectedAlgorithm . " route." . PHP_EOL;
unset ( $res, $original_sorted_res, $smart_sorted_res, $lin_kernighan_sorted_res, $routes );
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
function getSave(): \PDO {
	global $argc, $argv;
	$dir = getenv ( "APPDATA" ) . "/Onlink/users";
	$dir = strtr ( $dir, array (
			'\\' => '/' 
	) );
	if (is_dir ( $dir )) {
		// windows
	} else {
		$dir = getenv ( "HOME" ) . "/.onlink/users";
		$dir = strtr ( $dir, array (
				'\\' => '/' 
		) );
		if (is_dir ( $dir )) {
			// linux
		} else {
			die ( "error: cannot find the onlink user folder! (and there are only 2 dirs i'm coded to check, ~/.onlink/users  and %appdata%\Onlink\users )" );
		}
	}
	$files = glob ( "$dir/*.db", GLOB_NOESCAPE );
	if (empty ( $files )) {
		die ( "0 agents found!" );
	}
	$agents = [ ];
	foreach ( $files as $file ) {
		$agents [strtolower ( basename ( $file, ".db" ) )] = $file;
	}
	if ($argc === 1) {
		echo count ( $agents ) . " agent(s) were found: \n";
		$i = 0;
		foreach ( $agents as $agent => $unused ) {
			++ $i;
			echo "$i: $agent\n";
		}
		echo "you can pick an agent based on the name, or by agent number.\n";
		die ();
	} elseif ($argc === 2) {
		$target = strtolower ( basename ( trim ( $argv [1] ) ) );
		$i = 0;
		foreach ( $agents as $agent => $file ) {
			++ $i;
			if ($i == $target || $agent == $target) {
				// found target!
				$opts = array (
						PDO::ATTR_EMULATE_PREPARES => false,
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION 
				);
				try {
					$dbc = new PDO ( 'sqlite:' . $file, '', '', $opts );
				} catch ( Exception $ex ) {
					// workaround a weird sqlite3 cygwin bug...
					$dbc = new PDO ( 'sqlite:/cygdrive/' . str_replace ( ':', '', $file ), '', '', $opts );
				}
				return $dbc;
			}
		}
		die ( "error: agent \"$target\" not found!" );
	} else {
		die ( "only 0-1 arguments are supported, but " . ($argc - 1) . " given!" );
	}
}
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
			if ($input_list [$ii] == null) {
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
/**
 * Build several greedy routes with different first stops, retain the best one,
 * and remove crossing/detouring segments with 2-opt. The start (InterNIC) is
 * fixed and is not included in the returned list.
 */
function sort_by_xy_distance_smart(array $input_list, array $start, array $originalRoute = array ()): array {
	$input_list = array_values ( $input_list );
	$count = count ( $input_list, COUNT_NORMAL );
	if ($count < 2) {
		return $input_list;
	}

	$bestRoute = empty ( $originalRoute ) ? array () : improve_route_with_two_opt ( $originalRoute, $start );
	$bestDistance = empty ( $bestRoute ) ? PHP_INT_MAX : calculate_total_xy_distance_from ( $start, $bestRoute );
	for($firstIndex = 0; $firstIndex < $count; ++ $firstIndex) {
		$route = build_nearest_neighbour_route ( $input_list, $start, $firstIndex );
		$distance = calculate_total_xy_distance_from ( $start, $route );
		if ($distance < $bestDistance) {
			$bestRoute = $route;
			$bestDistance = $distance;
		}
	}

	$bestGreedyRoute = improve_route_with_two_opt ( $bestRoute, $start );
	$bestGreedyDistance = calculate_total_xy_distance_from ( $start, $bestGreedyRoute );
	return $bestGreedyDistance < $bestDistance ? $bestGreedyRoute : $bestRoute;
}
function build_nearest_neighbour_route(array $input_list, array $start, int $firstIndex): array {
	$remaining = array_values ( $input_list );
	$route = array (
			$remaining [$firstIndex] 
	);
	$current = $remaining [$firstIndex];
	array_splice ( $remaining, $firstIndex, 1 );

	while (! empty ( $remaining )) {
		$nearestIndex = 0;
		$nearestDistance = xy_distance ( $current, $remaining [0] );
		for($i = 1, $count = count ( $remaining, COUNT_NORMAL ); $i < $count; ++ $i) {
			$distance = xy_distance ( $current, $remaining [$i] );
			if ($distance < $nearestDistance) {
				$nearestIndex = $i;
				$nearestDistance = $distance;
			}
		}
		$current = $remaining [$nearestIndex];
		$route [] = $current;
		array_splice ( $remaining, $nearestIndex, 1 );
	}

	return $route;
}
function improve_route_with_two_opt(array $route, array $start): array {
	$count = count ( $route, COUNT_NORMAL );
	if ($count < 2) {
		return $route;
	}

	do {
		$improved = false;
		for($i = 0; $i < $count - 1; ++ $i) {
			$before = $i === 0 ? $start : $route [$i - 1];
			for($k = $i + 1; $k < $count; ++ $k) {
				$oldDistance = xy_distance ( $before, $route [$i] );
				$newDistance = xy_distance ( $before, $route [$k] );
				if ($k + 1 < $count) {
					$after = $route [$k + 1];
					$oldDistance += xy_distance ( $route [$k], $after );
					$newDistance += xy_distance ( $route [$i], $after );
				}
				if ($newDistance < $oldDistance) {
					$reversed = array_reverse ( array_slice ( $route, $i, $k - $i + 1 ) );
					array_splice ( $route, $i, $k - $i + 1, $reversed );
					$improved = true;
					continue 3;
				}
			}
		}
	} while ($improved);

	return $route;
}
/**
 * A bounded Lin-Kernighan-style search for an anchored, open route. It varies
 * the exchange depth by alternating 2-opt and Or-opt moves, then uses
 * deterministic double-bridge kicks to search beyond the first local optimum.
 */
function sort_by_xy_distance_lin_kernighan(array $route, array $start): array {
	$count = count ( $route, COUNT_NORMAL );
	if ($count < 4) {
		return improve_route_with_variable_opt ( $route, $start );
	}

	$bestRoute = improve_route_with_variable_opt ( $route, $start );
	$bestDistance = calculate_total_xy_distance_from ( $start, $bestRoute );
	$kickCount = min ( 32, max ( 8, (int) floor ( sqrt ( $count ) * 1.5 ) ) );
	for($kick = 0; $kick < $kickCount; ++ $kick) {
		$candidate = double_bridge_kick ( $bestRoute, $kick );
		$candidate = improve_route_with_variable_opt ( $candidate, $start );
		$distance = calculate_total_xy_distance_from ( $start, $candidate );
		if ($distance < $bestDistance) {
			$bestRoute = $candidate;
			$bestDistance = $distance;
		}
	}

	return $bestRoute;
}
function improve_route_with_variable_opt(array $route, array $start): array {
	do {
		$oldDistance = calculate_total_xy_distance_from ( $start, $route );
		$route = improve_route_with_two_opt ( $route, $start );
		$route = improve_route_with_or_opt ( $route, $start, 3 );
		$newDistance = calculate_total_xy_distance_from ( $start, $route );
	} while ($newDistance < $oldDistance);

	return $route;
}
function improve_route_with_or_opt(array $route, array $start, int $maxSegmentLength): array {
	$count = count ( $route, COUNT_NORMAL );
	if ($count < 3) {
		return $route;
	}

	do {
		$improved = false;
		$longestSegment = min ( $maxSegmentLength, $count - 1 );
		for($segmentLength = 1; $segmentLength <= $longestSegment; ++ $segmentLength) {
			for($from = 0; $from + $segmentLength <= $count; ++ $from) {
				$segmentEnd = $from + $segmentLength - 1;
				$before = $from === 0 ? $start : $route [$from - 1];
				$after = $segmentEnd + 1 < $count ? $route [$segmentEnd + 1] : null;
				$removalDelta = - xy_distance ( $before, $route [$from] );
				if ($after !== null) {
					$removalDelta += xy_distance ( $before, $after ) - xy_distance ( $route [$segmentEnd], $after );
				}

				$remaining = $route;
				$segment = array_splice ( $remaining, $from, $segmentLength );
				$remainingCount = count ( $remaining, COUNT_NORMAL );
				for($to = 0; $to <= $remainingCount; ++ $to) {
					if ($to === $from) {
						continue;
					}
					$insertBefore = $to === 0 ? $start : $remaining [$to - 1];
					$insertAfter = $to < $remainingCount ? $remaining [$to] : null;
					$insertionDelta = xy_distance ( $insertBefore, $segment [0] );
					if ($insertAfter !== null) {
						$insertionDelta += xy_distance ( $segment [$segmentLength - 1], $insertAfter ) - xy_distance ( $insertBefore, $insertAfter );
					}
					if ($removalDelta + $insertionDelta < 0) {
						array_splice ( $remaining, $to, 0, $segment );
						$route = $remaining;
						$improved = true;
						continue 4;
					}
				}
			}
		}
	} while ($improved);

	return $route;
}
function double_bridge_kick(array $route, int $kick): array {
	$count = count ( $route, COUNT_NORMAL );
	$quarter = max ( 1, intdiv ( $count, 4 ) );
	$first = 1 + (($kick * 17) % $quarter);
	$second = min ( $count - 2, $quarter + 1 + (($kick * 29) % $quarter) );
	$third = min ( $count - 1, ($quarter * 2) + 1 + (($kick * 43) % $quarter) );
	if (! ($first < $second && $second < $third)) {
		$first = max ( 1, intdiv ( $count, 4 ) );
		$second = max ( $first + 1, intdiv ( $count, 2 ) );
		$third = max ( $second + 1, intdiv ( $count * 3, 4 ) );
	}

	return array_merge (
			array_slice ( $route, 0, $first ),
			array_slice ( $route, $second, $third - $second ),
			array_slice ( $route, $first, $second - $first ),
			array_slice ( $route, $third ) 
	);
}
function xy_distance(array $a, array $b): int {
	return abs ( (int) $b ['x'] - (int) $a ['x'] ) + abs ( (int) $b ['y'] - (int) $a ['y'] );
}
function calculate_total_xy_distance(array $input_list) {
	if (empty ( $input_list )) {
		return 0;
	}
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
function calculate_total_xy_distance_from(array $start, array $input_list): int {
	$ret = 0;
	$current = $start;
	foreach ( $input_list as $location ) {
		$ret += xy_distance ( $current, $location );
		$current = $location;
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
