<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

class HtmlParser2{
    public function __construct($client) {
        $this->client = $client;
        $this->sp = new StringParcer();

        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('HtmlParser2');
        $this->log->pushHandler($stream);

    }

    public function addProductCats($productArr) {
        $sp = $this->sp;
        $catsArr = $sp->parseStrings($this->cats, '">', '</a>');
        
        $productArr['categories'] = implode($catsArr,'///');
        $catsArr = array_reverse($catsArr);
        $productArr['category'] = $catsArr[0];

        return  $productArr;
    }

    public function addProductImages($productArr) {
        $sp = $this->sp;
        $images = $sp->parseStrings($this->product, '<a class="fancybox" data-fullimage="', '\?');

        if(!$images) {
            $images = $sp->parseStr($this->product, '<span rel="productGallery" id="ProductImage" data-fullimage="', '\?');
            $images = (array)$images;
   
        }

        $productArr['pictures'] = [];
        foreach($images as $img) {
            $urlArr = explode('/', $img);
            $fileName = array_pop($urlArr);

            $productArr['pictures'][]  = $img . '.jpeg';
            $productArr['local-images'][]  = $fileName . '.jpeg';
        }

        $productArr['pictures'] = array_unique( $productArr['pictures']);
        $productArr['local-images'] = array_unique( $productArr['local-images']);
        return  $productArr;
    }

    public function addProductParams($productArr) {
        $sp = $this->sp;
        $attrsSrings1 = $sp->parseStrings($this->product, 'class="selectorButton\s?" data-primary="False" data-attribute-name="', 'data-name', true);
        $attrsSrings2 = $sp->parseStrings($this->product, 'class="radioButtonWrapper\s?" data-primary="False" data-attribute-name="', 'data-name', true);
        
        if(!is_array($attrsSrings1)) {
            $attrsSrings1 = $sp->parseStr($this->product, '<input type="hidden" class="selected" data-primary="False" data-attribute-name="', 'data-name', true);
            $attrsSrings1 = (array)$attrsSrings1;
        }
       

        if(is_array($attrsSrings1) && is_array($attrsSrings2)) {
            $attrsSrings = array_merge($attrsSrings1, $attrsSrings2);
        } elseif(is_array($attrsSrings1)) {
            $attrsSrings = $attrsSrings1;
        } elseif(is_array($attrsSrings2)) {
            $attrsSrings = $attrsSrings2;
        } else {
            $attrsSrings = [];
        }
        
        //print_r( $attrsSrings);die;

        $paramsArr =[];
        foreach($attrsSrings as $attrSring) {
            $p_name =  $sp->parseStr($attrSring, 'data-attribute-name="', '"');
            $p_val = $sp->parseStr($attrSring, 'data-value="', '"' );
            $paramsArr[$p_name][] = $p_val;
        }

        $productArr['params'] = $paramsArr;
        return $productArr;
    }

    
    public function getProduct($url, $html) {
        $sp = $this->sp;
        //$html = file_get_contents('./f1.txt');
       
        $this->product = $sp->parseStr($html, '<section id="ProductDetails"', '</section>', true);
        $this->cats = $sp->parseStr($html, '<nav>', '</nav>');

        if(!$this->product) {
            $this->log->error('product not found',(array)$url);
            var_dump($url);
            return [];
        }

        $productArr = [];

        $product =  $this->product;

      
        $productArr['url'] = $url;    
       
        $productArr['is_available'] = $sp->parseStr($product, '<input type="hidden" name="IsAvailable" value="', '"');
        $productArr['master-id'] = $sp->parseStr($product, '<input type="hidden" name="MasterID" value="', '"');
        $productArr['sku'] =  $sp->parseStr($product, '<span id="Sku">', '</span>');
        $productArr['name'] = $sp->parseStr($product, '<h1 itemprop="name">', '</h1>');
        $productArr['name'] = str_replace('&#39;',"'",$productArr['name']);


        $productArr['price'] = $sp->parseStr($product, '<span itemprop="price">', '</span>');


        $orig_price = $sp->parseStr($product, '<span class="origPrice">  Orig. <span>', '</span>');
       
        if($orig_price) {
            $orig_price = strip_tags($orig_price);
            $productArr['orig-price'] = trim(trim($orig_price ),'$');
        } else {
            $productArr['orig-price'] =  $productArr['price'];
        }
        
        

        $productArr['gender'] =  $sp->parseStr($product, '<input type="hidden" name="Gender" value="', '"');
        $productArr['vendor'] = $sp->parseStr($product, '<input id="Brand" name="Brand" type="hidden" value="', '"');
        $productArr['color'] = $sp->parseStr($product, '<span id="DisplayColor">', '</span>');
        
        
        $productArr = $this->addProductCats($productArr);

      
        $productArr = $this->addProductImages($productArr);

       

        $d1 = $sp->parseStr($product, '<p class="productFeaturesDetails" itemprop="description">', '</p>');
        $productArr['description'] = trim($d1);
        
        $d2 = $sp->parseStr($product, '<ul class="productFeaturesDetails">', '</ul>', true);
        $productArr['description2'] = '';
        if($d2) {
            $productArr['description2']= trim($d2);
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
            if(!$url) continue;

            $this->log->info('get product:',(array)$url);
            echo $url . PHP_EOL;
            $html = $this->client->getContents($url);
            if(!$html) continue;
            $product = $this->getProduct($url, $html);
            if($product) {
                $productsArr[] = $product;
            }
           
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
                    $product = $this->getProduct($url, $html);
                    if($product) {
                        $productsArr[] = $product;
                    }
                }
               
            }
        }
        return $productsArr;
    }

}