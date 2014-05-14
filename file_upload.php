<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php echo @$_FILES["file"]["name"]; ?></title>
</head>

<body>
<?php
use lovasoa\Ophir;
require_once("src/Ophir.php");
$ophir = new Ophir();

if (!isset($_FILES["file"])) {
?>
<form action="file_upload.php" method="post" enctype="multipart/form-data">
	<label for="file">Filename:</label>
	<input type="file" name="file" id="file"/>
	<br/>

	<p>Configuration:<br>
	<table cellspacing=0 cellpadding=0 border=1>
		<tr>
			<th>Element</th>
			<th>Import</th>
			<th>Import as text</th>
			<th>Remove</th>
		</tr>
		<?php
		foreach ($ophir->getConfiguration() as $conf => $value) {
			$checked = array();
			$checked[$value] = "checked";
			echo '<tr><td>' . $conf . '</td>';
			echo '<td><input type="radio" name="features[' . $conf . ']" value="'.Ophir::ALL.'" '.$checked[Ophir::ALL].'></td>';
			echo '<td><input type="radio" name="features[' . $conf . ']" value="'.Ophir::SIMPLE.'" '.$checked[Ophir::SIMPLE].'></td>';
			echo '<td><input type="radio" name="features[' . $conf . ']" value="'.Ophir::NONE.'" '.$checked[Ophir::NONE].'></td>';

			echo '</tr>';
			unset($checked);
		}
		?>
	</table>
	</p>
	<input type="submit" name="submit" value="Submit"/>
</form>

</body>
</html>
<?php
} else {
	if (($_FILES["file"]["size"] < 1e6)) {
		if ($_FILES["file"]["error"] > 0) {
			echo "Return Code: " . $_FILES["file"]["error"] . "<br />";
		} else {
			foreach($_POST["features"] as $feature => $value){
				$ophir->setConfiguration($feature,$value);
			}

			$time  = microtime(true);
			$ophir = new \lovasoa\Ophir();
			echo $ophir->odt2html($_FILES["file"]["tmp_name"]);


			echo '<div style="background-color:grey">';
			echo "Upload: " . $_FILES["file"]["name"] . "<br />";
			echo "Type: " . $_FILES["file"]["type"] . "<br />";
			echo "Size: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
			echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br />";
			echo "\n\n<br><font size='0.5em'>HTML generated in <b>" . (microtime(true) - $time) . "</b> microseconds</font>";
			echo "</div>";
		}
	} else {
		echo "Invalid file";
	}
}
?>
</body>
</html>
