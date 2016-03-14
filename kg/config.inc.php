<?php

// $Id: //

/**
 * @file config.php
 *
 * Global configuration variables (may be added to by other modules).
 *
 */

global $config;

// Date timezone
date_default_timezone_set('UTC');


// Proxy settings for connecting to the web----------------------------------------------- 
// Set these if you access the web through a proxy server. 
$config['proxy_name'] 	= '';
$config['proxy_port'] 	= '';

//$config['proxy_name'] 	= 'wwwcache.gla.ac.uk';
//$config['proxy_port'] 	= '8080';


$config['cache_dir']	= dirname(__FILE__) . '/resolvers/cache';
$config['cache_time']	= 0;

// CouchDB--------------------------------------------------------------------------------
		
		
// local
$config['couchdb_options'] = array(
		'database' => 'kg',
		'host' => 'localhost',
		'port' => 5984,
		'prefix' => 'http://'
		);		
	

// HTTP proxy
if ($config['proxy_name'] != '')
{
	if ($config['couchdb_options']['host'] != 'localhost')
	{
		$config['couchdb_options']['proxy'] = $config['proxy_name'] . ':' . $config['proxy_port'];
	}
}

$config['stale'] = true;


	
?>