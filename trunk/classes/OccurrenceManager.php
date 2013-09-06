<?php
include_once($serverRoot.'/config/dbconnection.php');

class OccurrenceManager{

	protected $conn;
	protected $taxaArr = Array();
	private $taxaSearchType;
	protected $searchTermsArr = Array();
	protected $localSearchArr = Array();
	protected $useCookies = 1;
	protected $reset = 0;
	protected $dynamicClid;
	private $clName;
	private $collArrIndex = 0;
	
 	public function __construct(){
		$this->conn = MySQLiConnectionFactory::getCon('readonly');
		$this->useCookies = (array_key_exists("usecookies",$_REQUEST)&&$_REQUEST["usecookies"]=="false"?0:1); 
 		if(array_key_exists("reset",$_REQUEST) && $_REQUEST["reset"]){
 			$this->reset();
 		}
 		if($this->useCookies && !$this->reset){
 			$this->readCollCookies();
 		}
 		//Read DB cookies no matter what
		if(array_key_exists("colldbs",$_COOKIE)){
			$this->searchTermsArr["db"] = $_COOKIE["colldbs"];
		}
		elseif(array_key_exists("collsurveyid",$_COOKIE)){
			$this->searchTermsArr["surveyid"] = $_COOKIE["collsurveyid"];
		}
		$this->readRequestVariables();
 	}

	public function __destruct(){
 		if(!($this->conn === false)) $this->conn->close();
	}

	protected function getConnection($conType = "readonly"){
		return MySQLiConnectionFactory::getCon($conType);
	}

	public function reset(){
		global $clientRoot;
		setCookie("colltaxa","",time()-3600,($clientRoot?$clientRoot:'/'));
		setCookie("collsearch","",time()-3600,($clientRoot?$clientRoot:'/'));
		setCookie("collvars","",time()-3600,($clientRoot?$clientRoot:'/'));
 		$this->reset = 1;
		if(array_key_exists("db",$this->searchTermsArr) || array_key_exists("oic",$this->searchTermsArr)){
			//reset all other search terms except maintain the db terms 
			$dbsTemp = "";
			if(array_key_exists("db",$this->searchTermsArr)) $dbsTemp = $this->searchTermsArr["db"];
			$surveyIdTemp = "";
			if(array_key_exists("surveyid",$this->searchTermsArr)) $surveyIdTemp = $this->searchTermsArr["surveyid"];
			unset($this->searchTermsArr);
			if($dbsTemp) $this->searchTermsArr["db"] = $dbsTemp;
			if($surveyIdTemp) $this->searchTermsArr["surveyid"] = $surveyIdTemp;
		}
	}

	private function readCollCookies(){
		if(array_key_exists("colltaxa",$_COOKIE)){
			$collTaxa = $_COOKIE["colltaxa"]; 
			$taxaArr = explode("&",$collTaxa);
			foreach($taxaArr as $value){
				$this->searchTermsArr[substr($value,0,strpos($value,":"))] = substr($value,strpos($value,":")+1);
			}
		}
		if(array_key_exists("collsearch",$_COOKIE)){
			$collSearch = $_COOKIE["collsearch"]; 
			$searArr = explode("&",$collSearch);
			foreach($searArr as $value){
				$this->searchTermsArr[substr($value,0,strpos($value,":"))] = substr($value,strpos($value,":")+1);
			}
		}
		if(array_key_exists("collvars",$_COOKIE)){
			$collVarStr = $_COOKIE["collvars"];
			$varsArr = explode("&",$collVarStr);
			foreach($varsArr as $value){
				if(strpos($value,"dynclid") === 0){
					$this->dynamicClid = substr($value,strpos($value,":")+1);
				}
				elseif(strpos($value,"reccnt") === 0){
					$this->recordCount = substr($value,strpos($value,":")+1);
				}
			}
		}
		return $this->searchTermsArr;
	}
	
	public function getSearchTerms(){
		return $this->searchTermsArr;
	}

	public function getSearchTerm($k){
		if(array_key_exists($k,$this->searchTermsArr)){
			return $this->searchTermsArr[$k];
		}
		else{
			return "";
		}
	}

	protected function getSqlWhere(){
		$sqlWhere = "";
		if(array_key_exists("surveyid",$this->searchTermsArr)){
			//$sqlWhere .= "AND (sol.surveyid IN('".str_replace(";","','",$this->searchTermsArr["surveyid"])."')) ";
			$sqlWhere .= "AND (sol.clid IN('".$this->searchTermsArr["surveyid"]."')) ";
		}
		elseif(array_key_exists("db",$this->searchTermsArr) && $this->searchTermsArr['db']){
			//Do nothing if db = all
			if($this->searchTermsArr['db'] != 'all'){
				if($this->searchTermsArr['db'] == 'allspec'){
					$sqlWhere .= 'AND (o.collid IN(SELECT collid FROM omcollections WHERE colltype = "Preserved Specimens")) ';
				}
				elseif($this->searchTermsArr['db'] == 'allobs'){
					$sqlWhere .= 'AND (o.collid IN(SELECT collid FROM omcollections WHERE colltype IN("General Observations","Observations"))) ';
				}
				else{
					$dbArr = explode(';',$this->searchTermsArr["db"]);
					$dbStr = '';
					if(isset($dbArr[0]) && $dbArr[0]){
						$dbStr = "(o.collid IN(".trim($dbArr[0]).")) ";
					}
					if(isset($dbArr[1]) && $dbArr[1]){
						$dbStr .= ($dbStr?'OR ':'').'(o.CollID IN(SELECT collid FROM omcollcatlink WHERE (ccpk = '.$dbArr[1].'))) ';
					}
					$sqlWhere .= 'AND ('.$dbStr.') ';
				}
			}
		}
		
		if(array_key_exists("taxa",$this->searchTermsArr)){
			$sqlWhereTaxa = "";
			$useThes = (array_key_exists("usethes",$this->searchTermsArr)?$this->searchTermsArr["usethes"]:0);
			$this->taxaSearchType = $this->searchTermsArr["taxontype"];
			$taxaArr = explode(";",trim($this->searchTermsArr["taxa"]));
			//Set scientific name
			$this->taxaArr = Array();
			foreach($taxaArr as $sName){
				$this->taxaArr[trim($sName)] = Array();
			}
			if($this->taxaSearchType == 5){
				//Common name search
				$this->setSciNamesByVerns();
			}
			else{
				if($useThes){ 
					$this->setSynonyms();
				}
			}

			//Build sql
			foreach($this->taxaArr as $key => $valueArray){
				if($this->taxaSearchType == 4){
					$rs1 = $this->conn->query("SELECT tid FROM taxa WHERE (sciname = '".$key."')");
					if($r1 = $rs1->fetch_object()){
						//$sqlWhereTaxa .= "OR (o.tidinterpreted IN(SELECT tid FROM taxstatus WHERE taxauthid = 1 AND hierarchystr LIKE '%,".$r1->tid.",%')) ";
						
						//$sql2 = "SELECT DISTINCT ts.family FROM taxstatus ts ".
						//	"WHERE ts.taxauthid = 1 AND (ts.hierarchystr LIKE '%,".$r1->tid.",%') AND ts.family IS NOT NULL AND ts.family <> '' ";
						$sql2 = 'SELECT DISTINCT t.sciname FROM taxstatus ts INNER JOIN taxa t ON ts.tid = t.tid '.
							'WHERE ts.taxauthid = 1 AND (ts.hierarchystr LIKE "%,'.$r1->tid.',%") AND t.rankid = 140';
						$sqlWhereTaxa .= "OR (o.family IN(".$sql2.")) ";
					}
				}
				else{
					if($this->taxaSearchType == 5){
						$famArr = array();
						if(array_key_exists("families",$valueArray)){
							$famArr = $valueArray["families"];
						}
						if(array_key_exists("tid",$valueArray)){
							$tidArr = $valueArray['tid'];
							$hSqlStr = '';
							foreach($tidArr as $tid){
								$hSqlStr .= 'OR (ts.hierarchystr LIKE "%,'.$tid.',%") ';
							}
							$sql = 'SELECT DISTINCT ts.family FROM taxstatus ts '.
								'WHERE ts.taxauthid = 1 AND ('.substr($hSqlStr,3).')';
							$rs = $this->conn->query($sql);
							while($r = $rs->fetch_object()){
								$famArr[] = $r->family;
							}
						}
						if($famArr){
							$famArr = array_unique($famArr);
							$sqlWhereTaxa .= 'OR (o.family IN("'.implode('","',$famArr).'")) ';
						}
						if(array_key_exists("scinames",$valueArray)){
							foreach($valueArray["scinames"] as $sciName){
								$sqlWhereTaxa .= "OR (o.sciname Like '".$sciName."%') ";
							}
						}
					}
					else{
						if($this->taxaSearchType == 2 || ($this->taxaSearchType == 1 && (strtolower(substr($key,-5)) == "aceae" || strtolower(substr($key,-4)) == "idae"))){
							$sqlWhereTaxa .= "OR (o.family = '".$key."') ";
						}
						if($this->taxaSearchType == 3 || ($this->taxaSearchType == 1 && strtolower(substr($key,-5)) != "aceae" && strtolower(substr($key,-4)) != "idae")){
							$sqlWhereTaxa .= "OR (o.sciname LIKE '".$key."%') ";
						}
					}
					if(array_key_exists("synonyms",$valueArray)){
						$synArr = $valueArray["synonyms"];
						foreach($synArr as $sciName){ 
							if($this->taxaSearchType == 1 || $this->taxaSearchType == 2 || $this->taxaSearchType == 5){
								$sqlWhereTaxa .= "OR (o.family = '".$sciName."') ";
							}
							if($this->taxaSearchType == 2){
								$sqlWhereTaxa .= "OR (o.sciname = '".$sciName."') ";
							}
							else{
			                    $sqlWhereTaxa .= "OR (o.sciname Like '".$sciName."%') ";
			                }
						}
					}
				}
			}
			$sqlWhere .= "AND (".substr($sqlWhereTaxa,3).") ";
		}

		if(array_key_exists("country",$this->searchTermsArr)){
			$countryArr = explode(";",$this->searchTermsArr["country"]);
			$tempArr = Array();
			foreach($countryArr as $value){
				$tempArr[] = "(o.Country = '".trim($value)."')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(" OR ",$countryArr);
		}
		if(array_key_exists("state",$this->searchTermsArr)){
			$stateAr = explode(";",$this->searchTermsArr["state"]);
			$tempArr = Array();
			foreach($stateAr as $value){
				$tempArr[] = "(o.StateProvince LIKE '".trim($value)."%')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(" OR ",$stateAr);
		}
		if(array_key_exists("county",$this->searchTermsArr)){
			$countyArr = explode(";",$this->searchTermsArr["county"]);
			$tempArr = Array();
			foreach($countyArr as $value){
				$tempArr[] = "(o.county LIKE '".trim($value)."%')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(" OR ",$countyArr);
		}
		if(array_key_exists("local",$this->searchTermsArr)){
			$localArr = explode(";",$this->searchTermsArr["local"]);
			$tempArr = Array();
			foreach($localArr as $value){
				$tempArr[] = "(o.municipality LIKE '".trim($value)."%' OR o.Locality LIKE '%".trim($value)."%')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(" OR ",$localArr);
		}
		if(array_key_exists("llbound",$this->searchTermsArr)){
			$llboundArr = explode(";",$this->searchTermsArr["llbound"]);
			if(count($llboundArr) == 4){
				$sqlWhere .= "AND (o.DecimalLatitude BETWEEN ".$llboundArr[1]." AND ".$llboundArr[0]." AND ".
					"o.DecimalLongitude BETWEEN ".$llboundArr[2]." AND ".$llboundArr[3].") ";
				$this->localSearchArr[] = "Lat: >".$llboundArr[1].", <".$llboundArr[0]."; Long: >".$llboundArr[2].", <".$llboundArr[3];
			}
		}
		if(array_key_exists("llpoint",$this->searchTermsArr)){
			$pointArr = explode(";",$this->searchTermsArr["llpoint"]);
			if(count($pointArr) == 3){
				//Formula approximates a bounding box; bounding box is for efficiency, will test practicality of doing a radius query in future  
				$latRadius = $pointArr[2] / 69.1;
				$longRadius = cos($pointArr[0]/57.3)*($pointArr[2]/69.1);
				$lat1 = $pointArr[0] - $latRadius;
				$lat2 = $pointArr[0] + $latRadius;
				$long1 = $pointArr[1] - $longRadius;
				$long2 = $pointArr[1] + $longRadius;
				$sqlWhere .= "AND (o.DecimalLatitude BETWEEN ".$lat1." AND ".$lat2." AND ".
					"o.DecimalLongitude BETWEEN ".$long1." AND ".$long2.") ";
			}
			$this->localSearchArr[] = "Point radius: ".$pointArr[0].", ".$pointArr[1].", within ".$pointArr[2]." miles";
		}
		if(array_key_exists("collector",$this->searchTermsArr)){
			$collectorArr = explode(";",$this->searchTermsArr["collector"]);
			$tempArr = Array();
			foreach($collectorArr as $value){
				$tempArr[] = "(o.recordedBy LIKE '%".trim($value)."%')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(", ",$collectorArr);
		}
		if(array_key_exists("collnum",$this->searchTermsArr)){
			$collNumArr = explode(";",$this->searchTermsArr["collnum"]);
			$rnWhere = '';
			foreach($collNumArr as $v){
				$v = trim($v);
				if($p = strpos($v,' - ')){
					$term1 = trim(substr($v,0,$p));
					$term2 = trim(substr($v,$p+3));
					if(is_numeric($term1) && is_numeric($term2)){
						$rnIsNum = true;
						$rnWhere = 'OR (o.recordnumber BETWEEN '.$term1.' AND '.$term2.')';
					}
					else{
						$catTerm = 'o.recordnumber BETWEEN "'.$term1.'" AND "'.$term2.'"';
						if(strlen($term1) == strlen($term2)) $catTerm .= ' AND length(o.recordnumber) = '.strlen($term2); 
						$rnWhere = 'OR ('.$catTerm.')';
					}
				}
				elseif(is_numeric($v)){
					$rnWhere .= 'OR (o.recordNumber = '.$v.') ';
				}
				else{
					$rnWhere .= 'OR (o.recordNumber = "'.$v.'") ';
				}
			}
			if($rnWhere){
				$sqlWhere .= "AND (".substr($rnWhere,3).") ";
				$this->localSearchArr[] = implode(", ",$collNumArr);
			}
		}
		if(array_key_exists('eventdate1',$this->searchTermsArr)){
			$dateArr = array();
			if(strpos($this->searchTermsArr['eventdate1'],' to ')){
				$dateArr = explode(' to ',$this->searchTermsArr['eventdate1']);
			}
			elseif(strpos($this->searchTermsArr['eventdate1'],' - ')){
				$dateArr = explode(' - ',$this->searchTermsArr['eventdate1']);
			}
			else{
				$dateArr[] = $this->searchTermsArr['eventdate1'];
				if(isset($this->searchTermsArr['eventdate2'])){
					$dateArr[] = $this->searchTermsArr['eventdate2'];
				}
			}
			if($eDate1 = $this->formatDate($dateArr[0])){
				$eDate2 = (count($dateArr)>1?$this->formatDate($dateArr[1]):'');
				if($eDate2){
					$sqlWhere .= 'AND (DATE(o.eventdate) BETWEEN "'.$eDate1.'" AND "'.$eDate2.'") ';
				}
				else{
					if(substr($eDate1,-5) == '00-00'){
						$sqlWhere .= 'AND (o.eventdate LIKE "'.substr($eDate1,0,5).'%") ';
					}
					elseif(substr($eDate1,-2) == '00'){
						$sqlWhere .= 'AND (o.eventdate LIKE "'.substr($eDate1,0,8).'%") ';
					}
					else{
						$sqlWhere .= 'AND (DATE(o.eventdate) = "'.$eDate1.'") ';
					}
				}
			}
			$this->localSearchArr[] = $this->searchTermsArr['eventdate1'].(isset($this->searchTermsArr['eventdate2'])?' to '.$this->searchTermsArr['eventdate2']:'');
		}
		if(array_key_exists('catnum',$this->searchTermsArr)){
			$catStr = $this->searchTermsArr['catnum'];
			$isOccid = false;
			if(substr($catStr,0,5) == 'occid'){
				$catStr = trim(substr($catStr,5));
				$isOccid = true;
			}
			$catArr = explode(',',str_replace(';',',',$catStr));
			$betweenFrag = array();
			$inFrag = array();
			foreach($catArr as $v){
				if($p = strpos($v,' - ')){
					$term1 = trim(substr($v,0,$p));
					$term2 = trim(substr($v,$p+3));
					if(is_numeric($term1) && is_numeric($term2)){
						if($isOccid){
							$betweenFrag[] = '(o.occid BETWEEN '.$term1.' AND '.$term2.')';
						}
						else{
							$betweenFrag[] = '(o.catalogNumber BETWEEN '.$term1.' AND '.$term2.')';
						} 
					}
					else{
						$catTerm = 'o.catalogNumber BETWEEN "'.$term1.'" AND "'.$term2.'"';
						if(strlen($term1) == strlen($term2)) $catTerm .= ' AND length(o.catalogNumber) = '.strlen($term2); 
						$betweenFrag[] = '('.$catTerm.')';
					}
				}
				else{
					$vStr = trim($v);
					$inFrag[] = $vStr;
					if(is_numeric($vStr) && substr($vStr,0,1) == '0'){
						$inFrag[] = ltrim($vStr,0);
					}
				}
			}
			$catWhere = '';
			if($betweenFrag){
				$catWhere .= 'OR '.implode(' OR ',$betweenFrag);
			}
			if($inFrag){
				if($isOccid){
					$catWhere .= 'OR (o.occid IN('.implode(',',$inFrag).')) ';
				}
				else{
					$catWhere .= 'OR (o.catalogNumber IN("'.implode('","',$inFrag).'")) ';
				} 
			}
			$sqlWhere .= 'AND ('.substr($catWhere,3).') ';
			$this->localSearchArr[] = $this->searchTermsArr['catnum'];
		}
		if(array_key_exists("typestatus",$this->searchTermsArr)){
			$typestatusArr = explode(";",$this->searchTermsArr["typestatus"]);
			$tempArr = Array();
			foreach($typestatusArr as $value){
				$tempArr[] = "(o.typestatus LIKE '%".trim($value)."%')";
			}
			$sqlWhere .= "AND (".implode(" OR ",$tempArr).") ";
			$this->localSearchArr[] = implode(", ",$typestatusArr);
		}
		if(array_key_exists("clid",$this->searchTermsArr)){
			$clid = $this->searchTermsArr["clid"];
			$clSql = ""; 
			if($clid){
				$sql = 'SELECT dynamicsql, name FROM fmchecklists WHERE (clid = '.$clid.')';
				$result = $this->conn->query($sql);
				if($row = $result->fetch_object()){
					$clSql = $row->dynamicsql;
					$this->clName = $row->name;
				}
				if($clSql){
					$sqlWhere .= "AND (".$clSql.") ";
					$this->localSearchArr[] = "SQL: ".$clSql;
				}
			}
		}
		if(array_key_exists("sql",$this->searchTermsArr)){
			$sqlTerm = $this->searchTermsArr["sql"];
			$sqlWhere .= "AND (".$clSql.") ";
			$this->localSearchArr[] = "SQL: ".$clSql;
		}
		$retStr = '';
		if($sqlWhere){
			$retStr = 'WHERE '.substr($sqlWhere,4);
		}
		else{
			//Make the sql valid, but return nothing
			$retStr = 'WHERE o.collid = -1 ';
		}
		//echo $retStr;
		return $retStr; 
	}

	private function formatDate($inDate){
		$inDate = trim($inDate);
		$retDate = '';
		$y=''; $m=''; $d='';
		if(preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/',$inDate)){
			$dateTokens = explode('-',$inDate);
			$y = $dateTokens[0];
			$m = $dateTokens[1];
			$d = $dateTokens[2];
		}
		elseif(preg_match('/^\d{1,2}\/*\d{0,2}\/\d{2,4}$/',$inDate)){
			//dd/mm/yyyy
			$dateTokens = explode('/',$inDate);
			$m = $dateTokens[0];
			if(count($dateTokens) == 3){
				$d = $dateTokens[1];
				$y = $dateTokens[2];
			}
			else{
				$d = '00';
				$y = $dateTokens[1];
			}
		}
		elseif(preg_match('/^\d{0,2}\s*\D+\s*\d{2,4}$/',$inDate)){
			$dateTokens = explode(' ',$inDate);
			if(count($dateTokens) == 3){
				$y = $dateTokens[2];
				$mText = substr($dateTokens[1],0,3);
				$d = $dateTokens[0];
			}
			else{
				$y = $dateTokens[1];
				$mText = substr($dateTokens[0],0,3);
				$d = '00';
			}
			$mText = strtolower($mText);
			$mNames = Array("ene"=>1,"jan"=>1,"feb"=>2,"mar"=>3,"abr"=>4,"apr"=>4,"may"=>5,"jun"=>6,"jul"=>7,"ago"=>8,"aug"=>8,"sep"=>9,"oct"=>10,"nov"=>11,"dic"=>12,"dec"=>12);
			$m = $mNames[$mText];
		}
		elseif(preg_match('/^\s*\d{4}\s*$/',$inDate)){
			$retDate = $inDate.'-00-00';
		}
		elseif($dateObj = strtotime($inDate)){
			$retDate = date('Y-m-d',$dateObj);
		}
		if(!$retDate && $y){
			if(strlen($y) == 2){
				if($y < 20){
					$y = "20".$y;
				}
				else{
					$y = "19".$y;
				}
			}
			if(strlen($m) == 1){
				$m = '0'.$m;
			}
			if(strlen($d) == 1){
				$d = '0'.$d;
			}
			$retDate = $y.'-'.$m.'-'.$d;
		}
		return $retDate;
	}

    protected function setSciNamesByVerns(){
        $sql = "SELECT DISTINCT v.VernacularName, t.tid, t.sciname, ts.family, t.rankid ".
            "FROM (taxstatus ts INNER JOIN taxavernaculars v ON ts.TID = v.TID) ".
            "INNER JOIN taxa t ON t.TID = ts.tidaccepted ";
    	$whereStr = "";
		foreach($this->taxaArr as $key => $value){
			$whereStr .= "OR v.VernacularName = '".$key."' ";
		}
		$sql .= "WHERE (ts.taxauthid = 1) AND (".substr($whereStr,3).") ORDER BY t.rankid LIMIT 20";
		//echo "<div>sql: ".$sql."</div>";
		$result = $this->conn->query($sql);
		if($result->num_rows){
			while($row = $result->fetch_object()){
				$vernName = strtolower($row->VernacularName);
				if($row->rankid < 140){
					$this->taxaArr[$vernName]["tid"][] = $row->tid;
				}
				elseif($row->rankid == 140){
					$this->taxaArr[$vernName]["families"][] = $row->sciname;
				}
				else{
					$this->taxaArr[$vernName]["scinames"][] = $row->sciname;
				}
			}
		}
		else{
			$this->taxaArr["no records"]["scinames"][] = "no records";
		}
		$result->close();
    }
    
    protected function setSynonyms(){
    	foreach($this->taxaArr as $key => $value){
    		if(array_key_exists("scinames",$value) && !in_array("no records",$value["scinames"])){
    			$this->taxaArr = $value["scinames"];
    			foreach($this->taxaArr as $sciname){
	        		$sql = "call ReturnSynonyms('".$sciname."',1)";
	        		$result = $this->conn->query($sql);
	        		while($row = $result->fetch_object()){
	        			$this->taxaArr[$key]["synonyms"][] = $row->sciname;
	        		}
        			$result->close();
    			}
    		}
    		else{
    			$sql = "call ReturnSynonyms('".$key."',1)";
    			$result = $this->conn->query($sql);
        		while($row = $result->fetch_object()){
        			$this->taxaArr[$key]["synonyms"][] = $row->sciname;
        		}
        		$result->close();
        		$this->conn->next_result();
    		}
    	}
    }
	
	public function getFullCollectionList($catId = ""){
		$retArr = array();
		//Set collection array
		$collIdArr = array();
		$catIdArr = array();
		if(isset($this->searchTermsArr['db']) && array_key_exists('db',$this->searchTermsArr)){
			$cArr = explode(';',$this->searchTermsArr['db']);
			$collIdArr = explode(',',$cArr[0]);
			if(isset($cArr[1])) $catIdStr = $cArr[1];
		}
		//Set collections
		$sql = 'SELECT c.collid, c.institutioncode, c.collectioncode, c.collectionname, c.icon, c.colltype, ccl.ccpk, cat.catagory '.
			'FROM omcollections c LEFT JOIN omcollcatlink ccl ON c.collid = ccl.collid '.
			'LEFT JOIN omcollcatagories cat ON ccl.ccpk = cat.ccpk '.
			'ORDER BY ccl.sortsequence, cat.catagory, c.sortseq, c.CollectionName ';
		//echo "<div>SQL: ".$sql."</div>";
		$result = $this->conn->query($sql);
		while($r = $result->fetch_object()){
			$collType = (stripos($r->colltype, "observation") !== false?'obs':'spec');
			if($r->ccpk){
				if(!isset($retArr[$collType]['cat'][$r->ccpk]['name'])){
					$retArr[$collType]['cat'][$r->ccpk]['name'] = $r->catagory;
					//if(in_array($r->ccpk,$catIdArr)) $retArr[$collType]['cat'][$catId]['isselected'] = 1;
					//if(in_array($r->ccpk,$catIdArr)) $retArr[$collType]['cat'][$catId]['icon'] = $r->icon;
				}
				$retArr[$collType]['cat'][$r->ccpk][$r->collid]["instcode"] = $r->institutioncode;
				$retArr[$collType]['cat'][$r->ccpk][$r->collid]["collcode"] = $r->collectioncode;
				$retArr[$collType]['cat'][$r->ccpk][$r->collid]["collname"] = $r->collectionname;
				$retArr[$collType]['cat'][$r->ccpk][$r->collid]["icon"] = $r->icon;
			}
			else{
				$retArr[$collType]['coll'][$r->collid]["instcode"] = $r->institutioncode;
				$retArr[$collType]['coll'][$r->collid]["collcode"] = $r->collectioncode;
				$retArr[$collType]['coll'][$r->collid]["collname"] = $r->collectionname;
				$retArr[$collType]['coll'][$r->collid]["icon"] = $r->icon;
			}
		}
		$result->close();
		//Modify sort so that default catid is first
		if(isset($retArr['spec']['cat'][$catId])){
			$targetArr = $retArr['spec']['cat'][$catId];
			unset($retArr['spec']['cat'][$catId]);
			array_unshift($retArr['spec']['cat'],$targetArr);
		} 
		elseif(isset($retArr['obs']['cat'][$catId])){
			$targetArr = $retArr['obs']['cat'][$catId];
			unset($retArr['obs']['cat'][$catId]);
			array_unshift($retArr['obs']['cat'],$targetArr);
		}
		return $retArr;
	}

	public function outputFullCollArr($occArr,$defaultCatid = 0){
		$collCnt = 0;
		if(isset($occArr['cat'])){
			$catArr = $occArr['cat'];
			?>
		    <div style="float:right;margin-top:80px;">
	        	<input type="image" src='../images/next.jpg'
	                onmouseover="javascript:this.src = '../images/next_rollover.jpg';" 
	                onmouseout="javascript:this.src = '../images/next.jpg';"
	                title="Click button to advance to the next step" />
	    	</div>
			<table>
			<?php 
			foreach($catArr as $catid => $catArr){
				$name = $catArr["name"];
				unset($catArr["name"]);
				$idStr = $this->collArrIndex.'-'.$catid;
				?>
				<tr>
					<td>
						<a href="#" onclick="toggleCat('<?php echo $idStr; ?>');return false;">
							<img id="plus-<?php echo $idStr; ?>" src="../images/plus.gif" style="<?php echo ($defaultCatid==$catid?'display:none;':'') ?>" /><img id="minus-<?php echo $idStr; ?>" src="../images/minus.gif" style="<?php echo ($defaultCatid==$catid?'':'display:none;') ?>" />
						</a>
					</td>
					<td>
						<input id="cat<?php echo $idStr; ?>Input" name="cat[]" value="<?php echo $catid; ?>" type="checkbox" onclick="selectAllCat(this,'cat-<?php echo $idStr; ?>')" /> 
					</td>
					<td>
			    		<span style='text-decoration:none;color:black;font-size:130%;font-weight:bold;'>
				    		<a href = 'misc/collprofiles.php?catid=<?php echo $catid; ?>'><?php echo $name; ?></a>
				    	</span>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<div id="cat-<?php echo $idStr; ?>" style="<?php echo ($defaultCatid==$catid?'':'display:none;') ?>margin:10px 0px;">
							<table style="margin-left:15px;">
						    	<?php 
								foreach($catArr as $collid => $collName2){
						    		?>
						    		<tr>
										<td>
											<?php 
											if($collName2["icon"]){
												$cIcon = (substr($collName2["icon"],0,6)=='images'?'../':'').$collName2["icon"]; 
												?>
												<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>'>
													<img src="<?php echo $cIcon; ?>" style="border:0px;width:30px;height:30px;" />
												</a>
										    	<?php
											}
										    ?>
										</td>
										<td style="padding:6px">
								    		<input name="db[]" value="<?php echo $collid; ?>" type="checkbox" class="cat-<?php echo $idStr; ?>" onclick="unselectCat('cat<?php echo $catid; ?>Input')" /> 
										</td>
										<td style="padding:6px">
								    		<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>' style='text-decoration:none;color:black;font-size:120%;'>
								    			<?php echo $collName2["collname"]." (".$collName2["instcode"].")"; ?>
								    		</a>
								    		<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>' style='font-size:75%;'>
								    			more info
								    		</a>
										</td>
									</tr>
						    		<?php 
					    			$collCnt++; 
						    	}
						    	?>
						    </table>
						</div>
					</td>
				</tr>
				<?php 
			}
			?>
			</table>
			<?php 
		}
		if(isset($occArr['coll'])){
			$collArr = $occArr['coll'];
			?>
		    <div style="float:right;margin-top:80px;">
	        	<input type="image" src='../images/next.jpg'
	                onmouseover="javascript:this.src = '../images/next_rollover.jpg';" 
	                onmouseout="javascript:this.src = '../images/next.jpg';"
	                title="Click button to advance to the next step" />
	    	</div>
			<table>
			<?php 
			foreach($collArr as $collid => $cArr){
				?>
				<tr>
					<td>
						<?php 
						if($cArr["icon"]){
							$cIcon = (substr($cArr["icon"],0,6)=='images'?'../':'').$cArr["icon"]; 
							?>
							<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>'>
								<img src="<?php echo $cIcon; ?>" style="border:0px;width:30px;height:30px;" />
							</a>
					    	<?php
						}
					    ?>
					    &nbsp;
					</td>
					<td style="padding:6px;">
			    		<input name="db[]" value="<?php echo $collid; ?>" type="checkbox" onclick="uncheckAll(this.form)" /> 
					</td>
					<td style="padding:6px">
			    		<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>' style='text-decoration:none;color:black;font-size:120%;'>
			    			<?php echo $cArr["collname"]." (".$cArr["instcode"].")"; ?>
			    		</a>
			    		<a href = 'misc/collprofiles.php?collid=<?php echo $collid; ?>' style='font-size:75%;'>
			    			more info
			    		</a>
				    </td>
				</tr>
				<?php
				$collCnt++; 
			}
			?>
			</table>
			<?php 
		}
		$this->collArrIndex++;
	}

	public function getCollectionList($collIdArr){
		$retArr = array();
		$sql = 'SELECT c.collid, c.institutioncode, c.collectioncode, c.collectionname, c.icon, cat.catagory '.
			'FROM omcollections c LEFT JOIN omcollcatlink l ON c.collid = l.collid '.
			'LEFT JOIN omcollcatagories cat ON l.ccpk = cat.ccpk '.
			'WHERE c.collid IN('.implode(',',$collIdArr).') ';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retArr[$r->collid]['instcode'] = $r->institutioncode;
			$retArr[$r->collid]['collcode'] = $r->collectioncode;
			$retArr[$r->collid]['name'] = $r->collectionname;
			$retArr[$r->collid]['icon'] = $r->icon;
			$retArr[$r->collid]['catagory'] = $r->catagory;
		}
		$rs->free();
		return $retArr;
	}
	
	public function getOccurVoucherProjects(){
		$returnArr = Array();
		$sql = 'SELECT p.projname, cl.clid, cl.name '.
			'FROM (fmprojects p INNER JOIN fmchklstprojlink pl ON p.pid = pl.pid) '. 
			'INNER JOIN fmchecklists cl ON pl.clid = cl.clid '.
			'INNER JOIN fmvouchers v ON cl.clid = v.clid '.
			'WHERE p.occurrencesearch = 1 '. 
			'ORDER BY p.sortsequence, p.projname, cl.name'; 
		//echo "<div>$sql</div>";
		$rs = $this->conn->query($sql);
		while($row = $rs->fetch_object()){
			$returnArr[$row->projname][$row->clid] = $row->name;
		}
		$rs->close();
		return $returnArr;
	}
	
	public function getSurveys(){
		$returnArr = Array();
		$sql = "SELECT p.projname, s.surveyid, s.projectname ".
			"FROM (fmprojects p INNER JOIN omsurveyprojlink spl ON p.pid = spl.pid) ".
			"INNER JOIN omsurveys s ON spl.surveyid = s.surveyid ".
			"WHERE p.occurrencesearch = 1 ".
			"ORDER BY p.sortsequence, p.projname, s.projectname"; 
		//echo "<div>$sql</div>";
		$rs = $this->conn->query($sql);
		while($row = $rs->fetch_object()){
			$returnArr[$row->projname][$row->surveyid] = $row->projectname;
		}
		$rs->close();
		return $returnArr;
	}
	
	public function getDatasetSearchStr(){
		$retStr ="";
		if(array_key_exists("surveyid",$this->searchTermsArr)){
			$retStr = $this->getSurveyStr();
		}
		else{
			if(!array_key_exists('db',$this->searchTermsArr) || $this->searchTermsArr['db'] == 'all'){
				$retStr = "All Collections";
			}
			elseif($this->searchTermsArr['db'] == 'allspec'){
				$retStr = "All Specimen Collections";
			}
			elseif($this->searchTermsArr['db'] == 'allobs'){
				$retStr = "All Observation Projects";
			}
			else{
				$cArr = explode(';',$this->searchTermsArr['db']);
				if($cArr[0]){
					$sql = 'SELECT collid, CONCAT_WS("-",institutioncode,collectioncode) as instcode '.
						'FROM omcollections WHERE collid IN('.$cArr[0].') ORDER BY institutioncode,collectioncode';
					$rs = $this->conn->query($sql);
					while($r = $rs->fetch_object()){
						$retStr .= '; '.$r->instcode;
					}
					$rs->free();
				}
				if(isset($cArr[1]) && $cArr[1]){
					$sql = 'SELECT ccpk, catagory FROM omcollcatagories WHERE ccpk IN('.$cArr[1].') ORDER BY catagory';
					$rs = $this->conn->query($sql);
					while($r = $rs->fetch_object()){
						$retStr .= '; '.$r->catagory;
					}
					$rs->free();
				}
				$retStr = substr($retStr,2);
			}
		}
		return $retStr;
	}
	
	private function getSurveyStr(){
		$returnStr = "";
		$sql = "SELECT projectname FROM omsurveys WHERE (surveyid IN(".str_replace(";",",",$this->searchTermsArr["surveyid"]).")) ";
		$rs = $this->conn->query($sql);
		while($row = $rs->fetch_object()){
			$returnStr .= " ;".$row->projectname; 
		}
		return substr($returnStr,2);
	}

	public function getTaxaSearchStr(){
		$returnArr = Array();
		foreach($this->taxaArr as $taxonName => $taxonArr){
			$str = $taxonName;
			if(array_key_exists("sciname",$taxonArr)){
				$str .= " => ".implode(",",$taxonArr["sciname"]);
			}
			if(array_key_exists("synonyms",$taxonArr)){
				$str .= " (".implode(",",$taxonArr["synonyms"]).")";
			}
			$returnArr[] = $str;
		}
		return implode("; ", $returnArr);
	}
	
	public function getLocalSearchStr(){
		return implode("; ", $this->localSearchArr);
	}
	
	public function getTaxonAuthorityList(){
		$taxonAuthorityList = Array();
		$sql = "SELECT ta.taxauthid, ta.name FROM taxauthority ta WHERE (ta.isactive <> 0)";
		$result = $this->conn->query($sql);
		while($row = $result->fetch_object()){
			$taxonAuthorityList[$row->taxauthid] = $row->name;
		}
		return $taxonAuthorityList;
	}

	private function readRequestVariables(){
		global $clientRoot;
		//Search will be confinded to a surveyid, collid, catid, or will remain open to all collection
		if(array_key_exists("surveyid",$_REQUEST)){
			//Limit by servey id 
			$surveyidArr = $_REQUEST["surveyid"];
			if(is_string($surveyidArr)) $surveyidArr = Array($surveyidArr); 
		 	$surveyidStr = implode(",",$surveyidArr);
		 	if($this->useCookies) setCookie("collsurveyid",$surveyidStr,0,($clientRoot?$clientRoot:'/'));
			$this->searchTermsArr["surveyid"] = $surveyidStr;
			//Since survey ID is being searched, clear colldbs
			setCookie("colldbs","",time()-3600,($clientRoot?$clientRoot:'/'));
		}
		else{
			//Limit collids and/or catids
			$dbStr = '';
			if(array_key_exists("db",$_REQUEST)){
				$dbs = $_REQUEST["db"];
				if(is_string($dbs)){
					$dbStr = $dbs.';';
				}
				else{
					$dbStr = $this->conn->real_escape_string(implode(',',$dbs)).';';
				}
				if(strpos($dbStr,'allspec') !== false){
					$dbStr = 'allspec';
				}
				elseif(strpos($dbStr,'allobs') !== false){
					$dbStr = 'allobs';
				}
				elseif(strpos($dbStr,'all') !== false){
					$dbStr = 'all';
				}
			}
			if(substr($dbStr,0,3) != 'all' && array_key_exists('cat',$_REQUEST)){
				$catArr = array();
				$catid = $_REQUEST['cat'];
				if(is_string($catid)){
					$catArr = Array($catid);
				}
				else{
					$catArr = $catid;
				}
				if(!$dbStr) $dbStr = ';';
				$dbStr .= $this->conn->real_escape_string(implode(",",$catArr));
			}

			if($dbStr){
				if($this->useCookies) setCookie("colldbs",$dbStr,0,($clientRoot?$clientRoot:'/'));
				$this->searchTermsArr["db"] = $dbStr;
			}
			//Since coll IDs are being searched, clear survey ids
			setCookie("collsurveyid","",time()-3600,($clientRoot?$clientRoot:'/'));
		}
		if(array_key_exists("taxa",$_REQUEST)){
			$taxa = $this->conn->real_escape_string($_REQUEST["taxa"]);
			$searchType = array_key_exists("type",$_REQUEST)?$this->conn->real_escape_string($_REQUEST["type"]):1;
			if($taxa){
				$taxaStr = "";
				if(is_numeric($taxa)){
					$sql = "SELECT t.sciname ". 
						"FROM taxa t ".
						"WHERE (t.tid = ".$taxa.')';
					$rs = $this->conn->query($sql);
					while($row = $rs->fetch_object()){
						$taxaStr = $row->sciname;
					}
					$rs->close();
				}
				else{
					$taxaStr = str_replace(",",";",$taxa);
					$taxaArr = explode(";",$taxaStr);
					foreach($taxaArr as $key => $sciName){
						$snStr = trim($sciName);
						if($searchType != 5) $snStr = ucfirst($snStr);
						$taxaArr[$key] = $snStr;
					}
					$taxaStr = implode(";",$taxaArr);
				}
				$collTaxa = "taxa:".$taxaStr;
				$this->searchTermsArr["taxa"] = $taxaStr;
				$useThes = array_key_exists("thes",$_REQUEST)?$this->conn->real_escape_string($_REQUEST["thes"]):0; 
				if($useThes){
					$collTaxa .= "&usethes:true";
					$this->searchTermsArr["usethes"] = true;
				}
				else{
					$this->searchTermsArr["usethes"] = false;
				}
				if($searchType){
					$collTaxa .= "&taxontype:".$searchType;
					$this->searchTermsArr["taxontype"] = $searchType;
				}
				if($this->useCookies) setCookie("colltaxa",$collTaxa,0,($clientRoot?$clientRoot:'/'));
			}
			else{
				if($this->useCookies) setCookie("colltaxa","",time()-3600,($clientRoot?$clientRoot:'/'));
				unset($this->searchTermsArr["taxa"]);
			}
		}
		$searchArr = Array();
		$searchFieldsActivated = false;
		if(array_key_exists("country",$_REQUEST)){
			$country = $this->conn->real_escape_string($_REQUEST["country"]);
			if($country){
				$str = str_replace(",",";",$country);
				if(stripos($str, "USA") !== false && stripos($str, "United States") === false){
					$str .= ";United States";
				}
				elseif(stripos($str, "United States") !== false && stripos($str, "USA") === false){
					$str .= ";USA";
				}
				$searchArr[] = "country:".$str;
				$this->searchTermsArr["country"] = $str;
			}
			else{
				unset($this->searchTermsArr["country"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("state",$_REQUEST)){
			$state = $this->conn->real_escape_string($_REQUEST["state"]);
			if($state){
				$str = str_replace(",",";",$state);
				$searchArr[] = "state:".$str;
				$this->searchTermsArr["state"] = $str;
			}
			else{
				unset($this->searchTermsArr["state"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("county",$_REQUEST)){
			$county = $this->conn->real_escape_string($_REQUEST["county"]);
			$county = str_ireplace(" Co.","",$county);
			$county = str_ireplace(" County","",$county);
			if($county){
				$str = str_replace(",",";",$county);
				$searchArr[] = "county:".$str;
				$this->searchTermsArr["county"] = $str;
			}
			else{
				unset($this->searchTermsArr["county"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("local",$_REQUEST)){
			$local = $this->conn->real_escape_string(trim($_REQUEST["local"]));
			if($local){
				$str = str_replace(",",";",$local);
				$searchArr[] = "local:".$str;
				$this->searchTermsArr["local"] = $str;
			}
			else{
				unset($this->searchTermsArr["local"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("collector",$_REQUEST)){
			$collector = $this->conn->real_escape_string(trim($_REQUEST["collector"]));
			if($collector){
				$str = str_replace(",",";",$collector);
				$searchArr[] = "collector:".$str;
				$this->searchTermsArr["collector"] = $str;
			}
			else{
				unset($this->searchTermsArr["collector"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("collnum",$_REQUEST)){
			$collNum = $this->conn->real_escape_string(trim($_REQUEST["collnum"]));
			if($collNum){
				$str = str_replace(",",";",$collNum);
				$searchArr[] = "collnum:".$str;
				$this->searchTermsArr["collnum"] = $str;
			}
			else{
				unset($this->searchTermsArr["collnum"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("eventdate1",$_REQUEST)){
			if($eventDate = $this->conn->real_escape_string(trim($_REQUEST["eventdate1"]))){
				$searchArr[] = "eventdate1:".$eventDate;
				$this->searchTermsArr["eventdate1"] = $eventDate;
				if(array_key_exists("eventdate2",$_REQUEST)){
					if($eventDate2 = $this->conn->real_escape_string(trim($_REQUEST["eventdate2"]))){
						if($eventDate2 != $eventDate){
							$searchArr[] = "eventdate2:".$eventDate2;
							$this->searchTermsArr["eventdate2"] = $eventDate2;
						}
					}
					else{
						unset($this->searchTermsArr["eventdate2"]);
					}
				}
			}
			else{
				unset($this->searchTermsArr["eventdate1"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("catnum",$_REQUEST)){
			$catNum = $this->conn->real_escape_string(trim($_REQUEST["catnum"]));
			if($catNum){
				$str = str_replace(",",";",$catNum);
				$searchArr[] = "catnum:".$str;
				$this->searchTermsArr["catnum"] = $str;
			}
			else{
				unset($this->searchTermsArr["catnum"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("typestatus",$_REQUEST)){
			$typestatus = $this->conn->real_escape_string(trim($_REQUEST["typestatus"]));
			if($typestatus){
				$str = str_replace(",",";",$typestatus);
				$searchArr[] = "typestatus:".$str;
				$this->searchTermsArr["typestatus"] = $str;
			}
			else{
				unset($this->searchTermsArr["typestatus"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("clid",$_REQUEST)){
			$clid = $this->conn->real_escape_string($_REQUEST["clid"]);
			$searchArr[] = "clid:".$clid;
			$this->searchTermsArr["clid"] = $clid;
			$searchFieldsActivated = true;
		}
		$latLongArr = Array();
		if(array_key_exists("upperlat",$_REQUEST)){
			$upperLat = $this->conn->real_escape_string($_REQUEST["upperlat"]);
			if($upperLat || $upperLat === "0") $latLongArr[] = $upperLat;
		
			$bottomlat = $this->conn->real_escape_string($_REQUEST["bottomlat"]);
			if($bottomlat || $bottomlat === "0") $latLongArr[] = $bottomlat;
		
			$leftLong = $this->conn->real_escape_string($_REQUEST["leftlong"]);
			if($leftLong || $leftLong === "0") $latLongArr[] = $leftLong;
		
			$rightlong = $this->conn->real_escape_string($_REQUEST["rightlong"]);
			if($rightlong || $rightlong === "0") $latLongArr[] = $rightlong;

			if(count($latLongArr) == 4){
				$searchArr[] = "llbound:".implode(";",$latLongArr);
				$this->searchTermsArr["llbound"] = implode(";",$latLongArr);
			}
			else{
				unset($this->searchTermsArr["llbound"]);
			}
			$searchFieldsActivated = true;
		}
		if(array_key_exists("pointlat",$_REQUEST)){
			$pointLat = $this->conn->real_escape_string($_REQUEST["pointlat"]);
			if($pointLat || $pointLat === "0") $latLongArr[] = $pointLat;
			
			$pointLong = $this->conn->real_escape_string($_REQUEST["pointlong"]);
			if($pointLong || $pointLong === "0") $latLongArr[] = $pointLong;
		
			$radius = $this->conn->real_escape_string($_REQUEST["radius"]);
			if($radius) $latLongArr[] = $radius;
			if(count($latLongArr) == 3){
				$searchArr[] = "llpoint:".implode(";",$latLongArr);
				$this->searchTermsArr["llpoint"] = implode(";",$latLongArr);
			}
			else{
				unset($this->searchTermsArr["llpoint"]);
			}
			$searchFieldsActivated = true;
		}

		$searchStr = implode("&",$searchArr);
		if($searchStr){
			if($this->useCookies) setCookie("collsearch",$searchStr,0,($clientRoot?$clientRoot:'/'));
		}
		elseif($searchFieldsActivated){
			if($this->useCookies) setCookie("collsearch","",time()-3600,($clientRoot?$clientRoot:'/'));
		}
	}
	
	public function getUseCookies(){
		return $this->useCookies;
	}
	
	public function getClName(){
		return $this->clName;
	}

	protected function cleanOutStr($str){
		$newStr = str_replace('"',"&quot;",$str);
		$newStr = str_replace("'","&apos;",$newStr);
		//$newStr = $this->conn->real_escape_string($newStr);
		return $newStr;
	}

	protected function cleanInStr($str){
		$newStr = trim($str);
		$newStr = preg_replace('/\s\s+/', ' ',$newStr);
		$newStr = $this->conn->real_escape_string($newStr);
		return $newStr;
	}
}
?>
