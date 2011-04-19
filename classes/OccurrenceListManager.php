<?php
/*
 * Created on 1 May 2009
 * @author  E. Gilbert: egbot@asu.edu
 */
include_once("OccurrenceManager.php");

class OccurrenceListManager extends OccurrenceManager{

	private $cntPerPage = 50;	  //default is 50 - this can be set in the jsp page
	protected $recordCount = 0;
	protected $dynamicClid = 0;
	
 	public function __construct(){
 		parent::__construct();
 	}

	public function __destruct(){
 		parent::__destruct();
	}

	public function getSpecimenMap($pageRequest){
		global $userRights;
		$returnArr = Array();
		$sqlWhere = $this->getSqlWhere();
		if(!$this->recordCount || $this->reset){
			$this->setRecordCnt($sqlWhere);
		}
		$sql = "SELECT o.occid, o.CollID, o.institutioncode, o.collectioncode, IFNULL(o.CatalogNumber,'') AS catalognumber, o.family, o.sciname, o.tidinterpreted, ".
			"IFNULL(o.scientificNameAuthorship,'') AS author, IFNULL(o.recordedBy,'') AS recordedby, IFNULL(o.recordNumber,'') AS recordnumber, ".
			"IFNULL(DATE_FORMAT(o.eventDate,'%d %M %Y'),'') AS date1, DATE_FORMAT(MAKEDATE(o.year,o.endDayOfYear),'%d %M %Y') AS date2, ".
			"IFNULL(o.country,'') AS country, IFNULL(o.StateProvince,'') AS state, IFNULL(o.county,'') AS county, ".
			"IFNULL(o.locality,'') AS locality, o.dbpk, IFNULL(o.LocalitySecurity,0) AS LocalitySecurity, o.localitysecurityreason, o.observeruid ".
			"FROM omoccurrences o INNER JOIN omcollections c ON o.collid = c.collid ";
		if(array_key_exists("surveyid",$this->searchTermsArr)) $sql .= "INNER JOIN omsurveyoccurlink sol ON o.occid = sol.occid ";
		$sql .= $sqlWhere;
		$bottomLimit = ($pageRequest - 1)*$this->cntPerPage;
		$sql .= "ORDER BY c.sortseq, c.collectionname ";
		if(strpos($sqlWhere,"(o.sciname") || strpos($sqlWhere,"o.family")){
			$sql .= ",o.sciname ";
		}
		$sql .= ",o.recordedBy,o.recordNumber ";			
		$sql .= "LIMIT ".$bottomLimit.",".$this->cntPerPage;
		//echo "<div>Spec sql: ".$sql."</div>";
		$result = $this->conn->query($sql);
		$canReadRareSpp = false;
		if(array_key_exists("SuperAdmin", $userRights) || array_key_exists("CollAdmin", $userRights) || array_key_exists("RareSppAdmin", $userRights) || array_key_exists("RareSppReadAll", $userRights)){
			$canReadRareSpp = true;
		}
		while($row = $result->fetch_object()){
			$collIdStr = $row->CollID;
			$dbpk = $row->dbpk;
			$returnArr[$collIdStr][$dbpk]["occid"] = $row->occid;
			$returnArr[$collIdStr][$dbpk]["institutioncode"] = $row->institutioncode;
			$returnArr[$collIdStr][$dbpk]["collectioncode"] = $row->collectioncode;
			$returnArr[$collIdStr][$dbpk]["accession"] = $row->catalognumber;
			$returnArr[$collIdStr][$dbpk]["family"] = $row->family;
			$returnArr[$collIdStr][$dbpk]["sciname"] = $row->sciname;
			$returnArr[$collIdStr][$dbpk]["tid"] = $row->tidinterpreted;
			$returnArr[$collIdStr][$dbpk]["author"] = $row->author;
			$returnArr[$collIdStr][$dbpk]["collector"] = $row->recordedby;
			$returnArr[$collIdStr][$dbpk]["collnumber"] = $row->recordnumber;
			$returnArr[$collIdStr][$dbpk]["date1"] = $row->date1;
			$returnArr[$collIdStr][$dbpk]["date2"] = $row->date2;
			$returnArr[$collIdStr][$dbpk]["country"] = $row->country;
			$returnArr[$collIdStr][$dbpk]["state"] = $row->state;
			$returnArr[$collIdStr][$dbpk]["county"] = $row->county;
			$returnArr[$collIdStr][$dbpk]["observeruid"] = $row->observeruid;
			$localitySecurity = $row->LocalitySecurity;
			if(!$localitySecurity || $canReadRareSpp || (array_key_exists("RareSppReader", $userRights) && in_array($collIdStr,$userRights["RareSppReader"]))){
				$returnArr[$collIdStr][$dbpk]["locality"] = $row->locality;
			}
			else{
				$securityStr = '<span style="color:red;">Detailed locality information protected. ';
				if($row->localitysecurityreason){
					$securityStr .= $row->localitysecurityreason;
				}
				else{
					$securityStr .= 'This is typically done to protect rare or threatened species localities.';
				}
				$returnArr[$collIdStr][$dbpk]["locality"] = $securityStr.'</span>';
			}
			$returnArr[$collIdStr][$dbpk]["dbpk"] = $row->dbpk;
		}
		$result->close();
		return $returnArr;
	}

	private function setRecordCnt($sqlWhere){
		global $clientRoot;
		if($sqlWhere){
			$sql = "SELECT COUNT(o.occid) AS cnt FROM omoccurrences o ";
			if(array_key_exists("surveyid",$this->searchTermsArr)) $sql .= "INNER JOIN omsurveyoccurlink sol ON o.occid = sol.occid ";
			$sql .= $sqlWhere;
			//echo "<div>Count sql: ".$sql."</div>";
			$result = $this->conn->query($sql);
			if($row = $result->fetch_object()){
				$this->recordCount = $row->cnt;
			}
			$result->close();
		}
		setCookie("collvars","reccnt:".$this->recordCount,time()+64800,$clientRoot);
	}

	public function getRecordCnt(){
		return $this->recordCount;
	}
	
	public function getCntPerPage(){
		return $this->cntPerPage;
	}
}
?>