<?	/*VERSION*/ 
	$v = "v0.4.0";
global $DEBUG;
$DEBUG = 0; 

function D($msg) {
	global $DEBUG;
	if($DEBUG)
		echo "\n---- DEBUG: $msg\n";
}

function parse_response($response){ 
/*    ***returns an array in the following format which varies depending on headers returned 

        [0] => the HTTP error or response code such as 404 
        [1] => Array 
        ( 
            [Server] => Microsoft-IIS/5.0 
            [Date] => Wed, 28 Apr 2004 23:29:20 GMT 
            [X-Powered-By] => ASP.NET 
            [Connection] => close 
            [Set-Cookie] => COOKIESTUFF 
            [Expires] => Thu, 01 Dec 1994 16:00:00 GMT 
            [Content-Type] => text/html 
            [Content-Length] => 4040 
        ) 
        [2] => Response body (string) 	<- removido
*/ 
	$response_headers = $response;
    //list($response_headers,$response_body) = explode("\r\n\r\n",$response,2); 
    $response_header_lines = explode("\r\n",$response_headers); 

    // first line of headers is the HTTP response code 
    $http_response_line = array_shift($response_header_lines); 
    if (preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@',$http_response_line, 
                   $matches)) { 
        $response_code = $matches[1]; 
    } 

    // put the rest of the headers in an array 
    $response_header_array = array(); 
    foreach ($response_header_lines as $header_line) { 
        list($header,$value) = explode(': ',$header_line,2); 
        $response_header_array[$header] = $value; 
    } 

    return array($response_code,$response_header_array/*,$response_body*/); 
} 


function testUrl($url) {
	//D("A testar $url: ");

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$res = curl_exec($ch);
	curl_close($ch);

	$head = parse_response($res);

	if(strpos($head[1]["Content-Type"],"image")===false) {
		return(0);
	}

	if (($head[0] == 400) || !(strpos($head[1]["Content-Type"],"text")===false)) {
		D("TestURL: KO");
		return 0;
	} else {
		D("TestURL: OK");
		return 1;
	}
}	

$keys = array_keys($_GET);
$comic = $keys[0];

//if($comic == NULL) $comic = "LICD";

mysql_connect(host,username,pass) or die(mysql_error()); mysql_select_db(db) or die(mysql_error());

if (!$comic) {
	echo "<h3>Feeds for comics:</h3>";
	echo "<p>I read webcomics on my mobile phone but I got tired of loading slow comic webpages on Opera Mini, or checking for new strip images and other weird stuff so I created this script to generate RSS feeds of webcomic images. No HTML loading, no more 'where was I?'... just plain webcomic plesure! Enjoy</p><ul>";
	$q = "SELECT * FROM comics ORDER BY comic ASC";
	$res = mysql_query($q) or die(mysql_error());
	while($r = mysql_fetch_array($res)) {
		echo "\n<li><a href='?".$r['id']."'>".$r['comic']."</a></li>";
	}
	?></ul>
	<h4>Made by anothers:</h4>
	<ul>
		<li><a href="http://www.l2f.inesc-id.pt/~nurv/garfield.php">Garfield</a></li>
	</ul>
<?	echo "<small>$v</small>";
	die();
}

$q = "SELECT * FROM comics WHERE id LIKE '$comic'";
D($q);
$res = mysql_query($q) or die(mysql_error());
$r = mysql_fetch_array($res);

	$id = $r['id'];
	$comic = $r['comic'];
	$url = $r['url'];
	$last = $r['last'];
	$lastcheck = $r['lastcheck'];
	$homepage = $r['homepage'];
	$type = $r['type'];
	$dateformat = $r['dateformat'];
	$cache = html_entity_decode($r['cache']);


if($type == 'seq') {
	D("URL: ".str_replace("*", $last+1, $url));
	while (testUrl(str_replace("*", $last+1, $url))) {
		$last++;
		//echo "$last OK  ";
	}
	mysql_query("UPDATE comics SET last = '$last' WHERE id = '$id'");
	header("Content-Type: application/rss+xml");
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	?><rss version="2.0">
		<channel>
			<title><? echo $comic;?></title>
			<link><? echo $homepage;?></link>
			<description><? echo $comic;?></description>
	<? for ($i = $last; $i>$last-10; $i--): ?>
	<item>
		<title><? echo $comic." - ".$i;?></title>
		<description>
		  &lt;img src="<? echo $fullUrl = str_replace("*", $i, $url);?>"/&gt;&lt;br/&gt;&lt;br/&gt;
		</description>
		<link><? echo $fullUrl; ?></link>
		<guid><? echo $fullUrl; ?></guid>
		<pubDate><? echo date(DATE_RFC822,time()+($i-$last)*(24*60*60));?></pubDate>
	</item>
	<? endfor;



} else if($type == 'date') {

	$date = date("Y-m-d");
	$datef= date($dateformat);
	D("DATE FORMAT: $dateformat ; DATE = $date ; DATEF: $datef");
	
	
	header("Content-Type: application/rss+xml");
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	?><rss version="2.0">
		<channel>
			<title><? echo $comic;?></title>
			<link><? echo $homepage;?></link>
			<description><? echo $comic;?></description>
			
	<?
	if($lastcheck == $date) { //Se já foi verificado hoje, só é preciso ver se há o de hoje novo
		if($last == $datef) {	//O de hoje já existe, vomitar a cache.
			D("FROM CACHE 1 - Today's comic already cached");
			echo $cache;		
		} else { // comic de hoje ainda não foi postada. procurar outra vez
			$fullUrl = str_replace("*", $datef, $url);
			if(!testUrl($fullUrl)) {
				D("FROM CACHE 2 - Today's comic not found. Already checked today.");
				echo $cache;
			} else { //FOUND!
				D("TODAY'S UP! GENERATING CACHE");
			
				$out  =	"<item>\n";
				$out .=	"	<title>$comic - ".date("Y-m-d",time()-$i*(24*60*60))."</title>\n";
				$out .=	"	<description>\n";
				$out .=	"		  &lt;img src='$fullUrl'/&gt;&lt;br/&gt;&lt;br/&gt;\n";
				$out .=	"	</description>\n";
				$out .=	"	<link>$fullUrl</link>\n";
				$out .=	"	<guid>$fullUrl</guid>\n";
				$out .= "	<pubDate>".date(DATE_RFC822,time()-$i*(24*60*60))."</pubDate>\n";
				$out .=	"</item>\n";

				$count=6;
				for ($i = 1; $count>0; $i++):
					$ed = date($dateformat,time()-$i*(24*60*60));
					$fullUrl = str_replace("*", $ed, $url);
					D("TestURL: #$i - $fullUrl");
					if(!testUrl($fullUrl)) {
						continue;
					}
					$count--;
					$out .=	"<item>\n";
					$out .=	"	<title>$comic - ".date("Y-m-d",time()-$i*(24*60*60))."</title>\n";
					$out .=	"	<description>\n";
					$out .=	"		  &lt;img src='$fullUrl'/&gt;&lt;br/&gt;&lt;br/&gt;\n";
					$out .=	"	</description>\n";
					$out .=	"	<link>$fullUrl</link>\n";
					$out .=	"	<guid>$fullUrl</guid>\n";
					$out .= "	<pubDate>".date(DATE_RFC822,time()-$i*(24*60*60))."</pubDate>\n";
					$out .=	"</item>\n";
				endfor;
				$q = "UPDATE comics SET cache = '".htmlentities(addslashes($out))."', last = '$date' WHERE id = '$id'";
			    mysql_query($q) or die(mysql_error());
			    echo $out;
			    
			}
		}
	} else { // Hoje ainda não foi verificado. verificar desde o lastcheck
		$out = "";	
    	$count=7;
    	$newLast=0;
    	if($cache!="")
    		$lastunix = strtotime($last)+(24*60*60);
    	else
	    	$lastunix = 0;
	    if(strtotime($last) < time()-10*(24*60*60)) { // se n é actualizado ha mt tempo, reduz o $count
	    	$count = 4;
	    }
    	D("last: $last  lastunix: $lastunix");
		for($time = strtotime(date("Y-m-d",time())); $time > $lastunix ; $time -= 24*60*60) {
			if(!$count) { break; }
			//echo "time: $time";
			$ed = date($dateformat,$time);
    		$fullUrl = str_replace("*", $ed, $url);
    		D("TestURL: $fullUrl");
    		
    		if(!testUrl($fullUrl)) {
    			continue;
    		}
    		$lastcheck = 0; //passa só a ter em conta o $count
    		if(!$newLast) $newLast = date("Y-m-d",$time);
    		$count--;
    		$out .=	"<item>\n";
    		$out .=	"	<title>$comic - ".date("Y-m-d",$time)."</title>\n";
    		$out .=	"		<description>\n";
    		$out .=	"		  &lt;img src='$fullUrl'/&gt;&lt;br/&gt;&lt;br/&gt;\n";
    		$out .=	"		</description>\n";
    		$out .=	"		<link>$fullUrl</link>\n";
    		$out .=	"		<guid>$fullUrl</guid>\n";
    		$out .= "		<pubDate>".date(DATE_RFC822,$time)."</pubDate>";
    		$out .=	"</item>\n";
    	}
    	
    	if($out != "") { // achou algo no periodo de tempo que ainda n tinha sido verificado
    		D("New comics found!");
	    	$q = "UPDATE comics SET cache = '".htmlentities(addslashes($out))."', last = '$newLast', lastcheck = '".date("Y-m-d")."' WHERE id = '$id'";
    	    mysql_query($q) or die(mysql_error());
        	echo $out;
    	} else { // não achou nada... pode só mandar a cache
			D("FROM CACHE - No new comics found.");
	    	$q = "UPDATE comics SET lastcheck = '".date("Y-m-d")."' WHERE id = '$id'";
    	    mysql_query($q) or die(mysql_error());
    		echo $cache;
    	}
    }
} else if ($type=="fetch") {
	$array = json_decode($r['cache'],true);
	$hoje = date("Y-m-d");
	if ($lastcheck != $hoje) {			// lastcheck => last updated...
		$res = fetchComicFromURL($homepage, $url);
		if ($res != $last) {			// last => id do ultimo reconhecido
			$array[$hoje] = $res;
			$newlast = $res;
			krsort($array);
			$array = array_slice($array, 0, 5, true);
			$serial = mysql_real_escape_string(json_encode($array));
			$q = "UPDATE comics SET cache = '$serial', last = '$newlast', lastcheck = '$hoje' WHERE id = '$id'";
		    mysql_query($q) or die(mysql_error());
		}
	}
	echo generateRSS($comic, $homepage, $url, $array);
}





function fetchComicFromURL($homepage, $url) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $homepage);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$html = curl_exec($ch);
	curl_close($ch);
	
	$escape = array("/" => "\/", "." => "\.", "*"=>"(.*");
	$patrn = "/".strtr($url, $escape)."(.jpg|.png|.gif))/";
	
	preg_match($patrn, $html , $matches);
	return $matches[1];
}


function generateRSS($comic, $homepage, $url, $a) {
	krsort($a);
	header("Content-Type: application/rss+xml");
	echo '<?xml version="1.0" encoding="UTF-8"?>';?>
<rss version="2.0">
	<channel>
		<title><? echo $comic;?></title>
		<link><? echo $homepage;?></link>
		<description><? echo $comic;?></description>
<?	foreach($a as $k => $v):
		$v = str_replace("*", $v, $url); ?>

	<item>
		<title><? echo $comic." - ".$k;?></title>
		<description>
		  &lt;img src="<? echo $v; ?>"/&gt;
		</description>
		<link><? echo $v; ?></link>
		<guid><? echo $v; ?></guid>
		<pubDate><? echo $k;?></pubDate>
	</item>
<? endforeach; 
} ?>

</channel>
</rss>