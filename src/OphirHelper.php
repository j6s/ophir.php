<?php
/**
 * Created by PhpStorm.
 * User: thephpjo
 * Date: 11/22/14
 * Time: 7:15 PM
 */

namespace lovasoa\ophir;

/**
 * Here you can find all sorts of Helpers for Ophir.
 * They were moved to this class, to keep the main Ophir class
 * clutter-free and easy to read
 *
 * Rules:
 *  - Every function has to be public static, so it can be called via OphirHelper::myFunction
 *  - If a helper needs to access the ophir instance, it should be the first argument
 *
 * Class OphirHelper
 * @package lovasoa\ophir
 */
abstract class OphirHelper {

    /**
     * Stupid old PHP doesn't let me set arrays as constants
     * @var array
     */
    public static $CSSSTYLES = array(
        'width',
        'height',
        'border',
        'border-left',
        'border-right',
        'border-bottom',
        'border-top',
        'margin',
        'padding',
        'font-style',
        'font-weight',
        'text-underline-style' => array(
            'text-decoration' => 'underline'
        ),
    );

    public static $CLASSSELECTORS = array(
        'text:style-name',
        'table:style-name'
    );


    /**
     * Returns DOMText from an XML String.
     * Discards everything within other Tags
     *
     * @param string $xmlString
     * @return string
     */
    public static function getDOMTextFromXMLString($xmlString){
        $dom = new \DOMDocument();
        $dom->loadXML('<ophir>'.$xmlString.'</ophir>');

        $text = array();

        foreach($dom->childNodes->item(0)->childNodes as $child){
            /** @var \DOMNode $child */
            if(get_class($child) === "DOMText"){
                $text[] = $child->textContent;
            }
        }

        return implode(" ",$text);
    }


    /**
     * Returns the Image source parameter from an draw:image Element
     *
     * @param Ophir $ophir
     * @param \XMLReader $xml
     * @return string
     */
    public static function getImageSource($ophir,$xml){
        $image_file = 'zip://' . $ophir->getFile() . '#' . $xml->getAttribute("xlink:href");

        $config = $ophir->getConfiguration();

        if(isset($config[Ophir::IMAGEFOLDER])){
            if(!is_dir($config[Ophir::IMAGEFOLDER])){
                mkdir($config[Ophir::IMAGEFOLDER]);
            }

            $path = $config[Ophir::IMAGEFOLDER]."/".basename($image_file);
            if(!file_exists($path)){
                copy($image_file,$path);
            }

            return $path;
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo,$image_file);

            return "data:{$mime};base64," . base64_encode(file_get_contents($image_file));
        }
    }

    /**
     * Parses styles given by a text:list-style Element
     *
     * @param \XMLReader    $xml
     * @param Ophir         $ophir
     * @return void
     */
    public static function getListStyle($ophir,$xml){
        $class = ".".$ophir->getClassPrefix().$xml->getAttribute("style:name");
        $style = $ophir->getStyles();

        $dom = new \DOMDocument();
        // xml needs a single parent node
        $dom->loadXML('<ophir>'.$xml->readInnerXml().'</ophir>');


        foreach($dom->childNodes->item(0)->childNodes as $child){
            /** @var \DOMNode $child */
            switch($child->nodeName){
                case "text:list-level-style-number":
                    $style->addStyle($class,'list-style-type','decimal');
                    break;
                case "text:list-level-style-bullet":
                    $style->addStyle($class, 'list-style-type','bullet');
                    break;
            }
            $level = (int)$child->attributes->getNamedItem('level')->value;

            // we assume, that the text properties are the second element
            $textProperties = $child->childNodes->item(1)->attributes;
            if($textProperties !== NULL){
                $color =    $textProperties->getNamedItem('color')->value;
                $fontSize = $textProperties->getNamedItem('font-size')->value;

                $selector = str_repeat($class. " ",$level);
                $style->addStyle($selector,'color',$color);
                $style->addStyle($selector,'font-size',$fontSize);
            }
        }
    }

    /**
     * Prints a simple DOMNode structure - for debugging purposes only
     *
     * @param \DOMNode $domnode
     */
    public static function DOMNodePrinter($domnode,$depth = 0){
        $prefix = str_repeat("  ",$depth);
        $content = $domnode->nodeName;

        switch($domnode->nodeName){
            case "#text":
                $content .= " => ".$domnode->nodeValue;
        }

        echo $prefix.$content."<br/>";

        foreach($domnode->childNodes as $child){
            OphirHelper::DOMNodePrinter($child,$depth+1);
        }
    }

    /**
     * Parses styles given by a style:style Element
     *
     * @param Ophir         $ophir
     * @param \XMLReader    $xml
     */
    public static function getStyleStyle($ophir,$xml){
        $styles = $ophir->getStyles();
        $dom = new \DOMDocument();
        // xml needs a single parent node
        $dom->loadXML('<ophir>'.$xml->readInnerXml().'</ophir>');

        $selector = $ophir->getClassPrefix().$xml->getAttribute('style:name');
        $selector = ".".OphirHelper::normalizeClass($selector);

        foreach($dom->childNodes->item(0)->childNodes as $child){
            /** @var \DOMNode $child */
            foreach(self::$CSSSTYLES as $style){
                $item = $child->attributes->getNamedItem($style);
                if($item !== NULL){
                    $styles->addStyle($selector,$style,$item->nodeValue);
                }
            }
        }
    }

    /**
     * Normalizes classes - removes unwanted characters
     *
     * @param   string $class
     * @return  string
     */
    public static function normalizeClass($class){
        return str_replace(array('.','#',':'),'-',$class);
    }

    /**
     * gets Classes from any Element. See OphirHelper::CLASSSELECTORS to define
     * the attributes to get the classes from
     *
     * @param Ophir $ophir
     * @param \XMLReader $xml
     * @return string
     */
    public static function extractClasses($ophir,$xml){
        $classes = array();
        $prefix = $ophir->getClassPrefix();

        foreach(self::$CLASSSELECTORS as $classSelector){
            $class = $xml->getAttribute($classSelector);
            if(!empty($class)){
                $classes[] = $prefix.self::normalizeClass($class);
            }
        }
        return implode(" ",$classes);
    }
} 