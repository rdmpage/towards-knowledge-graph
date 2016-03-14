<?php

// Resolve one object

require_once (dirname(dirname(__FILE__)) . '/resolvers/bold/fetch.php');
require_once (dirname(dirname(__FILE__)) . '/resolvers/genbank/fetch.php');
require_once (dirname(dirname(__FILE__)) . '/resolvers/gbif/fetch.php');
require_once (dirname(dirname(__FILE__)) . '/resolvers/lsid/fetch.php');
require_once (dirname(dirname(__FILE__)) . '/resolvers/orcid/fetch.php');
require_once (dirname(dirname(__FILE__)) . '/resolvers/pubmed/fetch.php');

$id = '';
$callback = '';

if (isset($_GET['id']))
{
	$id = $_GET['id'];
}
if (isset($_GET['callback']))
{
	$callback = $_GET['callback'];
}

if ($id == '')
{
	// Form
	echo '<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8" />
		
		<style type="text/css" title="text/css">
		body
		{
			font-family:sans-serif;padding:20px;
		}
		section {
			width: 100%;
			height: 200px;
			background: aqua;
			margin: auto;
			padding: 00px;
		}
		div#one {
			width: 50%;
			background: black;
			color:#80FF00;
			float: left;
			overflow:auto;
			height:600px;
		}
		div#two {
			margin-left: 50%;
			background: rgb(242,242,242);
			padding:10px;
			overflow:auto;
			height:600px;
		}	
		.card {
			margin:10px;
			padding:10px;
			background-color:white;
			color:#666666;
			border:1px solid rgb(224,224,224);
		}
		</style>
		<title>Data inspector</title>
	</head>
	<body>
	<h1>Resolver</h1>
	<h2>Identifier</h2>
	<form method="get" action="resolve.php">
		<input style="font-size:24px;" id="id" name="id" size="40" value="' . $id . '"></input>
		<input style="font-size:24px;" type="submit" value="Go"></input>
	</form>
	</body>
	</html>';
}
else
{
	//echo $id;
	$kind = '';
	$objects = array();
	
	if ($kind == '')
	{
		if (preg_match('/^(BOLD:)?(?<barcode>[A-Z]{3,5}\d+-\d+)$/', $id, $m))
		{
			$kind = 'barcode';
			$id = $m['barcode'];
		}
	}
	
	// JN270496
	if ($kind == '')
	{
		if (preg_match('/^(INSDC:)?(?<accession>[A-Z]{2}[0-9]{5,6})$/', $id, $m))
		{
			$kind = 'genbank';
			$id = $m['accession'];
		}
	}
	
	// http://www.ncbi.nlm.nih.gov/nucleotide/257798966
	
	if ($kind == '')
	{
		if (preg_match('/^(GI:)(?<gi>\d+)$/', $id, $m))
		{
			$kind = 'genbank';
			$id = $m['gi'];
		}
	}
	
	
	if ($kind == '')
	{
		if (preg_match('/^(PMID:)?(?<pmid>\d+)$/i', $id, $m))
		{
			$kind = 'pubmed';
			$id = $m['pmid'];
		}
	}
	
	if ($kind == '')
	{
		if (preg_match('/^(OCCURRENCE:)?(?<id>\d+)$/i', $id, $m))
		{
			$kind = 'gbif_occurrence';
			$id = $m['id'];
		}
	}
	
	if ($kind == '')
	{
		if (preg_match('/^urn:lsid/i', $id, $m))
		{
			$kind = 'lsid';
		}
	}
	
	if ($kind == '')
	{
		if (preg_match('/^(ORCID:)?([0-9]{4})(-[0-9A-Z]{4}){3}/i', $id, $m))
		{
			$kind = 'orcid';
		}
	}
	
	//0000-0002-6672-8075
	
	
	
	//echo "kind=$kind";
	
	
	switch ($kind)
	{
		case 'barcode':
			$objects = barcode_fetch($id);
			break;	
			
		case 'gbif_occurrence':
			$objects = gbif_fetch($id);
			break;	

		case 'genbank':
			$objects = genbank_fetch($id);
			break;	

		case 'orcid':
			$objects = orcid_fetch($id);
			break;
			
		case 'pubmed':
			$objects = pubmed_fetch($id);
			break;	
			
		case 'lsid':
			$objects = lsid_fetch($id);
			break;
			
		default:
			break;
	}
	
	/*
	// store (should do this elsewhere)
	foreach ($objects as $object)
	{
		$couch->add_update_or_delete_document($object,  $object->_id);
		//var_dump($resp);
	}
	*/
	header("Content-type: text/plain");
	if ($callback != '')
	{
		echo $callback . '(';
	}
	echo json_format(json_encode($objects));
	if ($callback != '')
	{
		echo ')';
	}
	
}


?>