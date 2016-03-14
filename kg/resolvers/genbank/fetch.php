<?php

// Fetch sequence(s) from GenBank
// and convert to JSON-LD (OMG the horror, the horror)
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/lib.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/nameparse.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');

// https://github.com/asonge/php-geohash
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/geohash.php');


//----------------------------------------------------------------------------------------
/**
 * @brief Convert degrees, minutes, seconds to a decimal value
 *
 * @param degrees Degrees
 * @param minutes Minutes
 * @param seconds Seconds
 * @param hemisphere Hemisphere (optional)
 *
 * @result Decimal coordinates
 */
function degrees2decimal($degrees, $minutes=0, $seconds=0, $hemisphere='N')
{
	$result = $degrees;
	$result += $minutes/60.0;
	$result += $seconds/3600.0;
	
	if ($hemisphere == 'S')
	{
		$result *= -1.0;
	}
	if ($hemisphere == 'W')
	{
		$result *= -1.0;
	}
	// Spanish
	if ($hemisphere == 'O')
	{
		$result *= -1.0;
	}
	// Spainish OCR error
	if ($hemisphere == '0')
	{
		$result *= -1.0;
	}
	
	return $result;
}

//----------------------------------------------------------------------------------------
function process_lat_lon(&$locality, $lat_lon)
{

	$matched = false;

	if (preg_match ("/(N|S)[;|,] /", $lat_lon))
	{
		// it's a literal string description, not a pair of decimal coordinates.
		if (!$matched)
		{
			//  35deg12'07'' N; 83deg05'2'' W, e.g. DQ995039
			if (preg_match("/([0-9]{1,2})deg([0-9]{1,2})'(([0-9]{1,2})'')?\s*([S|N])[;|,]\s*([0-9]{1,3})deg([0-9]{1,2})'(([0-9]{1,2})'')?\s*([W|E])/", $lat_lon, $matches))
			{
				//print_r($matches);
			
				$degrees = $matches[1];
				$minutes = $matches[2];
				$seconds = $matches[4];
				$hemisphere = $matches[5];
				$lat = $degrees + ($minutes/60.0) + ($seconds/3600);
				if ($hemisphere == 'S') { $lat *= -1.0; };

				$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;

				$degrees = $matches[6];
				$minutes = $matches[7];
				$seconds = $matches[9];
				$hemisphere = $matches[10];
				$long = $degrees + ($minutes/60.0) + ($seconds/3600);
				if ($hemisphere == 'W') { $long *= -1.0; };
				
				$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
				
				$matched = true;
			}
		}
		if (!$matched)
		{
			
			list ($lat, $long) = explode ("; ", $lat_lon);

			list ($degrees, $rest) = explode (" ", $lat);
			list ($minutes, $rest) = explode ('.', $rest);

			list ($decimal_minutes, $hemisphere) = explode ("'", $rest);

			$lat = $degrees + ($minutes/60.0) + ($decimal_minutes/6000);
			if ($hemisphere == 'S') { $lat *= -1.0; };

			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;

			list ($degrees, $rest) = explode (" ", $long);
			list ($minutes, $rest) = explode ('.', $rest);

			list ($decimal_minutes, $hemisphere) = explode ("'", $rest);

			$long = $degrees + ($minutes/60.0) + ($decimal_minutes/6000);
			if ($hemisphere == 'W') { $long *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
			
			$matched = true;
		}

	}
	
	if (!$matched)
	{			
		// N19.49048, W155.91167 [EF219364]
		if (preg_match ("/(?<lat_hemisphere>(N|S))(?<latitude>(\d+(\.\d+))), (?<long_hemisphere>(W|E))(?<longitude>(\d+(\.\d+)))/", $lat_lon, $matches))
		{
			$lat = $matches['latitude'];
			if ($matches['lat_hemisphere'] == 'S') { $lat *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;
			
			$long = $matches['longitude'];
			if ($matches['long_hemisphere'] == 'W') { $long *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
			
			$matched = true;

		}
	}
	
	if (!$matched)		
	{
		//13.2633 S 49.6033 E
		if (preg_match("/([0-9]+(\.[0-9]+)*) ([S|N]) ([0-9]+(\.[0-9]+)*) ([W|E])/", $lat_lon, $matches))
		{
			//print_r ($matches);
			
			$lat = $matches[1];
			if ($matches[3] == 'S') { $lat *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;

			$long = $matches[4];
			if ($matches[6] == 'W') { $long *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
			
			$matched = true;
		}
	}
	
	
	// AY249471 Palmer Archipelago 64deg51.0'S, 63deg34.0'W 
	if (!$matched)		
	{
		if (preg_match("/(?<lat_deg>[0-9]{1,2})deg(?<lat_min>[0-9]{1,2}(\.\d+)?)'\s*(?<lat_hemisphere>[S|N]),?\s*(?<long_deg>[0-9]{1,3})deg(?<long_min>[0-9]{1,2}(\.\d+)?)'\s*(?<long_hemisphere>[W|E])/", $lat_lon, $matches))
		{
			//print_r ($matches);
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]
				= degrees2decimal(
					$matches['lat_deg'], 
					$matches['lat_min'], 
					0,
					$matches['lat_hemisphere']
					);

			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]
				= degrees2decimal(
					$matches['long_deg'], 
					$matches['long_min'], 
					0,
					$matches['long_hemisphere']
					);
			
			/*
			//exit();
			
			$lat = $matches[1];
			if ($matches[3] == 'S') { $lat *= -1.0; };
			$sequence->source->latitude = $lat;

			$long = $matches[4];
			if ($matches[6] == 'W') { $long *= -1.0; };
			
			$sequence->source->longitude = $long;
			*/
			
			//print_r($sequence);
			//exit();
			
			$matched = true;
		}
	}
	
	if (!$matched)
	{
		
		if (preg_match("/(?<latitude>\-?\d+(\.\d+)?),?\s*(?<longitude>\-?\d+(\.\d+)?)/", $lat_lon, $matches))
		{
			//print_r($matches);
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]  = $matches['latitude'];
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $matches['longitude'];
		
			$matched = true;
		}
	}
}

//----------------------------------------------------------------------------------------
function process_locality(&$locality)
{
	$debug = false;
		
	if (isset($locality->{'http://rs.tdwg.org/dwc/terms/country'}))
	{
		$country = $locality->{'http://rs.tdwg.org/dwc/terms/country'}[0];

		$matches = array();	
		$parts = explode (":", $country);	
		$locality->{'http://rs.tdwg.org/dwc/terms/country'}[0] = $parts[0];
		
		$locality_string = trim($parts[1]);
		
		if (count($parts) > 1)
		{
			$locality->{'http://rs.tdwg.org/dwc/terms/locality'}[0] = $locality_string;
			// Clean up
			$locality->{'http://rs.tdwg.org/dwc/terms/locality'}[0] = preg_replace('/\(?GPS/', '', $locality_string);				
		}	
		
		if ($debug)
		{
			echo "Trying line " . __LINE__ . "\n";
		}

		// Handle AMNH stuff
		if (preg_match('/(?<latitude_degrees>[0-9]+)deg(?<latitude_minutes>[0-9]{1,2})\'\s*(?<latitude_hemisphere>[N|S])/i', $locality_string, $matches))
		{
			if ($debug) { print_r($matches); }	

			$degrees = $matches['latitude_degrees'];
			$minutes = $matches['latitude_minutes'];
			$hemisphere = $matches['latitude_hemisphere'];
			$lat = $degrees + ($minutes/60.0);
			if ($hemisphere == 'S') { $lat *= -1.0; };

			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]  = $lat;
		}
				

		if ($debug)
		{
			echo "Trying line " . __LINE__ . "\n";
		}
		if (preg_match('/(?<longitude_degrees>[0-9]+)deg(,\s*)?(?<longitude_minutes>[0-9]{1,2})\'\s*(?<longitude_hemisphere>[W|E])/i', $locality_string, $matches))
		{
		
			if ($debug) { print_r($matches); }	
			
			$degrees = $matches['longitude_degrees'];
			$minutes = $matches['longitude_minutes'];
			$hemisphere = $matches['longitude_hemisphere'];
			$long = $degrees + ($minutes/60.0);
			if ($hemisphere == 'W') { $long *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]  = $long;
		}
	
		if ($debug)
		{
			echo "Trying line " . __LINE__ . "\n";
		}

		if ($locality_string != '')
		{
			// AY249471 Palmer Archipelago 64deg51.0'S, 63deg34.0'W 
			if (preg_match("/(?<latitude_degrees>[0-9]{1,2})deg(?<latitude_minutes>[0-9]{1,2}(\.\d+)?)'\s*(?<latitude_hemisphere>[S|N]),\s*(?<longitude_degrees>[0-9]{1,3})deg(?<longitude_minutes>[0-9]{1,2}(\.\d+)?)'\s*(?<longitude_hemisphere>[W|E])/", $locality_string, $matches))
			{	
			
				if ($debug) { print_r($matches); }	

				$degrees = $matches['latitude_degrees'];
				$minutes = $matches['latitude_minutes'];
				$hemisphere = $matches['latitude_hemisphere'];
				$lat = $degrees + ($minutes/60.0);
				if ($hemisphere == 'S') { $lat *= -1.0; };

				$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;

				$degrees = $matches['longitude_degrees'];
				$minutes = $matches['longitude_minutes'];
				$hemisphere = $matches['longitude_hemisphere'];
				$long = $degrees + ($minutes/60.0);
				if ($hemisphere == 'W') { $long *= -1.0; };
				
				$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]  = $long;
				
				$matched = true;
			}
			
			if (!$matched)
			{
				
				//26'11'24N 81'48'16W
				
				//echo $seq['source']['locality'] . "\n";
				
				if (preg_match("/
				(?<latitude_degrees>[0-9]{1,2})
				'
				(?<latitude_minutes>[0-9]{1,2})
				'
				((?<latitude_seconds>[0-9]{1,2})
				'?)?
				(?<latitude_hemisphere>[S|N])
				\s+
				(?<longitude_degrees>[0-9]{1,3})
				'
				(?<longitude_minutes>[0-9]{1,2})
				'
				((?<longtitude_seconds>[0-9]{1,2})
				'?)?
				(?<longitude_hemisphere>[W|E])
				/x", $locality_string, $matches))
				{
					if ($debug) { print_r($matches); }	
						
					$degrees = $matches['latitude_degrees'];
					$minutes = $matches['latitude_minutes'];
					$seconds = $matches['latitude_seconds'];
					$hemisphere = $matches['latitude_hemisphere'];
					$lat = $degrees + ($minutes/60.0) + ($seconds/3600);
					if ($hemisphere == 'S') { $lat *= -1.0; };
	
					$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;
	
					$degrees = $matches['longitude_degrees'];
					$minutes = $matches['longitude_minutes'];
					$seconds = $matches['longtitude_seconds'];
					$hemisphere = $matches['longitude_hemisphere'];
					$long = $degrees + ($minutes/60.0) + ($seconds/3600);
					if ($hemisphere == 'W') { $long *= -1.0; };
					
					$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
					
					//print_r($seq);
					
					//exit();
					
					$matched = true;
				}
			}
			//exit();

			
		}
		
		if ($debug)
		{
			echo "Trying line " . __LINE__ . "\n";
		}
		
		
		//(GPS: 33 38' 07'', 146 33' 12'') e.g. AY281244
		if (preg_match("/\(GPS:\s*([0-9]{1,2})\s*([0-9]{1,2})'\s*([0-9]{1,2})'',\s*([0-9]{1,3})\s*([0-9]{1,2})'\s*([0-9]{1,2})''\)/", $country, $matches))
		{
			if ($debug) { print_r($matches); }	
			
			$lat = $matches[1] + $matches[2]/60 + $matches[3]/3600;
			
			// OMG
			if ($seq['source']['country'] == 'Australia')
			{
				$lat *= -1.0;
			}
			$long = $matches[4] + $matches[5]/60 + $matches[6]/3600;

			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]  = $lat;
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]  = $long;
			
		}
		
		if ($debug)
		{
			echo "Trying line " . __LINE__ . "\n";
		}
		
		
		// AJ556909
		// 2o54'59''N 98o38'24''E			
		if (preg_match("/
			(?<latitude_degrees>[0-9]{1,2})
			o
			(?<latitude_minutes>[0-9]{1,2})
			'
			(?<latitude_seconds>[0-9]{1,2})
			''
			(?<latitude_hemisphere>[S|N])
			\s+
			(?<longitude_degrees>[0-9]{1,3})
			o
			(?<longitude_minutes>[0-9]{1,2})
			'
			(?<longtitude_seconds>[0-9]{1,2})
			''
			(?<longitude_hemisphere>[W|E])
			/x", $locality_string, $matches))
		{
			if ($debug) { print_r($matches); }	
				
			$degrees = $matches['latitude_degrees'];
			$minutes = $matches['latitude_minutes'];
			$seconds = $matches['latitude_seconds'];
			$hemisphere = $matches['latitude_hemisphere'];
			$lat = $degrees + ($minutes/60.0) + ($seconds/3600);
			if ($hemisphere == 'S') { $lat *= -1.0; };

			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $lat;

			$degrees = $matches['longitude_degrees'];
			$minutes = $matches['longitude_minutes'];
			$seconds = $matches['longtitude_seconds'];
			$hemisphere = $matches['longitude_hemisphere'];
			$long = $degrees + ($minutes/60.0) + ($seconds/3600);
			if ($hemisphere == 'W') { $long *= -1.0; };
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $long;
			
		}
		
		
	}
	
	if ($debug)
	{
		echo "Trying line " . __LINE__ . "\n";
	}
	/*
	// Some records have lat and lon in isolation_source, e.g. AY922971
	if (isset($locality->isolation_source))
	{
		$isolation_source = $locality->isolation_source;
		$matches = array();
		if (preg_match('/([0-9]+\.[0-9]+) (N|S), ([0-9]+\.[0-9]+) (W|E)/i', $isolation_source, $matches))
		{
			if ($debug) { print_r($matches); }	
			
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0] = $matches[1];
			if ($matches[2] == 'S')
			{
				$seq['source']['latitude'] *= -1;
			}
			$locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0] = $matches[3];
			if ($matches[4] == 'W')
			{
				$seq['source']['longitude'] *= -1;
			}
			if  (!isset($locality->decimalLocality))
			{
				$locality->decimalLocality = $locality->isolation_source;
			}
		}
	}
	*/
	
}	


//----------------------------------------------------------------------------------------
function genbank_xml_to_jsonld($xml)
{		
	$objects = array();
	
	// delete some things which may cause problems for JSON
	$xml = str_replace('<GBFeature_partial5 value="true"/>', '', $xml);
	$xml = str_replace('<GBFeature_partial3 value="true"/>', '', $xml);
	$xml = str_replace('<GBQualifier_value></GBQualifier_value>', '', $xml);

	if ($xml != '')
	{
		$xp = new XsltProcessor();
		$xsl = new DomDocument;
		$xsl->load(dirname(__FILE__) . '/xml2json.xslt');
		$xp->importStylesheet($xsl);
		
		$dom = new DOMDocument;
		$dom->loadXML($xml);
		$xpath = new DOMXPath($dom);
	
		$json = $xp->transformToXML($dom);
		
		//echo $json;
	
		// fix "-" in variable names
		// fix "-" in variable names
		$json = str_replace('"GBSeq_feature-table"', 		'"GBSeq_feature_table"', $json);
		$json = str_replace('"GBSeq_primary-accession"', 	'"GBSeq_primary_accession"', $json);
		$json = str_replace('"GBSeq_other-seqids"', 		'"GBSeq_other_seqids"', $json);

		$json = str_replace('"GBSeq_update-date"', 			'"GBSeq_update_date"', $json);
		$json = str_replace('"GBSeq_create-date"', 			'"GBSeq_create_date"', $json);
		$json = str_replace('"GBSeq_accession-version"', 	'"GBSeq_accession_version"', $json);
		
		// idiosyncratic fixes
		// JF279882
		$json = str_replace('"GBQualifier_value":45307E', 	'"GBQualifier_value":"45307E"', $json);
		
		
		$sequences = json_decode($json);
				
		if (!isset($sequences->GBSet))
		{
			echo "Not found\n";
			exit();
		}
	
		foreach ($sequences->GBSet as $GBSet)
		{		
			// Array of objects 
			$items = array();

			// References
			$references = array();
			
			// Sequence
			$sequence = new stdclass;
			
			// Locality
			$locality = null;
			
			// Event
			$event =  null;
			
			// identification
			$identification = null;
			

			
		
			// Occurrence (core object)
			$occurrence = new stdclass;
			$occurrence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Occurrence';
			
			$occurrence->{'@id'} = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession . '/source';
			
			// metadata
			$metadata = new stdclass;
			$metadata->{'@id'} = $occurrence->{'@id'} . '/about';
			$metadata->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Dataset';
			$metadata->{'http://schema.org/publisher'}[] = 'NCBI';
			$metadata->{'http://schema.org/about'}[] = $occurrence->{'@id'};
			
			
			// Link to nucleotide sequence
			$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedSequences'}[] = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession;
			
			// Sequence identifiers
			
			
			$sequence->{'http://purl.org/dc/terms/identifier'}[] = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession;
			
			
			foreach ($GBSet->GBSeq_other_seqids as $seqids)
			{
				if (preg_match('/gi\|(?<gi>\d+)$/', $seqids, $m))
				{
					$sequence->{'http://purl.org/dc/terms/identifier'}[] = 'http://www.ncbi.nlm.nih.gov/nuccore/' . $m['gi'];
				}
			}
			
			
			$occurrence->{'http://purl.org/dc/terms/description'}[] = $GBSet->GBSeq_definition;
			
			// dates
			if (false != strtotime($GBSet->GBSeq_update_date))
			{
				$metadata->{'http://schema.org/dateModified'}[] = date("Y-m-d", strtotime($GBSet->GBSeq_update_date));
			}	
			if (false != strtotime($GBSet->GBSeq_create_date))
			{
				$metadata->{'http://schema.org/dateCreated'}[] = date("Y-m-d", strtotime($GBSet->GBSeq_create_date));
			}
			
			/*
			// keywords
			if (isset($GBSet->GBSeq_keywords))
			{
				$genbank_sequence->keywords = array();
				foreach ($GBSet->GBSeq_keywords as $k => $v)
				{
					$genbank_sequence->keywords[] = $v;
				}
			}
			*/
			
			// taxonomy
			// Treat this as an "Identification"
			$identification = new stdclass;
			$identification->{'@id'} = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession . '/Identification';
			$occurrence->{'http://rs.tdwg.org/dwc/terms/identificationID'}[] = $identification->{'@id'};
			$identification->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Identification';
			
			$identification->{'http://rs.tdwg.org/dwc/terms/higherClassification'}[] = $GBSet->GBSeq_taxonomy;
			$identification->{'http://rs.tdwg.org/dwc/terms/scientificName'}[] = $GBSet->GBSeq_organism;
			
			// Can we unpack the GBSeq_taxonomy string to help match to GBIF records?
			
			
			
			
			/*
			// project, sample ids
			if (isset($GBSet->GBSeq_project))
			{
				$genbank_sequence->project = $GBSet->GBSeq_project;
			}
			*/
			/*
			"GBSeq_xrefs": [
				{
				  "GBXref_dbname": "BioProject",
				  "GBXref_id": "PRJNA39517"
				},
				{
				  "GBXref_dbname": "BioSample",
				  "GBXref_id": "SAMN02743868"
				}
			  ]
			*/
			
			/*
			if (isset($GBSet->GBSeq_xrefs))
			{
				$genbank_sequence->xrefs = array();
				foreach ($GBSet->GBSeq_xrefs as $xref)
				{
					$genbank_sequence->xrefs[$xref->GBXref_dbname] = $xref->GBXref_id;
				}
			}
			*/
			
			
			// comment, including whether tenative id not provided => suppressed
			if (isset($GBSet->GBSeq_comment))
			{
				$comment = $GBSet->GBSeq_comment;
				$comment = str_replace('~', "\n", $comment);
				$occurrence->{'http://purl.org/dc/terms/description'}[] = $comment;
				
				/*
				if (preg_match('/Tentative Name not provided/i', $GBSet->GBSeq_comment))
				{
					$genbank_sequence->suppressed = true;
				}
				*/
			}
			
			
			//   
		  /*
		   <GBSeq_xrefs>
			<GBXref>
			  <GBXref_dbname>BioProject</GBXref_dbname>
			  <GBXref_id>PRJNA39517</GBXref_id>
			</GBXref>
		  </GBSeq_xrefs>
			*/
			
			/*
			$GBSeq_xrefs = $xpath->query('GBSeq_xrefs/GBXref', $GBSeq);
			foreach ($GBSeq_xrefs as $GBXref)
			{
				$nodeCollection = $xpath->query('GBXref_dbname', $GBXref);
				foreach ($nodeCollection as $node)
				{
					$dbname =  $node->firstChild->nodeValue;
					$GBXref_id = $xpath->query('GBXref_id', $GBXref);
					foreach ($GBXref_id as $id)
					{
						$genbank_sequence->xrefs[$dbname] = $id->firstChild->nodeValue;
					}
				}
			}
			*/
			
			//http://purl.org/dc/terms/isReferencedBy		
			
			// References
			$reference_count = 1;
			foreach ($GBSet->GBSeq_references as $GBReference)
			{
				// Do we have an external identifier?
				
				$pmid = 0;
				$doi = '';
				
				if (isset($GBReference->GBReference_pubmed))
				{
					$pmid = $GBReference->GBReference_pubmed;
				}
				
				if (isset($GBReference->GBReference_xref))
				{
					if ($GBReference->GBReference_xref->GBXref->GBXref_dbname == 'doi')
					{
						$doi = $GBReference->GBReference_xref->GBXref->GBXref_id;
					}
				}			
				
				if (($pmid <> 0) || ($doi != ''))
				{
					$reference = new stdclass;
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';		
					
					if ($pmid != 0)
					{
						$reference->{'@id'} = 'http://identifiers.org/pubmed/' . $pmid;
					}
					else
					{
						$reference->{'@id'} = 'http://identifiers.org/doi/' . $doi;
					}
					
					$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedReferences'}[] = $reference->{'@id'};
					$items[] = $reference;
				}
				else
				{
					// Construct reference object from metadata (bnode)
					$skip = false;  // use flag to skip some references (e.g., direct submission)
					
					$reference = new stdclass;
					$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';		
					
					// create id
					$reference->{'@id'} = $occurrence->{'@id'} . '/reference/' . $reference_count;
			
					// title
					$reference->{'http://purl.org/dc/terms/title'}[] = $GBReference->GBReference_title;
					$reference->{'http://schema.org/name'}[] = $GBReference->GBReference_title;
					
					// bibliographic citation
					if (isset($GBReference->GBReference_journal))
					{
						$reference->{'http://purl.org/dc/terms/bibliographicCitation'}[] = $GBReference->GBReference_journal;

						$journal = true;
						
						if ($GBReference->GBReference_title == 'Direct Submission')
						{
							$skip = true;
							$journal = false;
						}
						
						if ($journal)
						{
							$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/AcademicArticle';
							$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ScholarlyArticle';
							
							// Parse citation string into component parts							
							if (preg_match('/(?<journal>.*)\s+(?<volume>\d+)(\s+\((?<issue>.*)\))?,\s+(?<spage>\d+)-(?<epage>\d+)\s+\((?<year>[0-9]{4})\)/', $GBReference->GBReference_journal, $m))
							{
								$reference->{'http://prismstandard.org/namespaces/basic/2.1/publicationName'}[] = $m['journal'];
							
								$reference->{'http://purl.org/ontology/bibo/volume'}[] = $m['volume'];
								
								if ($m['issue'] != '')
								{
									$reference->{'http://purl.org/ontology/bibo/issue'}[] = $m['issue'];
								}
								$reference->{'http://purl.org/ontology/bibo/pageStart'}[] = $m['spage'];
								if ($m['epage'] != '')
								{
									$reference->{'http://purl.org/ontology/bibo/pageEnd'}[] = $m['epage'];
								}
								$reference->{'http://purl.org/dc/terms/date'}[] = $m['year'];
							}
						}
					}
					
					if (!$skip)
					{
						// authors
						if (isset($GBReference->GBReference_authors))
						{
							$count = 1;
							foreach ($GBReference->GBReference_authors as $a)
							{
								$author = new stdclass;
								$author->{'@id'} = $reference->{'@id'} . '/contributor/' . $count;
				
				
								$author->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Person';
				
								$author->{'http://schema.org/name'}[] = $a;
							
				
								$reference->{'http://purl.org/dc/terms/creator'}[] = $author->{'@id'};
				
								$items[] = $author;
								$count++;
							}
						}
					}
					
					// store reference
					if (!$skip)
					{
						$items[] = $reference;
					}
					
					// link to occurrence
					$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedReferences'}[] = $reference->{'@id'};
					
					
				}
				$reference_count++;
			}
				
				/*
				
			
				$reference = new stdclass;
				$reference->title = $GBReference->GBReference_title;
				$reference->citation = $GBReference->GBReference_journal;
				if (isset($GBReference->GBReference_authors))
				{
					foreach ($GBReference->GBReference_authors as $a)
					{
						$parts = parse_name($a);					
						$author = new stdClass();
						if (isset($parts['last']))
						{
							$author->lastname = $parts['last'];
						}
						if (isset($parts['first']))
						{
							$author->firstname = $parts['first'];
							
							if (array_key_exists('middle', $parts))
							{
								$author->firstname .= ' ' . $parts['middle'];
							}
							$author->firstname = preg_replace('/\.([A-Z])/', '. $1', $author->firstname);
							
						}
						if (isset($author->firstname) && isset($author->lastname))
						{
							$author->name = $author->firstname . ' ' . $author->lastname;
						}
						else
						{
							$author->name = $a;
						}


						$reference->author[] = $author;					
					}
				}		
				
				if (preg_match('/(?<journal>.*)\s+(?<volume>\d+)(\s+\((?<issue>.*)\))?,\s+(?<spage>\d+)-(?<epage>\d+)\s+\((?<year>[0-9]{4})\)/', $reference->citation, $m))
				{
					$reference->journal = new stdclass;
					$reference->journal->name = $m['journal'];
					
					$reference->journal->volume = $m['volume'];
					if ($m['issue'] != '')
					{
						$reference->journal->issue = $m['issue'];
					}
					$reference->journal->pages = $m['spage'];
					if ($m['epage'] != '')
					{
						$reference->journal->pages .= '--' . $m['epage'];
					}
				}
				
				
				if (isset($GBReference->GBReference_pubmed))
				{
					$identifier = new stdclass;
					$identifier->type = 'pmid';
					$identifier->id = (Integer)$GBReference->GBReference_pubmed;
					$reference->identifier[] = $identifier;
				}
				
				if (isset($GBReference->GBReference_xref))
				{
					if ($GBReference->GBReference_xref->GBXref->GBXref_dbname == 'doi')
					{
						$identifier = new stdclass;
						$identifier->type = 'doi';
						$identifier->id = $GBReference->GBReference_xref->GBXref->GBXref_id;
						$reference->identifier[] = $identifier;
					}
				}
				
				$genbank_sequence->references[] = $reference;
			}	
			*/							
			
			
			foreach ($GBSet->GBSeq_feature_table as $feature_table)
			{
				switch ($feature_table->GBFeature_key)
				{
					case 'source':
						foreach ($feature_table->GBFeature_quals as $feature_quals)
						{
							switch ($feature_quals->GBQualifier_name)
							{
								// Database cross links
								case 'db_xref':
									// NCBI taxonomy
									if (preg_match('/taxon:(?<id>\d+)$/', $feature_quals->GBQualifier_value, $m))
									{
										$identification->{'http://rs.tdwg.org/dwc/terms/taxonID'}[] = 'http://identifiers.org/taxonomy/' . $m['id'];
									}
									
									// DNA barcode
									if (preg_match('/BOLD:(?<id>.*)$/', $feature_quals->GBQualifier_value, $m))
									{
										$bold = $m['id'];
										$bold = str_replace('.COI-5P', '', $bold);
														
										$occurrence->{'http://schema.org/sameAs'}[] = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $bold;
									}
									break;
									
								// Locality
								case 'country':
								case 'locality':
									if (!$locality)
									{
										$locality = new stdclass;
										$locality->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/terms/Location';
									}
									$locality->{'http://rs.tdwg.org/dwc/terms/' . $feature_quals->GBQualifier_name}[] = $feature_quals->GBQualifier_value;
									break;
									
								// to do:
								// isolate, isolation source, host, etc.
								
								case 'isolate':
									$occurrence->{'http://rs.tdwg.org/dwc/terms/recordNumber'}[] = $feature_quals->GBQualifier_value;
									break;
									
								// latitude and longitude (needs parsing)
								case 'lat_lon':
									if (!$locality)
									{
										$locality = new stdclass;
										$locality->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/terms/Location';
									}
									process_lat_lon($locality, $feature_quals->GBQualifier_value);
									break;
									
								case 'collection_date':
									if (!$event)
									{
										$event = new stdclass;
										$event->{'@id'} = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession . '/Event';
										$occurrence->{'http://rs.tdwg.org/dwc/terms/eventID'}[] = $event->{'@id'};
										$event->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Event';
									}
									$event->{'http://rs.tdwg.org/dwc/terms/verbatimEventDate'}[] = $feature_quals->GBQualifier_value;
									
									// This will need a lot of care as dates can be crap
									// GQ260888 2007
									// GQ260888 Aug-2005
									/*
									// Can we interpret the collection date?
									if (false != strtotime($feature_quals->GBQualifier_value))
									{
										$date = date("Y-m-d", strtotime($feature_quals->GBQualifier_value));
										$event->{'http://rs.tdwg.org/dwc/terms/eventDate'}[] = $date;
										if (preg_match('/(?<year>[0-9]{4})-(?<month>[0-9]{2})-(?<day>[0-9]{2})/', $date, $m))
										{
											$event->{'http://rs.tdwg.org/dwc/terms/year'}[] = $m['year'];
											$event->{'http://rs.tdwg.org/dwc/terms/month'}[] = preg_replace('/^0/', '', $m['month']);
											$event->{'http://rs.tdwg.org/dwc/terms/day'}[] = preg_replace('/^0/', '', $m['day']);
										}
									}
									*/
									break;
									
								case 'identified_by':
									$identification->{'http://rs.tdwg.org/dwc/terms/identifiedBy'}[] = $feature_quals->GBQualifier_value;
									break;
									
								case 'specimen_voucher':
									//echo $feature_quals->GBQualifier_value . "\n";
									$occurrence->{'http://rs.tdwg.org/dwc/terms/otherCatalogNumbers'}[] = $feature_quals->GBQualifier_value;
									
									$occurrence->{'http://schema.org/alternateName'}[] = $feature_quals->GBQualifier_value;
								
									// Try to interpret them
									$matched = false;
									
									// TM<ZAF>40766
									if (!$matched)
									{
										if (preg_match('/^(?<institutionCode>(?<prefix>[A-Z]+)\<[A-Z]+\>)(?<catalogNumber>\d+)$/', $feature_quals->GBQualifier_value, $m))
										{
											$occurrence->{'http://rs.tdwg.org/dwc/terms/institutionCode'}[] =  $m['institutionCode'];
											$occurrence->{'http://rs.tdwg.org/dwc/terms/catalogNumber'}[] =  $m['catalogNumber'];
											
											$occurrence->{'http://rs.tdwg.org/dwc/terms/otherCatalogNumbers'}[] = $m['institutionCode'] . ' ' . $m['catalogNumber'];
											$occurrence->{'http://schema.org/alternateName'}[] = $m['institutionCode'] . ' ' . $m['catalogNumber'];
											
											$occurrence->{'http://rs.tdwg.org/dwc/terms/otherCatalogNumbers'}[] = $m['prefix'] . ' ' . $m['catalogNumber'];
											$occurrence->{'http://schema.org/alternateName'}[] = $m['prefix'] . ' ' . $m['catalogNumber'];
											
											$matched = true;
										}
									}
									
									
									if (!$matched)
									{
										if (preg_match('/^(?<institutionCode>[A-Z]+):(?<collectionCode>.*):(?<catalogNumber>\d+)$/', $feature_quals->GBQualifier_value, $m))
										{
											$occurrence->{'http://rs.tdwg.org/dwc/terms/institutionCode'}[] =  $m['institutionCode'];
											$occurrence->{'http://rs.tdwg.org/dwc/terms/collectionCode'}[] =  $m['collectionCode'];
											$occurrence->{'http://rs.tdwg.org/dwc/terms/catalogNumber'}[] =  $m['catalogNumber'];
											
											$occurrence->{'http://rs.tdwg.org/dwc/terms/otherCatalogNumbers'}[] = $m['institutionCode'] . ' ' . $m['catalogNumber'];
											$occurrence->{'http://schema.org/alternateName'}[] = $m['institutionCode'] . ' ' . $m['catalogNumber'];
											$matched = true;
										}
									}
									
									if (!$matched)
									{
										if (preg_match('/^(?<institutionCode>[A-Z]+)[\s|:]?(?<catalogNumber>\d+)$/', $feature_quals->GBQualifier_value, $m))
										{
											$occurrence->{'http://rs.tdwg.org/dwc/terms/institutionCode'}[] =  $m['institutionCode'];
											$occurrence->{'http://rs.tdwg.org/dwc/terms/catalogNumber'}[] =  $m['catalogNumber'];
											$occurrence->{'http://schema.org/alternateName'}[] = $m['institutionCode'] . ' ' . $m['catalogNumber'];
											
											// post process to help matching
											switch ($m['institutionCode'])
											{
												case 'KUNHM':
													$occurrence->{'http://schema.org/alternateName'}[] = 'KU' .  ' ' . $m['catalogNumber'];
													break;
													
												default:
													break;
											}
											
											
											
											$matched = true;
										}
									}
									break;
									
								// what other terms will we process?


								default:
									//$genbank_sequence->source->{$feature_quals->GBQualifier_name} = $feature_quals->GBQualifier_value;								
									break;
							}					
						}						
						break;
						
					/*
					case 'CDS':
					case 'rRNA':
						foreach ($feature_table->GBFeature_quals as $feature_quals)
						{
							switch ($feature_quals->GBQualifier_name)
							{
								case 'gene':
									if (!isset($genbank_sequence->gene))
									{
										$genbank_sequence->gene = array();
									}
									$genbank_sequence->gene[] = $feature_quals->GBQualifier_value;
									break;

								case 'product':
									if (!isset($genbank_sequence->product))
									{
										$genbank_sequence->product = array();
									}
									$genbank_sequence->product[] = $feature_quals->GBQualifier_value;
									break;

								default:
									break;
							}					
						}						
						break;
					*/
						
						
					default:
						break;
				}
			
			}
			
			
			$sequence->{'@id'} = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession;
						
			//$sequence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://identifiers.org/so/SO:0000110';
			$sequence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://ddbj.nig.ac.jp/ontologies/nucleotide/Sequence';
			$sequence->{'http://ddbj.nig.ac.jp/ontologies/nucleotide/sequence'}[] = $GBSet->GBSeq_sequence;


			
			
			/*
			if (isset($genbank_sequence->gene))
			{
				$genbank_sequence->gene = array_unique($genbank_sequence->gene);
			}
			if (isset($genbank_sequence->product))
			{
				$genbank_sequence->product = array_unique($genbank_sequence->product);
			}
			*/
			
			
			
			//print_r($occurrence);
			
			$items[] = $occurrence;
			$items[] = $sequence;
			
			// Post-process event
			if ($locality)
			{
				process_locality($locality);
				
				if (isset($locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}) && isset($locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}))
				{
					$geohash = new GeoHash();
					$geohash->SetLatitude($locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]);
					$geohash->SetLongitude($locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]);
		
					$locality->{'@id'} = 'http://geohash.org/' . $geohash->getHash();
		
				}
				else
				{
					$locality->{'@id'} = 'http://identifiers.org/insdc/' . $GBSet->GBSeq_primary_accession . '/Location';
				}

				$items[] = $locality;
				$occurrence->{'http://rs.tdwg.org/dwc/terms/locationID'}[] = $locality->{'@id'};
				
				
			}			
			
			// Add "identification" which makes link betwen occurrence and NCBI taxonomy
			$items[] = $identification;
			
			$items[] = $metadata;
			
			if ($event)
			{
				$items[] = $event;
			}
			
			
			//print_r($items);
			//echo "======\n";
			// Context for JSON-LD
			$context = new stdclass;

			// Darwin Core
			$context->{'@vocab'} = 'http://rs.tdwg.org/dwc/terms/';

			// Dublin Core
			$context->dc = 'http://purl.org/dc/terms/';
			$context->bibliographicCitation = 'dc:bibliographicCitation';
			$context->title = 'dc:title';
			$context->description = 'dc:description';
			$context->date = 'dc:date';
			$context->identifier = 'dc:identifier';
			$context->Location = 'dc:Location';

			//$context->dcmitype = 'http://purl.org/dc/dcmitype/';
			//$context->PhysicalObject = 'dcmitype:PhysicalObject';

			// Schema.org
			$context->schema = 'http://schema.org/';
			$context->sameAs = 'schema:sameAs';
			$context->about = 'schema:about';
			//$context->fileFormat = 'schema:fileFormat';
			//$context->ImageObject = 'schema:ImageObject';
			//$context->contentUrl = 'schema:contentUrl';
			$context->name 			= 'schema:name';
			$context->dateCreated = 'schema:dateCreated';
			$context->dateModified = 'schema:dateModified';
			$context->alternateName = 'schema:alternateName';
			$context->publisher = 'schema:publisher';
	
			// Schema types
			$context->CreativeWork 		= 'schema:CreativeWork';
			$context->Periodical 		= 'schema:Periodical';
			$context->Person 			= 'schema:Person';
			$context->ScholarlyArticle 	= 'schema:ScholarlyArticle';
			$context->Dataset 			= 'schema:Dataset';
			
			$context->alternateName = 'schema:alternateName';
			
			// Bibo
			$context->volume = 'http://purl.org/ontology/bibo/volume';
			$context->issue = 'http://purl.org/ontology/bibo/issue';
			$context->pageStart = 'http://purl.org/ontology/bibo/pageStart';
			$context->pageEnd = 'http://purl.org/ontology/bibo/pageEnd';
      
      		$context->AcademicArticle = 'http://purl.org/ontology/bibo/AcademicArticle';
      		
      		// Prism
      		$context->publicationName = 'http://prismstandard.org/namespaces/basic/2.1/publicationName';
 			

			// Identifiers.org namespaces
			$context->identifiers = 'http://identifiers.org/';
			$context->DOI = 'identifiers:doi/';
			$context->INSDC = 'identifiers:insdc/';
			$context->NCBI = 'identifiers:taxonomy/';
			$context->PMID = 'identifiers:pubmed/';

			// DDBJ
			$context->DDBJ = 'http://ddbj.nig.ac.jp/ontologies/nucleotide/';
			$context->Sequence = 'DDBJ:Sequence';
			$context->sequence = 'DDBJ:sequence';
			
			// Other identifiers
			$context->GI			= 'http://www.ncbi.nlm.nih.gov/nuccore/';			


			// Sequence Ontology
			//$context->SO = 'identifiers:so/SO:';


			$context->BOLD = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=';

			$context->geohash = 'http://geohash.org/';
			
			
			// Convert object to list of RDF nquads 
			$nq = item_to_quads($items);


			$jsonld = jsonld_from_rdf($nq);
			$jsonld = jsonld_compact($jsonld, $context);

			$jsonld->_id = 'http://www.ncbi.nlm.nih.gov/nuccore/' . $GBSet->GBSeq_primary_accession;
			
			$objects[] = $jsonld;

			//echo json_format(json_encode($jsonld));

			//echo "CouchDB...\n";
			//$couch->add_update_or_delete_document($jsonld,  $jsonld->_id);
			
	
		}
		
	}
	
	return $objects;
}

// JN270496
// http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=nucleotide&id=JN270496&rettype=gb&retmode=xml

// To fetch without sequence (handy for skipping genomes)
// add &seq_start=1&seq_stop=1

//$xml = file_get_contents('JQ173912.xml');
//genbank_xml_to_jsonld($xml);

//----------------------------------------------------------------------------------------
function genbank_fetch($id)
{
	$objects = array();
	
	// API call
	$parameters = array(
		'db' 		=> 'nucleotide',
		'id'		=> $id,
		'rettype'	=> 'gb',
		'retmode'	=> 'xml'
		
		// skip sequences so that we don't baff over genomes
		//'seq_start'	=> 1,
		//'seq_stop'	=> 1
	);
	
	$url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query($parameters);
	
	//echo $url;
	
	$xml = get($url);
	
	//echo "xml=$xml\n";
	
	if ($xml != '')
	{
		$objects = genbank_xml_to_jsonld($xml);
	}

	//print_r($objects);
	
	return ($objects);
}
	
//----------------------------------------------------------------------------------------

if (1)
{
	genbank_fetch('GU904421');
}

if (0)
{
	$xml = file_get_contents('JQ173912.xml');
	$objects = genbank_xml_to_jsonld($xml);
	print_r($objects);
	
}

?>
