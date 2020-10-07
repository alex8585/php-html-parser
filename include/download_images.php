<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

class DownloadImages {
    public function __construct($xmlFileName) {
        //$this->xmlFilePath = PARSER_PATCH . DS . $xmlFileName;
        $this->xmlFile = file_get_contents($xmlFileName);

        $this->dom = new DOMDocument("1.0");
        $this->dom->loadXML($this->xmlFile );

        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('DownloadImages');
        $this->log->pushHandler($stream);

    }

    public function download() {
        //$wpUploadDir = wp_get_upload_dir();
        //$dirPath = $wpUploadDir['basedir'] . DS . WP_ALL_IMPORT_FILES_DIRECTORY . DS;

        $dirPath = PARSER_PATCH . '/images/';
        if(!file_exists($dirPath)) {
            mkdir($dirPath, 0755);
        }
        $ofers = $this->dom->getElementsByTagName('offer');

        if($ofers->length == 0) {
            $this->log->error('There are no images in xml file to download');
            return;
        }

        foreach($ofers as $ofer) {
            $pictures = $ofer->getElementsByTagName('picture');
            foreach($pictures as $picture) {
                $url = $picture->nodeValue;
                $urlArr = explode('/', $url);
                $fileName = array_pop($urlArr);
                $path  = $dirPath .  $fileName;

                if (!file_exists($path)) {
                    $this->log->info('Download new image:',(array)$url);
                    echo 'Download new image: '. $url . PHP_EOL;
                    $file = file_get_contents( $url);
                    file_put_contents($path, $file );
                }
            }
        }

    }

    

    


   
       

}