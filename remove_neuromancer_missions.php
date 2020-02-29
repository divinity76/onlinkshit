<?php
declare(strict_types=1);
// these missions negatively affect your neuromancer rating..
// and since there isn't a way to ignore missions in-game...
const REMOVE_QUERIES=array(
	"DELETE FROM `mission` WHERE `description` LIKE '%Highly skilled Agent required for removal job%'",	
	"DELETE FROM `mission` WHERE `description` = 'Falsify a Social Security document'",	
);
init();
$dbc = getSave ();

foreach(REMOVE_QUERIES as $query){
	echo "running: {$query}\n..";
	var_dump($dbc->query($query)->rowCount());
}

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
