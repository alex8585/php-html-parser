<?php
use GuzzleHttp\Client as Client;
use GuzzleHttp\Promise as Promise;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

class HttpClient {
    public function __construct() {
        $userAgentsArr = [
            '0' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:35.0) Gecko/20100101 Firefox/35.0',
            '1' => 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http:',
            '2' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
            '3' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:74.0) Gecko/20100101 Firefox/74.0',
        ];
        
        $this->client = new Client([
            'http_errors' => false,
            'headers' => [
                'User-Agent' => $userAgentsArr[3]
        
            ]
        ]);


        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('HttpClient');
        $this->log->pushHandler($stream);
   
    }

    public function getContents($url) {
        if(!$url) {
            return '';
        }
        
        $res = $this->client->request('GET', $url);
        $content = $res->getBody()->getContents();

        if($res->getStatusCode() !== 200) {
            $this->log->error('page not found',(array)$url);
            return '';
        }
       
        //print_r($content);die;
        /*$response = wp_remote_get($url);
        $content     = wp_remote_retrieve_body( $response );
        print_r($content);die;*/
        return $content;
    }


    public function getMulti($urlsArr) {
      
        $promises = [];
        foreach ($urlsArr as $url) {
            if($url) {
                $promises[$url] = $this->client->getAsync($url);
            }
           
        }
        $results = Promise\settle($promises)->wait();

        $returnArr = [];
        foreach ($results as $url => $result) {
            if ($result['state'] === 'fulfilled') {
                $response = $result['value'];
                if ($response->getStatusCode() == 200) {
                    $returnArr[$url] = $response->getBody()->getContents();
                } else {
                    $returnArr[$url] = '';
                }
            } else if ($result['state'] === 'rejected') { 
                $returnArr[$url] = '';
            } else {
                $returnArr[$url] = '';
            }
        }
        return $returnArr;
    }
}