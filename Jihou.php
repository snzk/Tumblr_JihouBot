<?PHP

//post_text_timesignal
//PEARライブラリを使う
include 'HTTP/OAuth/Consumer.php';

//日時と曜日の取得
$Month = date('m');
$Day = date('d');
$Hour = date('H');
$Minute = date('i');
$ary_Youbi = array('日', '月', '火', '水', '木', '金', '土');
$Youbi = $ary_Youbi[date('w')];
$SVHost = "";	//接続先サーバー名
$SVUser = "";	//ユーザー名
$SVPass = "";	//パスワード
$DBName = "";	//データベース名
$tblName = "";		//テーブル名
$Honbun = "";	//Text Postのbody部分
$keyno = 0;

try{
	//漢字が文字化けするため事前に文字コードをUTF-8にする。そのうち直す
	$sql = "SET NAMES utf8";

	//コンシューマーキー、アクセストークンの取得
	//DBに接続してリンクIDを受け取る
	$con = @mysql_connect($SVHost,$SVUser,$SVPass);
	if($con)
	{
		mysql_select_db($DBName,$con);	//DBへ移動
		for($keyno = 0;$keyno <= 2;$keyno++)
		{
			$sql = "SELECT accesstoken, at_secretkey
					FROM  `tbl_AccessToken` 
					WHERE No = $keyno";
			$rst = mysql_query($sql,$con);    //データベースへリクエストする
			if($rst)
			{
				While($col = mysql_fetch_assoc($rst))
				{
					if($keyno == 0)
					{
						$consumer_key = $col['accesstoken'];
						$consumer_secret = $col['at_secretkey'];
					}
					else
					{
						$access_token = $col['accesstoken'];
						$access_token_secret = $col['at_secretkey'];
					}
				}
			}
			else
			{
				echo 'コンシューマーキー取得リクエスト失敗、もしくはヒットなし';
				mysql_close($con);
			}
		}
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
				// 月、日をKEYに記念日を検索するSQL文を作成
				$sql = "SELECT clm_Comment
				FROM $tblName
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
				}
			}
			else
			{
				mysql_close($con);
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