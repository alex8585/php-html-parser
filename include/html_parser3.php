<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;

class HtmlParser3{
    public function __construct($client) {
        $this->client = $client;

        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('HtmlParser3');
        $this->log->pushHandler($stream);
    }

    public function addProductCats($productArr) {
        $cats = $this->cats;
        $catsArr = explode('/',$cats);
        array_pop($catsArr);
        foreach($catsArr as &$cat) {
            $cat = trim($cat);
        }
        unset($cat);

        $productArr['categories'] = implode($catsArr,'///');
        $catsArr = array_reverse($catsArr);
        $productArr['category'] = $catsArr[0];
       
        return  $productArr;
    }

    public function addProductImages($xProduct, $productArr) {

        $images = $xProduct->query("//a[@class='fancybox']");
       
        if($images->length == 0) {
            $images = $xProduct->query("//span[@id='ProductImage']");
        }

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

    public function addProductParams($xProduct, $productArr) {
       
        $params = $xProduct->query("//*[@data-attribute-name]");
        
        $paramsArr =[];
        foreach($params as $param) {
            $p_name = $param->getAttribute('data-attribute-name');
            $p_val = $param->getAttribute('data-value');
            $paramsArr[$p_name][] = $p_val;
        }
        
        foreach($paramsArr as $k=>&$vAar) {
            $vAar = array_unique( $vAar);
        }

        $productArr['params'] = $paramsArr;
        return $productArr;
    }

    

    public function getProduct($url, $html) {
       
       
        $this->dom =  new DOMDocument("1.0");
        libxml_use_internal_errors(true);
        $this->dom->loadHTML($html);

     

        $this->product =  $this->dom->getElementById('ProductDetails');
        $this->cats = $this->dom->getElementById ('Breadcrumbs')->nodeValue;
        
       

        if(!$this->product) {
            $this->log->error('product not found',(array)$url);
            var_dump($url);
            return [];
        }

       
        $productArr = [];

        $newDom = new DomDocument;
        $newDom->appendChild($newDom->importNode($this->product, true));
        $xProduct = new DOMXpath($newDom);
        
        $productArr['url'] = $url;    
       
        $productArr['is_available'] = $xProduct->query("//input[@name='IsAvailable']/@value")->item(0)->nodeValue;
        $productArr['master-id'] = $xProduct->query("//input[@name='MasterID']/@value")->item(0)->nodeValue;
        $productArr['sku'] = $xProduct->query("//span[@id='Sku']")->item(0)->nodeValue;
        $productArr['name'] = ($xProduct->query("//h1[@itemprop='name']")->item(0)->nodeValue);
        $productArr['price'] = $xProduct->query("//span[@itemprop='price']")->item(0)->nodeValue;

        $productArr['orig-price'] =  $productArr['price'];
        
        if($orig_price =  $xProduct->query("//span[@class='origPrice']/span")->item(0)) {
            $productArr['orig-price'] = trim(trim($orig_price->nodeValue ),'$');
        }
        
        $productArr['gender'] = $xProduct->query("//input[@name='Gender']/@value")->item(0)->nodeValue;
        $productArr['vendor']= $xProduct->query("//input[@id='Brand']/@value")->item(0)->nodeValue;

       
        $productArr['color'] = '';
        $color = $xProduct->query("//span[@id='DisplayColor']")->item(0)->nodeValue;
        if($color) {
            $productArr['color']= trim($color);
        }

       
        $productArr = $this->addProductCats($productArr);

        $productArr = $this->addProductImages($xProduct, $productArr);
      

        $productArr['description'] = '';
        

        $d1 = $xProduct->query("//p[@itemprop='description']");
       
        if($d1->length > 0) {
            $productArr['description']= preg_replace('/\s+/', ' ',  trim($d1->item(0)->nodeValue)) ; 
        }


        $productArr['description2'] = '';
        $d2 = $xProduct->query("//div[@class='selfClearAfter detailsItems productFeatures features']");
        if($d2->length > 0) {
            $innerHTML = ''; 
            $children = $d2->item(0)->childNodes;
            foreach ($children as $child) 
                $innerHTML .= $d2->item(0)->ownerDocument->saveHTML($child);

            $productArr['description2'] = preg_replace('/\s+/', ' ',  trim($innerHTML)) ;
        }
        
        $productArr = $this->addProductParams($xProduct, $productArr);

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