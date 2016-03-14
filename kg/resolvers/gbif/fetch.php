<?php
/*

A GBIF occurrence. Takes JSON returned by GBIF API and converts to JSON-LD

Create separate objects for location, event, and identification, and extract
multimedia (e.g., images), links to provider record, GenBank saequences, etc.

Use Darwin Core, Dublin Core, and schema.org vocabularies

If objects have external URLs use those (e.g., URLs, geohash for geotagged records),
other use "internal" URIs by appending object type to URL.

Try and generate possible search terms, such as museum and herbaria codes.
*/

require_once(dirname(dirname(dirname(__FILE__))) . '/couchsimple.php');

require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/lib.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');

// https://github.com/asonge/php-geohash
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/geohash.php');

//----------------------------------------------------------------------------------------
function gbif_parse($json)
{
	$objects = array();

	// Array of objects 
	$items = array();


	$obj = json_decode($json);
	
	if (!$obj)
	{
		return $objects;
	}

	$occurrence = new stdclass;

	$occurrence->{'@id'} = 'http://www.gbif.org/occurrence/' . $obj->key;
	$occurrence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Occurrence';

	$occurrence->{'http://purl.org/dc/terms/identifier'}[] = $occurrence->{'@id'};

	$metadata = new stdclass;
	$metadata->{'@id'} = $occurrence->{'@id'} . '/about';
	$metadata->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Dataset';
	$metadata->{'http://schema.org/publisher'}[] = 'GBIF';
	$metadata->{'http://schema.org/about'}[] = $occurrence->{'@id'};


	$locality = null;
	$event = null;
	$identification = null;

	foreach ($obj as $k => $v)
	{
		// catch obvious stupid entries
		$go = true;
	
		if ($v == '[Not Stated]')
		{
			$go = false;
		}

		if ($v == '')
		{
			$go = false;
		}

		if ($go)
		{

			switch ($k)
			{
				
			
				// Record level (meta?) 
				case 'datasetKey':
					break;

				case 'modified':
					$metadata->{'http://schema.org/dateModified'}[] = $v;
					break;
				
				// DarwinCore
				case 'dynamicProperties':
				
						
				// Darwin Core occurrence---------------------------------------------------------
				case 'catalogNumber':
				case 'collectionCode':
				case 'collectionID':
				case 'institutionCode':
				case 'institutionID':
				case 'recordedBy':
				case 'individualCount':
				case 'organismQuantity':
				case 'sex':
				case 'lifeStage':
				case 'reproductiveCondition':
				case 'behavior':
				case 'establishmentMeans':
				case 'occurrenceStatus':
				case 'preparations':
				case 'disposition':
				case 'otherCatalogNumbers':
				case 'occurrenceRemarks':	
					$occurrence->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
					break;
					
				case 'recordNumber':
					$occurrence->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
					$occurrence->{'http://schema.org/alternateName'}[] = $v;
					break;
				
				case 'type':
					switch ($v)
					{
						case 'PhysicalObject':
							$occurrence->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/dcmitype/PhysicalObject';
							break;
						default:
							break;
					}
					break;
			
				case 'occurrenceID':
					if (!in_array($v, $occurrence->{'http://purl.org/dc/terms/identifier'}))
					{ 
						$id = $v;
						
						// special cases where we have a useable occurrenceID
						// NHM
						if ($obj->datasetKey == '7e380070-f762-11e1-a439-00145eb45e9a')
						{
							$id = 'http://data.nhm.ac.uk/object/' . $id;
							$occurrence->{'http://schema.org/sameAs'}[] = $id;
						}
					
						// URLs link to provider record
						if (preg_match('/^http:/', $id))
						{
							$occurrence->{'http://schema.org/sameAs'}[] = $id;
						}				
					
						$occurrence->{'http://purl.org/dc/terms/identifier'}[] = $id;
					}
					break;
			
				// Dublin Core
				case 'identifier':
					if (!isset($occurrence->{'http://purl.org/dc/terms/identifier'}))
					{
						$occurrence->{'http://purl.org/dc/terms/identifier'} = array();
					}
					if (!in_array($v, $occurrence->{'http://purl.org/dc/terms/identifier'}))
					{ 
						$occurrence->{'http://purl.org/dc/terms/identifier'}[] = $v;
					}
					break;				
			
				// location
				case 'decimalLatitude':
				case 'decimalLongitude':
					if (!$locality)
					{
						$locality = new stdclass;
						$locality->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/terms/Location';

					}
					switch ($k)
					{
						// lat long
						case 'decimalLatitude':
							$locality->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
							break;
						case 'decimalLongitude':
							$locality->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
							break;
					}
					break;
			
				// Darwin Core locality-----------------------------------------------------------
				case 'continent':
				case 'coordinateUncertaintyInM':
				case 'coordinatePrecision':
				case 'country':
				case 'county':
				case 'countryCode':
				case 'decimalLatitude':
				case 'decimalLongitude':
				case 'elevation':
				case 'footprintWKT':
				case 'footprintSRS':
				case 'footprintSpatialFit':
				case 'geodeticDatum':
				case 'georeferencedBy':
				case 'georeferencedDate':
				case 'georeferenceSources':
				case 'georeferenceProtocol':
				case 'georeferenceVerification':
				case 'georeferenceRemarks':
				case 'higherGeography':
				case 'higherGeographyID':
				case 'island':
				case 'islandGroup':
				case 'locality':
				case 'locationAccordingTo':
				case 'locationID':
				case 'locationRemarks':
				case 'maximumElevationInMeters':
				case 'minimumElevationInMeters':
				case 'maximumDepthInMeters':
				case 'minimumDepthInMeters':
				case 'minimumDistanceAboveSurf':
				case 'maximumDistanceAboveSurf':
				case 'municipality':
				case 'pointRadiusSpatialFit':
				case 'stateProvince':
				case 'verbatimCoordinates':
				case 'verbatimLatitude':
				case 'verbatimLongitude':
				case 'verbatimCoordinateSystem':
				case 'verbatimSRS':
				case 'verbatimDepth':
				case 'verbatimElevation':
				case 'verbatimLocality':
				case 'waterBody':
					if (!$locality)
					{
						$locality = new stdclass;
						$locality->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/dc/terms/Location';
					}
					$locality->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
					break;			
			
			
				// Darwin Core Event----------------------------------------------------------
				case 'fieldNumber':
				case 'eventDate':
				case 'year':
				case 'month':
				case 'day':
				case 'verbatimEventDate':
				case 'fieldNotes':
				case 'eventRemarks':
					if (!$event)
					{
						$event = new stdclass;
						$event->{'@id'} = $occurrence->{'@id'} . '/Event';
						$occurrence->{'http://rs.tdwg.org/dwc/terms/eventID'}[] = $event->{'@id'};
						$event->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Event';
					
					}
					$event->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
				
					// Uncomment below to keep DWC flat
					//$occurrence->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;
					break;
			
				
			
				// Darwin Core Identification-------------------------------------------------
				case 'taxonKey':
				case 'scientificName':
				case 'higherClassification':
				case 'kingdom':
				case 'phylum':
				case 'class':
				case 'order':
				case 'family':
				case 'genus':
				case 'subgenus':
				case 'specificEpithet':
				case 'infraspecificEpithet':
				case 'taxonRank':
				case 'typeStatus':
				case 'verbatimTaxonRank':
				case 'scientificNameAuthorship':
				case 'vernacularName':
				case 'nomenclaturalCode':
				case 'taxonRemarks':				
					if (!$identification)
					{
						$identification = new stdclass;
						$identification->{'@id'} = $occurrence->{'@id'}. '/Identification';
						$occurrence->{'http://rs.tdwg.org/dwc/terms/identificationID'}[] = $identification->{'@id'};
						$identification->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://rs.tdwg.org/dwc/terms/Identification';
					}
				
					if ($k == 'taxonKey')
					{
						$v = 'http://www.gbif.org/species/' . $v;
					}
					$identification->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $v;				
					break;
			
			

				// related data
		
				case 'associatedSequences':
					$genbank = $v;
					$genbank = preg_replace('/Genbank:/i', '', $genbank);
					$genbank = preg_replace('/\s+/', '', $genbank);
					$genbank = preg_replace('/http:\/\/www.ncbi.nlm.nih.gov\/nuccore/', '', $genbank);
				
				
					$genbank = preg_replace('/\s*;\s*/', '|', $genbank);
					
					//echo $genbank;
				
					if ($genbank != '')
					{
						$genbank_list = explode('|', $genbank);
				
						foreach ($genbank_list as $accession)
						{
							$item = new stdclass;
							$item->{'@id'} = 'http://identifiers.org/insdc/' . $accession;
				
							$items[] = $item;
				
							$occurrence->{'http://rs.tdwg.org/dwc/terms/' . $k}[] = $item->{'@id'};
				
						}
					}
					break;
		
			
				default:
					break;
			}
		}

	}

	//----------------------------------------------------------------------------------------
	// Post-process 
	if (isset($occurrence->{'http://rs.tdwg.org/dwc/terms/institutionCode'})
	 && isset($occurrence->{'http://rs.tdwg.org/dwc/terms/catalogNumber'})
	 )
	{
		$institutionCode = $occurrence->{'http://rs.tdwg.org/dwc/terms/institutionCode'}[0];
		$catalogNumber = $occurrence->{'http://rs.tdwg.org/dwc/terms/catalogNumber'}[0];
		
		$collectionCode = '';
		if (isset($occurrence->{'http://rs.tdwg.org/dwc/terms/collectionCode'}[0]))
		{
			$collectionCode = $occurrence->{'http://rs.tdwg.org/dwc/terms/collectionCode'}[0];
		}		
	
		switch ($institutionCode)
		{
			case 'E':
			case 'K':
				$occurrence->{'http://schema.org/alternateName'}[] = $catalogNumber;
				break;
				
			case 'BMNH':
				$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ' ' . $catalogNumber;
				$occurrence->{'http://schema.org/alternateName'}[] = 'NHMUK' . ' ' . $catalogNumber;
				break;
			
			case 'NHMUK':
				$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ' ' . $catalogNumber;
				$occurrence->{'http://schema.org/alternateName'}[] = 'BMNH' . ' ' . $catalogNumber;
				break;
				
			case 'MNHN':
				switch ($collectionCode)
				{
					case 'IM':
						// MNHN:IM:200717700
						$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ':' . $collectionCode . ':' . str_replace('-', '', $catalogNumber);
						break;
						
					default:
						break;
				}
				break;
			
			case 'USNM':
				$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ' ' . $catalogNumber;
	
				// other possbilities
				if (preg_match('/^(?<one>\d+)\.(?<two>\d+)$/', $catalogNumber, $m))
				{
					$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ' ' . $m['one'];
				}
				break;
			
			default:
				$occurrence->{'http://schema.org/alternateName'}[] = $institutionCode . ' ' . $catalogNumber;
				break;
		}

	}
 
 


	//----------------------------------------------------------------------------------------
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
			/*
			$str = '';
			foreach ($location as $k => $v)
			{
				if (is_string($v))
				{
					$str .= $v;
				}
			}
			$locality->{'@id'}  = '_:' . md5($str);
			*/
			$locality->{'@id'} = $occurrence->{'@id'} . '/Location';
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

	//-----------------------------------------------------------------------------------------
	// Post-process identification
	if ($identification)
	{
		$items[] = $identification;
	}

	//-----------------------------------------------------------------------------------------
	// Post process EMBL (and other datasets) that have GenBank accession numbers
	// to extract sequence accession numbers
	if ($obj->datasetKey == 'c1fc2df7-223b-4472-8998-70afb3b749ab')
	{
		$item = new stdclass;
		$item->{'@id'} = 'http://identifiers.org/insdc/' . $obj->catalogNumber;
	
		$items[] = $item;
	
		$occurrence->{'http://rs.tdwg.org/dwc/terms/associatedSequences'}[] = $item->{'@id'};
	}
	
	//-----------------------------------------------------------------------------------------
	// Dataset (metadata about record)
	$items[] = $metadata;	

	//-----------------------------------------------------------------------------------------
	if (isset($obj->media))
	{
		foreach ($obj->media as $media)
		{
			$m = new stdclass;
		
			if (isset($media->identifier))
			{
				$m->{'@id'} = $media->identifier;
			
				$m->{'http://schema.org/about'}[] = $occurrence->{'@id'};
		
				if (isset($media->type))
				{
					switch ($media->type)
					{
						case 'StillImage':
							$m->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ImageObject';
							break;
						
						default:
							break;
					}
				}
		
				if (isset($media->format))
				{
					$m->{'http://schema.org/fileFormat'}[] = $media->format;
				}

				if (isset($media->identifier))
				{
					$m->{'http://purl.org/dc/terms/identifier'}[] = $media->identifier;
				
					$m->{'http://schema.org/contentUrl'}[] = $media->identifier;
				}
			
				$items[] = $m;
			}
		}
	}


	$items[] = $occurrence;

	//print_r($items);

	// Context for JSON-LD
	$context = new stdclass;

	// Darwin Core
	$context->{'@vocab'} = 'http://rs.tdwg.org/dwc/terms/';

	// Dublin Core
	$context->dc = 'http://purl.org/dc/terms/';
	$context->identifier = 'dc:identifier';
	$context->Location = 'dc:Location';

	$context->dcmitype = 'http://purl.org/dc/dcmitype/';
	$context->PhysicalObject = 'dcmitype:PhysicalObject';

	// Schema.org
	$context->schema = 'http://schema.org/';
	$context->about = 'schema:about';
	$context->fileFormat = 'schema:fileFormat';
	$context->contentUrl = 'schema:contentUrl';
	$context->alternateName = 'schema:alternateName';
	$context->dateCreated = 'schema:dateCreated';
	$context->dateModified = 'schema:dateModified';
	$context->publisher = 'schema:publisher';
	
	// Schema types
	$context->ImageObject = 'schema:ImageObject';
	$context->Dataset = 'schema:Dataset';


	// Identifiers.org namespaces
	$context->identifiers = 'http://identifiers.org/';
	$context->INSDC = 'identifiers:insdc/';

	$context->geohash = 'http://geohash.org/';
	
	// GBIF
	$context->GBIF = 'http://www.gbif.org/species/';
	$context->OCCURRENCE = 'http://www.gbif.org/occurrence/';
	
	// data provider ids
	$context->NHM = 'http://data.nhm.ac.uk/object/';
	$context->ARK = 'http://n2t.net/ark:/';
	$context->KEW = 'http://specimens.kew.org/herbarium/';
	$context->RBGE = 'http://data.rbge.org.uk/herb/';


	// Convert object to list of RDF nquads
	$nq = item_to_quads($items);

	$jsonld = jsonld_from_rdf($nq);
	$jsonld = jsonld_compact($jsonld, $context);

	$jsonld->_id = 'http://www.gbif.org/occurrence/' . $obj->key;
	
	$objects[] = $jsonld;
	
	return $objects;
}

//----------------------------------------------------------------------------------------
function gbif_fetch($id)
{
	$objects = array();
	
	// API call
	$url = 'http://api.gbif.org/v1/occurrence/' . $id;
	
	//echo $url;
	
	$json = get($url);
	
	//echo $json;
		
	if ($json != '')
	{
		$objects = gbif_parse($json);
	}

	//print_r($objects);
	
	return ($objects);
}
	
//----------------------------------------------------------------------------------------

// GBIF
// swiftlet Smithsonian, one DNA sequence
$json = '{"key":886683894,"datasetKey":"5df38344-b821-49c2-8174-cf0f29f4df0d","publishingOrgKey":"bc092ff0-02e4-11dc-991f-b8a03c50a862","publishingCountry":"US","protocol":"DWC_ARCHIVE","lastCrawled":"2016-02-09T17:23:42.067+0000","lastParsed":"2016-02-09T17:23:42.074+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","sex":"MALE","taxonKey":2477185,"kingdomKey":1,"phylumKey":44,"classKey":212,"orderKey":1448,"familyKey":2993,"genusKey":2477184,"speciesKey":2477185,"scientificName":"Aerodramus vanikorensis (Quoy & Gaimard, 1830)","kingdom":"Animalia","phylum":"Chordata","order":"Apodiformes","family":"Apodidae","genus":"Aerodramus","species":"Aerodramus vanikorensis","genericName":"Aerodramus","specificEpithet":"vanikorensis","taxonRank":"SPECIES","decimalLongitude":152.95,"decimalLatitude":-4.55,"elevation":150.0,"continent":"OCEANIA","stateProvince":"New Ireland","year":1994,"month":2,"day":9,"eventDate":"1994-02-08T23:00:00.000+0000","issues":["GEODETIC_DATUM_ASSUMED_WGS84"],"lastInterpreted":"2016-02-09T17:31:20.818+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Aves","countryCode":"PG","country":"Papua New Guinea","identifier":"http://n2t.net/ark:/65665/3b9637139-8cdc-489f-81ef-da29a97a8ad4","verbatimEventDate":"9 Feb 1994","higherGeography":"Australia, Papua New Guinea, New Ireland, New Ireland Province","http://unknown.org/organismID":"608672","endDayOfYear":"40","locality":"Weitin River Valley","county":"New Ireland Province","verbatimCoordinateSystem":"Degrees Minutes Seconds","gbifID":"886683894","collectionCode":"Birds","occurrenceID":"http://n2t.net/ark:/65665/3b9637139-8cdc-489f-81ef-da29a97a8ad4","type":"PhysicalObject","preparations":"Skin: Whole","catalogNumber":"608672.4291238","recordedBy":"J. Angle & B. Beehler","institutionCode":"USNM","rights":"https://creativecommons.org/publicdomain/zero/1.0/","startDayOfYear":"40","associatedSequences":"Genbank: JQ173912","higherClassification":"Animalia, Chordata, Vertebrata, Aves, Apodiformes, Apodidae"}';

// sequence EMBL
$json = '{"key":1004022935,"datasetKey":"ad43e954-dd79-4986-ae34-9ccdbd8bf568","publishingOrgKey":"ada9d123-ddb4-467d-8891-806ea8d94230","publishingCountry":"GB","protocol":"DWC_ARCHIVE","lastCrawled":"2014-09-04T14:47:57.369+0000","lastParsed":"2014-09-04T14:47:57.530+0000","extensions":{},"basisOfRecord":"UNKNOWN","taxonKey":2477185,"kingdomKey":1,"phylumKey":44,"classKey":212,"orderKey":1448,"familyKey":2993,"genusKey":2477184,"speciesKey":2477185,"scientificName":"Aerodramus vanikorensis (Quoy & Gaimard, 1830)","kingdom":"Animalia","phylum":"Chordata","order":"Apodiformes","family":"Apodidae","genus":"Aerodramus","species":"Aerodramus vanikorensis","genericName":"Aerodramus","specificEpithet":"vanikorensis","taxonRank":"SPECIES","decimalLongitude":152.95,"decimalLatitude":-4.55,"year":1994,"month":2,"day":10,"eventDate":"1994-02-09T23:00:00.000+0000","issues":["GEODETIC_DATUM_ASSUMED_WGS84","COUNTRY_INVALID","COUNTRY_DERIVED_FROM_COORDINATES","REFERENCES_URI_INVALID"],"modified":"2012-01-28T23:00:00.000+0000","lastInterpreted":"2014-09-04T14:47:58.623+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Aves","countryCode":"PG","country":"Papua New Guinea","identifier":"JQ173912","created":"2011-12-04","gbifID":"1004022935","associatedSequences":"JQ173912","occurrenceID":"JQ173912","higherClassification":"species","taxonID":"243317"}';
/*
$json = '{"key":1004022935,"datasetKey":"ad43e954-dd79-4986-ae34-9ccdbd8bf568","publishingOrgKey":"ada9d123-ddb4-467d-8891-806ea8d94230","publishingCountry":"GB","protocol":"DWC_ARCHIVE","lastCrawled":"2014-09-04T14:47:57.369+0000","lastParsed":"2014-09-04T14:47:57.530+0000","extensions":{},"basisOfRecord":"UNKNOWN","taxonKey":2477185,"kingdomKey":1,"phylumKey":44,"classKey":212,"orderKey":1448,"familyKey":2993,"genusKey":2477184,"speciesKey":2477185,"scientificName":"Aerodramus vanikorensis (Quoy & Gaimard, 1830)","kingdom":"Animalia","phylum":"Chordata","order":"Apodiformes","family":"Apodidae","genus":"Aerodramus","species":"Aerodramus vanikorensis","genericName":"Aerodramus","specificEpithet":"vanikorensis","taxonRank":"SPECIES","year":1994,"month":2,"day":10,"eventDate":"1994-02-09T23:00:00.000+0000","issues":["GEODETIC_DATUM_ASSUMED_WGS84","COUNTRY_INVALID","COUNTRY_DERIVED_FROM_COORDINATES","REFERENCES_URI_INVALID"],"modified":"2012-01-28T23:00:00.000+0000","lastInterpreted":"2014-09-04T14:47:58.623+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Aves","countryCode":"PG","country":"Papua New Guinea","identifier":"JQ173912","created":"2011-12-04","gbifID":"1004022935","associatedSequences":"JQ173912","occurrenceID":"JQ173912","higherClassification":"species","taxonID":"243317"}';
*/

// Image
$json= '{"key":575094236,"datasetKey":"bf2a4bf0-5f31-11de-b67e-b8a03c50a862","publishingOrgKey":"98e934b0-5f31-11de-b67e-b8a03c50a862","publishingCountry":"GB","protocol":"DWC_ARCHIVE","lastCrawled":"2016-01-26T01:16:05.098+0000","lastParsed":"2014-05-29T12:50:33.305+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","taxonKey":4261707,"kingdomKey":6,"phylumKey":49,"classKey":220,"orderKey":941,"familyKey":2433,"genusKey":7311211,"speciesKey":4261707,"scientificName":"Duboscia viridiflora Mildbr.","kingdom":"Plantae","phylum":"Magnoliophyta","order":"Malvales","family":"Tiliaceae","genus":"Duboscia","species":"Duboscia viridiflora","genericName":"Duboscia","specificEpithet":"viridiflora","taxonRank":"SPECIES","decimalLongitude":10.41667,"decimalLatitude":3.08333,"year":1911,"month":1,"day":1,"eventDate":"1910-12-31T23:00:00.000+0000","issues":["COORDINATE_ROUNDED","GEODETIC_DATUM_ASSUMED_WGS84"],"modified":"2011-07-26T22:00:00.000+0000","lastInterpreted":"2014-06-05T09:37:25.433+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Magnoliopsida","countryCode":"CM","country":"Cameroon","identifier":"http://data.rbge.org.uk/herb/E00421509","recordNumber":"4165","nomenclaturalCode":"ICBN","verbatimEventDate":"1911","higherGeography":"Tropical Africa","locality":"Bipinde","verbatimCoordinateSystem":"degrees minutes seconds","datasetName":"Royal Botanic Garden Edinburgh Herbarium","gbifID":"575094236","collectionCode":"E","occurrenceID":"http://data.rbge.org.uk/herb/E00421509","type":"PhysicalObject","preparations":"herbarium specimen of unspecified type","catalogNumber":"E00421509","recordedBy":"Zenker, Georg August","otherCatalogNumbers":"BGBASE:469051","institutionCode":"E","fieldNotes":"Urwaldgebiet","ownerInstitutionCode":"E","startDayOfYear":"182","collectionID":"http://biocol.org/urn:lsid:biocol.org:col:15670"}';

// Key with image
$json = '{"key":1051251700,"datasetKey":"cd6e21c8-9e8a-493a-8a76-fbf7862069e5","publishingOrgKey":"061b4f20-f241-11da-a328-b8a03c50a862","publishingCountry":"GB","protocol":"DWC_ARCHIVE","lastCrawled":"2015-11-26T10:02:39.677+0000","lastParsed":"2015-11-26T10:02:39.696+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","taxonKey":4177482,"kingdomKey":6,"phylumKey":49,"classKey":220,"orderKey":407,"familyKey":6688,"genusKey":2988444,"speciesKey":4177482,"scientificName":"Laurus obtusifolia Willd. ex Meisn.","kingdom":"Plantae","phylum":"Magnoliophyta","order":"Laurales","family":"Lauraceae","genus":"Laurus","species":"Laurus obtusifolia","genericName":"Laurus","specificEpithet":"obtusifolia","taxonRank":"SPECIES","issues":[],"lastInterpreted":"2015-11-26T10:13:36.128+0000","identifiers":[],"media":[{"type":"StillImage","format":"image/jpeg","identifier":"http://www.kew.org/herbcatimg/686058.jpg","license":"© The Board of Trustees of the Royal Botanic Gardens, Kew."}],"facts":[],"relations":[],"class":"Magnoliopsida","countryCode":null,"identifier":"K001116483","recordNumber":"Cat. no. 2574","catalogNumber":"K001116483","recordedBy":"De Silva, F.","institutionCode":"K","locality":"Sylhet","collectionCode":"Herbarium","gbifID":"1051251700","occurrenceRemarks":"Sillet F. DeS.; Laurus obtusifolia Roxb.; EICH number: 2574 A","occurrenceID":"http://specimens.kew.org/herbarium/K001116483","higherClassification":"LAURACEAE"}';

// MVZ155014
// voucher for JF693846 (would need to make link manually)
$json = '{"key":1145726536,"datasetKey":"0daed095-478a-4af6-abf5-18acb790fbb2","publishingOrgKey":"8edbbde0-055e-11d8-b850-b8a03c50a862","publishingCountry":"US","protocol":"DWC_ARCHIVE","lastCrawled":"2016-01-20T05:30:54.539+0000","lastParsed":"2016-01-20T05:30:54.639+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","sex":"MALE","taxonKey":2439290,"kingdomKey":1,"phylumKey":44,"classKey":359,"orderKey":1459,"familyKey":5510,"genusKey":2439287,"speciesKey":2439290,"scientificName":"Neacomys spinosus (Thomas, 1882)","kingdom":"Animalia","phylum":"Chordata","order":"Rodentia","family":"Muridae","genus":"Neacomys","species":"Neacomys spinosus","genericName":"Neacomys","specificEpithet":"spinosus","taxonRank":"SPECIES","dateIdentified":"1999-01-26T23:00:00.000+0000","decimalLongitude":-78.16123,"decimalLatitude":-4.45563,"elevation":213.36,"elevationAccuracy":0.0,"continent":"SOUTH_AMERICA","stateProvince":"Amazonas","year":1978,"month":7,"day":7,"eventDate":"1978-07-06T23:00:00.000+0000","issues":["GEODETIC_DATUM_INVALID","GEODETIC_DATUM_ASSUMED_WGS84"],"lastInterpreted":"2016-01-20T05:37:25.999+0000","references":"http://arctos.database.museum/guid/MVZ:Mamm:155014","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Mammalia","countryCode":"PE","country":"Peru","institutionID":"urn:lsid:biocol.org:col:34777","dynamicProperties":"sex=male","http://unknown.org/organismID":"http://arctos.database.museum/guid/MVZ:Mamm:155014","identificationVerificationStatus":"legacy","gbifID":"1145726536","language":"en","type":"PhysicalObject","preparations":"skull; tissue (frozen); study skin","locationAccordingTo":"Kristina Yamamoto","catalogNumber":"155014","locationRemarks":"Checked by J.L. Patton.","institutionCode":"MVZ","rights":"http://creativecommons.org/publicdomain/zero/1.0/","identifiedBy":"Museum of Vertebrate Zoology, University of California, Berkeley","identifier":"http://arctos.database.museum/guid/MVZ:Mamm:155014?seid=562383","georeferencedDate":"2003-01-17 00:00:00.0","recordNumber":"7342","nomenclaturalCode":"ICZN","verbatimEventDate":"7 Jul 1978","higherGeography":"South America, Peru, Amazonas","georeferencedBy":"Kristina Yamamoto","georeferenceProtocol":"GeoLocate","endDayOfYear":"188","georeferenceVerificationStatus":"checked by curator","locality":"vicinity of Huampami (Aguaruna village), Rio Cenepa","verbatimCoordinateSystem":"deg. min. sec.","collectionCode":"Mammal specimens","verbatimLocality":"vicinity of Huampami [Aguaruna Village] , Rio Cenepa","occurrenceID":"http://arctos.database.museum/guid/MVZ:Mamm:155014?seid=562383","recordedBy":"Collector(s): James L. Patton","otherCatalogNumbers":"collector number=7342","previousIdentifications":"<i>Neacomys spinosus</i> (accepted ID) identified by Museum of Vertebrate Zoology, University of California, Berkeley on 1999-01-27; method: legacy<br><i>Neacomys spinosus</i> identified by Ronald H. Pine, Robert M. Timm, Marcelo Weksler on 2012 <i>sensu</i> <a href=\"http://arctos.database.museum/publication/10006517\">Pine et al. 2012</a>; method: ID of kin","identificationQualifier":"A","accessRights":"http://www.vertnet.org/resources/norms.html","higherClassification":"Animalia; Chordata; Mammalia; Rodentia; Cricetidae; Sigmodontinae;","collectionID":"urn:lsid:biocol.org:col:34904","georeferenceSources":"GeoLocate"}';

// voucher for JF693835 (NCBI has different genus)
//$json = '{"key":1211821464,"datasetKey":"c5c4a23e-2035-4416-ab64-032d6df52ddb","publishingOrgKey":"ff418020-1d67-11d9-8435-b8a03c50a862","publishingCountry":"CA","protocol":"DWC_ARCHIVE","lastCrawled":"2015-11-26T09:47:11.055+0000","lastParsed":"2015-11-26T09:47:11.144+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","sex":"FEMALE","lifeStage":"ADULT","taxonKey":2438073,"kingdomKey":1,"phylumKey":44,"classKey":359,"orderKey":1459,"familyKey":5510,"genusKey":2438064,"speciesKey":2438073,"scientificName":"Oryzomys alfaroi (J. A. Allen, 1891)","kingdom":"Animalia","phylum":"Chordata","order":"Rodentia","family":"Muridae","genus":"Oryzomys","species":"Oryzomys alfaroi","genericName":"Oryzomys","specificEpithet":"alfaroi","taxonRank":"SPECIES","decimalLongitude":-89.36667,"decimalLatitude":14.41667,"continent":"NORTH_AMERICA","stateProvince":"Santa Ana","year":1993,"month":3,"day":11,"eventDate":"1993-03-10T23:00:00.000+0000","issues":["COORDINATE_ROUNDED","GEODETIC_DATUM_ASSUMED_WGS84"],"modified":"2015-05-19T22:00:00.000+0000","lastInterpreted":"2015-11-26T09:47:11.203+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Mammalia","countryCode":"SV","country":"El Salvador","rightsHolder":"Royal Ontario Museum; ROM","gbifID":"1211821464","language":"en","type":"PhysicalObject","preparations":"skin; skull; skeleton","catalogNumber":"101537","locationRemarks":"ROM data parsed by Susan M. Woodward, 20060919.","institutionCode":"ROM","rights":"https://creativecommons.org/licenses/by-nc/4.0/","reproductiveCondition":"no emb","identifier":"URI:catalog:ROM:Mammals:101537","georeferencedDate":"Not recorded.","recordNumber":"F35715","verbatimEventDate":"19930311","higherGeography":"North America; El Salvador; Santa Ana","georeferencedBy":"Unknown (ROM)","georeferenceProtocol":"MaNIS/HerpNet/ORNIS Georeferencing Guidelines, GBIF Best Practices","locality":"Parque Nacional Montecristo, Bosque Nebuloso","datasetName":"Mammalogy","collectionCode":"Mammals","verbatimLocality":"Parque Nacional Montecristo, Bosque Nebuloso","occurrenceID":"URI:catalog:ROM:Mammals:101537","recordedBy":"Engstrom, MD; Reid, FA; Lim, BK","accessRights":"not-for-profit use only","higherClassification":"Animalia; Chordata; Mammalia; Rodentia; Muridae; Cricetidae; Oryzomys","collectionID":"urn:lsid:biocol.org:col:34909","georeferenceSources":"Most likely from the gazetteer in, The Times Atlas of the World, Comprehensive Edition. 1975. John Bartholomew and Son Limited. Edinburgh, Great Britain. xl + 123 plates + 223 pp."}';

// dynamic data
//$json = '{"key":1211933420,"datasetKey":"136560fa-9eb9-492f-866e-5bd110c52f74","publishingOrgKey":"ff418020-1d67-11d9-8435-b8a03c50a862","publishingCountry":"CA","protocol":"DWC_ARCHIVE","lastCrawled":"2016-01-15T18:19:24.971+0000","lastParsed":"2016-01-15T18:19:25.100+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","sex":"FEMALE","lifeStage":"ADULT","taxonKey":7191279,"kingdomKey":1,"phylumKey":44,"classKey":212,"orderKey":1450,"familyKey":9348,"genusKey":2497486,"speciesKey":2497522,"scientificName":"Strix nebulosa subsp. nebulosa","kingdom":"Animalia","phylum":"Chordata","order":"Strigiformes","family":"Strigidae","genus":"Strix","species":"Strix nebulosa","genericName":"Strix","specificEpithet":"nebulosa","infraspecificEpithet":"nebulosa","taxonRank":"SUBSPECIES","decimalLongitude":-75.7,"decimalLatitude":45.38018,"continent":"NORTH_AMERICA","stateProvince":"Ontario","year":2005,"month":1,"day":1,"eventDate":"2004-12-31T23:00:00.000+0000","issues":["COORDINATE_ROUNDED"],"modified":"2016-01-13T23:00:00.000+0000","lastInterpreted":"2016-01-15T18:20:32.656+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Aves","countryCode":"CA","country":"Canada","rightsHolder":"Royal Ontario Museum","dynamicProperties":"Meas:Bill Nasal (BN): 20.39; Tarsometatarsus (Tr): 53.31; Wing Chord (WC): 440; Tail (T): 296;; Toe Pad: 52.63 mm;Life Stage Remarks:no bursa;Fat:heavy;Diet:stomach: 3 Voles;Sex Remarks:none recorded","county":"Ottawa","gbifID":"1211933420","language":"en","type":"PhysicalObject","preparations":"skeleton; wing","catalogNumber":"101537","institutionCode":"ROM","rights":"https://creativecommons.org/licenses/by-nc/4.0/","reproductiveCondition":"ov: 14x6 mm, small granules","identifier":"URI:catalog:ROM:Birds:101537","georeferencedDate":"2008-02-14","recordNumber":"1B-4419; 38","verbatimEventDate":"20050000","higherGeography":"North America; Canada; Ontario; Ottawa","georeferencedBy":"Bradley G. Millen (ROM)","georeferenceProtocol":"MaNIS/HerpNet/ORNIS Georeferencing Guidelines, GBIF Best Practices","georeferenceVerificationStatus":"verification required","locality":"Ottawa area, Eastern Ontario","datasetName":"Ornithology","collectionCode":"Birds","verbatimLocality":"Ottawa area, Eastern Ontario","occurrenceID":"URI:catalog:ROM:Birds:101537","disposition":"in collection","accessRights":"not-for-profit use only","higherClassification":"Animalia; Chordata; Aves; Strigiformes; Strigidae Striginae; Strix","collectionID":"urn:lsid:biocol.org:col:34954","georeferenceSources":"BioGeomancer"}';



// Plant example, same as GenBank KF496459, cited by http://dx.doi.org/10.1111/ddi.12266
// I think, has same colelctor number, but date is different
$json = '{"key":994086117,"datasetKey":"4ce8e3f9-2546-4af1-b28d-e2eadf05dfd4","publishingOrgKey":"47e00926-5a2f-44e8-8509-139a689c8b4d","publishingCountry":"AU","protocol":"DWC_ARCHIVE","lastCrawled":"2016-01-18T23:42:37.555+0000","lastParsed":"2015-03-10T09:55:52.895+0000","extensions":{},"basisOfRecord":"PRESERVED_SPECIMEN","taxonKey":3155025,"kingdomKey":6,"phylumKey":49,"classKey":220,"orderKey":718,"familyKey":9291,"genusKey":3155023,"speciesKey":3155025,"scientificName":"Mitrephora diversifolia (Span.) Miq.","kingdom":"Plantae","phylum":"Magnoliophyta","order":"Magnoliales","family":"Annonaceae","genus":"Mitrephora","species":"Mitrephora diversifolia","genericName":"Mitrephora","specificEpithet":"diversifolia","taxonRank":"SPECIES","decimalLongitude":142.5167,"decimalLatitude":-10.7667,"elevation":80.0,"stateProvince":"Queensland","year":2001,"month":2,"day":9,"eventDate":"2001-02-08T23:00:00.000+0000","issues":["GEODETIC_DATUM_ASSUMED_WGS84"],"lastInterpreted":"2015-03-10T10:01:31.569+0000","identifiers":[],"facts":[],"relations":[],"geodeticDatum":"WGS84","class":"Magnoliopsida","countryCode":"AU","country":"Australia","identifier":"154819d7-06f4-42a8-9556-5cd05c9e55f4","recordNumber":"1466","catalogNumber":"QRS 121240.2","recordedBy":"Cooper, W.; Cooper, W.","institutionCode":"CNS","locality":"Lockerbie Scrub","collectionCode":"QRS","gbifID":"994086117","occurrenceRemarks":"Tree 6 m tall. Fruit ripe, green turning yellow at base. Seeds brown. Fruit in alcohol.","occurrenceID":"154819d7-06f4-42a8-9556-5cd05c9e55f4"}';

if (0)
{
	gbif_fetch(575094236);
}



?>
