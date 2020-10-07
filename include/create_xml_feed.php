<?php 
 class CreateXmlFeed {
    public function __construct($file ='') {
        $this->dom = new DOMDocument("1.0");
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;

        if($file) {
            $this->dom->load($file);
            $this->ofers = $this->dom->getElementsByTagName('offers')->item(0);
        }

        $this->filds = [
            'url',
            'master-id',
            'sku',
            'name',
            'price',
            'orig-price',
            'gender',
            'vendor',
            'color',
            'category',
            'categories',
            'pictures',
            'local-images',
            'description',
            'description2',
            'params',
        ];

    }

    public function setTemplate($file) {
        $this->dom->load($file);
        $this->ofers = $this->dom->getElementsByTagName('offers')->item(0);
    }

    public function saveXml($fileName) {
        //$path =  PARSER_PATCH . '/' . $fileName;
        $this->dom->loadXML( $this->dom->saveXML());
        $this->dom->save($fileName);
    }

    public function addProduct($product) {
        //print_r($product);die;
        $offer = $this->dom->createElement("offer");
        $availableAttr = $this->dom->createAttribute('available');
        $availableAttr->value = $product['is_available'];
        $offer->appendChild($availableAttr);


        foreach($this->filds as $field) {
            if($field == 'pictures' || $field == 'local-images') {
                $elemName = 'picture';
                if($field == 'local-images') {
                    $elemName = 'local-image';
                }

                $elements = $product[$field];
                foreach($elements as $element) {
                    $offer->appendChild($this->dom->createElement($elemName, $element));
                }
            } elseif ($field == 'params') {
                $params = $product['params'];
                foreach($params as $p_name=>$valuesArr) {
                    foreach($valuesArr as $p_value) {
                        $nameAttribute = $this->dom->createAttribute('name');
                        $nameAttribute->value = $p_name;

                        $param = $this->dom->createElement('param', $p_value);
                        $param->appendChild($nameAttribute);

                        $offer->appendChild($param);
                    }
                }
            
            } elseif ($field == 'description' || $field == 'description2') {
                $cdata = $this->dom->createCDATASection($product[$field]);
                $cdataElem = $this->dom->createElement($field);
                $cdataElem->appendChild($cdata);
                $offer->appendChild( $cdataElem);
            } else {
                $offer->appendChild($this->dom->createElement($field, htmlspecialchars($product[$field])));
            }
            
        }

        $this->ofers->appendChild($offer);
        
    }

    public function addProducts($productsArr) {
       
        if(!$productsArr) {
            return false;
        }

        foreach($productsArr as $product)  {
            $this->addProduct($product);
        }
    }

 }