<?php

//error_reporting(E_ALL);

// Fetch person from ORCID and convert to JSON-LD 
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/fingerprint.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/lib.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/nameparse.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');


//----------------------------------------------------------------------------------------
function orcid_parse($obj)
{
	$objects = array();
	
	$items = array();

	//------------------------------------------------------------------------------------
	// ORCID
	$orcid = $obj->{'orcid-profile'}->{'orcid-identifier'}->uri;


	//------------------------------------------------------------------------------------
	// Person with this ORCID
	$bio = $obj->{'orcid-profile'}->{'orcid-bio'};

	$person = new stdclass;
	$person->{'@id'} = $orcid;
	$person->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Person';
	
	// Parts of name
	if (isset($bio->{'personal-details'}->{'given-names'}->value))
	{
		$person->{'http://schema.org/givenName'}[] = $bio->{'personal-details'}->{'given-names'}->value;
	}
	if (isset($bio->{'personal-details'}->{'family-name'}->value))
	{
		$person->{'http://schema.org/familyName'}[] = $bio->{'personal-details'}->{'family-name'}->value;
	}

	// Construct a full name from parts (unless name is not "Western")
	// Check for locale of ORCID record
	$locale = '';
	if (isset($obj->{'orcid-profile'}->{'orcid-preferences'}))
	{
		if (isset($obj->{'orcid-profile'}->{'orcid-preferences'}->locale))
		{
			$locale = strtolower($obj->{'orcid-profile'}->{'orcid-preferences'}->locale);
		}
	}

	switch ($locale)
	{
		case 'zh_cn': // Chinese
			// If given name doesn't contain Chinse characters, treat as Western name
			// and also reverse the order to increase chance of matching name to publications
			if (isset($bio->{'personal-details'}->{'given-names'}->value) && $bio->{'personal-details'}->{'family-name'}->value)
			{
				// See http://www.regular-expressions.info/unicode.html
				// and http://stackoverflow.com/a/4923410
				if (preg_match('/\p{Han}+/u', $bio->{'personal-details'}->{'given-names'}->value))
				{
					// Contains Chinese characters, so don't attempt to create name
				}
				else
				{
					// Western
					$person->{'http://schema.org/name'}[] = 
						$bio->{'personal-details'}->{'given-names'}->value . ' ' . $bio->{'personal-details'}->{'family-name'}->value;
				
					// Reverse
					$person->{'http://schema.org/name'}[] = 
						$bio->{'personal-details'}->{'family-name'}->value . ' ' . $bio->{'personal-details'}->{'given-names'}->value;
				}
			}
			break;
		
		default:
			if (isset($bio->{'personal-details'}->{'given-names'}->value) && $bio->{'personal-details'}->{'family-name'}->value)
			{
				$person->{'http://schema.org/name'}[] = 
					$bio->{'personal-details'}->{'given-names'}->value . ' ' . $bio->{'personal-details'}->{'family-name'}->value;
			}
			break;
	}


	// Credit name (this is where we sometimes see full name in Chinese)
	if (isset($bio->{'personal-details'}->{'credit-name'}->value))
	{
		$person->{'http://schema.org/name'}[] = $bio->{'personal-details'}->{'credit-name'}->value;
	}

	// other names
	if (isset($bio->{'personal-details'}->{'other-names'}))
	{
		foreach ($bio->{'personal-details'}->{'other-names'}->{'other-name'} as $other_name)
		{
			$person->{'http://schema.org/alternateName'}[] = $other_name->value;
		}
	}

	// Other identifiers
	if (isset($bio->{'external-identifiers'}))
	{
		foreach ($bio->{'external-identifiers'}->{'external-identifier'} as $external_identifier)
		{
			
	
			if (isset($external_identifier->{'external-id-url'}))
			{	
				$person->{'http://schema.org/sameAs'}[] = $external_identifier->{'external-id-url'}->value;
			}
		}
	}
	

	// Other URLs
	if (isset($bio->{'researcher-urls'}))
	{
		foreach ($bio->{'researcher-urls'}->{'researcher-url'} as $researcher_urls)
		{
			$person->{'http://schema.org/sameAs'}[] = $researcher_urls->{'url'}->value;
		
			// Other interpretations of the URLs
			if (preg_match('/^http:\/\/www.ipni.org\/ipni\/idAuthorSearch.do\?id=(?<id>.*)$/', $researcher_urls->{'url'}->value, $m))
			{
				$person->{'http://schema.org/sameAs'}[] = 'urn:lsid:ipni.org:authors:' . $m['id'];
			}
		
		}
	}
	
	//------------------------------------------------------------------------------------
	// works

	$works = $obj->{'orcid-profile'}->{'orcid-activities'}->{'orcid-works'}->{'orcid-work'};

	foreach ($works as $work)
	{

		//print_r($work);

		$reference = new stdclass;
	
		// Use put-code as bnode identifier
		$reference->{'@id'} = $person->{'@id'} . '/work/' . $work->{'put-code'};
		
		// Creative work
		$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';			

		$reference->{'http://purl.org/dc/terms/title'}[] = $work->{'work-title'}->{'title'}->value;
	
		// Journal?
		if (isset($work->{'journal-title'}->value))
		{
			$reference->{'http://prismstandard.org/namespaces/basic/2.1/publicationName'}[] = $work->{'journal-title'}->value;
		}
		
		// Work type
		$work_type = 'unknown';
		
		if (isset($work->{'work-type'}))
		{
			switch ($work->{'work-type'})
			{
				case 'BOOK':
					$work_type = 'book';
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/AcademicArticle';
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ScholarlyArticle';
					break;
					
				case 'BOOK_CHAPTER':
					$work_type = 'chapter';
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/Chapter';
					break;
										
				case 'JOURNAL_ARTICLE':
					$work_type = 'article';
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/Book';
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Book';
					break;
					
				default:
					break;
			}
		}		
		
		
		// date
        $date = '';
		if (isset($work->{'publication-date'}))
		{
			if (isset($work->{'publication-date'}->{'year'}->value))
			{
				$date = $work->{'publication-date'}->{'year'}->value;
			}
			$reference->{'http://purl.org/dc/terms/date'}[] = $date;
		}
		

		// Parse BibTex-------------------------------------------------------------------
		if (isset($work->{'work-citation'}->citation))
		{
			$bibtext = $work->{'work-citation'}->citation;
		
			if (!isset($work->{'journal-title'}->value))
			{
				if (preg_match('/journal = \{(?<journal>.*)\}/Uu', $bibtext, $m))
				{
					$reference->{'http://prismstandard.org/namespaces/basic/2.1/publicationName'}[]= $m['journal'];
				}
			}
	
			if ($date == '')
			{
				if (preg_match('/year = \{(?<year>[0-9]{4})\}/', $bibtext, $m))
				{
					$reference->{'http://purl.org/dc/terms/date'}[] = $m['year'];
				}
			}
			
			if (preg_match('/volume = \{(?<volume>.*)\}/Uu', $bibtext, $m))
			{
				$reference->{'http://purl.org/ontology/bibo/volume'}[] = $m['volume'];
			}

			if (preg_match('/number = \{(?<issue>.*)\}/Uu', $bibtext, $m))
			{
				$reference->{'http://purl.org/ontology/bibo/issue'}[] = $m['issue'];
			}

			// pages = {41-68}
			if (preg_match('/pages = \{(?<pages>.*)\}/Uu', $bibtext, $m))
			{
				$pages = $m['pages'];
				if (preg_match('/(?<spage>\d+)-[-]?(?<epage>\d+)/', $pages, $mm))
				{
					$reference->{'http://purl.org/ontology/bibo/pageStart'}[] = $mm['spage'];
					$reference->{'http://purl.org/ontology/bibo/pageEnd'}[] = $mm['epage'];
				}
				else
				{	
					$reference->{'http://purl.org/ontology/bibo/pages'}[] = $pages;
				}
			}
		}
		
		// Identifiers
		if (isset($work->{'work-external-identifiers'}))
		{
			foreach ($work->{'work-external-identifiers'}->{'work-external-identifier'} as $identifier)
			{
				switch ($identifier->{'work-external-identifier-type'})
				{
					case 'DOI':
						$value = $identifier->{'work-external-identifier-id'}->value;
						// clean
						$value = preg_replace('/^doi:/', '', $value);
						$value = preg_replace('/\.$/', '', $value);
					
						// DOI
						$reference->{'http://purl.org/dc/terms/identifier'}[] = 'http://identifiers.org/doi/' . $value;
						$reference->{'http://purl.org/ontology/bibo/doi'}[] = $value;
					
						// sameAs
						$reference->{'http://schema.org/sameAs'}[] = 'http://identifiers.org/doi/' . $value;					
						break;
						
					case 'ISBN':
						$value = $identifier->{'work-external-identifier-id'}->value;
						
						if ($work_type == 'BOOK')
						{
							$reference->{'http://purl.org/ontology/bibo/isbn'}[] = $value;
							$reference->{'http://purl.org/dc/terms/identifier'}[] = 'http://www.worldcat.org/isbn/' . $value;
						}												
						break;
				
					case 'ISSN':
						$value = $identifier->{'work-external-identifier-id'}->value;
						$parts = explode(";", $value);
					
						foreach ($parts as $issn)
						{					
							$reference->{'http://purl.org/dc/terms/isPartOf'}[] = 'http://www.worldcat.org/issn/' . $issn;
						}
						break;

					case 'PMID':
						$value = $identifier->{'work-external-identifier-id'}->value;
						$reference->{'http://purl.org/ontology/bibo/pmid'}[] = $value;
						$reference->{'http://purl.org/dc/terms/identifier'}[] = 'http://identifiers.org/pmid/' . $value;
						break;
					
					default:
						break;
				}
			}
		}
	
		// URL
		if (isset($work->{'url'}))
		{
			if (isset($work->{'url'}->{'value'}))
			{
				$urls = explode(",", $work->{'url'}->{'value'});
				foreach ($urls as $url)
				{
					$reference->{'http://schema.org/relatedLink'}[] = $url;
				}
			}
		}
	
		// authors
		if (1)
		{
			if (isset($work->{'work-contributors'}))
			{		
				// OK, since this person is an author, find the best matching name amongst
				// and use the ORCID for that person. The others will be blank nodes.
				// ORCID has a field "contributor-orcid" but this always seems to be null :(
	
				// matching
				$min_d = 1000;
				$best_match = -1;
				$count = 1;
				foreach ($work->{'work-contributors'}->{'contributor'} as $contributor)
				{
					if (isset($person->{'http://schema.org/name'}))
					{
						// name as provided in this work
						$contributor = array($contributor->{'credit-name'}->value);
					
						// if there is a comma, swap name parts around 
						// (i.e., swap lastname, firstname => firstname lastname)
						if (preg_match('/(?<before>.*),\s*(?<after>.*)/u', $contributor[0], $m))
						{
							$contributor[] = $m['after'] . ' ' . $m['before'];
						}
				
						foreach ($person->{'http://schema.org/name'} as $name)
						{
							foreach ($contributor as $c)
							{
								$d = levenshtein(finger_print($name), finger_print($c));
								if ($d < $min_d) 
								{
									$min_d = $d;
									$best_match = $count;
								}
							}
						}
					}
					$count++;
				}
	
				$count = 1;
				foreach ($work->{'work-contributors'}->{'contributor'} as $contributor)
				{			
					// make object for author
					$author = new stdclass;
			
					if ($count == $best_match)
					{
						// person with this ORCID is the contributor, just make assertion of authorship
						$reference->{'http://purl.org/dc/terms/creator'}[] = $orcid;
					
						// Store name variation for this work
						$person->{'http://schema.org/alternateName'}[] = $contributor->{'credit-name'}->value;
					}
					else
					{		
						// Unidentified contributor	
						// make a bnode from bnode for work
						$author->{'@id'} = $reference->{'@id'} . '/contributor/' . $count;
						$author->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Person';			
						$author->{'http://schema.org/name'}[] = $contributor->{'credit-name'}->value;
					
						// Parse the name
						$parts = parse_name($contributor->{'credit-name'}->value);
			
						if (isset($parts['last']))
						{
							$author->{'http://schema.org/familyName'}[] = $parts['last'];
						}
						if (isset($parts['first']))
						{
							$givenName = $parts['first'];
				
							if (array_key_exists('middle', $parts))
							{
								$givenName .= ' ' . $parts['middle'];
							}
							$author->{'http://schema.org/givenName'}[] = $givenName;
						}
					
						$items[] = $author;
			
						// make link to this author using bnode
						$reference->{'http://purl.org/dc/terms/creator'}[] = $author->{'@id'};
					}

					$count++;
			
				}
			}
		}
	
	
		$items[] = $reference;
	
	}	

	// Postprocessing...
	// Only want one copy of names
	if (isset($person->{'http://schema.org/alternateName'}))
	{
		$person->{'http://schema.org/alternateName'} = array_unique($person->{'http://schema.org/alternateName'});
	}	
	
	

	$items[] = $person;
	

		
	//print_r($items);		
	//exit();
		
	// Context for JSON-LD
	$context = new stdclass;

	// Bibliographic Ontology
	$context->{'@vocab'} = 'http://purl.org/ontology/bibo/';
	
	// PRISM
	$context->publicationName = 'http://prismstandard.org/namespaces/basic/2.1/publicationName';
	
	// Dublin Core
	$context->dc 			= 'http://purl.org/dc/terms/';
	$context->date 			= 'dc:date';
	$context->creator 		= 'dc:creator';
	$context->identifier 	= 'dc:identifier';
	$context->title 		= 'dc:title';
	
	

	// Schema.org
	$context->schema 		= 'http://schema.org/';
	$context->sameAs 		= 'schema:sameAs';
	$context->about 		= 'schema:about';
	$context->alternateName = 'schema:alternateName';
	$context->datePublished	= 'schema:datePublished';
	$context->isPartOf 		= 'schema:isPartOf';
	$context->name 			= 'schema:name';
	$context->dateCreated 	= 'schema:dateCreated';
	$context->dateModified 	= 'schema:dateModified';
	$context->description 	= 'schema:description';
	$context->givenName 	= 'schema:givenName';
	$context->familyName 	= 'schema:familyName';
	$context->relatedLink	= 'http://schema.org/relatedLink';
	
	// Schema types
	$context->CreativeWork 		= 'schema:CreativeWork';
	$context->Periodical 		= 'schema:Periodical';
	$context->Person 			= 'schema:Person';
	$context->ScholarlyArticle 	= 'schema:ScholarlyArticle';
	$context->Dataset 			= 'schema:Dataset';
	$context->Book				= 'schema:Book';

	// Identifiers.org namespaces
	$context->identifiers 	= 'http://identifiers.org/';
	$context->DOI 			= 'identifiers:doi/';
	$context->INSDC 		= 'identifiers:insdc/';
	$context->MESH 			= 'identifiers:mesh/';
	$context->PMC 			= 'identifiers:pmc/';
	$context->PMID 			= 'identifiers:pubmed/';
	
	// Other identifiers
	$context->ISBN 			= 'http://www.worldcat.org/isbn/';
	$context->ISSN 			= 'http://www.worldcat.org/issn/';
	$context->ORCID 		= 'http://orcid.org/';


	// We go through each item because some object sets (such as Sandy Kanpp 0000-0001-7698-3945)
	// are so big that they cause a timeout in jsonld library :(
	foreach ($items as $item)
	{
		// item_to_quads assumes we are giving it an array
		$one = array();
		$one[] = $item;
		$nq = item_to_quads($one, $item->{'@id'});

		$jsonld = jsonld_from_rdf($nq);
		$jsonld = jsonld_compact($jsonld, $context);

//		$jsonld->_id = $orcid . '.json';

		$jsonld->_id = $item->{'@id'};
	
		$objects[] = $jsonld;
	}
	
	//echo json_format(json_encode($jsonld)) . "\n";
		
		
	return $objects;
}



//----------------------------------------------------------------------------------------
function orcid_fetch($orcid)
{
	global $config;
	
	$orcid = preg_replace('/^orcid:/i', '', $orcid);
	
	$objects = array();
	
	// Ensure we have cache folder
	$cache = $config['cache_dir']. "/orcid";
	if (!file_exists($cache))
	{
		$oldumask = umask(0); 
		mkdir($cache, 0777);
		umask($oldumask);
	}	
	
	$filename = $cache . '/' . $orcid . '.json';
	if (file_exists($filename))
	{
		$json = file_get_contents($filename);
	}
	else
	{
		$url = 'http://pub.orcid.org/v1.2/' . $orcid . '/orcid-profile';
		$json = get($url, '', 'application/orcid+json');
		file_put_contents($filename, $json);
	}
	
	if ($json != '')
	{
		$obj = json_decode($json);
		$objects = orcid_parse($obj);
	}
	
	
	
	return ($objects);
}
	
if (0)
{

	//$objects = orcid_fetch('0000-0002-6672-8075');
	$objects = orcid_fetch('0000-0003-4490-3490');
	print_r($objects);
	
	//$objects = orcid_fetch('0000-0001-7698-3945');
}





?>