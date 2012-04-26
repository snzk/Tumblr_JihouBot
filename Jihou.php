<?PHP
//tumblr時報bot

//■初期処理
//PEARライブラリを使う
include 'HTTP/OAuth/Consumer.php';

//日時と曜日の取得
$Month = date('m');
$Day = date('d');
$Hour = date('H');
$Minute = date('i');
$ary_Youbi = array('日', '月', '火', '水', '木', '金', '土');
$Youbi = $ary_Youbi[date('w')];
$Honbun = "";	//Text Postのbody部分
$keyno = 0;

try{
	//コンシューマーキーの取得
	//サーバーに接続する
	$contents = @file('SVinfo.txt');
	$i = 0;
	foreach($contents as $line)
	{
		$SVinfo[$i] = str_replace(array("\r\n","\r","\n"),'',$line);
		echo $SVinfo[$i]."<br />";
		$i = $i + 1;
	}
	//DBに接続してリンクIDを受け取る
	$con = @mysql_connect($SVinfo[0],$SVinfo[1],$SVinfo[2]);
	if($con)
	{
		$contents = @file('DBinfo.txt');
		$i = 0;
		foreach($contents as $line)
		{
			$DBinfo[$i] = str_replace(array("\r\n","\r","\n"),'',$line);
			echo $DBinfo[$i]."<br />";
			$i = $i + 1;
		}
		mysql_select_db($DBinfo[0],$con);	//DBへ移動
		$sql = "SELECT consumer, secretkey 
				FROM  tbl_Consumer";
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
	}

//■主処理
	//アクセストークンの取得
	for($keyno = 1;$keyno <= 2;$keyno++)
	{
		$sql = "SELECT accesstoken, at_secretkey
				FROM  $DBinfo[1] 
				WHERE No = $keyno";
		$rst = mysql_query($sql,$con);    //データベースへリクエストする
		if($rst)
		{
			While($col = mysql_fetch_assoc($rst))
			{
					$access_token = $col['accesstoken'];
					$access_token_secret = $col['at_secretkey'];
					echo $access_token.":".$access_token_secret."<br />";
			}
		}
		else
		{
			echo 'アクセストークン取得リクエスト失敗、もしくはヒットなし';
			mysql_close($con);
		}

		//POST内容の編集
		If($Hour == 00 or $Hour == 12)
		{
			$jihou = '○--------- '.$Month.'月'.$Day.'日('.$Youbi.')   '.$Hour.':'.$Minute.' ---------○';
			//0時なら本文を出力する
			If($Hour == 00)
			{
				if($con)
				{
					//漢字が文字化けするため事前に文字コードをUTF-8にする。そのうち直す
					$sql = "SET NAMES utf8";
					// 月、日をKEYに記念日を検索するSQL文を作成
					$sql = "SELECT clm_Comment
					FROM $DBinfo[2]
					Where clm_Month = $Month And clm_Day = $Day";
					$rst = mysql_query($sql,$con);    //データベースへリクエストする
					If($rst)
					{
						While($col = mysql_fetch_assoc($rst))
							{
								$Honbun = $col['clm_Comment'];
								echo $Honbun;
							}
						mysql_close($con);
					}
					else
					{
						echo 'リクエスト失敗、もしくはヒットなし';
						mysql_close($con);
						exit;
					}
				}
			}
		}
		else
		{
			$jihou = '○--------- '.$Hour.':'.$Minute.' ---------○';
		}

		// Textとして投稿する
		$params = array('type'   => 'text',
						'title'  => $jihou,
						'body'	 => $Honbun);

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
		$api_url = 'http://www.tumblr.com/api/write';
		$response = $consumer->sendRequest($api_url, $params);
	}
} catch (HTTP_OAuth_Consumer_Exception_InvalidResponse $e) {
// リクエストトークンの取得が失敗した場合
    echo $e->getMessage();
    exit;

} catch (HTTP_OAuth_Exception $e) {
// Tumblrからの読み込みが失敗した場合
    echo $e->getMessage();
    exit;
}
?>