<?php
class Config {

    public function __construct() {
        $this->conf = include PARSER_PATCH . DS . 'conf.php';
        $this->dot = new \Adbar\Dot($this->conf );
    }

    public function get($k) {
        return $this->dot->get($k);
    }

}
