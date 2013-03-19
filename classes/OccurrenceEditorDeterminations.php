<?php
if(isset($fpEnabled) && $fpEnabled){
	include_once('fp/FPNetworkFactory.php');
	include_once('fp/includes/symbiotahelper.php');
}

class OccurrenceEditorDeterminations extends OccurrenceEditorManager{

	private $detMap = Array();

	public function __construct(){
 		parent::__construct();
	}

	public function __destruct(){
 		parent::__destruct();
	}

	public function getDetMap($identBy, $dateIdent, $sciName){
		if(!$this->detMap && $this->occid){
			$this->setDeterminations($identBy, $dateIdent, $sciName);
		}
		return $this->detMap;
	}

	private function setDeterminations($identBy, $dateIdent, $sciName){
		$sql = "SELECT detid, identifiedBy, dateIdentified, sciname, scientificNameAuthorship, ".
			"identificationQualifier, identificationReferences, identificationRemarks, sortsequence ".
			"FROM omoccurdeterminations ".
			"WHERE (occid = ".$this->occid.") ORDER BY sortsequence";
		//echo "<div>".$sql."</div>";
		$result = $this->conn->query($sql);
		while($row = $result->fetch_object()){
			$detId = $row->detid;
			$this->detMap[$detId]["identifiedby"] = $this->cleanOutStr($row->identifiedBy);
			$this->detMap[$detId]["dateidentified"] = $this->cleanOutStr($row->dateIdentified);
			$this->detMap[$detId]["sciname"] = $this->cleanOutStr($row->sciname);
			$this->detMap[$detId]["scientificnameauthorship"] = $this->cleanOutStr($row->scientificNameAuthorship);
			$this->detMap[$detId]["identificationqualifier"] = $this->cleanOutStr($row->identificationQualifier);
			$this->detMap[$detId]["identificationreferences"] = $this->cleanOutStr($row->identificationReferences);
			$this->detMap[$detId]["identificationremarks"] = $this->cleanOutStr($row->identificationRemarks);
			$this->detMap[$detId]["sortsequence"] = $row->sortsequence;
			if($row->identifiedBy == $identBy && $row->dateIdentified == $dateIdent && $row->sciname == $sciName){
				$this->detMap[$detId]["iscurrent"] = "1";
			}
		}
		$result->close();
	}

	public function addDetermination($detArr){
		$status = "Determination submitted successfully!";
		$isCurrent = false;
		if(array_key_exists('makecurrent',$detArr) && $detArr['makecurrent'] == "1") $isCurrent = true;
		$sortSeq = 1;
		if(preg_match('/([1,2]{1}\d{3})/',$detArr['dateidentified'],$matches)){
			$sortSeq = 2100-$matches[1];
		}
		//Load new determination into omoccurdeterminations
		$sciname = $this->cleanInStr($detArr['sciname']);
		$sql = 'INSERT INTO omoccurdeterminations(occid, identifiedBy, dateIdentified, sciname, scientificNameAuthorship, '.
			'identificationQualifier, identificationReferences, identificationRemarks, sortsequence) '.
			'VALUES ('.$detArr['occid'].',"'.$this->cleanInStr($detArr['identifiedby']).'","'.$this->cleanInStr($detArr['dateidentified']).'","'.
			$sciname.'",'.($detArr['scientificnameauthorship']?'"'.$this->cleanInStr($detArr['scientificnameauthorship']).'"':'NULL').','.
			($detArr['identificationqualifier']?'"'.$this->cleanInStr($detArr['identificationqualifier']).'"':'NULL').','.
			($detArr['identificationreferences']?'"'.$this->cleanInStr($detArr['identificationreferences']).'"':'NULL').','.
			($detArr['identificationremarks']?'"'.$this->cleanInStr($detArr['identificationremarks']).'"':'NULL').','.$sortSeq.')';
		//echo "<div>".$sql."</div>";
		if($this->conn->query($sql)){
			//If is current, move old determination from omoccurrences to omoccurdeterminations and then load new record into omoccurrences  
			if($isCurrent){
				//If determination is already in omoccurdeterminations, INSERT will fail move omoccurrences determination to  table
				$sqlInsert = 'INSERT INTO omoccurdeterminations(occid, identifiedBy, dateIdentified, sciname, scientificNameAuthorship, '.
					'identificationQualifier, identificationReferences, identificationRemarks, sortsequence) '.
					'SELECT occid, IFNULL(identifiedby,"assumed to be collector") AS idby, IFNULL(dateidentified,"assumed to be collection date") AS di, '.
					'sciname, scientificnameauthorship, identificationqualifier, identificationreferences, identificationremarks, 10 AS sortseq '.
					'FROM omoccurrences WHERE (occid = '.$detArr['occid'].')';
				$this->conn->query($sqlInsert);
				//echo "<div>".$sqlInsert."</div>";
				//Check to see if taxon has a locality security protection (rare, threatened, or sensitive species)
				$sStatus = 0;
				$sqlSs = 'SELECT securitystatus FROM taxa WHERE (sciname = "'.$sciname.'")';
				$rsSs = $this->conn->query($sqlSs);
				if($rSs = $rsSs->fetch_object()){
					if($rSs->securitystatus == 1) $sStatus = 1;
				}
				$rsSs->free();
				
				//Load new determination into omoccurrences table
				$sqlNewDet = 'UPDATE omoccurrences '.
					'SET identifiedBy = "'.$this->cleanInStr($detArr['identifiedby']).'", dateIdentified = "'.$this->cleanInStr($detArr['dateidentified']).'",'.
					'family = '.($detArr['family']?'"'.$this->cleanInStr($detArr['family']).'"':'NULL').','.
					'sciname = "'.$sciname.'",genus = NULL, specificEpithet = NULL, taxonRank = NULL, infraspecificepithet = NULL,'.
					'scientificNameAuthorship = '.($detArr['scientificnameauthorship']?'"'.$this->cleanInStr($detArr['scientificnameauthorship']).'"':'NULL').','.
					'identificationQualifier = '.($detArr['identificationqualifier']?'"'.$this->cleanInStr($detArr['identificationqualifier']).'"':'NULL').','.
					'identificationReferences = '.($detArr['identificationreferences']?'"'.$this->cleanInStr($detArr['identificationreferences']).'"':'NULL').','.
					'identificationRemarks = '.($detArr['identificationremarks']?'"'.$this->cleanInStr($detArr['identificationremarks']).'"':'NULL').', '.
					'tidinterpreted = '.($detArr['tidtoadd']?$detArr['tidtoadd']:'NULL').', localitysecurity = '.$sStatus.
					' WHERE (occid = '.$detArr['occid'].')';
				//echo "<div>".$sqlNewDet."</div>";
				$this->conn->query($sqlNewDet);
			}
			$remapImages = false;
			if(array_key_exists('remapimages',$detArr) && $detArr['remapimages'] == "1") $remapImages = true;
			if($remapImages){
				$sql = 'UPDATE images SET tid = '.($detArr['tidtoadd']?$detArr['tidtoadd']:'NULL').' WHERE (occid = '.$detArr['occid'].')';
				//echo $sql;
				if(!$this->conn->query($sql)){
					$status = 'ERROR: Annotation added but failed to remap images to new name';
					$status .= ': '.$this->conn->error;
				}
			}
			//FP code
			global $fpEnabled;
			if(isset($fpEnabled) && $fpEnabled && isset($detArr['fpsubmit']) && $detArr['fpsubmit']) {
				$status = "Determination added successfully and submitted to Filtered Push!";
				try {
					// create an array that the annotation generator can understand from $detArr
					$annotation = fpNewDetArr($detArr);
			
					// generate rdf/xml
					$generator = FPNetworkFactory::getAnnotationGenerator();
					$rdf = $generator->generateRdfXml($annotation);
			
					// inject annotation into fp
					$network = FPNetworkFactory::getNetworkFacade();
					$response = $network->injectIntoFP($rdf);
				} 
				catch (Exception $e) {
					error_log($e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage());
					$status = "Determination added successfully but there was an error during submission to Filtered Push!";
				}
			}
		}
		else{
			$status = 'ERROR - failed to add determination: '.$this->conn->error;
		}
		return $status;
	}

	public function editDetermination($detArr){
		$status = "Determination editted successfully!";
		//Update determination table
		$sql = 'UPDATE omoccurdeterminations '.
			'SET identifiedBy = "'.$this->cleanInStr($detArr['identifiedby']).'", '.
			'dateIdentified = "'.$this->cleanInStr($detArr['dateidentified']).'", '.
			'sciname = "'.$this->cleanInStr($detArr['sciname']).'", '.
			'scientificNameAuthorship = '.($detArr['scientificnameauthorship']?'"'.$this->cleanInStr($detArr['scientificnameauthorship']).'"':'NULL').','.
			'identificationQualifier = '.($detArr['identificationqualifier']?'"'.$this->cleanInStr($detArr['identificationqualifier']).'"':'NULL').','.
			'identificationReferences = '.($detArr['identificationreferences']?'"'.$this->cleanInStr($detArr['identificationreferences']).'"':'NULL').','.
			'identificationRemarks = '.($detArr['identificationremarks']?'"'.$this->cleanInStr($detArr['identificationremarks']).'"':'NULL').','.
			'sortsequence = '.($detArr['sortsequence']?$detArr['sortsequence']:'10').' '.
			'WHERE (detid = '.$detArr['detid'].')';
		if(!$this->conn->query($sql)){
			$status = "ERROR - failed to edit determination: ".$this->conn->error;
		}
		return $status;
	}

	public function deleteDetermination($detId){
		$status = 'Determination deleted successfully!';
		$sql = 'DELETE FROM omoccurdeterminations WHERE (detid = '.$detId.')';
		if(!$this->conn->query($sql)){
			$status = "ERROR - failed to delete determination: ".$this->conn->error;
		}
		return $status;
	}

	public function makeDeterminationCurrent($detId,$remapImages){
		$status = 'Determination is now current!';
		//Make sure current is in omoccurdeterminations. If already there, INSERT will fail and nothing lost
		$sqlInsert = 'INSERT INTO omoccurdeterminations(occid, identifiedBy, dateIdentified, sciname, scientificNameAuthorship, '.
			'identificationQualifier, identificationReferences, identificationRemarks, sortsequence) '.
			'SELECT occid, IFNULL(identifiedby,"assumed to be collector") AS idby, '.
			'IFNULL(dateidentified,"assumed to be collection date") AS iddate, sciname, scientificnameauthorship, '.
			'identificationqualifier, identificationreferences, identificationremarks, 10 AS sortseq '.
			'FROM omoccurrences WHERE (occid = '.$this->occid.')';
		$this->conn->query($sqlInsert);
		//echo "<div>".$sqlInsert."</div>";
		//Update omoccurrences to reflect this determination
		$tid = 0;
		$sStatus = 0;
		$family = '';
		$sqlTid = 'SELECT t.tid, t.securitystatus, ts.family '.
			'FROM omoccurdeterminations d INNER JOIN taxa t ON d.sciname = t.sciname '.
			'INNER JOIN taxstatus ts ON t.tid = ts.tid '.
			'WHERE (d.detid = '.$detId.') AND (taxauthid = 1)';
		$rs = $this->conn->query($sqlTid);
		if($r = $rs->fetch_object()){
			$tid = $r->tid;
			$family = $r->family;
			if($r->securitystatus == 1) $sStatus = 1;
		}
		$rs->free();

		$sqlNewDet = 'UPDATE omoccurrences o INNER JOIN omoccurdeterminations d ON o.occid = d.occid '.
			'SET o.identifiedBy = d.identifiedBy, o.dateIdentified = d.dateIdentified,o.family = '.($family?'"'.$family.'"':'NULL').','.
			'o.sciname = d.sciname,o.genus = NULL,o.specificEpithet = NULL,o.taxonRank = NULL,o.infraspecificepithet = NULL,o.scientificname = NULL,'.
			'o.scientificNameAuthorship = d.scientificnameauthorship,o.identificationQualifier = d.identificationqualifier,'.
			'o.identificationReferences = d.identificationreferences,o.identificationRemarks = d.identificationremarks,'.
			'o.tidinterpreted = '.($tid?$tid:'NULL').', o.localitysecurity = '.$sStatus.
			' WHERE (detid = '.$detId.')';
		//echo "<div>".$sqlNewDet."</div>";
		$this->conn->query($sqlNewDet);

		if($remapImages){
			if($tid){
				$sql = 'UPDATE images SET tid = '.$tid.' WHERE (occid = '.$this->occid.')';
				//echo $sql;
				$this->conn->query($sql);
			}
			else{
				$status = 'ERROR: Annotation made current but failed to remap image because taxon name not linked to taxonomic thesaurus.';
			}
		}
	}
}
?>