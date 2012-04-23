<?php
include_once($serverRoot.'/config/dbconnection.php');

class SpecLoans{

	private $conn;
	private $collId = 0;
	private $loanId = 0;

	function __construct() {
		$this->conn = MySQLiConnectionFactory::getCon("write");
	}
	
	function __destruct(){
 		if($this->conn) $this->conn->close();
	}

	public function getLoanOutList($searchTerm,$displayAll){
		$retArr = array();
		$sql = 'SELECT loanid, loanidentifierown, dateclosed '.
			'FROM omoccurloans '.
			'WHERE collidown = '.$this->collId.' ';
		if($searchTerm){
			$sql .= 'AND loanidentifierown LIKE "%'.$searchTerm.'%" ';
		}
		if(!$displayAll){
			$sql .= 'AND ISNULL(dateclosed) ';
		}
		$sql .= 'ORDER BY loanidentifierown';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->loanid]['loanidentifierown'] = $r->loanidentifierown;
				$retArr[$r->loanid]['dateclosed'] = $r->dateclosed;
			}
			$rs->close();
		}
		return $retArr;
	} 
	
	public function getLoanOnWayList(){
		$retArr = array();
		$sql = 'SELECT DISTINCT o.loanid, o.loanidentifierown, c.collectionname '.
			'FROM omoccurloans AS o LEFT OUTER JOIN omcollections AS c ON o.iidBorrower = c.iid '.
			'WHERE c.CollID = '.$this->collId.' AND ISNULL(o.collidBorr) AND ISNULL(o.dateClosed)' ;
		//echo $sql;
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->loanid]['loanidentifierown'] = $r->loanidentifierown;
				$retArr[$r->loanid]['collectionname'] = $r->collectionname;
			}
			$rs->close();
		}
		return $retArr;
	}
	
	public function getLoanInList($searchTerm,$displayAll){
		$retArr = array();
		$sql = 'SELECT loanid, loanidentifierborr, dateclosed '.
			'FROM omoccurloans '.
			'WHERE collidborr = '.$this->collId.' ';
		if($searchTerm){
			$sql .= 'AND loanidentifierborr LIKE "%'.$searchTerm.'%" ';
		}
		if(!$displayAll){
			$sql .= 'AND ISNULL(dateclosed) ';
		}
		$sql .= 'ORDER BY loanidentifierborr';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->loanid]['loanidentifierborr'] = $r->loanidentifierborr;
				$retArr[$r->loanid]['dateclosed'] = $r->dateclosed;
			}
			$rs->close();
		}
		return $retArr;
	} 
	
	//Ed's version
	/*public function getLoansIn(){
		$retArr = array();
		$sql = 'SELECT loanid, IFNULL(loanIdentifierReceiver, loanIdentifier) AS loanidentifier, datesent, dateclosed, '. 
			'forwhom, description, datedue '.
			'FROM omoccurloans l INNER JOIN institutions i ON l.iidreceiving = i.iid '.
			'WHERE (i.collidborr = '.$this->collId.')';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['loanid']['loanidentifier'] = $r->loanidentifier;
				$retArr['loanid']['datesent'] = $r->datesent;
				$retArr['loanid']['dateclosed'] = $r->dateclosed;
				$retArr['loanid']['forwhom'] = $r->forwhom;
				$retArr['loanid']['description'] = $r->description;
				$retArr['loanid']['datedue'] = $r->datedue;
			}
			$rs->close();
		}
		return $retArr;
	}*/

	public function getLoanOutDetails($loanId){
		$retArr = array();
		$sql = 'SELECT loanid, loanidentifierown, iidborrower, datesent, totalboxes, '.
			'shippingmethod, datedue, datereceivedown, dateclosed, forwhom, description, '.
			'notes, createdbyown, processedbyown, processedbyreturnown '.
			'FROM omoccurloans '.
			'WHERE loanid = '.$loanId;
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['loanidentifierown'] = $r->loanidentifierown;
				$retArr['iidborrower'] = $r->iidborrower;
				$retArr['datesent'] = $r->datesent;
				$retArr['totalboxes'] = $r->totalboxes;
				$retArr['shippingmethod'] = $r->shippingmethod;
				$retArr['datedue'] = $r->datedue;
				$retArr['datereceivedown'] = $r->datereceivedown;
				$retArr['dateclosed'] = $r->dateclosed;
				$retArr['forwhom'] = $r->forwhom;
				$retArr['description'] = $r->description;
				$retArr['notes'] = $r->notes;
				$retArr['createdbyown'] = $r->createdbyown;
				$retArr['processedbyown'] = $r->processedbyown;
				$retArr['processedbyreturnown'] = $r->processedbyreturnown;
			}
			$rs->close();
		}
		return $retArr;
	} 
	
	public function getLoanInDetails($loanId){
		$retArr = array();
		$sql = 'SELECT loanid, loanidentifierown, loanidentifierborr, collidown, iidowner, datesentreturn, totalboxesreturned, '.
			'shippingmethodreturn, datedue, datereceivedborr, dateclosed, forwhom, description, numspecimens, '.
			'notes, createdbyborr, processedbyborr, processedbyreturnborr '.
			'FROM omoccurloans '.
			'WHERE loanid = '.$loanId;
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['loanidentifierown'] = $r->loanidentifierown;
				$retArr['loanidentifierborr'] = $r->loanidentifierborr;
				$retArr['collidown'] = $r->collidown;
				$retArr['iidowner'] = $r->iidowner;
				$retArr['datesentreturn'] = $r->datesentreturn;
				$retArr['totalboxesreturned'] = $r->totalboxesreturned;
				$retArr['shippingmethodreturn'] = $r->shippingmethodreturn;
				$retArr['datedue'] = $r->datedue;
				$retArr['datereceivedborr'] = $r->datereceivedborr;
				$retArr['dateclosed'] = $r->dateclosed;
				$retArr['forwhom'] = $r->forwhom;
				$retArr['description'] = $r->description;
				$retArr['numspecimens'] = $r->numspecimens;
				$retArr['notes'] = $r->notes;
				$retArr['createdbyborr'] = $r->createdbyborr;
				$retArr['processedbyborr'] = $r->processedbyborr;
				$retArr['processedbyreturnborr'] = $r->processedbyreturnborr;
			}
			$rs->close();
		}
		return $retArr;
	} 

	public function editLoanOut($pArr){
		$statusStr = '';
		$loanId = $pArr['loanid'];
		if(is_numeric($loanId)){
			$sql = '';
			foreach($pArr as $k => $v){
				if($k != 'formsubmit' && $k != 'loanid' && $k != 'collid'){
					$sql .= ','.$k.'='.($v?'"'.$this->cleanString($v).'"':'NULL');
				}
			}
			$sql = 'UPDATE omoccurloans SET '.substr($sql,1).' WHERE (loanid = '.$loanId.')';
			if($this->conn->query($sql)){
				$statusStr = 'SUCCESS: information saved';
			}
			else{
				$statusStr = 'ERROR: Editing of loan failed: '.$this->conn->error.'<br/>';
				$statusStr .= 'SQL: '.$sql;
			}
		}
		return $statusStr;
	}
	
	public function editLoanIn($pArr){
		$statusStr = '';
		$loanId = $pArr['loanid'];
		if(is_numeric($loanId)){
			$sql = '';
			foreach($pArr as $k => $v){
				if($k != 'formsubmit' && $k != 'loanid' && $k != 'collid'){
					$sql .= ','.$k.'='.($v?'"'.$this->cleanString($v).'"':'NULL');
				}
			}
			$sql = 'UPDATE omoccurloans SET '.substr($sql,1).' WHERE (loanid = '.$loanId.')';
			if($this->conn->query($sql)){
				$statusStr = 'SUCCESS: information saved';
			}
			else{
				$statusStr = 'ERROR: Editing of loan failed: '.$this->conn->error.'<br/>';
				$statusStr .= 'SQL: '.$sql;
			}
		}
		return $statusStr;
	}
	
	public function createNewLoanOut($pArr){
		$statusStr = '';
		$sql = 'INSERT INTO omoccurloans(collidown,loanidentifierown,iidowner,iidborrower,createdbyown) '.
			'VALUES('.$this->collId.',"'.$this->cleanString($pArr['loanidentifierown']).'",(SELECT iid FROM omcollections WHERE collid = '.$this->collId.'), '.
			'"'.$this->cleanString($pArr['reqinstitution']).'","'.$this->cleanString($pArr['createdbyown']).'") ';
		//echo $sql;
		if($this->conn->query($sql)){
			$this->loanId = $this->conn->insert_id;
		}
		else{
			$statusStr = 'ERROR: Creation of new loan failed: '.$this->conn->error.'<br/>';
			$statusStr .= 'SQL: '.$sql;
		}
		return $statusStr;
	}
	
	//
	public function getloanIdentifierBorr($pArr){
		$statusStr = '';
		$sql = 'INSERT INTO omoccurloans(collidborr,loanidentifierborr,iidowner,createdbyborr) '.
			'VALUES('.$this->collId.',"'.$this->cleanString($pArr['loanidentifierborr']).'","'.$this->cleanString($pArr['iidowner']).'",
			"'.$this->cleanString($pArr['createdbyborr']).'")';
		//echo $sql;
		if($this->conn->query($sql)){
			$this->loanId = $this->conn->insert_id;
		}
		else{
			$statusStr = 'ERROR: Creation of new loan failed: '.$this->conn->error.'<br/>';
			$statusStr .= 'SQL: '.$sql;
		}
		return $statusStr;
	}
	
	public function createNewLoanIn($pArr){
		$statusStr = '';
		$sql = 'INSERT INTO omoccurloans(collidborr,loanidentifierown,loanidentifierborr,iidowner,createdbyborr) '.
			'VALUES('.$this->collId.',"","'.$this->cleanString($pArr['loanidentifierborr']).'","'.$this->cleanString($pArr['iidowner']).'",
			"'.$this->cleanString($pArr['createdbyborr']).'")';
		//echo $sql;
		if($this->conn->query($sql)){
			$this->loanId = $this->conn->insert_id;
		}
		else{
			$statusStr = 'ERROR: Creation of new loan failed: '.$this->conn->error.'<br/>';
			$statusStr .= 'SQL: '.$sql;
		}
		return $statusStr;
	}
	
	public function getSpecTotal($loanId){
		$retArr = array();
		$sql = 'SELECT loanid, COUNT(loanid) AS speccount '.
			'FROM omoccurloanslink '.
			'WHERE loanid = '.$loanId.' '.
			'GROUP BY loanid';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['speccount'] = $r->speccount;
			}
			$rs->close();
		}
		return $retArr;
	} 
	
	public function getSpecList($loanId){
		$retArr = array();
		$sql = 'SELECT l.loanid, l.occid, o.catalognumber, o.sciname '.
			'FROM omoccurloanslink AS l LEFT OUTER JOIN omoccurrences AS o ON l.occid = o.occid '.
			'WHERE l.loanid = '.$loanId.' '.
			'ORDER BY o.catalognumber';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->occid]['catalognumber'] = $r->catalognumber;
				$retArr[$r->occid]['sciname'] = $r->sciname;
			}
			$rs->close();
		}
		return $retArr;
	} 
	
	//This method is used by the ajax script insertloanspecimen.php
	public function addSpecimen($loanId,$collId,$catNum){
		$statusStr = '';
		$retArr = array();
		$loanId = $this->cleanString($loanId);
		$collId = $this->cleanString($collId);
		$catNum = $this->cleanString($catNum);
		$sql = 'SELECT occid FROM omoccurrences WHERE (collid = '.$collId.') AND (catalognumber = "'.$catNum.'") ';
		//echo $sql;
		$result = $this->conn->query($sql);
		while ($row = $result->fetch_object()) {
			$retArr[] = $row->occid;
		}
		if (count($retArr) == 0){
			$statusStr = 0;
		}
		elseif (count($retArr) > 1){
			$statusStr = 2;
		}
		else {
			$statusStr = 1;
			$sql = 'INSERT INTO omoccurloanslink(loanid,occid) '.
				'VALUES ('.$loanId.','.$retArr[0].') ';
			//echo $sql;
			$this->conn->query($sql);
		}
		return $statusStr;
	}
	
	//General look up functions
	public function getInstitutionArr(){
		$retArr = array();
		$sql = 'SELECT i.iid, IFNULL(c.institutioncode,i.institutioncode) as institutioncode, '. 
			'i.institutionname '. 
			'FROM institutions i LEFT JOIN (SELECT iid, institutioncode, collectioncode, collectionname '. 
			'FROM omcollections WHERE colltype = "Preserved Specimens") c ON i.iid = c.iid '. 
			'ORDER BY i.institutioncode,c.institutioncode,c.collectionname,i.institutionname';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->iid] = $r->institutioncode.' - '.$r->institutionname;
			}
		}
		return $retArr;
	} 
	
	//Get and set functions 
	public function setCollId($c){
		$this->collId = $c;
	}
	
	public function getLoanId(){
		return $this->loanId;
	}
	
	protected function cleanString($inStr){
		$retStr = trim($inStr);
		$retStr = $this->conn->real_escape_string($retStr);
		return $retStr;
	}
}
?>