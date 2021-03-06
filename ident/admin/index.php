<?php
include_once('../../config/symbini.php');
include_once($serverRoot.'/classes/KeyCharAdmin.php');
header("Content-Type: text/html; charset=".$charset);

if(!$SYMB_UID) header('Location: ../../profile/index.php?refurl=../ident/admin/index.php?'.$_SERVER['QUERY_STRING']);

$langId = array_key_exists('langid',$_REQUEST)?$_REQUEST['langid']:'';

$charManager = new KeyCharAdmin();
$charManager->setLangId($langId);
$charArr = $charManager->getCharacterArr();

$headingArr = array();
if(isset($charArr['head'])){
	$headingArr = $charArr['head'];
	unset($charArr['head']);
}

$isEditor = false;
if($isAdmin || array_key_exists("KeyAdmin",$userRights)){
	$isEditor = true;
}

?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset;?>">
	<title>Character Admin</title>
    <link href="../../css/base.css?<?php echo $CSS_VERSION; ?>" type="text/css" rel="stylesheet" />
    <link href="../../css/main.css?<?php echo $CSS_VERSION; ?>" type="text/css" rel="stylesheet" />
	<script type="text/javascript" src="../../js/symb/shared.js"></script>
	<script type="text/javascript">

		function validateNewCharForm(f){
			if(f.charname.value == ""){
				alert("Character name must have a value");
				return false;
			}
			if(f.chartype.value == ""){
				alert("A character type must be selected");
				return false;
			} 
			if(f.sortsequence.value && !isNumeric(f.sortsequence.value)){
				alert("Sort Sequence must be a numeric value only");
				return false;
			}
			return true;
		}
	</script>
	<style type="text/css">
		input{ autocomplete: off; } 
	</style>
</head>
<body>
<?php
$displayLeftMenu = (isset($ident_admin_indexMenu)?$ident_admin_indexMenu:true);
include($serverRoot."/header.php");
if(isset($collections_loans_indexCrumbs)){
	if($collections_loans_indexCrumbs){
		?>
		<div class='navpath'>
			<?php echo $ident_admin_indexCrumbs; ?>
			<b>Character Management</b>
		</div>
		<?php 
	}
}
else{
	?>
	<div class='navpath'>
		<a href='../../index.php'>Home</a> &gt;&gt; 
		<b>Character Management</b>
	</div>
	<?php 
}
?>
	<!-- This is inner text! -->
	<div id="innertext">
		<?php 
		if($isEditor){
			?>
			<div id="addeditchar">
				<div style="float:right;margin:10px;">
					<a href="#" onclick="toggle('addchardiv');">
						<img src="../../images/add.png" alt="Create New Character" />
					</a>
				</div>
				<div id="addchardiv" style="display:none;margin-bottom:8px;">
					<form name="newcharform" action="chardetails.php" method="post" onsubmit="return validateNewCharForm(this)">
						<fieldset style="padding:10px;">
							<legend><b>New Character</b></legend>
							<div>
								Character Name:<br />
								<input type="text" name="charname" maxlength="255" style="width:400px;" />
							</div>
							<div style="padding-top:6px;">
								<div style="float:left;">
									Type:<br />
									<select name="chartype" style="width:180px;">
										<option value="">------------------------</option>
										<option value="UM">Unordered Multi-state</option>
										<option value="IN">Integer</option>
										<option value="RN">Real Number</option>
									</select>
								</div>
								<div style="margin-left:30px;float:left;">
									Difficulty:<br />
									<select name="difficultyrank" style="width:100px;">
										<option value="">---------------</option>
										<option value="1">Easy</option>
										<option value="2">Intermediate</option>
										<option value="3">Advanced</option>
										<option value="4">Hidden</option>
									</select>
								</div>
								<div style="margin-left:30px;float:left;">
									Heading:<br />
									<select name="hid" style="width:125px;">
										<option value="">No Heading</option>
										<option value="">---------------------</option>
										<?php 
										foreach($headingArr as $k => $v){
											echo '<option value="'.$k.'">'.$v.'</option>';
										}
										?>
									</select>
								</div>
							</div>
							<div style="padding-top:6px;clear:both;">
								<b>Sort Sequence</b><br />
								<input type="text" name="sortsequence" />
							</div>
							<div style="width:100%;padding-top:6px;">
								<button name="formsubmit" type="submit" value="Create">Create</button>
							</div>
						</fieldset>
					</form>
				</div>
				<div id="charlist" style="padding-left:10px;">
					<?php 
					if($charArr){
						?>
						<h3>Characters by Heading</h3>
						<ul>
							<?php 
							foreach($charArr as $k => $charList){
								?>
								<li>
									<a href="#" onclick="toggle('char-<?php echo $k; ?>');"><?php echo $headingArr[$k]; ?></a>
									<div id="char-<?php echo $k; ?>" style="display:block;">
										<ul>
											<?php 
											foreach($charList as $cid => $charName){
												echo '<li>';
												echo '<a href="chardetails.php?cid='.$cid.'">'.$charName.'</a>';
												echo '</li>';
											}
											?>
										</ul>
									</div>
								</li>
								<?php 
							}
							?>
						</ul>
					<?php 
					}
					else{
						echo '<div style="font-weight:bold;font-size:120%;">There are no existing characters</div>';
					}
					?>
				</div>
			</div>
			<?php 
		}
		else{
			echo '<h2>You are not authorized to add characters</h2>';
		}
		?>
	</div>
	<?php 
	include($serverRoot.'/footer.php');
	?>
</body>
</html>