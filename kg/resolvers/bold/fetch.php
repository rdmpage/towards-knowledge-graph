<?php

// Fetch DNA barcode
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/lib.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');

// https://github.com/asonge/php-geohash
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/geohash.php');


// fetch a barcode sequence

// http://www.boldsystems.org/index.php/API_Public/combined?ids=USNMD174-11&format=tsv
// http://www.boldsystems.org/index.php/API_Public/combined?ids=ASDOR472-10&format=tsv
// http://www.boldsystems.org/index.php/API_Public/combined?ids=ABCSA085-06&format=tsv

// http://www.boldsystems.org/index.php/API_Public/combined?ids=ASANH1000-10&format=tsv


//$data = file_get_contents('USNMD174-11.txt');

//$data = file_get_contents('ABCSA085-06.txt');

//$data = file_get_contents('ASANH1000-10.txt');


//----------------------------------------------------------------------------------------
// Map barcode column headings to Darwin Core terms
$barcode_to_darwincore = array(
	'catalognum' 			=> 'catalogNumber',
	'collectiondate'		=> 'verbatimEventDate',
	'collectors'			=> 'recordedBy',
	'country' 				=> 'country',
	'fieldnum'				=> 'recordNumber',
	'institution_storing' 	=> 'institutionCode',
	'lat' 					=> 'decimalLatitude',
	'lifestage'				=> 'lifeStage',
	'lon' 					=> 'decimalLongitude',
	'province' 				=> 'stateProvince',
	'reproduction'			=> 'reproductionCondition',
	'sampleid'				=> 'otherCatalogNumbers',
	'sex'					=> 'sex',
	
	'phylum_name'			=> 'phylum',
	'order_name'			=> 'order',
	'class_name'			=> 'class',
	'family_name'			=> 'family',
	'genus_name'			=> 'genus',
	'species_name'			=> 'species'
);


//----------------------------------------------------------------------------------------
function barcode_parse($data)
{
	global $barcode_to_darwincore;
	
	$objects = array();

	$lines = explode("\n", $data);

	$keys = array();
	$row_count = 0;

	foreach ($lines as $line)
	{
		if ($line == '') break;
		$row = explode("\t", $line);
	
		if ($row_count == 0)
		{
			$keys = $row;
		}
		else
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
		
		
			$n = count($row);
			for ($i = 0; $i < $n; $i++)
			{
				if (trim($row[$i]) != '')
				{
					switch ($keys[$i])
					{
						case 'processid':
							$occurrence->{'@id'} = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $row[$i];
						
							$sequence->{'@id'} = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $row[$i] . '/sequence';
							$sequence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://ddbj.nig.ac.jp/ontologies/nucleotide/Sequence';

							$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedSequences'}[0] = $sequence->{'@id'};
							break;
				
						// Location
						case 'country':
						case 'province':
						case 'lat':
						case 'lon':
							if (!$locality)
							{
								$locality = new stdclass;
								$locality->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/terms/Location';
							}
							$locality->{'http://rs.tdwg.org/dwc/terms/' . $barcode_to_darwincore[$keys[$i]]}[] = $row[$i];
							break;
						
						
						// Event
						case 'collectiondate':
							if (!$event)
							{
								$event = new stdclass;
								$event->{'@id'} = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $row[0] . '/Event';

								$occurrence->{'http://rs.tdwg.org/dwc/terms/eventID'}[] = $event->{'@id'};
								$event->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Event';
							}
							$event->{'http://rs.tdwg.org/dwc/terms/' . $barcode_to_darwincore[$keys[$i]]}[] = $row[$i];
						
							// Can we interpret the collection date?
							if (false != strtotime($row[$i]))
							{
								$date = date("Y-m-d", strtotime($row[$i]));
								$event->{'http://rs.tdwg.org/dwc/terms/eventDate'}[] = $date;
								if (preg_match('/(?<year>[0-9]{4})-(?<month>[0-9]{2})-(?<day>[0-9]{2})/', $date, $m))
								{
									$event->{'http://rs.tdwg.org/dwc/terms/year'}[] = $m['year'];
									$event->{'http://rs.tdwg.org/dwc/terms/month'}[] = preg_replace('/^0/', '', $m['month']);
									$event->{'http://rs.tdwg.org/dwc/terms/day'}[] = preg_replace('/^0/', '', $m['day']);
								}
							}						
							break;
						
						// Occurrence
						case 'catalognum':
						case 'collectors':
						case 'fieldnum':
						case 'institution_storing':	
						case 'lifestage':
						case 'reproduction':
						case 'sampleid':
						case 'sex':
							$occurrence->{'http://rs.tdwg.org/dwc/terms/' . $barcode_to_darwincore[$keys[$i]]}[] = $row[$i];
							break;
						
						// Identification
						case 'phylum_name':
						case 'order_name':
						case 'class_name':	
						case 'family_name':	
						case 'genus_name':
						case 'species_name':
							if (!$identification)
							{
								$identification = new stdclass;
								$identification->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Identification';
							}
							$identification->{'http://rs.tdwg.org/dwc/terms/' . $barcode_to_darwincore[$keys[$i]]}[] = $row[$i];
							break;
						
						case 'bin_uri':
							if (!$identification)
							{
								$identification = new stdclass;
								$identification->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Identification';
							}
							$identification->{'@id'} = 'http://www.boldsystems.org/index.php/Public_BarcodeCluster?clusteruri=' . $row[$i];
							$occurrence->{'http://rs.tdwg.org/dwc/terms/identificationID'}[] = $identification->{'@id'};
							break;
						
						// Sequence
						case 'genbank_accession':
							$sequence->{'http://schema.org/sameAs'}[] = 'http://identifiers.org/insdc/' . $row[$i];
							break;
						
						case 'nucleotides':
							$sequence->{'http://ddbj.nig.ac.jp/ontologies/nucleotide/sequence'}[] = $row[$i];
							break;
						
						case 'markercode':
							$sequence->{'@id'} = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $row[0] . '.' . $row[$i];
							$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedSequences'}[0] = $sequence->{'@id'};
							break;
						
						// Image
						case 'image_urls':
							$images = explode("|", $row[$i]);
							foreach ($images as $image_url)
							{
								$image = new stdclass;
								$image->{'@id'} = $image_url;
								$image->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ImageObject';
								$image->{'http://schema.org/about'}[] = $occurrence->{'@id'};
								$image->{'http://schema.org/contentUrl'}[] = $image_url;
							
								$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedMedia'}[] = $image->{'@id'};
								$items[] = $image;
							}
							break;
	

					
						default:
							break;
					}
				}
			}
		
			//--------------------------------------------------------------------------------
			// Post-process locality
			if ($locality)
			{
				// generate id for locality
		
				if (isset($locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}) && isset($locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}))
				{
					$geohash = new GeoHash();
					$geohash->SetLatitude($locality->{'http://rs.tdwg.org/dwc/terms/decimalLatitude'}[0]);
					$geohash->SetLongitude($locality->{'http://rs.tdwg.org/dwc/terms/decimalLongitude'}[0]);
		
					$locality->{'@id'} = 'http://geohash.org/' . $geohash->getHash();
		
				}
				else
				{
					$locality->{'@id'} = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=' . $row[0] . '/Location';
				}

				$items[] = $locality;
				$occurrence->{'http://rs.tdwg.org/dwc/terms/locationID'}[] = $locality->{'@id'};
			}
		
			//-----------------------------------------------------------------------------------------
			// Post-process event
			if ($event)
			{
				$items[] = $event;
			}
		
		
		
			$items[] = $occurrence;
			$items[] = $sequence;
			$items[] = $identification;
		
			if ($locality)
			{
				$items[] = $locality;
			}
		
			// RDF
		
			// Context for JSON-LD
			$context = new stdclass;

			// Darwin Core
			$context->{'@vocab'} = 'http://rs.tdwg.org/dwc/terms/';

			// Dublin Core
			$context->dc = 'http://purl.org/dc/terms/';
			$context->identifier = 'dc:identifier';
			$context->Location = 'dc:Location';

			// Schema.org
			$context->schema = 'http://schema.org/';
			$context->sameAs = 'schema:sameAs';
			$context->about = 'schema:about';
			$context->fileFormat = 'schema:fileFormat';
			$context->ImageObject = 'schema:ImageObject';
			$context->contentUrl = 'schema:contentUrl';

			$context->alternateName = 'schema:alternateName';

			// Identifiers.org namespaces
			$context->identifiers = 'http://identifiers.org/';
			$context->INSDC = 'identifiers:insdc/';

			// Sequence Ontology
			//$context->SO = 'identifiers:so/SO:';
		
			// UniProt
			$context->UNIPROTCORE = 'http://purl.uniprot.org/core/';
		
			// DDBJ
			$context->DDBJ = 'http://ddbj.nig.ac.jp/ontologies/nucleotide/';
			$context->Sequence = 'DDBJ:Sequence';
			$context->sequence = 'DDBJ:sequence';

			// Barcodes
			$context->BARCODE = 'http://bins.boldsystems.org/index.php/Public_RecordView?processid=';
			$context->BIN = 'http://www.boldsystems.org/index.php/Public_BarcodeCluster?clusteruri=';

			$context->geohash = 'http://geohash.org/';
		
		
			$nq = item_to_quads($items);
		
			$jsonld = jsonld_from_rdf($nq);
			$jsonld = jsonld_compact($jsonld, $context);

			$jsonld->_id = $occurrence->{'@id'};
			
			$objects[] = $jsonld;

			//echo json_format(json_encode($jsonld)) . "\n";
		
		
		
		}
	
		$row_count++;
	}
	
	return $objects;
}

//----------------------------------------------------------------------------------------
function barcode_fetch($barcode)
{
	$objects = array();
	
	// API call
	
	$parameters = array(
		'ids' 	=> $barcode,
		'format'	=> 'tsv'
	);
	
	$url = 'http://www.boldsystems.org/index.php/API_Public/combined?' . http_build_query($parameters);
	
	//echo $url;
	
	$data = get($url);
	
	//echo $data;
	
	if ($data != '')
	{
		$objects = barcode_parse($data);
	}

	//print_r($objects);
	
	return ($objects);
}
	
if (0)
{
	barcode_fetch('USNMD174-11');

}

?>
