<?php
/**
 * Created by PhpStorm.
 * User: thephpjo
 * Date: 11/22/14
 * Time: 6:59 PM
 */

namespace lovasoa\ophir;


class OphirStyles {
    /**
     * @var array
     */
    private $styles = array();

    /**
     * @param string $selector
     * @param string $property
     * @param string $value
     */
    public function addStyle($selector,$property,$value){
        if(!is_array($this->styles[$selector])){
            $this->styles[$selector] = array();
        }
        $this->styles[$selector][$property] = $value;
    }

    /**
     * @param string        $selector
     * @param string|null   $property
     */
    public function removeStyle($selector,$property = NULL){
        if($property === NULL){
            // remove all styles of that selector
            unset($this->styles[$selector]);
        } else {
            unset($this->styles[$selector][$property]);
        }
    }

    /**
     * @return string
     */
    public function getCss(){
        $style = "";
        foreach($this->styles as $selector=>$properties){
            $style .= $selector . '{';
            foreach($properties as $property=>$value){
                $style .= $property.":".$value.";";
            }
            $style .= '}';
        }
        return $style;
    }
} 