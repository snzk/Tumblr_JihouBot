<?PHP
//tumblr時報bot

//■初期処理
//PEARライブラリを使う
include 'HTTP/OAuth/Consumer.php';

//日時と曜日の取得
$month = date('m');
$day = date('d');
$hour = date('H');
$minute = date('i');
$ary_youbi = array('日', '月', '火', '水', '木', '金', '土');
$youbi = $ary_youbi[date('w')];

$jihou = '';	//Text Postのtitle部分
$honbun = '';	//Text Postのbody部分
$photopath = '';	//画像のpath
$photocap = '';	//画像postの文章
$posttype = '';

//データベースに接続してコンシューマーキーを取得する
	//データベースのログイン情報が書いてあるテキストファイルを読み込む
$contents = @file('SVinfo.txt');
$i = 0;
	//テキストファイルの改行コードを削除する
foreach($contents as $line)
{
	$SVinfo[$i] = str_replace(array("\r\n","\r","\n"),'',$line);
	$i = $i + 1;
}
//DBに接続してリンクIDを受け取り、MySQLからコンシューマーキーを取得
$con = @mysql_connect($SVinfo[0],$SVinfo[1],$SVinfo[2]);
if($con)
{
	$contents = @file('DBinfo.txt');
	$i = 0;
	foreach($contents as $line)
	{
		$DBinfo[$i] = str_replace(array("\r\n","\r","\n"),'',$line);
		$i = $i + 1;
	}
	mysql_select_db($DBinfo[0],$con);	//DBへ移動
	$sql = "SELECT consumer, secretkey 
			FROM  $DBinfo[2]";
	$rst = mysql_query($sql,$con);    //データベースへリクエストする
	if($rst)
	{
		While($col = mysql_fetch_assoc($rst))
		{
			$consumer_key = $col['consumer'];
			$consumer_secret = $col['secretkey'];
		}
	}
	else
	{
		echo 'コンシューマーキー取得リクエスト失敗、もしくはヒットなし';
		mysql_close($con);
	}
}else{
	echo 'DB接続失敗。';
}

/*
$sql = "SELECT accesstoken, at_secretkey, base_hostname 
		FROM  $DBinfo[1]
		WHERE accesstoken = 'hdRSItrOVTF5FNUKsKy3q5iQs7aGDPcwT6FKXWNUwJGVfvuMWc'";
*/
$sql = "SELECT accesstoken, at_secretkey, base_hostname 
		FROM  $DBinfo[1]";
$rst = mysql_query($sql,$con);    //データベースへリクエストする

//■主処理
	//シークレットキーの数だけ処理を繰り返す
While($ATcol = mysql_fetch_assoc($rst))
{
		$access_token = $ATcol['accesstoken'];
		$access_token_secret = $ATcol['at_secretkey'];
		$base_hostname = $ATcol['base_hostname'];
		echo 'アクセストークン：'.$access_token."<br />シークレットキー：".$access_token_secret."<br />base-hostname:".$base_hostname;
		echo "<br/>現在時刻 -> ".$hour.':'.$minute."<br/>" ;

		//投稿内容の編集
		if($hour == '12' or $hour == '00'){
			$jihou = '○--------- '.$month.'月'.$day.'日('.$youbi.')   '.$hour.':'.$minute.' ---------○';
			$posttype = 'text';
			echo $jihou;
		}else{
			$jihou = '○--------- '.$hour.':'.$minute.' ---------○';
			$posttype = 'text';
			echo $jihou;
		}

		//投稿パラメータを設定
		if($posttype == 'text')
		{
			$params = array('type'   => 'text',
							'title'  => $jihou,
							'body'	 => $honbun);
		}else{
			$params = array('type' => 'photo',
							'source' => '',
							'caption' => '');
		}

		$http_request = new HTTP_Request2();
		$http_request->setConfig('ssl_verify_peer', false);	
		$consumer = new HTTP_OAuth_Consumer($consumer_key, $consumer_secret);
		$consumer_request = new HTTP_OAuth_Consumer_Request;
		$consumer_request->accept($http_request);
		$consumer->accept($consumer_request);

		// リクエストトークンの発行を依頼
		$consumer->getRequestToken('http://www.tumblr.com/oauth/request_token');

		// リクエストトークンを取得
		$request_token = $consumer->getToken();
		$request_token_secret = $consumer->getTokenSecret();

		// リクエストトークンをセット
		$consumer->setToken($request_token);
		$consumer->setTokenSecret($request_token_secret);

		// 発行済みのアクセストークンをセット
		$consumer->setToken($access_token);
		$consumer->setTokenSecret($access_token_secret);

		// OAuthを経由してTumblrの投稿APIへデータを投げる
			//tumblr API v2になってリクエストするURLが変更。{base-hostname}≒tumblrBlogアドレス
		$api_url = 'http://api.tumblr.com/v2/blog/'.$base_hostname.'/post';
		$response = $consumer->sendRequest($api_url, $params);

		//返り値を画面に表示する
		$value=json_decode($response->getBody(),true);
		$status = $value['meta']['status'] ;
		$msg = $value['meta']['msg'] ;
		$id = $value['response']['id'] ;
		echo '$id='.$id.'、'.'$status='.$status.'、$msg='.$msg.'、base-hostname='.$ATcol['base_hostname']."<br/>";
}
mysql_close($con);
?>