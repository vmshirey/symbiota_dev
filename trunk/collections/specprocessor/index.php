<?php
include_once('../../config/symbini.php');
include_once($serverRoot.'/classes/SpecProcessorManager.php');
include_once($serverRoot.'/classes/OccurrenceCrowdSource.php');
include_once($serverRoot.'/classes/ImageBatchProcessor.php');

header("Content-Type: text/html; charset=".$charset);

if(!$SYMB_UID) header('Location: ../../profile/index.php?refurl=../collections/specprocessor/index.php?'.$_SERVER['QUERY_STRING']);

$action = array_key_exists('submitaction',$_REQUEST)?$_REQUEST['submitaction']:'';
$collid = array_key_exists('collid',$_REQUEST)?$_REQUEST['collid']:0;
$spprId = array_key_exists('spprid',$_REQUEST)?$_REQUEST['spprid']:0;
//NLP variables
$spNlpId = array_key_exists('spnlpid',$_REQUEST)?$_REQUEST['spnlpid']:0;

$tabIndex = array_key_exists("tabindex",$_REQUEST)?$_REQUEST["tabindex"]:0; 

$specManager= new SpecProcessorManager();

$specManager->setCollId($collid);
$specManager->setSpprId($spprId);

$isEditor = false;
if($isAdmin || (array_key_exists("CollAdmin",$userRights) && in_array($collid,$userRights["CollAdmin"]))){
 	$isEditor = true;
}

$status = "";
if($isEditor){
	if($action == 'Add New Image Project'){
		$specManager->addProject($_REQUEST);
	}
	elseif($action == 'Save Image Project'){
		$specManager->editProject($_REQUEST);
	}
	elseif($action == 'Delete Image Project'){
		$specManager->deleteProject($_REQUEST['sppriddel']);
	}
	elseif($action == 'addtoqueue'){
		$csManager = new OccurrenceCrowdSource();
		$csManager->setCollid($collid);
		$statusStr = $csManager->addToQueue();
		$action = '';
	}
	elseif($action == 'Edit Crowdsource Project'){
		$omcsid = $_POST['omcsid'];
		$csManager = new OccurrenceCrowdSource();
		$csManager->setCollid($collid);
		$statusStr = $csManager->editProject($omcsid,$_POST['instr'],$_POST['url']);
	}
	elseif($action == 'dlnoimg'){
		$specManager->downloadReportData($action);
		exit;
	}
	elseif($action == 'unprocnoimg'){
		$specManager->downloadReportData($action);
		exit;
	}
	elseif($action == 'noskel'){
		$specManager->downloadReportData($action);
		exit;
	}
}
?>
<html>
	<head>
		<title>Specimen Processor Control Panel</title>
		<link rel="stylesheet" href="<?php echo $clientRoot; ?>/css/main.css" type="text/css" />
		<link type="text/css" href="../../css/jquery-ui.css" rel="Stylesheet" />	
		<script type="text/javascript" src="../../js/jquery.js"></script>
		<script type="text/javascript" src="../../js/jquery-ui.js"></script>
		<script type="text/javascript" src="../../js/symb/collections.occureditorshare.js?ver=131106"></script>
		<script language=javascript>
			$(document).ready(function() {
				$('#tabs').tabs({
					select: function(event, ui) {
						return true;
					},
					active: <?php echo $tabIndex; ?>
					//,spinner: 'Loading...'
				});

			});
		</script>
	</head>
	<body>
		<?php
		$displayLeftMenu = false;
		include($serverRoot.'/header.php');
		if(isset($collections_specprocessor_indexCrumbs)){
			if($collections_specprocessor_indexCrumbs){
				echo "<div class='navpath'>";
				echo "<a href='../../index.php'>Home</a> &gt;&gt; ";
				echo $collections_specprocessor_indexCrumbs;
				echo " <b>Specimen Processor Control Panel</b>";
				echo "</div>";
			}
		}
		else{
			echo '<div class="navpath">';
			echo '<a href="../../index.php">Home</a> &gt;&gt; ';
			echo '<a href="../misc/collprofiles.php?collid='.$collid.'&emode=1">Collection Control Panel</a> &gt;&gt; ';
			echo '<b>Specimen Processor Control Panel</b>';
			echo '</div>';
		}
		?>
		<!-- This is inner text! -->
		<div id="innertext">
			<h2><?php echo $specManager->getCollectionName(); ?></h2>
			<?php
			$specManager->setProjVariables(); 
			if($action == 'Upload ABBYY File'){
				$statusArr = $specManager->loadLabelFile();
				if($statusArr){
					$status = '<div style="padding:15px;"><ul><li>'.implode('</li><li>',$statusArr).'</li></ul></div>';
				}
			}
			elseif($action == 'Process Images'){
				echo '<div style="padding:15px;">'."\n";
				$imageProcessor = new ImageBatchProcessor();

				$imageProcessor->setLogMode(1);
				$imageProcessor->initProcessor();
				$imageProcessor->setCollArr(array($collid => array('pmterm' => $specManager->getSpecKeyPattern())));
				$imageProcessor->setDbMetadata(1);
				$imageProcessor->setSourcePathBase($specManager->getSourcePath());
				$imageProcessor->setTargetPathBase($specManager->getTargetPath());
				$imageProcessor->setImgUrlBase($specManager->getImgUrlBase());
				$imageProcessor->setServerRoot($serverRoot);
				$imageProcessor->setWebPixWidth($specManager->getWebPixWidth());
				$imageProcessor->setTnPixWidth($specManager->getTnPixWidth());
				$imageProcessor->setLgPixWidth($specManager->getLgPixWidth());
				$imageProcessor->setWebFileSizeLimit($specManager->getWebMaxFileSize());
				$imageProcessor->setLgFileSizeLimit($specManager->getLgMaxFileSize());
				$imageProcessor->setJpgQuality($specManager->getJpgQuality());
				$imageProcessor->setUseImageMagick($specManager->getUseImageMagick());
				$imageProcessor->setCreateWebImg($_REQUEST['mapweb']);
				$imageProcessor->setCreateTnImg($_REQUEST['maptn']);
				$imageProcessor->setCreateLgImg($_REQUEST['maplarge']);
				$imageProcessor->setCreateNewRec($_REQUEST['createnewrec']);
				$imageProcessor->setCopyOverImg($_REQUEST['copyoverimg']);
				$imageProcessor->setKeepOrig(0);
				
				//Run process
				$imageProcessor->batchLoadImages();
				echo '</div>'."\n";
			}
			if($status){ 
				?>
				<div style='margin:20px 0px 20px 0px;'>
					<hr/>
					<?php echo $status; ?>
					<hr/>
				</div>
				<?php 
			}
			if($collid){
				?>
				<div id="tabs" class="taxondisplaydiv">
				    <ul>
				        <li><a href="#introdiv">Introduction</a></li>
				        <li><a href="imageprocessor.php?collid=<?php echo $collid.'&spprid='.$spprId.'&submitaction='.$action; ?>">Image Loading</a></li>
				        <li><a href="crowdsource/controlpanel.php?collid=<?php echo $collid; ?>">Crowdsourcing Module</a></li>
				        <!-- 
				        <li><a href="ocrprocessor.php?collid=<?php echo $collid.'&spprid='.$spprId; ?>">Optical Character Recognition</a></li>
				         -->
				        <li><a href="nlpprocessor.php?collid=<?php echo $collid.'&spnlpid='.$spNlpId; ?>">Natural Language Processing</a></li>
				        <li><a href="reports.php?collid=<?php echo $collid.'&menu='.(isset($_REQUEST['menu'])?$_REQUEST['menu']:''); ?>">Reports</a></li>
				        <li><a href="exporter.php?collid=<?php echo $collid; ?>">Exporter</a></li>
				    </ul>
					<div id="introdiv">
						<h1>Specimen Processor Control Panel</h1>
						<div style="margin:10px">
							This management module is designed to aid in establishing advanced processing workflows 
							for unprocessed specimens using images of the specimen label. The central functions addressed in this page are:
							Batch loading images, Optical Character Resolution (OCR), Natural Language Processing (NLP), 
							and crowdsourcing data entry. 
							Use tabs above for access tools.     
						</div>
						<div style="margin:10px;height:400px;">
							<h2>Image Loading</h2>
							<div style="margin:15px">
								The batch image loading module is designed to batch process specimen images that are deposited in a 
								drop folder. This module will produce web-ready images for a group of specimen images and 
								map the new image derivative to records in the the database. Images can be loaded for already existing 
								specimen records, or loaded to create a skeletal specimen record for further digitization within the portal.  
								For more information, see the
								<b><a href="http://symbiota.org/tiki/tiki-index.php?page=Batch+Loading+Specimen+Images">Batch Image Loading</a></b> section 
								on the <b><a href="http://symbiota.org">Symbiota</a> website</b>.   
							</div>

							<h2>Crowdsourcing Module</h2>
							<div style="margin:15px">
								The crowdsourcing module can be used to make unprocessed records accessible for data entry by 
								general users who typically do not have explicit editing writes for a particular collection. 
								For more information, see the
								<b><a href="http://symbiota.org/tiki/tiki-index.php?page=Crowdsourcing">Crowdsource</a></b> section 
								on the <b><a href="http://symbiota.org">Symbiota</a> website</b>.   
							</div>

							<!--  
							<h2>Optical Character Resolution (OCR)</h2>
							<div style="margin:15px">Description to be added </div>

							<h2>Natural Language Processing (NLP)</h2>
							<div style="margin:15px 0px 40px 15px">Description to be added </div>
							-->
							
							
							
							
							
							
							
						</div>
					</div>
				</div>
				<?php 
			}
			else{
				?>
				<div style='font-weight:bold;'>
					Collection project has not been identified
				</div>
				<?php
			}
			?>
		</div>
		<?php
			include($serverRoot.'/footer.php');
		?>
	</body>
</html>
