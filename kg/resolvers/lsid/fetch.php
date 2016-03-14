<?php

// Generic LSID resolver

require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');
require_once (dirname(__FILE__) . '/lsid.php');

//----------------------------------------------------------------------------------------
function lsid_fetch($lsid)
{
	$objects = array();
	
	$xml = ResolveLSID($lsid);
	
	$jsonld = rdftojsonld($xml);

	$jsonld->_id = $lsid;
	$objects[] = $jsonld;
	
	return ($objects);
}
	
if (0)
{
	//$objects = lsid_fetch('urn:lsid:nmbe.ch:spidergen:01778');
	
	//$objects =  lsid_fetch('urn:lsid:organismnames.com:name:1776318');
	
	$objects = lsid_fetch('urn:lsid:ipni.org:names:20012728-1');
	
	print_r($objects);
}


