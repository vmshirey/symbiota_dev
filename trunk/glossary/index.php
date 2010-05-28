<?php
//error_reporting(E_ALL);

 header("Content-Type: text/html; charset=ISO-8859-1");
 include_once("../util/dbconnection.php");
 include_once("../util/symbini.php");
?>
<html>
<head>
	<title><?php echo $defaultTitle; ?> Glossary</title>
	<link rel="stylesheet" href="../css/main.css" type="text/css" />
	<script type="text/javascript">
	</script>
</head>

<body>

	<?php
	$displayLeftMenu = (isset($glossary_indexMenu)?$glossary_indexMenu:"true");
	include($serverRoot."/util/header.php");
	if(isset($glossary_indexCrumbs)){
		echo "<div class='navpath'>";
		echo "<a href='../index.php'>Home</a> &gt; ";
		echo $glossary_indexCrumbs;
		echo " <b>Glossary</b>";
		echo "</div>";
	}
	?>
	<!-- This is inner text! -->
	<div id="innertext">
		<h1>Glossary</h1>
	</div>
	
	<?php
		include($serverRoot."/util/footer.php");
	?>

	<script type="text/javascript">
		var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
		document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
	</script>
	<script type="text/javascript">
		try {
			var pageTracker = _gat._getTracker("<?php echo $googleAnalyticsKey; ?>");
			pageTracker._trackPageview();
		} catch(err) {}
	</script>
</body>
</html>
<?php
 
 class GlossaryManager {

	private $con;

 	public function __construct(){
 		$this->con = MySQLiConnectionFactory::getCon("readonly");
 	}
 	
 	public function __destruct(){
		if(!($this->con === null)) $this->con->close();
	}
	
 }

 ?>