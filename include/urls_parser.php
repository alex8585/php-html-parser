<?php
class UrlsParser {
    public function __construct($client, $pageUrl) {
        $this->client = $client;
        $this->dom = new DOMDocument("1.0");
        $this->catsFile = PARSER_PATCH . '/cat_urls.txt';
        $this->productsFile = PARSER_PATCH . '/products_urls.txt';
        $this->pageUrl = $pageUrl;
    }

    public function getProductsUrls() {
        $productsUrlsFile = trim(file_get_contents($this->productsFile),"\n");
        $productsUrls = explode("\n", $productsUrlsFile);
        return $productsUrls;
    }

    public function fetchCategoriesUrls() {
        libxml_use_internal_errors(true);
        $html = $this->client->getContents( $this->pageUrl);
    
        $this->dom->loadHTML($html);
        $this->xDom = new DOMXpath($this->dom);

        $urls = $this->xDom->query("//div[@class='subNavCat']/div/a");
       
        $urlsArr = [];
        foreach($urls as $url) {
            //$title = $url->getAttribute('title');
           $urlsArr[] =  $this->pageUrl . $url->getAttribute('href');
        }
        file_put_contents($this->catsFile, implode("\n",$urlsArr));
    }

    public function fetchProductsUrls() {
        if(URLS_THREADS > 1) {
            $this->fetchProductsUrlsMulti(URLS_THREADS);
        } else {
            $this->fetchProductsUrlsOneThread();
        }
    }

    public function fetchProductsUrlsOneThread() {
        $catsUrlsFile = trim(file_get_contents($this->catsFile), "\n");
        $catsUrls = explode("\n", $catsUrlsFile);

        $productsUrlsArr = [];
        foreach($catsUrls as $catUrl) {
            $page = 1;
            while($page < 100) {
                $url = $catUrl . "?PageSize=60&Page=$page";
                $html = $this->client->getContents($url);
                $this->dom = new DOMDocument("1.0");
                libxml_use_internal_errors(true);
                $this->dom->loadHTML($html);
                $this->xDom = new DOMXpath($this->dom);

                $productsUrls = $this->xDom->query("//section[@id='Items']/descendant::figcaption/a/@href");
                
                if(!$productsUrls->length) {
                    break;
                }

                foreach($productsUrls as $pUrl) {
                    $productsUrlsArr[] =  $this->pageUrl . $pUrl->nodeValue;
                }
                $page++;
               
            }
        }
        file_put_contents($this->productsFile, implode("\n",$productsUrlsArr));   
    }  


    public function fetchProductsUrlsMulti($threads) {
        $catsUrlsFile = trim(file_get_contents($this->catsFile), "\n");
        $catsUrls = explode("\n", $catsUrlsFile);
       
        $productsUrlsArr = [];
        foreach($catsUrls as $catUrl) {
            if(empty($catUrl)) break;
            $page = 1;
            $isCategoryEnd = false;
            while($page < 100) {
                if($isCategoryEnd) break;
        
                $urlsArr = [];
                for($i = $page; $i < $page + $threads; $i++) {
                    $url = $catUrl . "?PageSize=60&Page=$i";
                    $urlsArr[] = $url;
                    
                }
                $page += $threads;
               // print_r( $urlsArr);
                
                $htmlArr = $this->client->getMulti($urlsArr);
                foreach($htmlArr as $html) {
                    $this->dom = new DOMDocument("1.0");
                    libxml_use_internal_errors(true);
                    $this->dom->loadHTML($html);
                    $this->xDom = new DOMXpath($this->dom);

                    $productsUrls = $this->xDom->query("//section[@id='Items']/descendant::figcaption/a/@href");
                    
                    if(!$productsUrls->length) {
                        $isCategoryEnd = true;
                        break;
                    }

                    foreach($productsUrls as $pUrl) {
                        $productsUrlsArr[] =  $this->pageUrl . $pUrl->nodeValue;
                    }
                }
                
            }
        }

        file_put_contents($this->productsFile, implode("\n",$productsUrlsArr));
           
    }
       

}