#!/usr/bin/php
<?php

require_once 'MinecraftLogParser.class.php';


if($argc<2) 
{
	echo 'Usage: '.basename(__FILE__)." [log_path (.)] {output [php_serialized_array]} {mode [summarized]} {verbose} {start_date} {end_date} {report email} {verbose}\n";
	exit;
}

if($argc>1) $path = rtrim($argv[1],DIRECTORY_SEPARATOR);
else $path = '.';

set_include_path(get_include_path().PATH_SEPARATOR.__DIR__);

if($argc>2) $output = strtolower($argv[2]);
else $output = 'php_serialized_array';

if(is_file(__DIR__.DIRECTORY_SEPARATOR.'configure_database.inc.php')) 
{
	include_once 'configure_database.inc.php';
}

$debugging = true;

if($argc>3) $mode = $argv[3];
else $mode = 'summarized';

if($argc>4) $verbose = $argv[4];
else $verbose = false;

if($argc>5) $start_date = $argv[5];
else $start_date = false;

if($argc>6) $end_date = $argv[6];
else $end_date = false;

if($argc>7) $report_email = $argv[7];
else $report_email = false; //'ghbarratt@gmail.com';

if(!is_bool($verbose)) 
{
	if(strtolower($verbose[0])=='v' || strtolower($verbose[0])=='y' || strtolower($verbose)=='true') $verbose = true;
	else if(!is_numeric($verbose)) $verbose = false;
}

if(!is_dir($path)) 
{
	echo 'ERROR '.$path." is not a valid path.\n";
	exit;
}

if(stripos($output, 'database')!==false && !$dbh) 
{
	echo "ERROR No dbh defined!\n";
	exit;
}

$mlp = new MinecraftLogParser($path, $mode, $verbose, null, $dbh);
$mlp->parse();
if(stripos($output, 'database')!==false)
{
	if(is_object($dbh)) $mlp->saveDataToDatabase($dbh);
}
else if(strtolower($output)=='print_r') print_r($mlp->getData());
else echo serialize($mlp->getData());

