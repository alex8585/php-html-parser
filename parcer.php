<?php

if(php_sapi_name() !== 'cli')
    die();

//ob_start();

//require( dirname(dirname( __FILE__ )) . '/wp-load.php' );
//require_once(ABSPATH . 'wp-admin/includes/image.php');
//require_once(ABSPATH . 'wp-admin/includes/file.php');
//require_once(ABSPATH . 'wp-admin/includes/media.php');

//ob_end_clean();

define('DS', DIRECTORY_SEPARATOR);
require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

require_once __DIR__ . '/include/http_client.php';
require_once __DIR__ . '/include/string_parser.php';
require_once __DIR__ . '/include/urls_parser.php';
require_once __DIR__ . '/include/config.php';



require_once __DIR__ . '/include/html_parser.php';
require_once __DIR__ . '/include/html_parser2.php';
require_once __DIR__ . '/include/html_parser3.php';
require_once __DIR__ . '/include/html_parser4.php';

require_once __DIR__ . '/include/create_xml_feed.php';
require_once __DIR__ . '/include/download_images.php';





define('PARSER', 4);
define('XML_FILE_NAME', 'feed4.xml');


define('PARSER_PATCH', __DIR__);
define('URLS_THREADS', 1);
define('PRODUCTS_THREADS', 1);
define('LOG_FILE_PATH', PARSER_PATCH . DS . 'log.txt');
define('XML_FILE_PATCH', PARSER_PATCH . DS . XML_FILE_NAME );

define('PARSE_URLS', false);
define('PARSE_PRODUCTS', true);
define('SAVE_XML', true);
define('DOWNLOAD_IMAGES', false);



file_put_contents(LOG_FILE_PATH,'');
$dateFormat = "Y-m-d H:i:s";
$output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
$formatter = new LineFormatter($output, $dateFormat);
$stream = new StreamHandler(LOG_FILE_PATH );
$stream->setFormatter($formatter);
$log = new Logger('main');
$log->pushHandler($stream);



$conf = new Config();
$url = $conf->get('parcer.url');


$log->info('===PARSER START===');
$start = microtime(true);

$client = new HttpClient();
$urlsParcer = new UrlsParser($client, $url);


if(PARSE_URLS) {
    $log->info('PARSE_URLS start');
    $urlsParcer->fetchCategoriesUrls();
    $urlsParcer->fetchProductsUrls();
    $log->info('PARSE_URLS end');
}



if(PARSE_PRODUCTS) {
    $productsUrls = $urlsParcer->getProductsUrls();

    $log->info("PARSE_PRODUCTS PARSER ". PARSER. " start");
    if(PARSER == 1) {
        $hParcer = new HtmlParser($client);
    } elseif(PARSER == 2) {
        $hParcer = new HtmlParser2($client);
    } elseif(PARSER == 3) {
        $hParcer = new HtmlParser3($client);
    }elseif(PARSER == 4) {
        $hParcer = new HtmlParser4($client);
    }
    $productsArr = $hParcer->getProducts($productsUrls );
    $log->info("PARSE_PRODUCTS PARSER ". PARSER . " end");

}



if(SAVE_XML) {
    $log->info('SAVE_XML start');
    if($productsArr) {
        $createXml =  new CreateXmlFeed();
        $createXml->setTemplate(PARSER_PATCH . DS . 'template.xml');
        $createXml->addProducts($productsArr);
        $createXml->saveXml(XML_FILE_PATCH);
    } else{
        $log->error('productsArr is empty');
    }
    $log->info('SAVE_XML end');
}



if(DOWNLOAD_IMAGES) {
    $log->info('DOWNLOAD_IMAGES start');
    $di = new DownloadImages(XML_FILE_PATCH);
    $di->download();
    $log->info('DOWNLOAD_IMAGES end');
}



$time = microtime(true) - $start;
echo $time ;
$log->info('===PARSER END===');