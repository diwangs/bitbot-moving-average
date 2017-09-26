<html>
	<head>
		<title>Monitor</title>
		<meta http-equiv="refresh" content="300">
	</head>
	<body>
		<?php
			session_id("wellfuckyou");
			session_start();
		?>
		<table border=1>
			<tr>
				<th>Currency</th>
				<th>Fast EMA</th>
				<th>Slow EMA</th>
				<th>isGambled</th>
				<th>24h</th>
			</tr>
			<?php
				foreach ($_SESSION['isGambled'] as $key=>$value) {
					echo "<tr>";
					echo "<td>".$key."</td>";
					echo "<td>".$_SESSION[$key.'Fast']."</td>";
					echo "<td>".$_SESSION[$key.'Slow']."</td>";
					echo "<td>".$value."</td>";
					echo "<td>".$_SESSION[$key.'24']." %</td>";
					echo "</tr>";
				}
			?>
		</table>
		<?php
			//echo "<br/>Alt stake : ".$_SESSION['altStake']." BTC";
			echo "<br/>Nett BTC asset : ".$_SESSION['totalAssetBTC']." BTC";
			echo "<br/>Nett asset : Rp ".number_format($_SESSION['totalAssetIDR'], 0, '.', ',');
			echo "<br/><br/>Elapsed : ".$_SESSION['elapsed'];
			echo "<br/>Last checked : ".$_SESSION['date'];
			session_write_close();
		?>
	</body>
</html>
