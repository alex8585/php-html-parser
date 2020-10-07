<?php 
class StringParcer {
    public function __construct($str = '') {
        $this->str = $str;
    }

    public function setString($str) {
        $this->str = $str;
    }

    public function parseStr($str='', $start='', $stop='', $outer= false) {

        $str = preg_replace('/\s+/', ' ', $str);
        $start = preg_replace('/\s+/', ' ', $start);
        $stop = preg_replace('/\s+/', ' ', $stop);

        $pattern = '#' . $start . '(.*?)' . $stop . '#i'; 
        
        //print_r($str);
        preg_match($pattern, $str, $matches);
        
        $result = '';
        if($outer) {
            if(!empty($matches[0])) {
                $result = $matches[0];
            }
        } else {
            if(!empty($matches[1])) {
                $result = $matches[1];
            }
        }
        
        //print_r($matches);
        return $result;
    }

    public function parseStrings($str='', $start='', $stop='', $outer= false) {

        $str = preg_replace('/\s+/', ' ', $str);
        $start = preg_replace('/\s+/', ' ', $start);
        $stop = preg_replace('/\s+/', ' ', $stop);

        $pattern = '#' . $start . '(.*?)' . $stop . '#i'; 
        
        //print_r($str);
        preg_match_all($pattern, $str, $matches);
        
        $result = '';
        if($outer) {
            if(!empty($matches[0])) {
                $result = $matches[0];
            }
        } else {
            if(!empty($matches[1])) {
                $result = $matches[1];
            }
        }
        
        //print_r($matches);
        return $result;
    }


}