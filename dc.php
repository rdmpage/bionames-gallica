<?php

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/couchsimple.php');

$ark = '';
if (isset($_GET['ark']))
{
	$ark = $_GET['ark'];
}
preg_match('/^(?<id>.*)f(?<start>\d+)$/', $ark, $m);

$id = $m['id'];
$start = $m['start'];

$callback = '';
if (isset($_GET['callback']))
{
	$callback = $_GET['callback'];
}	
	
$resp = $couch->send("GET", "/" . $config['couchdb'] . '/' . urlencode($ark));

//echo $resp;

$obj = new stdclass;

$result = json_decode($resp);
if (isset($result->error))
{
}
else
{
	//print_r($result);
	$obj->title 		= 'Gallica';
	$obj->description 	= 'Gallica';
	$obj->canonical_url = 'http://gallica.bnf.fr/ark:/12148/' . $id . '/f' . $start;
	$obj->id			= $ark;
	
	$num_pages = $result->epage - $result->spage + 1;
	$obj->pages = $num_pages;
		
	$obj->resources = new stdclass;
	
	$obj->resources->page = new stdclass;
	$obj->resources->page->text 	= $config['web_root'] . '/' . $id . '/start/' . $start  . '/pages/{page}';
	$obj->resources->page->image 	= $config['web_root'] . '/' . $id . '/start/' . $start  . '/pages/{page}-{size}';
	
	$obj->sections = array();	
	$obj->annotations = array();
}

header("Content-type: text/plain");
if ($callback != '')
{
	echo $callback . '(';
}
echo json_encode($obj);
if ($callback != '')
{
	echo ')';
}	
	

?>

