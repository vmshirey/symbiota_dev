<?php
error_reporting(0);
include_once('../../config/symbini.php');
include_once($serverRoot.'/classes/TPImageEditorManager.php');
header("Content-Type: text/html; charset=".$charset);

$tid = array_key_exists("tid",$_REQUEST)?$_REQUEST["tid"]:0;
$category = array_key_exists("category",$_REQUEST)?$_REQUEST["category"]:""; 
$lang = array_key_exists("lang",$_REQUEST)?$_REQUEST["lang"]:"";
$action = array_key_exists("action",$_REQUEST)?$_REQUEST["action"]:"";

$imageEditor = new TPImageEditorManager();
$editable = false;
if($tid){
	$imageEditor->setTid($tid);
	$imageEditor->setLanguage($lang);
	 
	if($isAdmin || array_key_exists("TaxonProfile",$userRights)){
		$editable = true;
	}
	 
	$status = "";
	if($editable){
		if($action == "Submit Image Sort Edits"){
			$imgSortArr = Array();
			foreach($_REQUEST as $sortKey => $sortValue){
				if($sortValue && substr($sortKey,0,6) == "imgid-"){
					$imgSortArr[substr($sortKey,6)]  = $sortValue;
				}
			}
			$status = $imageEditor->editImageSort($imgSortArr);
		} 
		elseif($action == "Upload Image"){
			$status = $imageEditor->loadImageData();
		}
	}
}
else{
	header('Location: tpeditor.php?category='.$category.'&lang='.$lang.'&action='.$action);
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN">
<html>
<head>
	<title><?php echo $defaultTitle." Taxon Editor: ".$imageEditor->getSciName(); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset;?>" />
	<link type="text/css" href="../../css/main.css" rel="stylesheet" />
	<link type="text/css" href="../../css/speciesprofile.css" rel="stylesheet" />
	<script type="text/javascript" src="../../js/symb/taxa.tpimageeditor.js"></script>
</head>
<body>
<?php
$displayLeftMenu = (isset($taxa_admin_tpimageeditorMenu)?$taxa_admin_tpimageeditorMenu:false);
include($serverRoot.'/header.php');
if(isset($taxa_admin_tpimageeditorCrumbs)){
	echo "<div class='navpath'>";
	echo "<a href='../index.php'>Home</a> &gt; ";
	echo $taxa_admin_tpimageeditorCrumbs;
	echo " <b>Taxon Profile Image Editor</b>";
	echo "</div>";
}
?>
<!-- This is inner text! --> 
<div class="innertext">
	<?php 
	if($editable && $tid){
		?>
		<fieldset style="float:right;">
			<legend>
				<b>Menu</b>
			</legend>
			<div class="mdiv" style="float:right;cursor:pointer;color:blue;" onclick="toggle('mdiv')">
				Display Full Menu
			</div>
			<div class="mdiv" style="display:none;">
				<ul style="margin:0px">
					<li><a href="tpimageeditor.php?tid=<?php echo $imageEditor->getTid(); ?>&category=images">Edit Images</a></li>
					<li><a href="tpimageeditor.php?tid=<?php echo $imageEditor->getTid(); ?>&category=imagequicksort">Edit Image Sorting Order</a></li>
					<li><a href="tpimageeditor.php?tid=<?php echo $imageEditor->getTid(); ?>&category=imageadd">Add a New Image</a></li>
					<li><a href="tpdesceditor.php?tid=<?php echo $imageEditor->getTid(); ?>&category=textdescr">Text Descriptions</a></li>
					<?php if($isAdmin || array_key_exists("Taxonomy",$userRights)){ ?>
						<li><a href="taxonomydisplay.php?target=<?php echo $imageEditor->getSciName(); ?>">View Taxonomic Tree</a></li>
						<li><a href="taxonomyeditor.php?target=<?php echo $imageEditor->getTid(); ?>">Edit Taxonomic Placement</a></li>
						<li><a href="taxonomyloader.php">Add New Taxonomic Name</a></li>
					<?php } ?>
					<li><a href="../index.php?taxon=<?php echo $imageEditor->getTid(); ?>">Return to Taxon Profile Page</a></li>
				</ul>
			</div>
		</fieldset>
		<?php 
	 	if($imageEditor->getSubmittedTid()){
	 		?>
	 		<div style='font-size:16px;margin-top:5px;margin-left:10px;font-weight:bold;'>
	 			Redirected from: <i><?php echo $imageEditor->getSubmittedSciName(); ?></i>
	 		</div>
	 		<?php  
	 	}
	 	?>
		<div>
			<span style='font-size:16px;margin-top:15px;margin-left:10px;'>
				<a href="../index.php?taxon=<?php echo $imageEditor->getTid(); ?>" style="color:#990000;text-decoration:none;">
					<b><i><?php echo $imageEditor->getSciName(); ?></i></b>
				</a> 
				<?php echo $imageEditor->getAuthor(); ?>
			</span>
			<?php 
			if($imageEditor->getRankId() > 140){
				?>
				<a href='tpeditor.php?tid=<?php echo $imageEditor->getParentTid(); ?>'><img border='0' height='10px' src='../../images/toparent.jpg' title='Go to Parent' /></a> 
				<?php 
			}
			if($imageEditor->getRankId() == 220 && $childArr = $imageEditor->getChildrenArr()){
				?>
				<a href="#" onclick="toggle('childrendiv')"><img border='0' height='10px' src='../../images/tochild.png' title='Go to a child taxon' /></a>
				<div id="childrendiv" style="width:300px;margin-left:325px;padding:10px;border:2px solid green;display:none;">
					<b>Go To:</b><br/>
					<?php 
					foreach($childArr as $id => $sn){
						?>
						<a href='tpeditor.php?tid=<?php echo $id; ?>'>
							<?php echo $sn; ?>
						</a>
						<br/>
						<?php 
					}
					?>
				</div>
				<?php 
			}
			?>
		</div>
		<div id='family' style='margin-left:20px;margin-top:0.25em;'>
			<b>Family:</b> <?php echo $imageEditor->getFamily();?>
		</div>
		<?php 
		if($status){
			echo "<h3 style='color:red;'>Error: $status<h3>";
		}

		if($category == "imagequicksort"){
			$images = $imageEditor->getImages();
			?>
			<div style='clear:both;'>
				<form action='tpimageeditor.php' method='post' target='_self'>
					<table border='0' cellspacing='0'>
						<tr>
							<?php 
							$imgCnt = 0;
							foreach($images as $imgArr){
								$webUrl = (array_key_exists("imageDomain",$GLOBALS)&&substr($imgArr["url"],0,1)=="/"?$GLOBALS["imageDomain"]:"").$imgArr["url"]; 
								$tnUrl = (array_key_exists("imageDomain",$GLOBALS)&&substr($imgArr["thumbnailurl"],0,1)=="/"?$GLOBALS["imageDomain"]:"").$imgArr["thumbnailurl"];
								if(!$tnUrl) $tnUrl = $webUrl;
								?>
								<td align='center' valign='bottom'>
									<div style='margin:20px 0px 0px 0px;'>
										<a href="<?php echo $webUrl; ?>">
											<img width="150" src="<?php echo $tnUrl;?>" />
										</a>
									</div>
									<div style='margin-top:2px;'>
										Sort sequence: 
										<b><?php echo $imgArr["sortsequence"];?></b>
									</div>
									<div>
										New Value: 
										<input name="imgid-<?php echo $imgArr["imgid"];?>" type="text" size="5" maxlength="5" />
									</div>
								</td>
								<?php 
								$imgCnt++;
								if($imgCnt%5 == 0){
									?>
									</tr>
									<tr>
										<td colspan='5'>
											<hr>
											<div style='margin-top:2px;'>
												<input type='submit' name='action' id='submit' value='Submit Image Sort Edits' />
											</div>
										</td>
									</tr>
									<tr>
									<?php 
								}
							}
							for($i = (5 - $imgCnt%5);$i > 0; $i--){
								echo "<td>&nbsp;</td>";
							}
							?>
						</tr>
					</table>
					<input name='tid' type='hidden' value='<?php echo $imageEditor->getTid(); ?>'>
					<input name='category' type='hidden' value='<?php echo $category; ?>'>
					<?php 
					if($imgCnt%5 != 0) echo "<div style='margin-top:2px;'><input type='submit' name='action' id='imgsortsubmit' value='Submit Image Sort Edits'/></div>\n";
					?>
				</form>
			</div>
			<?php 
		}
		elseif($category == "imageadd"){
			?>
			<div style='clear:both;'>
				<form enctype='multipart/form-data' action='tpimageeditor.php' id='imageaddform' method='post' target='_self' onsubmit='return submitAddForm(this);'>
					<fieldset style='margin:15px;width:90%;'>
				    	<legend>Add a New Image</legend>
						<div style='padding:10px;width:550px;border:1px solid yellow;background-color:FFFF99;'>
							<div class="targetdiv" style="display:block;">
								<div style="font-weight:bold;font-size:110%;margin-bottom:5px;">
									Select an image file located on your computer that you want to upload:
								</div>
						    	<!-- following line sets MAX_FILE_SIZE (must precede the file input field)  -->
								<input type='hidden' name='MAX_FILE_SIZE' value='2000000' />
								<div>
									<input name='userfile' type='file' size='70'/>
								</div>
								<div style="margin-left:10px;">
									<input type="checkbox" name="createlargeimg" value="1" /> Create a large version of image, when applicable
								</div>
								<div style="margin-left:10px;">Note: upload image size can not be greater than 1MB</div>
								<div style="margin:10px 0px 0px 350px;cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="toggle('targetdiv')">
									Link to External Image
								</div>
							</div>
							<div class="targetdiv" style="display:none;">
								<div style="font-weight:bold;font-size:110%;margin-bottom:5px;">
									Enter a URL to an image already located on a web server:
								</div>
								<div>
									<b>URL:</b> 
									<input type='text' name='filepath' size='70'/>
								</div>
								<div style="margin:10px 0px 0px 350px;cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="toggle('targetdiv')">
									Upload Local Image
								</div>
							</div>
						</div>
						
						<!-- Image metadata -->
				    	<div style='margin-top:2px;'>
				    		<b>Caption:</b> 
							<input name='caption' type='text' value='' size='25' maxlength='100'>
						</div>
						<div style='margin-top:2px;'>
							<b>Photographer:</b> 
							<select name='photographeruid' name='photographeruid'>
								<option value="">Select Photographer</option>
								<option value="">---------------------------------------</option>
								<?php $imageEditor->echoPhotographerSelect($paramsArr["uid"]); ?>
							</select>
							<a href="#" onclick="toggle('photooveridediv');return false;" title="Display photographer override field">
								<img src="../../images/editplus.png" style="border:0px;width:12px;" />
							</a>
						</div>
						<div id="photooveridediv" style='margin:2px 0px 5px 10px;display:none;'>
							<b>Photographer Override:</b> 
							<input name='photographer' type='text' value='' size='37' maxlength='100'><br/> 
							* Use only when photographer is not found in above pulldown
						</div>
						<div style="margin-top:2px;" title="Use if manager is different than photographer">
							<b>Manager:</b> 
							<input name='owner' type='text' value='' size='35' maxlength='100'>
						</div>
						<div style='margin-top:2px;'>
							<b>Locality:</b> 
							<input name='locality' type='text' value='' size='70' maxlength='250'>
						</div>
						<div style='margin-top:2px;'>
							<b>Notes:</b> 
							<input name='notes' type='text' value='' size='70' maxlength='250'>
						</div>
						<div style='margin-top:2px;'>
							<b>Sort sequence:</b> 
							<input name='sortsequence' type='text' value='' size='5' maxlength='5'>
							<span style="cursor:pointer;" onclick="toggle('adoptiondiv');" title="Additional Options">
								<img style="border:0px;" src="../../images/add.png" />
							</span>
						</div>
						<div id="adoptiondiv" style="border:1px dotted blue;margin:10px;padding:10px;display:none;">
							<div style="font-size:120%;font-weight:bold;margin-left:-5px;">Additional Options:</div>
							<div style='margin-top:2px;' title="URL to source project. Use when linking to an external image.">
								<b>Source URL:</b> 
								<input name='sourceurl' type='text' value='' size='70' maxlength='250'>
							</div>
							<div style='margin-top:2px;'>
								<b>Copyright:</b> 
								<input name='copyright' type='text' value='' size='70' maxlength='250'>
							</div>
							<div style='margin-top:2px;'>
								<b>Occurrence Record #:</b> 
								<input id="occidadd" name="occid" type="text" value="" READONLY/>
								<span style="cursor:pointer;color:blue;"  onclick="openOccurrenceSearch('occidadd')">Link to Occurrence Record</span>
							</div>
						</div>
						<input name="tid" type="hidden" value="<?php echo $imageEditor->getTid();?>">
						<input name='category' type='hidden' value='images'>
						<div style='margin-top:2px;'>
							<input type='submit' name='action' id='imgaddsubmit' value='Upload Image'/>
						</div>
					</fieldset>
				</form>
			</div>
			<?php 
		}
		else{
			?>
			<div style='clear:both;'>
				<table>
					<?php 
					//catagory == images or is null => just list images 
					$images = $imageEditor->getImages();
					foreach($images as $imgArr){
						?>
						<tr><td>
							<div style="margin:20px;float:left;text-align:center;">
								<?php 
								$webUrl = (array_key_exists("imageDomain",$GLOBALS)&&substr($imgArr["url"],0,1)=="/"?$GLOBALS["imageDomain"]:"").$imgArr["url"]; 
								$tnUrl = (array_key_exists("imageDomain",$GLOBALS)&&substr($imgArr["thumbnailurl"],0,1)=="/"?$GLOBALS["imageDomain"]:"").$imgArr["thumbnailurl"];
								if(!$tnUrl) $tnUrl = $webUrl;
								?>
								<a href="../../imagelib/imgdetails.php?imgid=<?php echo $imgArr['imgid']; ?>">
									<img src="<?php echo $tnUrl;?>" style="width:200px;"/>
								</a>
								<?php 
								if($imgArr["originalurl"]){
									$origUrl = (array_key_exists("imageDomain",$GLOBALS)&&substr($imgArr["originalurl"],0,1)=="/"?$GLOBALS["imageDomain"]:"").$imgArr["originalurl"];
									?>
									<br /><a href="<?php echo $origUrl;?>">Open Large Image</a>
									<?php 
								}
								?>
							</div>
						</td>
						<td valign="middle" style="width:90%">
							<?php
							if($imgArr['occid']){
								?>
								<div style="float:right;margin-right:10px;" title="Must have editing privileges for this collection managing image">
									<a href="../../collections/editor/occurrenceeditor.php?occid=<?php echo $imgArr['occid']; ?>&tabtarget=imagediv" target="_blank">
										<img src="../../images/edit.png" style="border:0px;"/>
									</a>
								</div>
								<?php
							}
							else{
								?>
								<div style='float:right;margin-right:10px;'>
									<a href="../../imagelib/imgdetails.php?imgid=<?php echo $imgArr["imgid"];?>&emode=1" target="_blank">
										<img src="../../images/edit.png" style="border:0px;" />
									</a>
								</div>
								<?php 
							} 
							?>
							<div style='margin:60px 0px 10px 10px;clear:both;'>
								<?php if($imgArr["caption"]){ ?>
								<div>
									<b>Caption:</b> 
									<?php echo $imgArr["caption"];?>
								</div>
								<?php 
								}
								?>
								<div>
									<b>Photographer:</b> 
									<?php echo $imgArr["photographerdisplay"];?>
								</div>
								<?php 
								if($imgArr["owner"]){
								?>
								<div>
									<b>Manager:</b> 
									<?php echo $imgArr["owner"];?>
								</div>
								<?php
								} 
								if($imgArr["sourceurl"]){
								?>
								<div>
									<b>Source URL:</b> 
									<?php echo $imgArr["sourceurl"];?>
								</div>
								<?php
								} 
								if($imgArr["copyright"]){
								?>
								<div>
									<b>Copyright:</b> 
									<?php echo $imgArr["copyright"];?>
								</div>
								<?php
								} 
								if($imgArr["locality"]){
								?>
								<div>
									<b>Locality:</b> 
									<?php echo $imgArr["locality"];?>
								</div>
								<?php
								} 
								if($imgArr["occid"]){
								?>
								<div>
									<b>Occurrence Record #:</b> 
									<a href="<?php echo $clientRoot;?>/collections/individual/index.php?occid=<?php echo $imgArr["occid"]; ?>">
										<?php echo $imgArr["occid"];?>
									</a>
								</div>
								<?php
								}
								if($imgArr["notes"]){
								?>
								<div>
									<b>Notes:</b> 
									<?php echo $imgArr["notes"];?>
								</div>
								<?php
								} 
								?>
								<div>
									<b>Sort sequence:</b> 
									<?php echo $imgArr["sortsequence"];?>
								</div>
							</div>
						
						</td></tr>
						<tr><td colspan='2'>
							<div style='margin:10px 0px 0px 0px;clear:both;'>
								<hr />
							</div>
						</td></tr>
						<?php 
					}
					?>
				</table>
			</div>
			<?php 
		}
	}
	else{
		?>
		<div style="margin:30px;">
			<div style="margin:30px;font-weight:bold;font-size:120%;">
				Please 
				<a href="<?php echo $clientRoot; ?>/profile/index.php?tid=<?php echo $tid; ?>&refurl=<?php echo $clientRoot?>/taxa/admin/tpimageeditor.php">
					login
				</a>
			</div>
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