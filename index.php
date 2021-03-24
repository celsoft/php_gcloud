<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if ( isset($_GET['clear_cache']) AND $_GET['clear_cache'] == 'true' ) {
    $files = glob('cache/*');
    foreach($files as $file){
        if(is_file($file)) {
            @unlink($file);
        }
    }
    $files_mobile = glob('cache/mobile/*');
    foreach($files_mobile as $file){
        if(is_file($file)) {
            @unlink($file);
        }
    }
    echo 'ok';
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Mobile_Detect.php';

use SimpleCache\SimpleCache;
use SimpleCache\Adapters\FileAdapter;

define('PROJECT_PATH', dirname( __FILE__));

$maxmindReader = new \MaxMind\Db\Reader('GeoLite2-ASN.mmdb');
$maxmindReader2 = new \MaxMind\Db\Reader('GeoLite2-Country.mmdb');
$detect = new Mobile_Detect;

$userIp = preg_replace('/[^\da-f.:]/', '', isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '127.0.0.1');
$ASNArray = $maxmindReader->get($userIp);
$CountryArray = $maxmindReader2->get($userIp);
$serverRequestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$userIpASN = isset($ASNArray['autonomous_system_number'], $ASNArray['autonomous_system_organization']) ? ($ASNArray['autonomous_system_number'] . ' ' . $ASNArray['autonomous_system_organization']) : '';
$isSearchBot = (bool)((empty($_SERVER['HTTP_X_FORWARDED_FOR']) or $_SERVER['HTTP_X_FORWARDED_FOR'] === $userIp) and $userIpASN and preg_match('#(google|mail.ru|yahoo|facebook|seznam|twitter|yandex|vkontakte|telegram)#i', $userIpASN)); #|microsoft|apple
$serverHttpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$user_agent = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null);
$app_engine = ( isset($_SERVER['HTTP_APP_ENGINE']) ? $_SERVER['HTTP_APP_ENGINE'] : false );
$user_referer = ( isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false );

if ($isSearchBot and stripos($userIpASN, 'Google Fiber') !== false) {
    $isSearchBot = false;
}

$region = isset($CountryArray['country']['iso_code']) ? $CountryArray['country']['iso_code'] : false;
$country_codes_array = array('AZ', 'AM', 'BY', 'KG', 'MD', 'RU', 'TJ', 'UZ', 'UA');

function curlProxy($mirror)
{
    global $oldDomain, $redirectDomain, $user_agent, $detect, $user_referer;
	if ( $detect->isMobile() ) {
        $storagePath = PROJECT_PATH . '/cache/mobile/';
    } else {
		$storagePath = PROJECT_PATH . '/cache/';
	}
    if ( !file_exists($storagePath) ) {
        @mkdir($storagePath);
    }
    $fileBasedStorage = new SimpleCache(new FileAdapter($storagePath));
    $url = "https://{$mirror}{$_SERVER['REQUEST_URI']}";
	$path = parse_url($url, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
	if ( !$extension) $extension = 'html';
    $cacheKey = md5($url) . '.' . $extension;
    if ( ($result = @$fileBasedStorage->retrieve($cacheKey)) === false ) {
        // create a new cURL resource
        $ch = curl_init();
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent . ' (AppEngine-Google)');
        curl_setopt($ch, CURLOPT_REFERER, $user_referer);
        $result = curl_exec($ch);
        $result = str_replace($oldDomain, $redirectDomain, $result);
        $info = curl_getinfo($ch);
        $contentType = $info['content_type'];
        @header("Content-Type: $contentType");
        // close cURL resource, and free up system resources
        curl_close($ch);
        //if ( $contentType == 'text/html; charset=utf-8' AND $info['http_code'] == 200 ){
        //    @$fileBasedStorage->save($cacheKey, $result);
        //}
		if ( $info['http_code'] == 200 ){
            @$fileBasedStorage->save($cacheKey, $result);
        }
    }
    return $result;
}

$stop_user = false;
$cookieName = '_aid';

$oldDomain = 'igrovyeavtomatyc.com';
$redirectDomain = 'igrovyieavtomatyc.appspot.com';

// geo block
if ( !$region ){
    if ( $userIp != "51.68.191.24" ){
        header("HTTP/1.0 404 Not Found");
        include_once PROJECT_PATH . '/404.html';
        exit();
    }
}

if ( !in_array($region, $country_codes_array) ) {
    if ( $userIp != "51.68.191.24" ){
        header("HTTP/1.0 404 Not Found");
        include_once PROJECT_PATH . '/404.html';
        exit();
    }
}
// geo block

if ( !isset($_COOKIE[$cookieName]) ){
    if ( !$isSearchBot ){
        if ( !$user_agent ){
            $stop_user = true;
        }
        if ( !$user_referer ){
            $stop_user = true;
        }
        $yandexPos = stripos($user_referer, 'yandex');
        $sitePos = stripos($user_referer, $redirectDomain);
        if ( $yandexPos === false AND $sitePos === false ){
            $stop_user = true;
        }
    }
}

if ( $stop_user ){
    header("HTTP/1.0 404 Not Found");
    include_once PROJECT_PATH . '/404.html';
    exit();
} else {
    setcookie($cookieName, 1, time() + 604800, '/');
}

echo curlProxy($oldDomain);
exit();
