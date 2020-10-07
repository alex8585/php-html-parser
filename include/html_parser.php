<?php

use PHPHtmlParser\Dom;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

class HtmlParser{
    public function __construct($client) {
        $this->client = $client;

        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('HtmlParser');
        $this->log->pushHandler($stream);
    }

    public function addProductCats($productArr) {
        $cats = $this->cats;
        $catsArr = [];

        foreach ($cats  as $cat) {
            $catsArr[]= $cat->innerHtml;
        }

        $productArr['categories'] = implode($catsArr,'///');
        $catsArr = array_reverse($catsArr);
        $productArr['category'] = $catsArr[0];

        return  $productArr;
    }

    public function addProductImages($productArr) {
        $images = $this->product->find('[data-fullimage]');

        $productArr['pictures'] = [];
        foreach($images as $img) {
            $imgStr = trim($img->getAttribute('data-fullimage'));

            $url = explode('?', $imgStr)[0];
            $urlArr = explode('/', $url);
            $fileName = array_pop($urlArr);

            $productArr['pictures'][]  = $url . '.jpeg';
            $productArr['local-images'][]  = $fileName . '.jpeg';
        }

        $productArr['pictures'] = array_unique( $productArr['pictures']);
        $productArr['local-images'] = array_unique( $productArr['local-images']);
        return  $productArr;
    }

    public function addProductParams($productArr) {
        $params = $this->product->find('[data-attribute-name]');
        $paramsArr =[];
        foreach($params as $param) {
            $p_name = $param->getAttribute('data-attribute-name');
            $p_val = $param->getAttribute('data-value');
            $paramsArr[$p_name][] = $p_val;
        }

        $productArr['params'] = $paramsArr;
        return $productArr;
    }

    

    public function getProduct($url, $html) {
       
        $this->dom =  new Dom();
        $this->dom->loadStr($html);

        $this->product =  $this->dom->find('#ProductDetails');
        $this->cats = $this->dom->find('form nav ul li a');
        
        
        if(!$this->product->count()) {
            var_dump($url);
            return [];
        }

        $productArr = [];

        $product =  $this->product;

      
        $productArr['url'] = $url;    
        $productArr['is_available'] = trim($product->find('[name="IsAvailable"]')->getAttribute('value'));

        $productArr['master-id'] = trim($product->find('[name="MasterID"]')->getAttribute('value'));
        $productArr['sku'] = trim(($product->find('#Sku')->innerHtml));
        $productArr['name'] = trim($product->find('[itemprop="name"]')->innerHtml);
        $productArr['name'] = str_replace('&#39;',"'",$productArr['name']);
        //print_r($productArr['name']); die;

        $productArr['price'] = trim($product->find('[itemprop="price"]')->innerHtml);

        try {
            $orig_price =  (strip_tags($product->find('.origPrice span')->innerHtml));
            $productArr['orig-price'] = trim(trim($orig_price ),'$');
        } catch (Exception $e) {
            $productArr['orig-price'] =  $productArr['price'];
        }

        
        
        $productArr['gender'] = trim($product->find('[name="Gender"]')->getAttribute('value'));
        $productArr['vendor']= trim($product->find('#Brand')->getAttribute('value'));
        
        $productArr['color'] = '';
        $colorObj = $product->find('[id="DisplayColor"]');
        if($colorObj->count()) {
            $productArr['color']= trim($colorObj->innerHtml);
        }
       

        $productArr = $this->addProductCats($productArr);

        $productArr = $this->addProductImages($productArr);
        

        $productArr['description'] = '';
        $d1 = $product->find('p[itemprop="description"]')[0];
        if($d1) {
            $productArr['description']= trim($d1->innerHtml);
        }
       

        $d2 = $product->find('div.selfClearAfter.detailsItems.productFeatures.features')[0];
        $productArr['description2'] = '';
        if($d2) {
            $productArr['description2']= trim($d2->innerHtml);
        }
       

        $productArr = $this->addProductParams($productArr);

        
        return $productArr;
    }

    public function getProducts($productsUrls) {
        if(PRODUCTS_THREADS >1) {
            $productsArr = $this->getProductsMulti($productsUrls, PRODUCTS_THREADS);
        } else {
            $productsArr = $this->getProductsOneTread($productsUrls);
        }
        return $productsArr;
    }

    public function getProductsOneTread($productsUrls) {
        $productsArr = [];
        $i = 0;
        foreach($productsUrls as $url) {
            $this->log->info('get product:',(array)$url);
            echo $url . PHP_EOL;
            $html = $this->client->getContents($url);
            $productsArr[] = $this->getProduct($url, $html);
            $i++;
        }
        
        return $productsArr;
    }


    public function  getProductsMulti($productsUrls, $threads) {
        
        $productsUrls = array_chunk($productsUrls, $threads);
       
        $productsArr = [];
        $i = 0;
        foreach($productsUrls as $k=>$prodUrlArr) {
            $this->log->info('get product:',$prodUrlArr);
            print_r($prodUrlArr) . PHP_EOL;
            $i++;
            $htmlArr = $this->client->getMulti($prodUrlArr);
            foreach($htmlArr as $url=>$html) {
                if($html) {
                    $productsArr[] = $this->getProduct($url, $html);
                }
               
            }
        }
        return $productsArr;
    }

    

}