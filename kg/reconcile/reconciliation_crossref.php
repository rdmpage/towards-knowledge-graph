<?php

// Match specimen codes to GBIF occurrences

require_once (dirname(__FILE__) . '/reconciliation_api.php');

require_once (dirname(__FILE__) . '/fingerprint.php');
require_once (dirname(__FILE__) . '/lcs.php');



//----------------------------------------------------------------------------------------
class CrossRefDOIService extends ReconciliationService
{
	//----------------------------------------------------------------------------------------------
	function __construct()
	{
		$this->name 			= 'CrossRef DOI';
		
		$this->identifierSpace 	= 'http://crossref.org/';
		$this->schemaSpace 		= 'http://rdf.freebase.com/ns/type.object.id';
		$this->Types();
		
		$view_url = 'http://dx.doi.org/{{id}}';

		$preview_url = '';	
		$width = 430;
		$height = 300;
		
		if ($view_url != '')
		{
			$this->View($view_url);
		}
		if ($preview_url != '')
		{
			$this->Preview($preview_url, $width, $height);
		}
	}
	
	//----------------------------------------------------------------------------------------------
	function Types()
	{
		$type = new stdclass;
		$type->id = 'https://schema.org/CreativeWork';
		$type->name = 'CreativeWork';
		$this->defaultTypes[] = $type;
	} 	
		
	//----------------------------------------------------------------------------------------------
	// Handle an individual query
	function OneQuery($query_key, $text, $limit = 1, $properties = null)
	{
		global $config;
		
		$post_data = array();
		$post_data[] = $text;
		
		$ch = curl_init(); 
		
		$url = 'http://search.crossref.org/links';
		
		curl_setopt ($ch, CURLOPT_URL, $url); 
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
	
		// Set HTTP headers
		$headers = array();
		$headers[] = 'Content-type: application/json'; // we are sending JSON
		
		// Override Expect: 100-continue header (may cause problems with HTTP proxies
		// http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
		$headers[] = 'Expect:'; 
		curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
		
		if ($config['proxy_name'] != '')
		{
			curl_setopt($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
		}
	
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
		
		$response = curl_exec($ch);
		
		$obj = json_decode($response);
		if (count($obj->results) == 1)
		{
			if ($obj->results[0]->match)
			{
				// to do: double check
				$doi = str_replace('http://dx.doi.org/', '', $obj->results[0]->doi);
				
				// unpack metadata 
				$parts = explode('&', html_entity_decode($obj->results[0]->coins));
				$kv = array();
				foreach( $parts as $part)
				{
				  list($key, $value) = explode('=', $part);
		  
				  $key = preg_replace('/^\?/', '', urldecode($key));
				  $kv[$key][] = trim(urldecode($value));
				}				
								
				$hit = new stdclass;
				$hit->id 	= $doi;
				
				if (isset($kv['rft.atitle']))
				{
					$hit->name 	= $kv['rft.atitle'][0];
				}
				else
				{
					$hit->name 	= $doi;
				}				
				
				$hit->score = $obj->results[0]->score;
				$hit->match = true;
				$this->StoreHit($query_key, $hit);
			}
		}
		
	}
	
	
}

$service = new CrossRefDOIService();

if (0)
{
	file_put_contents(dirname(__FILE__) . '/tmp/gbif_occurrence.txt', $_REQUEST['queries'], FILE_APPEND);
}

$service->Call($_REQUEST);

?>