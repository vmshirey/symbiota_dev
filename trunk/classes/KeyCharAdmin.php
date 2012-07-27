<?php
include_once($serverRoot.'/config/dbconnection.php');

class KeyAdmin{

	private $conn;
	private $collId = 0;
	private $cId = 0;

	function __construct() {
		$this->conn = MySQLiConnectionFactory::getCon("write");
	}
	
	function __destruct(){
 		if($this->conn) $this->conn->close();
	}
	
	public function getCharList(){
		$retArr = array();
		$sql = 'SELECT cid, charname '.
			'FROM kmcharacters '.
			'ORDER BY charname ASC';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->cid]['charname'] = $r->charname;
			}
			$rs->close();
		}
		return $retArr;
	}
	
	public function createCharacter($pArr){
		$statusStr = '';
		$sql = 'INSERT INTO kmcharacters(charname,chartype,defaultlang,difficultyrank,hid,enteredby) '.
			'VALUES("'.$this->cleanString($pArr['charname']).'","'.$this->cleanString($pArr['chartype']).'",
			"'.$this->cleanString($pArr['defaultlang']).'","'.$this->cleanString($pArr['difficultyrank']).'",
			"'.$this->cleanString($pArr['hid']).'","'.$this->cleanString($pArr['enteredby']).'") ';
		//echo $sql;
		if($this->conn->query($sql)){
			$this->cId = $this->conn->insert_id;
		}
		else{
			$statusStr = 'ERROR: Creation of new character failed: '.$this->conn->error.'<br/>';
			$statusStr .= 'SQL: '.$sql;
		}
		return $statusStr;
	}
	
	public function createState($pArr){
		$statusStr = '';
		$sql = 'INSERT INTO kmcs(cid,charstatename,implicit,language,enteredby) '.
			'VALUES("'.$this->cleanString($pArr['cid']).'","'.$this->cleanString($pArr['charstatename']).'",1,
			"'.$this->cleanString($pArr['language']).'","'.$this->cleanString($pArr['enteredby']).'") ';
		//echo $sql;
		if($this->conn->query($sql)){
			$this->cs = $this->conn->insert_id;
		}
		else{
			$statusStr = 'ERROR: Creation of new character failed: '.$this->conn->error.'<br/>';
			$statusStr .= 'SQL: '.$sql;
		}
		return $statusStr;
	}
	
	public function editCharacter($pArr){
		$statusStr = '';
		$cId = $pArr['cid'];
		if(is_numeric($cId)){
			$sql = '';
			foreach($pArr as $k => $v){
				if($k != 'formsubmit' && $k != 'cid'){
					$sql .= ','.$k.'='.($v?'"'.$this->cleanString($v).'"':'NULL');
				}
			}
			$sql = 'UPDATE kmcharacters SET '.substr($sql,1).' WHERE (cid = '.$cId.')';
			if($this->conn->query($sql)){
				$statusStr = 'SUCCESS: information saved';
			}
			else{
				$statusStr = 'ERROR: Editing of character failed: '.$this->conn->error.'<br/>';
				$statusStr .= 'SQL: '.$sql;
			}
		}
		return $statusStr;
	}
	
	public function getCharDetails($cId){
		$retArr = array();
		$sql = 'SELECT cid, charname, chartype, defaultlang, difficultyrank, hid, units, '.
			'description, notes, helpurl, enteredby '.
			'FROM kmcharacters '.
			'WHERE cid = '.$cId;
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['charname'] = $r->charname;
				$retArr['chartype'] = $r->chartype;
				$retArr['defaultlang'] = $r->defaultlang;
				$retArr['difficultyrank'] = $r->difficultyrank;
				$retArr['hid'] = $r->hid;
				$retArr['units'] = $r->units;
				$retArr['description'] = $r->description;
				$retArr['notes'] = $r->notes;
				$retArr['helpurl'] = $r->helpurl;
				$retArr['enteredby'] = $r->enteredby;
			}
			$rs->close();
		}
		return $retArr;
	}
	
	public function getCharStateDetails($cId,$cs){
		$retArr = array();
		$sql = 'SELECT cid, cs, charstatename, implicit, notes, description, illustrationurl, '.
			'language, enteredby '.
			'FROM kmcs '.
			'WHERE cid = '.$cId.' AND cs = '.$cs;
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr['charstatename'] = $r->charstatename;
				$retArr['implicit'] = $r->implicit;
				$retArr['notes'] = $r->notes;
				$retArr['description'] = $r->description;
				$retArr['illustrationurl'] = $r->illustrationurl;
				$retArr['language'] = $r->language;
				$retArr['enteredby'] = $r->enteredby;
			}
			$rs->close();
		}
		return $retArr;
	}
	
	public function deleteChar($cId){
		$status = 0;
		if(is_numeric($cId)){
			$sql = 'DELETE FROM kmcharacters WHERE (cid = '.$cId.')';
			if($this->conn->query($sql)){
				$status = 1;
			}
		}
		return $status;
	}
	
	public function getCharStateList($cId){
		$retArr = array();
		$sql = 'SELECT cs, charstatename '.
			'FROM kmcs '.
			'WHERE cid = '.$cId.' '.
			'ORDER BY charstatename ASC';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->cs]['cs'] = $r->cs;
				$retArr[$r->cs]['charstatename'] = $r->charstatename;
			}
			$rs->close();
		}
		return $retArr;
	}

	//Get and set functions 
	public function getHeadingArr(){
		$retArr = array();
		$sql = 'SELECT hid, headingname '. 
			'FROM kmcharheading '.
			'WHERE language = "English" '.
			'ORDER BY hid';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->hid] = $r->headingname;
			}
		}
		return $retArr;
	}
	
	public function setCollId($c){
		$this->collId = $c;
	}
	
	public function getcId(){
		return $this->cId;
	}
	
	public function getcs(){
		return $this->cs;
	}
	
	protected function cleanString($inStr){
		$retStr = trim($inStr);
		$retStr = $this->conn->real_escape_string($retStr);
		return $retStr;
	}
}
?>