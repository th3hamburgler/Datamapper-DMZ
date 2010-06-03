#!/usr/bin/php
<?php
/*
 * Builds the DataMapper file from the set of files contained in src
 */

// Constants
$src_path = './src';

$build_file = './application/libraries/datamapper.php';

// -------------------------------------------------------

$file_list = scandir($src_path);

foreach($file_list as $index => $name) {
	if(strpos($name, '.') === 0) {
		unset($file_list[$index]);
	}
}

if ( ! $fp = @fopen($build_file, 'w')) {
	die("Error creating file.\n");
}

foreach($file_list as $name) {
	echo("Adding file {$name}...\n");
	$contents = file($src_path . '/' . $name);
	for($i=1; $i < (count($contents)-1); $i++) {
		if(fwrite($fp, $contents[$i]) === FALSE) {
			die("Error writing output!\n");
		}
	}
}
fclose($fp);

echo("\n");
// validate
passthru("php -l $build_file");

if(count($argv) > 0) {
	echo("\nCopying to other destinations\n");
}

// also copies the file to each of the locations specified after $argv[0]
array_shift($argv);
foreach($argv as $arg) {
	echo("Copying to $arg ...");
	if(copy($build_file, $arg)) {
		echo("OK\n");
	} else {
		echo("FAILED\n");
	}
}

echo("DMZ Built.\n");
