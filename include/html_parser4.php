<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Utils;
use Monolog\Formatter\LineFormatter;
use DiDom\Document;

class HtmlParser4{
    public function __construct($client) {
        $this->client = $client;

        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%   %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler(LOG_FILE_PATH );
        $stream->setFormatter($formatter);
        $this->log = new Logger('HtmlParser4');
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

    public function addProductImages($product, $productArr) {

        $images = $product->xpath("//a[@class='fancybox']");
        
        if(!$images) {
            $images = $product->xpath("//span[@id='ProductImage']");
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

    public function addProductParams($product, $productArr) {
       
        $params = $product->xpath("//*[@data-attribute-name]");
      
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
        //print_r($productArr['params']);
        return $productArr;
    }

    

    public function getProduct($url, $html) {
       
       
        // $this->dom =  new DOMDocument("1.0");
        // libxml_use_internal_errors(true);
        // $this->dom->loadHTML($html);
        
     
        $document = new Document($html);
        
       
       
       
        //print_r( $product); die;

        // $this->product =  $this->dom->getElementById('ProductDetails');
        // $this->cats = $this->dom->getElementById ('Breadcrumbs')->text();
        
        $product = $document->first('#ProductDetails');
        $this->cats = $document->first('#Breadcrumbs')->text();
      

        if(!$product) {
            $this->log->error('product not found',(array)$url);
            var_dump($url);
            return [];
        }

       
        $productArr = [];

        // $newDom = new DomDocument;
        // $newDom->appendChild($newDom->importNode($this->product, true));
        // $xProduct = new DOMXpath($newDom);
        
        $productArr['url'] = $url;    
       
        $productArr['is_available'] = $product->first("[name='IsAvailable']")->attr('value');
        
       
        $productArr['master-id'] = $product->first("[name='MasterID']")->attr('value');

       
       
        $productArr['sku'] = $product->first("#Sku")->text();

      



        $productArr['name'] = $product->first("[itemprop='name']")->text();

       
     


        $productArr['price'] = $product->first("[itemprop='price']")->text();

       
        

        $productArr['orig-price'] =  $productArr['price'];
        

        

        

        if($orig_price =  $product->first(".origPrice span")) {
            
            $productArr['orig-price'] = trim(trim($orig_price->text() ),'$');
           
        }
       
       
        
        $productArr['gender'] = $product->first("[name='Gender']")->attr('value');

       
       


        $productArr['vendor']= $product->first("#Brand")->attr('value');

        

        $productArr['color'] = '';
        $color = $product->first("#DisplayColor")->text();
        if($color) {
            $productArr['color']= trim($color);
        }

      


        $productArr = $this->addProductCats($productArr);

        $productArr = $this->addProductImages($product, $productArr);
      

        $productArr['description'] = '';
        

        $d1 = $product->first("p[itemprop='description']");
       
        if($d1) {
            $productArr['description']= preg_replace('/\s+/', ' ',  trim($d1->text())) ; 
        }
        
       


        $productArr['description2'] = '';
        $d2 = $product->first("div.selfClearAfter.detailsItems.productFeatures.features ul");
        if($d2) {
            $productArr['description2'] = preg_replace('/\s+/', ' ',  trim($d2)) ;
        }

       


        $productArr = $this->addProductParams($product, $productArr);

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