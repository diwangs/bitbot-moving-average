<?php
	$start = microtime(True);

	//functions
	function btcid_query($method, array $req = array()) {
		// API settings
		$key = 'something'; // your API-key
		$secret = 'anotherSomething'; // your Secret-key
		$req['method'] = $method;
		$req['nonce'] = '9223372036854775808'.time();

		// generate the POST data string
		$post_data = http_build_query($req, '', '&');
		$sign = hash_hmac('sha512', $post_data, $secret);

		// generate the extra headers
		$headers = array(
			'Sign: '.$sign,
			'Key: '.$key,
		);

		// our curl handle (initialize if required)
		$query_curl = curl_init();
		curl_setopt($query_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($query_curl, CURLOPT_URL, 'https://vip.bitcoin.co.id/tapi/');
		curl_setopt($query_curl, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($query_curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($query_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		// run the query
		$res = curl_exec($query_curl);
		curl_close($query_curl);
		if ($res === false) throw new Exception('Could not get reply:'.curl_error($query_curl));
		$dec = json_decode($res, true);
		if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists: '.$res);
		return $dec['return'];
	}
		//parametered query example : $temp = btcid_query('openOrders',$param);
		//with $param['pair']['etc'] already filled
	function instantSellBuy($op, $currency, $ammount) {
		$price = $GLOBALS['price'];
		if ($op == 'sell') {
			$currencyP = $currency;
		} elseif ($op == 'buy') {
			$currency == 'btc' ? $currencyP = 'idr' : $currencyP = 'btc';
		}
		//P for parameter
		$param = array('pair','type','price',$currencyP);
		$currency == 'btc' ? $pair = 'btc_idr' : $pair = $currency.'_btc';
		$param['pair'] = $pair;
		$param['type'] = $op;
		//put it slightly below or slightly above
		$op == 'sell' ? ($priceP = 0.9*$price[0][$currency]) : ($priceP = 1.1*$price[0][$currency]);
		$param['price'] = $priceP;
		$param[$currencyP] = $ammount;
		btcid_query('trade',$param);
	}
		//$op : sell / buy
	function ema($currency, $period) {
		$price = $GLOBALS['price'];
		$weight = 2/($period + 1);
		$ema_raw = array();
		$sum = 0;
		for ($i=0; $i<$period; $i++) {
			$sum = $sum + $price[$i][$currency];
		}
		$ema_raw[$period-1] = $sum/$period;
		for ($i=$period-2; $i>=0; $i--) {
			$ema_raw[$i] = $ema_raw[$i+1]*(1-$weight) + $price[$i][$currency]*$weight;
		}
		return $ema_raw[0];
	}
		//exponential moving average. $period in 5 minutes
	function altCheck($currency, $fast, $slow) {
		$price = $GLOBALS['price'];
		$_SESSION[$currency.'Fast'] = ema($currency, $fast);
		$_SESSION[$currency.'Slow'] = ema($currency, $slow);
		if (($_SESSION[$currency.'Fast'] > $_SESSION[$currency.'Slow']) && !$_SESSION['isGambled'][$currency]) {
			//buy alt if the trend goes up
			instantSellBuy('buy', $currency, 0.003);
			$_SESSION['isGambled'][$currency] = True;
		} elseif (($_SESSION[$currency.'Fast'] < $_SESSION[$currency.'Slow']) && $_SESSION['isGambled'][$currency]) {
			//sell alt if the trend goes down
			$balances_raw = btcid_query('getInfo');
			instantSellBuy('sell', $currency, $balances_raw['balance'][$currency]);
			$_SESSION['isGambled'][$currency] = False;
			$balances_raw = null;
		}
	}


	//initialization
	date_default_timezone_set('Asia/Jakarta');
	session_id("wellfuckyou");
	session_start();

	//$price
	//get the price from a MySQL database
	$conn = mysqli_connect('localhost', 'yourUsername', 'yourPassword', 'yourDatabase');
	$result = mysqli_query($conn, "SELECT `btc`, `bch`, `bts`,`drk`,`doge`,`eth`,`ltc`,`nxt`,`str`,`nem`,`xrp` FROM `priceHistory` ORDER BY `time` DESC LIMIT 433");
	$price = mysqli_fetch_all($result, MYSQLI_ASSOC);
	mysqli_close($conn);
	$conn = null;
	$result = null;

	//$_SESSION['isGambled']
	if (!isset($_SESSION['isGambled'])) {
		$balances_raw = btcid_query('getInfo');
		$balances_raw['balance']['btc'] > 0 ? $_SESSION['isGambled']['btc'] = True : $_SESSION['isGambled']['btc'] = False;
		$balances_raw['balance']['bts'] > 0 ? $_SESSION['isGambled']['bts'] = True : $_SESSION['isGambled']['bts'] = False;
		$balances_raw['balance']['drk'] > 0 ? $_SESSION['isGambled']['drk'] = True : $_SESSION['isGambled']['drk'] = False;
		$balances_raw['balance']['doge'] > 0 ? $_SESSION['isGambled']['doge'] = True : $_SESSION['isGambled']['doge'] = False;
		$balances_raw['balance']['eth'] > 0 ? $_SESSION['isGambled']['eth'] = True : $_SESSION['isGambled']['eth'] = False;
		$balances_raw['balance']['ltc'] > 0 ? $_SESSION['isGambled']['ltc'] = True : $_SESSION['isGambled']['ltc'] = False;
		$balances_raw['balance']['nxt'] > 0 ? $_SESSION['isGambled']['nxt'] = True : $_SESSION['isGambled']['nxt'] = False;
		$balances_raw['balance']['str'] > 0 ? $_SESSION['isGambled']['str'] = True : $_SESSION['isGambled']['str'] = False;
		$balances_raw['balance']['nem'] > 0 ? $_SESSION['isGambled']['nem'] = True : $_SESSION['isGambled']['nem'] = False;
		$balances_raw['balance']['xrp'] > 0 ? $_SESSION['isGambled']['xrp'] = True : $_SESSION['isGambled']['xrp'] = False;
		$balances_raw = null;
	}

	//process
	if ($price[432]['btc'] != null) {
		$_SESSION['btcFast'] = ema('btc', 144);
		$_SESSION['btcSlow'] = ema('btc', 432);
		if (!$_SESSION['isGambled']['btc'] && ($_SESSION['btcFast'] > $_SESSION['btcSlow'])) {
			//buy btc if the trend goes up
			$balances_raw = btcid_query('getInfo');
			instantSellBuy('buy','btc',$balances_raw['balance']['idr']);
			$_SESSION['isGambled']['btc'] = True;
			$balances_raw = null;
		} elseif ($_SESSION['isGambled']['btc']) {
			//sell btc if the trend goes down
			$balances_raw = btcid_query('getInfo');
			if ($_SESSION['btcFast'] < $_SESSION['btcSlow']) {
				foreach ($price[0] as $key=>$value) {
					if ($_SESSION['isGambled'][$key] && ($key != 'btc')) {
						instantSellBuy('sell', $key, $balances_raw['balance'][$key]);
						$_SESSION['isGambled'][$key] = False;
					}
				}
				$balances_raw = btcid_query('getInfo');
				instantSellBuy('sell','btc',$balances_raw['balance']['btc']);
				$_SESSION['isGambled']['btc'] = False;
				$balances_raw = null;
			} else {
				//altcheck
				altCheck('bts', 249, 252);
				altCheck('drk', 249, 278);
				altCheck('doge', 292, 293);
				altCheck('eth', 252, 254);
				altCheck('ltc', 256, 404);
				altCheck('nxt', 270, 278);
				//too unstable
				//altCheck('str', 266, 287);
				//altCheck('nem', 110, 369);
				//altCheck('xrp', 266, 268);
			}
		}
	}

	//altstake, total asset, and 24
	$balances_raw = btcid_query('getInfo');
	$btc_raw = 0;
	//$btconly = True;
	foreach ($price[0] as $key=>$value) {
		$_SESSION[$key.'24'] = round((($value-$price[287][$key])/$price[287][$key])*100, 2);
		if (($key != 'btc') && ($key != 'bch')) {
			$btc_raw = $btc_raw + $value * $balances_raw['balance'][$key];
			//$_SESSION['isGambled'][$key] == True ? $btconly = False : $btconly = True;
		}
	}
	$_SESSION['totalAssetBTC'] = round($btc_raw + $balances_raw['balance']['btc'], 8);
	$_SESSION['totalAssetIDR'] = round($balances_raw['balance']['idr'] + ($_SESSION['totalAssetBTC'] * $price[0]['btc']) + ($balances_raw['balance']['bch'] * $price[0]['bch']));
	//$btconly ? $_SESSION['altStake'] = 0.1 * $_SESSION['totalAssetBTC'];
	$balances_raw = null;
	$btc_raw = null;

	//save to session
	$_SESSION['elapsed'] = microtime(True)-$start;
	$_SESSION['date'] = date('j M Y G:i:s');
	session_write_close();
?>
