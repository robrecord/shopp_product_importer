<!DOCTYPE HTML>
<html lang="ru-RU">
<head>
	<meta charset="UTF-8">
	<title></title>
</head>
<body>
	<p>Start</p>
<?
$url = "http://seita.dev.redraw.mine.nu/wp-content/edge/001-100-00052.jpg";
function file_exists_from_url($url)
{
	if ( !defined('ABSPATH') )
		define('ABSPATH', dirname(__FILE__) . '/');
	echo "---- Debugging file_exists_from_url<br>";
	echo "Original : $url<br>";
	$url_parsed = parse_url($url);
	$path = $url_parsed['path'];
	$path = ABSPATH.$path;
	echo "Result : $path<br>";
	die;
}
echo file_exists_from_url($url);

?>	
</body>
</html>
