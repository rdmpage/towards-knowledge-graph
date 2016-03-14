<?php

require_once (dirname(__FILE__) . '/lib.php');
require_once (dirname(dirname(__FILE__)) . '/vendor/arc2/ARC2.php');
require_once (dirname(dirname(__FILE__)) . '/vendor/php-json-ld/jsonld.php');


// RDF tools

//----------------------------------------------------------------------------------------

// Take item (array of objects) and convert to nquads
// If $items is an array we get a named graph, but if just one object we don't unless we
// explicitly provide a graph_name. Need to clarify this.
function item_to_quads($items, $graph_name = '')
{
	// Convert object to list of RDF nquads 
	$nquads = array();

	foreach ($items as $item)
	{
		$id = $item->{'@id'};
		foreach ($item as $k => $v)
		{
			switch ($k)
			{
				case '@id':
					break;

				default:
					if (!is_array($v)) { echo $k . ' ' . $v; exit(); }
					foreach ($v as $value)
					{
						$nquad = array();
						if (preg_match('/^(http|urn|_)/', $item->{'@id'}))
						{
							$nquad[] = '<' . $item->{'@id'} . '>';
						}
						else
						{
							$nquad[] = $item->{'@id'};
						}
						$nquad[] = '<' . $k . '>';
	
						if (preg_match('/^(http|urn|_)/', $value))
						{
							$nquad[] = '<' . $value . '>';
						}
						else
						{					
							$nquad[] = '"' . addcslashes($value, '"') . '"';
						}
						
						if ($graph_name != '')
						{
							$nquad[] = ' <http://x.org>';
						}

						$nquads[] = join(" ", $nquad) . " .";					
					}
					break;
			}

		}
	}


	$nq = join("\n", $nquads);
	
	if (0)
	{
		echo $nq ."\n";
		echo "====\n";
	}

	return $nq;
}

//--------------------------------------------------------------------------------------------------
function rdftojsonld($xml)
{
	// Namespaces
	$sxe = new SimpleXMLElement($xml);
	
	
	$namespaces = $sxe->getNamespaces(true);
	
	//print_r($namespaces);exit();
	
	// Parse RDF into triples
	$parser = ARC2::getRDFParser();		
	$base = 'http://example.com/';
	$parser->parse($base, $xml);	
	
	$triples = $parser->getTriples();
	
	//print_r($triples);exit();
	
	$context = new stdclass;
	
	// nquads
	$nquads = '';
	foreach ($triples as $triple)
	{
		// skip empty values (e.g., in ION RDF)
		if ($triple['o'] != "")
		{
			$predicate = $triple['p'];
			// (Sigh) Fix known fuck ups
			// ION
			$predicate = str_replace('http://rs.tdwg.org/ontology/voc/Common#PublishedIn', 'http://rs.tdwg.org/ontology/voc/Common#publishedIn', $predicate);
			$predicate = str_replace('http://purl.org/dc/elements/1.1/Title', 'http://purl.org/dc/elements/1.1/title', $predicate);

			
			$nquads .=  '<' . $triple['s'] . '> <' .  $predicate . '> ';
		
			// URNs aren't recognised as URIs, apparently
			if (($triple['o_type'] == 'uri') || preg_match('/^urn:/', $triple['o']))
			{
				// Create context for predicates
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						//echo $pattern . "\n";
						if (preg_match($pattern, $predicate, $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = (object)array("@id" => $predicate, "@type"=> "@id");
							}
							$namespace_found = true;
						}
					}
				}
				
				// Create context for object
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						//echo $pattern . "\n";
						if (preg_match($pattern, $triple['o'], $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = $triple['o'];
							}
							$namespace_found = true;
						}
					}
				}
				
				
				$nquads .= ' <' . $triple['o'] . '>';
			}
			else
			{
				// literal
		
				$object = $triple['o'];
			
				// Handle encoding issues
				$encoding = mb_detect_encoding($object);
				if ($encoding != "ASCII")
				{
					$object = mb_convert_encoding($object, 'UTF-8', $encoding);
				}
			
				// Make sure literals are escaped
				$nquads .= ' "' . addcslashes($object, '"') . '"';
			
				// language
				$lang = '';
				if (isset($triple['o_lang']))
				{
					if ($triple['o_lang'] != '')
					{
						$nquads .= '@' . $triple['o_lang'];
					}
					else
					{
						// try and detect language
						if ($triple['o_type'] == 'literal')
						{
							// See http://www.regular-expressions.info/unicode.html
							// and http://stackoverflow.com/a/4923410
							// Note that this may detect Chinese as well :O	
							if (preg_match('/\p{Hiragana}+/u', $object))
							{
								$lang = 'jp';
							}
							if (preg_match('/\p{Katakana}+/u', $object))
							{
								$lang = 'jp';
							}
							if (preg_match('/\p{Han}+/u', $object))
							{
								$lang = 'jp';
							}
							
							if (preg_match('/\p{Cyrillic}+/u', $object))
							{
								$lang = 'ru';
							}							
							
							
							if ($lang != '')
							{
								$nquads .= '@' . $lang;
							}
						}
					}
				}
				
				$namespace_found = false;
				foreach($namespaces as $k => $v)
				{
					if (!$namespace_found)
					{
						$pattern = '/^' . str_replace("/", "\\/", $v) . '(?<q>.*)$/';
						if (preg_match($pattern, $predicate, $m))
						{
							if (!isset($context->{$m['q']}))
							{
								$context->{$m['q']} = $predicate;
							}
							if ($lang != '')
							{
								$key = $m['q'] . '_' . $lang;
								
								if (!isset($context->{$key}))
								{
									$context->{$key} = new stdclass;
									$context->{$key} ->{'@id'} = $predicate;
									$context->{$key} ->{'@language'} = $lang;
								}
								
							}

							$namespace_found = true;
						}
					}
				}
				
				
				
							
			}
			// ensure we get a named graph
			//$nquads .= ' <' . $triple['s'] . '>'; 
			$nquads .= " . \n";
		}	
	}
	
	
	//print_r($context);

	if (0)
	{
		echo "---\n";
		echo $nquads;
		echo "---\n";
	}
	
	$jsonld = jsonld_from_rdf($nquads);
	$jsonld = jsonld_compact($jsonld, $context);
	
	//return json_encode($jsonld);
	return $jsonld;
}



