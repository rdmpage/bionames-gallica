<?php

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/couchsimple.php');


$filename = 'dump.txt';

$file_handle = fopen($filename, "r");

while (!feof($file_handle)) 
{
	$parts = explode("\t", trim(fgets($file_handle)));
	
	$obj = new stdclass;
	//$obj->_id = str_replace('http://gallica.bnf.fr/', '', $parts[0]);
	$obj->_id = str_replace('http://gallica.bnf.fr/ark:/12148/', '', $parts[0]);
	$obj->_id = str_replace('/', '', $obj->_id);
	$obj->spage = $parts[1];
	$obj->epage = $parts[2];
	
	$json = json_encode($obj);
	
	echo $json;
	echo "\n";
	
	$couch->add_update_or_delete_document($obj, $obj->_id);	
	
	
}
?>