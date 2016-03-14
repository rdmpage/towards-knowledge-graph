<?php

// Fetch reference from PubMed
// and convert to JSON-LD 
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/lib.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/nameparse.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/utilities/rdf.php');
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/php-json-ld/jsonld.php');

// https://github.com/asonge/php-geohash
require_once (dirname(dirname(dirname(__FILE__))) . '/vendor/geohash.php');

//----------------------------------------------------------------------------------------
function pubmed_parse($data)
{
	$objects = array();
	
	
	$obj = json_decode($data);
	
	$uids = $obj->result->uids;
	
	foreach ($uids as $uid)
	{
		$p = $obj->result->{$uid};
		
		$items = array();
		
		$reference = new stdclass;
		$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';		
			
		
		if (isset($p->pubtype))
		{
			foreach ($p->pubtype as $pubtype)
			{
				switch ($pubtype)
				{
					case 'Journal Article':
						$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/AcademicArticle';
						$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ScholarlyArticle';
						break;
						
					default:
						break;
				}
			}
		}

		// PubMed is primary identifier
		$reference->{'@id'} = 'http://identifiers.org/pubmed/' . $uid;
		$reference->{'http://purl.org/ontology/bibo/pmid'}[] = 	$uid;
		
		// metadata about this record
		$metadata = new stdclass;
		$metadata->{'@id'} = $reference->{'@id'}  . '/about';
		$metadata->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Dataset';
		
		if (isset($p->history))
		{
			foreach ($p->history as $history)
			{
				if ($history->pubstatus == "pubmed")
				{
					$metadata->{'http://schema.org/dateCreated'}[] = date("Y-m-d", strtotime($history->date));
				}	
			}
        }
		
		
		// Identifiers
		foreach ($p->articleids as $articleid)
		{
			switch ($articleid->idtype)
			{
				case 'doi':
					$reference->{'http://schema.org/sameAs'}[] = 'http://identifiers.org/doi/' . $articleid->value;
					$reference->{'http://purl.org/ontology/bibo/doi'}[] = $articleid->value;
					break;

				case 'pmc':
					$reference->{'http://schema.org/sameAs'}[] = 'http://identifiers.org/pmc/' . $articleid->value;
					break;
					
				default:
					break;
			}
		}
		
		// Article metadata
		foreach ($p as $k => $v)
		{
			if ($v != '')
			{
				switch ($k)
				{
					case 'volume':
					case 'issue':
					case 'pages':
					case 'issn':
						$reference->{'http://purl.org/ontology/bibo/' . $k}[] = $v;
						break;
				
					case 'essn':
						$reference->{'http://purl.org/ontology/bibo/eissn'}[] = $v;
						break;
					
					case 'title':
						$reference->{'http://purl.org/dc/terms/title'}[] = $v;
						$reference->{'http://schema.org/name'}[] = $v;
						break;
					
					case 'pubdate':
						// to do: parswe this properly FFS
						$reference->{'http://schema.org/datePublished'}[] = $v;
						break;
				
					default:
						break;
				}
			}
		}
		
		// Container (e.g., journal)
		$journal_id = '';
		if (isset($p->essn) && ($p->essn != ''))
		{
			$journal_id = $p->essn;
		}
		// use print by default
		if (isset($p->issn) && ($p->issn != ''))
		{
			$journal_id = $p->issn;
		}
		if ($journal_id != '')
		{
			$journal_id = 'http://www.worldcat.org/issn/' . $journal_id;
			$reference->{'http://schema.org/isPartOf'}[] = $journal_id;
		
			$journal = new stdclass;
			$journal->{'@id'} = $journal_id;
			
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Periodical';
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/Journal';
			
			if (isset($p->fulljournalname))
			{
				$journal->{'http://schema.org/name'}[] = $p->fulljournalname;
			}

			$items[] = $journal;
		}
		
		// authors
		if (isset($p->authors))
		{
			$count = 1;
			foreach ($p->authors as $a)
			{
				$author = new stdclass;
				$author->{'@id'} = $reference->{'@id'} . '/contributor/' . $count;
				
				
				$author->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Person';
				
				if (isset($a->name))
				{
					$author->{'http://schema.org/name'}[] = $a->name;
				}
				
				$reference->{'http://purl.org/dc/terms/creator'}[] = $author->{'@id'};
				
				$items[] = $author;
				$count++;
			}
		}
		
		
		
		
		
		//print_r($p);
		
		
		// OK, we need to add any sequences cited, and any citation links we can get through PMC
		
		// citedby in PMC  http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dbfrom=pubmed&linkname=pubmed_pmc_refs&id=17148433  (pmid)
		
		// listed of papers it cites that ar ein PubMed
		// http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dbfrom=pubmed&linkname=pubmed_pubmed_refs&id=17148433
		
		// sequences
		
		
		
		
		$items[] = $reference;
		$items[] = $metadata;
		
		
		//print_r($items);
		
			// Context for JSON-LD
			$context = new stdclass;

			// Bibliographic Ontology
			$context->{'@vocab'} = 'http://purl.org/ontology/bibo/';

			
			// Dublin Core
			$context->dc 			= 'http://purl.org/dc/terms/';
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
			
			// Schema types
			$context->CreativeWork 		= 'schema:CreativeWork';
			$context->Periodical 		= 'schema:Periodical';
			$context->Person 			= 'schema:Person';
			$context->ScholarlyArticle 	= 'schema:ScholarlyArticle';
			$context->Dataset 			= 'schema:Dataset';

			// Identifiers.org namespaces
			$context->identifiers 	= 'http://identifiers.org/';
			$context->DOI 			= 'identifiers:doi/';
			$context->INSDC 		= 'identifiers:insdc/';
			$context->PMC 			= 'identifiers:pmc/';
			$context->PMID 			= 'identifiers:pubmed/';
			
			// Other identifiers
			$context->ISSN 			= 'http://www.worldcat.org/issn/';

		
			$nq = item_to_quads($items);
		
			$jsonld = jsonld_from_rdf($nq);
			$jsonld = jsonld_compact($jsonld, $context);

			$jsonld->_id = 'http://www.ncbi.nlm.nih.gov/pubmed/' . $uid;
		
			
			$objects[] = $jsonld;

			//echo json_format(json_encode($jsonld)) . "\n";
		
		
	}
	return $objects;
}

//----------------------------------------------------------------------------------------
// Given a PMC return PMC's of articles that cite this article
function cited_by_in_pmc($pmc, &$reference)
{
	$pmc = str_replace('PMC', '', $pmc);

	// Cited by in PMC
	$url = 	'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?db=pmc&dbfrom=pmc&id=' . $pmc . '&retmode=xml';
	$xml = get($url);
	
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$nodeCollection = $xpath->query ('//LinkSetDb/Link/Id');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/spar/cito/isCitedBy'}[] = 'http://identifiers.org/pmc/' . $node->firstChild->nodeValue;
	}
	
}

//----------------------------------------------------------------------------------------
// Given a PMC return PMIDs of articles that this article cites
function cites_in_pubmed($pmc, &$reference)
{
	$pmc = str_replace('PMC', '', $pmc);

	// PMIDs cited db=pubmed&dbfrom=pmc&id=4536039&retmode=xml
	$url = 	'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?db=pubmed&dbfrom=pmc&id=' . $pmc . '&retmode=xml';
	$xml = get($url);
	
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	// second //LinkSetDb has citations
	$nodeCollection = $xpath->query ('//LinkSetDb[2]/Link/Id');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/spar/cito/cites'}[] = 'http://identifiers.org/pubmed/' . $node->firstChild->nodeValue;
	}
	
}

http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?db=nucleotide&dbfrom=pubmed&id=17148433&retmode=xml

//----------------------------------------------------------------------------------------
// Given PMIDs return list of linked nucleotides
function pubmed_to_nucleotides($pmid, &$reference)
{

	$url = 	'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?db=nucleotide&dbfrom=pubmed&id=' . $pmid . '&retmode=xml';
	$xml = get($url);
	
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	// second //LinkSetDb has citations
	$nodeCollection = $xpath->query ('//LinkSetDb/Link/Id');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/dc/terms/references'}[] = 'http://www.ncbi.nlm.nih.gov/nuccore/' . $node->firstChild->nodeValue;
	}
	
}


//----------------------------------------------------------------------------------------
function pubmed_parse_xml($xml)
{
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$items = array();
	
	$pmid = 0;
	$pmc = '';

	$reference = new stdclass;
	
	$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';		
			
	$nodeCollection = $xpath->query ('//PublicationTypeList/PublicationType');
	foreach ($nodeCollection as $node)
	{	
		if ($node->firstChild->nodeValue == "Journal Article")
		{
			$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/AcademicArticle';
			$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ScholarlyArticle';
		}					
	}

	// PMID is identifier
	$nodeCollection = $xpath->query ('//PubmedArticle/MedlineCitation/PMID');
	foreach ($nodeCollection as $node)
	{		
		$reference->{'@id'} = 'http://identifiers.org/pubmed/' . $node->firstChild->nodeValue;
		$reference->{'http://purl.org/ontology/bibo/pmid'}[] = 	$node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[] = $reference->{'@id'};
	}

	// title
	$nodeCollection = $xpath->query ('//ArticleTitle');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/dc/terms/title'}[] = $node->firstChild->nodeValue;
		$reference->{'http://schema.org/name'}[] = $node->firstChild->nodeValue;
	}

	// abstract
	$nodeCollection = $xpath->query ('//Abstract/AbstractText');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/ontology/bibo/abstract'}[] = $node->firstChild->nodeValue;
		$reference->{'http://schema.org/description'}[] = $node->firstChild->nodeValue;
	}
	
	/*
	// journal
<Journal>
<ISSN IssnType="Print">1055-7903</ISSN>
<JournalIssue CitedMedium="Print">
<Volume>31</Volume>
<Issue>1</Issue>
<PubDate>
<Year>2004</Year>
<Month>Apr</Month>
</PubDate>
</JournalIssue>
<Title>Molecular phylogenetics and evolution</Title>
<ISOAbbreviation>Mol. Phylogenet. Evol.</ISOAbbreviation>
</Journal>*/

	$nodeCollection = $xpath->query ('//Journal');
	foreach ($nodeCollection as $journal_node)
	{	
		$nc = $xpath->query ('JournalIssue/Volume', $journal_node);
		foreach ($nc as $n)
		{	
			$reference->{'http://purl.org/ontology/bibo/volume'}[] =  $n->firstChild->nodeValue;
		}
		$nc = $xpath->query ('JournalIssue/Issue', $journal_node);
		foreach ($nc as $n)
		{	
			$reference->{'http://purl.org/ontology/bibo/issue'}[] =  $n->firstChild->nodeValue;
		}

		$journal_id = '';
		$nc = $xpath->query ('ISSN[@IssnType="Print"]', $journal_node);
		foreach ($nc as $n)
		{	
			$journal_id =  $n->firstChild->nodeValue;
		}
		$nc = $xpath->query ('ISSN[@IssnType="Electronic"]', $journal_node);
		foreach ($nc as $n)
		{	
			if ($journal_id == '')
			{
				$journal_id =  $n->firstChild->nodeValue;
			}
		}
		
		if ($journal_id != '')
		{
			$journal_id = 'http://www.worldcat.org/issn/' . $journal_id;
			$reference->{'http://schema.org/isPartOf'}[] = $journal_id;
			
			$journal = new stdclass;
			$journal->{'@id'} = $journal_id;			
			
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Periodical';
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/Journal';
	
			$nc = $xpath->query ('Title', $journal_node);
			foreach ($nc as $n)
			{	
				$journal->{'http://schema.org/name'}[] =  $n->firstChild->nodeValue;
			}
			
			$items[] = $journal;
			

		}

	}
	

	
	
	
	// authors
	$count = 1;
	$nodeCollection = $xpath->query ('//AuthorList/Author');
	foreach ($nodeCollection as $node)
	{	
		$author = new stdclass;
		$author->{'@id'} = $reference->{'@id'} . '/contributor/' . $count;
		$author->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Person';

		$name = '';
		
		$nc = $xpath->query ('ForeName', $node);
		foreach ($nc as $n)
		{	
			$name = $n->firstChild->nodeValue;
		}
		if ($name == '')
		{
			$nc = $xpath->query ('Initials', $node);
			foreach ($nc as $n)
			{	
				$name .= ' ' . $n->firstChild->nodeValue;
			}
		}
		$nc = $xpath->query ('LastName', $node);
		foreach ($nc as $n)
		{	
			$name .= ' ' . $n->firstChild->nodeValue;
		}
		
		$author->{'http://schema.org/name'}[] = $name;
		
		$reference->{'http://purl.org/dc/terms/creator'}[] = $author->{'@id'};		
		
		
		$items[] = $author;
		$count++;		
	}		
	
	
	// identifiers
	$nodeCollection = $xpath->query ('//ArticleIdList/ArticleId[@IdType = "doi"]');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/ontology/bibo/doi'}[] 	= $node->firstChild->nodeValue;
		$reference->{'http://schema.org/sameAs'}[] 				= 'http://identifiers.org/doi/' . $node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[]  	= 'http://identifiers.org/doi/' . $node->firstChild->nodeValue;
	}
	$nodeCollection = $xpath->query ('//ArticleIdList/ArticleId[@IdType = "pmc"]');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://schema.org/sameAs'}[] 				= 'http://identifiers.org/pmc/' . $node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[]  	= 'http://identifiers.org/pmc/' . $node->firstChild->nodeValue;
		
		// store PMC because we can use it 
		$pmc = $node->firstChild->nodeValue;
	}
	

	// mesh	
	$nodeCollection = $xpath->query ('//MeshHeadingList/MeshHeading/DescriptorName/@UI');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://schema.org/about'}[] = 'http://identifiers.org/mesh/' . $node->firstChild->nodeValue;
	}
	
	if ($pmc != '')
	{
		// grab citation links (resolve later)
		cited_by_in_pmc($pmc, $reference);

		cites_in_pubmed($pmc, $reference);
		
		pubmed_to_nucleotides($reference->{'@id'}, $reference);
	}
	
	
	$items[] = $reference;
	
	//print_r($items);
	
	// Context for JSON-LD
	$context = new stdclass;

	// Bibliographic Ontology
	$context->{'@vocab'} = 'http://purl.org/ontology/bibo/';

	
	// Dublin Core
	$context->dc 			= 'http://purl.org/dc/terms/';
	$context->creator 		= 'dc:creator';
	$context->identifier 	= 'dc:identifier';
	$context->references	= 'dc:references';
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
	
	// Schema types
	$context->CreativeWork 		= 'schema:CreativeWork';
	$context->Periodical 		= 'schema:Periodical';
	$context->Person 			= 'schema:Person';
	$context->ScholarlyArticle 	= 'schema:ScholarlyArticle';
	$context->Dataset 			= 'schema:Dataset';
	
	// CITO
	$context->cites 			= 'http://purl.org/spar/cito/cites';
	$context->isCitedBy 		= 'http://purl.org/spar/cito/isCitedBy';

	// Identifiers.org namespaces
	$context->identifiers 	= 'http://identifiers.org/';
	$context->DOI 			= 'identifiers:doi/';
	$context->INSDC 		= 'identifiers:insdc/';
	$context->MESH 			= 'identifiers:mesh/';
	$context->PMC 			= 'identifiers:pmc/';
	$context->PMID 			= 'identifiers:pubmed/';
	
	// Other identifiers
	$context->ISSN 			= 'http://www.worldcat.org/issn/';
	
	$context->GI			= 'http://www.ncbi.nlm.nih.gov/nuccore/';


	$nq = item_to_quads($items);

	$jsonld = jsonld_from_rdf($nq);
	$jsonld = jsonld_compact($jsonld, $context);

	$jsonld->_id = 'http://www.ncbi.nlm.nih.gov/pubmed/' . $reference->{'@id'};

	
	$objects[] = $jsonld;

	return $objects;	

}

//----------------------------------------------------------------------------------------
function pubmed_fetch($pmid)
{
	$objects = array();
	
	if (0)
	{
	
		// API call (ESummary supports JSON)
		// XML
		// http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&id=17148433&rettype=xml

		// http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=17148433&retmode=json
	
		$parameters = array(
			'db'		=> 'pubmed',
			'id' 		=> $pmid,
			'retmode'	=> 'json'
		);
	
		$url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?' . http_build_query($parameters);
	
		$data = get($url);
	
		//echo $data;
	
		if ($data != '')
		{
			$objects = pubmed_parse($data);
		}
	}
	
	if (1)
	{
		// Eutils XML
		
		$parameters = array(
			'db'		=> 'pubmed',
			'id' 		=> $pmid,
			'retmode'	=> 'xml'
		);
	
		$url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query($parameters);
	
		$xml = get($url);
		
		if ($xml != '')
		{
			$objects = pubmed_parse_xml($xml);
		}
		
	}
	
	
	//print_r($objects);
	
	return ($objects);
}
	
if (0)
{
	//pubmed_fetch('17148433');
	
	$data = file_get_contents('17148433.json');
	pubmed_parse($data);
}

if (0)
{
	$xml = file_get_contents('948206.xml');
	
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$items = array();

	$reference = new stdclass;
	
	$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/CreativeWork';		
			
	$nodeCollection = $xpath->query ('//PublicationTypeList/PublicationType');
	foreach ($nodeCollection as $node)
	{	
		if ($node->firstChild->nodeValue == "Journal Article")
		{
			$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/AcademicArticle';
			$reference->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/ScholarlyArticle';
		}					
	}

	// PMID is identifier
	$nodeCollection = $xpath->query ('//PMID');
	foreach ($nodeCollection as $node)
	{		
		$reference->{'@id'} = 'http://identifiers.org/pubmed/' . $node->firstChild->nodeValue;
		$reference->{'http://purl.org/ontology/bibo/pmid'}[] = 	$node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[] = $reference->{'@id'};
	}

	// title
	$nodeCollection = $xpath->query ('//ArticleTitle');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/dc/terms/title'}[] = $node->firstChild->nodeValue;
		$reference->{'http://schema.org/name'}[] = $node->firstChild->nodeValue;
	}

	// abstract
	$nodeCollection = $xpath->query ('//Abstract/AbstractText');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/ontology/bibo/abstract'}[] = $node->firstChild->nodeValue;
		$reference->{'http://schema.org/description'}[] = $node->firstChild->nodeValue;
	}
	
	/*
	// journal
<Journal>
<ISSN IssnType="Print">1055-7903</ISSN>
<JournalIssue CitedMedium="Print">
<Volume>31</Volume>
<Issue>1</Issue>
<PubDate>
<Year>2004</Year>
<Month>Apr</Month>
</PubDate>
</JournalIssue>
<Title>Molecular phylogenetics and evolution</Title>
<ISOAbbreviation>Mol. Phylogenet. Evol.</ISOAbbreviation>
</Journal>*/

	$nodeCollection = $xpath->query ('//Journal');
	foreach ($nodeCollection as $journal_node)
	{	
		$nc = $xpath->query ('JournalIssue/Volume', $journal_node);
		foreach ($nc as $n)
		{	
			$reference->{'http://purl.org/ontology/bibo/volume'}[] =  $n->firstChild->nodeValue;
		}
		$nc = $xpath->query ('JournalIssue/Issue', $journal_node);
		foreach ($nc as $n)
		{	
			$reference->{'http://purl.org/ontology/bibo/issue'}[] =  $n->firstChild->nodeValue;
		}

		$journal_id = '';
		$nc = $xpath->query ('ISSN[@IssnType="Print"]', $journal_node);
		foreach ($nc as $n)
		{	
			$journal_id =  $n->firstChild->nodeValue;
		}
		$nc = $xpath->query ('ISSN[@IssnType="Electronic"]', $journal_node);
		foreach ($nc as $n)
		{	
			if ($journal_id == '')
			{
				$journal_id =  $n->firstChild->nodeValue;
			}
		}
		
		if ($journal_id != '')
		{
			$journal_id = 'http://www.worldcat.org/issn/' . $journal_id;
			$reference->{'http://schema.org/isPartOf'}[] = $journal_id;
			
			$journal = new stdclass;
			$journal->{'@id'} = $journal_id;			
			
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://schema.org/Periodical';
			$journal->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = 'http://purl.org/ontology/bibo/Journal';
	
			$nc = $xpath->query ('Title', $journal_node);
			foreach ($nc as $n)
			{	
				$journal->{'http://schema.org/name'}[] =  $n->firstChild->nodeValue;
			}
			
			$items[] = $journal;
			

		}

	}
	

	
	
	
	// authors
	
	// identifiers
	$nodeCollection = $xpath->query ('//ArticleIdList/ArticleId[@IdType = "doi"]');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://purl.org/ontology/bibo/doi'}[] 	= $node->firstChild->nodeValue;
		$reference->{'http://schema.org/sameAs'}[] 				= $node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[]  	= $node->firstChild->nodeValue;
	}
	$nodeCollection = $xpath->query ('//ArticleIdList/ArticleId[@IdType = "pmc"]');
	foreach ($nodeCollection as $node)
	{	
		$reference->{'http://schema.org/sameAs'}[] 				= 'http://identifiers.org/pmc/' . $node->firstChild->nodeValue;
		$reference->{'http://purl.org/dc/terms/identifier'}[]  	= 'http://identifiers.org/pmc/' . $node->firstChild->nodeValue;
	}
	

	// mesh	
	
	
	$items[] = $reference;
	
	print_r($items);
	
	/*
	
		$data = new stdclass;
	
		// attributes of //core
		if ($core->hasAttributes()) 
		{ 
			$data->attributes = array();
			$attrs = $core->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$data->attributes[$attr->name] = $attr->value; 
			}
		}
		
		//print_r($attributes);
		
		// file
		$files = $xpath->query ('dwc_text:files/dwc_text:location', $core);
		foreach ($files as $file)
		{		
			$data->filename = $file->firstChild->nodeValue;
		}
	*/


	
}




?>
