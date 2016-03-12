<?php 
$nome = "Nome: &nbsp;&nbsp;";
$ip = "Ip:&nbsp;&nbsp;";
$playersj = "Players conectados: &nbsp;&nbsp;";
$capacidade = "Slots: &nbsp;&nbsp;";
$map = "Mapa Atual:&nbsp;&nbsp; ";
$bots = "Bots conectados: &nbsp;&nbsp;";
$vacstatus = "Vac Status: &nbsp;&nbsp;";
$gameversion = "Versão: &nbsp;&nbsp;";
$tipo = "Tipo: &nbsp;&nbsp;";

?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
<form method="POST" action="" enctype="multipart/form-data">
<input type="text" name="server" id="server" class="server"><br>
<input type="submit" name="submit" value="GO!">
</form>
</body>

</html>
<?php


include("class_cstrike.php");

$errovazio = "Insere o IP";

$iptotal = explode(":", $_POST['server']);
$ipserver = $iptotal[0];
$portserver = $iptotal[1];

if(isset($_POST['submit'])) {
	if(empty($_POST['server'])) {
		
	echo $errovazio;
	
	} else {
		
		

						
if((filter_var($ipserver, FILTER_VALIDATE_IP) && is_numeric($portserver)) == true) {
		if(strlen($_POST['server']) > 20) {
		 echo "server não e valido";
		 
		}else {	

		$server = new cstrike($ipserver, $portserver);


		$infoarrays = $server->getInfo();

		echo $nome, $infoarrays["serverinfo"]["servername"]. "</br>";
		echo $ip, $infoarrays["serverinfo"]["ip"] . ":" . $portserver. "</br>";
		echo $playersj, $infoarrays["serverinfo"]["player_cur"]. "</br>";
		echo $capacidade, $infoarrays["serverinfo"]["player_max"]. "</br>";
		echo $map, $infoarrays["serverinfo"]["map"]. "</br></br>";

		echo $bots, $infoarrays["serverinfo"]["bots"]. "</br>";
		echo $vacstatus, $infoarrays["serverinfo"]["secure"] . "&nbsp;&nbsp;(1=Ligado/0=desligado)</br>";
		echo $gameversion, $infoarrays["serverinfo"]["gameversion"]. "</br>";
		echo $tipo, $infoarrays["serverinfo"]["type"]. "</br></br>";
		
				}
		
			} else {
				echo "server não valido";

			
			}
	

	}
}
// Uncomment for the full data array, view source of the page to see it!
// print_r($infoarrays);
?>
